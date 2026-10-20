# Laundrables

A laundry shop web app made for a school project. Customers book and track laundry orders online. Staff manage the order queue, payments and support tickets.

Built with plain PHP 8 (PDO), vanilla JavaScript, CSS and MySQL/MariaDB. If MySQL is not available, the app switches to SQLite on its own. There is no build step.

## Features

**Customers**
- Register, log in and manage a profile (details, password, profile photo)
- Place orders for Wash & Fold, Dry Clean or Special Care, with a live price estimate
- Pick a drop-off date from today up to 60 days ahead. Past dates are blocked.
- Choose a time slot (10 AM, 2 PM or 4 PM). Slots that have already started today are greyed out.
- Saved laundry preferences (fragrance-free detergent, low-heat drying, SMS and email alerts) pre-fill new orders
- The drop-off section shows the address saved on the customer's profile
- Dashboard with active order progress, a tracking window and order history
- Help & Support: open tickets and reply in a conversation thread

**Staff / Admin**
- Live counters for waiting, in progress, ready and completed orders, plus payment totals
- Search, service and status filters, and pagination over all orders
- Create, edit and delete orders (new walk-in orders use today's date)
- Reply to and update support tickets
- "Customer View" to see the site as a customer, and "Back to Admin" to return

## Services and pricing

| Service      | Rate            | Minimum | Turnaround |
|--------------|-----------------|---------|------------|
| Wash & Fold  | ₱60 per kg      | 3 kg    | 24 hours   |
| Dry Clean    | ₱500 per piece  | 1 piece | 48 hours   |
| Special Care | ₱300 per kg     | 2 kg    | 36 hours   |

Prices are set on the server in `api.php`, so customers always pay the listed rate. Staff can override amounts when creating an order.

## Tech stack

- PHP 8 with PDO
- MySQL / MariaDB, with an automatic SQLite fallback
- Vanilla JavaScript (`js/app.js`) and CSS (`css/style.css`)
- All dates and times use the Asia/Manila timezone

## Project structure

```
laundrables/
├── index.php        Customer dashboard
├── new-order.php    New order form (date picker, time slots, live estimate)
├── profile.php      Profile, password, photo, preferences
├── service.php      Help & Support tickets
├── admin.php        Staff / admin workspace
├── login.php        Sign in (customers and staff)
├── register.php     Customer sign up
├── logout.php       End session
├── api.php          JSON endpoint for all actions
├── config/
│   ├── db.php                    Database connection, schema, helpers, timezone
│   └── config.local.php.example  Template for your own database settings
├── database.sql     MySQL/MariaDB schema and sample data
├── js/app.js        Client-side logic
├── css/style.css    Styles
└── img/promo.jpg    Promo image
```

## Getting started

Requires PHP 8 or newer with the `pdo_mysql` and/or `pdo_sqlite` extension.

### Option 1: XAMPP / WAMP / Laragon
1. Copy the project folder into `htdocs/` (XAMPP) or `www/` (WAMP/Laragon).
2. Start Apache and MySQL.
3. Open `http://localhost/laundrables/login.php`. The database and tables are created and filled with sample data automatically. You can also import `database.sql` in phpMyAdmin.

### Option 2: PHP built-in server
```bash
git clone https://github.com/a-karin/laundrables.git
cd laundrables
php -S localhost:8000
```
Open `http://localhost:8000/login.php`. Without MySQL running, the app creates `config/laundrables.sqlite` and uses that.

## Configuration

Database settings are read in this order:

1. `config/config.local.php` (your own file, ignored by git)
2. Environment variables
3. Defaults

| Setting   | Environment variable | Default          |
|-----------|----------------------|------------------|
| Host      | `DB_HOST`            | `127.0.0.1`      |
| Port      | `DB_PORT`            | `3306`           |
| Database  | `DB_NAME`            | `laundrables_db` |
| User      | `DB_USER`            | `root`           |
| Password  | `DB_PASS`            | (empty)          |

To use a hosted database, copy `config/config.local.php.example` to `config/config.local.php` and fill in your details. Never commit that file.

## Deploying to free hosting (for example InfinityFree)

1. Create a MySQL database in your hosting panel and import `database.sql` with phpMyAdmin. Import it into the existing database. The file does not create one.
2. Upload the project files into `htdocs/laundrables/`.
3. Create `config/config.local.php` on the server with your database host, name, user and password.
4. Open `https://your-site/laundrables/login.php`.

To reset the sample data, drop the tables and import `database.sql` again.

## Demo accounts

| Role     | Email                   | Password      |
|----------|-------------------------|---------------|
| Customer | `troypogi@example.com`  | `password123` |
| Admin    | `admin@laundrables.com` | `admin123`    |

## Security notes

This is a coursework project and is not ready for real customers as shipped. Before using it publicly:

- Remove the demo-password fallback in the `login` action of `api.php`. It accepts `password123`, `admin123` or `password` for any existing account.
- Remove the default auto-login in `get_current_user_record()` in `config/db.php`. It opens pages as a sample user when nobody is signed in.
- Add role and ownership checks to the order and support actions in `api.php`, and add CSRF protection.
- Change the sample passwords and use a dedicated database user instead of `root`.

## Contributing

1. Create a branch for your change: `git checkout -b my-change`
2. Commit with a clear message and push the branch.
3. Open a pull request and ask a teammate to review it before merging into `main`.

## Authors

- Amir L. Wagas
- Raizen John R. Angeles
- David Troy P. Yco
