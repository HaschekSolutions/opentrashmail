# Changelog

## Nov 19th 2023 - V1.1.3
- Switched SMTP server to Python3 and aiosmptd
- Switched PHP backend to PHP8.1
- Implemented content-id replacement with smart link to API so embedded images will now work
- Updated JSON to include details about attachments (filename,size in bytes,id,cid and a download URL)
- Removed quotes from ini settings
- Made docker start script more neat

## Nov 13th 2023 - V1.0.0
- Launch of V1.0.0
- Complete rewrite of the GUI
- Breaking: New API (/rss, /json, /api) instead of old `api.php` calls
