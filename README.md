<p align="center">
  <a href="" rel="noopener">
 <img height=200px src="https://raw.githubusercontent.com/HaschekSolutions/opentrashmail/master/web/imgs/logo_300_roundbg.png" alt="Open Trashmail"></a>
</p>

<h1 align="center">Open Trashmail</h1>



<div align="center">
  
![](https://img.shields.io/badge/php-7%2B-brightgreen.svg)
![](https://img.shields.io/badge/python-2.7%2B-brightgreen.svg)
![](https://img.shields.io/badge/made%20with-htmx-brightgreen.svg)
[![](https://img.shields.io/docker/pulls/hascheksolutions/opentrashmail?color=brightgreen)](https://hub.docker.com/r/hascheksolutions/opentrashmail)
[![](https://github.com/hascheksolutions/opentrashmail/actions/workflows/build-docker.yml/badge.svg?color=brightgreen)](https://github.com/HaschekSolutions/opentrashmail/actions)
[![Apache License](https://img.shields.io/badge/license-Apache-blue.svg?style=flat)](https://github.com/HaschekSolutions/opentrashmail/blob/master/LICENSE)
[![Hits](https://hits.seeyoufarm.com/api/count/incr/badge.svg?url=https%3A%2F%2Fgithub.com%2FHaschekSolutions%2Fopentrashmail&count_bg=%2379C83D&title_bg=%23555555&icon=&icon_color=%23E7E7E7&title=hits&edge_flat=false)](https://hits.seeyoufarm.com)
[![](https://img.shields.io/github/stars/HaschekSolutions/opentrashmail.svg?label=Stars&style=social)](https://github.com/HaschekSolutions/opentrashmail)

#### Selfhosted `trashmail` solution - Receive Emails via `Web UI`, `JSON API` and `RSS feed`
  
</div>


![Screenshot of Open Trashmail](https://pictshare.net/9tim7k.png)

# Features
- Python-powered mail server that works out of the box for any domain you throw at it
- RSS feed for every email address
- JSON API for integrating it in your own projects. Can be used to automate 2fa emails
- Handles attachments
- Web interface to manage emails
- Generates random email addresses
- 100% file based, no database needed
- Can be used as Email Honeypot

# JSON API

| Endpoint                   | Explanation                                                                                                                                                                                           | Example output                   |
|----------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------|
| /json/`[email-address]`      | Returns an array of received emails with links to the attachments and the parsed text based body of the email. If `ADMIN` email is entered, will return all emails of all accounts                    | [![](https://pictshare.net/100x100/sflw6t.png)](https://pictshare.net/sflw6t.png) |
| /json/`[email-address]/[id]` | To see all the data of a received email, take the ID from the previous call and poll this to get the raw and HTML body of the email. Can be huge since the body can contain all attachments in base64 | [![](https://pictshare.net/100x100/eltku4.png)](https://pictshare.net/eltku4.png) |
| /json/listaccounts         | If `SHOW_ACCOUNT_LIST` is set to true in the config.ini, this endpoint will return an array of all email addresses which have received at least one email                                             | [![](https://pictshare.net/100x100/u6agji.png)](https://pictshare.net/u6agji.png) |


# Configuration
Just edit the `config.ini` You can use the following settings

- `URL` -> The url under which the GUI will be hosted. No tailing slash! example: https://trashmail.mydomain.eu
- `DOMAINS` -> Comma separated list of domains this mail server will be receiving emails on. It's just so the web interface can generate random addresses
- `MAILPORT`-> The port the Python-powered SMTP server will listen on. `Default: 25`
- `ADMIN` -> An email address (doesn't have to exist, just has to be valid) that will list all emails of all addresses the server has received. Kind of a catch-all
- `DATEFORMAT` -> How should timestamps be shown on the web interface ([moment.js syntax](https://momentjs.com/docs/#/displaying/))

# Roadmap
- [x] Mail server
  - [x] Storing received mails in JSON
  - [x] Storing file attachments
- [x] Docker files and configs
- [ ] Web interface
  - [x] Choose email
  - [x] Get random email address
  - [x] Download attachments safely
  - [x] Display Text/HTML
  - [x] API so all features from the site can also be automated and integrated
  - [x] Automatically check for new emails while on site
  - [x] Admin overview for all available email addresses
  - [x] Option to show raw email
  - [x] Delete messages
  - [x] Make better theme
  - [x] Secure HTML, so no malicious things can be loaded
  - [ ] Display embedded images inline using Content-ID
- [ ] Configurable settings
  - [x] Choose domains for random generation
  - [x] Choose if out-of-scope emails are discarded
  - [x] Automated cleanup of old mails
  - [ ] Honeypot mode where all emails are also saved for a catchall account
  - [ ] Optionally secure whole site with a password
  - [ ] Optionally allow site to be seen only from specific IP Range

# Quick start

Simple start with no persistence

```bash
docker run -it -p 25:25 -p 80:80 ghcr.io/hascheksolutions/opentrashmail
```

Saving data directory on host machine

```bash
docker run -p 80:80 -p 25:25 -v /path/on/host/where/to/save/data:/var/www/opentrashmail/data ghcr.io/hascheksolutions/opentrashmail
```

Complete example with running as daemon, persistence, a domain for auto-generation of emails, acceptng only emails for configured domains, cleanup for mails older than 90 days and auto restart

```bash
docker run -d --restart=unless-stopped --name opentrashmail -e "DOMAINS=mydomain.eu" -e "DATEFORMAT='D.M.YYYY HH:mm'" -e "DISCARD_UNKNOWN=false" -e "DELETE_OLDER_THAN_DAYS=90" -p 80:80 -p 25:25 -v /path/on/host/where/to/save/data:/var/www/opentrashmail/data ghcr.io/hascheksolutions/opentrashmail
```

# How it works

The heart of Open Trashmail is a **Python-powered SMTP server** that listens on incoming emails and stores them as JSON files. The server doesn't have to know the right email domain, it will just **catch everything** it receives. You only have to **expose port 25 to the web** and set an **MX record** of your domain pointing to the IP address of your machine.
