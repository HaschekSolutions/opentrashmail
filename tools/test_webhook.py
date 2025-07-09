#!/usr/bin/env python3
"""
Test script for OpenTrashmail webhook functionality
This script sets up a test webhook receiver and can send test emails
"""

import asyncio
import json
import hmac
import hashlib
import argparse
from aiohttp import web
import aiohttp
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

# Global to store received webhooks
received_webhooks = []

async def webhook_handler(request):
    """Handle incoming webhook requests"""
    data = await request.json()
    
    # Check signature if provided
    signature = request.headers.get('X-Webhook-Signature')
    if signature and args.secret:
        expected_sig = hmac.new(
            args.secret.encode('utf-8'),
            await request.text(),
            hashlib.sha256
        ).hexdigest()
        
        if signature != expected_sig:
            print(f"⚠️  Invalid signature! Expected: {expected_sig}, Got: {signature}")
    
    received_webhooks.append(data)
    print(f"\n✅ Webhook received!")
    print(f"From: {data.get('from', 'N/A')}")
    print(f"To: {data.get('email', 'N/A')}")
    print(f"Subject: {data.get('subject', 'N/A')}")
    print(f"Body preview: {data.get('body', '')[:100]}...")
    if data.get('attachments'):
        print(f"Attachments: {len(data.get('attachments', []))}")
    print("-" * 50)
    
    return web.Response(text="OK", status=200)

async def setup_webhook_config(email, webhook_url, secret=None):
    """Configure webhook for the given email address"""
    config = {
        'enabled': 'true',
        'webhook_url': webhook_url,
        'payload_template': json.dumps({
            "email": "{{to}}",
            "from": "{{from}}",
            "subject": "{{subject}}",
            "body": "{{body}}",
            "timestamp": "{{sender_ip}}",
            "attachments": "{{attachments}}"
        }),
        'max_attempts': '5',
        'backoff_multiplier': '2'
    }
    
    if secret:
        config['secret_key'] = secret
    
    async with aiohttp.ClientSession() as session:
        url = f"{args.opentrashmail_url}/api/webhook/save/{email}"
        async with session.post(url, data=config) as resp:
            result = await resp.json()
            if result.get('success'):
                print(f"✅ Webhook configured for {email}")
            else:
                print(f"❌ Failed to configure webhook: {result.get('message')}")

def send_test_email(to_email, smtp_host, smtp_port):
    """Send a test email"""
    msg = MIMEMultipart()
    msg['From'] = 'test@example.com'
    msg['To'] = to_email
    msg['Subject'] = 'Test Webhook Email'
    
    body = "This is a test email to verify webhook functionality.\n\nIf you receive this in your webhook, everything is working!"
    msg.attach(MIMEText(body, 'plain'))
    
    try:
        with smtplib.SMTP(smtp_host, smtp_port) as server:
            server.send_message(msg)
        print(f"✅ Test email sent to {to_email}")
    except Exception as e:
        print(f"❌ Failed to send email: {e}")

async def main():
    # Start webhook receiver
    app = web.Application()
    app.router.add_post('/webhook', webhook_handler)
    
    runner = web.AppRunner(app)
    await runner.setup()
    site = web.TCPSite(runner, '0.0.0.0', args.webhook_port)
    await site.start()
    
    webhook_url = f"http://{args.webhook_host}:{args.webhook_port}/webhook"
    print(f"🚀 Webhook receiver started at {webhook_url}")
    
    # Configure webhook in OpenTrashmail
    await setup_webhook_config(args.email, webhook_url, args.secret)
    
    # Send test email if requested
    if args.send_email:
        await asyncio.sleep(1)  # Give server time to start
        send_test_email(args.email, args.smtp_host, args.smtp_port)
    
    print("\n⏳ Waiting for webhooks... Press Ctrl+C to stop")
    
    try:
        await asyncio.Event().wait()
    except KeyboardInterrupt:
        print(f"\n\n📊 Summary: Received {len(received_webhooks)} webhooks")

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Test OpenTrashmail webhook functionality')
    parser.add_argument('email', help='Email address to test')
    parser.add_argument('--opentrashmail-url', default='http://localhost:8080', 
                        help='OpenTrashmail URL (default: http://localhost:8080)')
    parser.add_argument('--webhook-host', default='localhost',
                        help='Host for webhook receiver (default: localhost)')
    parser.add_argument('--webhook-port', type=int, default=8888,
                        help='Port for webhook receiver (default: 8888)')
    parser.add_argument('--smtp-host', default='localhost',
                        help='SMTP host for OpenTrashmail (default: localhost)')
    parser.add_argument('--smtp-port', type=int, default=25,
                        help='SMTP port for OpenTrashmail (default: 25)')
    parser.add_argument('--secret', help='Secret key for HMAC signature')
    parser.add_argument('--send-email', action='store_true',
                        help='Send a test email after setup')
    
    args = parser.parse_args()
    
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\n👋 Goodbye!")