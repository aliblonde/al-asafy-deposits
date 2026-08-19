# Simple Logistics Shipment Tracking

A lightweight PHP 8 + MySQL shipment system for employees and public customer tracking. It has no build step and no server-side framework.

## cPanel installation

1. Upload all project files into `public_html` (or a subdomain folder).
2. In cPanel **MySQL Databases**, create a database and a database user, then assign the user **All Privileges**.
3. Open phpMyAdmin, select the database, and import `database.sql`.
4. Edit `config/database.php` with the database host, name, username, and password. cPanel commonly prefixes the database and username with your account name.
5. Edit `config/company.php` with the company name, logo path, and Iraq/Dubai phone numbers.
6. Visit `https://your-domain.com/setup.php` and create the first administrator (password must be at least eight characters).
7. Delete `setup.php` after setup. It also disables itself automatically once the first user exists.
8. Sign in at `https://your-domain.com/admin/login.php`.

Bootstrap is loaded from its public CDN. The application itself requires only PHP 8+, PDO MySQL, sessions, and MySQL/MariaDB.

## Optional sample data

Import `sample-data.sql` after setup if you want customer `1972` and four demonstration shipments. Do not import it into a database where those codes/JTR values already exist.

## URLs

- Employee login: `/admin/login.php`
- Admin dashboard: `/admin/index.php`
- Customer tracking: `/onlineview.php?code=CUSTOMER_CODE`
- Optional clean tracking URL (Apache rewrite): `/onlineview/CUSTOMER_CODE`

The query-string tracking URL always works, even when rewrite rules are unavailable.

## Backups and migration

Use **Export CSV** on Customers and Shipments for easy migration. For a full backup, open phpMyAdmin, select the database, choose **Export**, use the Quick SQL method, and download the file. Keep database backups outside `public_html`.

## Security and operations

- Passwords use PHP's `password_hash` and `password_verify`.
- All variable database queries use PDO prepared statements.
- Mutating forms use session CSRF tokens.
- Public tracking omits phone, email, and internal notes.
- Employees can manage customers and shipments. Only admins can manage accounts.
- Deleting a customer also deletes all of that customer's shipments. Confirm and export data first.
- Use HTTPS and keep PHP/MySQL updated on production hosting.

## Limitations

This MVP has no audit log, granular permissions, automated backups, pagination, email/SMS notifications, label printing, or carrier integrations. The customer selector is a standard native dropdown, suitable for a modest customer list. CSV exports intentionally include internal notes and therefore require an authenticated employee session.
