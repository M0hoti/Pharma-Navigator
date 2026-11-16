# PharmaNear (PHP + Supabase Postgres)
1. The schema and sample data are already applied to the Supabase project (migration `create_pharmanear_schema`; see Database > Migrations).
2. In Supabase: Project > Connect > Session pooler. Put the host, user and your database password in `config.php`.
3. PHP needs the `pdo_pgsql` extension (`php -m | grep pdo_pgsql`).
4. Create a pharmacist: `php create_admin.php amina 'LongPassword123' 1`
5. Run: `php -S localhost:8000`, open `/index.html` (customers) and `/admin.php` (pharmacists).
Production: HTTPS, config.php outside the web root, compiled Tailwind CSS instead of the CDN.
