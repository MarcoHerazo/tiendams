<?php
function contar_carrito() {
    if (session_status() === PHP_SESSION_NONE) {
        return 0;
    }
    
    if (!isset($_SESSION['carrito_session'])) {
        return 0;
    }
    
    include __DIR__ . '/../conexion.php';
    $session_id = $_SESSION['carrito_session'];
    
    $result = $conn->query("SELECT SUM(cantidad) as total FROM carrito WHERE session_id = '$session_id'");
    $row = $result->fetch_assoc();
    
    return $row['total'] ?? 0;
}
?>