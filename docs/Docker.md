# Quick testing

From the `docker` directory run

```bash
docker build -t hascheksolutions/opentrashmail . && docker run --rm -it --name trashmail -p 3000:80 -p 2525:25 hascheksolutions/opentrashmail
```

And check if it works on http://localhost:3000