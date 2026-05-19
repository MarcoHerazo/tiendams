<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Estadísticas generales
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos")->fetch_assoc()['total'];
$total_visitas = $conn->query("SELECT SUM(visitas) as total FROM productos")->fetch_assoc()['total'] ?? 0;
$total_destacados = $conn->query("SELECT COUNT(*) as total FROM productos_destacados WHERE activo = 1")->fetch_assoc()['total'];
$sin_stock = $conn->query("SELECT COUNT(*) as total FROM productos WHERE stock = 0 OR stock IS NULL")->fetch_assoc()['total'];

// Productos más vistos (top 5)
$mas_vistos = $conn->query("
    SELECT nombre, visitas, id 
    FROM productos 
    WHERE visitas > 0 
    ORDER BY visitas DESC 
    LIMIT 5
");

// Productos con stock bajo (menos de 5 unidades)
$stock_bajo = $conn->query("
    SELECT nombre, stock, id 
    FROM productos 
    WHERE stock > 0 AND stock < 5 
    ORDER BY stock ASC 
    LIMIT 5
");

// Productos por categoría
$por_categoria = $conn->query("
    SELECT c.nombre, COUNT(p.id) as total 
    FROM categorias c 
    LEFT JOIN productos p ON c.id = p.categoria_id 
    WHERE c.activo = 1 
    GROUP BY c.id 
    ORDER BY total DESC
");

// Últimos productos agregados
$ultimos = $conn->query("
    SELECT nombre, fecha_creacion, id 
    FROM productos 
    ORDER BY id DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Estadísticas - Tienda MS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #ff6b35;
            --primary-dark: #e55a2b;
            --primary-light: #ff8c5a;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --bg: #0f172a;
            --card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --border-light: rgba(255, 255, 255, 0.1);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.2);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.2);
            --shadow-lg: 0 15px 25px -8px rgba(0,0,0,0.3);
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-full: 999px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --sidebar-width: 260px;
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
            background: #020617;
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

        .nav-item i { width: 20px; color: var(--primary); opacity: 0.8; }
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
            border-left: 4px solid var(--primary);
        }

        .header h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 4px; }
        .header p { color: var(--text-muted); font-size: 0.9rem; }

        /* ===== TARJETAS DE RESUMEN ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: rgba(255, 107, 53, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ===== TABLAS ===== */
        .tables-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .table-container {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }

        .table-container h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .table-container h2 i { color: var(--primary); }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-container td {
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .table-container tr:last-child td {
            border-bottom: none;
        }

        .rank {
            font-weight: 700;
            color: var(--primary);
            width: 35px;
        }

        .link-edit {
            color: var(--primary);
            text-decoration: none;
            margin-left: 8px;
            transition: var(--transition);
        }

        .link-edit:hover {
            color: var(--primary-light);
        }

        .progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
            margin: 8px 0 5px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 3px;
        }

        .badge-warning {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
        }

        .categoria-item {
            margin-bottom: 15px;
        }

        .categoria-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .categoria-header span:last-child {
            font-weight: 700;
            color: white;
        }

        /* ===== MOBILE ===== */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            width: 45px;
            height: 45px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            z-index: 1100;
            cursor: pointer;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
        }

        @media (max-width: 1024px) {
            .tables-grid {
                grid-template-columns: 1fr;
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
            
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 15px; flex-direction: column; text-align: center; gap: 10px; }
            .stat-icon { width: 45px; height: 45px; font-size: 1.3rem; margin: 0 auto; }
            .stat-value { font-size: 1.5rem; }
            
            .tables-grid { gap: 15px; }
            .table-container { padding: 18px; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .stats-grid { grid-template-columns: 1fr; }
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
                <a href="estadisticas.php" class="nav-item active"><i class="fa-solid fa-chart-simple"></i> Estadísticas</a>
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
            <h1><i class="fa-solid fa-chart-simple"></i> Panel de Estadísticas</h1>
            <p>Resumen completo del estado de tu tienda</p>
        </div>

        <!-- Tarjetas de resumen -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
                <div>
                    <span class="stat-value"><?php echo $total_productos; ?></span>
                    <span class="stat-label">Total productos</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-eye"></i></div>
                <div>
                    <span class="stat-value"><?php echo number_format($total_visitas); ?></span>
                    <span class="stat-label">Total visitas</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-star"></i></div>
                <div>
                    <span class="stat-value"><?php echo $total_destacados; ?></span>
                    <span class="stat-label">Destacados</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>
                <div>
                    <span class="stat-value"><?php echo $sin_stock; ?></span>
                    <span class="stat-label">Sin stock</span>
                </div>
            </div>
        </div>

        <!-- Tablas -->
        <div class="tables-grid">
            <!-- Productos más vistos -->
            <div class="table-container">
                <h2><i class="fa-solid fa-fire"></i> Productos más vistos</h2>
                <table>
                    <?php 
                    $max_visitas = 0;
                    $visitas_array = [];
                    $mas_vistos->data_seek(0);
                    while($v = $mas_vistos->fetch_assoc()) {
                        $visitas_array[] = $v;
                        if ($v['visitas'] > $max_visitas) $max_visitas = $v['visitas'];
                    }
                    
                    foreach($visitas_array as $index => $v): 
                        $porcentaje = $max_visitas > 0 ? ($v['visitas'] / $max_visitas) * 100 : 0;
                    ?>
                    <tr>
                        <td class="rank">#<?php echo $index + 1; ?></td>
                        <td>
                            <?php echo htmlspecialchars($v['nombre']); ?>
                            <a href="editar_producto.php?id=<?php echo $v['id']; ?>" class="link-edit"><i class="fa-solid fa-pen"></i></a>
                        </td>
                        <td style="text-align: right;"><?php echo number_format($v['visitas']); ?></td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Stock bajo -->
            <div class="table-container">
                <h2><i class="fa-solid fa-exclamation-circle"></i> Productos con stock bajo</h2>
                <?php 
                $stock_bajo->data_seek(0);
                if ($stock_bajo->num_rows > 0): ?>
                    <table>
                        <?php while($s = $stock_bajo->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($s['nombre']); ?>
                                <a href="editar_producto.php?id=<?php echo $s['id']; ?>" class="link-edit"><i class="fa-solid fa-pen"></i></a>
                            </td>
                            <td style="text-align: right;">
                                <span class="badge-warning"><?php echo $s['stock']; ?> uds</span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                <?php else: ?>
                    <div class="success-message">
                        <i class="fa-solid fa-check-circle"></i> Todos los productos tienen stock suficiente
                    </div>
                <?php endif; ?>
            </div>

            <!-- Productos por categoría -->
            <div class="table-container">
                <h2><i class="fa-solid fa-folder"></i> Productos por categoría</h2>
                <?php 
                $total_cats = 0;
                $categorias_array = [];
                $por_categoria->data_seek(0);
                while($c = $por_categoria->fetch_assoc()) {
                    $categorias_array[] = $c;
                    $total_cats += $c['total'];
                }
                
                foreach($categorias_array as $c): 
                    $porcentaje = $total_cats > 0 ? ($c['total'] / $total_cats) * 100 : 0;
                ?>
                <div class="categoria-item">
                    <div class="categoria-header">
                        <span><?php echo htmlspecialchars($c['nombre'] ?: 'Sin categoría'); ?></span>
                        <span><?php echo $c['total']; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Últimos productos -->
            <div class="table-container">
                <h2><i class="fa-solid fa-clock"></i> Últimos agregados</h2>
                <table>
                    <?php 
                    $ultimos->data_seek(0);
                    while($u = $ultimos->fetch_assoc()): 
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($u['nombre']); ?>
                            <a href="editar_producto.php?id=<?php echo $u['id']; ?>" class="link-edit"><i class="fa-solid fa-pen"></i></a>
                        </td>
                        <td style="text-align: right; color: var(--text-muted);">
                            <?php echo date('d/m/Y', strtotime($u['fecha_creacion'])); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
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
    </script>
</body>
</html>