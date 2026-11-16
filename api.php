<?php
require __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';
try {
  if ($action === 'stock') {
    // Returns pharmacies (+ matching in-stock lines). Distance sorting happens client-side.
    $q = '%'.trim($_GET['q'] ?? '').'%';
    $st = db()->prepare("SELECT p.id AS pid,p.name AS pharmacy,p.town,p.street,p.phone,p.latitude AS lat,p.longitude AS lng,
        m.id AS mid,m.name AS medicine,m.strength,m.form,s.price,s.quantity
      FROM pharmacies p JOIN pharmacy_stock s ON s.pharmacy_id=p.id JOIN medicines m ON m.id=s.medicine_id
      WHERE m.name ILIKE ? ORDER BY m.name LIMIT 300");
    $st->execute([$q]);
    $towns = db()->query('SELECT town, AVG(latitude) AS lat, AVG(longitude) AS lng FROM pharmacies GROUP BY town ORDER BY town')->fetchAll();
    echo json_encode(['rows'=>$st->fetchAll(),'towns'=>$towns], JSON_NUMERIC_CHECK);
  } elseif ($action === 'order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    $ph=(int)($d['pharmacy_id']??0); $med=(int)($d['medicine_id']??0); $qty=(int)($d['qty']??0);
    $name=trim($d['name']??''); $phone=preg_replace('/[^\d+]/','',$d['phone']??'');
    if (!$ph||!$med||$qty<1||$qty>20||$name===''||mb_strlen($name)>80||!preg_match('/^\+?\d{9,15}$/',$phone))
      throw new InvalidArgumentException('Check your name, phone number and quantity (1-20).');
    $pdo = db(); $pdo->beginTransaction();
    $s = $pdo->prepare('SELECT price,quantity FROM pharmacy_stock WHERE pharmacy_id=? AND medicine_id=? FOR UPDATE');
    $s->execute([$ph,$med]); $row = $s->fetch();
    if (!$row || $row['quantity'] < $qty) { $pdo->rollBack(); throw new InvalidArgumentException('Not enough stock left. Try a smaller quantity.'); }
    $pdo->prepare('UPDATE pharmacy_stock SET quantity=quantity-? WHERE pharmacy_id=? AND medicine_id=?')->execute([$qty,$ph,$med]);
    $ins = $pdo->prepare('INSERT INTO orders (pharmacy_id,customer_name,customer_phone) VALUES (?,?,?) RETURNING id');
    $ins->execute([$ph,$name,$phone]); $oid = (int)$ins->fetchColumn();
    $pdo->prepare('INSERT INTO order_items (order_id,medicine_id,quantity,unit_price) VALUES (?,?,?,?)')->execute([$oid,$med,$qty,$row['price']]);
    $pdo->commit();
    echo json_encode(['ok'=>true,'order_id'=>$oid,'total'=>$qty*$row['price']]);
  } else { http_response_code(404); echo '{"error":"Unknown action"}'; }
} catch (InvalidArgumentException $x) { http_response_code(422); echo json_encode(['error'=>$x->getMessage()]);
} catch (Throwable $x) { if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); http_response_code(500); echo '{"error":"Server error. Try again."}'; }
