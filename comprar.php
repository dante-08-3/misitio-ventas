<?php
require __DIR__ . '/conexion.php';

function volver($params) {
    header('Location: ./?' . http_build_query($params));
    exit;
}

// La bolsa llega como JSON: [{"id":3,"talla":"M","cantidad":2}, ...]
$items = json_decode($_POST['carrito'] ?? '[]', true);
if (!is_array($items) || !$items) {
    volver(['error' => 'vacio']);
}

$limpios = [];
$por_producto = [];
foreach ($items as $i) {
    $id = (int)($i['id'] ?? 0);
    $cant = (int)($i['cantidad'] ?? 0);
    $talla = substr(preg_replace('/[^\p{L}0-9]/u', '', (string)($i['talla'] ?? '')), 0, 10);
    if ($id <= 0 || $cant <= 0 || $cant > 100) {
        volver(['error' => 'datos']);
    }
    $limpios[] = ['id' => $id, 'cantidad' => $cant, 'talla' => $talla ?: 'Única'];
    $por_producto[$id] = ($por_producto[$id] ?? 0) + $cant;
}
ksort($por_producto); // bloquear siempre en el mismo orden evita bloqueos cruzados

try {
    $pdo->beginTransaction();

    // Bloquear los productos para que dos compras al mismo tiempo no vendan de mas
    $productos = [];
    $stmt = $pdo->prepare("SELECT id, nombre, precio, stock FROM productos WHERE id = ? FOR UPDATE");
    foreach ($por_producto as $id => $cant) {
        $stmt->execute([$id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) {
            $pdo->rollBack();
            volver(['error' => 'producto']);
        }
        if ($cant > (int)$p['stock']) {
            $pdo->rollBack();
            volver(['error' => 'stock', 'p' => $p['nombre']]);
        }
        $productos[$id] = $p;
    }

    $insertar = $pdo->prepare("INSERT INTO compras (producto_id, producto_nombre, cantidad, total, talla) VALUES (?, ?, ?, ?, ?)");
    $total_general = 0;
    $piezas = 0;
    foreach ($limpios as $i) {
        $p = $productos[$i['id']];
        $total = $p['precio'] * $i['cantidad'];
        $insertar->execute([$i['id'], $p['nombre'], $i['cantidad'], $total, $i['talla']]);
        $total_general += $total;
        $piezas += $i['cantidad'];
    }

    $restar = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
    foreach ($por_producto as $id => $cant) {
        $restar->execute([$cant, $id]);
    }

    $pdo->commit();
    volver(['compra' => 'ok', 'piezas' => $piezas, 'total' => number_format($total_general, 2, '.', '')]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    volver(['error' => 'servidor']);
}
