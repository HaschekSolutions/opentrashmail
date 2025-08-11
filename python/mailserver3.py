import aiohttp
import asyncio
import ssl
from aiosmtpd.controller import Controller
from email.parser import BytesParser
from email.header import decode_header, make_header
from email import policy
import os
import re
import time
import json
import hashlib
import hmac
import configparser
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
import logging
from pprint import pprint

logger = logging.getLogger(__name__)

# globals for settings
DISCARD_UNKNOWN = False
DELETE_OLDER_THAN_DAYS = False
ATTACHMENTS_MAX_SIZE = 0
DOMAINS = []
LAST_CLEANUP = 0
URL = ""
MAILPORT_TLS = 0
TLS_CERTIFICATE = ""
TLS_PRIVATE_KEY = ""
WEBHOOK_URL = ""

class CustomHandler:
    connection_type = ''
    def __init__(self,conntype='Plaintext'):
        self.connection_type = conntype

    async def handle_DATA(self, server, session, envelope):
        peer = session.peer
        rcpts = []
        for rcpt in envelope.rcpt_tos:
            rcpts.append(rcpt)

        logger.debug('Receiving message from: %s (%s)', peer,self.connection_type)

        logger.debug('Message addressed from: %s' % envelope.mail_from)
        logger.debug('Message addressed to: %s' % str(rcpts))

        filenamebase = str(int(round(time.time() * 1000)))

        # Get the raw email data
        raw_email = envelope.content.decode('utf-8')

        # Parse the email
        message = BytesParser(policy=policy.default).parsebytes(envelope.content)
        subject = str(make_header(decode_header(message['subject']))) if message['subject'] else "(No Subject)"


        # Separate HTML and plaintext parts
        plaintext = ''
        html = ''
        attachments = {}
        for part in message.walk():
            if part.get_content_maintype() == 'multipart':
                continue
            if part.get_content_type() == 'text/plain':
                #if it's a file
                if part.get_filename() is not None:
                    att = self.handleAttachment(part)
                    if(att == False):
                        return '500 Attachment too large. Max size: ' + str(ATTACHMENTS_MAX_SIZE/1000000)+"MB"
                    attachments['file%d' % len(attachments)] = att                                            
                else:                                                                                         
                    try:                                                                                      
                        plaintext += part.get_payload(decode=True).decode('utf-8')                            
                        logger.debug('UTF-8 received')                                                        
                    except UnicodeDecodeError:                                                                
                        plaintext += part.get_payload(decode=True).decode('latin1')                           
                        logger.debug('latin1 received')                                                       
                    except Exception as e:                                                                    
                        print(f"Error decoding payload: {e}")                                                 
                        logger.debug(e)                                                                       
            elif part.get_content_type() == 'text/html':                                                      
                try:                                                                                          
                    html += part.get_payload(decode=True).decode('utf-8')
                    logger.debug('UTF-8 received')
                except UnicodeDecodeError:                                                                    
                    html += part.get_payload(decode=True).decode('latin1')
                    logger.debug('latin1 received')                                                       
                except Exception as e:                                                                        
                    print(f"Error decoding payload: {e}")                                                     
                    logger.debug(e)                                                                           
            else:                                                                                             
                att = self.handleAttachment(part)  
                if(att == False):
                    return '500 Attachment too large. Max size: ' + str(ATTACHMENTS_MAX_SIZE/1000000)+"MB"
                attachments['file%d' % len(attachments)] = att

        for em in rcpts:
                em = em.lower()
                if not re.match(r"[^@\s]+@[^@\s]+\.[a-zA-Z0-9]+$", em):
                    logger.exception('Invalid recipient: %s' % em)
                    continue

                domain = em.split('@')[1]
                found = False
                for x in DOMAINS:
                    if  "*" in x and domain.endswith(x.replace('*', '')):
                        found = True
                    elif domain == x:
                        found = True
                if(DISCARD_UNKNOWN and found==False):
                    logger.info('Discarding email for unknown domain: %s' % domain)
                    continue

                if not os.path.exists("../data/"+em):
                    os.mkdir( "../data/"+em, 0o755 )


                edata = {
                    'subject': subject,
                    'body': plaintext,
                    'htmlbody': self.replace_cid_with_attachment_id(html, attachments,filenamebase,em),
                    'from': message['from'],
                    'attachments':[],
                    'attachments_details':[]
                }
                savedata = {'sender_ip':peer[0],
                    'from':message['from'],
                    'rcpts':rcpts,
                    'raw':raw_email,
                    'parsed':edata
                }

                #same attachments if any
                for att in attachments:
                    if not os.path.exists("../data/"+em+"/attachments"):
                        os.mkdir( "../data/"+em+"/attachments", 0o755 )
                    attd = attachments[att]
                    file_id = attd[3]
                    file = open("../data/"+em+"/attachments/"+file_id, 'wb')
                    file.write(attd[1])
                    file.close()
                    edata["attachments"].append(file_id)
                    edata["attachments_details"].append({
                            "filename":attd[0],
                            "cid":attd[2],
                            "id":attd[3],
                            "download_url":URL+"/api/attachment/"+em+"/"+file_id,
                            "size":len(attd[1])
                        })

                # save actual json data
                with open("../data/"+em+"/"+filenamebase+".json", "w") as outfile:
                    json.dump(savedata, outfile)

                await self.send_to_webhook(em, savedata)

        cleanup()

        return '250 OK'

    async def send_to_webhook(self, email, data):
        # Try per-email webhook first
        webhook_config = self.load_webhook_config(email)
        
        if webhook_config and webhook_config.get('enabled'):
            await self.send_configured_webhook(email, data, webhook_config)
        elif WEBHOOK_URL != "":
            # Fallback to global webhook
            await self.send_global_webhook(data)
    
    def load_webhook_config(self, email):
        webhook_file = "../data/" + email + "/webhook.json"
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
                # For non-string values, convert to string first
                str_value = str(value)
                # Escape special characters for JSON
                return (str_value.replace('\\', '\\\\')
                               .replace('"', '\\"')
                               .replace('\n', '\\n')
                               .replace('\r', '\\r')
                               .replace('\t', '\\t'))
            
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
                await session.post(WEBHOOK_URL, json=data)
                logger.info("Global webhook sent successfully.")
        except Exception as e:
            logger.error("Error sending global webhook: %s" % str(e))

    def handleAttachment(self, part):
        filename = part.get_filename()
        if filename is None:
            filename = 'untitled'
        cid = part.get('Content-ID')
        if cid is not None:
            cid = cid[1:-1]
        elif part.get('X-Attachment-Id') is not None:
            cid = part.get('X-Attachment-Id')
        else: # else create a unique id using md5 of the attachment
            cid = hashlib.md5(part.get_payload(decode=True)).hexdigest()
        fid = hashlib.md5(filename.encode('utf-8')).hexdigest()+filename
        logger.debug('Handling attachment: "%s" (ID: "%s") of type "%s" with CID "%s"',filename, fid,part.get_content_type(), cid)

        if(ATTACHMENTS_MAX_SIZE > 0 and len(part.get_payload(decode=True)) > ATTACHMENTS_MAX_SIZE):
            logger.info("Attachment too large: " + filename)
            return False

        return (filename,part.get_payload(decode=True),cid,fid)

    def replace_cid_with_attachment_id(self, html_content, attachments,filenamebase,email):
        # Replace cid references with attachment filename
        for attachment_id in attachments:
            attachment = attachments[attachment_id]
            filename = attachment[0]
            cid = attachment[2]
            if cid is None:
                continue
            cid = cid[1:-1]
            if cid is not None:
                html_content = html_content.replace('cid:' + cid, "/api/attachment/"+email+"/"+filenamebase+"-"+filename)
        return html_content

