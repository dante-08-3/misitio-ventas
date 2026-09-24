<?php
require __DIR__ . '/conexion.php';

try {
    $stmt = $pdo->query("
        SELECT id, nombre, descripcion, precio, stock,
               COALESCE(categoria, 'Otros') AS categoria,
               COALESCE(imagen, 'img/sin-imagen.svg') AS imagen
        FROM productos
        ORDER BY id
    ");
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar el catalogo: " . $e->getMessage());
}

// Categorias en el orden en que aparecen
$categorias = array_values(array_unique(array_column($productos, 'categoria')));

// Mensaje despues de comprar
$aviso = null;
$aviso_tipo = 'ok';
if (isset($_GET['compra']) && $_GET['compra'] === 'ok') {
    $id = (int)($_GET['id'] ?? 0);
    $cant = max(1, (int)($_GET['cantidad'] ?? 1));
    $nombre = 'tu producto';
    foreach ($productos as $p) {
        if ((int)$p['id'] === $id) { $nombre = $p['nombre']; break; }
    }
    $aviso = "Compra registrada: $cant × $nombre";
} elseif (isset($_GET['error'])) {
    $errores = [
        'datos'    => 'Elige una cantidad de al menos 1 pieza.',
        'producto' => 'Ese producto ya no está en el catálogo.',
        'stock'    => 'No hay suficientes piezas. Elige una cantidad menor.',
        'servidor' => 'No se pudo registrar la compra. Intenta de nuevo en un momento.',
    ];
    $aviso = $errores[$_GET['error']] ?? $errores['servidor'];
    $aviso_tipo = 'error';
}

// Producto destacado para la portada: la sudadera si existe, si no el primero
$destacado = $productos[0] ?? null;
foreach ($productos as $p) {
    if (stripos($p['nombre'], 'sudadera') !== false) { $destacado = $p; break; }
}

function h($t) { return htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); }
function precio($n) { return '$' . number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tienda del campus · misitio.edu</title>
<meta name="description" content="Ropa, accesorios y papelería oficial de misitio.edu.">
<link rel="icon" href="img/playera.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
<style>
  :root {
    --azul: #2340C8;
    --azul-osc: #1B33A3;
    --amarillo: #F6C343;
    --tinta: #1A1D24;
    --gris: #5B6272;
    --linea: #DADEE6;
    --fondo: #F3F4F7;
    --blanco: #FFFFFF;
    --rojo: #C23A2E;
    --radio: 14px;
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body {
    margin: 0;
    background: var(--fondo);
    color: var(--tinta);
    font-family: 'Archivo', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-size: 16px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }
  img { max-width: 100%; height: auto; display: block; }
  a { color: inherit; }
  :focus-visible { outline: 3px solid var(--amarillo); outline-offset: 2px; }
  .contenedor { width: min(1180px, 100% - 40px); margin-inline: auto; }

  /* Barra superior */
  .barra {
    position: sticky; top: 0; z-index: 20;
    background: var(--blanco);
    border-bottom: 1px solid var(--linea);
  }
  .barra .contenedor { display: flex; align-items: center; gap: 24px; height: 68px; }
  .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
  .logo-marca {
    width: 36px; height: 36px; border-radius: 9px;
    background: var(--azul); color: var(--blanco);
    display: grid; place-items: center;
    font-weight: 900; font-stretch: 125%; font-size: 20px;
  }
  .logo-texto { line-height: 1.1; }
  .logo-texto strong { display: block; font-stretch: 115%; font-weight: 800; font-size: 17px; }
  .logo-texto span { font-size: 13px; color: var(--gris); }
  .buscar { margin-left: auto; position: relative; width: min(340px, 45vw); }
  .buscar input {
    width: 100%; height: 42px;
    border: 1px solid var(--linea); border-radius: 999px;
    padding: 0 16px 0 40px;
    font: inherit; font-size: 15px;
    background: var(--fondo);
  }
  .buscar input:focus { outline: none; border-color: var(--azul); background: var(--blanco); }
  .buscar svg { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gris); }

  /* Portada */
  .portada { background: var(--azul); color: var(--blanco); overflow: hidden; }
  .portada .contenedor {
    display: grid; grid-template-columns: 1.15fr 0.85fr; align-items: center; gap: 40px;
    padding-block: 56px 64px;
  }
  .portada h1 {
    margin: 0 0 20px;
    font-stretch: 125%; font-weight: 900;
    font-size: clamp(44px, 7vw, 92px);
    line-height: 0.92; letter-spacing: -0.015em;
  }
  .portada p { margin: 0 0 28px; font-size: 19px; max-width: 34ch; color: #DCE2FA; }
  .boton-claro {
    display: inline-flex; align-items: center; height: 50px; padding: 0 26px;
    background: var(--amarillo); color: var(--tinta);
    border-radius: 999px; text-decoration: none;
    font-weight: 800; font-stretch: 110%;
  }
  .boton-claro:hover { background: #FFD466; }
  .destacado {
    position: relative; text-decoration: none; display: block;
    transform: rotate(2deg);
  }
  .destacado img { border-radius: 22px; box-shadow: 0 30px 60px -20px rgba(8, 16, 60, .55); }
  .destacado-etiqueta {
    position: absolute; left: -18px; bottom: 26px;
    background: var(--blanco); color: var(--tinta);
    padding: 12px 18px; border-radius: 12px;
    transform: rotate(-2deg);
    box-shadow: 0 10px 24px -10px rgba(8, 16, 60, .45);
  }
  .destacado-etiqueta small { display: block; color: var(--gris); font-size: 13px; }
  .destacado-etiqueta strong { font-stretch: 115%; font-size: 20px; }

  /* Catalogo */
  .catalogo { padding-block: 48px 72px; }
  .catalogo-encabezado { display: flex; align-items: end; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
  .catalogo h2 { margin: 0; font-stretch: 120%; font-weight: 900; font-size: 36px; line-height: 1; }
  .conteo { color: var(--gris); font-size: 15px; }
  .filtros { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 28px; }
  .chip {
    height: 38px; padding: 0 16px; border-radius: 999px;
    border: 1px solid var(--linea); background: var(--blanco); color: var(--tinta);
    font: inherit; font-size: 15px; font-weight: 600; cursor: pointer;
  }
  .chip:hover { border-color: var(--tinta); }
  .chip[aria-pressed="true"] { background: var(--tinta); border-color: var(--tinta); color: var(--blanco); }
  .orden { margin-left: auto; display: flex; align-items: center; gap: 8px; font-size: 15px; color: var(--gris); }
  .orden select {
    height: 38px; border-radius: 10px; border: 1px solid var(--linea);
    background: var(--blanco); font: inherit; font-size: 15px; padding: 0 10px; color: var(--tinta);
  }

  .rejilla { display: grid; grid-template-columns: repeat(4, 1fr); gap: 36px 24px; }
  .producto { display: flex; flex-direction: column; }
  .foto { position: relative; border-radius: var(--radio); overflow: hidden; aspect-ratio: 1; margin-bottom: 14px; }
  .foto img { width: 100%; height: 100%; object-fit: cover; transition: transform .35s ease; }
  .producto:hover .foto img { transform: scale(1.04); }
  .insignia {
    position: absolute; top: 12px; left: 12px;
    padding: 5px 10px; border-radius: 8px;
    font-size: 13px; font-weight: 700;
    background: var(--amarillo); color: var(--tinta);
  }
  .insignia.agotado { background: var(--tinta); color: var(--blanco); }
  .producto.sin-stock .foto img { filter: grayscale(1); opacity: .6; }
  .categoria { font-size: 13px; color: var(--gris); margin: 0 0 2px; }
  .producto h3 { margin: 0 0 4px; font-stretch: 108%; font-weight: 800; font-size: 19px; line-height: 1.2; }
  .descripcion { margin: 0 0 12px; font-size: 14px; color: var(--gris); line-height: 1.45; }
  .precio-fila { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-top: auto; margin-bottom: 12px; }
  .precio { font-stretch: 112%; font-weight: 800; font-size: 22px; }
  .stock { font-size: 13px; color: var(--gris); white-space: nowrap; }
  .compra { display: flex; gap: 8px; }
  .compra input {
    width: 64px; height: 44px; border-radius: 10px;
    border: 1px solid var(--linea); background: var(--blanco);
    font: inherit; text-align: center;
  }
  .compra button {
    flex: 1; height: 44px; border: 0; border-radius: 10px;
    background: var(--azul); color: var(--blanco);
    font: inherit; font-weight: 700; cursor: pointer;
  }
  .compra button:hover { background: var(--azul-osc); }
  .compra button:disabled { background: var(--linea); color: var(--gris); cursor: not-allowed; }

  .vacio { display: none; padding: 48px 0; text-align: center; color: var(--gris); }
  .vacio strong { display: block; color: var(--tinta); font-size: 20px; margin-bottom: 6px; }

  /* Aviso de compra */
  .aviso {
    position: fixed; left: 50%; bottom: 24px; z-index: 30;
    transform: translateX(-50%);
    display: flex; align-items: center; gap: 12px;
    background: var(--tinta); color: var(--blanco);
    padding: 14px 18px 14px 20px; border-radius: 12px;
    box-shadow: 0 16px 40px -12px rgba(0,0,0,.45);
    max-width: calc(100% - 32px);
    animation: aparecer .35s ease-out;
  }
  .aviso.error { background: var(--rojo); }
  .aviso button { background: none; border: 0; color: inherit; font-size: 22px; line-height: 1; cursor: pointer; padding: 0 4px; opacity: .8; }
  @keyframes aparecer { from { opacity: 0; transform: translate(-50%, 16px); } }

  footer { border-top: 1px solid var(--linea); background: var(--blanco); }
  footer .contenedor { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding-block: 28px; font-size: 14px; color: var(--gris); }

  @media (max-width: 960px) {
    .rejilla { grid-template-columns: repeat(3, 1fr); }
  }
  @media (max-width: 760px) {
    .portada .contenedor { grid-template-columns: 1fr; padding-block: 40px 48px; }
    .destacado { max-width: 260px; margin: 8px auto 24px; transform: none; }
    .destacado-etiqueta { left: 50%; bottom: -28px; transform: translateX(-50%); white-space: nowrap; }
    .rejilla { grid-template-columns: repeat(2, 1fr); gap: 28px 14px; }
    .logo-texto span { display: none; }
    .orden { margin-left: 0; width: 100%; }
    .producto h3 { font-size: 17px; }
    .descripcion { display: none; }
    .precio { font-size: 19px; }
    .precio-fila { flex-direction: column; align-items: flex-start; gap: 0; }
    .compra input { width: 52px; }
  }
  @media (prefers-reduced-motion: reduce) {
    * { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
  }
</style>
</head>
<body>

<header class="barra">
  <div class="contenedor">
    <a class="logo" href="./">
      <span class="logo-marca" aria-hidden="true">M</span>
      <span class="logo-texto"><strong>misitio.edu</strong><span>Tienda del campus</span></span>
    </a>
    <label class="buscar">
      <span class="visually-hidden" style="position:absolute;left:-9999px">Buscar productos</span>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" id="buscar" placeholder="Buscar sudadera, termo…" autocomplete="off">
    </label>
  </div>
</header>

<section class="portada">
  <div class="contenedor">
    <div>
      <h1>Lleva el campus contigo</h1>
      <p>Ropa, accesorios y papelería oficial de misitio.edu, con el logo de tu escuela.</p>
      <a class="boton-claro" href="#catalogo">Ver productos</a>
    </div>
    <?php if ($destacado): ?>
    <a class="destacado" href="#p<?= (int)$destacado['id'] ?>">
      <img src="<?= h($destacado['imagen']) ?>" alt="<?= h($destacado['nombre']) ?>" width="400" height="400">
      <span class="destacado-etiqueta">
        <small>Favorito de la temporada</small>
        <strong><?= h($destacado['nombre']) ?> · <?= precio($destacado['precio']) ?></strong>
      </span>
    </a>
    <?php endif; ?>
  </div>
</section>

<main class="catalogo contenedor" id="catalogo">
  <div class="catalogo-encabezado">
    <h2>Catálogo</h2>
    <span class="conteo" id="conteo"><?= count($productos) ?> productos</span>
  </div>

  <div class="filtros" role="group" aria-label="Filtrar por categoría">
    <button class="chip" type="button" data-cat="todo" aria-pressed="true">Todo</button>
    <?php foreach ($categorias as $c): ?>
    <button class="chip" type="button" data-cat="<?= h($c) ?>" aria-pressed="false"><?= h($c) ?></button>
    <?php endforeach; ?>
    <label class="orden">Ordenar
      <select id="orden">
        <option value="destacados">Destacados</option>
        <option value="menor">Menor precio</option>
        <option value="mayor">Mayor precio</option>
      </select>
    </label>
  </div>

  <div class="rejilla" id="rejilla">
    <?php foreach ($productos as $i => $p):
      $stock = (int)$p['stock']; ?>
    <article class="producto<?= $stock <= 0 ? ' sin-stock' : '' ?>" id="p<?= (int)$p['id'] ?>"
             data-cat="<?= h($p['categoria']) ?>"
             data-texto="<?= h(mb_strtolower($p['nombre'] . ' ' . $p['descripcion'] . ' ' . $p['categoria'], 'UTF-8')) ?>"
             data-precio="<?= (float)$p['precio'] ?>"
             data-orden="<?= $i ?>">
      <div class="foto">
        <img src="<?= h($p['imagen']) ?>" alt="<?= h($p['nombre']) ?>" width="400" height="400" loading="lazy">
        <?php if ($stock <= 0): ?>
          <span class="insignia agotado">Agotado</span>
        <?php elseif ($stock <= 5): ?>
          <span class="insignia">Quedan <?= $stock ?></span>
        <?php endif; ?>
      </div>
      <p class="categoria"><?= h($p['categoria']) ?></p>
      <h3><?= h($p['nombre']) ?></h3>
      <p class="descripcion"><?= h($p['descripcion']) ?></p>
      <div class="precio-fila">
        <span class="precio"><?= precio($p['precio']) ?></span>
        <span class="stock"><?= $stock > 0 ? $stock . ' disponibles' : 'Sin piezas' ?></span>
      </div>
      <?php if ($stock > 0): ?>
      <form class="compra" action="comprar.php" method="POST">
        <input type="hidden" name="producto_id" value="<?= (int)$p['id'] ?>">
        <input type="number" name="cantidad" value="1" min="1" max="<?= $stock ?>" aria-label="Cantidad de <?= h($p['nombre']) ?>">
        <button type="submit">Comprar</button>
      </form>
      <?php else: ?>
      <div class="compra"><button type="button" disabled>Agotado</button></div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <div class="vacio" id="vacio">
    <strong>No hay productos con esa búsqueda</strong>
    Prueba con otra palabra o elige “Todo”.
  </div>
</main>

<footer>
  <div class="contenedor">
    <span>© <?= date('Y') ?> misitio.edu · Tienda del campus</span>
    <span>Hecho con PHP y PostgreSQL</span>
  </div>
</footer>

<?php if ($aviso): ?>
<div class="aviso <?= $aviso_tipo === 'error' ? 'error' : '' ?>" role="status" id="aviso">
  <span><?= h($aviso) ?></span>
  <button type="button" aria-label="Cerrar aviso" onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>

<script>
  const rejilla = document.getElementById('rejilla');
  const productos = [...rejilla.querySelectorAll('.producto')];
  const chips = document.querySelectorAll('.chip');
  const buscar = document.getElementById('buscar');
  const orden = document.getElementById('orden');
  const conteo = document.getElementById('conteo');
  const vacio = document.getElementById('vacio');
  let categoria = 'todo';

  function actualizar() {
    const q = buscar.value.trim().toLowerCase();
    let visibles = 0;
    productos.forEach(p => {
      const ok = (categoria === 'todo' || p.dataset.cat === categoria) && (!q || p.dataset.texto.includes(q));
      p.hidden = !ok;
      if (ok) visibles++;
    });
    const orden_ = [...productos].sort((a, b) => {
      if (orden.value === 'menor') return a.dataset.precio - b.dataset.precio;
      if (orden.value === 'mayor') return b.dataset.precio - a.dataset.precio;
      return a.dataset.orden - b.dataset.orden;
    });
    orden_.forEach(p => rejilla.appendChild(p));
    conteo.textContent = visibles + (visibles === 1 ? ' producto' : ' productos');
    vacio.style.display = visibles ? 'none' : 'block';
  }

  chips.forEach(c => c.addEventListener('click', () => {
    chips.forEach(x => x.setAttribute('aria-pressed', 'false'));
    c.setAttribute('aria-pressed', 'true');
    categoria = c.dataset.cat;
    actualizar();
  }));
  buscar.addEventListener('input', () => {
    if (buscar.value && location.hash !== '#catalogo') document.getElementById('catalogo').scrollIntoView();
    actualizar();
  });
  orden.addEventListener('change', actualizar);

  // Quita el aviso y limpia la URL para que no reaparezca al recargar
  const aviso = document.getElementById('aviso');
  if (aviso) {
    setTimeout(() => aviso.remove(), 6000);
    history.replaceState(null, '', location.pathname);
  }
</script>
</body>
</html>
