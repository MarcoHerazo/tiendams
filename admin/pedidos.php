<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Procesar cambios de estado
if (isset($_GET['cambiar_estado'])) {
    $id = intval($_GET['cambiar_estado']);
    $estado = $_GET['estado'];
    
    $stmt = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
    $stmt->bind_param("si", $estado, $id);
    $stmt->execute();
    
    header("Location: pedidos.php?mensaje=estado_actualizado");
    exit;
}

// Procesar actualización de teléfono
if (isset($_POST['editar_telefono'])) {
    $cliente_id = intval($_POST['cliente_id']);
    $telefono = $_POST['telefono'];
    
    $stmt = $conn->prepare("UPDATE clientes SET telefono = ? WHERE id = ?");
    $stmt->bind_param("si", $telefono, $cliente_id);
    $stmt->execute();
    
    header("Location: pedidos.php?mensaje=telefono_actualizado");
    exit;
}

// Procesar eliminación de pedido
if (isset($_GET['eliminar_pedido'])) {
    $id = intval($_GET['eliminar_pedido']);
    
    // Primero eliminar los detalles del pedido
    $conn->query("DELETE FROM detalle_pedidos WHERE pedido_id = $id");
    // Luego eliminar el pedido
    $conn->query("DELETE FROM pedidos WHERE id = $id");
    
    header("Location: pedidos.php?mensaje=pedido_eliminado");
    exit;
}

// Obtener filtro de estado y búsqueda
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

$where = "";

// Construir WHERE según filtros
if ($filtro_estado == 'todos') {
    $where = "WHERE p.estado != 'cancelado'";
} elseif ($filtro_estado != 'todos') {
    $where = "WHERE p.estado = '$filtro_estado'";
}

// Agregar búsqueda por nombre o teléfono
if (!empty($busqueda)) {
    if (empty($where)) {
        $where = "WHERE (c.nombre LIKE '%$busqueda%' OR c.telefono LIKE '%$busqueda%')";
    } else {
        $where .= " AND (c.nombre LIKE '%$busqueda%' OR c.telefono LIKE '%$busqueda%')";
    }
}

// Obtener pedidos
$pedidos = $conn->query("
    SELECT p.*, c.nombre as cliente_nombre, c.telefono, c.direccion, c.id as cliente_id
    FROM pedidos p
    JOIN clientes c ON p.cliente_id = c.id
    $where
    ORDER BY 
        FIELD(p.estado, 'pendiente', 'confirmado', 'enviado', 'entregado'),
        p.fecha_pedido DESC
");

// Estadísticas (incluyendo cancelados para el contador)
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'confirmado' THEN 1 ELSE 0 END) as confirmados,
        SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviados,
        SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) as entregados,
        SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados
    FROM pedidos
")->fetch_assoc();

// Estadísticas para mostrar (total sin cancelados)
$total_activos = $stats['pendientes'] + $stats['confirmados'] + $stats['enviados'] + $stats['entregados'];

