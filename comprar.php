<?php
require __DIR__ . '/conexion.php';

function volver($params) {
    header('Location: ./?' . http_build_query($params));
    exit;
}

$producto_id = (int)($_POST['producto_id'] ?? 0);
$cantidad    = (int)($_POST['cantidad'] ?? 0);

if ($producto_id <= 0 || $cantidad <= 0) {
    volver(['error' => 'datos']);
}

try {
    $pdo->beginTransaction();

    // Bloquea la fila para que dos compras al mismo tiempo no vendan de mas
    $stmt = $pdo->prepare("SELECT nombre, precio, stock FROM productos WHERE id = ? FOR UPDATE");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        $pdo->rollBack();
        volver(['error' => 'producto']);
    }

    if ($cantidad > (int)$producto['stock']) {
        $pdo->rollBack();
        volver(['error' => 'stock']);
    }

    $total = $producto['precio'] * $cantidad;

    $stmt = $pdo->prepare("INSERT INTO compras (producto_id, producto_nombre, cantidad, total) VALUES (?, ?, ?, ?)");
    $stmt->execute([$producto_id, $producto['nombre'], $cantidad, $total]);

    $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
    $stmt->execute([$cantidad, $producto_id]);

    $pdo->commit();
    volver(['compra' => 'ok', 'id' => $producto_id, 'cantidad' => $cantidad]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    volver(['error' => 'servidor']);
}
