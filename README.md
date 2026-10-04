<div align="center">

<img src="docs/screenshots/02-dashboard.png" alt="MediCore Pharmacy ERP dashboard" width="100%">

# MediCore Pharmacy ERP

**A complete, production-ready ERP for pharmacies and medical stores.**
Inventory with batch and expiry tracking, a barcode-ready point of sale, purchasing, invoices, returns, customers, suppliers, expenses, staff permissions, reports and a full audit trail.

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-3-8BC0D0?logo=alpinedotjs&logoColor=white)](https://alpinejs.dev)
[![Tests](https://img.shields.io/badge/tests-88%20passing-2EA44F)](#-running-the-tests)

[Features](#-features) · [Screenshots](#-screenshots) · [Quick start](#-quick-start) · [Installation](#-detailed-installation) · [Usage](#-first-steps-after-installing) · [Deployment](#-production-deployment) · [Troubleshooting](#-troubleshooting)

</div>

---

## 📋 Table of contents

1. [Features](#-features)
2. [Screenshots](#-screenshots)
3. [Technology stack](#-technology-stack)
4. [Quick start (5 minutes)](#-quick-start)
5. [Detailed installation](#-detailed-installation)
   - [Step 1: Install the tools](#step-1--install-the-tools) (macOS · Ubuntu/Debian · Windows)
   - [Step 2: Get the code](#step-2--get-the-code)
   - [Step 3: Install dependencies](#step-3--install-dependencies)
   - [Step 4: Create the database](#step-4--create-the-mysql-database)
   - [Step 5: Configure `.env`](#step-5--configure-the-environment-file)
   - [Step 6: Build the database](#step-6--create-tables-and-the-first-admin)
   - [Step 7: Run it](#step-7--run-the-application)
6. [First steps after installing](#-first-steps-after-installing)
7. [Roles & permissions](#-roles--permissions)
8. [Command cheat sheet](#-command-cheat-sheet)
9. [Running the tests](#-running-the-tests)
10. [Project structure & architecture](#-project-structure--architecture)
11. [Security](#-security)
12. [Production deployment](#-production-deployment)
13. [Troubleshooting](#-troubleshooting)
14. [Roadmap](#-roadmap)

---

## ✨ Features

| Module | What it does |
|---|---|
| 📊 **Dashboard** | Today's and monthly revenue, month-over-month change, estimated net profit, customer and supplier dues, low-stock and expiry alerts, 30-day sales trend, top sellers, collections by payment method, recent sales and purchases, inventory value |
| 💊 **Medicines** | Name, generic name, strength, brand, category, SKU, barcode, unit, prices, tax rate, reorder level, prescription flag. Search, filters, sorting, pagination, bulk actions, CSV export, printable barcode shelf labels, archive and restore |
| 📦 **Batches & expiry** | Several batches per medicine, each with its own batch number, expiry date, cost and quantity. **First-expiry-first-out** selling. **Expired stock can never be sold.** Write-off of expired stock and stock adjustments with a reason |
| 🔁 **Stock ledger** | Every stock change is recorded: purchase, sale, return, adjustment, write-off and cancellation, with the user and running balance |
| 🚚 **Purchases** | Supplier → invoice → batch lines (batch no., expiry, qty, cost, discount, tax) → stock in → supplier balance → optional payment. Safe cancellation |
| 🛒 **Point of sale** | Barcode scanner support, fast search, keyboard shortcuts, sellable-stock limits, permission-based discounts, tax, customer lookup and quick-add, Cash/Card/Bank/Other, change calculation, credit sales |
| 🧾 **Invoices** | Sequential invoice numbers that are safe even with several cashiers at once. Professional screen, print and **PDF** layouts |
| ↩️ **Returns & refunds** | Find the original invoice, return selected items, restock or not (damaged), reason. Outstanding balances are settled before cash is refunded. Original sales are never deleted |
| 👥 **Customers & suppliers** | Contact details, full purchase history, outstanding balances, payments applied to the oldest invoices first |
| 💸 **Expenses** | Categories, payment method, reference, private receipt upload (PDF or image) |
| 🔐 **Staff & roles** | Admin, Staff and Pharmacist roles, plus any custom roles you create. 31 granular permissions, activate/deactivate accounts, password resets, last sign-in |
| 📈 **Reports** | Daily, weekly, monthly and annual sales. By medicine, category, staff and customer. Purchases by supplier, inventory valuation, low stock, expiry, expenses, payment methods, **profit & loss**. Date presets and **CSV export** |
| 🕵️ **Audit log** | Logins (including failed ones), logouts, changes with before and after values, stock adjustments, sales, refunds, staff and permission changes |
| ⚙️ **Settings** | Store name, logo, address, contact details, licence and tax numbers, currency, tax rules, invoice prefixes, alert thresholds, invoice footer |
| 📱 **Responsive** | Desktop-first ERP layout that also works on tablets and phones |

---

## 📸 Screenshots

> All screenshots were taken from a real installation running on **MySQL 8+** with the built-in demo data.

### Point of sale

<table>
  <tr>
    <td width="50%"><img src="docs/screenshots/04-pos-cart.png" alt="POS with a cart"><br><sub><b>Cart</b>: scanned items, prescription flag, customer, change calculation</sub></td>
    <td width="50%"><img src="docs/screenshots/05-pos-complete.png" alt="Sale completed"><br><sub><b>Sale completed</b>: invoice number with print and invoice shortcuts</sub></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/03-pos.png" alt="POS search"><br><sub><b>Live search</b>: by name, generic, SKU or barcode, showing sellable stock</sub></td>
    <td><img src="docs/screenshots/09-invoice.png" alt="Invoice"><br><sub><b>Invoice</b>: print and PDF-ready, with batch and expiry on every line</sub></td>
  </tr>
</table>

### Inventory

<table>
  <tr>
    <td width="50%"><img src="docs/screenshots/06-medicines.png" alt="Medicines list"><br><sub><b>Medicines</b>: filters, sorting, bulk actions, low-stock indicators</sub></td>
    <td width="50%"><img src="docs/screenshots/07-medicine-detail.png" alt="Medicine detail"><br><sub><b>Medicine detail</b>: batches, sellable stock, adjustments, movement history</sub></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/08-batches-expiry.png" alt="Batches and expiry"><br><sub><b>Batches & expiry</b>: every batch with its expiry status</sub></td>
    <td><img src="docs/screenshots/10-purchase-entry.png" alt="Purchase entry"><br><sub><b>Purchase entry</b>: batch numbers, expiry, cost, live totals</sub></td>
  </tr>
</table>

### Sales follow-up, reports & administration

<table>
  <tr>
    <td width="50%"><img src="docs/screenshots/11-returns.png" alt="Returns"><br><sub><b>Returns</b>: look up the invoice, choose items, restock or not</sub></td>
    <td width="50%"><img src="docs/screenshots/12-customer.png" alt="Customer profile"><br><sub><b>Customer profile</b>: history, dues, payments, returns</sub></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/13-report-sales.png" alt="Sales report"><br><sub><b>Sales report</b>: by day, week, month or year, with CSV export</sub></td>
    <td><img src="docs/screenshots/14-profit-loss.png" alt="Profit and loss"><br><sub><b>Profit & loss</b>: revenue, cost of goods, expenses, net profit</sub></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/15-expiry-report.png" alt="Expiry report"><br><sub><b>Expiry report</b>: soon-to-expire and expired stock, valued at cost</sub></td>
    <td><img src="docs/screenshots/16-roles.png" alt="Roles and permissions"><br><sub><b>Roles & permissions</b>: build any role from 31 permissions</sub></td>
  </tr>
  <tr>
    <td><img src="docs/screenshots/17-settings.png" alt="Settings"><br><sub><b>Settings</b>: store details, tax, numbering, thresholds, payment methods</sub></td>
    <td><img src="docs/screenshots/18-audit-log.png" alt="Audit log"><br><sub><b>Audit log</b>: who did what and when, with details</sub></td>
  </tr>
</table>

### Sign-in & mobile

<table>
  <tr>
    <td width="60%"><img src="docs/screenshots/01-login.png" alt="Login"><br><sub><b>Sign-in</b>: throttled, audited, with password reset</sub></td>
    <td width="20%"><img src="docs/screenshots/19-mobile-dashboard.png" alt="Mobile dashboard"><br><sub><b>Mobile dashboard</b></sub></td>
    <td width="20%"><img src="docs/screenshots/20-mobile-pos.png" alt="Mobile POS"><br><sub><b>Mobile POS</b></sub></td>
  </tr>
</table>

---

## 🧰 Technology stack

| Layer | Technology |
|---|---|
| Backend | **PHP 8.3+** (tested on 8.5), **Laravel 13**, Eloquent ORM |
| Database | **MySQL 8+** (or MariaDB 10.6+) |
| Frontend | Blade components, **Tailwind CSS 4**, **Alpine.js 3**, **Chart.js 4**, built with **Vite** |
| PDF | `barryvdh/laravel-dompdf`, rendered on the server with no external calls |
| Tests | PHPUnit 12, 88 tests (run on both MySQL and SQLite) |

No paid or third-party cloud services are needed: it runs on any normal VPS or your own computer, and fonts are bundled.

---

## ⚡ Quick start

Already have **PHP 8.3+, Composer, Node.js 20+ and MySQL 8** installed? Then:

```bash
git clone https://github.com/mohakamran/medicore-pharmacy-erp.git
cd medicore-pharmacy-erp

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate

# create an empty MySQL database (enter your MySQL root password when asked)
mysql -u root -p -e "CREATE DATABASE pharmacy_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Open `.env` and set your MySQL details:

```dotenv
DB_DATABASE=pharmacy_erp
DB_USERNAME=root
DB_PASSWORD=your-mysql-password

SEED_ADMIN_PASSWORD=ChooseAStrongPassword1
SEED_DEMO_DATA=true        # optional sample data
```

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

🎉 Open **http://localhost:8000** and sign in as `admin@pharmacy.test` with the password you set.

Not sure about any of these steps? Follow the detailed guide below.

---

## 🛠 Detailed installation

### Step 1 · Install the tools

You need **Git, PHP 8.3+ (with extensions), Composer, Node.js 20+ and MySQL 8+**.

<details open>
<summary><b>🍎 macOS (Apple Silicon M1/M2/M3 or Intel)</b></summary>

```bash
# 1. Homebrew (the macOS package manager), if you don't have it
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
# On Apple Silicon, add Homebrew to your PATH (the installer prints this command):
echo 'eval "$(/opt/homebrew/bin/brew shellenv)"' >> ~/.zprofile && eval "$(/opt/homebrew/bin/brew shellenv)"

# 2. Everything else
brew install git php composer node mysql

# 3. Start MySQL now and at every login
brew services start mysql

# 4. Set a MySQL root password and remove test users (answer the prompts)
mysql_secure_installation
```

Homebrew's PHP already includes the required extensions.
</details>

<details>
<summary><b>🐧 Ubuntu / Debian</b></summary>

```bash
sudo apt update
sudo apt install -y git unzip curl software-properties-common

# PHP 8.3+ and extensions (Ubuntu 24.04 ships 8.3; on older releases add ppa:ondrej/php first)
sudo apt install -y php php-cli php-mysql php-mbstring php-xml php-bcmath php-intl php-gd php-zip php-curl

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
sudo mysql_secure_installation
```
</details>

<details>
<summary><b>🪟 Windows</b></summary>

**Option A (easiest): [Laragon](https://laragon.org/download/)**
1. Install **Laragon Full**. It includes PHP, MySQL, Composer, Node.js and Git.
2. Make sure PHP 8.3+ is selected: *Menu → PHP → Version*. If 8.3 isn't listed, download it via *Menu → Tools → Quick add*.
3. Click **Start All**, then open **Terminal** from Laragon and continue with Step 2.
4. Laragon's MySQL user is `root` with an **empty password** by default.

**Option B: WSL2 (Ubuntu)**
Run `wsl --install` in an administrator PowerShell, restart, open *Ubuntu*, and follow the Ubuntu instructions above.
</details>

**Check everything is installed:**

```bash
git --version        # any recent version
php -v               # must be 8.3 or higher
php -m | grep -E "pdo_mysql|mbstring|bcmath|intl|gd|zip"   # all six should be listed
composer -V          # 2.x
node -v              # v20 or higher
npm -v
mysql --version      # 8.x or higher
```

---

### Step 2 · Get the code

```bash
git clone https://github.com/mohakamran/medicore-pharmacy-erp.git
cd medicore-pharmacy-erp
```

No Git? Click **Code → Download ZIP** on GitHub, unzip it and open a terminal in that folder.

---

### Step 3 · Install dependencies

```bash
composer install      # PHP packages (creates the vendor/ folder)
npm install           # JavaScript/CSS build tools
npm run build         # compiles CSS & JS into public/build
```

---

### Step 4 · Create the MySQL database

Log in to MySQL (on Laragon, run `mysql -u root`):

```bash
mysql -u root -p
```

Then paste the following. It's best to create a dedicated user instead of using `root`:

```sql
CREATE DATABASE pharmacy_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pharmacy_app'@'localhost' IDENTIFIED BY 'ChooseAStrongDbPassword!';
GRANT ALL PRIVILEGES ON pharmacy_erp.* TO 'pharmacy_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### Step 5 · Configure the environment file

```bash
cp .env.example .env          # Windows CMD: copy .env.example .env
php artisan key:generate      # creates the encryption key
```

Open `.env` in any text editor and fill in:

```dotenv
APP_NAME="Your Pharmacy Name"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Karachi            # your timezone: defines what "today" means in reports

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pharmacy_erp
DB_USERNAME=pharmacy_app
DB_PASSWORD=ChooseAStrongDbPassword!

# The first administrator account
SEED_ADMIN_NAME="Your Name"
SEED_ADMIN_EMAIL=admin@pharmacy.test
SEED_ADMIN_PASSWORD=ChooseAStrongPassword1   # leave empty to have one generated and printed

# Sample medicines, suppliers, customers and 60 days of sales (good for trying it out)
SEED_DEMO_DATA=true
```

<details>
<summary>All environment options explained</summary>

| Key | What it's for |
|---|---|
| `APP_NAME` | Store name used until you save one under Settings |
| `APP_ENV` / `APP_DEBUG` | `local`/`true` while developing, **`production`/`false`** on a live server |
| `APP_URL` | The address you open in the browser |
| `APP_TIMEZONE` | Your local timezone, e.g. `Asia/Karachi`, `Europe/London`, `America/New_York` |
| `DB_*` | MySQL connection details |
| `SESSION_ENCRYPT` | Keep `true`; sessions are encrypted |
| `SESSION_SECURE_COOKIE` | Set to `true` when the site is served over HTTPS |
| `MAIL_*` | Used for "forgot password" emails. With `MAIL_MAILER=log` they're written to `storage/logs/laravel.log` |
| `SEED_ADMIN_*` | The first admin created by the seeder |
| `SEED_DEMO_DATA` | `true` loads demo data (works only when `APP_ENV=local`) |
</details>

---

### Step 6 · Create tables and the first admin

```bash
php artisan migrate --seed     # creates all tables, permissions, roles, payment methods and your admin
php artisan storage:link       # lets the store logo be displayed
```

You should see `Admin account: admin@pharmacy.test`. If you left `SEED_ADMIN_PASSWORD` empty, a generated password is printed **once**, so copy it now.

---

### Step 7 · Run the application

```bash
php artisan serve
```

Open **http://localhost:8000** and sign in. 🎉

**While developing**, run this instead. It starts the server, the queue worker, log viewer and Vite with hot reload together:

```bash
composer run dev
```

---

## 🚀 First steps after installing

1. **Settings** → enter the store name, address, phone, licence and tax numbers, currency, logo and invoice footer.
2. **Settings → Tax** → turn tax on or off and set its label (VAT, GST…).
3. **Roles & permissions** → review the *Staff* and *Pharmacist* roles, or create your own (e.g. *Cashier*).
4. **Staff** → create an account for each team member and assign a role.
5. **Suppliers** → add your distributors.
6. **Medicines** → add your products, or keep the demo catalogue to explore.
7. **Purchases → New purchase** → receive stock with batch numbers and expiry dates. *(Use **Opening stock** on a medicine for stock you already have.)*
8. **Point of sale** → start selling. Scan barcodes or type to search. Press **F2** to focus the search box and **Ctrl/⌘ + Enter** to complete a sale.

> 💡 **Starting fresh after trying the demo data:** set `SEED_DEMO_DATA=false` in `.env`, then run `php artisan migrate:fresh --seed`. **This deletes all data.**

---

## 🔐 Roles & permissions

| Role | Default access |
|---|---|
| **Administrator** | Everything. This system role can't be edited or removed, and at least one active admin always remains |
| **Pharmacist** | Inventory, stock adjustments, purchases, POS with discounts and credit, returns, customers, reports |
| **Staff** | Dashboard, POS, viewing medicines and stock, customers, invoices |
| *Custom roles* | Build your own from **31 permissions** in 10 groups |

Permissions are checked **on the server for every request**. Hiding a menu item is only cosmetic.

---

## 📖 Command cheat sheet

| Task | Command |
|---|---|
| Start the app | `php artisan serve` |
| Development mode (server + Vite + logs) | `composer run dev` |
| Rebuild CSS/JS | `npm run build` |
| Run migrations | `php artisan migrate` |
| Reset the database **(deletes data)** | `php artisan migrate:fresh --seed` |
| Re-sync permissions after editing `config/pharmacy.php` | `php artisan db:seed --class=PermissionSeeder` |
| Run all tests | `php artisan test` |
| Format code (PSR-12 / Laravel style) | `./vendor/bin/pint` |
| Clear caches | `php artisan optimize:clear` |
| Production caches | `php artisan optimize` |

---

## 🧪 Running the tests

```bash
php artisan test
```

Tests use a temporary in-memory SQLite database by default, so your data is never touched. To run them against MySQL, create an empty `pharmacy_erp_test` database and run:

```bash
DB_CONNECTION=mysql DB_DATABASE=pharmacy_erp_test DB_USERNAME=root DB_PASSWORD=secret php artisan test
```

**88 tests / 584 assertions** pass on both MySQL 8+ (strict mode, `ONLY_FULL_GROUP_BY`) and SQLite. They cover:

- **Authentication:** login, logout, lockout after failed attempts, inactive accounts, password reset
- **Authorization:** staff blocked from admin areas, no privilege escalation, no mass assignment
- **Inventory:** first-expiry-first-out selling, expired stock never sold, adjustments, write-offs, stock always reconciled
- **Purchases:** totals, batches, supplier balance, safe cancellation, rollback on error
- **Sales:** stock decrease, multi-batch lines, gap-free invoice numbers, tax and discount rounding, credit rules, cross-store isolation
- **Invoices and returns:** the full workflow from medicine → stock → sale → invoice/PDF → return → stock restored
- **Whole application:** every page and CSV export renders with realistic data

---

## 🏗 Project structure & architecture

```
app/
├── Enums/                  Status and movement types
├── Exceptions/             BusinessRuleException → friendly error, transaction rolled back
├── Http/
│   ├── Controllers/        Thin controllers, permission checks on every route
│   ├── Middleware/         Active-user check, tenant context, security headers
│   └── Requests/           Form Request validation (store-scoped)
├── Models/                 Eloquent models (+ BelongsToPharmacy tenant trait)
├── Services/               ← all business logic lives here
│   ├── InventoryService    the only place stock can change (FEFO, adjustments, ledger)
│   ├── SaleService         POS checkout in one database transaction
│   ├── PurchaseService     receiving and cancelling purchases
│   ├── ReturnService       returns and refunds
│   ├── PaymentService      customer and supplier payments
│   ├── DocumentNumberService  gap-free invoice/purchase/return numbers
│   └── ReportService       all reports and dashboard figures
└── Support/                Money (integer-cent maths), Barcode (Code 128), tenancy helpers
config/pharmacy.php         permissions, units, default settings
database/                   migrations, seeders (incl. demo data), factories
resources/views/            Blade pages and reusable UI components
resources/js/               POS, purchase form and chart logic (Alpine.js)
tests/                      Feature & unit tests
docs/screenshots/           Images used in this README
```

**Design principles**

- **Stock lives in batches.** Every change goes through `InventoryService` and writes a `stock_movements` row, and the tests check that totals always reconcile.
- **Everything that moves money or stock runs in a database transaction with row locks.** If anything fails, nothing is saved.
- **Invoice numbers are sequential and gap-free**, even with several cashiers selling at the same time.
- **Money is calculated in whole cents**, so there are no floating-point rounding errors.
- **Nothing important is ever deleted.** Sales are reversed by returns, purchases are cancelled, and master data is archived.
- **Multi-store ready.** Every business table has a `pharmacy_id`, so the app can grow into a multi-pharmacy SaaS.

---

## 🛡 Security

- Server-side authorization on every route, with 31 granular permissions
- No privilege escalation: only admins can create admins, and users can't change their own role
- Records from another store return *404 Not Found*
- CSRF protection, Blade output escaping, validated input, parameterised queries
- Login throttling, failed logins audited, password-reset responses don't reveal whether an account exists
- Encrypted sessions, regenerated at login; a password change signs out other devices
- Deactivated users are signed out immediately
- Safe uploads: logos accept PNG/JPG/WebP only (SVG is blocked), receipts are stored privately
- Security headers (X-Frame-Options, nosniff, Referrer-Policy, HSTS over HTTPS), no caching of private pages
- CSV exports are protected against spreadsheet formula injection
- No secrets in the code; everything sensitive lives in `.env`, which is never committed

---

## 🌐 Production deployment

Example for an **Ubuntu VPS with Nginx**:

```bash
# 1. Get the code
cd /var/www && git clone https://github.com/mohakamran/medicore-pharmacy-erp.git pharmacy && cd pharmacy

# 2. Install (no dev packages)
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# 3. Configure
cp .env.example .env && php artisan key:generate
#   edit .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain,
#   DB_* details, SESSION_SECURE_COOKIE=true, SEED_DEMO_DATA=false, real MAIL_* settings

# 4. Database & caches
php artisan migrate --force && php artisan db:seed --force
php artisan storage:link && php artisan optimize

# 5. Permissions
sudo chown -R www-data:www-data storage bootstrap/cache
```

Point Nginx's `root` to `/var/www/pharmacy/public`, enable HTTPS with Let's Encrypt (`sudo certbot --nginx`), run a queue worker (`php artisan queue:work`) under Supervisor or systemd, and back up the database and `storage/app` every day.

**Updating later**

```bash
git pull && composer install --no-dev -o && npm ci && npm run build
php artisan migrate --force && php artisan optimize
```

---

## 🩺 Troubleshooting

| Problem | Fix |
|---|---|
| `SQLSTATE[HY000] [1045] Access denied` | Wrong `DB_USERNAME`/`DB_PASSWORD` in `.env`. Test them with `mysql -u <user> -p` |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL isn't running: `brew services start mysql` (macOS), `sudo systemctl start mysql` (Linux), or *Start All* in Laragon |
| `Unknown database 'pharmacy_erp'` | Create it (see [Step 4](#step-4--create-the-mysql-database)) |
| `Vite manifest not found` | Run `npm install && npm run build` |
| Page has no styling | Same as above; or run `npm run dev` while developing |
| `No application encryption key` | `php artisan key:generate` |
| Logo doesn't show | `php artisan storage:link` |
| Changed `.env` but nothing happens | `php artisan optimize:clear` |
| Forgot the admin password | Use *Forgot password?* (with `MAIL_MAILER=log` the link is in `storage/logs/laravel.log`), or run `php artisan tinker` → `App\Models\User::where('email','admin@pharmacy.test')->first()->forceFill(['password' => 'NewPassw0rd!'])->save();` |
| `php -v` shows an old version | Make sure PHP 8.3+ comes first in your PATH (`which php`) |
| Too many failed logins | Wait 1 minute; login is rate-limited on purpose |

---

## 🗺 Roadmap

- Prescription capture and controlled-drug register
- Purchase orders and supplier returns (debit notes)
- 80 mm thermal receipt layout and cashier shift close-out
- Excel import for an existing product list
- Multi-branch stock transfers and multi-pharmacy SaaS mode
- Two-factor authentication for administrators
- REST API for mobile apps

---

<div align="center">

Built by **Muhammad Kamran** · [@mohakamran](https://github.com/mohakamran)

If this project helps you, consider giving it a ⭐

</div>
