<?php
require __DIR__.'/config.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>!empty($_SERVER['HTTPS'])]);
session_start();
const IDLE = 900; // 15 min idle timeout
if (isset($_SESSION['admin']) && time() - ($_SESSION['last'] ?? 0) > IDLE) { session_unset(); session_destroy(); session_start(); $err = 'Session expired. Log in again.'; }
$_SESSION['last'] = time();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function csrf_ok(): bool { return hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? ''); }
$pdo = db(); $err = $err ?? ''; $msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_ok()) { http_response_code(403); exit('Bad request'); }
  $a = $_POST['a'] ?? '';
  if ($a === 'login') {
    if (($_SESSION['fails'] ?? 0) >= 5 && time() < ($_SESSION['lock'] ?? 0)) $err = 'Too many attempts. Wait a minute.';
    else {
      $s = $pdo->prepare('SELECT * FROM admins WHERE username=?'); $s->execute([trim($_POST['u'] ?? '')]); $u = $s->fetch();
      // verify against a dummy hash when user is missing to keep timing even
      $hash = $u['password_hash'] ?? '$2y$12$usesomesillystringforsalt.abcdefghijklmnopqrstuvwxyzABC';
      if (password_verify($_POST['p'] ?? '', $hash) && $u) {
        session_regenerate_id(true);
        $_SESSION = ['admin'=>$u['id'],'pharm'=>$u['pharmacy_id'],'last'=>time(),'csrf'=>bin2hex(random_bytes(32))];
        if (password_needs_rehash($u['password_hash'], PASSWORD_BCRYPT, ['cost'=>12]))
          $pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($_POST['p'], PASSWORD_BCRYPT, ['cost'=>12]), $u['id']]);
        header('Location: admin.php'); exit;
      }
      $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1; $_SESSION['lock'] = time() + 60; $err = 'Wrong username or password.';
    }
  } elseif (isset($_SESSION['admin'])) {
    $ph = (int)$_SESSION['pharm'];
    if ($a === 'logout') { session_destroy(); header('Location: admin.php'); exit; }
    if ($a === 'stock') {
      $name = trim($_POST['name'] ?? ''); $str = trim($_POST['strength'] ?? ''); $form = trim($_POST['form'] ?? 'tablet');
      $price = (float)($_POST['price'] ?? 0); $qty = (int)($_POST['qty'] ?? -1);
      if ($name === '' || $price < 0 || $qty < 0) $err = 'Enter a name, a price and a quantity of 0 or more.';
      else {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO medicines (name,strength,form) VALUES (?,?,?) ON CONFLICT (name,strength,form) DO NOTHING')->execute([$name,$str,$form]);
        $m = $pdo->prepare('SELECT id FROM medicines WHERE name=? AND strength=? AND form=?'); $m->execute([$name,$str,$form]);
        $pdo->prepare('INSERT INTO pharmacy_stock (pharmacy_id,medicine_id,price,quantity) VALUES (?,?,?,?)
          ON CONFLICT (pharmacy_id,medicine_id) DO UPDATE SET price=EXCLUDED.price, quantity=EXCLUDED.quantity, updated_at=now()')->execute([$ph,$m->fetchColumn(),$price,$qty]);
        $pdo->commit(); $msg = 'Stock saved.';
      }
    }
    if ($a === 'status') {
      $oid = (int)$_POST['oid']; $new = $_POST['status'] ?? '';
      if (in_array($new, ['pending','ready','collected','cancelled'], true)) {
        $pdo->beginTransaction();
        $o = $pdo->prepare('SELECT status FROM orders WHERE id=? AND pharmacy_id=? FOR UPDATE'); $o->execute([$oid,$ph]); $old = $o->fetchColumn();
        if ($old && $old !== 'cancelled') {
          if ($new === 'cancelled') // return reserved units to stock
            $pdo->prepare('UPDATE pharmacy_stock s SET quantity=s.quantity+i.quantity FROM order_items i
              WHERE i.medicine_id=s.medicine_id AND i.order_id=? AND s.pharmacy_id=?')->execute([$oid,$ph]);
          $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$new,$oid]); $msg = 'Order updated.';
        }
        $pdo->commit();
      }
    }
  }
}
$in = isset($_SESSION['admin']);
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pharmacist dashboard</title><script src="https://cdn.tailwindcss.com/3.4.17"></script></head>
<body class="bg-white text-slate-950 text-lg"><main class="max-w-3xl mx-auto p-4 space-y-5">
<?php if (!$in): ?>
  <h1 class="text-2xl font-extrabold">Pharmacist login</h1>
  <?php if ($err): ?><p class="font-bold text-red-800" role="alert"><?= e($err) ?></p><?php endif; ?>
  <form method="post" class="space-y-3">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="a" value="login">
    <label class="block font-bold">Username <input name="u" required autocomplete="username" class="w-full border-2 border-slate-950 rounded-lg px-3 py-3"></label>
    <label class="block font-bold">Password <input name="p" type="password" required autocomplete="current-password" class="w-full border-2 border-slate-950 rounded-lg px-3 py-3"></label>
    <button class="w-full bg-emerald-900 text-white font-bold rounded-lg py-3">Log in</button>
  </form>