def cleanup():
    global LAST_CLEANUP
    if(DELETE_OLDER_THAN_DAYS == False or time.time() - LAST_CLEANUP < 86400):
        return
    logger.info("Cleaning up")
    LAST_CLEANUP = time.time()
    rootdir = '../data/'
    for subdir, dirs, files in os.walk(rootdir):
        for file in files:
            if(file.endswith(".json")):
                filepath = os.path.join(subdir, file)
                file_modified = os.path.getmtime(filepath)
                if(time.time() - file_modified > (DELETE_OLDER_THAN_DAYS * 86400)):
                    os.remove(filepath)
                    logger.info("Deleted file: " + filepath)
                        # delete empty folders now
    for entry in os.scandir(rootdir):
        if entry.is_dir() and not os.listdir(entry.path) :
            os.rmdir(entry.path)
            logger.info("Deleted folder: " + entry.path)
async def run(port):

    if TLS_CERTIFICATE != "" and TLS_PRIVATE_KEY != "":
        context = ssl.create_default_context(ssl.Purpose.CLIENT_AUTH)
        context.load_cert_chain(TLS_CERTIFICATE, TLS_PRIVATE_KEY)
        if MAILPORT_TLS > 0:
            controller_tls = Controller(CustomHandler("TLS"), hostname='0.0.0.0', port=MAILPORT_TLS, ssl_context=context)
            controller_tls.start()

        controller_plaintext = Controller(CustomHandler("Plaintext or STARTTLS"), hostname='0.0.0.0', port=port,tls_context=context)
        controller_plaintext.start()

        logger.info("[i] Starting TLS only Mailserver on port " + str(MAILPORT_TLS))
        logger.info("[i] Starting plaintext Mailserver (with STARTTLS support) on port " + str(port))
    else:
        controller_plaintext = Controller(CustomHandler("Plaintext"), hostname='0.0.0.0', port=port)
        controller_plaintext.start()

        logger.info("[i] Starting plaintext Mailserver on port " + str(port))


    logger.info("[i] Ready to receive Emails")
    logger.info("")

    try:
        while True:
            await asyncio.sleep(1)
    except KeyboardInterrupt:
        controller_plaintext.stop()
        if(MAILPORT_TLS > 0 and TLS_CERTIFICATE != "" and TLS_PRIVATE_KEY != ""):
            controller_tls.stop()

