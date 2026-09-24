<?php
require __DIR__ . '/conexion.php';

try {
    $stmt = $pdo->query("SELECT id, nombre, descripcion, precio, stock FROM productos ORDER BY id");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexion: " . $e->getMessage());
}

$compra_exitosa = isset($_GET['compra']) && $_GET['compra'] === 'ok';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catalogo - misitio.edu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  :root {
    --bg: #0c1220;
    --bg-card: #121a2c;
    --border: #223049;
    --text: #e7ecf5;
    --text-dim: #6f7f9c;
    --amber: #e8964f;
    --green: #52d67a;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font-family: 'JetBrains Mono', ui-monospace, Consolas, monospace;
    padding: 40px 24px;
  }
  header { text-align: center; margin-bottom: 32px; }
  header h1 { color: var(--amber); font-size: 32px; margin: 0 0 8px; }
  header p { color: var(--text-dim); font-size: 14px; margin: 0; }
  .banner {
    max-width: 600px;
    margin: 0 auto 28px;
    background: #123320;
    border: 1px solid var(--green);
    color: var(--green);
    padding: 14px 18px;
    border-radius: 6px;
    text-align: center;
    font-size: 14px;
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    max-width: 1000px;
    margin: 0 auto;
  }
  .card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
  }
  .card h2 { font-size: 18px; margin: 0 0 8px; color: var(--text); }
  .card p { color: var(--text-dim); font-size: 13px; margin: 0 0 16px; min-height: 32px; }
  .price { color: var(--amber); font-size: 22px; font-weight: 700; }
  .stock { color: var(--text-dim); font-size: 12px; margin-top: 6px; margin-bottom: 16px; }
  form.compra { display: flex; gap: 8px; margin-top: auto; }
  form.compra input[type="number"] {
    width: 60px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    font-family: inherit;
    padding: 8px;
    border-radius: 4px;
  }
  form.compra button {
    flex: 1;
    background: var(--amber);
    color: var(--bg);
    border: none;
    font-family: inherit;
    font-weight: 700;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
  }
  form.compra button:disabled {
    background: var(--border);
    color: var(--text-dim);
    cursor: not-allowed;
  }
</style>
</head>
<body>
  <header>
    <h1>Catalogo de productos</h1>
    <p>www.misitio.edu</p>
  </header>

  <?php if ($compra_exitosa): ?>
  <div class="banner">Compra registrada correctamente</div>
  <?php endif; ?>

  <div class="grid">
    <?php foreach ($productos as $p): ?>
    <div class="card">
      <h2><?= htmlspecialchars($p['nombre']) ?></h2>
      <p><?= htmlspecialchars($p['descripcion']) ?></p>
      <div class="price">$<?= number_format($p['precio'], 2) ?></div>
      <div class="stock">Disponibles: <?= (int)$p['stock'] ?></div>

      <?php if ($p['stock'] > 0): ?>
      <form class="compra" action="comprar.php" method="POST">
        <input type="hidden" name="producto_id" value="<?= (int)$p['id'] ?>">
        <input type="number" name="cantidad" value="1" min="1" max="<?= (int)$p['stock'] ?>">
        <button type="submit">Comprar</button>
      </form>
      <?php else: ?>
      <form class="compra">
        <button type="button" disabled>Agotado</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</body>
</html>
