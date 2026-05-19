<?php
// guardar_pedido.php
session_start();
header('Content-Type: application/json');
include 'conexion.php';

// Recibir datos del formulario
$nombre = $_POST['nombre'] ?? '';
$telefono = $_POST['telefono'] ?? ''; // Ahora es opcional
$ciudad = $_POST['ciudad'] ?? '';
$producto_id = $_POST['producto_id'] ?? 0;
$talla_id = $_POST['talla_id'] ?? 0;
$color_id = $_POST['color_id'] ?? 0;
$talla_nombre = $_POST['talla_nombre'] ?? '';
$color_nombre = $_POST['color_nombre'] ?? '';
$cantidad = $_POST['cantidad'] ?? 1;

// ===== NUEVO: RECIBIR PRECIO CON DESCUENTO DESDE EL MODAL =====
$precio = $_POST['precio'] ?? 0;  // ← Este viene del modal con descuento

// Si no viene precio, calcularlo desde la base de datos (fallback)
if ($precio == 0) {
    $stmt = $conn->prepare("SELECT precio FROM productos WHERE id = ?");
    $stmt->bind_param("i", $producto_id);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $precio = $producto['precio'];
}

// Obtener nombre del producto
$stmt = $conn->prepare("SELECT nombre FROM productos WHERE id = ?");
$stmt->bind_param("i", $producto_id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$producto_nombre = $producto['nombre'];

// ✅ VALIDACIÓN ACTUALIZADA: Ya no requiere teléfono
if (empty($nombre) || empty($producto_id)) {
    echo json_encode(['error' => 'Faltan datos obligatorios (nombre y producto)']);
    exit;
}

// 2️⃣ VERIFICAR O CREAR CLIENTE (AHORA SIN TELÉFONO OBLIGATORIO)
$cliente_id = null;

if (!empty($telefono)) {
    // Si hay teléfono, buscar por teléfono
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE telefono = ?");
    $stmt->bind_param("s", $telefono);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $cliente = $result->fetch_assoc();
        $cliente_id = $cliente['id'];
        
        // Actualizar nombre y dirección
        $stmt = $conn->prepare("UPDATE clientes SET nombre = ?, direccion = ? WHERE id = ?");
        $direccion_completa = $ciudad;
        $stmt->bind_param("ssi", $nombre, $direccion_completa, $cliente_id);
        $stmt->execute();
    }
}

if (!$cliente_id) {
    // Crear nuevo cliente (con o sin teléfono)
    $direccion_completa = $ciudad;
    
    if (!empty($telefono)) {
        $stmt = $conn->prepare("INSERT INTO clientes (nombre, telefono, direccion) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $telefono, $direccion_completa);
    } else {
        // Insertar sin teléfono
        $stmt = $conn->prepare("INSERT INTO clientes (nombre, direccion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $direccion_completa);
    }
    $stmt->execute();
    $cliente_id = $conn->insert_id;
}

// 3. Crear pedido (con el precio con descuento)
$total = $precio * $cantidad;
$estado = 'pendiente';
$fecha = date('Y-m-d H:i:s');

$stmt = $conn->prepare("INSERT INTO pedidos (cliente_id, total, estado, fecha_pedido) VALUES (?, ?, ?, ?)");
$stmt->bind_param("idss", $cliente_id, $total, $estado, $fecha);
$stmt->execute();
$pedido_id = $conn->insert_id;

// 4. Guardar detalle del pedido (con el precio con descuento)
$stmt = $conn->prepare("INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiid", $pedido_id, $producto_id, $cantidad, $precio);
$stmt->execute();

// 5. Guardar variantes (talla y color)
if ($talla_id > 0 || $color_id > 0) {
    $stmt = $conn->prepare("INSERT INTO pedido_variantes (pedido_id, talla, color) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $pedido_id, $talla_nombre, $color_nombre);
    $stmt->execute();
}

// 6. Obtener configuración (número WhatsApp)
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave = 'tienda_whatsapp'");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// 7. Devolver éxito con datos para WhatsApp
echo json_encode([
    'success' => true,
    'pedido_id' => $pedido_id,
    'cliente_nombre' => $nombre,
    'cliente_telefono' => $telefono ?: 'No proporcionado',
    'cliente_ciudad' => $ciudad,
    'producto_nombre' => $producto_nombre,
    'talla' => $talla_nombre,
    'color' => $color_nombre,
    'precio' => $precio,  // ← AHORA ES EL PRECIO CON DESCUENTO
    'total' => $total,     // ← TOTAL CON DESCUENTO
    'tienda_whatsapp' => $config['tienda_whatsapp'] ?? ''
]);
?>