# TESCON Membership and Management System

A PHP and MySQL application for TESCON membership applications, member administration, audit records, and public leadership profiles.

## Requirements

- PHP 8.1 or later with PDO MySQL and mbstring enabled
- MySQL 8.0 or later
- Apache (for example, the Apache and MySQL services included with XAMPP)

## Run locally with XAMPP

1. Place this project in `C:\xampp\htdocs\tescon-membership`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import [`schema.sql`](schema.sql) into MySQL using phpMyAdmin (`http://localhost/phpmyadmin`) or the MySQL command-line client. The script creates the `tescon_membership` database, tables, triggers, and starter records.
4. Copy [`.env.example`](.env.example) to `.env` and set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` to match your local MySQL account:

   ```powershell
   Copy-Item .env.example .env
   ```

   The application reads `.env` on the server. Environment variables provided by the web server take precedence. Never commit `.env`; Apache access to it is blocked by [`.htaccess`](.htaccess).
5. Create the local application database account and grant the permissions the app needs. In phpMyAdmin's SQL tab or the MySQL command line, run:

   ```sql
   CREATE USER IF NOT EXISTS 'tescon_app'@'localhost' IDENTIFIED BY 'replace-with-your-local-db-password';
   GRANT SELECT ON tescon_membership.users TO 'tescon_app'@'localhost';
   GRANT SELECT, INSERT, UPDATE ON tescon_membership.members TO 'tescon_app'@'localhost';
   GRANT SELECT, INSERT ON tescon_membership.audit_logs TO 'tescon_app'@'localhost';
   GRANT SELECT ON tescon_membership.leaders TO 'tescon_app'@'localhost';
   GRANT SELECT, INSERT, UPDATE, DELETE ON tescon_membership.announcements TO 'tescon_app'@'localhost';
   ```

   Use the same password in `.env` as in the `CREATE USER` statement. For production, inject a strong `DB_PASS` through the web server environment and use a matching least-privilege MySQL account.
   For an existing installation, an administrator must apply the SQL migration documented below before accepting registration photos; the runtime database account does not need schema-alteration privileges.
6. Open the application:
   - Public homepage: <http://localhost/tescon-membership/public/>
   - Membership application: <http://localhost/tescon-membership/public/register.php>
   - Member portal sign-in: <http://localhost/tescon-membership/public/login.php>
   - Admin sign-in: <http://localhost/tescon-membership/admin/>
   - Auditor sign-in: <http://localhost/tescon-membership/auditor/>

The application detects its base path automatically, so the links also work if the project folder name changes or it is deployed at the web root.

## Account setup

`schema.sql` inserts starter account records for local evaluation. Treat their credentials as compromised: change all seeded passwords through your organization's secure database administration process before deployment. The application stores password hashes; do not put account passwords in source control, `.env`, or web pages.

## Architecture & Security Model

- **Portal Isolation & Access Control:** Admin (`/admin/`), Auditor (`/auditor/`), and Member (`/member/`) portals are strictly isolated. Authenticated users attempting to visit foreign portals or public guest forms are automatically redirected to their respective dashboards.
- **Dual-Vector Rate Limiting:** Brute-force protection combines IP-level rate limiting with account-identifier rate limiting via the `audit_logs` table.
- **Password Security:** Passwords enforce 8–72 byte boundaries (preventing bcrypt truncation issues), use `PASSWORD_DEFAULT` (bcrypt with cost 10), and automatically rehash upon login via `password_needs_rehash()`.
- **Session Security:** Sessions use strict cookie-only IDs, HTTP-only and SameSite cookies, HTTPS-only cookies on HTTPS connections, a 30-minute idle timeout, and no-store headers on authenticated pages.
- **Live Session Eviction:** Active sessions for administrative users or members are checked in real-time; deactivated administrators or deleted members are evicted on their next request.
- **Server-side configuration:** Database credentials are read from `.env` or the server environment and are never included in browser scripts or markup. `config/environment.php` loads the optional local file; `includes/session.php` owns session setup; `includes/formatters.php` centralizes UTC date display.
- **Member portal UI:** Dashboard, announcements, and digital ID pages use shared navigation and the Aurora glass UI system in `assets/css/style.css`. The ID-card PNG and print actions are handled by the self-hosted `assets/js/member-id-card.js`.
- **Registration verification photos:** Applicants can optionally attach a JPEG or PNG student ID image (up to 5 MB). Images are validated server-side, stored in the Apache-denied `private/member-photos/` directory, and only viewable by administrators reviewing applications. For an existing installation, apply [`database/migrations/20261106_add_member_verification_photo.sql`](database/migrations/20261106_add_member_verification_photo.sql).

  To update an existing database, select `tescon_membership` in phpMyAdmin, open its SQL tab, and execute the migration file above. The app database user intentionally has no `ALTER` permission; the migration must be applied by a database administrator.
- **Append-Only Audit Trail:** `audit_logs` records all sign-ins, logouts, application reviews, member modifications, and deletions. Database triggers reject any direct `UPDATE` or `DELETE` statements on audit logs.

## Leadership profiles

The homepage displays active records from the `leaders` table. Schema seed profiles are intentionally inactive examples; replace them with verified information before publishing. Add a profile by inserting its verified details and setting `status` to `active`. For example:

```sql
INSERT INTO leaders
    (full_name, position, category, region, image_url, bio, display_order, status)
VALUES
    ('Verified leader name', 'Campus Executive', 'tescon', 'Greater Accra',
     NULL, 'Short approved profile description.', 1, 'active');
```

Use one of the supported categories: `npp_national`, `regional`, or `tescon`. Regional profiles can specify a region for the homepage filter. `image_url` may be a site-relative path or an HTTPS URL; leaving it empty displays initials instead. There is currently no leadership-management screen, so profiles must be maintained directly in the database.

## Security notes

- Change the seeded account passwords and remove or disable any accounts that are not needed before deployment. Do not use starter credentials in production.
- Keep database credentials out of public source control and use a dedicated application database account with only the permissions the application needs.
- Preserve the append-only protections on `audit_logs`; the schema installs triggers that reject updates and deletes.
- Use HTTPS and set appropriate production PHP and database configuration before exposing the application publicly.
- **Production Apache deployment:** The protections in the root, `config/`, `includes/`, and `private/` `.htaccess` files only apply when Apache permits the required overrides. Configure the application directory with `AllowOverride All`, or allow the `Options`, `FileInfo`, and `AuthConfig` override classes needed by these rules. Confirm that a request for `.env` is denied, `.git/HEAD` returns 404, and private files are not accessible over HTTP.
- Enforce HTTP-to-HTTPS redirects in the production TLS virtual host or trusted reverse proxy; this application deliberately does not force HTTPS in `.htaccess` so local XAMPP development can use HTTP. Verify that HTTPS reaches PHP as HTTPS (including trusted proxy deployments) so session cookies receive the `Secure` attribute. HSTS is sent only on HTTPS responses; enable it only after HTTPS is correctly deployed for the host and its subdomains.