if __name__ == '__main__':
    ch = logging.StreamHandler()
    ch.setLevel(logging.DEBUG)
    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    ch.setFormatter(formatter)
    logger.setLevel(logging.DEBUG)
    logger.addHandler(ch)

    if not os.path.isfile("../config.ini"):
        logger.info("[ERR] Config.ini not found. Rename example.config.ini to config.ini. Defaulting to port 25")
        port = 25
    else:
        Config = configparser.ConfigParser(allow_no_value=True)
        Config.read("../config.ini")
        port = int(Config.get("MAILSERVER", "MAILPORT"))
        if("discard_unknown" in Config.options("MAILSERVER")):
            DISCARD_UNKNOWN = (Config.get("MAILSERVER", "DISCARD_UNKNOWN").lower() == "true")
        DOMAINS = Config.get("GENERAL", "DOMAINS").lower().split(",")
        URL = Config.get("GENERAL", "URL")
        if("attachments_max_size" in Config.options("MAILSERVER")):
            ATTACHMENTS_MAX_SIZE = int(Config.get("MAILSERVER", "ATTACHMENTS_MAX_SIZE"))
        if "CLEANUP" in Config.sections() and "delete_older_than_days" in Config.options("CLEANUP"):
            raw_val = Config.get("CLEANUP", "DELETE_OLDER_THAN_DAYS").strip().lower()
            try:
                if raw_val in ["false", "off", "no", "none"]:
                    DELETE_OLDER_THAN_DAYS = 0
                else:
                    DELETE_OLDER_THAN_DAYS = float(raw_val)
            except ValueError:
                logger.warning("Invalid value for DELETE_OLDER_THAN_DAYS: %s. Defaulting to 0." % raw_val)
                DELETE_OLDER_THAN_DAYS = 0
        if("mailport_tls" in Config.options("MAILSERVER")):
            MAILPORT_TLS = int(Config.get("MAILSERVER", "MAILPORT_TLS"))
        if("tls_certificate" in Config.options("MAILSERVER")):
            TLS_CERTIFICATE = Config.get("MAILSERVER", "TLS_CERTIFICATE")
        if("tls_private_key" in Config.options("MAILSERVER")):
            TLS_PRIVATE_KEY = Config.get("MAILSERVER", "TLS_PRIVATE_KEY")

        if "webhook_url" in Config.options("WEBHOOK"):
            WEBHOOK_URL = Config.get("WEBHOOK", "WEBHOOK_URL")
        else:
            WEBHOOK_URL = ""


    logger.info("[i] Discard unknown domains: " + str(DISCARD_UNKNOWN))
    logger.info("[i] Max size of attachments: " + str(ATTACHMENTS_MAX_SIZE))
    logger.info("[i] Listening for domains: " + str(DOMAINS))

    asyncio.run(run(port))
