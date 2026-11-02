<?php
// orden.php: recibe el formulario de proyecto.html, valida, calcula el total
// en el servidor y redirige a pago.html (el pago es simulado).

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: proyecto.html#pedido');
    exit;
}

// Precios definidos en el servidor (no se confía en el navegador)
$productos = [
    'gordita'   => ['nombre' => 'Gorditas',    'precio' => 20],
    'tortillon' => ['nombre' => 'Tortillones', 'precio' => 60],
    'burrito'   => ['nombre' => 'Burritos',    'precio' => 22],
];

$nombre      = trim(strip_tags($_POST['nombre'] ?? ''));
$telefono    = trim(strip_tags($_POST['telefono'] ?? ''));
$tipo        = ($_POST['tipo'] ?? 'aqui') === 'llevar' ? 'Para llevar' : 'Comer aquí';
$comentarios = trim(strip_tags($_POST['comentarios'] ?? ''));

if ($nombre === '' || $telefono === '') {
    header('Location: proyecto.html#pedido');
    exit;
}

$total   = 0;
$detalle = [];

foreach ($productos as $clave => $info) {
    $cant = (int) ($_POST['cantidad_' . $clave] ?? 0);
    $cant = max(0, min($cant, 50));
    if ($cant > 0) {
        $guiso     = trim(strip_tags($_POST['guiso_' . $clave] ?? ''));
        $subtotal  = $cant * $info['precio'];
        $total    += $subtotal;
        $detalle[] = $cant . ' x ' . $info['nombre'] . ($guiso !== '' ? ' (' . $guiso . ')' : '') . ' - $' . $subtotal;
    }
}

// Sin piezas no hay pedido
if ($total === 0) {
    header('Location: proyecto.html#pedido');
    exit;
}

$folio = 'GF-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

// Guardar el pedido en un archivo de texto (opcional, útil para el proyecto).
// Si la carpeta no permite escribir, simplemente se omite.
$linea = date('Y-m-d H:i:s') . " | $folio | $nombre | $telefono | $tipo | "
       . implode('; ', $detalle) . " | Total: \$$total | $comentarios" . PHP_EOL;
@file_put_contents(__DIR__ . '/pedidos.txt', $linea, FILE_APPEND | LOCK_EX);

// Enviar a pago.html con los datos del pedido
$query = http_build_query([
    'folio'   => $folio,
    'nombre'  => $nombre,
    'tipo'    => $tipo,
    'detalle' => implode('|', $detalle),
    'total'   => $total,
]);

header('Location: pago.html?' . $query);
exit;