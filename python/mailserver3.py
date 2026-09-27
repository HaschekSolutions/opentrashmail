import aiohttp
import asyncio
import ssl
import socket
import threading
from aiosmtpd.controller import Controller
from aiosmtpd.smtp import SMTP
from email.parser import BytesParser
from email.header import decode_header, make_header
from email import policy
from urllib.parse import quote, urlparse
import os
import re
import time
import json
import hashlib
import hmac
import configparser
import logging
import mimetypes
import pwd

logger = logging.getLogger(__name__)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.normpath(os.path.join(BASE_DIR, '..', 'data'))
CONFIG_FILE = os.path.normpath(os.path.join(BASE_DIR, '..', 'config.ini'))

# globals for settings
DISCARD_UNKNOWN = False
DELETE_OLDER_THAN_DAYS = False
ATTACHMENTS_MAX_SIZE = 0
DOMAINS = []
URL = ""
MAILPORT_TLS = 0
TLS_CERTIFICATE = ""
TLS_PRIVATE_KEY = ""
WEBHOOK_URL = ""
SMTP_HOSTNAME = ""

# Neutral greeting, e.g. "220 mx.example.com ESMTP". aiosmtpd would otherwise announce itself as "Python SMTP x.y.z"
SMTP_BANNER = "ESMTP"
# Max size of a whole message (also advertised via the SIZE extension)
DATA_SIZE_LIMIT = 33554432
CLEANUP_INTERVAL = 3600

# Only accept addresses that are safe to use as a directory name and in a URL path.
# Everything here is also accepted by PHP's FILTER_VALIDATE_EMAIL so the web UI can show it
LOCALPART_RE = re.compile(r"^[a-z0-9!$&'*+=^_`{|}~-]+(\.[a-z0-9!$&'*+=^_`{|}~-]+)*$")
DOMAIN_RE = re.compile(r"^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$")
EMAIL_ID_RE = re.compile(r"^\d+\.json$")

# Serializes id allocation + writing since the plaintext and TLS servers run in separate threads
STORE_LOCK = threading.Lock()


def normalize_address(address):
    """Returns the lowercased address if it's safe to store, None otherwise"""
    if not address:
        return None
    address = address.strip().lower()
    if len(address) > 254 or address.count('@') != 1:
        return None
    local, domain = address.split('@')
    if len(local) > 64 or not LOCALPART_RE.match(local) or not DOMAIN_RE.match(domain):
        return None
    return address


def is_domain_allowed(domain):
    for x in DOMAINS:
        if "*" in x and domain.endswith(x.replace('*', '')):
            return True
        elif domain == x:
            return True
    return False


def mailbox_dir(email):
    path = os.path.normpath(os.path.join(DATA_DIR, email))
    # defense in depth, normalize_address() should already make this impossible
    if os.path.dirname(path) != DATA_DIR:
        raise ValueError('Mailbox path outside of data directory: %r' % email)
    return path


def bytes_to_text(data):
    try:
        return data.decode('utf-8')
    except UnicodeDecodeError:
        return data.decode('latin-1')


def clean_text(value):
    """Turn a (header) value into a plain str that can be saved as JSON.
    The email package keeps undecodable 8-bit bytes as surrogates which PHP's json_decode can't handle"""
    if value is None:
        return ''
    value = str(value)
    try:
        value.encode('utf-8')
        return value
    except UnicodeEncodeError:
        return bytes_to_text(value.encode('utf-8', 'surrogateescape'))


def decode_text_part(part):
    payload = part.get_payload(decode=True) or b''
    charset = part.get_content_charset() or 'utf-8'
    try:
        return payload.decode(charset)
    except (LookupError, UnicodeDecodeError):
        return bytes_to_text(payload)


def safe_filename(filename):
    filename = clean_text(filename).replace('\\', '/').split('/')[-1]
    filename = re.sub(r'[\x00-\x1f\x7f?#%"<>|:*]', '_', filename).strip(' .')
    root, ext = os.path.splitext(filename)
    if len(ext.encode('utf-8')) > 20:
        root, ext = filename, ''
    while len((root + ext).encode('utf-8')) > 180:
        root = root[:-1]
    return (root + ext) or 'untitled'


