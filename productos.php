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
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar el catalogo: " . $e->getMessage());
}

// Si existe una foto real (misma ruta pero .jpg, .webp o .png), se usa en lugar del dibujo SVG
function foto($ruta) {
    $base = preg_replace('/\.[a-z]+$/i', '', $ruta);
    foreach (['.jpg', '.jpeg', '.webp', '.png'] as $ext) {
        if (is_file(__DIR__ . '/' . $base . $ext)) return $base . $ext;
    }
    return $ruta;
}
function h($t) { return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8'); }
function precio($n) { return '$' . number_format((float)$n, 2); }

$productos = [];
foreach ($filas as $p) {
    $ropa_con_talla = $p['categoria'] === 'Ropa' && !preg_match('/gorra|gorro|calceta/i', $p['nombre']);
    $productos[] = [
        'id'          => (int)$p['id'],
        'nombre'      => $p['nombre'],
        'descripcion' => $p['descripcion'],
        'precio'      => (float)$p['precio'],
        'stock'       => (int)$p['stock'],
        'categoria'   => $p['categoria'],
        'imagen'      => foto($p['imagen']),
        'tallas'      => $ropa_con_talla ? ['CH', 'M', 'G', 'XG'] : ['Única'],
    ];
}
$categorias = array_values(array_unique(array_column($productos, 'categoria')));

// Imagen para cada categoria: img/cat-ropa.jpg si existe, si no un producto representativo
$preferidos = ['Ropa' => 'sudadera', 'Accesorios' => 'mochila', 'Papelería' => 'cuaderno'];
$foto_categoria = [];
foreach ($categorias as $c) {
    $slug = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $c));
    $propia = foto("img/cat-$slug.svg");
    if ($propia !== "img/cat-$slug.svg") { $foto_categoria[$c] = $propia; continue; }
    foreach ($productos as $p) {
        if ($p['categoria'] === $c && (!isset($preferidos[$c]) || stripos($p['nombre'], $preferidos[$c]) !== false)) {
            $foto_categoria[$c] = $p['imagen']; break;
        }
    }
    if (!isset($foto_categoria[$c])) {
        foreach ($productos as $p) { if ($p['categoria'] === $c) { $foto_categoria[$c] = $p['imagen']; break; } }
    }
}

// Portada: img/portada.jpg si existe
$portada = foto('img/portada.svg');
$portada_foto = $portada !== 'img/portada.svg';
$destacado = null;
foreach ($productos as $p) { if (stripos($p['nombre'], 'sudadera') !== false) { $destacado = $p; break; } }
$destacado = $destacado ?? ($productos[0] ?? null);

