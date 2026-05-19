<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: pedidos.php");
    exit;
}

// Procesar actualización de datos del cliente
if (isset($_POST['editar_cliente'])) {
    $cliente_id = intval($_POST['cliente_id']);
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $direccion = $_POST['direccion'];
    
    $stmt = $conn->prepare("UPDATE clientes SET nombre = ?, telefono = ?, direccion = ? WHERE id = ?");
    $stmt->bind_param("sssi", $nombre, $telefono, $direccion, $cliente_id);
    $stmt->execute();
    
    header("Location: detalle_pedido.php?id=$id&mensaje=cliente_actualizado");
    exit;
}

// Procesar actualización de notas del pedido
if (isset($_POST['guardar_notas'])) {
    $notas = $_POST['notas_pedido'];
    $stmt = $conn->prepare("UPDATE pedidos SET notas_admin = ? WHERE id = ?");
    $stmt->bind_param("si", $notas, $id);
    $stmt->execute();
    
    header("Location: detalle_pedido.php?id=$id&mensaje=notas_guardadas");
    exit;
}

// Procesar actualización de anticipo
if (isset($_POST['actualizar_anticipo'])) {
    $anticipo = intval($_POST['anticipo']);
    $stmt = $conn->prepare("UPDATE pedidos SET anticipo = ? WHERE id = ?");
    $stmt->bind_param("ii", $anticipo, $id);
    $stmt->execute();
    
    header("Location: detalle_pedido.php?id=$id&mensaje=anticipo_actualizado");
    exit;
}

// Procesar actualización de guía de envío
if (isset($_POST['guardar_guia'])) {
    $guia = $_POST['numero_guia'];
    $transportadora = $_POST['transportadora'];
    $stmt = $conn->prepare("UPDATE pedidos SET numero_guia = ?, transportadora = ? WHERE id = ?");
    $stmt->bind_param("ssi", $guia, $transportadora, $id);
    $stmt->execute();
    
    header("Location: detalle_pedido.php?id=$id&mensaje=guia_guardada");
    exit;
}

// Procesar cambio de estado con historial
if (isset($_GET['cambiar_estado'])) {
    $nuevo_estado = $_GET['estado'];
    $admin_nombre = $_SESSION['admin_nombre'] ?? 'Administrador';
    
    // Guardar historial
    $stmt = $conn->prepare("INSERT INTO pedidos_historial (pedido_id, estado_anterior, estado_nuevo, usuario, fecha) 
                            SELECT ?, estado, ?, ?, NOW() FROM pedidos WHERE id = ?");
    $stmt->bind_param("issi", $id, $nuevo_estado, $admin_nombre, $id);
    $stmt->execute();
    
    // Actualizar estado
    $stmt = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
    $stmt->bind_param("si", $nuevo_estado, $id);
    $stmt->execute();
    
    header("Location: detalle_pedido.php?id=$id&mensaje=estado_actualizado");
    exit;
}

// Obtener configuración para el logo
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('tienda_nombre', 'tienda_direccion', 'tienda_telefono')");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Obtener datos del pedido
$pedido = $conn->query("
    SELECT p.*, c.nombre as cliente_nombre, c.telefono, c.direccion, c.id as cliente_id
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    WHERE p.id = $id
");

if ($pedido->num_rows == 0) {
    header("Location: pedidos.php");
    exit;
}

$pedido_data = $pedido->fetch_assoc();

// Obtener productos del pedido
$productos = $conn->query("
    SELECT dp.*, pr.nombre, pr.imagen, pr.precio as precio_original, pr.id as producto_id,
           (SELECT d.tipo_descuento FROM productos_descuento d 
            WHERE d.producto_id = pr.id AND d.activo = 1 
            AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= CURDATE())
            AND (d.fecha_fin IS NULL OR d.fecha_fin >= CURDATE())
            LIMIT 1) as tenia_descuento,
           (SELECT d.valor_descuento FROM productos_descuento d 
            WHERE d.producto_id = pr.id AND d.activo = 1 
            AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= CURDATE())
            AND (d.fecha_fin IS NULL OR d.fecha_fin >= CURDATE())
            LIMIT 1) as valor_desc,
           (SELECT d.tipo_descuento FROM productos_descuento d 
            WHERE d.producto_id = pr.id AND d.activo = 1 
            AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= CURDATE())
            AND (d.fecha_fin IS NULL OR d.fecha_fin >= CURDATE())
            LIMIT 1) as tipo_desc
    FROM detalle_pedidos dp
    JOIN productos pr ON dp.producto_id = pr.id
    WHERE dp.pedido_id = $id
