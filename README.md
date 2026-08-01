# Original Concepts Support System

A full-featured IT support ticket and billing management system built for [Original Concepts](https://www.originalconcepts.co.za) — a company providing IT Support, Web Development, WhatsApp Development, Software Development, and more.

## 🚀 Quick Start

See **[INSTALL.md](INSTALL.md)** for the complete installation guide.

**Target URL:** `https://www.originalconcepts.co.za/support`

## ✨ Features

- **Ticket System** — Create, assign, update, and close support tickets with priority levels, categories, comments, internal notes, and file attachments
- **Client Management** — Store client details, GPS location, address; view all tickets and invoices per client with Google Maps navigation
- **Invoicing** — Generate professional invoices with line items, VAT (15%), print/PDF; mark as paid
- **Quoting** — Create quotes that convert to invoices on acceptance
- **QuickBooks Integration** — OAuth2 sync of invoices and quotes to QuickBooks Online
- **Payment Tracking** — Dashboard showing outstanding, overdue, and paid totals
- **Location** — GPS coordinates for clients with Google Maps navigation for field technicians
- **Email Notifications** — Auto email alerts to clients on ticket create/update/resolve
- **Role-based Access** — Admin, Manager, Technician, Client roles
- **Web Installer** — One-page setup wizard for easy deployment on shared hosting

## 🛠️ Tech Stack

- PHP 7.4+ (no framework — pure PHP, easy to deploy on any shared host)
- MySQL 5.7+ / MariaDB 10.3+
- Bootstrap 5 + Bootstrap Icons (CDN)

## 📁 Structure

```
support/                ← Deploy to public_html/support/
├── index.php           ← Dashboard
├── auth/               ← Login / logout
├── tickets/            ← Ticket management
├── clients/            ← Client management  
├── invoices/           ← Invoice management
├── quotes/             ← Quote management
├── payments/           ← Payment tracking
├── quickbooks/         ← QuickBooks OAuth2 integration
├── users/              ← User management
├── install/            ← Web installer (DELETE after setup)
├── config/             ← Configuration files
├── includes/           ← Shared PHP includes
└── assets/             ← CSS, JS, images
```

## 📖 Installation

1. Upload the `support/` folder to `public_html/support/` on your web host
2. Create a MySQL database via cPanel
3. Visit `https://www.originalconcepts.co.za/support/install/`
4. Complete the web installer form
5. **Delete the `/install` folder** immediately after setup

Full step-by-step guide: **[INSTALL.md](INSTALL.md)**

## 🔐 Default Credentials (manual SQL import only)

- **Email:** `admin@originalconcepts.co.za`
- **Password:** `Admin@1234` ← **Change this immediately after first login!**

## 📄 License

Proprietary — Original Concepts © 2024. All rights reserved.
