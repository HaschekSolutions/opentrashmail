# Quick testing

From the main directory run

```bash
docker build -f docker/Dockerfile -t opentrashmail . && docker run --rm -it --name trashmail -p 3000:80 -p 2525:25 opentrashmail
```

And check if it works on http://localhost:3000

## Sending debug emails from the command line

Using the text file `tools/testmail.txt` and the following line of bash you can send emails to your server and test if it's acceping emails like you want.

Note that if you change cour config.ini, the mail server needs to be restarted before it takes effect.

```bash
cat "tools/testmail.txt" | while read L; do sleep "0.2"; echo "$L"; done  | "nc" -C -v "localhost" "2525"
```