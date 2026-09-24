<?php
require __DIR__ . '/conexion.php';

try {
    $producto_id = isset($_POST['producto_id']) ? (int)$_POST['producto_id'] : 0;
    $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : 0;

    if ($producto_id <= 0 || $cantidad <= 0) {
        die("Datos invalidos.");
    }

    $stmt = $pdo->prepare("SELECT nombre, precio, stock FROM productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        die("Producto no encontrado.");
    }

    if ($cantidad > $producto['stock']) {
        die("No hay suficiente stock disponible.");
    }

    $total = $producto['precio'] * $cantidad;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO compras (producto_id, producto_nombre, cantidad, total) VALUES (?, ?, ?, ?)");
    $stmt->execute([$producto_id, $producto['nombre'], $cantidad, $total]);

    $stmt = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
    $stmt->execute([$cantidad, $producto_id]);

    $pdo->commit();

    header("Location: productos.php?compra=ok");
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage());
}