def get_header(message, name):
    raw = next((value for key, value in message.raw_items() if key.lower() == name), None)
    if raw is None:
        return ''
    try:
        raw.encode('utf-8')
        return clean_text(message[name])
    except Exception:
        # Raw 8-bit bytes in the header (UTF-8 per RFC 6532 or some legacy charset) which policy.default
        # would replace with "?" chars, or a header so malformed that policy.default can't parse it
        raw = re.sub(r'\r?\n(?=[ \t])', '', clean_text(raw)).strip()
        try:
            return str(make_header(decode_header(raw)))
        except Exception:
            return raw


def iter_parts(part):
    """Like Message.walk() but yields multipart containers' children only and doesn't descend into attached messages"""
    if part.get_content_maintype() == 'message':
        yield part
    elif part.is_multipart():
        for subpart in part.get_payload():
            yield from iter_parts(subpart)
    else:
        yield part


class AttachmentTooLarge(Exception):
    pass


class TrashmailSMTP(SMTP):
    # RFC 5321 limits lines to 1000 chars but plenty of real world mailers send longer ones.
    # aiosmtpd would reject the whole message, so we accept them like most MTAs do
    line_length_limit = DATA_SIZE_LIMIT


class TrashmailController(Controller):
    def factory(self):
        return TrashmailSMTP(self.handler, **self.SMTP_kwargs)


