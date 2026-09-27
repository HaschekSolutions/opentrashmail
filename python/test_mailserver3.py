"""End-to-end tests for the SMTP server. Run from the python directory: python3 -m unittest test_mailserver3"""
import json
import os
import shutil
import smtplib
import socket
import tempfile
import time
import unittest

import mailserver3 as ms


def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def multipart(*parts, subject='test'):
    out = ('From: Sender <sender@sender.org>\r\nTo: a@example.com\r\nSubject: %s\r\nMIME-Version: 1.0\r\n'
           'Content-Type: multipart/mixed; boundary="BOUNDARY"\r\n\r\n' % subject).encode()
    for headers, body in parts:
        out += b'--BOUNDARY\r\n' + headers.encode() + b'\r\n\r\n' + body + b'\r\n'
    return out + b'--BOUNDARY--\r\n'


class MailserverTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.mkdtemp()
        ms.DATA_DIR = os.path.join(self.tmp, 'data')
        os.mkdir(ms.DATA_DIR)
        ms.DOMAINS = ['example.com', '*.wild.org']
        ms.DISCARD_UNKNOWN = True
        ms.ATTACHMENTS_MAX_SIZE = 0
        ms.DELETE_OLDER_THAN_DAYS = 0
        ms.URL = 'http://localhost:8080'
        ms.WEBHOOK_URL = ''
        ms.SMTP_HOSTNAME = 'mx.example.com'
        self.port = free_port()
        self.controller = ms.start_controller(ms.CustomHandler(), self.port)

    def tearDown(self):
        self.controller.stop()
        shutil.rmtree(self.tmp)

    def client(self):
        return smtplib.SMTP('127.0.0.1', self.port, timeout=10)

    def send(self, data, rcpts=('a@example.com',)):
        with self.client() as c:
            return c.sendmail('sender@sender.org', list(rcpts), data)

    def stored(self, email):
        maildir = os.path.join(ms.DATA_DIR, email)
        files = sorted(f for f in os.listdir(maildir) if f.endswith('.json'))
        out = []
        for f in files:
            with open(os.path.join(maildir, f)) as fh:
                out.append(json.load(fh))
        return out

    def attachment(self, email, file_id):
        with open(os.path.join(ms.DATA_DIR, email, 'attachments', file_id), 'rb') as f:
            return f.read()

    # --- fingerprinting / relay behaviour ---

    def test_banner_and_ehlo_do_not_reveal_software(self):
        with socket.create_connection(('127.0.0.1', self.port), timeout=5) as s:
            banner = s.recv(1024).decode()
        self.assertEqual(banner.strip(), '220 mx.example.com ESMTP')
        with self.client() as c:
            code, resp = c.ehlo('client.test')
        self.assertEqual(code, 250)
        self.assertTrue(resp.startswith(b'mx.example.com'))
        self.assertNotIn(b'python', resp.lower())

    def test_ehlo_does_not_advertise_auth(self):
        # aiosmtpd offers AUTH once the connection is encrypted. Simulate that without needing a certificate
        controller = ms.start_controller(ms.CustomHandler(), free_port(), auth_require_tls=False)
        try:
            with smtplib.SMTP('127.0.0.1', controller.port, timeout=10) as c:
                code, resp = c.ehlo('client.test')
        finally:
            controller.stop()
        self.assertEqual(code, 250)
        self.assertNotIn(b'AUTH', resp)
        self.assertIn(b'8BITMIME', resp)

    def test_rejects_unknown_domain_at_rcpt(self):
        with self.client() as c:
            c.ehlo()
            c.mail('sender@sender.org')
            code, msg = c.rcpt('victim@gmail.com')
        self.assertEqual(code, 550)
        self.assertEqual(os.listdir(ms.DATA_DIR), [])

    def test_accepts_wildcard_subdomain(self):
        self.send(b'Subject: hi\r\n\r\nbody\r\n', rcpts=['a@sub.wild.org'])
        self.assertEqual(len(self.stored('a@sub.wild.org')), 1)

    def test_accepts_unknown_domain_when_discard_disabled(self):
        ms.DISCARD_UNKNOWN = False
        self.send(b'Subject: hi\r\n\r\nbody\r\n', rcpts=['a@other.net'])
        self.assertEqual(len(self.stored('a@other.net')), 1)

    def test_rejects_path_traversal_recipients(self):
        for rcpt in ['../../escape@example.com', 'a/b@example.com', '..@example.com', 'a@..example.com', 'a%b@example.com']:
            with self.client() as c:
                c.ehlo()
                c.mail('sender@sender.org')
                code, msg = c.rcpt(rcpt)
            self.assertEqual(code, 553, rcpt)
        self.assertEqual(os.listdir(ms.DATA_DIR), [])
        self.assertEqual(sorted(os.listdir(self.tmp)), ['data'])

    def test_recipient_is_lowercased(self):
        self.send(b'Subject: hi\r\n\r\nbody\r\n', rcpts=['Some.One+tag@Example.com'])
        self.assertEqual(self.stored('some.one+tag@example.com')[0]['rcpts'], ['some.one+tag@example.com'])

    # --- messages that used to be rejected ---

    def test_simple_message(self):
        self.send(b'From: Sender <sender@sender.org>\r\nSubject: =?utf-8?q?Gr=C3=BC=C3=9Fe?=\r\n\r\nHello\r\n')
        mail = self.stored('a@example.com')[0]
        self.assertEqual(mail['parsed']['subject'], 'Grüße')
        self.assertEqual(mail['parsed']['from'], 'Sender <sender@sender.org>')
        self.assertEqual(mail['parsed']['body'], 'Hello\r\n')
        self.assertEqual(mail['sender_ip'], '127.0.0.1')

    def test_8bit_latin1_message_without_charset(self):
        self.send(b'Subject: Gr\xfc\xdfe\r\n\r\nGr\xfc\xdfe\r\n')
        mail = self.stored('a@example.com')[0]
        self.assertIn('Grüße', mail['raw'])
        self.assertIn('Grüße', mail['parsed']['body'])
        self.assertIn('Grüße', mail['parsed']['subject'])

    def test_raw_utf8_headers(self):
        self.send('From: Jörg <j@sender.org>\r\nSubject: Grüße\r\n aus Wien\r\n\r\nHallo\r\n'.encode('utf-8'))
        mail = self.stored('a@example.com')[0]
        self.assertEqual(mail['parsed']['subject'], 'Grüße aus Wien')
        self.assertEqual(mail['parsed']['from'], 'Jörg <j@sender.org>')

    def test_declared_charset_is_respected(self):
        self.send(multipart(('Content-Type: text/plain; charset=koi8-r\r\nContent-Transfer-Encoding: 8bit',
                             'Привет'.encode('koi8-r'))))
        self.assertIn('Привет', self.stored('a@example.com')[0]['parsed']['body'])

    def test_long_lines(self):
        self.send(b'Subject: hi\r\n\r\n' + b'a' * 5000 + b'\r\n')
        self.assertIn('a' * 5000, self.stored('a@example.com')[0]['parsed']['body'])

    def test_forwarded_message_attachment(self):
        inner = b'From: y@z.org\r\nSubject: inner\r\n\r\ninner body\r\n'
        self.send(multipart(('Content-Type: text/plain', b'see attached'),
                            ('Content-Type: message/rfc822\r\nContent-Disposition: attachment', inner)))
        mail = self.stored('a@example.com')[0]
        self.assertEqual(mail['parsed']['body'].strip(), 'see attached')
        self.assertEqual(len(mail['parsed']['attachments']), 1)
        self.assertIn(b'inner body', self.attachment('a@example.com', mail['parsed']['attachments'][0]))

    def test_malicious_attachment_filenames_are_sanitized(self):
        self.send(multipart(('Content-Type: text/plain', b'body'),
                            ('Content-Type: application/octet-stream\r\nContent-Disposition: attachment; filename="../../../web/x.php"\r\n'
                             'Content-Transfer-Encoding: base64', b'AAAA'),
                            ('Content-Type: application/octet-stream\r\nContent-Disposition: attachment; filename="a/b.bin"\r\n'
                             'Content-Transfer-Encoding: base64', b'AAAA')))
        mail = self.stored('a@example.com')[0]
        for file_id in mail['parsed']['attachments']:
            self.assertNotIn('/', file_id)
            self.assertTrue(os.path.isfile(os.path.join(ms.DATA_DIR, 'a@example.com', 'attachments', file_id)))
        self.assertEqual(sorted(os.listdir(self.tmp)), ['data'])

    def test_same_attachment_name_in_two_mails_does_not_collide(self):
        for content in (b'Rmlyc3Q=', b'U2Vjb25k'):
            self.send(multipart(('Content-Type: text/plain', b'body'),
                                ('Content-Type: text/plain\r\nContent-Disposition: attachment; filename="doc.txt"\r\n'
                                 'Content-Transfer-Encoding: base64', content)))
        first, second = self.stored('a@example.com')
        self.assertNotEqual(first['parsed']['attachments'], second['parsed']['attachments'])
        self.assertEqual(self.attachment('a@example.com', first['parsed']['attachments'][0]), b'First')
        self.assertEqual(self.attachment('a@example.com', second['parsed']['attachments'][0]), b'Second')

    def test_duplicate_attachment_names_in_one_mail(self):
        self.send(multipart(('Content-Type: image/png; name="img.png"\r\nContent-Transfer-Encoding: base64', b'AAAA'),
                            ('Content-Type: image/png; name="img.png"\r\nContent-Transfer-Encoding: base64', b'AQEB')))
        ids = self.stored('a@example.com')[0]['parsed']['attachments']
        self.assertEqual(len(set(ids)), 2)

    def test_unnamed_attachment_gets_extension(self):
        self.send(multipart(('Content-Type: image/png\r\nContent-Transfer-Encoding: base64', b'AAAA')))
        self.assertTrue(self.stored('a@example.com')[0]['parsed']['attachments'][0].endswith('-untitled.png'))

    def test_inline_image_cid_points_to_stored_attachment(self):
        self.send(multipart(('Content-Type: text/html; charset=utf-8', b'<img src="cid:logo@x">'),
                            ('Content-Type: image/png\r\nContent-ID: <logo@x>\r\nContent-Disposition: inline; filename="logo.png"\r\n'
                             'Content-Transfer-Encoding: base64', b'AAAA')))
        mail = self.stored('a@example.com')[0]
        file_id = mail['parsed']['attachments'][0]
        self.assertEqual(mail['parsed']['htmlbody'], '<img src="/api/attachment/a@example.com/%s">' % file_id)
        details = mail['parsed']['attachments_details'][0]
        self.assertEqual(details['cid'], 'logo@x')
        self.assertEqual(details['download_url'], 'http://localhost:8080/api/attachment/a@example.com/' + file_id)

    def test_inline_image_links_include_web_ui_path(self):
        ms.URL = 'https://example.com/trash/'
        self.send(multipart(('Content-Type: text/html; charset=utf-8', b'<img src="cid:logo@x">'),
                            ('Content-Type: image/png\r\nContent-ID: <logo@x>\r\nContent-Transfer-Encoding: base64', b'AAAA')))
        mail = self.stored('a@example.com')[0]
        file_id = mail['parsed']['attachments'][0]
        self.assertEqual(mail['parsed']['htmlbody'], '<img src="/trash/api/attachment/a@example.com/%s">' % file_id)
        self.assertEqual(mail['parsed']['attachments_details'][0]['download_url'], 'https://example.com/trash/api/attachment/a@example.com/' + file_id)

    def test_attachment_too_large_is_rejected_with_proper_code(self):
        ms.ATTACHMENTS_MAX_SIZE = 2
        with self.assertRaises(smtplib.SMTPDataError) as ctx:
            self.send(multipart(('Content-Type: application/octet-stream; name="a.bin"\r\nContent-Transfer-Encoding: base64', b'AAAAAAAA')))
        self.assertEqual(ctx.exception.smtp_code, 552)

    def test_multiple_recipients_and_unique_ids(self):
        self.send(b'Subject: one\r\n\r\n1\r\n', rcpts=['a@example.com', 'b@example.com'])
        self.send(b'Subject: two\r\n\r\n2\r\n', rcpts=['a@example.com'])
        self.assertEqual(len(self.stored('a@example.com')), 2)
        self.assertEqual(len(self.stored('b@example.com')), 1)

    def test_internal_errors_are_temporary_and_do_not_leak(self):
        original = ms.CustomHandler.store_message
        ms.CustomHandler.store_message = lambda *a: 1 / 0
        try:
            with self.assertRaises(smtplib.SMTPDataError) as ctx:
                self.send(b'Subject: hi\r\n\r\nbody\r\n')
        finally:
            ms.CustomHandler.store_message = original
        self.assertEqual(ctx.exception.smtp_code, 451)
        self.assertNotIn(b'ZeroDivision', ctx.exception.smtp_error)

    def test_unparseable_message_is_still_stored(self):
        original = ms.CustomHandler.parse_message
        def broken(*a):
            raise ValueError('parser bug')
        ms.CustomHandler.parse_message = broken
        try:
            self.send(b'Subject: hi\r\n\r\nstill here\r\n')
        finally:
            ms.CustomHandler.parse_message = original
        mail = self.stored('a@example.com')[0]
        self.assertIn('still here', mail['parsed']['body'])
        self.assertEqual(mail['parsed']['attachments'], [])

    # --- cleanup ---

    def test_cleanup_keeps_webhook_config_and_removes_attachments(self):
        self.send(multipart(('Content-Type: application/octet-stream; name="a.bin"\r\nContent-Transfer-Encoding: base64', b'AAAA')))
        self.send(b'Subject: hi\r\n\r\nbody\r\n', rcpts=['b@example.com'])
        maildir = os.path.join(ms.DATA_DIR, 'a@example.com')
        with open(os.path.join(maildir, 'webhook.json'), 'w') as f:
            f.write('{}')
        old = time.time() - 3 * 86400
        for root, dirs, files in os.walk(ms.DATA_DIR):
            for name in files:
                os.utime(os.path.join(root, name), (old, old))
        ms.DELETE_OLDER_THAN_DAYS = 1
        ms.cleanup()
        self.assertEqual(os.listdir(maildir), ['webhook.json'])
        self.assertFalse(os.path.exists(os.path.join(ms.DATA_DIR, 'b@example.com')))


if __name__ == '__main__':
    unittest.main()