// Avisos despues de comprar
$aviso = null; $aviso_tipo = 'ok';
if (($_GET['compra'] ?? '') === 'ok') {
    $piezas = max(1, (int)($_GET['piezas'] ?? 1));
    $total  = (float)($_GET['total'] ?? 0);
    $aviso = "Compra registrada: $piezas " . ($piezas === 1 ? 'pieza' : 'piezas') . " por " . precio($total) . ".";
} elseif (isset($_GET['error'])) {
    $prod = trim($_GET['p'] ?? '');
    $errores = [
        'vacio'    => 'Tu bolsa está vacía. Agrega un producto para comprar.',
        'datos'    => 'La bolsa tenía datos inválidos. Vuelve a agregar tus productos.',
        'producto' => 'Uno de los productos ya no está en el catálogo. Quítalo de tu bolsa.',
        'stock'    => 'No hay suficientes piezas' . ($prod ? " de $prod" : '') . '. Baja la cantidad en tu bolsa.',
        'servidor' => 'No se pudo registrar la compra. Intenta de nuevo en un momento.',
    ];
    $aviso = $errores[$_GET['error']] ?? $errores['servidor'];
    $aviso_tipo = 'error';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>misitio.edu · Tienda del campus</title>
<meta name="description" content="Ropa, accesorios y papelería oficial de misitio.edu.">
<link rel="icon" href="img/playera.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap" rel="stylesheet">
<style>
  :root {
    --negro: #111111;
    --blanco: #FFFFFF;
    --fondo-foto: #F5F5F5;
    --gris: #707072;
    --linea: #E5E5E5;
    --azul: #2340C8;
    --amarillo: #F6C343;
    --rojo: #D43F21;
  }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; scroll-padding-top: 130px; }
  body {
    margin: 0; background: var(--blanco); color: var(--negro);
    font-family: 'Archivo', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-size: 16px; line-height: 1.5; -webkit-font-smoothing: antialiased;
  }
  img { max-width: 100%; height: auto; display: block; }
  button { font: inherit; color: inherit; }
  :focus-visible { outline: 2px solid var(--azul); outline-offset: 2px; }
  .oculto { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
  .ancho { width: min(1440px, 100% - 48px); margin-inline: auto; }

  /* Botones */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 48px; padding: 0 26px; border-radius: 999px; border: 1.5px solid transparent;
    font-weight: 600; font-size: 16px; cursor: pointer; text-decoration: none; white-space: nowrap;
    transition: background .15s, color .15s, border-color .15s;
  }
  .btn-negro { background: var(--negro); color: var(--blanco); }
  .btn-negro:hover { background: #3a3a3a; }
  .btn-blanco { background: var(--blanco); color: var(--negro); }
  .btn-blanco:hover { background: #e8e8e8; }
  .btn-borde { background: transparent; border-color: currentColor; }
  .btn-borde:hover { border-color: var(--gris); }
  .btn:disabled { background: var(--linea); color: var(--gris); cursor: not-allowed; }
  .icono { width: 44px; height: 44px; border-radius: 50%; border: 0; background: transparent; display: grid; place-items: center; cursor: pointer; position: relative; }
  .icono:hover { background: var(--fondo-foto); }
  .burbuja {
    position: absolute; top: 4px; right: 2px; min-width: 18px; height: 18px; padding: 0 5px;
    border-radius: 9px; background: var(--negro); color: var(--blanco);
    font-size: 11px; font-weight: 700; display: grid; place-items: center;
  }
  .burbuja:empty { display: none; }

  /* Barra promo y encabezado */
  .promo { background: var(--fondo-foto); text-align: center; font-size: 14px; padding: 9px 16px; }
  .promo a { color: inherit; font-weight: 600; }
  .encabezado { position: sticky; top: 0; z-index: 40; background: var(--blanco); border-bottom: 1px solid var(--linea); }
  .encabezado .ancho { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; height: 64px; gap: 16px; }
  .logo { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; }
  .logo-marca { width: 34px; height: 34px; border-radius: 8px; background: var(--azul); color: var(--blanco); display: grid; place-items: center; font-weight: 900; font-stretch: 125%; font-size: 19px; }
  .logo b { font-stretch: 115%; font-weight: 800; font-size: 17px; }
  .menu { display: flex; gap: 4px; }
  .menu button { background: none; border: 0; padding: 8px 12px; font-weight: 600; cursor: pointer; border-bottom: 2px solid transparent; }
  .menu button:hover, .menu button.activo { border-bottom-color: var(--negro); }
  .acciones { display: flex; align-items: center; justify-content: flex-end; gap: 4px; }
  .buscar { position: relative; margin-right: 6px; }
  .buscar input {
    width: 190px; height: 40px; border: 0; border-radius: 999px; background: var(--fondo-foto);
    padding: 0 14px 0 40px; font: inherit; font-size: 15px; transition: width .2s;
  }
  .buscar input:focus { outline: none; width: 240px; background: #ececec; }
  .buscar svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; }

  /* Portada */
  .portada { position: relative; margin: 0 auto; width: min(1440px, 100% - 48px); margin-top: 24px; }
  .portada-fondo {
    position: relative; overflow: hidden; border-radius: 6px;
    min-height: min(78vh, 720px); background: var(--azul);
    display: grid; grid-template-columns: 1fr 1fr; align-items: end;
  }
  .portada-fondo.con-foto { display: block; }
  .portada-fondo.con-foto img.fondo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
  .portada-fondo.con-foto::after { content: ""; position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.55), rgba(0,0,0,0) 60%); }
  .portada-dibujo { align-self: center; justify-self: center; width: min(520px, 90%); transform: rotate(3deg); }
  .portada-dibujo img { border-radius: 20px; box-shadow: 0 40px 80px -30px rgba(5,12,50,.7); }
  .portada-texto { position: relative; z-index: 1; color: var(--blanco); padding: 48px; }
  .portada-fondo.con-foto .portada-texto { position: absolute; left: 0; bottom: 0; max-width: 820px; }
  .portada-texto p.sub { margin: 0 0 10px; font-size: 17px; font-weight: 600; }
  .titular {
    margin: 0 0 24px; text-transform: uppercase;
    font-stretch: 62%; font-weight: 900; line-height: .86; letter-spacing: -.01em;
    font-size: clamp(64px, 9.5vw, 148px);
  }
  .portada-botones { display: flex; gap: 12px; flex-wrap: wrap; }
  .portada .btn-borde { color: var(--blanco); }

  /* Categorias */
  .seccion { padding-top: 64px; }
  .seccion-titulo { font-size: 26px; font-weight: 700; margin: 0 0 20px; }
  .categorias { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
  .cat-tile { position: relative; border: 0; padding: 0; cursor: pointer; border-radius: 6px; overflow: hidden; background: var(--fondo-foto); aspect-ratio: 4 / 5; text-align: left; }
  .cat-tile img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s ease; }
  .cat-tile:hover img { transform: scale(1.04); }
  .cat-tile span { position: absolute; left: 24px; bottom: 24px; display: inline-flex; align-items: center; height: 44px; padding: 0 22px; border-radius: 999px; background: var(--blanco); font-weight: 600; box-shadow: 0 6px 18px -8px rgba(0,0,0,.35); }

  /* Barra del catalogo */
  .barra-catalogo {
    position: sticky; top: 64px; z-index: 30; background: var(--blanco);
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    padding: 18px 0 14px;
  }
  .barra-catalogo h2 { margin: 0; font-size: 26px; font-weight: 700; }
  .barra-catalogo h2 small { font-weight: 400; color: var(--gris); font-size: 20px; }
  .controles { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .chip { height: 38px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--linea); background: var(--blanco); font-size: 15px; font-weight: 500; cursor: pointer; }
  .chip:hover { border-color: var(--negro); }
  .chip[aria-pressed="true"] { background: var(--negro); color: var(--blanco); border-color: var(--negro); }
  .ordenar { display: flex; align-items: center; gap: 6px; font-size: 15px; margin-left: 8px; }
  .ordenar select { height: 38px; border: 0; background: transparent; font: inherit; font-weight: 600; cursor: pointer; }

  /* Rejilla de productos */
  .rejilla { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px 12px; padding-bottom: 80px; }
  .tarjeta { position: relative; cursor: pointer; }
  .tarjeta-foto { position: relative; aspect-ratio: 1; background: var(--fondo-foto); overflow: hidden; }
  .tarjeta-foto img { width: 100%; height: 100%; object-fit: cover; transition: transform .45s ease; }
  .tarjeta:hover .tarjeta-foto img { transform: scale(1.03); }
  .tarjeta.sin-stock .tarjeta-foto img { filter: grayscale(1); opacity: .55; }
  .insignia { position: absolute; top: 14px; left: 14px; background: var(--blanco); padding: 5px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; }
  .insignia.poco { color: var(--rojo); }
  .fav { position: absolute; top: 10px; right: 10px; background: var(--blanco); }
  .fav:hover { background: var(--blanco); transform: scale(1.06); }
  .fav[aria-pressed="true"] svg path { fill: var(--negro); }
  .vista-rapida {
    position: absolute; left: 50%; bottom: 16px; transform: translate(-50%, 12px); opacity: 0;
    transition: opacity .2s, transform .2s;
  }
  .tarjeta:hover .vista-rapida, .vista-rapida:focus-visible { opacity: 1; transform: translate(-50%, 0); }
  .tarjeta-info { padding-top: 12px; }
  .tarjeta-info .nuevo { color: var(--rojo); font-weight: 600; font-size: 15px; margin: 0; }
  .tarjeta-info h3 { margin: 0; font-size: 16px; font-weight: 600; }
  .tarjeta-info .cat { margin: 0; color: var(--gris); font-size: 15px; }
  .tarjeta-info .precio { margin: 8px 0 0; font-weight: 600; font-size: 16px; }
  .abrir { all: unset; cursor: pointer; }
  .abrir::after { content: ""; position: absolute; inset: 0; }
  .fav, .vista-rapida { z-index: 2; }
  .vacio { display: none; text-align: center; padding: 60px 0 100px; color: var(--gris); }
  .vacio strong { display: block; color: var(--negro); font-size: 22px; margin-bottom: 6px; }

  /* Modal de producto */
  dialog { border: 0; padding: 0; color: inherit; }
  dialog::backdrop { background: rgba(0,0,0,.45); }
  .modal { width: min(1040px, 100% - 32px); max-height: calc(100% - 32px); border-radius: 10px; overflow: auto; }
  .modal[open] { animation: entrar .25s ease-out; }
  @keyframes entrar { from { opacity: 0; transform: translateY(16px); } }
  .modal-cuerpo { display: grid; grid-template-columns: 1.1fr 1fr; }
  .modal-foto { background: var(--fondo-foto); }
  .modal-foto img { width: 100%; height: 100%; object-fit: cover; aspect-ratio: 1; }
  .modal-info { padding: 40px 36px; display: flex; flex-direction: column; gap: 18px; position: relative; }
  .modal-info .cerrar { position: absolute; top: 12px; right: 12px; }
  .modal-info h2 { margin: 0; font-size: 30px; font-weight: 700; line-height: 1.15; padding-right: 40px; }
  .modal-info .cat { margin: 0 0 -12px; color: var(--gris); }
  .modal-info .precio { font-size: 20px; font-weight: 600; margin: 0; }
  .modal-info .desc { margin: 0; color: #39393b; max-width: 46ch; }
  .etiqueta { font-weight: 600; margin: 0 0 8px; display: flex; justify-content: space-between; }
  .etiqueta span { font-weight: 600; color: var(--rojo); }
  .tallas { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
  .talla { height: 48px; border: 1px solid var(--linea); border-radius: 6px; background: var(--blanco); cursor: pointer; font-size: 16px; }
  .talla:hover { border-color: var(--negro); }
  .talla[aria-pressed="true"] { border-color: var(--negro); box-shadow: inset 0 0 0 1px var(--negro); }
  .paso { display: inline-flex; align-items: center; border: 1px solid var(--linea); border-radius: 999px; height: 44px; }
  .paso button { width: 44px; height: 42px; border: 0; background: none; cursor: pointer; font-size: 20px; }
  .paso button:disabled { color: #c5c5c5; cursor: not-allowed; }
  .paso output { min-width: 28px; text-align: center; font-weight: 600; }
  .modal-botones { display: grid; gap: 10px; margin-top: 6px; }
  .modal-botones .btn { height: 56px; width: 100%; }
  .stock-texto { font-size: 14px; color: var(--gris); margin: -6px 0 0; }

  /* Bolsa (cajon lateral) */
  .cajon { margin: 0 0 0 auto; height: 100%; max-height: 100%; width: min(440px, 100%); border-radius: 0; }
  .cajon[open] { display: flex; flex-direction: column; animation: deslizar .28s ease-out; }
  @keyframes deslizar { from { transform: translateX(100%); } }
  .cajon-cabeza { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px 18px 24px; border-bottom: 1px solid var(--linea); }
  .cajon-cabeza h2 { margin: 0; font-size: 22px; font-weight: 700; }
  .cajon-lista { flex: 1; overflow: auto; padding: 8px 24px; }
  .item { display: grid; grid-template-columns: 96px 1fr; gap: 16px; padding: 18px 0; border-bottom: 1px solid var(--linea); }
  .item img { width: 96px; height: 96px; object-fit: cover; background: var(--fondo-foto); }
  .item h3 { margin: 0; font-size: 16px; font-weight: 600; }
  .item p { margin: 0; color: var(--gris); font-size: 14px; }
  .item-fila { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
  .item .paso { height: 36px; }
  .item .paso button { width: 36px; height: 34px; font-size: 18px; }
  .quitar { background: none; border: 0; color: var(--gris); text-decoration: underline; cursor: pointer; font-size: 14px; padding: 0; }
  .cajon-pie { padding: 20px 24px 24px; border-top: 1px solid var(--linea); display: grid; gap: 14px; }
  .total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; }
  .cajon-pie small { color: var(--gris); }
  .cajon-pie .btn { height: 56px; width: 100%; }
  .bolsa-vacia { text-align: center; padding: 60px 10px; color: var(--gris); }
  .bolsa-vacia strong { display: block; color: var(--negro); font-size: 18px; margin-bottom: 6px; }

  /* Aviso */
  .aviso {
    position: fixed; left: 50%; bottom: 24px; z-index: 60; transform: translateX(-50%);
    display: flex; align-items: center; gap: 14px; width: max-content; max-width: calc(100% - 32px);
    background: var(--negro); color: var(--blanco); padding: 14px 16px 14px 20px; border-radius: 10px;
    box-shadow: 0 16px 40px -12px rgba(0,0,0,.45); animation: subir .3s ease-out;
  }
  .aviso.error { background: var(--rojo); }
  .aviso button { background: none; border: 0; color: inherit; font-size: 22px; cursor: pointer; line-height: 1; }
  @keyframes subir { from { opacity: 0; transform: translate(-50%, 14px); } }

  /* Pie */
  footer { background: var(--negro); color: #b5b5b7; font-size: 14px; }
  footer .ancho { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 32px; padding: 48px 0; }
  footer h4 { color: var(--blanco); margin: 0 0 12px; font-size: 15px; }
  footer p { margin: 0 0 6px; }
  footer .final { border-top: 1px solid #2c2c2e; padding: 18px 0; grid-template-columns: 1fr; }

  @media (max-width: 1024px) {
    .menu { display: none; }
    .encabezado .ancho { grid-template-columns: 1fr auto; }
    .rejilla { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 720px) {
    .ancho, .portada { width: calc(100% - 24px); }
    .buscar input { width: 44px; padding: 0 0 0 40px; background: transparent; }
    .buscar input:focus { width: 170px; padding-right: 12px; background: var(--fondo-foto); }
    .logo b { display: none; }
    .portada-fondo { grid-template-columns: 1fr; min-height: 0; }
    .portada-dibujo { order: -1; width: 70%; margin-top: 28px; }
    .portada-texto { padding: 28px 22px 30px; }
    .categorias { grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
    .cat-tile { aspect-ratio: 3 / 4; }
    .cat-tile span { left: 8px; bottom: 8px; height: 34px; padding: 0 12px; font-size: 13px; }
    .barra-catalogo { position: static; }
    .controles { flex-wrap: nowrap; overflow-x: auto; width: 100%; padding-bottom: 4px; scrollbar-width: none; }
    .chip { flex: none; }
    .ordenar { flex: none; }
    .barra-catalogo h2 { font-size: 22px; }
    .ordenar { margin-left: 0; }
    .rejilla { gap: 28px 8px; }
    .vista-rapida { display: none; }
    .modal { width: 100%; max-height: 100%; height: 100%; max-width: 100%; border-radius: 0; margin: 0; }
    .modal-cuerpo { grid-template-columns: 1fr; }
    .modal-info { padding: 24px 20px 32px; }
    .modal-info .cerrar { position: fixed; top: 12px; right: 12px; background: var(--blanco); z-index: 2; }
    footer .ancho { grid-template-columns: 1fr; }
  }
  @media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
  }
</style>
</head>
<body>

<div class="promo">Nueva colección campus 2026. <a href="#catalogo">Ver productos</a></div>

<header class="encabezado">
  <div class="ancho">
    <a class="logo" href="./" aria-label="Inicio misitio.edu">
      <span class="logo-marca" aria-hidden="true">M</span><b>misitio.edu</b>
    </a>
    <nav class="menu" aria-label="Categorías">
      <button type="button" data-ir="todo">Novedades</button>
      <?php foreach ($categorias as $c): ?>
      <button type="button" data-ir="<?= h($c) ?>"><?= h($c) ?></button>
      <?php endforeach; ?>
    </nav>
    <div class="acciones">
      <label class="buscar">
        <span class="oculto">Buscar productos</span>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" id="buscar" placeholder="Buscar" autocomplete="off">
      </label>
      <button class="icono" type="button" id="ver-favoritos" aria-label="Ver favoritos">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 20s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 10c0 5.65-7 10-7 10z"/></svg>
        <span class="burbuja" id="num-favoritos"></span>
      </button>
      <button class="icono" type="button" id="abrir-bolsa" aria-label="Abrir bolsa de compra">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
        <span class="burbuja" id="num-bolsa"></span>
      </button>
    </div>
  </div>
</header>

<section class="portada" aria-label="Portada">
  <div class="portada-fondo<?= $portada_foto ? ' con-foto' : '' ?>">
    <?php if ($portada_foto): ?>
      <img class="fondo" src="<?= h($portada) ?>" alt="">
    <?php endif; ?>
    <div class="portada-texto">
      <p class="sub">Colección campus 2026</p>
      <h1 class="titular">Lleva el campus contigo</h1>
      <div class="portada-botones">
        <a class="btn btn-blanco" href="#catalogo">Comprar</a>
        <button class="btn btn-borde" type="button" data-ir="Ropa">Ver ropa</button>
      </div>
    </div>
    <?php if (!$portada_foto && $destacado): ?>
    <div class="portada-dibujo"><img src="<?= h($destacado['imagen']) ?>" alt="<?= h($destacado['nombre']) ?>" width="520" height="520"></div>
    <?php endif; ?>
  </div>
</section>

<section class="seccion ancho" aria-labelledby="t-categorias">
  <h2 class="seccion-titulo" id="t-categorias">Compra por categoría</h2>
  <div class="categorias">
    <?php foreach ($categorias as $c): ?>
    <button class="cat-tile" type="button" data-ir="<?= h($c) ?>">
      <img src="<?= h($foto_categoria[$c] ?? 'img/sin-imagen.svg') ?>" alt="" loading="lazy">
      <span><?= h($c) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</section>

<main class="ancho seccion" id="catalogo">
  <div class="barra-catalogo">
    <h2><span id="titulo-lista">Todos los productos</span> <small id="conteo">(<?= count($productos) ?>)</small></h2>
    <div class="controles" role="group" aria-label="Filtros">
      <button class="chip" type="button" data-cat="todo" aria-pressed="true">Todo</button>
      <?php foreach ($categorias as $c): ?>
      <button class="chip" type="button" data-cat="<?= h($c) ?>" aria-pressed="false"><?= h($c) ?></button>
      <?php endforeach; ?>
      <button class="chip" type="button" data-cat="favoritos" aria-pressed="false">Favoritos</button>
      <label class="ordenar">Ordenar por
        <select id="orden">
          <option value="destacados">Destacados</option>
          <option value="menor">Precio: menor a mayor</option>
          <option value="mayor">Precio: mayor a menor</option>
        </select>
      </label>
    </div>
  </div>

  <div class="rejilla" id="rejilla">
    <?php foreach ($productos as $i => $p): $s = $p['stock']; ?>
    <article class="tarjeta<?= $s <= 0 ? ' sin-stock' : '' ?>" data-id="<?= $p['id'] ?>" data-cat="<?= h($p['categoria']) ?>"
             data-texto="<?= h(mb_strtolower($p['nombre'] . ' ' . $p['descripcion'] . ' ' . $p['categoria'], 'UTF-8')) ?>"
             data-precio="<?= $p['precio'] ?>" data-orden="<?= $i ?>">
      <div class="tarjeta-foto">
        <img src="<?= h($p['imagen']) ?>" alt="<?= h($p['nombre']) ?>" width="600" height="600" loading="lazy">
        <?php if ($s <= 0): ?><span class="insignia">Agotado</span>
        <?php elseif ($s <= 5): ?><span class="insignia poco">Quedan <?= $s ?></span><?php endif; ?>
        <button class="icono fav" type="button" aria-pressed="false" aria-label="Agregar <?= h($p['nombre']) ?> a favoritos">
          <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 10c0 5.65-7 10-7 10z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
        </button>
        <?php if ($s > 0): ?><span class="btn btn-blanco vista-rapida" aria-hidden="true">Vista rápida</span><?php endif; ?>
      </div>
      <div class="tarjeta-info">
        <h3><button class="abrir" type="button"><?= h($p['nombre']) ?></button></h3>
        <p class="cat"><?= h($p['categoria']) ?><?= count($p['tallas']) > 1 ? ' · Tallas CH a XG' : '' ?></p>
        <p class="precio"><?= precio($p['precio']) ?></p>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <div class="vacio" id="vacio">
    <strong id="vacio-titulo">No encontramos productos</strong>
    <span id="vacio-texto">Prueba con otra palabra o elige “Todo”.</span>
  </div>
</main>

<footer>
  <div class="ancho">
    <div>
      <h4>misitio.edu · Tienda del campus</h4>
      <p>Ropa, accesorios y papelería con el logo de tu escuela.</p>
    </div>
    <div>
      <h4>Tienda</h4>
      <?php foreach ($categorias as $c): ?><p><?= h($c) ?></p><?php endforeach; ?>
    </div>
    <div>
      <h4>Sobre el sitio</h4>
      <p>Hecho con PHP y PostgreSQL</p>
      <p>Desplegado en Render y Neon</p>
    </div>
  </div>
  <div class="ancho final"><p>© <?= date('Y') ?> misitio.edu</p></div>
</footer>

<!-- Detalle de producto -->
<dialog class="modal" id="detalle" aria-labelledby="d-nombre">
  <div class="modal-cuerpo">
    <div class="modal-foto"><img id="d-img" src="" alt=""></div>
    <div class="modal-info">
      <button class="icono cerrar" type="button" data-cerrar aria-label="Cerrar">
        <svg width="22" height="22" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
      <p class="cat" id="d-cat"></p>
      <h2 id="d-nombre"></h2>
      <p class="precio" id="d-precio"></p>
      <p class="desc" id="d-desc"></p>
      <div id="d-bloque-tallas">
        <p class="etiqueta">Elige tu talla <span id="d-talla-aviso"></span></p>
        <div class="tallas" id="d-tallas"></div>
      </div>
      <div>
        <p class="etiqueta">Cantidad</p>
        <div class="paso">
          <button type="button" id="d-menos" aria-label="Quitar una">−</button>
          <output id="d-cant">1</output>
          <button type="button" id="d-mas" aria-label="Agregar una">+</button>
        </div>
      </div>
      <p class="stock-texto" id="d-stock"></p>
      <div class="modal-botones">
        <button class="btn btn-negro" type="button" id="d-agregar">Agregar a la bolsa</button>
        <button class="btn btn-borde" type="button" id="d-fav" aria-pressed="false">Favorito ♡</button>
      </div>
    </div>
  </div>
</dialog>

<!-- Bolsa -->
<dialog class="cajon" id="bolsa" aria-labelledby="b-titulo">
  <div class="cajon-cabeza">
    <h2 id="b-titulo">Bolsa</h2>
    <button class="icono" type="button" data-cerrar aria-label="Cerrar bolsa">
      <svg width="22" height="22" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
  </div>
  <div class="cajon-lista" id="b-lista"></div>
  <form class="cajon-pie" id="b-pie" method="POST" action="comprar.php">
    <input type="hidden" name="carrito" id="b-carrito">
    <div class="total"><span>Total</span><span id="b-total">$0.00</span></div>
    <small>Recoges tu pedido en la tienda del campus.</small>
    <button class="btn btn-negro" type="submit" id="b-pagar">Finalizar compra</button>
  </form>
</dialog>

<?php if ($aviso): ?>
<div class="aviso <?= $aviso_tipo === 'error' ? 'error' : '' ?>" role="status" id="aviso-servidor">
  <span><?= h($aviso) ?></span>
  <button type="button" aria-label="Cerrar aviso" onclick="this.parentElement.remove()">×</button>
</div>
<?php endif; ?>

<script>
const PRODUCTOS = <?= json_encode($productos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const porId = Object.fromEntries(PRODUCTOS.map(p => [p.id, p]));
const dinero = n => '$' + Number(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const $ = id => document.getElementById(id);

// ---------- Guardado local ----------
function leer(clave, def) { try { return JSON.parse(localStorage.getItem(clave)) ?? def; } catch { return def; } }
function guardar(clave, valor) { try { localStorage.setItem(clave, JSON.stringify(valor)); } catch {} }
let bolsa = leer('mv_bolsa', []).filter(i => porId[i.id]);
let favoritos = new Set(leer('mv_favs', []).filter(id => porId[id]));

// Si la compra salio bien, se vacia la bolsa
const params = new URLSearchParams(location.search);
if (params.get('compra') === 'ok') { bolsa = []; guardar('mv_bolsa', bolsa); }
if (params.has('compra') || params.has('error')) history.replaceState(null, '', location.pathname);
const avisoServidor = $('aviso-servidor');
if (avisoServidor) setTimeout(() => avisoServidor.remove(), 7000);

function avisar(texto, tipo = 'ok') {
  document.querySelectorAll('.aviso').forEach(a => a.remove());
  const a = document.createElement('div');
  a.className = 'aviso' + (tipo === 'error' ? ' error' : '');
  a.setAttribute('role', 'status');
  a.innerHTML = '<span></span><button type="button" aria-label="Cerrar aviso">×</button>';
  a.querySelector('span').textContent = texto;
  a.querySelector('button').onclick = () => a.remove();
  document.body.appendChild(a);
  setTimeout(() => a.remove(), 4000);
}

// ---------- Filtros, busqueda y orden ----------
const rejilla = $('rejilla');
const tarjetas = [...rejilla.querySelectorAll('.tarjeta')];
const chips = document.querySelectorAll('.chip');
let categoria = 'todo';

function actualizarLista() {
  const q = $('buscar').value.trim().toLowerCase();
  let visibles = 0;
  tarjetas.forEach(t => {
    const id = +t.dataset.id;
    const enCat = categoria === 'todo' || (categoria === 'favoritos' ? favoritos.has(id) : t.dataset.cat === categoria);
    const ok = enCat && (!q || t.dataset.texto.includes(q));
    t.hidden = !ok;
    if (ok) visibles++;
  });
  const orden = $('orden').value;
  [...tarjetas].sort((a, b) =>
    orden === 'menor' ? a.dataset.precio - b.dataset.precio :
    orden === 'mayor' ? b.dataset.precio - a.dataset.precio :
    a.dataset.orden - b.dataset.orden
  ).forEach(t => rejilla.appendChild(t));

  $('titulo-lista').textContent = categoria === 'todo' ? 'Todos los productos' : categoria === 'favoritos' ? 'Tus favoritos' : categoria;
  $('conteo').textContent = '(' + visibles + ')';
  $('vacio').style.display = visibles ? 'none' : 'block';
  if (categoria === 'favoritos' && !favoritos.size) {
    $('vacio-titulo').textContent = 'Aún no tienes favoritos';
    $('vacio-texto').textContent = 'Toca el corazón de un producto para guardarlo aquí.';
  } else {
    $('vacio-titulo').textContent = 'No encontramos productos';
    $('vacio-texto').textContent = 'Prueba con otra palabra o elige “Todo”.';
  }
  document.querySelectorAll('.menu button').forEach(b => b.classList.toggle('activo', b.dataset.ir === categoria && categoria !== 'todo'));
}
function elegirCategoria(c, desplazar = true) {
  categoria = c;
  chips.forEach(x => x.setAttribute('aria-pressed', String(x.dataset.cat === c)));
  actualizarLista();
  if (desplazar) $('catalogo').scrollIntoView();
}
chips.forEach(c => c.addEventListener('click', () => elegirCategoria(c.dataset.cat, false)));
document.querySelectorAll('[data-ir]').forEach(b => b.addEventListener('click', () => elegirCategoria(b.dataset.ir)));
$('ver-favoritos').addEventListener('click', () => elegirCategoria('favoritos'));
$('orden').addEventListener('change', actualizarLista);
$('buscar').addEventListener('input', () => { actualizarLista(); });
$('buscar').addEventListener('keydown', e => { if (e.key === 'Enter') $('catalogo').scrollIntoView(); });

// ---------- Favoritos ----------
function pintarFavoritos() {
  tarjetas.forEach(t => {
    const b = t.querySelector('.fav');
    b.setAttribute('aria-pressed', String(favoritos.has(+t.dataset.id)));
  });
  $('num-favoritos').textContent = favoritos.size || '';
}
function alternarFavorito(id) {
  favoritos.has(id) ? favoritos.delete(id) : favoritos.add(id);
  guardar('mv_favs', [...favoritos]);
  pintarFavoritos();
  if (categoria === 'favoritos') actualizarLista();
  return favoritos.has(id);
}

// ---------- Detalle de producto ----------
const detalle = $('detalle');
let actual = null, talla = null, cantidad = 1;
const enBolsa = id => bolsa.filter(i => i.id === id).reduce((s, i) => s + i.cantidad, 0);

function abrirDetalle(id) {
  const p = porId[id]; actual = p; cantidad = 1;
  talla = p.tallas.length === 1 ? p.tallas[0] : null;
  $('d-img').src = p.imagen; $('d-img').alt = p.nombre;
  $('d-cat').textContent = p.categoria;
  $('d-nombre').textContent = p.nombre;
  $('d-precio').textContent = dinero(p.precio);
  $('d-desc').textContent = p.descripcion;
  $('d-bloque-tallas').hidden = p.tallas.length === 1;
  $('d-talla-aviso').textContent = '';
  $('d-tallas').innerHTML = '';
  p.tallas.forEach(t => {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'talla'; b.textContent = t;
    b.setAttribute('aria-pressed', 'false');
    b.onclick = () => {
      talla = t; $('d-talla-aviso').textContent = '';
      $('d-tallas').querySelectorAll('.talla').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    };
    $('d-tallas').appendChild(b);
  });
  $('d-fav').setAttribute('aria-pressed', String(favoritos.has(id)));
  $('d-fav').textContent = favoritos.has(id) ? 'En favoritos ♥' : 'Favorito ♡';
  pintarDetalle();
  detalle.showModal();
}
function pintarDetalle() {
  const libre = actual.stock - enBolsa(actual.id);
  if (cantidad > libre) cantidad = Math.max(1, libre);
  $('d-cant').textContent = cantidad;
  $('d-menos').disabled = cantidad <= 1;
  $('d-mas').disabled = cantidad >= libre;
  const agregar = $('d-agregar');
  if (actual.stock <= 0) { agregar.disabled = true; agregar.textContent = 'Agotado'; $('d-stock').textContent = 'Sin piezas por ahora.'; }
  else if (libre <= 0) { agregar.disabled = true; agregar.textContent = 'Ya tienes todas en tu bolsa'; $('d-stock').textContent = ''; }
  else {
    agregar.disabled = false; agregar.textContent = 'Agregar a la bolsa';
    $('d-stock').textContent = actual.stock <= 5 ? `Solo quedan ${actual.stock} piezas.` : `${actual.stock} piezas disponibles.`;
  }
}
$('d-menos').onclick = () => { cantidad--; pintarDetalle(); };
$('d-mas').onclick = () => { cantidad++; pintarDetalle(); };
$('d-fav').onclick = () => {
  const on = alternarFavorito(actual.id);
  $('d-fav').setAttribute('aria-pressed', String(on));
  $('d-fav').textContent = on ? 'En favoritos ♥' : 'Favorito ♡';
};
$('d-agregar').onclick = () => {
  if (!talla) { $('d-talla-aviso').textContent = 'Selecciona una talla'; $('d-tallas').querySelector('.talla').focus(); return; }
  const existente = bolsa.find(i => i.id === actual.id && i.talla === talla);
  existente ? existente.cantidad += cantidad : bolsa.push({ id: actual.id, talla, cantidad });
  guardar('mv_bolsa', bolsa);
  pintarBolsa();
  detalle.close();
  $('bolsa').showModal();
};

// Clic en tarjeta abre el detalle; el corazon solo marca favorito
tarjetas.forEach(t => {
  const id = +t.dataset.id;
  t.addEventListener('click', e => {
    if (e.target.closest('.fav')) return;
    abrirDetalle(id);
  });
  t.querySelector('.fav').addEventListener('click', () => {
    const on = alternarFavorito(id);
    avisar(on ? `${porId[id].nombre} se agregó a favoritos.` : `${porId[id].nombre} se quitó de favoritos.`);
  });
});

// ---------- Bolsa ----------
function pintarBolsa() {
  const lista = $('b-lista');
  const piezas = bolsa.reduce((s, i) => s + i.cantidad, 0);
  $('num-bolsa').textContent = piezas || '';
  $('b-titulo').textContent = piezas ? `Bolsa (${piezas})` : 'Bolsa';
  lista.innerHTML = '';
  if (!bolsa.length) {
    lista.innerHTML = '<div class="bolsa-vacia"><strong>Tu bolsa está vacía</strong>Agrega productos del catálogo para comprarlos.</div>';
    $('b-pie').hidden = true;
    return;
  }
  $('b-pie').hidden = false;
  let total = 0;
  bolsa.forEach((i, idx) => {
    const p = porId[i.id];
    total += p.precio * i.cantidad;
    const libre = p.stock - enBolsa(p.id);
    const fila = document.createElement('div');
    fila.className = 'item';
    fila.innerHTML = `
      <img src="" alt="">
      <div>
        <h3></h3>
        <p class="talla-txt"></p>
        <div class="item-fila">
          <div class="paso">
            <button type="button" class="menos" aria-label="Quitar una">−</button>
            <output></output>
            <button type="button" class="mas" aria-label="Agregar una">+</button>
          </div>
          <strong class="sub"></strong>
        </div>
        <p style="margin-top:8px"><button type="button" class="quitar">Eliminar</button></p>
      </div>`;
    fila.querySelector('img').src = p.imagen;
    fila.querySelector('img').alt = p.nombre;
    fila.querySelector('h3').textContent = p.nombre;
    fila.querySelector('.talla-txt').textContent = (i.talla === 'Única' ? 'Talla única' : 'Talla ' + i.talla) + ' · ' + dinero(p.precio);
    fila.querySelector('output').textContent = i.cantidad;
    fila.querySelector('.sub').textContent = dinero(p.precio * i.cantidad);
    fila.querySelector('.menos').disabled = i.cantidad <= 1;
    fila.querySelector('.mas').disabled = libre <= 0;
    fila.querySelector('.menos').onclick = () => { i.cantidad--; guardar('mv_bolsa', bolsa); pintarBolsa(); };
    fila.querySelector('.mas').onclick = () => { i.cantidad++; guardar('mv_bolsa', bolsa); pintarBolsa(); };
    fila.querySelector('.quitar').onclick = () => { bolsa.splice(idx, 1); guardar('mv_bolsa', bolsa); pintarBolsa(); };
    lista.appendChild(fila);
  });
  $('b-total').textContent = dinero(total);
  $('b-carrito').value = JSON.stringify(bolsa);
}
$('abrir-bolsa').onclick = () => { pintarBolsa(); $('bolsa').showModal(); };
$('b-pie').addEventListener('submit', () => {
  $('b-pagar').disabled = true;
  $('b-pagar').textContent = 'Registrando compra…';
});

// Cerrar dialogos con la X o tocando fuera
document.querySelectorAll('dialog').forEach(d => {
  d.addEventListener('click', e => { if (e.target === d) d.close(); });
  d.querySelectorAll('[data-cerrar]').forEach(b => b.onclick = () => d.close());
});

pintarFavoritos();
pintarBolsa();
actualizarLista();
</script>
</body>
</html>