<?php else:
  $ph = (int)$_SESSION['pharm'];
  $p = $pdo->prepare('SELECT name,town FROM pharmacies WHERE id=?'); $p->execute([$ph]); $pharm = $p->fetch();
  $stock = $pdo->prepare('SELECT m.name,m.strength,m.form,s.price,s.quantity FROM pharmacy_stock s JOIN medicines m ON m.id=s.medicine_id WHERE s.pharmacy_id=? ORDER BY s.quantity>10, m.name');
  $stock->execute([$ph]);
  $ord = $pdo->prepare("SELECT o.id,o.customer_name,o.customer_phone,o.status,o.created_at,
      string_agg(i.quantity||'× '||m.name||' '||m.strength, ', ') AS items, SUM(i.quantity*i.unit_price) AS total
    FROM orders o JOIN order_items i ON i.order_id=o.id JOIN medicines m ON m.id=i.medicine_id
    WHERE o.pharmacy_id=? GROUP BY o.id ORDER BY (o.status='pending') DESC, o.id DESC LIMIT 50");
  $ord->execute([$ph]);
?>
  <div class="flex justify-between items-center">
    <h1 class="text-2xl font-extrabold"><?= e($pharm['name']) ?> <span class="text-slate-600 text-lg">(<?= e($pharm['town']) ?>)</span></h1>
    <form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="a" value="logout">
      <button class="border-2 border-slate-950 rounded-lg px-4 py-2 font-bold">Log out</button></form>
  </div>
  <?php if ($msg): ?><p class="font-bold text-emerald-900" role="status"><?= e($msg) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="font-bold text-red-800" role="alert"><?= e($err) ?></p><?php endif; ?>

  <section><h2 class="text-xl font-extrabold mb-2">Add or update stock</h2>
  <form method="post" class="grid grid-cols-2 gap-2">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="a" value="stock">
    <input name="name" placeholder="Medicine" required class="col-span-2 border-2 border-slate-950 rounded-lg px-3 py-3">
    <input name="strength" placeholder="Strength (500mg)" class="border-2 border-slate-950 rounded-lg px-3 py-3">
    <input name="form" placeholder="Form (tablet)" value="tablet" class="border-2 border-slate-950 rounded-lg px-3 py-3">
    <input name="price" type="number" step="0.01" min="0" placeholder="Price KES" required class="border-2 border-slate-950 rounded-lg px-3 py-3">
    <input name="qty" type="number" min="0" placeholder="Quantity" required class="border-2 border-slate-950 rounded-lg px-3 py-3">
    <button class="col-span-2 bg-emerald-900 text-white font-bold rounded-lg py-3">Save stock</button>
  </form></section>

  <section><h2 class="text-xl font-extrabold mb-2">Orders</h2><div class="space-y-2">
  <?php foreach ($ord as $o): ?>
    <form method="post" class="border-2 border-slate-950 rounded-lg p-3 flex flex-wrap gap-2 items-center justify-between">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="a" value="status"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
      <div><p class="font-bold">#<?= (int)$o['id'] ?> <?= e($o['customer_name']) ?> <a class="underline text-emerald-900" href="tel:<?= e($o['customer_phone']) ?>"><?= e($o['customer_phone']) ?></a></p>
        <p><?= e($o['items']) ?> • KES <?= e($o['total']) ?></p></div>
      <div class="flex gap-2"><select name="status" class="border-2 border-slate-950 rounded-lg px-2 py-2">
        <?php foreach (['pending','ready','collected','cancelled'] as $s): ?><option <?= $s === $o['status'] ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
        <button class="bg-slate-950 text-white font-bold rounded-lg px-3 py-2">Update</button></div>
    </form>
  <?php endforeach; ?></div></section>

  <section><h2 class="text-xl font-extrabold mb-2">Current stock (lowest first)</h2>
  <div class="overflow-x-auto"><table class="w-full text-left"><tr class="border-b-2 border-slate-950"><th>Medicine</th><th>Price</th><th>Qty</th></tr>
  <?php foreach ($stock as $r): ?><tr class="border-b border-slate-300 <?= $r['quantity'] <= 10 ? 'bg-amber-100 font-bold' : '' ?>">
    <td><?= e("{$r['name']} {$r['strength']} ({$r['form']})") ?></td><td><?= e($r['price']) ?></td><td><?= (int)$r['quantity'] ?></td></tr><?php endforeach; ?></table></div></section>
<?php endif; ?>
</main></body></html>
