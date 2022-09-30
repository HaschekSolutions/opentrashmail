import smtpd
import asyncore
import logging
import email
from email.header import decode_header
from email.Utils import parseaddr
import re
#import requests
import ConfigParser
import time
import os, sys
import json

logger = logging.getLogger(__name__)

# globals for settings
DISCARD_UNKNOWN = False
DELETE_OLDER_THAN_DAYS = False
DOMAINS = []
LAST_CLEANUP = 0

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

class CustomSMTPServer(smtpd.SMTPServer):
    def process_message(self, peer, mailfrom, rcpttos, data):
        try:
            mailfrom = parseaddr(mailfrom)[1]
            logger.debug('Receiving message from: %s:%d' % peer)
            logger.debug('Message addressed from: %s' % mailfrom)
            logger.debug('Message addressed to: %s' % str(rcpttos))

            

            msg = email.message_from_string(data)
            subject = ''
            for encoded_string, charset in decode_header(msg.get('Subject')):
                try:
                    if charset is not None:
                        subject += encoded_string.decode(charset)
                    else:
                        subject += encoded_string
                except:
                    logger.exception('Error reading part of subject: %s charset %s' %
                                     (encoded_string, charset))

            logger.debug('Subject: %s' % subject)

            text_parts = []
            html_parts = []
            attachments = {}

            #logger.debug('Headers: %s' % msg.items())

            # YOU CAN DO SOME SECURITY CONTROLS HERE
            #if (not mailfrom.endswith("@hankenfeld.at") or
            #    not msg.get('Mail-Header') == 'expected value'):
            #    raise Exception("Email not trusted")

            # loop on the email parts
            for part in msg.walk():
                if part.get_content_maintype() == 'multipart':
                    continue

                c_type = part.get_content_type()
                c_disp = part.get('Content-Disposition')

                # text parts will be appended to text_parts
                if c_type == 'text/plain' and c_disp == None:
                    text_parts.append(part.get_payload(decode=True).strip())
                # ignore html part
                elif c_type == 'text/html':
                    html_parts.append(part.get_payload(decode=True).strip())
                # attachments will be sent as files in the POST request
                else:
                    filename = part.get_filename()
                    filecontent = part.get_payload(decode=True)
                    if filecontent is not None:
                        if filename is None:
                            filename = 'untitled'
                        attachments['file%d' % len(attachments)] = (filename,
                                                                    filecontent)

            body = '\n'.join(text_parts)
            htmlbody = '\n'.join(html_parts)
            
        except:
            logger.exception('Error reading incoming email')
        else:
            # this data will be sent as POST data
            edata = {
                'subject': subject,
                'body': body,
                'htmlbody': htmlbody,
                'from': mailfrom,
                'attachments':[]
            }
            savedata = {'sender_ip':peer[0],'from':mailfrom,'rcpts':rcpttos,'raw':data,'parsed':edata}

            filenamebase = str(int(round(time.time() * 1000)))

            for em in rcpttos:
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

            #print edata
            cleanup()
        return

if __name__ == '__main__':
    ch = logging.StreamHandler()
    ch.setLevel(logging.DEBUG)
    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    ch.setFormatter(formatter)
    logger.setLevel(logging.DEBUG)
    logger.addHandler(ch)

    if not os.path.isfile("../config.ini"):
        print "[ERR] Config.ini not found. Rename example.config.ini to config.ini. Defaulting to port 25"
        port = 25
    else :
        Config = ConfigParser.ConfigParser(allow_no_value=True)
        Config.read("../config.ini")
        port = int(Config.get("MAILSERVER","MAILPORT"))
        if("discard_unknown" in Config.options("MAILSERVER")):
            DISCARD_UNKNOWN = (Config.get("MAILSERVER","DISCARD_UNKNOWN").lower() == "true")            
        DOMAINS = Config.get("GENERAL","DOMAINS").lower().split(",")

        if("CLEANUP" in Config.sections() and "delete_older_than_days" in Config.options("CLEANUP")):
            DELETE_OLDER_THAN_DAYS = (Config.get("CLEANUP","DELETE_OLDER_THAN_DAYS").lower() == "true")    

    print "[i] Starting Mailserver on port",port

    server = CustomSMTPServer(('0.0.0.0', port), None) # use your public IP here
    print "[i] Ready to receive Emails"
    print ""
    asyncore.loop()
