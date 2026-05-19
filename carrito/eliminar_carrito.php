<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_GET['id'])) {
    header("Location: carrito.php");
    exit;
}

$id = intval($_GET['id']);
$conn->query("DELETE FROM carrito WHERE id = $id");

header("Location: carrito.php?eliminado=1");
exit;
?>