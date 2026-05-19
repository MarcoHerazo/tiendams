<?php
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Obtener estadísticas
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos")->fetch_assoc()['total'];
$total_pedidos = $conn->query("SELECT COUNT(*) as total FROM pedidos")->fetch_assoc()['total'];
$total_clientes = $conn->query("SELECT COUNT(*) as total FROM clientes")->fetch_assoc()['total'];
$productos_bajo_stock = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock < 5 AND stock > 0")->fetch_assoc()['total'];

// Obtener productos para el selector
$productos = $conn->query("SELECT id, nombre, imagen FROM productos ORDER BY nombre");

// Obtener pedidos recientes
$pedidos_recientes = $conn->query("
    SELECT p.id, p.total, p.estado, p.fecha_pedido, c.nombre as cliente 
    FROM pedidos p 
    JOIN clientes c ON p.cliente_id = c.id 
    ORDER BY p.fecha_pedido DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Dashboard Pro - Tienda MS</title>
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
            transition: var(--transition);
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
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            transition: var(--transition);
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

        /* ===== STATS GRID - TARJETAS MÁS PEQUEÑAS ===== */
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
            position: relative;
            overflow: hidden;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 107, 53, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-value { 
            font-size: 1.3rem; 
            font-weight: 800; 
            display: block; 
            color: white; 
            line-height: 1.2;
        }
        
        .stat-label { 
            font-size: 0.6rem; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            font-weight: 600; 
            letter-spacing: 0.5px;
        }

        .stat-card.warning .stat-icon { background: rgba(245, 158, 11, 0.1); }

        /* ===== SECTIONS ===== */
        .card-section {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .card-section h2 { font-size: 1.2rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .card-section h2 i { color: var(--accent); }

        /* PRODUCT SELECTOR */
        .producto-selector {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .producto-select {
            flex: 1; min-width: 200px;
            background: var(--primary-dark);
            border: 1px solid var(--border);
            color: white;
            padding: 12px;
            border-radius: 10px;
            outline: none;
            cursor: pointer;
        }

        .producto-select:focus {
            border-color: var(--accent);
        }

        .producto-preview {
            display: flex; align-items: center; gap: 12px;
            background: rgba(0,0,0,0.2);
            padding: 8px 15px; border-radius: 12px;
        }

        .preview-img { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: flex; align-items: center; gap: 8px;
            transition: var(--transition);
            font-size: 0.85rem;
        }

        .btn-edit { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .btn-edit:hover { background: #3b82f6; color: white; transform: translateY(-2px); }
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .btn-delete:hover { background: var(--danger); color: white; transform: translateY(-2px); }

        /* TABLE */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        td { padding: 15px 12px; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: var(--text-secondary); }
        tr:hover td { background: rgba(255,255,255,0.02); }

        .badge {
            padding: 4px 10px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        }
        .pendiente { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .confirmado, .entregado { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .enviado { background: rgba(139, 92, 246, 0.1); color: #a78bfa; }
        .cancelado { background: rgba(239, 68, 68, 0.1); color: var(--danger); }

        /* MOBILE OVERLAY & TOGGLE */
        .menu-toggle {
            display: none;
            position: fixed; top: 15px; left: 15px;
            width: 45px; height: 45px;
            background: var(--accent);
            color: white; border: none; border-radius: 10px;
            z-index: 1100; align-items: center; justify-content: center; font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
            cursor: pointer;
            transition: var(--transition);
        }

        .menu-toggle:hover {
            transform: scale(1.05);
        }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 999; display: none;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: 260px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 15px; padding-top: 75px; }
            .menu-toggle { display: flex; }
            .overlay.active { display: block; }
            .stats-grid { gap: 12px; }
            .stat-card { padding: 12px; gap: 10px; }
            .stat-icon { width: 36px; height: 36px; font-size: 1rem; }
            .stat-value { font-size: 1.2rem; }
            .stat-label { font-size: 0.55rem; }
            .header h1 { font-size: 1.4rem; }
            .producto-selector { flex-direction: column; align-items: stretch; }
            .card-section { padding: 18px; }
            .btn { padding: 8px 14px; font-size: 0.75rem; }
        }

        @media (max-width: 480px) {
            .stats-grid { gap: 10px; }
            .stat-card { padding: 10px; }
            .stat-icon { width: 32px; height: 32px; font-size: 0.9rem; }
            .stat-value { font-size: 1rem; }
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
                <a href="dashboard.php" class="nav-item active"><i class="fa-solid fa-house"></i> Dashboard</a>
                <a href="agregar_producto.php" class="nav-item"><i class="fa-solid fa-circle-plus"></i> Nuevo Producto</a>
                <a href="pedidos.php" class="nav-item"><i class="fa-solid fa-cart-shopping"></i> Pedidos</a>
                <a href="clientes.php" class="nav-item"><i class="fa-solid fa-user-group"></i> Clientes</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">CATÁLOGO</div>
                <a href="categorias.php" class="nav-item"><i class="fa-solid fa-layer-group"></i> Categorías</a>
                <a href="tallas.php" class="nav-item"><i class="fa-solid fa-ruler-combined"></i> Tallas</a>
                <a href="colores.php" class="nav-item"><i class="fa-solid fa-droplet"></i> Colores</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">REPORTES</div>
                <a href="finanzas.php" class="nav-item"><i class="fa-solid fa-wallet"></i> Finanzas</a>
                <a href="estadisticas.php" class="nav-item"><i class="fa-solid fa-chart-simple"></i> Estadísticas</a>
            </div>
            <a href="logout.php" class="nav-item logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Panel de Control</h1>
            <p>Hola, <strong><?php echo $_SESSION['admin']; ?></strong>. Así va tu tienda hoy.</p>
        </div>

        <!-- Tarjetas de estadísticas más pequeñas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div>
                    <span class="stat-value"><?php echo $total_productos; ?></span>
                    <span class="stat-label">Productos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🛒</div>
                <div>
                    <span class="stat-value"><?php echo $total_pedidos; ?></span>
                    <span class="stat-label">Pedidos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div>
                    <span class="stat-value"><?php echo $total_clientes; ?></span>
                    <span class="stat-label">Clientes</span>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon">⚠️</div>
                <div>
                    <span class="stat-value"><?php echo $productos_bajo_stock; ?></span>
                    <span class="stat-label">Bajo Stock</span>
                </div>
            </div>
        </div>

        <div class="card-section">
            <h2><i class="fa-solid fa-sliders"></i> Acceso Rápido a Producto</h2>
            <div class="producto-selector">
                <select id="productoSelect" class="producto-select" onchange="actualizarPreview()">
                    <option value="">Buscar producto...</option>
                    <?php 
                    $productos->data_seek(0);
                    while($row = $productos->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>" data-img="<?php echo $row['imagen']; ?>">
                            <?php echo htmlspecialchars($row['nombre']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <div id="previewArea" class="producto-preview" style="display: none;">
                    <img id="previewImg" src="" class="preview-img">
                    <div class="producto-btns" style="display: flex; gap: 8px;">
                        <button onclick="editarP()" class="btn btn-edit"><i class="fa-solid fa-pen"></i></button>
                        <button onclick="eliminarP()" class="btn btn-delete"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-section">
            <h2><i class="fa-solid fa-receipt"></i> Pedidos Recientes</h2>
            <div class="table-responsive">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php if ($pedidos_recientes->num_rows > 0): ?>
                            <?php while($p = $pedidos_recientes->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $p['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($p['cliente']); ?></td>
                                    <td>$<?php echo number_format($p['total'], 0); ?></td>
                                    <td><span class="badge <?php echo $p['estado']; ?>"><?php echo $p['estado']; ?></span></td>
                                    <td><?php echo date('d/m/Y', strtotime($p['fecha_pedido'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center;">Sin movimientos hoy</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Toggle Menú Móvil
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        const toggleMenu = () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        };

        menuToggle.onclick = toggleMenu;
        overlay.onclick = toggleMenu;

        // Gestión de Selección de Producto
        function actualizarPreview() {
            const select = document.getElementById('productoSelect');
            const previewArea = document.getElementById('previewArea');
            const previewImg = document.getElementById('previewImg');
            const selected = select.options[select.selectedIndex];
            
            if(select.value) {
                previewArea.style.display = 'flex';
                previewImg.src = '../img/' + selected.getAttribute('data-img');
            } else {
                previewArea.style.display = 'none';
            }
        }

        function editarP() {
            const id = document.getElementById('productoSelect').value;
            if(id) window.location.href = 'editar_producto.php?id=' + id;
        }

        function eliminarP() {
            const id = document.getElementById('productoSelect').value;
            if(id && confirm('¿Eliminar definitivamente?')) {
                window.location.href = 'eliminar_producto.php?id=' + id;
            }
        }
    </script>
</body>
</html>