<?php
include 'conexion.php';
session_start();


// ===== CONTADOR DE VISITAS A LA PÁGINA =====
$hoy = date('Y-m-d');
$conn->query("INSERT INTO visitas_pagina (fecha, visitas) 
              VALUES ('$hoy', 1) 
              ON DUPLICATE KEY UPDATE visitas = visitas + 1");

// Obtener categoría seleccionada
$categoria_seleccionada = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;

// Obtener búsqueda
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// ===== ORDENAMIENTO =====
$orden = isset($_GET['orden']) ? $_GET['orden'] : 'relevancia';

// Obtener todas las categorías activas
$categorias = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// Consulta de productos
$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE 1=1";

// Si hay categoría seleccionada, filtrar por ella
if ($categoria_seleccionada > 0) {
    $sql .= " AND p.categoria_id = $categoria_seleccionada";
}

// Si hay búsqueda, filtrar por nombre o descripción
if (!empty($busqueda)) {
    $busqueda_segura = $conn->real_escape_string($busqueda);
    $sql .= " AND (p.nombre LIKE '%$busqueda_segura%' 
                   OR p.descripcion LIKE '%$busqueda_segura%')";
}

// Ordenamiento
if ($orden == 'menor') {
    $sql .= " ORDER BY p.precio ASC";
} elseif ($orden == 'mayor') {
    $sql .= " ORDER BY p.precio DESC";
} elseif ($orden == 'nuevos') {
    $sql .= " ORDER BY p.id DESC";
} else {
    $sql .= " ORDER BY p.id DESC";
}

$productos = $conn->query($sql);

// Obtener configuración básica
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('tienda_nombre', 'tienda_whatsapp', 'moneda_simbolo')");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Función para contar colores de un producto
function contar_colores_producto($producto_id, $conn) {
    $result = $conn->query("SELECT COUNT(DISTINCT color_id) as total FROM producto_variantes WHERE producto_id = $producto_id");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

// ===== NUEVA FUNCIÓN PARA DESCUENTOS =====
function obtenerDescuentoProducto($producto_id, $conn) {
    $query = $conn->query("
        SELECT * FROM productos_descuento 
        WHERE producto_id = $producto_id 
        AND activo = 1 
        AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
        AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
        LIMIT 1
    ");
    return $query->fetch_assoc();
}

// Obtener total del carrito
require_once 'carrito/funciones_carrito.php';
$total_carrito = contar_carrito();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
/* ===== ESTILOS GENERALES ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(27deg, #3A6073, #16222A);
    padding: 5px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
}

/* ===== CONTROL DE VISTAS ===== */
.desktop-view {
    display: block;
}

.mobile-view {
    display: none;
}

/* ===== ESTILOS DESKTOP ===== */
.sample-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: white;
    padding: 20px 30px;
    border-radius: 24px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    min-height: 100px;
    overflow: hidden;
}

.sample-header::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
}

.sample-header h1 {
    font-size: 2.2rem;
}

/* ===== CARRITO MEJORADO ===== */
.carrito-icono {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: white;
    font-size: 1.5rem;
    width: 60px;
    height: 60px;
    background: rgb(24, 123, 138);
    border-radius: 50%;
    transition: all 0.3s ease;
    border: 2px solid rgba(255, 255, 255, 0.3);
    margin-left: auto;
}

.carrito-icono:hover {
    background: #82e8fa;
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 1px 200px rgb(253, 252, 252);
}

.carrito-contador {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #0f172a;
    color: white;
    border-radius: 50%;
    width: 26px;
    height: 26px;
    font-size: 0.9rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    animation: pulse 2s infinite;
    border: 2px solid #fefeff;
}

/* ===== BUSCADOR CON SUGERENCIAS ===== */
.buscador-container {
    position: relative;
    width: 100%;
    margin-bottom: 15px;
}

.search-demo {
    display: flex;
    gap: 10px;
}

