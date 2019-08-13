import smtpd
import asyncore
import uuid
import time
import os, sys
import json

class CustomSMTPServer(smtpd.SMTPServer):    
    def process_message(self, peer, mailfrom, rcpttos, data):
        print 'Receiving message from:', peer
        print 'Message addressed from:', mailfrom
        print 'Message addressed to  :', rcpttos
        print 'Message length        :', len(data)
        print "----------"
        for email in rcpttos:
            if not os.path.exists("../data/"+email):
                os.mkdir( "../data/"+email, 0755 )
            with open("../data/"+email+"/"+str(int(round(time.time() * 1000)))+".json", "w") as outfile:
                json.dump({'sender_ip':peer[0],'from':mailfrom,'rcpts':rcpttos,'data':data}, outfile)
	
        return
server = CustomSMTPServer(('0.0.0.0', 25), None)

asyncore.loop()