// Estados para mostrar
$estados = [
    'pendiente' => '⏳ Pendiente',
    'confirmado' => '✅ Confirmado',
    'enviado' => '🚚 Enviado',
    'entregado' => '🎉 Entregado',
    'cancelado' => '❌ Cancelado'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Pedidos - Tienda MS</title>
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

        .header {
            margin-bottom: 30px;
            background: linear-gradient(to right, var(--card), transparent);
            padding: 20px;
            border-radius: 15px;
            border-left: 4px solid var(--accent);
        }

        .header h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 4px; }
        .header p { color: var(--text-muted); font-size: 0.9rem; }

        /* ===== ESTADÍSTICAS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 14px;
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent);
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            background: rgba(255, 107, 53, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-value { font-size: 1.3rem; font-weight: 800; color: white; line-height: 1.2; }
        .stat-label { font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; }

        .stat-card.total .stat-icon { background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        .stat-card.pendiente .stat-icon { background: rgba(245, 158, 11, 0.1); color: #fbbf24; }
        .stat-card.confirmado .stat-icon { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .stat-card.enviado .stat-icon { background: rgba(139, 92, 246, 0.1); color: #a78bfa; }
        .stat-card.entregado .stat-icon { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .stat-card.cancelado .stat-icon { background: rgba(239, 68, 68, 0.1); color: #f87171; }

        /* ===== BÚSQUEDA Y FILTROS ===== */
        .search-container { margin-bottom: 24px; }
        .search-form {
            display: flex;
            gap: 10px;
            background: var(--card);
            border-radius: 50px;
            padding: 5px;
            border: 1px solid var(--border);
        }
        .search-input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 12px 20px;
            color: white;
            font-size: 14px;
            outline: none;
        }
        .search-input::placeholder { color: var(--text-muted); }
        .search-btn {
            background: var(--accent);
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .search-btn:hover { background: var(--accent-light); transform: scale(1.02); }
        .search-clear {
            background: rgba(255,255,255,0.1);
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            color: var(--text-light);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .search-clear:hover { background: rgba(255,255,255,0.2); color: white; }

        .search-info {
            background: rgba(255, 107, 53, 0.1);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .search-info-text { color: var(--accent); font-size: 13px; }

        .filtros-container {
            background: var(--card);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        .filtros-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; font-weight: 600; color: white; }
        .filtros { display: flex; flex-wrap: wrap; gap: 12px; }
        .filtro-btn {
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: var(--transition);
            background: rgba(255,255,255,0.1);
            color: var(--text-light);
        }
        .filtro-btn.active { background: var(--accent); color: white; }
        .filtro-btn:hover { background: var(--accent); color: white; transform: translateY(-2px); }

        /* ===== PEDIDOS ===== */
        .pedidos-list { display: flex; flex-direction: column; gap: 20px; }
        .pedido-card {
            background: var(--card);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: var(--transition);
        }
        .pedido-card:hover { transform: translateY(-2px); border-color: var(--accent); }

        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border);
        }
        .pedido-info { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .pedido-id { font-weight: 800; font-size: 16px; color: var(--accent); }
        .pedido-fecha { font-size: 13px; color: var(--text-light); }
        .pedido-estado {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .pedido-estado.pendiente { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .pedido-estado.confirmado { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .pedido-estado.enviado { background: rgba(139, 92, 246, 0.2); color: #a78bfa; border: 1px solid rgba(139, 92, 246, 0.3); }
        .pedido-estado.entregado { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .pedido-estado.cancelado { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }

        .pedido-body {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 20px;
        }
        .cliente-info h4, .productos-info h4 {
            font-size: 13px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        .cliente-nombre { font-weight: 700; margin-bottom: 6px; color: white; }
        .cliente-telefono { font-size: 13px; color: var(--text-light); margin-top: 6px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .cliente-telefono i { color: var(--accent); }
        .btn-editar-telefono {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--border);
            color: var(--text-light);
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-editar-telefono:hover { background: var(--accent); color: white; border-color: var(--accent); }
        .cliente-direccion { font-size: 13px; color: var(--text-light); margin-top: 6px; }

        .productos-lista { display: flex; flex-direction: column; gap: 12px; }
        .producto-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            background: rgba(0,0,0,0.2);
            padding: 10px;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .producto-item:hover { background: rgba(255, 107, 53, 0.1); transform: translateX(3px); }
        .producto-imagen {
            width: 50px;
            height: 50px;
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .producto-imagen img { width: 100%; height: 100%; object-fit: cover; }
        .producto-discount-badge {
            position: absolute;
            top: 3px;
            left: 3px;
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 20px;
        }
        .producto-detalle { flex: 1; }
        .producto-nombre {
            font-size: 14px;
            font-weight: 600;
            color: white;
            text-decoration: none;
        }
        .producto-nombre:hover { color: var(--accent); }
        .producto-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .producto-cantidad { font-size: 12px; color: var(--text-light); }
        .producto-precio-original { text-decoration: line-through; color: #64748b; font-size: 12px; }
        .producto-precio { font-weight: bold; color: var(--accent); font-size: 13px; }

        .pedido-total {
            text-align: right;
            padding: 16px 20px;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid var(--border);
        }
        .pedido-total strong { font-size: 20px; color: var(--accent); margin-left: 12px; }

        .pedido-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .estado-select {
            padding: 8px 16px;
            border-radius: 30px;
            border: 1px solid var(--border);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            background: rgba(0,0,0,0.5);
            color: white;
        }
        .pedido-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-whatsapp, .btn-ver, .btn-eliminar {
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-whatsapp { background: var(--whatsapp); color: white; }
        .btn-whatsapp:hover { background: var(--whatsapp-dark); transform: translateY(-2px); }
        .btn-ver { background: rgba(255,255,255,0.1); color: white; border: 1px solid var(--border); }
        .btn-ver:hover { background: var(--accent); transform: translateY(-2px); border-color: var(--accent); }
        .btn-eliminar { background: #ef4444; color: white; }
        .btn-eliminar:hover { background: #dc2626; transform: translateY(-2px); }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card);
            border-radius: 20px;
            border: 1px solid var(--border);
        }
        .empty-state i { font-size: 60px; color: var(--text-muted); margin-bottom: 20px; }
        .empty-state h3 { color: white; margin-bottom: 8px; }

        /* Modal teléfono */
        .modal-telefono {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        .modal-telefono-contenido {
            background: var(--card);
            padding: 30px;
            border-radius: 20px;
            max-width: 400px;
            width: 90%;
            border: 1px solid var(--border);
        }
        .modal-telefono-contenido h3 { color: white; margin-bottom: 20px; font-size: 20px; }
        .modal-telefono-contenido input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: var(--primary-dark);
            color: white;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .modal-buttons { display: flex; gap: 12px; justify-content: flex-end; }
        .btn-guardar, .btn-cancelar {
            padding: 10px 20px;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-guardar { background: var(--accent); color: white; }
        .btn-cancelar { background: rgba(255,255,255,0.1); color: white; border: 1px solid var(--border); }

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

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 12px; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; padding-top: 75px; }
            .menu-toggle { display: flex; align-items: center; justify-content: center; }
            .overlay.active { display: block; }
            
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stat-card { padding: 12px; flex-direction: column; text-align: center; gap: 8px; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; margin: 0 auto; }
            .stat-value { font-size: 1.2rem; }
            
            .pedido-body { grid-template-columns: 1fr; gap: 16px; }
            .pedido-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .pedido-footer { flex-direction: column; }
            .pedido-actions { width: 100%; justify-content: center; }
            .estado-select { width: 100%; }
            
            .search-form { flex-wrap: wrap; }
            .search-btn, .search-clear { flex: 1; justify-content: center; }
            .filtros { justify-content: center; }
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
                <a href="pedidos.php" class="nav-item active"><i class="fa-solid fa-cart-shopping"></i> Pedidos</a>
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
        <div class="header">
            <h1><i class="fa-solid fa-truck"></i> Pedidos</h1>
            <p>Gestiona los pedidos de tus clientes</p>
        </div>

        <?php if (isset($_GET['mensaje'])): ?>
            <div class="success-message" style="background: rgba(16,185,129,0.1); color: #34d399; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(16,185,129,0.3); display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check"></i>
                <?php if ($_GET['mensaje'] == 'estado_actualizado'): ?>Estado del pedido actualizado correctamente
                <?php elseif ($_GET['mensaje'] == 'telefono_actualizado'): ?>Teléfono del cliente actualizado correctamente
                <?php elseif ($_GET['mensaje'] == 'pedido_eliminado'): ?>Pedido eliminado correctamente
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Búsqueda -->
        <div class="search-container">
            <form method="GET" action="pedidos.php" class="search-form">
                <input type="text" name="buscar" class="search-input" placeholder="🔍 Buscar por nombre o teléfono..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <button type="submit" class="search-btn"><i class="fa-solid fa-search"></i> Buscar</button>
                <?php if (!empty($busqueda)): ?>
                    <a href="pedidos.php" class="search-clear"><i class="fa-solid fa-times"></i> Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($busqueda)): ?>
            <div class="search-info">
                <div class="search-info-text"><i class="fa-solid fa-magnifying-glass"></i> Resultados para: <strong>"<?php echo htmlspecialchars($busqueda); ?>"</strong></div>
                <div class="search-info-text"><i class="fa-solid fa-box"></i> <?php echo $pedidos->num_rows; ?> pedido(s) encontrado(s)</div>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card total"><div class="stat-icon"><i class="fa-solid fa-box"></i></div><div><span class="stat-value"><?php echo $total_activos; ?></span><span class="stat-label">Activos</span></div></div>
            <div class="stat-card pendiente"><div class="stat-icon"><i class="fa-solid fa-clock"></i></div><div><span class="stat-value"><?php echo $stats['pendientes']; ?></span><span class="stat-label">Pendientes</span></div></div>
            <div class="stat-card confirmado"><div class="stat-icon"><i class="fa-solid fa-check-circle"></i></div><div><span class="stat-value"><?php echo $stats['confirmados']; ?></span><span class="stat-label">Confirmados</span></div></div>
            <div class="stat-card enviado"><div class="stat-icon"><i class="fa-solid fa-truck"></i></div><div><span class="stat-value"><?php echo $stats['enviados']; ?></span><span class="stat-label">Enviados</span></div></div>
            <div class="stat-card entregado"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><span class="stat-value"><?php echo $stats['entregados']; ?></span><span class="stat-label">Entregados</span></div></div>
            <div class="stat-card cancelado"><div class="stat-icon"><i class="fa-solid fa-ban"></i></div><div><span class="stat-value"><?php echo $stats['cancelados']; ?></span><span class="stat-label">Cancelados</span></div></div>
        </div>

        <!-- Filtros -->
        <div class="filtros-container">
            <div class="filtros-header"><i class="fa-solid fa-filter"></i> <span>Filtrar por estado:</span></div>
            <div class="filtros">
                <a href="pedidos.php<?php echo !empty($busqueda) ? '?buscar='.urlencode($busqueda) : ''; ?>" class="filtro-btn <?php echo $filtro_estado == 'todos' ? 'active' : ''; ?>">Activos</a>
                <a href="pedidos.php?estado=pendiente<?php echo !empty($busqueda) ? '&buscar='.urlencode($busqueda) : ''; ?>" class="filtro-btn pendiente <?php echo $filtro_estado == 'pendiente' ? 'active' : ''; ?>"><i class="fa-solid fa-clock"></i> Pendientes</a>
                <a href="pedidos.php?estado=confirmado<?php echo !empty($busqueda) ? '&buscar='.urlencode($busqueda) : ''; ?>" class="filtro-btn confirmado <?php echo $filtro_estado == 'confirmado' ? 'active' : ''; ?>"><i class="fa-solid fa-check-circle"></i> Confirmados</a>
                <a href="pedidos.php?estado=enviado<?php echo !empty($busqueda) ? '&buscar='.urlencode($busqueda) : ''; ?>" class="filtro-btn enviado <?php echo $filtro_estado == 'enviado' ? 'active' : ''; ?>"><i class="fa-solid fa-truck"></i> Enviados</a>
                <a href="pedidos.php?estado=entregado<?php echo !empty($busqueda) ? '&buscar='.urlencode($busqueda) : ''; ?>" class="filtro-btn entregado <?php echo $filtro_estado == 'entregado' ? 'active' : ''; ?>"><i class="fa-solid fa-circle-check"></i> Entregados</a>
                <a href="pedidos.php?estado=cancelado<?php echo !empty($busqueda) ? '&buscar='.urlencode($busqueda) : ''; ?>" class="filtro-btn cancelado <?php echo $filtro_estado == 'cancelado' ? 'active' : ''; ?>"><i class="fa-solid fa-ban"></i> Cancelados</a>
            </div>
        </div>

        <!-- Lista de pedidos -->
        <div class="pedidos-list">
            <?php if ($pedidos->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <h3>No hay pedidos</h3>
                    <p>Los pedidos que lleguen aparecerán aquí</p>
                </div>
            <?php else: ?>
                <?php while($pedido = $pedidos->fetch_assoc()): 
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
                        WHERE dp.pedido_id = " . $pedido['id']
                    );
                ?>
                    <div class="pedido-card">
                        <div class="pedido-header">
                            <div class="pedido-info">
                                <span class="pedido-id">#<?php echo $pedido['id']; ?></span>
                                <span class="pedido-fecha"><i class="fa-regular fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></span>
                            </div>
                            <div class="pedido-estado <?php echo $pedido['estado']; ?>"><?php echo $estados[$pedido['estado']]; ?></div>
                        </div>

                        <div class="pedido-body">
                            <div class="cliente-info">
                                <h4><i class="fa-solid fa-user"></i> Cliente</h4>
                                <p class="cliente-nombre"><strong><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></strong></p>
                                <div class="cliente-telefono">
                                    <i class="fa-solid fa-phone"></i> 
                                    <span id="telefono-<?php echo $pedido['cliente_id']; ?>"><?php echo htmlspecialchars($pedido['telefono'] ?: 'No registrado'); ?></span>
                                    <button class="btn-editar-telefono" onclick="abrirModalEditarTelefono(<?php echo $pedido['cliente_id']; ?>, '<?php echo htmlspecialchars($pedido['cliente_nombre']); ?>', '<?php echo htmlspecialchars($pedido['telefono']); ?>')">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </button>
                                </div>
                                <?php if ($pedido['direccion']): ?>
                                    <p class="cliente-direccion"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($pedido['direccion']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="productos-info">
                                <h4><i class="fa-solid fa-box"></i> Productos</h4>
                                <div class="productos-lista">
                                    <?php while($prod = $productos->fetch_assoc()): ?>
                                        <div class="producto-item">
                                            <div class="producto-imagen" style="position: relative;">
                                                <a href="../producto.php?id=<?php echo $prod['producto_id']; ?>" target="_blank">
                                                    <?php if ($prod['imagen'] && file_exists("../img/".$prod['imagen'])): ?>
                                                        <img src="../img/<?php echo $prod['imagen']; ?>" alt="<?php echo htmlspecialchars($prod['nombre']); ?>">
                                                    <?php else: ?>
                                                        <i class="fa-solid fa-shirt"></i>
                                                    <?php endif; ?>
                                                </a>
                                                <?php if ($prod['tenia_descuento']): ?>
                                                    <span class="producto-discount-badge">
                                                        <?php if ($prod['tipo_desc'] == 'porcentaje'): ?>-<?php echo $prod['valor_desc']; ?>%
                                                        <?php else: ?>-$<?php echo number_format($prod['valor_desc'], 0); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="producto-detalle">
                                                <a href="../producto.php?id=<?php echo $prod['producto_id']; ?>" target="_blank" class="producto-nombre"><?php echo htmlspecialchars($prod['nombre']); ?></a>
                                                <div class="producto-meta">
                                                    <span class="producto-cantidad">x<?php echo $prod['cantidad']; ?></span>
                                                    <?php if ($prod['tenia_descuento'] && $prod['precio_original'] > $prod['precio_unitario']): ?>
                                                        <span class="producto-precio-original">$<?php echo number_format($prod['precio_original'], 0); ?></span>
                                                        <span class="producto-precio">$<?php echo number_format($prod['precio_unitario'], 0); ?></span>
                                                    <?php else: ?>
                                                        <span class="producto-precio">$<?php echo number_format($prod['precio_unitario'], 0); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <div class="pedido-total">
                                <span>Total:</span> <strong>$<?php echo number_format($pedido['total'], 0); ?></strong>
                            </div>
                        </div>

                        <div class="pedido-footer">
                            <select onchange="cambiarEstado(<?php echo $pedido['id']; ?>, this.value)" class="estado-select <?php echo $pedido['estado']; ?>">
                                <option value="pendiente" <?php echo $pedido['estado'] == 'pendiente' ? 'selected' : ''; ?>>⏳ Pendiente</option>
                                <option value="confirmado" <?php echo $pedido['estado'] == 'confirmado' ? 'selected' : ''; ?>>✅ Confirmado</option>
                                <option value="enviado" <?php echo $pedido['estado'] == 'enviado' ? 'selected' : ''; ?>>🚚 Enviado</option>
                                <option value="entregado" <?php echo $pedido['estado'] == 'entregado' ? 'selected' : ''; ?>>🎉 Entregado</option>
                                <option value="cancelado" <?php echo $pedido['estado'] == 'cancelado' ? 'selected' : ''; ?>>❌ Cancelado</option>
                            </select>
                            
                            <div class="pedido-actions">
<a href="https://wa.me/<?php echo $pedido['telefono']; ?>?text=Hola%20<?php echo urlencode($pedido['cliente_nombre']); ?>%2C%20<?php 
    if ($pedido['estado'] == 'pendiente') {
        echo 'tu%20pedido%20está%20pendiente%20de%20confirmación.%20Pronto%20te%20contactaremos.';
    } elseif ($pedido['estado'] == 'confirmado') {
        echo 'tu%20pedido%20ha%20sido%20confirmado.%20Lo%20estamos%20preparando.';
    } elseif ($pedido['estado'] == 'enviado') {
        echo 'tu%20pedido%20ha%20sido%20enviado.%20Pronto%20recibirás%20tu%20guía%20de%20seguimiento.';
    } elseif ($pedido['estado'] == 'entregado') {
        echo 'tu%20pedido%20ya%20fue%20entregado.%20¡Gracias%20por%20comprar%20en%20Tienda%20MS!';
    } else {
        echo 'te%20contactamos%20para%20información%20de%20tu%20pedido.';
    }
?>" target="_blank" class="btn-whatsapp"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>                                <button onclick="verDetalle(<?php echo $pedido['id']; ?>)" class="btn-ver"><i class="fa-solid fa-eye"></i> Ver detalle</button>
                                <?php if ($pedido['estado'] == 'cancelado'): ?>
                                    <button onclick="eliminarPedido(<?php echo $pedido['id']; ?>)" class="btn-eliminar"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para editar teléfono -->
    <div id="modalEditarTelefono" class="modal-telefono" style="display: none;">
        <div class="modal-telefono-contenido">
            <h3><i class="fa-solid fa-phone"></i> Editar teléfono</h3>
            <form method="POST" action="pedidos.php">
                <input type="hidden" name="cliente_id" id="edit_cliente_id">
                <p style="color: var(--text-light); margin-bottom: 10px;">Cliente: <strong id="edit_cliente_nombre"></strong></p>
                <input type="tel" name="telefono" id="edit_telefono" placeholder="Ej: 3012345678" required>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancelar" onclick="cerrarModalTelefono()">Cancelar</button>
                    <button type="submit" name="editar_telefono" class="btn-guardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function cambiarEstado(pedidoId, nuevoEstado) {
            if (confirm('¿Cambiar el estado del pedido?')) {
                window.location.href = 'pedidos.php?cambiar_estado=' + pedidoId + '&estado=' + nuevoEstado;
            }
        }

        function verDetalle(pedidoId) {
            window.location.href = 'detalle_pedido.php?id=' + pedidoId;
        }

        function eliminarPedido(pedidoId) {
            if (confirm('⚠️ ¿Estás seguro de ELIMINAR este pedido?\n\nEsta acción no se puede deshacer.')) {
                window.location.href = 'pedidos.php?eliminar_pedido=' + pedidoId;
            }
        }

        function abrirModalEditarTelefono(clienteId, clienteNombre, telefonoActual) {
            document.getElementById('edit_cliente_id').value = clienteId;
            document.getElementById('edit_cliente_nombre').innerText = clienteNombre;
            document.getElementById('edit_telefono').value = telefonoActual || '';
            document.getElementById('modalEditarTelefono').style.display = 'flex';
        }

        function cerrarModalTelefono() {
            document.getElementById('modalEditarTelefono').style.display = 'none';
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
            const modal = document.getElementById('modalEditarTelefono');
            if (event.target == modal) {
                cerrarModalTelefono();
            }
        }
    </script>
</body>
</html>