.search-demo input {
    flex: 1;
    padding: 15px 20px;
    border: 2px solid rgba(255,255,255,0.1);
    border-radius: 50px;
    font-size: 1rem;
    background: rgba(255,255,255,0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.search-demo input::placeholder {
    color: rgba(255,255,255,0.7);
}

.search-demo input:focus {
    outline: none;
    border-color: #ff6b35;
    background: rgba(255,255,255,0.15);
}

.search-demo button {
    padding: 15px 30px;
    background: #4CA1AF;
    color: white;
    border: none;
    border-radius: 50px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
}

.search-demo button:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgb(28, 77, 97);
}

/* Caja de sugerencias */
.sugerencias-box {
    position: absolute;
    top: calc(100% + 5px);
    left: 0;
    right: 0;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    z-index: 1000;
    display: none;
    overflow: hidden;
}

.sugerencias-box.activo {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.sugerencias-titulo {
    padding: 12px 20px;
    background: #f8fafc;
    color: #64748b;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e2e8f0;
}

.sugerencia-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px 20px;
    text-decoration: none;
    color: #1e293b;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s;
}

.sugerencia-item:hover {
    background: #fff3f0;
    transform: translateX(5px);
}

.sugerencia-info {
    flex: 1;
}

.sugerencia-nombre {
    font-weight: 600;
    margin-bottom: 2px;
}

.sugerencia-categoria {
    font-size: 0.75rem;
    color: #64748b;
}

.ver-todos {
    display: block;
    padding: 15px 20px;
    text-align: center;
    background: linear-gradient(135deg, #0f172a, #1e293b);
    color: white;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.ver-todos:hover {
    background: #ff6b35;
}

.no-resultados {
    padding: 20px;
    text-align: center;
    color: #64748b;
    font-style: italic;
}

/* Categorías */
.categorias-demo {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.categoria-item {
    padding: 12px 28px;
    background: rgba(255, 255, 255, 0.10);
    color: #ffffff;
    border-radius: 40px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(6px);
    text-decoration: none !important;
}

.categoria-item:hover {
    background: rgba(255, 255, 255, 0.20);
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.25);
}

.categoria-item.active {
    background: #4CA1AF;
    color: #000000;
    border-color: #4CA1AF;
    box-shadow: 0 8px 20px rgba(76, 161, 175, 0.35);
}

/* ===== ORDENAR SENCILLO ===== */
.ordenar-sencillo {
    display: flex;
    justify-content: flex-end;
    margin: 0 0 20px 0;
    width: 100%;
    position: relative;
    z-index: 5;
}

.ordenar-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.1);
    padding: 8px 16px;
    border-radius: 40px;
    backdrop-filter: blur(5px);
}

.ordenar-label {
    color: white;
    font-size: 0.95rem;
    font-weight: 500;
}

.select-wrapper {
    position: relative;
    min-width: 150px;
}

.select-wrapper select {
    width: 100%;
    padding: 8px 30px 8px 15px;
    appearance: none;
    -webkit-appearance: none;
    background: white;
    border: none;
    border-radius: 30px;
    font-size: 0.95rem;
    font-weight: 500;
    color: #1a2b3c;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.select-flecha {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #2b7985;
    font-size: 0.8rem;
    pointer-events: none;
}

/* ===== NUEVOS ESTILOS PARA DESCUENTOS ===== */
.badge-descuento {
    position: absolute;
    top: 10px;
    right: 10px;
    background: linear-gradient(100deg, #81a4d4, #123c5e);
    color: white;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(252, 251, 251, 0.45);
    z-index: 2;
    animation: pulse 2s infinite;
}

.precio-original {
    text-decoration: line-through;
    color: #393d41;
    font-size: 0.9rem;
    margin-right: 8px;
}

.precio-descuento {
    color: #1f1d1d;
    font-weight: 750;
    font-size: 1.3rem;
}

/* Tarjetas de productos */
.productos-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
    margin: 40px 0;
}

.producto-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255,255,255,0.1);
    backdrop-filter: blur(5px);
}

.producto-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

.producto-imagen {
    height: 240px;
    background: linear-gradient(135deg, #f5f5f5, #e8e8e8);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.producto-imagen::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.3));
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 1;
}

.producto-card:hover .producto-imagen::before {
    opacity: 1;
}

.producto-imagen img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s;
}

.producto-card:hover .producto-imagen img {
    transform: scale(1.08);
}

.producto-imagen .badge-destacado {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #ff6b35;
    color: white;
    padding: 6px 14px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    z-index: 2;
    animation: pulse 2s infinite;
}

