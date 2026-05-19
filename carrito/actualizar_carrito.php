<?php
session_start();
include __DIR__ . '/../conexion.php';

if (!isset($_POST['item_id']) || !isset($_POST['cantidad'])) {
    header("Location: carrito.php");
    exit;
}

$id = intval($_POST['item_id']);
$cantidad = intval($_POST['cantidad']);

if ($cantidad < 1) $cantidad = 1;
if ($cantidad > 10) $cantidad = 10;

$conn->query("UPDATE carrito SET cantidad = $cantidad WHERE id = $id");

header("Location: carrito.php?actualizado=1");
exit;
?>