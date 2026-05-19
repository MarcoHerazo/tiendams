<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_SESSION['carrito_session'])) {
    header("Location: carrito.php");
    exit;
}

$session_id = $_SESSION['carrito_session'];
$conn->query("DELETE FROM carrito WHERE session_id = '$session_id'");

header("Location: carrito.php?vaciado=1");
exit;
?>