.producto-info {
    padding: 20px;
    background: linear-gradient(to bottom, white, #fafafa);
    text-align: center;
}

.producto-info h3 {
    color: #1a2b3c;
    margin-bottom: 8px;
    font-size: 1.2rem;
    font-weight: 600;
}

.producto-categoria {
    color: #888;
    font-size: 0.8rem;
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.producto-precio {
    margin-bottom: 18px;
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 5px;
    background: #b8cff5;
    padding: 10px 22px;
    border-radius: 30px;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
}

/* Banner destacados */
.banner-destacados {
    background: linear-gradient(135deg, #4CA1AF 0%, #000000 100%);
    padding: 8px 40px;
    border-radius: 20px;
    margin: 0 0 25px 0;
    text-align: center;
    box-shadow: 0 10px 30px rgba(89, 190, 236, 0.57);
    position: relative;
    overflow: hidden;
}

.banner-destacados::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 50%);
    animation: shine 6s infinite;
}

@keyframes shine {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.banner-destacados h2 {
    color: white;
    font-size: 1.3rem;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}

/* Footer desktop */
.footer-demo {
    background: #1a2b3c;
    color: white;
    padding: 40px;
    border-radius: 12px;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin-top: 40px;
}

.footer-demo h4 {
    color: #ffffff;
    margin-bottom: 15px;
}

.footer-demo p {
    color: rgba(255,255,255,0.8);
    margin-bottom: 8px;
}

/* ===== ESTILOS MOBILE MEJORADOS ===== */
.mobile-view {
    font-family: 'Plus Jakarta Sans', sans-serif;
    color: #1e293b;
}

.mobile-view .container-mobile {
    max-width: 480px;
    margin: 0 auto;
    padding: 12px;
}

.mobile-view .header-minimal {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0 16px;
    min-height: 70px;
}

.mobile-view .menu-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 24px;
    color: white;
    cursor: pointer;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    transition: all 0.3s;
}

.mobile-view .menu-btn:active {
    background: #ff6b35;
    transform: scale(0.95);
}

.mobile-view .logo-container {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
}

.mobile-view .mobile-logo {
    width: 70px;
    height: 55px;
    object-fit: contain;
    transition: transform 0.3s ease;
    filter: drop-shadow(0 2px 5px rgba(0,0,0,0.2));
}

.mobile-view .cart-minimal {
    position: relative;
    background: linear-gradient(27deg, #3A6073, #16222A);
    border: 1px solid #3A6073;
    color: white;
    font-size: 1.3rem;
    text-decoration: none;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    backdrop-filter: blur(10px);
    transition: all 0.3s;
}

.mobile-view .cart-minimal:active {
    background: #ff6b35;
    transform: scale(0.95);
}

.mobile-view .cart-badge-minimal {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ff6b35;
    color: white;
    font-size: 11px;
    font-weight: 700;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #16222A;
    animation: pulse 2s infinite;
}

/* Buscador móvil con sugerencias */
.mobile-view .search-minimal {
    position: relative;
    margin-bottom: 20px;
}

.mobile-view .search-minimal form {
    display: flex;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 30px;
    padding: 4px 4px 4px 16px;
    backdrop-filter: blur(10px);
}

.mobile-view .search-minimal input {
    flex: 1;
    border: none;
    padding: 14px 0;
    font-size: 15px;
    background: transparent;
    color: white;
}

.mobile-view .search-minimal input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.mobile-view .search-minimal input:focus {
    outline: none;
}

.mobile-view .search-minimal button {
    background: #41a8b8;
    border: none;
    color: white;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s;
}

.mobile-view .search-minimal button:active {
    transform: scale(0.95);
}

/* Sugerencias móvil */
.mobile-view .sugerencias-mobile {
    position: absolute;
    top: calc(100% + 5px);
    left: 0;
    right: 0;
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    z-index: 1000;
    display: none;
    max-height: 300px;
    overflow-y: auto;
}

.mobile-view .sugerencias-mobile.activo {
    display: block;
}

.mobile-view .sugerencia-mobile-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    text-decoration: none;
    color: #1e293b;
    border-bottom: 1px solid #f1f5f9;
}

.mobile-view .sugerencia-mobile-item:active {
    background: #fff3f0;
}

.mobile-view .sugerencia-mobile-info {
    flex: 1;
}

.mobile-view .sugerencia-mobile-nombre {
    font-size: 14px;
    font-weight: 600;
}

/* Menú lateral */
.mobile-view .side-menu {
    position: fixed;
    top: 0;
    left: -290px;
    width: 290px;
    height: 100vh;
    background: linear-gradient(145deg, #d3d5d8 50%, #8697a7 100%);
    box-shadow: 10px 0 30px rgba(0,0,0,0.15);
    z-index: 1000;
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
    overflow-y: auto;
    border-radius: 0 30px 30px 0;
}

.mobile-view .side-menu.show {
    left: 0;
}

.mobile-view .menu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(5px);
}

.mobile-view .menu-title {
    font-size: 22px;
    font-weight: 800;
    color: #1e293b;
    letter-spacing: -0.5px;
}

.mobile-view .close-menu {
    background: white;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #1e293b;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.mobile-view .close-menu:hover {
    transform: rotate(90deg);
    background: #ff6b35;
    color: white;
}

.mobile-view .menu-content {
    padding: 25px 15px;
}

.mobile-view .menu-section {
    margin-bottom: 30px;
}

.mobile-view .menu-section-title {
    font-size: 12px;
    font-weight: 800;
    color: rgba(30, 41, 59, 0.6);
    text-transform: uppercase;
    margin-bottom: 15px;
    margin-left: 15px;
    letter-spacing: 1.5px;
}

.mobile-view .menu-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 14px 18px;
    color: #1e293b;
    text-decoration: none;
    border-radius: 16px;
    transition: all 0.3s ease;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 5px;
    border: 1px solid transparent;
}

.mobile-view .menu-item i {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 8px;
    color: #ff6b35;
    font-size: 14px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.mobile-view .menu-item:hover {
    background: rgba(255, 255, 255, 0.4);
    transform: translateX(8px);
    border-color: rgba(255, 255, 255, 0.5);
}

.mobile-view .menu-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(4px);
    z-index: 999;
    display: none;
    opacity: 0;
    transition: opacity 0.4s ease;
}

.mobile-view .menu-overlay.show {
    display: block;
    opacity: 1;
}

/* Filtro móvil */
.mobile-view .filtro-compacto {
    margin-bottom: 25px;
    position: relative;
    z-index: 10;
}

.mobile-view .filtro-compacto::before {
    content: "";
    position: absolute;
    inset: -1px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.3), transparent, rgba(58, 96, 115, 0.5));
    border-radius: 20px;
    z-index: -1;
}

