<?php
// guardar_pedido_carrito.php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../conexion.php';

// Recibir datos JSON
$data = json_decode(file_get_contents('php://input'), true);

$nombre = $data['nombre'] ?? '';
$ciudad = $data['ciudad'] ?? '';
$session_id = $data['session_id'] ?? '';

// Validar datos obligatorios
if (empty($nombre) || empty($session_id)) {
    echo json_encode(['error' => 'Faltan datos obligatorios']);
    exit;
}

// 1. Obtener productos del carrito
$stmt = $conn->prepare("
    SELECT c.*, p.nombre, p.precio, t.talla, col.nombre as color_nombre
    FROM carrito c
    JOIN productos p ON c.producto_id = p.id
    LEFT JOIN tallas t ON c.talla_id = t.id
    LEFT JOIN colores col ON c.color_id = col.id
    WHERE c.session_id = ?
");
$stmt->bind_param("s", $session_id);
$stmt->execute();
$carrito = $stmt->get_result();

if ($carrito->num_rows == 0) {
    echo json_encode(['error' => 'El carrito está vacío']);
    exit;
}

// 2. Crear cliente (sin teléfono por ahora)
$direccion = $ciudad;
$stmt = $conn->prepare("INSERT INTO clientes (nombre, direccion) VALUES (?, ?)");
$stmt->bind_param("ss", $nombre, $direccion);
$stmt->execute();
$cliente_id = $conn->insert_id;

// 3. Calcular total y preparar productos
$total = 0;
$productos_lista = [];
$detalle_pedido = [];

while ($item = $carrito->fetch_assoc()) {
    $subtotal = $item['precio'] * $item['cantidad'];
    $total += $subtotal;
    
    $productos_lista[] = [
        'nombre' => $item['nombre'],
        'talla' => $item['talla'] ?? '',
        'color' => $item['color_nombre'] ?? '',
        'cantidad' => $item['cantidad'],
        'precio' => $item['precio'],
        'subtotal' => $subtotal
    ];
    
    $detalle_pedido[] = [
        'producto_id' => $item['producto_id'],
        'cantidad' => $item['cantidad'],
        'precio' => $item['precio'],
        'talla_nombre' => $item['talla'] ?? '',
        'color_nombre' => $item['color_nombre'] ?? ''
    ];
    
    // Guardar para usar después
    $item_ids[] = $item['id'];
}

// 4. Crear pedido
$estado = 'pendiente';
$fecha = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO pedidos (cliente_id, total, estado, fecha_pedido) VALUES (?, ?, ?, ?)");
$stmt->bind_param("idss", $cliente_id, $total, $estado, $fecha);
$stmt->execute();
$pedido_id = $conn->insert_id;

// 5. Guardar detalles del pedido
foreach ($detalle_pedido as $detalle) {
    $stmt = $conn->prepare("INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiid", $pedido_id, $detalle['producto_id'], $detalle['cantidad'], $detalle['precio']);
    $stmt->execute();
    
    // Guardar variantes (talla y color) si existen
    if (!empty($detalle['talla_nombre']) || !empty($detalle['color_nombre'])) {
        $stmt2 = $conn->prepare("INSERT INTO pedido_variantes (pedido_id, talla, color) VALUES (?, ?, ?)");
        $stmt2->bind_param("iss", $pedido_id, $detalle['talla_nombre'], $detalle['color_nombre']);
        $stmt2->execute();
    }
}

// 6. Vaciar carrito
if (!empty($item_ids)) {
    $ids_string = implode(',', $item_ids);
    $conn->query("DELETE FROM carrito WHERE id IN ($ids_string)");
}

// 7. Obtener configuración (número WhatsApp)
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave = 'tienda_whatsapp'");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// 8. Devolver éxito con datos
echo json_encode([
    'success' => true,
    'pedido_id' => $pedido_id,
    'cliente_nombre' => $nombre,
    'cliente_ciudad' => $ciudad,
    'productos' => $productos_lista,
    'total' => $total,
    'tienda_whatsapp' => $config['tienda_whatsapp'] ?? ''
]);
?>