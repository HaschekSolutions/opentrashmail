# Changelog

## V1.6.0
- Fixed emails being rejected by the mail server: 8-bit/non UTF-8 messages, lines longer than 1000 characters, forwarded emails (message/rfc822 attachments) and attachments with a `/` in the filename
- Fixed path traversal: recipient addresses and attachment filenames could write files outside of the data directory
- Recipients are now validated and (with `DISCARD_UNKNOWN=true`) foreign domains are rejected during the SMTP dialog instead of being accepted and silently dropped
- The SMTP greeting no longer announces "Python SMTP" and uses a real hostname instead of the container id. New setting `SMTP_HOSTNAME`
- Internal errors now answer with a temporary `451` (sender retries) instead of a permanent `500` that exposed Python exception names
- Declared charsets of text parts are respected, raw UTF-8/8-bit headers are decoded properly
- Attachments with the same name no longer overwrite each other and inline images (`cid:`) work again
- Webhooks no longer delay the SMTP response, cleanup runs on a timer and no longer deletes webhook configs or leaves attachments behind
- Mail server listens on IPv6 as well if available
- HTML emails are rendered in a sandboxed iframe, attachments are sent with a sandbox CSP, fixed several XSS issues in templates and the log viewer
- `X-Forwarded-For` and similar headers are only trusted from private networks or `TRUSTED_PROXIES`, before `ALLOWED_IPS` could be bypassed with a fake header
- Passwords via GET parameter work again (nginx dropped the query string)
- Web UI, API and RSS are no longer indexed by search engines, referrers are no longer sent, nginx and PHP versions are hidden
- Docker image is now based on Alpine 3.22 with PHP 8.3

## V1.5.0
- Added per-email webhook configuration with customizable JSON payloads
- Implemented webhook retry mechanism with exponential backoff
- Added HMAC signature support for webhook security
- Created web UI for webhook configuration management
- Maintained backward compatibility with global webhook configuration

## V1.4.0
- Added support for webhooks
- Moved account list and logs to admin site with optional passwords

## V1.3.0
- Added TLS and STARTTLS support
- Various bug fixes and docs updates

## V1.2.6
- Fixed link to raw email in RSS template
- Added version string to branding part of the nav
- Fixed bug with double "v" in the version string

## V1.2.3
- Fixed attachment deletion bug
- Fixed random email generation

## V1.2.0
 - Implemented IP/Subnet filter using the config option `ALLOWED_IPS`
 - Implemented Password authentication of the site and API using config option `PASSWORD`
 - Implemented max attachment size as mentioned in [#63](https://github.com/HaschekSolutions/opentrashmail/issues/63)
 - Reworked the navbar header to look better on smaller screens

## V1.1.5
- Added support for plaintext file attachments
- Updated the way attachments are stored. Now it's md5 + filename

## V1.1.4
- Fixed crash when email contains attachment

## V1.1.3
- Switched SMTP server to Python3 and aiosmptd
- Switched PHP backend to PHP8.1
- Implemented content-id replacement with smart link to API so embedded images will now work
- Updated JSON to include details about attachments (filename,size in bytes,id,cid and a download URL)
- Removed quotes from ini settings
- Made docker start script more neat

## V1.0.0
- Launch of V1.0.0
- Complete rewrite of the GUI
- Breaking: New API (/rss, /json, /api) instead of old `api.php` calls
