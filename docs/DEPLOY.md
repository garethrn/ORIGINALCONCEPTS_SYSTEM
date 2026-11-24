# Deploying on the Contabo VPS

This app is a Docker Compose project named `oc-support`. It publishes **127.0.0.1:8085** only. It does not bind ports 80 or 443, and the scripts below always pass `-p oc-support`, so they do not stop or rebuild other containers.

The database is MySQL 8 on an internal Docker network. It has no host port. Uploads live in the named volume `oc-support-storage`. The database lives in `oc-support-mysql`.

## Pre-flight (read only)

On the VPS, from a clone of this repo:

```bash
bash deploy/preflight.sh
```

Check:

- What is already listening, especially 80, 443, and 8085. If 8085 is taken, pick another port in `.env` as `APP_BIND_PORT` and use that same port in the proxy snippet.
- Whether the public web server is nginx or Apache.
- That `docker` and `docker compose` run.

Do not edit the other sites' server blocks.

## First deploy

```bash
git clone <this-repo> /opt/oc-support
cd /opt/oc-support
cp .env.example .env
php artisan key:generate --show
```

Put the key into `.env` as `APP_KEY`. Set `DB_PASSWORD` and `DB_ROOT_PASSWORD` to long random values. Set the SMTP password. Leave `APP_DEBUG=false`. Leave `AI_API_KEY` empty until you want the assistant. Do not put an admin password in `.env`.

```bash
bash deploy/update.sh
```

That builds the image, starts `app`, `queue`, `scheduler`, and `mysql`, and runs migrations.

Create the first administrator. The password is typed into the terminal or passed for that one process, then forgotten:

```bash
docker compose -p oc-support -f docker-compose.prod.yml exec -it app php artisan app:create-admin
```

Or, without saving it in a file:

```bash
docker compose -p oc-support -f docker-compose.prod.yml exec \
  -e ADMIN_NAME="Gareth Naidoo" \
  -e ADMIN_EMAIL="you@originalconcepts.co.za" \
  -e ADMIN_PASSWORD="a long mixed password" \
  app php artisan app:create-admin --no-interaction
```

Unset that password from the shell history afterwards. Sign in and set the company legal name, address, and VAT number under Settings before issuing a tax invoice. Optional starter catalogue rows (IT support, cameras, and so on) are added with:

```bash
docker compose -p oc-support -f docker-compose.prod.yml exec -T app php artisan db:seed --force
```

Seeding again does not overwrite prices you have edited.

## Reverse proxy

The container listens on port 8080 inside Docker. Compose publishes that as `127.0.0.1:8085` on the host. Point only `support.originalconcepts.co.za` at it.

- nginx: `deploy/nginx-support.conf`
- Apache: `deploy/apache-support.conf`

Certificate, this hostname only:

```bash
sudo certbot --nginx -d support.originalconcepts.co.za
# or
sudo certbot --apache -d support.originalconcepts.co.za
```

Do not expand the certificate to the other names on the server. After HTTPS works, requests arrive with `X-Forwarded-Proto`. The app trusts the local proxy (`TRUSTED_PROXIES=*`) because nothing else can reach the published port.

Health: `curl -fsS http://127.0.0.1:8085/health` returns `{"status":"ok"}` when the app can see its database.

## Updates

```bash
cd /opt/oc-support
git pull
bash deploy/update.sh
```

`update.sh` builds, recreates the oc-support services, runs `php artisan migrate --force`, refreshes the framework cache, and restarts queue workers. It does not prune Docker and it does not name any other project.

## Backups

```bash
bash deploy/backup.sh /var/backups/oc-support
```

A daily cron that only dumps this database:

```cron
15 2 * * * cd /opt/oc-support && bash deploy/backup.sh /var/backups/oc-support
```

Keep a copy off the VPS. Restoring is a `gunzip` into `docker compose -p oc-support exec -T mysql mysql ...` for this database only.

## Environment variables

| Name | Secret | Purpose |
| --- | --- | --- |
| APP_NAME | no | Shown in the browser title and mail |
| APP_ENV | no | `production` on the VPS |
| APP_KEY | yes | Laravel encryption key |
| APP_DEBUG | no | Must stay `false` in production |
| APP_URL | no | `https://support.originalconcepts.co.za` |
| APP_TIMEZONE | no | `Africa/Johannesburg` |
| DB_CONNECTION | no | `mysql` |
| DB_HOST | no | `mysql` inside Compose |
| DB_PORT | no | `3306` |
| DB_DATABASE | no | `oc_support` |
| DB_USERNAME | no | `oc_support` |
| DB_PASSWORD | yes | App database user |
| DB_ROOT_PASSWORD | yes | MySQL root, this container only |
| SESSION_DRIVER | no | `database` |
| SESSION_ENCRYPT | no | `true` |
| SESSION_SECURE_COOKIE | no | Leave empty so HTTPS is detected from the proxy |
| SESSION_HTTP_ONLY | no | `true` |
| SESSION_SAME_SITE | no | `lax` |
| QUEUE_CONNECTION | no | `database` |
| CACHE_STORE | no | `database` |
| MAIL_MAILER | no | `smtp` |
| MAIL_HOST | no | SMTP host |
| MAIL_PORT | no | Usually 587 |
| MAIL_USERNAME | no | SMTP user |
| MAIL_PASSWORD | yes | SMTP password |
| MAIL_FROM_ADDRESS | no | From address on outgoing mail |
| MAIL_FROM_NAME | no | From name |
| APP_BIND_PORT | no | Host port, default 8085, bound to 127.0.0.1 |
| TRUSTED_PROXIES | no | `*` because the port is localhost-only |
| AI_DRIVER | no | `openai` in production. `fake` is a local demo only |
| AI_API_KEY | yes | Provider key. Empty disables the assistant |
| AI_MODEL | no | Model name the provider expects |
| AI_BASE_URL | no | OpenAI-compatible base URL, default `https://api.openai.com/v1` |

`ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` are read by `app:create-admin` from the process environment. They are not application settings and should not be written into `.env`.

## Install on iPhone or iPad

HTTPS is required. In Safari, open `https://support.originalconcepts.co.za`, tap Share, then **Add to Home Screen**. The icon opens the app full screen. A new deploy updates the installed copy the next time it is opened online.

## What you still need to supply

- DNS for `support.originalconcepts.co.za` pointing at this VPS.
- The SMTP mailbox and password.
- The company legal name, street address, and VAT number (entered in Settings, not in git).
- The first administrator's email and a password you choose at create time.
- An AI API key only if you want the assistant. Any OpenAI-compatible endpoint works.
