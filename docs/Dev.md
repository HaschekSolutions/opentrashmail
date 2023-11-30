# Local dev

## Web UI

For testing the web UI you need to have php installed. From within the `web/` directory run `php -S localhost:8080 index.php` then you will be able to access the UI via http://localhost:8080

Since OpenTrashmail does not use a database, it will work right away but you won't be able to receive emails without running the python SMTP server. 

## Mailserver

In combination with the PHP command from above you can use docker to run the mail server (since you probably don't have python2 installed on your machine).

From the root directory run:

```bash
docker build -f docker/Dockerfile -t opentrashmail .
docker run --rm -it --name trashmail -p 2525:25 \
-v $( pwd )/data:/var/www/opentrashmail/data \
-v $( pwd )/logs:/var/www/opentrashmail/logs \
-v $( pwd )/config.ini:/var/www/opentrashmail/config.ini:ro opentrashmail
```

This binds the mailserver on port 2525 and also mounts the local data directory and your `config.ini` to the container. So emails you receive will show up in your `data` folder.

## Sending debug emails from the command line

Using the text file `tools/testmail.txt` and the following line of bash you can send emails to your python mailserver and test if it's acceping emails like you want.

Note that if you change cour config.ini, the mail server needs to be restarted before it takes effect.

```bash
cat "tools/testmail.txt" | while read L; do sleep "0.2"; echo "$L"; done  | "nc" -C -v "localhost" "2525"
```

### Via TLS

Testing with the TLS version (non-plaintext).
Needs config options `MAILPORT_TLS`, `TLS_CERTIFICATE` and `TLS_PRIVATE_KEY` set.

```bash
echo 'Testing' | swaks --to test@example.com --from "something@example.com" --server localhost --port 2525 -tlsc
```

### Via STARTTLS

STARTTOS runs on the default plaintext port and is just a option for servers to upgrade to TLS but starts in plaintext.
Needs config options `TLS_CERTIFICATE` and `TLS_PRIVATE_KEY` set.

Testing STARTTLS version
```bash
echo 'Testing' | swaks --to test@example.com --from "something@example.com" --server localhost --port 465 -tlsc
```
