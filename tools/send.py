from smtplib import SMTP as Client

client = Client("localhost", "2525")
r = client.sendmail('a@example.com', ['b@domain.tld'], """\
From: Anne Person <anne@example.com>
To: Bart Person <bart@example.com>
Subject: A test
Message-ID: <ant>

Hi Bart, this is Anne.
""")