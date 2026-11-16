# PharmaNear

A pharmacy stock finder and reservation app. Customers search for a medicine, see the nearest pharmacies that stock it (Haversine distance, Leaflet map, manual town fallback) and reserve it. Pharmacists manage stock and orders from a protected dashboard.

**Stack:** PHP (PDO), Supabase Postgres, HTML5, Tailwind CSS, Leaflet.js.

## Setup (XAMPP on Windows)

1. Copy this folder to `C:\xampp\htdocs\PharmaNear`.
2. Copy `config.example.php` to `config.php`. `config.php` is git-ignored, so your password is never committed.
3. In Supabase, open your project, click **Connect**, choose **Session pooler**, and copy the host and user into `config.php`. Add your database password to `DB_PASS`.
4. The database schema is a Supabase migration named `create_pharmanear_schema`. Apply it to a new project from **SQL Editor** or **Database > Migrations**.
5. Enable PHP extensions: in XAMPP, click **Config > PHP (php.ini)** next to Apache, remove the `;` before `extension=pdo_pgsql` and `extension=pgsql`, then restart Apache.
6. Create a pharmacist account from the XAMPP Shell:

   ```
   cd C:\xampp\htdocs\PharmaNear
   php create_admin.php yourusername "YourLongPassword" 1
   ```

   The password must be at least 10 characters. The last number is the pharmacy the account manages.

## Use

- Customers: `http://localhost/PharmaNear/index.html`
- Pharmacists: `http://localhost/PharmaNear/admin.php`

## Production checklist

- Serve over HTTPS.
- Move `config.php` outside the web root.
- Replace the Tailwind CDN script with compiled CSS.
- Keep Row Level Security enabled on every table.
