<p align="center">
  <a href="" rel="noopener">
 <img height=200px src="https://raw.githubusercontent.com/HaschekSolutions/opentrashmail/master/web/imgs/logo_300.png" alt="Open Trashmail"></a>
</p>

<h1 align="center">Open Trashmail</h1>

<div align="center">

![](https://img.shields.io/badge/php-7.1%2B-brightgreen.svg)
![](https://img.shields.io/badge/python-2.7%2B-brightgreen.svg)
[![](https://img.shields.io/docker/pulls/hascheksolutions/opentrashmail?color=brightgreen)](https://hub.docker.com/r/hascheksolutions/opentrashmail)
[![](https://img.shields.io/docker/cloud/build/hascheksolutions/opentrashmail?color=brightgreen)](https://hub.docker.com/r/hascheksolutions/opentrashmail/builds)
[![Apache License](https://img.shields.io/badge/license-Apache-blue.svg?style=flat)](https://github.com/HaschekSolutions/opentrashmail/blob/master/LICENSE)
[![HitCount](http://hits.dwyl.io/HaschekSolutions/opentrashmail.svg)](http://hits.dwyl.io/HaschekSolutions/opentrashmail)
[![](https://img.shields.io/github/stars/HaschekSolutions/opentrashmail.svg?label=Stars&style=social)](https://github.com/HaschekSolutions/opentrashmail)

#### Host your own `trashmail` solution to use with your own domains or subdomains

</div>

![Screenshot of Open Trashmail](https://pictshare.net/shz4tq.png)

# Roadmap
- [x] Mailserver
  - [x] Storing received mails in JSON
  - [x] Storing file attachments
- [x] Docker files and configs
- [ ] Web interface
  - [x] Choose email
  - [x] Get random email address
  - [x] Download attachments in a safe way
  - [x] Display Text/HTML
  - [x] API so all features from the site can also be automated and integrated
  - [x] Automatically check for new emails while on site
  - [ ] Secure HTML so no malicious things can be loaded
  - [ ] Display embedded images inline using Content-ID
  - [ ] Admin overview for all available email addresses
  - [ ] Delete messages
  - [ ] Make better theme
- [ ] Configurable settings
  - [x] Choose domains for random generation
  - [ ] Choose if out-of-scope emails are discarded
  - [ ] Honeypot mode where all emails are also saved for a catchall account
  - [ ] Optionally secure whole site with a password
  - [ ] Optinally allow site to be seen only from specific IP Range

# Features
- Python powered mail server that works out of the box for any domain you throw at it
- Web interface to manage emails
- Generate random email adresses
- 100% file based, no database needed

# Quick start

Simple start with no persistence

```bash
docker run --it -p 25:25 -p 80:80 hascheksolutions/opentrashmail
```

Saving data directory on host machine

```bash
docker run -p 80:80 -p 25:25 -v /path/on/host/where/to/save/data:/var/www/opentrashmail/data hascheksolutions/opentrashmail
```

Complete example with running as daemon, persistence and auto restart

```bash
docker run -d --restart=always --name opentrashmail -p 80:80 -p 25:25 -v /path/on/host/where/to/save/data:/var/www/opentrashmail/data hascheksolutions/opentrashmail
```

# How it works

The heart of Open Trashmail is a **python powered SMTP server** that listens on incoming emails and stores them as json objects. The server doesn't have to know the right Email domain, it will just **catch everything** it receives. You only have to **expose port 25 to the web** and set an **MX record** of your domain pointing to the IP adress of your machine.

The server then saves all received emails as JSON objects and the web interface can access it.