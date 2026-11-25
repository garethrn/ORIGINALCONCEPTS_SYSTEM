# Original Concepts Support

Ticketing, customers, and billing for [Original Concepts](https://www.originalconcepts.co.za). Staff sign in, log work, and turn it into quotes, tax invoices, and credit notes. Customers can see their own tickets and issued documents.

The app is Laravel 13 on PHP 8.3. It is meant to run in Docker on the company VPS, behind the web server that is already there, on `https://support.originalconcepts.co.za`.

## What it does

- Roles: admin, finance, manager, technician, and client. A client login is linked to a customer record.
- Customers, contacts, sites (address and GPS), VAT number, and payment terms.
- Tickets with categories, priority, a fixed status workflow, assignment, public and internal comments, a timeline, private files, an SLA due time, and email.
- A product catalogue (stock, service, labour, subscription, print) with a VAT code of standard 15%, zero-rated, or exempt.
- Quotes, tax invoices, and credit notes. Money is stored in cents. Line VAT is calculated per line. Prices can be entered including or excluding VAT. Numbers (`QUO-`, `INV-`, `CN-`) are assigned only when a document is issued, with no gaps. An issued invoice cannot be edited. A correction is a credit note.
- Manual payments (EFT, cash, card, PayFast, Yoco) allocated across invoices, including part payments, plus a debtors list.
- Time and parts on a ticket, which can be billed onto a draft invoice.
- An audit log of who changed what, and the values before and after.
- A staff assistant that turns a sentence into a draft invoice, quote, or ticket. A person has to confirm before anything is issued or emailed.
- A progressive web app so the site can be installed on an iPhone or iPad.

PayFast, Yoco, and recurring subscriptions are extension points only. Nothing calls those services.

## Local checks

```bash
composer install
cp .env.example .env
php artisan key:generate
# For a laptop, sqlite is enough: set DB_CONNECTION=sqlite and DB_DATABASE=database/database.sqlite
touch database/database.sqlite
php artisan migrate
npm install
npm run build
php artisan test
php artisan app:create-admin
```

`app:create-admin` asks for the name, email, and password. There is no default password in the repository. For a script, set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in the environment of that one command and run `php artisan app:create-admin --no-interaction`. Do not store `ADMIN_PASSWORD` in `.env`.

## Deploy

See [docs/DEPLOY.md](docs/DEPLOY.md). The production compose project is named `oc-support` and listens only on `127.0.0.1:8085`. If `docker-compose.override.yml` is present, `deploy/update.sh` and `deploy/backup.sh` include it (used to join the Caddy network). A sample is `docker-compose.override.example.yml`.

## Layout

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Install on iPhone or iPad

The site has to be opened over HTTPS.

1. Open `https://support.originalconcepts.co.za` in Safari.
2. Tap the Share button.
3. Tap **Add to Home Screen**.
4. Open Original Concepts Support from the home screen. It opens without the Safari toolbar.

The service worker keeps the shell and static files for a short offline message. Tickets, invoices, and anything you submit still need a connection. After a deploy, the next visit picks up the new version.
