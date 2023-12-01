# Changelog

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