class CustomHandler:
    connection_type = ''
    def __init__(self,conntype='Plaintext'):
        self.connection_type = conntype
        self.background_tasks = set()

    async def handle_EHLO(self, server, session, envelope, hostname, responses):
        # We only receive mail. aiosmtpd offers AUTH after STARTTLS (always failing), which an MX normally doesn't
        session.host_name = hostname
        return [r for r in responses if not r.startswith('250-AUTH')]

    async def handle_RCPT(self, server, session, envelope, address, rcpt_options):
        email = normalize_address(address)
        if email is None:
            logger.info('Rejecting invalid recipient: %r' % address)
            return '553 5.1.3 Bad recipient address syntax'
        if DISCARD_UNKNOWN and not is_domain_allowed(email.split('@')[1]):
            logger.info('Rejecting recipient for unknown domain: %s' % email)
            return '550 5.7.1 Relay access denied'
        if email not in envelope.rcpt_tos:
            envelope.rcpt_tos.append(email)
        return '250 2.1.5 OK'

    async def handle_exception(self, error):
        # Default would send "500 Error: (ExceptionName) ..." which leaks internals and makes senders bounce permanently
        logger.exception('SMTP session exception: %s' % error)
        return '451 4.3.0 Temporary server error, please try again later'

    async def handle_DATA(self, server, session, envelope):
        peer = session.peer
        rcpts = list(envelope.rcpt_tos)

        logger.debug('Receiving message from: %s (%s)', peer,self.connection_type)
        logger.debug('Message addressed from: %s' % envelope.mail_from)
        logger.debug('Message addressed to: %s' % str(rcpts))

        try:
            parsed = self.parse_message(envelope.content)
        except AttachmentTooLarge:
            return '552 5.3.4 Message size exceeds fixed limit'
        except Exception:
            # never lose an email because we couldn't parse it. Keep the raw version
            logger.exception('Error parsing message from %s, saving raw version only' % (peer,))
            parsed = {
                'subject': '(No Subject)',
                'body': bytes_to_text(envelope.content),
                'htmlbody': '',
                'from': clean_text(envelope.mail_from),
                'attachments': [],
            }

        try:
            saved = self.store_message(peer, rcpts, envelope.content, parsed)
        except Exception:
            logger.exception('Error saving message')
            return '451 4.3.0 Temporary server error, please try again later'

        for em, savedata in saved:
            task = asyncio.create_task(self.send_to_webhook(em, savedata))
            self.background_tasks.add(task)
            task.add_done_callback(self.background_tasks.discard)

        return '250 2.0.0 OK: queued'

    def parse_message(self, content):
        message = BytesParser(policy=policy.default).parsebytes(content)
        subject = get_header(message, 'subject') or "(No Subject)"

        # Separate HTML and plaintext parts
        plaintext = ''
        html = ''
        attachments = []
        for part in iter_parts(message):
            content_type = part.get_content_type()
            try:
                filename = part.get_filename()
            except Exception:
                filename = None
            is_attachment = filename is not None or part.get_content_disposition() == 'attachment'

            if content_type == 'text/plain' and not is_attachment:
                plaintext += decode_text_part(part)
            elif content_type == 'text/html' and not is_attachment:
                html += decode_text_part(part)
            else:
                attachments.append(self.handleAttachment(part, filename))

        return {
            'subject': subject,
            'body': plaintext,
            'htmlbody': html,
            'from': get_header(message, 'from'),
            'attachments': attachments,
        }

    def store_message(self, peer, rcpts, content, parsed):
        raw_email = bytes_to_text(content)
        saved = []
        with STORE_LOCK:
            # millisecond timestamp is the id of the email. Make sure it's unique for all recipients
            mailid = int(round(time.time() * 1000))
            while any(os.path.exists(os.path.join(mailbox_dir(em), '%d.json' % mailid)) for em in rcpts):
                mailid += 1
            filenamebase = str(mailid)

            # give every attachment a unique file name
            file_ids = []
            for (filename, payload, cid) in parsed['attachments']:
                root, ext = os.path.splitext(filename)
                file_id = filenamebase + '-' + filename
                n = 1
                while file_id in file_ids:
                    file_id = '%s-%s-%d%s' % (filenamebase, root, n, ext)
                    n += 1
                file_ids.append(file_id)

            for em in rcpts:
                maildir = mailbox_dir(em)
                os.makedirs(maildir, 0o755, exist_ok=True)

                # relative to the web UI, which might be hosted under a path (eg. https://example.com/trashmail)
                attachment_base_url = urlparse(URL).path.rstrip('/') + "/api/attachment/" + quote(em, safe="@+") + "/"

                edata = {
                    'subject': parsed['subject'],
                    'body': parsed['body'],
                    'htmlbody': self.replace_cid_with_attachment_id(parsed['htmlbody'], parsed['attachments'], file_ids, attachment_base_url),
                    'from': parsed['from'],
                    'attachments':[],
                    'attachments_details':[]
                }
                savedata = {'sender_ip':peer[0],
                    'from':parsed['from'],
                    'rcpts':rcpts,
                    'raw':raw_email,
                    'parsed':edata
                }

                #save attachments if any
                if parsed['attachments']:
                    os.makedirs(os.path.join(maildir, 'attachments'), 0o755, exist_ok=True)
                for (filename, payload, cid), file_id in zip(parsed['attachments'], file_ids):
                    with open(os.path.join(maildir, 'attachments', file_id), 'wb') as file:
                        file.write(payload)
                    edata["attachments"].append(file_id)
                    edata["attachments_details"].append({
                            "filename":filename,
                            "cid":cid,
                            "id":file_id,
                            "download_url":URL.rstrip('/')+"/api/attachment/"+quote(em, safe="@+")+"/"+quote(file_id, safe=""),
                            "size":len(payload)
                        })

                # save actual json data. Write to a temp file first so the web UI never reads a half written email
                tmpfile = os.path.join(maildir, '.' + filenamebase + '.json.tmp')
                with open(tmpfile, "w") as outfile:
                    json.dump(savedata, outfile)
                os.replace(tmpfile, os.path.join(maildir, filenamebase + '.json'))
                logger.info('Saved email %s for %s' % (filenamebase, em))
                saved.append((em, savedata))
        return saved

    async def send_to_webhook(self, email, data):
        # Try per-email webhook first
        webhook_config = self.load_webhook_config(email)

        if webhook_config and webhook_config.get('enabled'):
            await self.send_configured_webhook(email, data, webhook_config)
        elif WEBHOOK_URL != "":
            # Fallback to global webhook
            await self.send_global_webhook(data)

    def load_webhook_config(self, email):
        webhook_file = os.path.join(mailbox_dir(email), "webhook.json")
        if os.path.exists(webhook_file):
            try:
                with open(webhook_file, 'r') as f:
                    config = json.load(f)
                    # Validate config structure
                    if not isinstance(config, dict):
                        logger.error("Invalid webhook config format for %s: not a dictionary" % email)
                        return None
                    return config
            except json.JSONDecodeError as e:
                logger.error("Invalid JSON in webhook config for %s: %s" % (email, str(e)))
            except Exception as e:
                logger.error("Error loading webhook config for %s: %s" % (email, str(e)))
        return None

    def replace_template_variables(self, template, data):
        """Replace {{variable}} placeholders in template with actual data"""
        try:
            # Helper function to escape JSON strings
            def json_escape(value):
                if value is None:
                    return ''
                # json.dumps escapes everything JSON needs (incl. control chars), strip the surrounding quotes
                return json.dumps(str(value))[1:-1]

            replacements = {
                '{{to}}': json_escape(data['rcpts'][0] if data.get('rcpts') else ''),
                '{{from}}': json_escape(data.get('parsed', {}).get('from', '')),
                '{{subject}}': json_escape(data.get('parsed', {}).get('subject', '')),
                '{{body}}': json_escape(data.get('parsed', {}).get('body', '')),
                '{{htmlbody}}': json_escape(data.get('parsed', {}).get('htmlbody', '')),
                '{{sender_ip}}': json_escape(data.get('sender_ip', '')),
                '{{attachments}}': json.dumps(data.get('parsed', {}).get('attachments_details', []))
            }

            result = template
            for key, value in replacements.items():
                result = result.replace(key, value)

            return result
        except Exception as e:
            logger.error("Error replacing template variables: %s" % str(e))
            return template

    def sign_payload(self, payload, secret_key):
        """Generate HMAC signature for webhook payload"""
        if not secret_key:
            return None

        signature = hmac.new(
            secret_key.encode('utf-8'),
            payload.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()

        return signature

    async def send_configured_webhook(self, email, data, config):
        """Send webhook with custom configuration and retry logic"""
        webhook_url = config.get('webhook_url')
        if not webhook_url:
            logger.error("No webhook URL configured for %s" % email)
            return

        # Prepare payload from template
        template = config.get('payload_template', '{}')
        payload_str = self.replace_template_variables(template, data)

        try:
            payload = json.loads(payload_str)
        except json.JSONDecodeError as e:
            logger.error("Invalid JSON in webhook payload template for %s: %s" % (email, str(e)))
            logger.error("Template: %s" % template)
            logger.error("Payload string: %s" % payload_str)
            return

        # Retry configuration
        retry_config = config.get('retry_config', {})
        max_attempts = retry_config.get('max_attempts', 3)
        backoff_multiplier = retry_config.get('backoff_multiplier', 2)

        # Prepare headers
        headers = {'Content-Type': 'application/json'}

        # Add signature if secret key is configured
        secret_key = config.get('secret_key')
        if secret_key:
            signature = self.sign_payload(json.dumps(payload), secret_key)
            headers['X-Webhook-Signature'] = signature

        # Send with retry logic
        for attempt in range(max_attempts):
            try:
                async with aiohttp.ClientSession() as session:
                    async with session.post(webhook_url, json=payload, headers=headers, timeout=aiohttp.ClientTimeout(total=30)) as response:
                        if response.status >= 200 and response.status < 300:
                            logger.info("Webhook sent successfully to %s for %s (attempt %d)" % (webhook_url, email, attempt + 1))
                            return
                        else:
                            logger.warning("Webhook failed with status %d for %s (attempt %d)" % (response.status, email, attempt + 1))
            except Exception as e:
                logger.error("Error sending webhook for %s (attempt %d): %s" % (email, attempt + 1, str(e)))

            # Wait before retry (exponential backoff)
            if attempt < max_attempts - 1:
                wait_time = (backoff_multiplier ** attempt) * 1  # Start with 1 second
                logger.info("Retrying webhook for %s in %d seconds..." % (email, wait_time))
                await asyncio.sleep(wait_time)

        logger.error("Failed to send webhook for %s after %d attempts" % (email, max_attempts))

    async def send_global_webhook(self, data):
        """Send to global webhook URL (backward compatibility)"""
        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(WEBHOOK_URL, json=data, timeout=aiohttp.ClientTimeout(total=30)) as response:
                    logger.info("Global webhook sent (status %d)." % response.status)
        except Exception as e:
            logger.error("Error sending global webhook: %s" % str(e))

    def handleAttachment(self, part, filename):
        if part.get_content_maintype() == 'message':
            # attached (e.g. forwarded) email. Save it as a whole
            payload = b''.join(p.as_bytes(policy=policy.compat32) for p in part.get_payload())
            if filename is None:
                filename = 'message.eml' if part.get_content_type() == 'message/rfc822' else 'untitled.txt'
        else:
            payload = part.get_payload(decode=True) or b''
        if filename is None:
            filename = 'untitled' + (mimetypes.guess_extension(part.get_content_type()) or '')
        filename = safe_filename(filename)
        cid = part.get('Content-ID')
        if cid is not None:
            cid = str(cid).strip().strip('<>')
        elif part.get('X-Attachment-Id') is not None:
            cid = str(part.get('X-Attachment-Id'))
        else: # else create a unique id using md5 of the attachment
            cid = hashlib.md5(payload).hexdigest()
        logger.debug('Handling attachment: "%s" of type "%s" with CID "%s"',filename,part.get_content_type(), cid)

        if(ATTACHMENTS_MAX_SIZE > 0 and len(payload) > ATTACHMENTS_MAX_SIZE):
            logger.info("Attachment too large: " + filename)
            raise AttachmentTooLarge(filename)

        return (filename,payload,cid)

    def replace_cid_with_attachment_id(self, html_content, attachments, file_ids, attachment_base_url):
        # Replace cid references with attachment urls
        for (filename, payload, cid), file_id in zip(attachments, file_ids):
            if cid:
                html_content = html_content.replace('cid:' + cid, attachment_base_url + quote(file_id, safe=""))
        return html_content

def cleanup():
    if not DELETE_OLDER_THAN_DAYS:
        return
    logger.info("Cleaning up")
    cutoff = time.time() - (DELETE_OLDER_THAN_DAYS * 86400)
    for mailbox in os.scandir(DATA_DIR):
        if not mailbox.is_dir():
            continue
        for entry in os.scandir(mailbox.path):
            # only emails. Keeps webhook configs
            if not EMAIL_ID_RE.match(entry.name) or entry.stat().st_mtime > cutoff:
                continue
            try:
                with open(entry.path) as f:
                    attachments = json.load(f).get('parsed', {}).get('attachments') or []
            except Exception:
                attachments = []
            for attachment in attachments:
                attachment_path = os.path.join(mailbox.path, 'attachments', os.path.basename(attachment))
                if os.path.isfile(attachment_path):
                    os.remove(attachment_path)
            os.remove(entry.path)
            logger.info("Deleted file: " + entry.path)
        # delete empty folders now
        attachments_dir = os.path.join(mailbox.path, 'attachments')
        if os.path.isdir(attachments_dir) and not os.listdir(attachments_dir):
            os.rmdir(attachments_dir)
        if not os.listdir(mailbox.path):
            os.rmdir(mailbox.path)
            logger.info("Deleted folder: " + mailbox.path)

def start_controller(handler, port, **kwargs):
    # "" binds on all IPv4 and IPv6 addresses. Fall back to IPv4 only if IPv6 is disabled (eg. default Docker networks)
    for hostname in ('', '0.0.0.0'):
        controller = TrashmailController(handler, hostname=hostname, port=port,
                                         server_hostname=SMTP_HOSTNAME, ident=SMTP_BANNER,
                                         data_size_limit=DATA_SIZE_LIMIT, **kwargs)
        try:
            controller.start()
            return controller
        except OSError as e:
            if hostname == '0.0.0.0':
                raise
            logger.info("[i] Could not listen on all interfaces (%s), falling back to IPv4 only" % e)

def drop_privileges(username):
    # Ports below 1024 need root to bind. After that there's no reason to keep running as root
    user = pwd.getpwnam(username)
    os.setgroups([])
    os.setgid(user.pw_gid)
    os.setuid(user.pw_uid)
    logger.info("[i] Running as user %s (uid %d, gid %d)" % (username, user.pw_uid, user.pw_gid))

async def run(port):
    controllers = []
    if TLS_CERTIFICATE != "" and TLS_PRIVATE_KEY != "":
        context = ssl.create_default_context(ssl.Purpose.CLIENT_AUTH)
        context.load_cert_chain(TLS_CERTIFICATE, TLS_PRIVATE_KEY)
        if MAILPORT_TLS > 0:
            controllers.append(start_controller(CustomHandler("TLS"), MAILPORT_TLS, ssl_context=context))
            logger.info("[i] Starting TLS only Mailserver on port " + str(MAILPORT_TLS))

        controllers.append(start_controller(CustomHandler("Plaintext or STARTTLS"), port, tls_context=context))
        logger.info("[i] Starting plaintext Mailserver (with STARTTLS support) on port " + str(port))
    else:
        controllers.append(start_controller(CustomHandler("Plaintext"), port))
        logger.info("[i] Starting plaintext Mailserver on port " + str(port))

    if os.getuid() == 0 and os.environ.get('MAILSERVER_USER'):
        drop_privileges(os.environ['MAILSERVER_USER'])

    logger.info("[i] Ready to receive Emails")
    logger.info("")

    try:
        while True:
            try:
                cleanup()
            except Exception:
                logger.exception("Error during cleanup")
            await asyncio.sleep(CLEANUP_INTERVAL)
    finally:
        for controller in controllers:
            controller.stop()

if __name__ == '__main__':
    ch = logging.StreamHandler()
    ch.setLevel(logging.DEBUG)
    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    ch.setFormatter(formatter)
    logger.setLevel(logging.DEBUG)
    logger.addHandler(ch)

    if not os.path.isfile(CONFIG_FILE):
        logger.info("[ERR] Config.ini not found. Rename example.config.ini to config.ini. Defaulting to port 25")
        port = 25
    else:
        Config = configparser.ConfigParser(allow_no_value=True)
        Config.read(CONFIG_FILE)
        port = int(Config.get("MAILSERVER", "MAILPORT"))
        if("discard_unknown" in Config.options("MAILSERVER")):
            DISCARD_UNKNOWN = (Config.get("MAILSERVER", "DISCARD_UNKNOWN").lower() == "true")
        DOMAINS = [d.strip() for d in Config.get("GENERAL", "DOMAINS").lower().split(",") if d.strip()]
        URL = Config.get("GENERAL", "URL").strip('"')
        if("attachments_max_size" in Config.options("MAILSERVER")):
            ATTACHMENTS_MAX_SIZE = int(Config.get("MAILSERVER", "ATTACHMENTS_MAX_SIZE") or 0)
        if "CLEANUP" in Config.sections() and "delete_older_than_days" in Config.options("CLEANUP"):
            raw_val = Config.get("CLEANUP", "DELETE_OLDER_THAN_DAYS").strip().lower()
            try:
                if raw_val in ["false", "off", "no", "none", ""]:
                    DELETE_OLDER_THAN_DAYS = 0
                else:
                    DELETE_OLDER_THAN_DAYS = float(raw_val)
            except ValueError:
                logger.warning("Invalid value for DELETE_OLDER_THAN_DAYS: %s. Defaulting to 0." % raw_val)
                DELETE_OLDER_THAN_DAYS = 0
        if("mailport_tls" in Config.options("MAILSERVER")):
            MAILPORT_TLS = int(Config.get("MAILSERVER", "MAILPORT_TLS") or 0)
        if("tls_certificate" in Config.options("MAILSERVER")):
            TLS_CERTIFICATE = Config.get("MAILSERVER", "TLS_CERTIFICATE") or ""
        if("tls_private_key" in Config.options("MAILSERVER")):
            TLS_PRIVATE_KEY = Config.get("MAILSERVER", "TLS_PRIVATE_KEY") or ""
        if("smtp_hostname" in Config.options("MAILSERVER")):
            SMTP_HOSTNAME = (Config.get("MAILSERVER", "SMTP_HOSTNAME") or "").strip()

        if "WEBHOOK" in Config.sections() and "webhook_url" in Config.options("WEBHOOK"):
            WEBHOOK_URL = Config.get("WEBHOOK", "WEBHOOK_URL") or ""
        else:
            WEBHOOK_URL = ""

    if SMTP_HOSTNAME == "":
        # Name used in the greeting and EHLO response. Without it aiosmtpd would use the machine's name
        # which in Docker is the random container id. Should match the host your MX record points to
        candidates = [d.lstrip('*.') for d in DOMAINS if d.lstrip('*.') and '*' not in d.lstrip('*.')]
        SMTP_HOSTNAME = candidates[0] if candidates else socket.getfqdn()

    logger.info("[i] Discard unknown domains: " + str(DISCARD_UNKNOWN))
    logger.info("[i] Max size of attachments: " + str(ATTACHMENTS_MAX_SIZE))
    logger.info("[i] Listening for domains: " + str(DOMAINS))
    logger.info("[i] SMTP hostname: " + SMTP_HOSTNAME)

    asyncio.run(run(port))
