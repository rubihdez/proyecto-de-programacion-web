<?php
/* 
   Se llama cuando el pago se aprueba (el simulado,
   después el botón de PayPal). 
   Hace 3 cosas en una sola operación:
     - marca el pedido como "pagado"
     - DESCUENTA el stock de cada producto
     - SUMA las piezas al conteo de "vendidos"
    */

session_start();
require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

function responder($ok, $mensaje = '', $codigo = 200) {
    http_response_code($codigo);
    echo json_encode(['ok' => $ok, 'error' => $mensaje]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método no permitido.', 405);
}

$folio     = trim($_POST['folio'] ?? '');
$paypal_id = mb_substr(trim($_POST['paypal_id'] ?? 'SIMULADO'), 0, 60);

// Solo se puede pagar el pedido que se hizo en ESTA sesión
if (!isset($_SESSION['pedido_actual']) || $_SESSION['pedido_actual']['folio'] !== $folio) {
    responder(false, 'No encontramos tu pedido. Vuelve a hacerlo desde el formulario.', 403);
}

try {
    $conexion->begin_transaction();

    // Buscar el pedido (y bloquearlo mientras se procesa)
    $stmt = $conexion->prepare("SELECT id, estado FROM pedidos WHERE folio = ? FOR UPDATE");
    $stmt->bind_param('s', $folio);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedido) {
        throw new Exception('Pedido inexistente.');
    }

    // Si ya estaba pagado, no se vuelve a descontar nada
    if ($pedido['estado'] === 'pagado') {
        $conexion->commit();
        responder(true);
    }
    if ($pedido['estado'] !== 'pendiente') {
        throw new Exception('Este pedido ya no se puede pagar.');
    }

    // Marcar como pagado
    $stmt = $conexion->prepare("UPDATE pedidos SET estado = 'pagado', paypal_id = ? WHERE id = ?");
    $stmt->bind_param('si', $paypal_id, $pedido['id']);
    $stmt->execute();
    $stmt->close();

    // Descontar stock y sumar vendidos, producto por producto
    $stmt = $conexion->prepare("SELECT producto_id, cantidad FROM detalle_pedido WHERE pedido_id = ?");
    $stmt->bind_param('i', $pedido['id']);
    $stmt->execute();
    $lineas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmtStock = $conexion->prepare(
        "UPDATE productos SET stock = stock - ?, vendidos = vendidos + ? WHERE id = ? AND stock >= ?"
    );
    foreach ($lineas as $l) {
        $cant = (int) $l['cantidad'];
        $pid  = (int) $l['producto_id'];
        $stmtStock->bind_param('iiii', $cant, $cant, $pid, $cant);
        $stmtStock->execute();
        if ($stmtStock->affected_rows !== 1) {
            throw new Exception('Se agotó uno de los productos.');
        }
    }
    $stmtStock->close();

    $conexion->commit();
    responder(true);

} catch (Throwable $e) {
    $conexion->rollback();
    responder(false, 'No se pudo confirmar el pago: ' . $e->getMessage(), 409);
}
