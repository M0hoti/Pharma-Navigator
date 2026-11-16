<?php
// CLI only:  php create_admin.php <username> <password> <pharmacy_id>
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__.'/config.php';
[, $u, $p, $ph] = $argv + [null,null,null,null];
if (!$u || !$p || !$ph || strlen($p) < 10) exit("Usage: php create_admin.php user password(min 10 chars) pharmacy_id\n");
db()->prepare('INSERT INTO admins (pharmacy_id,username,password_hash) VALUES (?,?,?)')
    ->execute([(int)$ph, $u, password_hash($p, PASSWORD_BCRYPT, ['cost'=>12])]);
echo "Admin created.\n";