.mobile-view .filtro-select {
    width: 100%;
    padding: 16px 45px 16px 20px;
    background: rgba(22, 34, 42, 0.7);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    color: #e2e8f0;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    transition: all 0.4s;
}

.mobile-view .filtro-compacto::after {
    content: '\f078';
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 12px;
    color: #3A6073;
    position: absolute;
    right: 22px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
    transition: all 0.4s ease;
}

.mobile-view .filtro-select:focus {
    outline: none;
    border-color: #3A6073;
    background: rgba(22, 34, 42, 0.9);
}

/* Badges móvil */
.mobile-view .badges-scroll {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding: 4px 0 16px;
    margin-bottom: 8px;
    scrollbar-width: none;
}

.mobile-view .badges-scroll::-webkit-scrollbar {
    display: none;
}

.mobile-view .badge-minimal {
    display: inline-block;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 10px 18px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    border: 1px solid rgba(255, 255, 255, 0.2);
    text-decoration: none;
    color: white;
    transition: all 0.2s;
}

.mobile-view .badge-minimal:active {
    transform: scale(0.95);
}

.mobile-view .badge-minimal.active {
    background: #ff6b35;
    color: white;
    border-color: #ff6b35;
    box-shadow: 0 4px 10px rgba(255,107,53,0.3);
}

/* Productos móvil */
.mobile-view .section-title {
    font-size: 20px;
    font-weight: 800;
    margin-bottom: 16px;
    color: white;
}

.mobile-view .productos-grid-mobile {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 30px;
}

.mobile-view .producto-card-mobile {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    transition: 0.2s;
    border: 1px solid rgba(255,255,255,0.8);
}

