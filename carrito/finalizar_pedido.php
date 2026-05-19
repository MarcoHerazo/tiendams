<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_SESSION['carrito_session'])) {
    header("Location: carrito.php");
    exit;
}

$session_id = $_SESSION['carrito_session'];

// Obtener productos del carrito
$productos = $conn->query("
    SELECT c.*, p.nombre, p.precio, t.talla, col.nombre as color_nombre
    FROM carrito c
    JOIN productos p ON c.producto_id = p.id
    LEFT JOIN tallas t ON c.talla_id = t.id
    LEFT JOIN colores col ON c.color_id = col.id
    WHERE c.session_id = '$session_id'
");

// Obtener configuración
$config = [];
$result = $conn->query("SELECT valor FROM configuracion WHERE clave = 'tienda_whatsapp'");
if ($row = $result->fetch_assoc()) {
    $whatsapp = $row['valor'];
} else {
    $whatsapp = '';
}

// Construir mensaje
$mensaje = "🛒 *NUEVO PEDIDO* 🛒\n\n";
$total = 0;

while($item = $productos->fetch_assoc()) {
    $subtotal = $item['precio'] * $item['cantidad'];
    $total += $subtotal;
    
    $mensaje .= "• " . $item['nombre'];
    if ($item['talla']) $mensaje .= " (Talla: " . $item['talla'] . ")";
    if ($item['color_nombre']) $mensaje .= " (Color: " . $item['color_nombre'] . ")";
    $mensaje .= "\n  Cantidad: " . $item['cantidad'] . " x $" . number_format($item['precio'], 0) . " = $" . number_format($subtotal, 0) . "\n\n";
}

$mensaje .= "💰 *TOTAL: $" . number_format($total, 0) . "*\n\n";
$mensaje .= "📍 *Datos del cliente:*\n";
$mensaje .= "Nombre: [escribe tu nombre]\n";
$mensaje .= "Teléfono: [tu teléfono]\n";
$mensaje .= "Dirección: [tu dirección]\n\n";
$mensaje .= "¡Quedo atento a la confirmación! 🙌";

// Vaciar carrito después de enviar
$conn->query("DELETE FROM carrito WHERE session_id = '$session_id'");

// Redirigir a WhatsApp
header("Location: https://wa.me/$whatsapp?text=" . urlencode($mensaje));
exit;
?>