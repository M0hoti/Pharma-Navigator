<?php
// Supabase: Project > Connect > "Session pooler" (works over IPv4; the direct host is IPv6-only on the free plan).
// Fill the values below. Never commit real credentials; keep this file outside the web root.
const DB_HOST='aws-0-ap-south-1.pooler.supabase.com', DB_PORT=5432, DB_NAME='postgres',
      DB_USER='postgres.pywecatgjwpkzfapgxdx', DB_PASS='';   // DB_PASS = the database password you chose
function db(): PDO {
  static $pdo = null;
  return $pdo ??= new PDO('pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';sslmode=require', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