.mobile-view .producto-card-mobile:active {
    transform: translateY(-4px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    border-color: #ff6b35;
}

.mobile-view .producto-imagen-mobile {
    aspect-ratio: 1/1;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.mobile-view .producto-imagen-mobile img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.mobile-view .producto-info-mobile {
    padding: 12px 10px 14px;
}

.mobile-view .producto-badge-mobile {
    display: inline-block;
    background: #ff6b35;
    color: white;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 30px;
    margin-bottom: 6px;
}

.mobile-view .producto-nombre-mobile {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 4px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: #1e293b;
}

.mobile-view .producto-categoria-mobile {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 4px;
}

.mobile-view .producto-colores-mobile {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.mobile-view .producto-precio-mobile {
    font-size: 16px;
    font-weight: 800;
    color: #000000;
}

.mobile-view .separador-mobile {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 24px 0 16px;
}

.mobile-view .separador-linea {
    flex: 1;
    height: 1px;
    background: linear-gradient(90deg, transparent, #ff6b35, transparent);
}

.mobile-view .separador-texto {
    font-size: 14px;
    font-weight: 600;
    color: white;
    white-space: nowrap;
}

.mobile-view .footer-minimal {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    text-align: center;
    font-size: 12px;
    color: rgba(255,255,255,0.7);
}




/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .desktop-view {
        display: none;
    }
    
    .mobile-view {
        display: block;
    }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes touchRipple {
    0% { transform: scale(1); }
    50% { transform: scale(0.97); filter: brightness(1.2); }
    100% { transform: scale(1); }
}

.mobile-view .filtro-select:active,
.mobile-view .btn-comprar-mobile:active {
    animation: touchRipple 0.3s ease;
}

/* Scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.2);
}

::-webkit-scrollbar-thumb {
    background: #4CA1AF;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #087283;
}
    </style>
</head>
<body>

<!-- ===== VERSIÓN DESKTOP ===== -->
<div class="desktop-view">
    <div class="container">
        <!-- Header con carrito mejorado -->
        <div class="sample-header">
            <div style="flex: 1;">
                <img src="img/logo.png" alt="Tienda MS" style="height: 80px; width: auto;">
            </div>
            
            <div style="position: absolute; left: 50%; transform: translateX(-50%); text-align: center;">
                <h1 style="margin: 0; font-size: 2.5rem; font-weight: 700;">𝐓𝐈𝐄𝐍𝐃𝐀 𝐌𝐒</h1>
                <p style="margin: 5px 0 0; color: #f7f7f7; font-size: 0.9rem; font-style: italic;">
                    Pisa fuerte, marca tu estilo.
                </p>
            </div>
            
            <div style="flex: 1; text-align: right;">
                <a href="carrito/carrito.php" class="carrito-icono">
                    🛒
                    <?php if ($total_carrito > 0): ?>
                        <span class="carrito-contador"><?php echo $total_carrito; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Buscador con sugerencias AJAX -->
        <div class="buscador-container">
            <form method="GET" class="search-demo" id="searchForm" autocomplete="off">
                <input type="text" 
                       name="buscar" 
                       id="buscadorInput" 
                       placeholder="¿Qué producto buscas hoy?" 
                       value="<?php echo htmlspecialchars($busqueda); ?>"
                       onkeyup="buscarSugerencias(this.value)">
                <button type="submit">🔍 Buscar</button>
            </form>
            <div id="sugerencias" class="sugerencias-box"></div>
        </div>

        <!-- Categorías -->
        <div class="categorias-demo">
            <a href="?" class="categoria-item <?php echo $categoria_seleccionada == 0 ? 'active' : ''; ?>">
                Todos
            </a>
            <?php 
            if ($categorias && $categorias->num_rows > 0):
                while($cat = $categorias->fetch_assoc()): 
            ?>
                <a href="?categoria=<?php echo $cat['id']; ?>" 
                   class="categoria-item <?php echo $categoria_seleccionada == $cat['id'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['nombre']); ?>
                </a>
            <?php 
                endwhile;
            endif; 
            ?>
        </div>

        <!-- ORDENAR POR -->
        <div class="ordenar-sencillo">
            <form method="GET" id="ordenForm">
                <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
                <input type="hidden" name="categoria" value="<?php echo $categoria_seleccionada; ?>">
                
                <div class="ordenar-wrapper">
                    <span class="ordenar-label">Ordenar por:</span>
                    <div class="select-wrapper">
                        <select name="orden" onchange="this.form.submit()">
                            <option value="relevancia" <?php echo $orden == 'relevancia' ? 'selected' : ''; ?>>Más relevantes</option>
                            <option value="menor" <?php echo $orden == 'menor' ? 'selected' : ''; ?>>Menor precio</option>
                            <option value="mayor" <?php echo $orden == 'mayor' ? 'selected' : ''; ?>>Mayor precio</option>
                            <option value="nuevos" <?php echo $orden == 'nuevos' ? 'selected' : ''; ?>>Más nuevos</option>
                        </select>
                        <span class="select-flecha">▼</span>
                    </div>
                </div>
            </form>
        </div>

        <?php 
        // Separar productos destacados y normales
        $productos_destacados = [];
        $productos_normales = [];

        if ($productos && $productos->num_rows > 0) {
            mysqli_data_seek($productos, 0);
            while($producto = $productos->fetch_assoc()) {
                $destacado = $conn->query("
                    SELECT * FROM productos_destacados 
                    WHERE producto_id = " . $producto['id'] . " 
                    AND activo = 1 
                    AND (fecha_fin IS NULL OR fecha_fin > NOW())
                    LIMIT 1
                ");
                
                if ($destacado && $destacado->num_rows > 0) {
                    $productos_destacados[] = $producto;
                } else {
                    $productos_normales[] = $producto;
                }
            }
        }
        ?>

        <!-- SECCIÓN DESTACADOS -->
        <?php if (count($productos_destacados) > 0): ?>
        <section class="seccion-destacados">
            <div class="banner-destacados">
                <h2>🔥 PRODUCTOS DESTACADOS</h2>
            </div>
            
            <div class="productos-grid">
                <?php foreach($productos_destacados as $producto): 
                    $destacado = $conn->query("
                        SELECT * FROM productos_destacados 
                        WHERE producto_id = " . $producto['id'] . " 
                        AND activo = 1 
                        AND (fecha_fin IS NULL OR fecha_fin > NOW())
                        ORDER BY orden, id DESC 
                        LIMIT 1
                    ");
                    
                    $badge_texto = '';
                    $badge_color = '#ff6b35';
                    
                    if ($destacado && $destacado->num_rows > 0) {
                        $d = $destacado->fetch_assoc();
                        $badge_texto = $d['badge_texto'] ?: $d['tipo_destacado'];
                        $badge_color = $d['badge_color'] ?: '#ff6b35';
                    }
                    
                    // ===== NUEVO: Obtener descuento =====
                    $descuento = obtenerDescuentoProducto($producto['id'], $conn);
                    $precio_original = $producto['precio'];
                    $precio_final = $precio_original;
                    $porcentaje = 0;
                    
                    if ($descuento) {
                        $porcentaje = $descuento['valor_descuento'];
                        $precio_final = $precio_original * (1 - $porcentaje / 100);
                    }
                ?>
                    <a href="producto.php?id=<?php echo $producto['id']; ?>" style="text-decoration: none; color: inherit; display: block;">    
                        <div class="producto-card">
                            <div class="producto-imagen" style="position: relative;">
                                <?php if (!empty($producto['imagen']) && file_exists("img/".$producto['imagen'])): ?>
                                    <img src="img/<?php echo $producto['imagen']; ?>" 
                                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span style="font-size: 3rem;">👟</span>
                                <?php endif; ?>
                                
                                <?php if ($badge_texto): ?>
                                    <span style="position: absolute; top: 10px; left: 10px; background: <?php echo $badge_color; ?>; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; z-index: 10;">
                                        <?php echo htmlspecialchars($badge_texto); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($descuento): ?>
                                    <span class="badge-descuento" style="top: 10px; right: 10px;">
                                        🔥 <?php echo $porcentaje; ?>% OFF
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="producto-info">
                                <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                <div class="producto-categoria">
                                    <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                </div>
                                <div class="producto-precio">
                                    <?php if ($descuento): ?>
                                        <span class="precio-original"><?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_original, 0); ?></span>
                                        <span class="precio-descuento"><?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_final, 0); ?></span>
                                    <?php else: ?>
                                        <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($producto['precio'], 0); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>    
            </div>
        </section>
        <?php endif; ?>

        <!-- SECCIÓN OTROS PRODUCTOS -->
        <?php if (count($productos_normales) > 0): ?>
        <section class="seccion-productos">
            <div class="separador-productos">
                <span class="linea"></span>
                <h2></h2>
                <span class="linea"></span>
            </div>
            
            <div class="productos-grid">
                <?php foreach($productos_normales as $producto): 
                    // ===== NUEVO: Obtener descuento =====
                    $descuento = obtenerDescuentoProducto($producto['id'], $conn);
                    $precio_original = $producto['precio'];
                    $precio_final = $precio_original;
                    $porcentaje = 0;
                    
                    if ($descuento) {
                        $porcentaje = $descuento['valor_descuento'];
                        $precio_final = $precio_original * (1 - $porcentaje / 100);
                    }
                ?>
                    <a href="producto.php?id=<?php echo $producto['id']; ?>" style="text-decoration: none; color: inherit; display: block;">    
                        <div class="producto-card">
                            <div class="producto-imagen" style="position: relative;">
                                <?php if (!empty($producto['imagen']) && file_exists("img/".$producto['imagen'])): ?>
                                    <img src="img/<?php echo $producto['imagen']; ?>" 
                                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span style="font-size: 3rem;">👟</span>
                                <?php endif; ?>
                                
                                <?php if ($descuento): ?>
                                    <span class="badge-descuento">
                                        🔥 <?php echo $porcentaje; ?>% OFF
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="producto-info">
                                <h3><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                                <div class="producto-categoria">
                                    <?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?>
                                </div>
                                <div class="producto-precio">
                                    <?php if ($descuento): ?>
                                        <span class="precio-original"><?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_original, 0); ?></span>
                                        <span class="precio-descuento"><?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_final, 0); ?></span>
                                    <?php else: ?>
                                        <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($producto['precio'], 0); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-demo">
            <div>
                <h4>TIENDA MS</h4>
                <p>"Pisa con confianza, viste con estilo. Calidad y comodidad que te acompañan a donde vayas"</p>
            </div>
            <div>
                <h4>Contacto</h4>
                <p>📞 WhatsApp: <?php echo $config['tienda_whatsapp'] ?? '300 363 3730'; ?></p>
                <p>📍 Sahagun, Cordoba</p>
            </div>
            <div>
        <h4>🕒 Horario de atención</h4>
        <p>Lunes a Sábado: 9am - 7pm</p>
        <p>💬 WhatsApp disponible 24/7</p>
            </div>
        </div>
        <!-- Copyright -->
<div style="text-align: center; margin-top: 13px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.2); color: rgba(255,255,255,0.7); font-size: 12px;">
    TIENDA MS - Todos los derechos reservados © <?php echo date('Y'); ?>
</div>
    </div>
</div>

<!-- ===== VERSIÓN MOBILE ===== -->
<div class="mobile-view">
    <div class="container-mobile">
        <!-- Header con carrito -->
        <div class="header-minimal">
            <button class="menu-btn" onclick="toggleMenu()">
                <i class="fa-solid fa-bars"></i>
            </button>
            
            <div class="logo-container">
                <img src="img/logo.png" alt="MS" class="mobile-logo">
            </div>
            
            <a href="carrito/carrito.php" class="cart-minimal">
                <i class="fa-solid fa-bag-shopping"></i>
                <?php if ($total_carrito > 0): ?>
                    <span class="cart-badge-minimal"><?php echo $total_carrito; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Menú lateral -->
        <div class="side-menu" id="sideMenu">
            <div class="menu-header">
                <span class="menu-title">Menú</span>
                <button class="close-menu" onclick="toggleMenu()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="menu-content">
                <div class="menu-section">
                    <h4 class="menu-section-title">Categorías</h4>
                    <a href="?" class="menu-item" onclick="toggleMenu()">
                        <i class="fa-solid fa-tag"></i> Todos
                    </a>
                    <?php 
                    if ($categorias && $categorias->num_rows > 0):
                        $categorias->data_seek(0);
                        while($cat = $categorias->fetch_assoc()): 
                    ?>
                    <a href="?categoria=<?php echo $cat['id']; ?>" class="menu-item" onclick="toggleMenu()">
                        <i class="fa-solid fa-folder"></i> <?php echo htmlspecialchars($cat['nombre']); ?>
                    </a>
                    <?php 
                        endwhile;
                    endif; 
                    ?>
                </div>
                
                <div class="menu-section">
                    <h4 class="menu-section-title">Opciones</h4>
                    <a href="index.php" class="menu-item" onclick="toggleMenu()">
                        <i class="fa-solid fa-home"></i> Inicio
                    </a>
                    <a href="carrito/carrito.php" class="menu-item" onclick="toggleMenu()">
                        <i class="fa-solid fa-shopping-cart"></i> Mi Carrito
                        <?php if ($total_carrito > 0): ?>
                            <span style="background: #ff6b35; color: white; padding: 2px 8px; border-radius: 20px; margin-left: 10px; font-size: 12px;">
                                <?php echo $total_carrito; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Overlay -->
        <div class="menu-overlay" id="menuOverlay" onclick="toggleMenu()"></div>

        <!-- Buscador con sugerencias móvil -->
        <div class="search-minimal">
            <form method="GET" id="searchFormMobile" autocomplete="off">
                <input type="text" 
                       name="buscar" 
                       id="buscadorMobileInput" 
                       placeholder="¿Qué producto buscas hoy?" 
                       value="<?php echo htmlspecialchars($busqueda); ?>"
                       onkeyup="buscarSugerenciasMobile(this.value)">
                <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
            </form>
            <div id="sugerenciasMobile" class="sugerencias-mobile"></div>
        </div>

        <!-- Filtro compacto -->
        <div class="filtro-compacto">
            <form method="GET" id="filtroFormMobile">
                <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
                <input type="hidden" name="categoria" value="<?php echo $categoria_seleccionada; ?>">
                <select name="orden" class="filtro-select" onchange="this.form.submit()">
                    <option value="relevancia" <?php echo $orden == 'relevancia' ? 'selected' : ''; ?>>Más relevantes</option>
                    <option value="menor" <?php echo $orden == 'menor' ? 'selected' : ''; ?>>Menor precio</option>
                    <option value="mayor" <?php echo $orden == 'mayor' ? 'selected' : ''; ?>>Mayor precio</option>
                    <option value="nuevos" <?php echo $orden == 'nuevos' ? 'selected' : ''; ?>>Más nuevos</option>
                </select>
            </form>
        </div>

        <!-- Badges -->
        <?php 
        $badges_activos = $conn->query("SELECT * FROM badges WHERE activo = 1 ORDER BY orden");
        ?>
        
        <?php if ($badges_activos && $badges_activos->num_rows > 0): ?>
        <div class="badges-scroll">
            <?php 
            $badge_filtro = isset($_GET['badge']) ? $_GET['badge'] : '';
            $badges_activos->data_seek(0);
            while($badge = $badges_activos->fetch_assoc()): 
            ?>
            <a href="?badge=<?php echo $badge['tipo_filtro']; ?>&buscar=<?php echo urlencode($busqueda); ?>&categoria=<?php echo $categoria_seleccionada; ?>&orden=<?php echo $orden; ?>" 
               class="badge-minimal <?php echo $badge_filtro == $badge['tipo_filtro'] ? 'active' : ''; ?>"
               style="<?php echo $badge_filtro == $badge['tipo_filtro'] ? 'background: ' . $badge['color'] . '; color: white; border-color: ' . $badge['color'] . ';' : ''; ?>">
                <i class="fa-solid <?php echo $badge['icono']; ?>"></i> <?php echo $badge['nombre']; ?>
            </a>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <!-- Productos destacados -->
        <?php if (count($productos_destacados) > 0): ?>
        <h2 class="section-title">🔥 Destacados</h2>
        <div class="productos-grid-mobile">
            <?php foreach($productos_destacados as $producto): 
                $num_colores = contar_colores_producto($producto['id'], $conn);
                $descuento = obtenerDescuentoProducto($producto['id'], $conn);
                $precio_original = $producto['precio'];
                $precio_final = $precio_original;
                $porcentaje = 0;
                
                if ($descuento) {
                    $porcentaje = $descuento['valor_descuento'];
                    $precio_final = $precio_original * (1 - $porcentaje / 100);
                }
            ?>
            <a href="producto.php?id=<?php echo $producto['id']; ?>" class="producto-card-mobile">
                <div class="producto-imagen-mobile" style="position: relative;">
                    <?php if (!empty($producto['imagen']) && file_exists("img/".$producto['imagen'])): ?>
                        <img src="img/<?php echo $producto['imagen']; ?>" alt="<?php echo $producto['nombre']; ?>">
                    <?php else: ?>
                        <span style="font-size: 40px;">👟</span>
                    <?php endif; ?>
                    
                    <?php if ($descuento): ?>
                        <span style="position: absolute; top: 5px; right: 5px; background: #f7f4f3; color: white; padding: 2px 6px; border-radius: 20px; font-size: 0.6rem; font-weight: bold;">
                            -<?php echo $porcentaje; ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <div class="producto-info-mobile">
                    <div class="producto-badge-mobile">Superventas</div>
                    <div class="producto-nombre-mobile"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                    <div class="producto-categoria-mobile"><?php echo htmlspecialchars($producto['categoria_nombre'] ?? ''); ?></div>
                    <?php if ($num_colores > 0): ?>
                    <div class="producto-colores-mobile">
                        <i class="fa-regular fa-palette"></i> <?php echo $num_colores; ?> colores
                    </div>
                    <?php endif; ?>
                    <div class="producto-precio-mobile">
                        <?php if ($descuento): ?>
                            <span style="text-decoration: line-through; font-size: 0.8rem; color: #999;"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($precio_original, 0); ?></span>
                            <span style="color: #ff6b35;"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($precio_final, 0); ?></span>
                        <?php else: ?>
                            <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($producto['precio'], 0); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Separador -->
        <?php if (count($productos_normales) > 0 && count($productos_destacados) > 0): ?>
        <div class="separador-mobile">
            <span class="separador-linea"></span>
            <span class="separador-texto">También te puede gustar</span>
            <span class="separador-linea"></span>
        </div>
        <?php endif; ?>

        <!-- Productos normales -->
        <?php if (count($productos_normales) > 0): ?>
        <div class="productos-grid-mobile">
            <?php foreach($productos_normales as $producto): 
                $num_colores = contar_colores_producto($producto['id'], $conn);
                $descuento = obtenerDescuentoProducto($producto['id'], $conn);
                $precio_original = $producto['precio'];
                $precio_final = $precio_original;
                $porcentaje = 0;
                
                if ($descuento) {
                    $porcentaje = $descuento['valor_descuento'];
                    $precio_final = $precio_original * (1 - $porcentaje / 100);
                }
            ?>
            <a href="producto.php?id=<?php echo $producto['id']; ?>" class="producto-card-mobile">
                <div class="producto-imagen-mobile" style="position: relative;">
                    <?php if (!empty($producto['imagen']) && file_exists("img/".$producto['imagen'])): ?>
                        <img src="img/<?php echo $producto['imagen']; ?>" alt="<?php echo $producto['nombre']; ?>">
                    <?php else: ?>
                        <span style="font-size: 40px;">👟</span>
                    <?php endif; ?>
                    
                    <?php if ($descuento): ?>
                        <span style="position: absolute; top: 5px; right: 5px; background: linear-gradient(100deg, #81a4d4, #123c5e); color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.9rem; font-weight: bold;">
                          🔥-<?php echo $porcentaje; ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <div class="producto-info-mobile">
                    <div class="producto-nombre-mobile"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                    <div class="producto-categoria-mobile"><?php echo htmlspecialchars($producto['categoria_nombre'] ?? ''); ?></div>
                    <?php if ($num_colores > 0): ?>
                    <div class="producto-colores-mobile">
                        <i class="fa-regular fa-palette"></i> <?php echo $num_colores; ?> colores
                    </div>
                    <?php endif; ?>
                    <div class="producto-precio-mobile">
                        <?php if ($descuento): ?>
                            <span style="text-decoration: line-through; font-size: 0.8rem; color: #999;"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($precio_original, 0); ?></span>
                            <span style="color: #000000;"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($precio_final, 0); ?></span>
                        <?php else: ?>
                            <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($producto['precio'], 0); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-minimal">
            <p><?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?> © <?php echo date('Y'); ?></p>
        </div>
    </div>
</div>

<script>
// Función para buscar sugerencias en desktop (usa buscar_ajax.php)
function buscarSugerencias(query) {
    if (query.length < 2) {
        document.getElementById('sugerencias').classList.remove('activo');
        return;
    }
    
    fetch('buscar_ajax.php?q=' + encodeURIComponent(query))
        .then(response => response.text())
        .then(html => {
            const sugerenciasDiv = document.getElementById('sugerencias');
            sugerenciasDiv.innerHTML = html;
            sugerenciasDiv.classList.add('activo');
        });
}

// Función para buscar sugerencias en móvil (usa buscar_ajax.php)
function buscarSugerenciasMobile(query) {
    if (query.length < 2) {
        document.getElementById('sugerenciasMobile').classList.remove('activo');
        return;
    }
    
    fetch('buscar_ajax.php?q=' + encodeURIComponent(query))
        .then(response => response.text())
        .then(html => {
            const sugerenciasDiv = document.getElementById('sugerenciasMobile');
            sugerenciasDiv.innerHTML = html;
            sugerenciasDiv.classList.add('activo');
        });
}

// Cerrar sugerencias al hacer clic fuera
document.addEventListener('click', function(e) {
    const sugerencias = document.getElementById('sugerencias');
    const buscador = document.getElementById('buscadorInput');
    if (!buscador.contains(e.target) && !sugerencias.contains(e.target)) {
        sugerencias.classList.remove('activo');
    }
    
    const sugerenciasM = document.getElementById('sugerenciasMobile');
    const buscadorM = document.getElementById('buscadorMobileInput');
    if (buscadorM && !buscadorM.contains(e.target) && !sugerenciasM.contains(e.target)) {
        sugerenciasM.classList.remove('activo');
    }
});

// Función para abrir/cerrar el menú móvil
function toggleMenu() {
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    
    if (menu) {
        menu.classList.toggle('show');
        overlay.classList.toggle('show');
        
        if (menu.classList.contains('show')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
}

// Cerrar menú con ESC
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const menu = document.getElementById('sideMenu');
            const overlay = document.getElementById('menuOverlay');
            if (menu && menu.classList.contains('show')) {
                menu.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
    });
});
</script>
</body>
</html>