import asyncio
from aiosmtpd.controller import Controller
from email.parser import BytesParser
from email import policy
import os
import re
import time
import json
import uuid
import configparser
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
import logging

logger = logging.getLogger(__name__)

# globals for settings
DISCARD_UNKNOWN = False
DELETE_OLDER_THAN_DAYS = False
DOMAINS = []
LAST_CLEANUP = 0

class CustomHandler:
    async def handle_DATA(self, server, session, envelope):
        peer = session.peer
        rcpts = []
        for rcpt in envelope.rcpt_tos:
            rcpts.append(rcpt)
        
        logger.debug('Receiving message from: %s:%d' % peer)
        logger.debug('Message addressed from: %s' % envelope.mail_from)
        logger.debug('Message addressed to: %s' % str(rcpts))

        # Get the raw email data
        raw_email = envelope.content.decode('utf-8')

        # Parse the email
        message = BytesParser(policy=policy.default).parsebytes(envelope.content)

        # Separate HTML and plaintext parts
        plaintext = ''
        html = ''
        attachments = {}
        for part in message.walk():
            if part.get_content_maintype() == 'multipart':
                continue
            if part.get_content_type() == 'text/plain':
                plaintext += part.get_payload()
            elif part.get_content_type() == 'text/html':
                html += part.get_payload()
            else:
                filename = part.get_filename()
                if filename is None:
                    filename = 'untitled'
                attachments['file%d' % len(attachments)] = (filename,part.get_payload(decode=True))

        edata = {
                'subject': message['subject'],
                'body': plaintext,
                'htmlbody': html,
                'from': message['from'],
                'attachments':[]
            }
        savedata = {'sender_ip':peer[0],
                    'from':message['from'],
                    'rcpts':rcpts,
                    'raw':raw_email,
                    'parsed':edata
                    }
        
        filenamebase = str(int(round(time.time() * 1000)))

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
                
                #same attachments if any
                for att in attachments:
                    if not os.path.exists("../data/"+em+"/attachments"):
                        os.mkdir( "../data/"+em+"/attachments", 0o755 )
                    attd = attachments[att]
                    file = open("../data/"+em+"/attachments/"+filenamebase+"-"+attd[0], 'wb')
                    file.write(attd[1])
                    file.close()
                    edata["attachments"].append(filenamebase+"-"+attd[0])

                # save actual json data
                with open("../data/"+em+"/"+filenamebase+".json", "w") as outfile:
                    json.dump(savedata, outfile)

        return '250 OK'

def cleanup():
    if(DELETE_OLDER_THAN_DAYS == False or time.time() - LAST_CLEANUP < 86400):
        return
    logger.info("Cleaning up")
    rootdir = '../data/'
    for subdir, dirs, files in os.walk(rootdir):
        for file in files:
            if(file.endswith(".json")):
                filepath = os.path.join(subdir, file)
                file_modified = os.path.getmtime(filepath)
                if(time.time() - file_modified > (DELETE_OLDER_THAN_DAYS * 86400)):
                    os.remove(filepath)
                    logger.info("Deleted file: " + filepath)

async def run(port):
    controller = Controller(CustomHandler(), hostname='0.0.0.0', port=port)
    controller.start()
    logger.info("[i] Ready to receive Emails")
    logger.info("")

    try:
        while True:
            await asyncio.sleep(1)
    except KeyboardInterrupt:
        controller.stop()

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

        if("CLEANUP" in Config.sections() and "delete_older_than_days" in Config.options("CLEANUP")):
            DELETE_OLDER_THAN_DAYS = (Config.get("CLEANUP", "DELETE_OLDER_THAN_DAYS").lower() == "true")

    logger.info("[i] Starting Mailserver on port " + str(port))
    logger.info("[i] Discard unknown domains: " + str(DISCARD_UNKNOWN))
    logger.info("[i] Listening for domains: " + str(DOMAINS))

    asyncio.run(run(port))
