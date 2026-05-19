<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Procesar acciones
$mensaje = '';
$error = '';

// Agregar cliente manualmente
if (isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $notas = trim($_POST['notas']);
    
    if (!empty($nombre) && !empty($telefono)) {
        $stmt = $conn->prepare("INSERT INTO clientes (nombre, telefono, direccion, notas) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre, $telefono, $direccion, $notas);
        
        if ($stmt->execute()) {
            $mensaje = "Cliente agregado exitosamente";
        } else {
            $error = "Error al agregar el cliente";
        }
    } else {
        $error = "El nombre y teléfono son obligatorios";
    }
}

// Editar cliente
if (isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $notas = trim($_POST['notas']);
    
    if (!empty($nombre) && !empty($telefono)) {
        $stmt = $conn->prepare("UPDATE clientes SET nombre = ?, telefono = ?, direccion = ?, notas = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $nombre, $telefono, $direccion, $notas, $id);
        
        if ($stmt->execute()) {
            $mensaje = "Cliente actualizado exitosamente";
        } else {
            $error = "Error al actualizar el cliente";
        }
    } else {
        $error = "El nombre y teléfono son obligatorios";
    }
}

// Eliminar cliente
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    // Verificar si tiene pedidos
    $check = $conn->query("SELECT COUNT(*) as total FROM pedidos WHERE cliente_id = $id");
    $result = $check->fetch_assoc();
    
    if ($result['total'] == 0) {
        $conn->query("DELETE FROM clientes WHERE id = $id");
        $mensaje = "Cliente eliminado exitosamente";
    } else {
        $error = "No se puede eliminar: el cliente tiene pedidos registrados";
    }
}

// Obtener filtro de búsqueda
$busqueda = isset($_GET['buscar']) ? $_GET['buscar'] : '';

// Obtener clientes
if ($busqueda) {
    $busqueda_param = "%$busqueda%";
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE nombre LIKE ? OR telefono LIKE ? ORDER BY id DESC");
    $stmt->bind_param("ss", $busqueda_param, $busqueda_param);
    $stmt->execute();
    $clientes = $stmt->get_result();
} else {
    $clientes = $conn->query("SELECT * FROM clientes ORDER BY id DESC");
}

// Estadísticas
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_clientes,
        COUNT(DISTINCT c.id) as clientes_con_compras
    FROM clientes c
    LEFT JOIN pedidos p ON c.id = p.cliente_id
")->fetch_assoc();

$total_pedidos = $conn->query("SELECT COUNT(*) as total FROM pedidos")->fetch_assoc()['total'];
$promedio = $stats['clientes_con_compras'] > 0 ? round($total_pedidos / $stats['clientes_con_compras'], 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Clientes - Tienda MS</title>
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
            grid-template-columns: repeat(4, 1fr);
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

        /* ===== BÚSQUEDA ===== */
        .clientes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            display: flex;
            align-items: center;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 5px;
            gap: 8px;
        }

        .search-box i {
            padding-left: 15px;
            color: var(--text-muted);
        }

        .search-box input {
            flex: 1;
            background: transparent;
            border: none;
            padding: 12px 0;
            color: white;
            font-size: 0.9rem;
            outline: none;
        }

        .search-box input::placeholder {
            color: var(--text-muted);
        }

        .search-box button {
            background: var(--accent);
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .search-box button:hover {
            background: var(--accent-light);
            transform: scale(1.02);
        }

        .clear-search {
            background: rgba(255,255,255,0.1);
            border: 1px solid var(--border);
            padding: 10px 20px;
            border-radius: 40px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .clear-search:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        /* ===== LISTA DE CLIENTES ===== */
        .clientes-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }

        .cliente-card {
            background: var(--card);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: var(--transition);
        }

        .cliente-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
        }

        .cliente-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid var(--border);
        }

        .cliente-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: bold;
            color: white;
        }

        .cliente-info {
            flex: 1;
        }

        .cliente-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            margin-bottom: 5px;
        }

        .cliente-contacto {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .contacto-item {
            font-size: 0.75rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .cliente-fecha {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .cliente-body {
            padding: 16px 20px;
        }

        .direccion {
            display: flex;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .direccion .label {
            color: var(--text-muted);
        }

        .stats-pedidos {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .stat-pedido {
            text-align: center;
        }

        .stat-pedido-value {
            display: block;
            font-size: 1.1rem;
            font-weight: 800;
            color: white;
        }

        .stat-pedido-label {
            font-size: 0.6rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .badge-estado {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-estado.pendiente {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }

        .badge-estado.confirmado,
        .badge-estado.entregado {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .notas {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 10px;
        }

        .notas .label {
            color: var(--text-muted);
            display: block;
            margin-bottom: 5px;
        }

        .cliente-footer {
            display: flex;
            gap: 8px;
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            background: rgba(0,0,0,0.2);
            flex-wrap: wrap;
        }

        .btn-whatsapp, .btn-pedidos, .btn-editar, .btn-eliminar {
            padding: 8px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }

        .btn-whatsapp {
            background: var(--whatsapp);
            color: white;
        }

        .btn-whatsapp:hover {
            background: #128C7E;
            transform: translateY(-2px);
        }

        .btn-pedidos {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-pedidos:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-editar {
            background: rgba(255, 107, 53, 0.1);
            color: var(--accent);
            border: 1px solid rgba(255, 107, 53, 0.3);
        }

        .btn-editar:hover {
            background: var(--accent);
            color: white;
        }

        .btn-eliminar {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-eliminar:hover {
            background: var(--danger);
            color: white;
        }

        /* ===== MODAL ===== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(5px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card);
            padding: 30px;
            border-radius: 20px;
            max-width: 450px;
            width: 90%;
            border: 1px solid var(--border);
        }

        .modal-content h2 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .close:hover {
            color: var(--accent);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            background: var(--primary-dark);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 12px 20px;
            border: 1px solid var(--border);
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card);
            border-radius: 20px;
            border: 1px solid var(--border);
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: white;
            margin-bottom: 8px;
        }

        /* ===== MOBILE ===== */
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .clientes-list { grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; z-index: 1000; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; padding-top: 75px; }
            .menu-toggle { display: flex; align-items: center; justify-content: center; }
            .overlay.active { display: block; }
            
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 12px; flex-direction: column; text-align: center; gap: 8px; }
            .stat-icon { width: 40px; height: 40px; font-size: 1.1rem; margin: 0 auto; }
            
            .clientes-header { flex-direction: column; }
            .search-box { width: 100%; }
            .btn-primary { width: 100%; justify-content: center; }
            
            .clientes-list { grid-template-columns: 1fr; }
            .stats-pedidos { grid-template-columns: repeat(2, 1fr); }
            .cliente-footer { justify-content: center; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .cliente-header { flex-direction: column; text-align: center; }
            .stats-pedidos { grid-template-columns: repeat(2, 1fr); }
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
                <a href="clientes.php" class="nav-item active"><i class="fa-solid fa-user-group"></i> Clientes</a>
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
            <h1><i class="fa-solid fa-users"></i> Clientes</h1>
            <p>Gestiona la información de tus clientes</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="success-message" style="background: rgba(16,185,129,0.1); color: #34d399; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(16,185,129,0.3); display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message" style="background: rgba(239,68,68,0.1); color: #f87171; padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.3); display: flex; align-items: center; gap: 10px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                <div>
                    <span class="stat-value"><?php echo $stats['total_clientes']; ?></span>
                    <span class="stat-label">Total clientes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <div>
                    <span class="stat-value"><?php echo $stats['clientes_con_compras']; ?></span>
                    <span class="stat-label">Con compras</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
                <div>
                    <span class="stat-value"><?php echo $total_pedidos; ?></span>
                    <span class="stat-label">Total pedidos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div>
                    <span class="stat-value"><?php echo $promedio; ?></span>
                    <span class="stat-label">Prom. pedidos/cliente</span>
                </div>
            </div>
        </div>

        <!-- Barra de búsqueda -->
        <div class="clientes-header">
            <form method="GET" class="search-box">
                <i class="fa-solid fa-search"></i>
                <input type="text" name="buscar" placeholder="Buscar cliente por nombre o teléfono..." 
                       value="<?php echo htmlspecialchars($busqueda); ?>">
                <button type="submit">Buscar</button>
                <?php if ($busqueda): ?>
                    <a href="clientes.php" class="clear-search"><i class="fa-solid fa-times"></i> Limpiar</a>
                <?php endif; ?>
            </form>
            
            <button onclick="abrirModalNuevo()" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Nuevo Cliente
            </button>
        </div>

        <!-- Lista de clientes -->
        <div class="clientes-list">
            <?php if ($clientes->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-users-slash"></i>
                    <h3>No hay clientes</h3>
                    <p>Los clientes aparecerán aquí cuando registres pedidos</p>
                    <button onclick="abrirModalNuevo()" class="btn-primary" style="margin-top: 15px;">
                        <i class="fa-solid fa-plus"></i> Agregar primer cliente
                    </button>
                </div>
            <?php else: ?>
                <?php while($cliente = $clientes->fetch_assoc()): 
                    $pedidos = $conn->query("
                        SELECT COUNT(*) as total_pedidos, SUM(total) as total_gastado 
                        FROM pedidos 
                        WHERE cliente_id = " . $cliente['id']
                    );
                    $pedidos_data = $pedidos->fetch_assoc();
                    
                    $ultimo = $conn->query("
                        SELECT fecha_pedido, estado 
                        FROM pedidos 
                        WHERE cliente_id = " . $cliente['id'] . " 
                        ORDER BY fecha_pedido DESC LIMIT 1
                    ");
                    $ultimo_pedido = $ultimo->fetch_assoc();
                ?>
                    <div class="cliente-card">
                        <div class="cliente-header">
                            <div class="cliente-avatar">
                                <?php 
                                $iniciales = '';
                                $palabras = explode(' ', $cliente['nombre']);
                                foreach ($palabras as $p) {
                                    $iniciales .= strtoupper(substr($p, 0, 1));
                                }
                                echo substr($iniciales, 0, 2);
                                ?>
                            </div>
                            <div class="cliente-info">
                                <h3><?php echo htmlspecialchars($cliente['nombre']); ?></h3>
                                <div class="cliente-contacto">
                                    <span class="contacto-item"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($cliente['telefono']); ?></span>
                                </div>
                            </div>
                            <div class="cliente-fecha">
                                <i class="fa-regular fa-calendar"></i> <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?>
                            </div>
                        </div>

                        <div class="cliente-body">
                            <?php if ($cliente['direccion']): ?>
                                <div class="direccion">
                                    <span class="label"><i class="fa-solid fa-location-dot"></i> Dirección:</span>
                                    <span class="value"><?php echo htmlspecialchars($cliente['direccion']); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="stats-pedidos">
                                <div class="stat-pedido">
                                    <span class="stat-pedido-value"><?php echo $pedidos_data['total_pedidos']; ?></span>
                                    <span class="stat-pedido-label">Pedidos</span>
                                </div>
                                <div class="stat-pedido">
                                    <span class="stat-pedido-value">$<?php echo number_format($pedidos_data['total_gastado'] ?? 0, 0); ?></span>
                                    <span class="stat-pedido-label">Gastado</span>
                                </div>
                                <?php if ($ultimo_pedido): ?>
                                    <div class="stat-pedido">
                                        <span class="stat-pedido-value"><?php echo date('d/m', strtotime($ultimo_pedido['fecha_pedido'])); ?></span>
                                        <span class="stat-pedido-label">Último</span>
                                    </div>
                                    <div class="stat-pedido">
                                        <span class="badge-estado <?php echo $ultimo_pedido['estado']; ?>">
                                            <?php echo $ultimo_pedido['estado']; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($cliente['notas']): ?>
                                <div class="notas">
                                    <span class="label"><i class="fa-solid fa-note-sticky"></i> Notas:</span>
                                    <p><?php echo nl2br(htmlspecialchars($cliente['notas'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="cliente-footer">
                            <a href="https://wa.me/<?php echo $cliente['telefono']; ?>" target="_blank" class="btn-whatsapp">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="pedidos.php?cliente=<?php echo $cliente['id']; ?>" class="btn-pedidos">
                                <i class="fa-solid fa-box"></i> Ver pedidos
                            </a>
                            <button onclick="editarCliente(<?php echo $cliente['id']; ?>)" class="btn-editar">
                                <i class="fa-solid fa-pen"></i> Editar
                            </button>
                            <?php if ($pedidos_data['total_pedidos'] == 0): ?>
                                <a href="?eliminar=<?php echo $cliente['id']; ?>" 
                                   class="btn-eliminar" 
                                   onclick="return confirm('¿Eliminar este cliente?')">
                                    <i class="fa-solid fa-trash"></i> Eliminar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para nuevo/editar cliente -->
    <div id="clienteModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitulo"><i class="fa-solid fa-user-plus"></i> Nuevo Cliente</h2>
            
            <form method="POST" class="cliente-form" id="clienteForm">
                <input type="hidden" name="id" id="cliente_id">
                
                <div class="form-group">
                    <label for="nombre">Nombre completo *</label>
                    <input type="text" id="nombre" name="nombre" required placeholder="Ej: María Gómez">
                </div>
                
                <div class="form-group">
                    <label for="telefono">Teléfono *</label>
                    <input type="text" id="telefono" name="telefono" required placeholder="Ej: 57300123456">
                </div>
                
                <div class="form-group">
                    <label for="direccion">Dirección</label>
                    <input type="text" id="direccion" name="direccion" placeholder="Ej: Calle 123 #45-67">
                </div>
                
                <div class="form-group">
                    <label for="notas">Notas</label>
                    <textarea id="notas" name="notas" rows="3" placeholder="Información adicional del cliente..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="agregar" id="btnGuardar" class="btn-primary">Guardar Cliente</button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const clientes = <?php 
            $clientes->data_seek(0);
            $clientes_array = [];
            while($row = $clientes->fetch_assoc()) {
                $clientes_array[] = $row;
            }
            echo json_encode($clientes_array); 
        ?>;

        function abrirModalNuevo() {
            document.getElementById('modalTitulo').innerHTML = '<i class="fa-solid fa-user-plus"></i> Nuevo Cliente';
            document.getElementById('cliente_id').value = '';
            document.getElementById('nombre').value = '';
            document.getElementById('telefono').value = '';
            document.getElementById('direccion').value = '';
            document.getElementById('notas').value = '';
            
            document.getElementById('btnGuardar').name = 'agregar';
            document.getElementById('clienteModal').style.display = 'flex';
        }

        function editarCliente(id) {
            const cliente = clientes.find(c => c.id == id);
            
            if (cliente) {
                document.getElementById('modalTitulo').innerHTML = '<i class="fa-solid fa-user-pen"></i> Editar Cliente';
                document.getElementById('cliente_id').value = cliente.id;
                document.getElementById('nombre').value = cliente.nombre;
                document.getElementById('telefono').value = cliente.telefono;
                document.getElementById('direccion').value = cliente.direccion || '';
                document.getElementById('notas').value = cliente.notas || '';
                
                document.getElementById('btnGuardar').name = 'editar';
                document.getElementById('clienteModal').style.display = 'flex';
            }
        }

        function cerrarModal() {
            document.getElementById('clienteModal').style.display = 'none';
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

        document.querySelector('.close').onclick = cerrarModal;

        window.onclick = function(event) {
            const modal = document.getElementById('clienteModal');
            if (event.target == modal) {
                cerrarModal();
            }
        }
    </script>
</body>
</html>