");

// Obtener variantes del pedido
$variantes = $conn->query("
    SELECT talla, color FROM pedido_variantes WHERE pedido_id = $id
");

$variantes_data = [];
if ($variantes && $variantes->num_rows > 0) {
    while ($var = $variantes->fetch_assoc()) {
        $variantes_data[] = $var;
    }
}

// Obtener historial de cambios de estado
$historial = $conn->query("
    SELECT * FROM pedidos_historial 
    WHERE pedido_id = $id 
    ORDER BY fecha DESC
");

// Estados
$estados = [
    'pendiente' => '⏳ Pendiente',
    'confirmado' => '✅ Confirmado',
    'enviado' => '🚚 Enviado',
    'entregado' => '🎉 Entregado',
    'cancelado' => '❌ Cancelado'
];

$estado_colores = [
    'pendiente' => '#f59e0b',
    'confirmado' => '#10b981',
    'enviado' => '#8b5cf6',
    'entregado' => '#10b981',
    'cancelado' => '#ef4444'
];

// Calcular total
$total_general = 0;
$productos->data_seek(0);
while($prod = $productos->fetch_assoc()) {
    $total_general += $prod['precio_unitario'] * $prod['cantidad'];
}
$productos->data_seek(0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Detalle Pedido #<?php echo $pedido_data['id']; ?> - Tienda MS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f172a;
            --primary-dark: #020617;
            --primary-light: #1e293b;
            --accent: #ff6b35;
            --accent-light: #ff8c5a;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #0b1120;
            --card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #64748b;
            --border: rgba(255, 255, 255, 0.08);
            --sidebar-width: 260px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --whatsapp: #25d366;
            --whatsapp-dark: #128C7E;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary-dark);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            padding: 20px 0;
            border-right: 1px solid var(--border);
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .logo {
            padding: 0 24px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            margin-bottom: 15px;
        }

.logo h2 {
    font-size: 1.4rem;
    font-weight: 800;
    background: linear-gradient(135deg, #fff, var(--primary));
    background-clip: text;              /* ← propiedad estándar */
    -webkit-background-clip: text;      /* ← soporte para navegadores antiguos */
    -webkit-text-fill-color: transparent;
    color: transparent;                 /* ← fallback */
}

        nav { padding: 0 12px; height: calc(100vh - 100px); overflow-y: auto; }
        nav::-webkit-scrollbar { width: 3px; }
        nav::-webkit-scrollbar-thumb { background: var(--border); }

        .nav-section { margin-bottom: 20px; }
        .nav-section-title {
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--text-muted);
            padding: 10px 15px;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 10px;
            font-size: 0.88rem;
            transition: var(--transition);
            margin-bottom: 2px;
        }

        .nav-item i { width: 20px; color: var(--accent); opacity: 0.8; }
        .nav-item:hover { background: rgba(255, 107, 53, 0.08); color: white; transform: translateX(4px); }
        .nav-item.active { background: rgba(255, 107, 53, 0.15); color: white; font-weight: 600; }
        .nav-item.logout { color: var(--danger); border-top: 1px solid var(--border); margin-top: 10px; border-radius: 0; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: margin-left 0.3s ease;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 800;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            color: var(--accent);
        }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: 1px solid var(--border);
        }

        .btn-back:hover {
            background: var(--accent);
            transform: translateX(-3px);
        }

        /* Logo para impresión */
        .print-logo {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ddd;
            display: none;
        }

        .print-logo img {
            max-width: 120px;
            height: auto;
            margin-bottom: 10px;
        }

        .print-logo h2 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 5px;
        }

        .print-logo p {
            color: #666;
            font-size: 12px;
        }

        /* Mensajes */
        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        /* Estado badge */
        .estado-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            background: <?php echo $estado_colores[$pedido_data['estado']]; ?>20;
            color: <?php echo $estado_colores[$pedido_data['estado']]; ?>;
            border: 1px solid <?php echo $estado_colores[$pedido_data['estado']]; ?>40;
        }

        /* Tarjetas */
        .card {
            background: var(--card);
            border-radius: 20px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .card-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header-left i {
            font-size: 18px;
            color: var(--accent);
        }

        .card-header-left h2 {
            font-size: 16px;
            font-weight: 700;
            color: white;
        }

        .btn-edit {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit:hover {
            background: var(--accent);
            color: white;
        }

        .card-body {
            padding: 20px;
        }

        /* Info cliente */
        .cliente-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .info-row i {
            width: 24px;
            color: var(--accent);
        }

        .info-row strong {
            color: white;
            min-width: 80px;
        }

        .info-row span {
            color: var(--text-secondary);
        }

        /* Anticipo y guía */
        .anticipo-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .anticipo-pagado {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .anticipo-pendiente {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        /* Tabla productos */
        .productos-table {
            width: 100%;
            border-collapse: collapse;
        }

        .productos-table th {
            text-align: left;
            padding: 12px 8px;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 12px;
            border-bottom: 1px solid var(--border);
        }

        .productos-table td {
            padding: 12px 8px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .producto-imagen {
            width: 45px;
            height: 45px;
            background: var(--primary-dark);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .producto-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .producto-nombre {
            font-weight: 600;
            color: white;
            text-decoration: none;
        }

        .producto-nombre:hover {
            color: var(--accent);
        }

        .producto-precio {
            font-weight: bold;
            color: var(--accent);
        }

        .producto-precio-original {
            text-decoration: line-through;
            color: var(--text-muted);
            font-size: 12px;
            margin-right: 6px;
        }

        .producto-descuento-badge {
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            font-size: 9px;
            padding: 2px 5px;
            border-radius: 20px;
            margin-left: 6px;
        }

        .producto-tipo-badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 4px;
        }

        .producto-tipo-badge.inmediato {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .producto-tipo-badge.encargo {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        /* Selector de estado */
        .estado-select {
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid var(--border);
            background: var(--primary-dark);
            color: white;
            font-size: 13px;
            cursor: pointer;
        }

        /* Total */
        .total-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .total-label {
            font-size: 16px;
            color: var(--text-secondary);
        }

        .total-amount {
            font-size: 28px;
            font-weight: 800;
            color: white;
        }

        /* Historial */
        .historial-item {
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .historial-item:last-child {
            border-bottom: none;
        }

        .historial-estado {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .historial-fecha {
            font-size: 11px;
            color: var(--text-muted);
        }

        .historial-usuario {
            font-size: 11px;
            color: var(--text-secondary);
        }

        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--card);
            padding: 30px;
            border-radius: 20px;
            max-width: 450px;
            width: 90%;
            border: 1px solid var(--border);
        }

        .modal-content h3 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .modal-content input, .modal-content textarea, .modal-content select {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--primary-dark);
            color: white;
            margin-bottom: 15px;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-modal-guardar, .btn-modal-cancelar {
            padding: 10px 20px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-modal-guardar {
            background: var(--accent);
            color: white;
        }

        .btn-modal-cancelar {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        /* Acciones */
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-wa, .btn-imprimir {
            padding: 12px 24px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-wa {
            background: var(--whatsapp);
            color: white;
        }

        .btn-wa:hover {
            background: var(--whatsapp-dark);
            transform: translateY(-2px);
        }

        .btn-imprimir {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid var(--border);
            cursor: pointer;
        }

        .btn-imprimir:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }

        /* MOBILE */
        .menu-toggle {
            display: none;
            position: fixed; top: 15px; left: 15px;
            width: 45px; height: 45px;
            background: var(--accent);
            color: white; border: none; border-radius: 10px;
            z-index: 1100;
            cursor: pointer;
        }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 999; display: none;
        }

        /* ===== ESTILOS PARA IMPRESIÓN ===== */
        @media print {
            /* Ocultar elementos no deseados */
            .sidebar, .menu-toggle, .overlay, .btn-back, .btn-edit, 
            .estado-select, .actions, .card-header button, .modal, .header {
                display: none !important;
            }
            
            /* Ajustar márgenes y fondo */
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .container {
                max-width: 100%;
                padding: 20px;
                margin: 0;
            }
            
            /* Mostrar logo en impresión */
            .print-logo {
                display: block !important;
            }
            
            /* Tarjetas en impresión */
            .card {
                background: white;
                border: 1px solid #ddd;
                box-shadow: none;
                page-break-inside: avoid;
            }
            
            .card-header {
                background: #f5f5f5;
                border-bottom: 1px solid #ddd;
            }
            
            .card-header-left h2 {
                color: #333 !important;
            }
            
            .card-body {
                padding: 15px;
            }
            
            /* Colores de texto para impresión */
            .estado-badge, .info-row span, .producto-nombre, 
            .producto-precio, .total-label, .total-amount {
                color: #333 !important;
            }
            
            .total-card {
                background: #f5f5f5;
                border: 1px solid #ddd;
            }
            
            .productos-table th {
                background: #f5f5f5;
                color: #333;
            }
            
            .productos-table td {
                color: #333;
            }
            
            .info-row i, .card-header-left i {
                color: #666 !important;
            }
            
            /* Asegurar que los colores se impriman */
            * {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1000;
            }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; padding-top: 75px; }
            .menu-toggle { display: flex; align-items: center; justify-content: center; }
            .overlay.active { display: block; }
            
            .container { padding: 0; }
            .header { flex-direction: column; align-items: flex-start; }
            .productos-table th:nth-child(3), .productos-table td:nth-child(3) { display: none; }
            .card-body { overflow-x: auto; }
            .actions { flex-direction: column; }
            .btn-wa, .btn-imprimir { justify-content: center; }
        }
        
    </style>
</head>
<body>

    <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
    <div class="overlay" id="overlay"></div>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h2>MS ADMIN</h2>
        </div>
        <nav>
            <div class="nav-section">
                <div class="nav-section-title">PRINCIPAL</div>
                <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i> Dashboard</a>
                <a href="agregar_producto.php" class="nav-item"><i class="fa-solid fa-circle-plus"></i> Nuevo Producto</a>
                <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-cart-shopping"></i> Pedidos</a>
                <a href="clientes.php" class="nav-item"><i class="fa-solid fa-user-group"></i> Clientes</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">CATÁLOGO</div>
                <a href="categorias.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Categorías</a>
                <a href="tallas.php" class="nav-item"><i class="fa-solid fa-ruler-combined"></i> Tallas</a>
                <a href="colores.php" class="nav-item"><i class="fa-solid fa-droplet"></i> Colores</a>
                <a href="destacar.php" class="nav-item"><i class="fa-solid fa-star"></i> Destacados</a>
                <a href="badges.php" class="nav-item"><i class="fa-solid fa-medal"></i> Badges</a>
                <a href="mas_vistos.php" class="nav-item"><i class="fa-solid fa-chart-line"></i> Más vistos</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">PROMOCIONES & FINANZAS</div>
                <a href="descuentos.php" class="nav-item"><i class="fa-solid fa-tag"></i> Descuentos</a>
                <a href="finanzas.php" class="nav-item"><i class="fa-solid fa-wallet"></i> Finanzas</a>
                <a href="estadisticas.php" class="nav-item"><i class="fa-solid fa-chart-simple"></i> Estadísticas</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">CONFIGURACIÓN</div>
                <a href="configuracion.php" class="nav-item"><i class="fa-solid fa-gear"></i> Configuración</a>
                <a href="logout.php" class="nav-item logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
            </div>
        </nav>
    </div>

    <div class="main-content">
        <div class="container">
            <!-- Logo para impresión -->
            <div class="print-logo">
                <img src="../img/logo.png" alt="Tienda MS">
                <h2><?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></h2>
                <p><?php echo $config['tienda_direccion'] ?? 'Sahagún, Córdoba'; ?> | Tel: <?php echo $config['tienda_telefono'] ?? '300 123 4567'; ?></p>
            </div>

            <!-- Header normal -->
            <div class="header">
                <h1>
                    <i class="fa-solid fa-receipt"></i>
                    Pedido #<?php echo $pedido_data['id']; ?>
                </h1>
                <a href="pedidos.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </div>

            <?php if (isset($_GET['mensaje'])): ?>
                <div class="success-message">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php if ($_GET['mensaje'] == 'cliente_actualizado'): ?>
                        Información del cliente actualizada
                    <?php elseif ($_GET['mensaje'] == 'notas_guardadas'): ?>
                        Notas guardadas correctamente
                    <?php elseif ($_GET['mensaje'] == 'anticipo_actualizado'): ?>
                        Anticipo actualizado
                    <?php elseif ($_GET['mensaje'] == 'guia_guardada'): ?>
                        Guía de envío guardada
                    <?php elseif ($_GET['mensaje'] == 'estado_actualizado'): ?>
                        Estado actualizado
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Estado y fecha -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-truck"></i>
                        <h2>Estado del pedido</h2>
                    </div>
                    <select onchange="cambiarEstado(this.value)" class="estado-select">
                        <option value="pendiente" <?php echo $pedido_data['estado'] == 'pendiente' ? 'selected' : ''; ?>>⏳ Pendiente</option>
                        <option value="confirmado" <?php echo $pedido_data['estado'] == 'confirmado' ? 'selected' : ''; ?>>✅ Confirmado</option>
                        <option value="enviado" <?php echo $pedido_data['estado'] == 'enviado' ? 'selected' : ''; ?>>🚚 Enviado</option>
                        <option value="entregado" <?php echo $pedido_data['estado'] == 'entregado' ? 'selected' : ''; ?>>🎉 Entregado</option>
                        <option value="cancelado" <?php echo $pedido_data['estado'] == 'cancelado' ? 'selected' : ''; ?>>❌ Cancelado</option>
                    </select>
                </div>
                <div class="card-body">
                    <div class="estado-badge">
                        <?php echo $estados[$pedido_data['estado']]; ?>
                    </div>
                    <p style="margin-top: 12px; color: var(--text-secondary); font-size: 13px;">
                        <i class="fa-regular fa-calendar"></i> Fecha: <?php echo date('d/m/Y H:i', strtotime($pedido_data['fecha_pedido'])); ?>
                    </p>
                </div>
            </div>

            <!-- Información del cliente -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-user"></i>
                        <h2>Información del cliente</h2>
                    </div>
                    <button class="btn-edit" onclick="abrirModalCliente()">
                        <i class="fa-solid fa-pen"></i> Editar
                    </button>
                </div>
                <div class="card-body">
                    <div class="cliente-info">
                        <div class="info-row">
                            <i class="fa-solid fa-user"></i>
                            <strong>Nombre:</strong>
                            <span id="cliente_nombre"><?php echo htmlspecialchars($pedido_data['cliente_nombre']); ?></span>
                        </div>
                        <div class="info-row">
                            <i class="fa-solid fa-phone"></i>
                            <strong>Teléfono:</strong>
                            <span id="cliente_telefono"><?php echo htmlspecialchars($pedido_data['telefono'] ?: 'No registrado'); ?></span>
                        </div>
                        <div class="info-row">
                            <i class="fa-solid fa-location-dot"></i>
                            <strong>Dirección:</strong>
                            <span id="cliente_direccion"><?php echo htmlspecialchars($pedido_data['direccion'] ?: 'No registrada'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Anticipo y guía -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-money-bill"></i>
                        <h2>Anticipo y envío</h2>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <p style="color: var(--text-secondary); margin-bottom: 8px;">💰 Anticipo (50%)</p>
                            <?php $anticipo = $pedido_data['anticipo'] ?? 0; ?>
                            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                <span class="anticipo-badge <?php echo $anticipo == 1 ? 'anticipo-pagado' : 'anticipo-pendiente'; ?>">
                                    <?php echo $anticipo == 1 ? '✅ Pagado' : '⏳ Pendiente'; ?>
                                </span>
                                <button class="btn-edit" onclick="abrirModalAnticipo()">
                                    <i class="fa-solid fa-pen"></i> Cambiar
                                </button>
                            </div>
                        </div>
                        <div>
                            <p style="color: var(--text-secondary); margin-bottom: 8px;">📦 Guía de envío</p>
                            <div>
                                <span><?php echo htmlspecialchars($pedido_data['numero_guia'] ?? 'No registrada'); ?></span>
                                <?php if (!empty($pedido_data['transportadora'])): ?>
                                    <span style="color: var(--text-muted);"> (<?php echo $pedido_data['transportadora']; ?>)</span>
                                <?php endif; ?>
                                <button class="btn-edit" style="margin-left: 10px;" onclick="abrirModalGuia()">
                                    <i class="fa-solid fa-pen"></i> Editar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notas del pedido -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-pen"></i>
                        <h2>Notas del pedido</h2>
                    </div>
                    <button class="btn-edit" onclick="abrirModalNotas()">
                        <i class="fa-solid fa-pen"></i> Editar
                    </button>
                </div>
                <div class="card-body">
                    <p style="color: var(--text-secondary); line-height: 1.5;">
                        <?php echo nl2br(htmlspecialchars($pedido_data['notas_admin'] ?? 'Sin notas')); ?>
                    </p>
                </div>
            </div>

            <!-- Productos -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-box"></i>
                        <h2>Productos</h2>
                    </div>
                </div>
                <div class="card-body" style="overflow-x: auto;">
                    <table class="productos-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($prod = $productos->fetch_assoc()): 
                                $subtotal = $prod['precio_unitario'] * $prod['cantidad'];
                                $tiene_descuento = ($prod['tenia_descuento'] && $prod['precio_original'] > $prod['precio_unitario']);
                            ?>
                             <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <a href="../producto.php?id=<?php echo $prod['producto_id']; ?>" target="_blank" class="producto-imagen">
                                            <?php if ($prod['imagen'] && file_exists("../img/".$prod['imagen'])): ?>
                                                <img src="../img/<?php echo $prod['imagen']; ?>" alt="">
                                            <?php else: ?>
                                                <i class="fa-solid fa-shirt"></i>
                                            <?php endif; ?>
                                        </a>
                                        <div>
                                            <a href="../producto.php?id=<?php echo $prod['producto_id']; ?>" target="_blank" class="producto-nombre">
                                                <?php echo htmlspecialchars($prod['nombre']); ?>
                                            </a>
                                            <div class="producto-tipo-badge <?php echo isset($prod['tipo_pedido']) && $prod['tipo_pedido'] == 'inmediato' ? 'inmediato' : 'encargo'; ?>">
                                                <?php echo isset($prod['tipo_pedido']) && $prod['tipo_pedido'] == 'inmediato' ? '🚚 Entrega Inmediata' : '📦 Por Encargo'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($tiene_descuento): ?>
                                        <span class="producto-precio-original">$<?php echo number_format($prod['precio_original'], 0); ?></span>
                                        <span class="producto-precio">$<?php echo number_format($prod['precio_unitario'], 0); ?></span>
                                        <span class="producto-descuento-badge">-<?php echo $prod['valor_desc']; ?>%</span>
                                    <?php else: ?>
                                        <span class="producto-precio">$<?php echo number_format($prod['precio_unitario'], 0); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $prod['cantidad']; ?></td>
                                <td class="producto-precio">$<?php echo number_format($subtotal, 0); ?></td>
                             </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Total -->
            <div class="total-card">
                <span class="total-label">TOTAL DEL PEDIDO</span>
                <span class="total-amount">$<?php echo number_format($total_general, 0); ?></span>
            </div>

            <!-- Historial de cambios -->
            <?php if ($historial && $historial->num_rows > 0): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <h2>Historial de cambios</h2>
                    </div>
                </div>
                <div class="card-body">
                    <?php while($h = $historial->fetch_assoc()): ?>
                    <div class="historial-item">
                        <span class="historial-estado" style="background: <?php echo $estado_colores[$h['estado_nuevo']]; ?>20; color: <?php echo $estado_colores[$h['estado_nuevo']]; ?>;">
                            <?php echo $estados[$h['estado_nuevo']]; ?>
                        </span>
                        <span class="historial-fecha"><i class="fa-regular fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?></span>
                        <span class="historial-usuario"><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($h['usuario']); ?></span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Acciones -->
            <div class="actions">
                <a href="https://wa.me/<?php echo $pedido_data['telefono']; ?>?text=Hola%20<?php echo urlencode($pedido_data['cliente_nombre']); ?>%2C%20soy%20de%20Tienda%20MS.%20Te%20escribo%20para%20confirmar%20tu%20pedido%20por%20un%20total%20de%20%24<?php echo number_format($total_general, 0); ?>." 
                   target="_blank" class="btn-wa">
                    <i class="fa-brands fa-whatsapp"></i> Contactar
                </a>
                <button onclick="imprimirPedido()" class="btn-imprimir">
                    <i class="fa-solid fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    </div>

    <!-- MODALES -->
    <div id="modalCliente" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Editar información del cliente</h3>
            <form method="POST">
                <input type="hidden" name="cliente_id" value="<?php echo $pedido_data['cliente_id']; ?>">
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($pedido_data['cliente_nombre']); ?>" placeholder="Nombre" required>
                <input type="tel" name="telefono" value="<?php echo htmlspecialchars($pedido_data['telefono']); ?>" placeholder="Teléfono">
                <input type="text" name="direccion" value="<?php echo htmlspecialchars($pedido_data['direccion']); ?>" placeholder="Dirección">
                <div class="modal-buttons">
                    <button type="button" class="btn-modal-cancelar" onclick="cerrarModal('modalCliente')">Cancelar</button>
                    <button type="submit" name="editar_cliente" class="btn-modal-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalNotas" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Editar notas del pedido</h3>
            <form method="POST">
                <textarea name="notas_pedido" rows="4" placeholder="Notas internas (solo visible para administradores)"><?php echo htmlspecialchars($pedido_data['notas_admin'] ?? ''); ?></textarea>
                <div class="modal-buttons">
                    <button type="button" class="btn-modal-cancelar" onclick="cerrarModal('modalNotas')">Cancelar</button>
                    <button type="submit" name="guardar_notas" class="btn-modal-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalAnticipo" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Estado del anticipo (50%)</h3>
            <form method="POST">
                <select name="anticipo">
                    <option value="0" <?php echo ($pedido_data['anticipo'] ?? 0) == 0 ? 'selected' : ''; ?>>⏳ Pendiente</option>
                    <option value="1" <?php echo ($pedido_data['anticipo'] ?? 0) == 1 ? 'selected' : ''; ?>>✅ Pagado</option>
                </select>
                <div class="modal-buttons">
                    <button type="button" class="btn-modal-cancelar" onclick="cerrarModal('modalAnticipo')">Cancelar</button>
                    <button type="submit" name="actualizar_anticipo" class="btn-modal-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalGuia" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Información de envío</h3>
            <form method="POST">
                <input type="text" name="numero_guia" value="<?php echo htmlspecialchars($pedido_data['numero_guia'] ?? ''); ?>" placeholder="Número de guía">
                <input type="text" name="transportadora" value="<?php echo htmlspecialchars($pedido_data['transportadora'] ?? ''); ?>" placeholder="Transportadora">
                <div class="modal-buttons">
                    <button type="button" class="btn-modal-cancelar" onclick="cerrarModal('modalGuia')">Cancelar</button>
                    <button type="submit" name="guardar_guia" class="btn-modal-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function cambiarEstado(estado) {
            if (confirm('¿Cambiar el estado del pedido a ' + estado.toUpperCase() + '?')) {
                window.location.href = 'detalle_pedido.php?id=<?php echo $id; ?>&cambiar_estado=1&estado=' + estado;
            }
        }

        function abrirModalCliente() { document.getElementById('modalCliente').style.display = 'flex'; }
        function abrirModalNotas() { document.getElementById('modalNotas').style.display = 'flex'; }
        function abrirModalAnticipo() { document.getElementById('modalAnticipo').style.display = 'flex'; }
        function abrirModalGuia() { document.getElementById('modalGuia').style.display = 'flex'; }

        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function imprimirPedido() {
            // Mostrar el logo antes de imprimir
            const logoImpresion = document.querySelector('.print-logo');
            if (logoImpresion) {
                logoImpresion.style.display = 'block';
            }
            
            // Imprimir
            window.print();
            
            // Ocultar el logo después de imprimir
            setTimeout(() => {
                if (logoImpresion) {
                    logoImpresion.style.display = 'none';
                }
            }, 1000);
        }

        // Menú móvil
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleMenu() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        if (menuToggle) menuToggle.onclick = toggleMenu;
        if (overlay) overlay.onclick = toggleMenu;

        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) toggleMenu();
            });
        });

        window.onclick = function(event) {
            const modals = ['modalCliente', 'modalNotas', 'modalAnticipo', 'modalGuia'];
            modals.forEach(id => {
                const modal = document.getElementById(id);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>