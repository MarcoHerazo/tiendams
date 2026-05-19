<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_POST['producto_id'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_SESSION['carrito_session'])) {
    $_SESSION['carrito_session'] = session_id();
}

$session_id = $_SESSION['carrito_session'];
$producto_id = intval($_POST['producto_id']);
$talla_id = isset($_POST['talla_id']) && $_POST['talla_id'] !== '' ? intval($_POST['talla_id']) : 'NULL';
$color_id = isset($_POST['color_id']) && $_POST['color_id'] !== '' ? intval($_POST['color_id']) : 'NULL';
$cantidad = intval($_POST['cantidad']);
$precio = isset($_POST['precio_final']) ? floatval($_POST['precio_final']) : 0;

if ($cantidad < 1) $cantidad = 1;
if ($cantidad > 10) $cantidad = 10;

// Verificar si ya existe
$sql_check = "SELECT id, cantidad FROM carrito 
              WHERE session_id = '$session_id' 
              AND producto_id = $producto_id";

if ($talla_id != 'NULL') {
    $sql_check .= " AND talla_id = $talla_id";
} else {
    $sql_check .= " AND talla_id IS NULL";
}

if ($color_id != 'NULL') {
    $sql_check .= " AND color_id = $color_id";
} else {
    $sql_check .= " AND color_id IS NULL";
}

$check = $conn->query($sql_check);

if ($check && $check->num_rows > 0) {
    $row = $check->fetch_assoc();
    $nueva_cantidad = $row['cantidad'] + $cantidad;
    $conn->query("UPDATE carrito SET cantidad = $nueva_cantidad WHERE id = " . $row['id']);
} else {
    // Construir consulta INSERT con precio
    if ($talla_id == 'NULL' && $color_id == 'NULL') {
        $sql = "INSERT INTO carrito (session_id, producto_id, cantidad, precio) 
                VALUES ('$session_id', $producto_id, $cantidad, $precio)";
    } elseif ($talla_id == 'NULL') {
        $sql = "INSERT INTO carrito (session_id, producto_id, color_id, cantidad, precio) 
                VALUES ('$session_id', $producto_id, $color_id, $cantidad, $precio)";
    } elseif ($color_id == 'NULL') {
        $sql = "INSERT INTO carrito (session_id, producto_id, talla_id, cantidad, precio) 
                VALUES ('$session_id', $producto_id, $talla_id, $cantidad, $precio)";
    } else {
        $sql = "INSERT INTO carrito (session_id, producto_id, talla_id, color_id, cantidad, precio) 
                VALUES ('$session_id', $producto_id, $talla_id, $color_id, $cantidad, $precio)";
    }
    
    if ($conn->query($sql)) {
        // Éxito
    } else {
        // Error - mostramos para depurar
        echo "Error: " . $conn->error;
        exit;
    }
}

header("Location: ../producto.php?id=$producto_id&agregado=1");
exit;
?>