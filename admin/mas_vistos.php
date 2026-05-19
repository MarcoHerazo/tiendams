<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Reiniciar visitas si se envió el formulario
if (isset($_POST['reiniciar'])) {
    $conn->query("UPDATE productos SET visitas = 0");
    echo "<script>alert('✅ Visitas reiniciadas correctamente'); window.location.href = 'mas_vistos.php';</script>";
    exit;
}

// Obtener productos ordenados por visitas
$productos = $conn->query("
    SELECT p.*, c.nombre as categoria_nombre,
           (SELECT COUNT(*) FROM producto_variantes WHERE producto_id = p.id) as total_variantes
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    ORDER BY p.visitas DESC
");

// Total de visitas general
$total_visitas = $conn->query("SELECT SUM(visitas) as total FROM productos")->fetch_assoc()['total'];

// Obtener visitas de la página
$hoy = date('Y-m-d');
$hoy_visitas = $conn->query("SELECT visitas FROM visitas_pagina WHERE fecha = '$hoy'")->fetch_assoc();
$hoy_total = $hoy_visitas['visitas'] ?? 0;

$semana = $conn->query("
    SELECT SUM(visitas) as total 
    FROM visitas_pagina 
    WHERE fecha >= DATE_SUB('$hoy', INTERVAL 7 DAY)
")->fetch_assoc()['total'] ?? 0;

$mes = $conn->query("
    SELECT SUM(visitas) as total 
    FROM visitas_pagina 
    WHERE fecha >= DATE_SUB('$hoy', INTERVAL 30 DAY)
")->fetch_assoc()['total'] ?? 0;

$total_visitas_pagina = $conn->query("SELECT SUM(visitas) as total FROM visitas_pagina")->fetch_assoc()['total'] ?? 0;

$ultimos_7 = $conn->query("
    SELECT fecha, visitas 
    FROM visitas_pagina 
    ORDER BY fecha DESC 
    LIMIT 7
");

$top_producto = $conn->query("SELECT nombre, visitas FROM productos ORDER BY visitas DESC LIMIT 1")->fetch_assoc();
$total_productos = $conn->query("SELECT COUNT(*) as total FROM productos")->fetch_assoc()['total'];
$promedio_visitas = $total_productos > 0 ? $total_visitas / $total_productos : 0;
$cero_visitas = $conn->query("SELECT COUNT(*) as total FROM productos WHERE visitas = 0")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Productos más vistos - Tienda MS</title>
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

        /* ===== STATS HEADER ===== */
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            background: var(--card);
            padding: 20px;
            border-radius: 15px;
            border: 1px solid var(--border);
        }

        .stats-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-total {
            background: rgba(255,107,53,0.1);
            padding: 8px 16px;
            border-radius: 30px;
            color: var(--primary);
        }

        .stats-total span {
            font-weight: 800;
            font-size: 1.3rem;
        }

        /* ===== BOTÓN REINICIAR ===== */
        .boton-reiniciar {
            text-align: right;
            margin-bottom: 20px;
        }

        .btn-reiniciar {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reiniciar:hover {
            background: var(--danger);
            color: white;
        }

        /* ===== TABLA DE VISITAS ===== */
        .tabla-visitas {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            text-align: left;
            padding: 12px;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        .producto-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .producto-imagen {
            width: 50px;
            height: 50px;
            background: #0f172a;
            border-radius: 10px;
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

        .badge-visitas {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .grafico-barra {
            width: 100px;
            height: 6px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
        }

        .grafico-barra-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 3px;
        }

        .stock-disponible {
            color: var(--success);
        }

        .stock-agotado {
            color: var(--danger);
        }

        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 16px;
            padding: 18px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
        }

        .stat-card .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .stat-card.highlight {
            background: linear-gradient(135deg, rgba(255,107,53,0.1), transparent);
            border-color: var(--primary);
        }

        /* ===== VISITAS TIENDA ===== */
        .visitas-tienda {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
        }

        .visitas-tienda h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .visitas-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .visita-card {
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .visita-card .numero {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary);
        }

        .visita-card .label {
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .grafico-container {
            margin-top: 50px;
        }

        .grafico-container h3 {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 60px;
        }

        .grafico-barras {
            display: flex;
            gap: 8px;
            align-items: flex-end;
            height: 120px;
        }

        .barra-item {
            flex: 1;
            text-align: center;
        }

        .barra {
            width: 100%;
            background: linear-gradient(0deg, var(--primary), var(--primary-light));
            border-radius: 4px 4px 0 0;
            transition: height 0.3s ease;
            min-height: 4px;
        }

        .barra-fecha {
            font-size: 0.6rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .barra-valor {
            font-size: 0.55rem;
            color: var(--primary);
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
            
            .stats-header { flex-direction: column; align-items: flex-start; }
            .stats-grid { grid-template-columns: 1fr; gap: 12px; }
            .visitas-cards { grid-template-columns: repeat(2, 1fr); }
            .grafico-barras { height: 100px; }
            .table-responsive { overflow-x: auto; }
            table { min-width: 600px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .visitas-cards { grid-template-columns: 1fr; }
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
                <a href="mas_vistos.php" class="nav-item active"><i class="fa-solid fa-chart-line"></i> Más vistos</a>
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
            <h1><i class="fa-solid fa-chart-line"></i> Productos más vistos</h1>
            <p>Análisis de popularidad de tus productos</p>
        </div>

        <div class="stats-header">
            <h1><i class="fa-solid fa-eye"></i> Total de visitas</h1>
            <div class="stats-total">
                <span><?php echo number_format($total_visitas); ?></span> visitas en todos los productos
            </div>
        </div>

        <div class="boton-reiniciar">
            <form method="POST" onsubmit="return confirm('⚠️ ¿Estás seguro? Esto pondrá todas las visitas en 0. Esta acción no se puede deshacer.');">
                <button type="submit" name="reiniciar" class="btn-reiniciar">
                    <i class="fa-solid fa-rotate"></i> Reiniciar todas las visitas
                </button>
            </form>
        </div>

        <!-- Tabla de visitas -->
        <div class="tabla-visitas">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Visitas</th>
                            <th>Popularidad</th>
                            <th>Stock</th>
                        </thead>
                    <tbody>
                        <?php 
                        $max_visitas = 0;
                        $productos_array = [];
                        while($p = $productos->fetch_assoc()) {
                            $productos_array[] = $p;
                            if ($p['visitas'] > $max_visitas) $max_visitas = $p['visitas'];
                        }
                        
                        foreach($productos_array as $p): 
                            $porcentaje = $max_visitas > 0 ? ($p['visitas'] / $max_visitas) * 100 : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="producto-info">
                                    <div class="producto-imagen">
                                        <?php if ($p['imagen'] && file_exists("../img/".$p['imagen'])): ?>
                                            <img src="../img/<?php echo $p['imagen']; ?>" alt="">
                                        <?php else: ?>
                                            <i class="fa-solid fa-shirt"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($p['nombre']); ?></strong>
                                        <?php if ($p['destacado']): ?>
                                            <i class="fa-solid fa-star" style="color: #f59e0b;"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($p['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                            <td><strong>$<?php echo number_format($p['precio'], 0); ?></strong></td>
                            <td>
                                <span class="badge-visitas"><?php echo number_format($p['visitas']); ?></span>
                            </td>
                            <td>
                                <div class="grafico-barra">
                                    <div class="grafico-barra-fill" style="width: <?php echo $porcentaje; ?>%;"></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($p['stock'] > 0): ?>
                                    <span class="stock-disponible"><?php echo $p['stock']; ?> disponibles</span>
                                <?php else: ?>
                                    <span class="stock-agotado">Agotado</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cards de estadísticas adicionales -->
        <div class="stats-grid">
            <div class="stat-card highlight">
                <h3><i class="fa-solid fa-crown"></i> Producto estrella</h3>
                <div class="stat-value"><?php echo htmlspecialchars($top_producto['nombre'] ?? 'N/A'); ?></div>
                <div class="stat-label"><?php echo number_format($top_producto['visitas'] ?? 0); ?> visitas</div>
            </div>

            <div class="stat-card">
                <h3><i class="fa-solid fa-calculator"></i> Promedio por producto</h3>
                <div class="stat-value"><?php echo number_format($promedio_visitas, 0); ?></div>
                <div class="stat-label">visitas por producto</div>
            </div>

            <div class="stat-card">
                <h3><i class="fa-solid fa-exclamation-triangle"></i> Recomendación</h3>
                <div class="stat-value" style="color: var(--danger);"><?php echo $cero_visitas; ?></div>
                <div class="stat-label">productos sin visitas</div>
            </div>
        </div>

        <!-- Visitas a la tienda -->
        <div class="visitas-tienda">
            <h2><i class="fa-solid fa-eye"></i> Visitas a la tienda</h2>
            
            <div class="visitas-cards">
                <div class="visita-card">
                    <div class="numero"><?php echo $hoy_total; ?></div>
                    <div class="label">Hoy</div>
                </div>
                <div class="visita-card">
                    <div class="numero"><?php echo $semana; ?></div>
                    <div class="label">Esta semana</div>
                </div>
                <div class="visita-card">
                    <div class="numero"><?php echo $mes; ?></div>
                    <div class="label">Este mes</div>
                </div>
                <div class="visita-card">
                    <div class="numero"><?php echo $total_visitas_pagina; ?></div>
                    <div class="label">Total histórico</div>
                </div>
            </div>

            <!-- Gráfico últimos 7 días -->
            <div class="grafico-container">
                <h3><i class="fa-solid fa-chart-simple"></i> Últimos 7 días</h3>
                <div class="grafico-barras">
                    <?php 
                    $max_visitas_grafico = 0;
                    $datos = [];
                    $ultimos_7->data_seek(0);
                    while($v = $ultimos_7->fetch_assoc()) {
                        $datos[] = $v;
                        if ($v['visitas'] > $max_visitas_grafico) $max_visitas_grafico = $v['visitas'];
                    }
                    $datos = array_reverse($datos);
                    
                    foreach($datos as $d):
                        $altura = $max_visitas_grafico > 0 ? ($d['visitas'] / $max_visitas_grafico) * 100 : 0;
                    ?>
                    <div class="barra-item">
                        <div class="barra" style="height: <?php echo $altura; ?>px;"></div>
                        <div class="barra-fecha"><?php echo date('d/m', strtotime($d['fecha'])); ?></div>
                        <div class="barra-valor"><?php echo $d['visitas']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
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