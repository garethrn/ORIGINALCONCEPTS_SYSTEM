# Original Concepts Support System — Installation Guide

**Target URL:** `https://www.originalconcepts.co.za/support`

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [File Upload via FTP/cPanel](#file-upload)
3. [Database Setup](#database-setup)
4. [Run the Web Installer](#web-installer)
5. [Manual Configuration](#manual-configuration)
6. [QuickBooks Integration Setup](#quickbooks-integration)
7. [Email / SMTP Setup](#email-setup)
8. [Post-Installation Security](#post-installation-security)
9. [First Login & Initial Setup](#first-login)
10. [Feature Overview](#feature-overview)
11. [Troubleshooting](#troubleshooting)

---

## 1. Prerequisites <a name="prerequisites"></a>

Your hosting environment must support:

| Requirement             | Minimum Version |
|-------------------------|-----------------|
| PHP                     | 7.4 or higher   |
| MySQL / MariaDB         | 5.7 / 10.3      |
| Apache with mod_rewrite | Any             |
| PHP extensions          | PDO, pdo_mysql, json, curl, mbstring |
| SSL Certificate         | Required (HTTPS) |
| Disk Space              | 50 MB minimum   |

Most shared hosting packages (cPanel) with PHP 7.4+ will meet these requirements.

---

## 2. File Upload via FTP/cPanel <a name="file-upload"></a>

### Option A: cPanel File Manager (Recommended for beginners)

1. Log into your **cPanel** at `https://cpanel.originalconcepts.co.za` (or your hosting provider's URL).
2. Open **File Manager**.
3. Navigate to `public_html/`.
4. Create a new folder called `support` (if it doesn't exist).
5. Click **Upload** and upload the entire `support/` folder contents into `public_html/support/`.

### Option B: FTP Upload

1. Use an FTP client (e.g., **FileZilla** — free download from filezilla-project.org).
2. Connect using your FTP credentials:
   - **Host:** `ftp.originalconcepts.co.za`
   - **Username:** Your cPanel FTP username
   - **Password:** Your cPanel FTP password
   - **Port:** `21` (or `22` for SFTP)
3. Navigate to `/public_html/` on the remote side.
4. Upload the `support/` folder from this repository to `/public_html/support/`.

### Expected Directory Structure After Upload

```
public_html/
└── support/
    ├── index.php          ← Dashboard
    ├── .htaccess
    ├── config/
    │   ├── config.php     ← Main config
    │   ├── database.php   ← DB credentials
    │   └── .htaccess
    ├── includes/
    ├── assets/
    │   ├── css/
    │   └── js/
    ├── auth/
    ├── tickets/
    ├── clients/
    ├── invoices/
    ├── quotes/
    ├── payments/
    ├── quickbooks/
    ├── users/
    ├── uploads/           ← Create this folder (chmod 755)
    └── install/
```

> **Important:** Create an `uploads/` folder in `public_html/support/` and set permissions to **755**.

---

## 3. Database Setup <a name="database-setup"></a>

### Create a MySQL Database via cPanel

1. In cPanel, go to **MySQL Databases**.
2. Create a new database: `oc_support`
3. Create a new user: `oc_user` with a **strong password** (save this password).
4. Add the user to the database with **All Privileges**.
5. Note down:
   - **DB Host:** `localhost` (usually)
   - **DB Name:** `oc_support` (or with cPanel prefix e.g. `origco_oc_support`)
   - **DB User:** `oc_user` (or with prefix e.g. `origco_oc_user`)
   - **DB Password:** the password you set

---

## 4. Run the Web Installer <a name="web-installer"></a>

The easiest way to install:

1. Open your browser and go to:
   ```
   https://www.originalconcepts.co.za/support/install/
   ```

2. The installer will check your server requirements. All items should show **✓ OK**.

3. Click **Continue to Configuration**.

4. Fill in the form:
   - **DB Host:** `localhost`
   - **Database Name:** `oc_support` (or your cPanel-prefixed name)
   - **DB Username:** `oc_user`
   - **DB Password:** your database password
   - **Admin Name:** `System Administrator`
   - **Admin Email:** `admin@originalconcepts.co.za`
   - **Admin Password:** A strong password (min 8 chars, save this!)
   - **Site URL:** `https://www.originalconcepts.co.za/support`

5. Click **Install Now**.

6. On success, you will see a confirmation message.

> ⚠️ **IMMEDIATELY delete the `/install` folder after installation is complete!**

---

## 5. Manual Configuration <a name="manual-configuration"></a>

If you prefer to configure manually (instead of using the installer):

### Edit `config/database.php`

```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'oc_support');    // Your database name
define('DB_USER',    'oc_user');       // Your database username
define('DB_PASS',    'your_password'); // Your database password
```

### Edit `config/config.php`

```php
define('SITE_NAME',    'Original Concepts Support');
define('SITE_URL',     'https://www.originalconcepts.co.za/support');
define('SITE_EMAIL',   'support@originalconcepts.co.za');
define('COMPANY_NAME', 'Original Concepts');
define('COMPANY_PHONE', '+27 (0) 000 000 0000');
define('COMPANY_ADDRESS', 'Your Full Address, South Africa');
```

### Import the Database

1. In cPanel, open **phpMyAdmin**.
2. Select your database (`oc_support`) from the left panel.
3. Click **Import**.
4. Choose `support/install/install.sql`.
5. Click **Go**.

---

## 6. QuickBooks Integration Setup <a name="quickbooks-integration"></a>

To enable automatic invoicing and quoting via QuickBooks Online:

### Step 1 – Create a QuickBooks Developer Account

1. Go to [developer.intuit.com](https://developer.intuit.com) and sign up.
2. Log in to the [Intuit Developer Portal](https://developer.intuit.com/app/developer/appcentral/list).

### Step 2 – Create a New App

1. Click **Create an app**.
2. Select **QuickBooks Online and Payments**.
3. Under **Scopes**, select **Accounting**.
4. Give your app a name (e.g., "Original Concepts Support").

### Step 3 – Configure Redirect URI

In your app settings → **Keys & credentials** → **Redirect URIs**, add:
```
https://www.originalconcepts.co.za/support/quickbooks/callback.php
```

### Step 4 – Copy Credentials to config.php

In your app's **Keys & credentials** (Production tab for live use, Sandbox for testing):

```php
// In config/config.php
define('QB_CLIENT_ID',     'ABCdefXYZ123...');   // Your Client ID
define('QB_CLIENT_SECRET', 'XYZabc789...');       // Your Client Secret
define('QB_ENVIRONMENT',   'production');          // 'sandbox' | 'production'
```

### Step 5 – Connect QuickBooks

1. Log into the support system at `https://www.originalconcepts.co.za/support`.
2. Go to **Billing → QuickBooks** in the top menu.
3. Click **Connect to QuickBooks**.
4. Authorise access via QuickBooks.
5. You will be redirected back and see "Connected" status.

### Features After Connection

- **Sync invoices** to QuickBooks from each invoice's view page.
- **Sync quotes** to QuickBooks as Estimates.
- Customers are automatically created/matched in QuickBooks by email.

---

## 7. Email / SMTP Setup <a name="email-setup"></a>

The system uses PHP's built-in `mail()` function by default. For better deliverability, configure your hosting's SMTP settings via cPanel.

If you want to use an external SMTP provider, update these settings in `config/config.php`:

```php
define('SMTP_HOST',     'mail.originalconcepts.co.za');
define('SMTP_PORT',     587);
define('SMTP_USER',     'support@originalconcepts.co.za');
define('SMTP_PASS',     'your_email_password');
define('SMTP_SECURE',   'tls');
```

> **Note:** To use SMTP (instead of PHP mail), install [PHPMailer](https://github.com/PHPMailer/PHPMailer) and update `sendMail()` in `includes/functions.php`.

---

## 8. Post-Installation Security <a name="post-installation-security"></a>

After successful installation, do the following immediately:

### ✅ Security Checklist

- [ ] **Delete the `/install` folder** from `public_html/support/install/`
- [ ] **Change the default admin password** if you used the default (`Admin@1234`)
- [ ] Ensure `config/` directory is **not publicly accessible** (`.htaccess` blocks this)
- [ ] Enable **SSL/HTTPS** (your hosting provider may have a free Let's Encrypt certificate)
- [ ] Set **`display_errors = Off`** in PHP settings
- [ ] Ensure `uploads/` directory has **no PHP execution** (`.htaccess` handles this)
- [ ] Regularly **back up your database** via cPanel backup tools
- [ ] Set the `uploads/` folder permission to **755** (not 777)

### Create the uploads directory and set permissions (via cPanel Terminal or SSH)

```bash
mkdir -p /home/yourusername/public_html/support/uploads
chmod 755 /home/yourusername/public_html/support/uploads
```

---

## 9. First Login & Initial Setup <a name="first-login"></a>

1. Open your browser: `https://www.originalconcepts.co.za/support`
2. You will be redirected to the login page.
3. Log in with:
   - **Email:** `admin@originalconcepts.co.za` (or your admin email)
   - **Password:** the password you set during installation
4. You will see the **Dashboard**.

### Initial Configuration Steps

1. **Add technicians/staff:**
   - Go to **Users** → **Add User**
   - Create accounts for each team member with appropriate roles:
     - `Admin` – Full access
     - `Manager` – Billing + tickets
     - `Technician` – Tickets only

2. **Add your clients:**
   - Go to **Clients** → **Add Client**
   - Fill in name, company, email, phone, and address/location

3. **Create your first ticket:**
   - Click **New Ticket** in the top navigation

4. **Connect QuickBooks** (optional):
   - Go to **Billing** → **QuickBooks**

5. **Review Admin Settings**:
   - Go to **Settings** (admin only)
   - Configure default invoice VAT and due days
   - Control whether managers can access billing and whether managers/technicians can manage clients

---

## 10. Feature Overview <a name="feature-overview"></a>

### 📋 Ticket Management
- Create tickets with client, category, priority, description, location, and attachments
- Categories: IT Support, Web Development, WhatsApp Development, Software Development, Networking, Cloud, Cybersecurity, Consulting
- Priority levels: Low, Medium, High, Critical
- Statuses: Open → In Progress → Waiting → Resolved → Closed / Cancelled
- Internal notes (not visible to clients)
- Activity history/timeline
- Email notifications to clients on ticket create, update, resolve
- Attach files to tickets and comments

### 👥 Client Management
- Add clients with full contact details, company information, and address
- **GPS location capture** – use browser geolocation or enter coordinates manually
- Interactive **Google Maps integration** for on-site service navigation
- Get Directions button for field technicians
- View all tickets and invoices per client
- Quick action buttons (Create Ticket, Create Invoice, Email, Call)

### 💰 Billing – Invoices & Quotes
- Create invoices with line items, quantities, unit prices
- Auto-calculates VAT (15% South African standard, configurable)
- Invoice statuses: Draft → Sent → Paid / Overdue / Void
- Create quotes with validity period
- Convert accepted quotes to invoices with one click
- Print-ready invoice and quote views (PDF via browser print)
- Email invoices/quotes directly to clients from the system
- Link invoices/quotes to support tickets

### 💳 Payment Tracking
- Payment summary dashboard with outstanding, overdue, paid amounts
- Mark invoices as paid with timestamp
- Overdue invoice alerts on dashboard
- Revenue reporting (this month)

### 🔗 QuickBooks Integration
- OAuth2 secure connection to QuickBooks Online
- Sync invoices to QuickBooks (creates or updates)
- Sync quotes as QuickBooks Estimates
- Auto-create/match customers in QuickBooks by email
- Re-sync capability for updates

### 👤 User Management (Admin only)
- Create accounts for admins, managers, and technicians
- Role-based access control
- Profile management with password change
- Last login tracking

### 🌍 Location Features
- Client GPS coordinates storage
- Google Maps embed on client profiles
- Service location field on tickets
- "Get Directions" link for field technicians

---

## 11. Troubleshooting <a name="troubleshooting"></a>

### "Database connection failed"
- Check `config/database.php` has the correct credentials
- Verify the database user has privileges on the database
- Try `localhost` as the DB host (some hosts use `127.0.0.1`)

### "Call to undefined function dbRow()"
- The bootstrap file is not being loaded. Check paths in `includes/bootstrap.php`
- Ensure all files are uploaded and paths are correct

### "No such file or directory" errors
- The `uploads/` directory may not exist. Create it manually in cPanel File Manager

### Blank white page / 500 error
- Enable PHP error display temporarily: add `ini_set('display_errors', 1);` at the top of `index.php`
- Check Apache error logs in cPanel → Logs → Error Log

### QuickBooks "State mismatch" error
- This is a CSRF protection. Try the connection again from a fresh browser tab
- Ensure your session is properly configured (cookies enabled)

### Emails not sending
- Test with cPanel's built-in email test tool
- Ensure `SMTP_FROM` matches a valid email on your domain
- Consider using [PHPMailer](https://github.com/PHPMailer/PHPMailer) for reliable SMTP sending

### "mod_rewrite not enabled" on localhost (development)
- Enable `mod_rewrite` in Apache or comment out the rewrite rules in `.htaccess` for testing

---

## Support

For support with this system, contact:

- **Email:** support@originalconcepts.co.za
- **Website:** https://www.originalconcepts.co.za

---

*Original Concepts Support System – Built for IT Support, Web Development, WhatsApp Development, Software Development, and more.*
