<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// ============================================
// OBTENER DATOS PARA REPORTES
// ============================================

// Filtros
$tipo_reporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'ventas';
$periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'mes';
$fecha_desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-d');

// 1. REPORTE DE VENTAS
if ($tipo_reporte == 'ventas') {
    // Ventas por período
    $ventas = $conn->query("
        SELECT 
            DATE(fecha_pedido) as fecha,
            COUNT(*) as total_pedidos,
            SUM(total) as ingresos,
            AVG(total) as promedio
        FROM pedidos 
        WHERE estado = 'entregado'
        AND DATE(fecha_pedido) BETWEEN '$fecha_desde' AND '$fecha_hasta'
        GROUP BY DATE(fecha_pedido)
        ORDER BY fecha DESC
    ");
    
    // Totales
    $totales = $conn->query("
        SELECT 
            COUNT(*) as total_pedidos,
            SUM(total) as total_ingresos,
            AVG(total) as promedio_venta
        FROM pedidos 
        WHERE estado = 'entregado'
        AND DATE(fecha_pedido) BETWEEN '$fecha_desde' AND '$fecha_hasta'
    ")->fetch_assoc();
}

// 2. REPORTE DE GASTOS
if ($tipo_reporte == 'gastos') {
    // Gastos por categoría
    $gastos_categoria = $conn->query("
        SELECT 
            categoria,
            COUNT(*) as total_gastos,
            SUM(monto) as total
        FROM gastos 
        WHERE fecha BETWEEN '$fecha_desde' AND '$fecha_hasta'
        GROUP BY categoria
        ORDER BY total DESC
    ");
    
    // Gastos diarios
    $gastos_diarios = $conn->query("
        SELECT 
            fecha,
            COUNT(*) as total_gastos,
            SUM(monto) as total
        FROM gastos 
        WHERE fecha BETWEEN '$fecha_desde' AND '$fecha_hasta'
        GROUP BY fecha
        ORDER BY fecha DESC
    ");
    
    // Total gastos
    $total_gastos = $conn->query("
        SELECT SUM(monto) as total 
        FROM gastos 
        WHERE fecha BETWEEN '$fecha_desde' AND '$fecha_hasta'
    ")->fetch_assoc()['total'] ?? 0;
}

// 3. REPORTE COMPARATIVO
if ($tipo_reporte == 'comparativo') {
    // Ventas por mes (últimos 6 meses)
    $ventas_mensuales = $conn->query("
        SELECT 
            DATE_FORMAT(fecha_pedido, '%Y-%m') as mes,
            DATE_FORMAT(fecha_pedido, '%M %Y') as mes_nombre,
            COUNT(*) as total_pedidos,
            SUM(total) as ingresos
        FROM pedidos 
        WHERE estado = 'entregado'
        AND fecha_pedido >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha_pedido, '%Y-%m')
        ORDER BY mes DESC
    ");
    
    // Gastos por mes (últimos 6 meses)
    $gastos_mensuales = $conn->query("
        SELECT 
            DATE_FORMAT(fecha, '%Y-%m') as mes,
            DATE_FORMAT(fecha, '%M %Y') as mes_nombre,
            COUNT(*) as total_gastos,
            SUM(monto) as total
        FROM gastos 
        WHERE fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha, '%Y-%m')
        ORDER BY mes DESC
    ");
}

// Obtener configuración básica
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('tienda_nombre', 'moneda_simbolo')");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Función para formato pesos colombianos
function formato_peso($numero) {
    return '$' . number_format($numero, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Reportes Financieros - <?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

.header h1 {
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 4px;
    background: linear-gradient(135deg, #fff, var(--text-secondary));
    background-clip: text;           /* ← propiedad estándar */
    -webkit-background-clip: text;   /* ← soporte WebKit */
    -webkit-text-fill-color: transparent;
    color: transparent;              /* ← fallback por si acaso */
}

        .header p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .btn-back {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }

        /* ===== FILTROS ===== */
        .filtros {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .filtros-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 24px;
            border-radius: 30px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .tab-btn:hover {
            background: rgba(255, 107, 53, 0.1);
            color: white;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: transparent;
        }

        .filtros-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filtro-group {
            flex: 1;
            min-width: 160px;
        }

        .filtro-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .filtro-group input {
            width: 100%;
            padding: 10px 14px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 30px;
            color: white;
            font-size: 0.9rem;
        }

        .btn-filtrar {
            padding: 10px 28px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-filtrar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        /* ===== TARJETAS DE RESUMEN ===== */
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .resumen-card {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .resumen-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .resumen-card .label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .resumen-card .value {
            font-size: 1.8rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }

        /* ===== GRÁFICAS ===== */
        .grafica-container {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .grafica-container h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .grafica-container h3 i {
            color: var(--primary);
        }

        .grafica-wrapper {
            height: 300px;
            position: relative;
        }

        /* ===== TABLAS ===== */
        .table-container {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            overflow-x: auto;
        }

        .table-container h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .table-container h3 i {
            color: var(--primary);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 500px;
        }

        th {
            text-align: left;
            padding: 12px 10px;
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-categoria {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 107, 53, 0.15);
            color: var(--primary-light);
            display: inline-block;
        }

        .text-center {
            text-align: center;
        }

        .empty-data {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
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
            
            .header { flex-direction: column; align-items: flex-start; }
            .header h1 { font-size: 1.5rem; }
            
            .filtros-tabs { justify-content: center; }
            .filtros-form { flex-direction: column; }
            .filtro-group { width: 100%; }
            .btn-filtrar { width: 100%; justify-content: center; }
            
            .resumen-grid { grid-template-columns: 1fr; gap: 15px; }
            .resumen-card .value { font-size: 1.5rem; }
            
            .grafica-wrapper { height: 250px; }
            
            .table-container { padding: 18px; }
            th, td { padding: 10px 6px; font-size: 0.85rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .grafica-wrapper { height: 200px; }
            table { min-width: 400px; }
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
        <div class="header">
            <div>
                <h1><i class="fa-solid fa-file-lines" style="color: var(--primary);"></i> Reportes Financieros</h1>
                <p>Analiza tus ventas, gastos y comparativos</p>
            </div>
            <a href="finanzas.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
        </div>

        <!-- Filtros -->
        <div class="filtros">
            <div class="filtros-tabs">
                <a href="?tipo=ventas&desde=<?php echo $fecha_desde; ?>&hasta=<?php echo $fecha_hasta; ?>" 
                   class="tab-btn <?php echo $tipo_reporte == 'ventas' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i> Ventas
                </a>
                <a href="?tipo=gastos&desde=<?php echo $fecha_desde; ?>&hasta=<?php echo $fecha_hasta; ?>" 
                   class="tab-btn <?php echo $tipo_reporte == 'gastos' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i> Gastos
                </a>
                <a href="?tipo=comparativo&desde=<?php echo $fecha_desde; ?>&hasta=<?php echo $fecha_hasta; ?>" 
                   class="tab-btn <?php echo $tipo_reporte == 'comparativo' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-bar"></i> Comparativo
                </a>
            </div>

            <form method="GET" class="filtros-form">
                <input type="hidden" name="tipo" value="<?php echo $tipo_reporte; ?>">
                
                <div class="filtro-group">
                    <label><i class="fa-regular fa-calendar"></i> Desde</label>
                    <input type="date" name="desde" value="<?php echo $fecha_desde; ?>">
                </div>
                
                <div class="filtro-group">
                    <label><i class="fa-regular fa-calendar"></i> Hasta</label>
                    <input type="date" name="hasta" value="<?php echo $fecha_hasta; ?>">
                </div>
                
                <button type="submit" class="btn-filtrar">
                    <i class="fa-solid fa-filter"></i> Aplicar
                </button>
            </form>
        </div>

        <!-- REPORTE DE VENTAS -->
        <?php if ($tipo_reporte == 'ventas'): ?>
            <div class="resumen-grid">
                <div class="resumen-card">
                    <span class="label">💰 Ingresos totales</span>
                    <div class="value"><?php echo formato_peso($totales['total_ingresos'] ?? 0); ?></div>
                </div>
                <div class="resumen-card">
                    <span class="label">📦 Total pedidos</span>
                    <div class="value"><?php echo number_format($totales['total_pedidos'] ?? 0, 0); ?></div>
                </div>
                <div class="resumen-card">
                    <span class="label">📊 Promedio por venta</span>
                    <div class="value"><?php echo formato_peso($totales['promedio_venta'] ?? 0); ?></div>
                </div>
            </div>

            <?php if ($ventas && $ventas->num_rows > 0): ?>
                <div class="grafica-container">
                    <h3><i class="fa-solid fa-chart-line"></i> Ventas diarias</h3>
                    <div class="grafica-wrapper">
                        <canvas id="graficaVentas"></canvas>
                    </div>
                </div>

                <div class="table-container">
                    <h3><i class="fa-solid fa-table"></i> Detalle de ventas</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Pedidos</th>
                                <th>Ingresos</th>
                                <th>Promedio</th>
                            </thead>
                        <tbody>
                            <?php while($v = $ventas->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($v['fecha'])); ?></td>
                                <td><?php echo $v['total_pedidos']; ?></td>
                                <td><strong style="color: var(--primary);"><?php echo formato_peso($v['ingresos']); ?></strong></td>
                                <td><?php echo formato_peso($v['promedio']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <script>
                    const ctx = document.getElementById('graficaVentas').getContext('2d');
                    const fechas = [<?php 
                        $ventas->data_seek(0);
                        $labels = [];
                        $valores = [];
                        while($v = $ventas->fetch_assoc()) {
                            $labels[] = "'" . date('d/m', strtotime($v['fecha'])) . "'";
                            $valores[] = $v['ingresos'];
                        }
                        echo implode(',', $labels);
                    ?>];
                    const valores = [<?php echo implode(',', $valores); ?>];
                    
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: fechas,
                            datasets: [{
                                label: 'Ventas',
                                data: valores,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#94a3b8' } },
                                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                            }
                        }
                    });
                </script>
            <?php else: ?>
                <div class="table-container">
                    <div class="empty-data">
                        <i class="fa-solid fa-chart-line" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No hay ventas en el período seleccionado</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- REPORTE DE GASTOS -->
        <?php if ($tipo_reporte == 'gastos'): ?>
            <div class="resumen-grid">
                <div class="resumen-card">
                    <span class="label">💸 Total gastos</span>
                    <div class="value"><?php echo formato_peso($total_gastos); ?></div>
                </div>
            </div>

            <?php if ($gastos_categoria && $gastos_categoria->num_rows > 0): ?>
                <div class="grafica-container">
                    <h3><i class="fa-solid fa-chart-pie"></i> Gastos por categoría</h3>
                    <div class="grafica-wrapper">
                        <canvas id="graficaGastos"></canvas>
                    </div>
                </div>

                <div class="table-container">
                    <h3><i class="fa-solid fa-table"></i> Gastos por categoría</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Categoría</th>
                                <th>Cantidad</th>
                                <th>Total</th>
                                <th>%</th>
                            </thead>
                        <tbody>
                            <?php 
                            $gastos_categoria->data_seek(0);
                            while($g = $gastos_categoria->fetch_assoc()): 
                                $porcentaje = $total_gastos > 0 ? round(($g['total'] / $total_gastos) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><span class="badge-categoria"><?php echo $g['categoria']; ?></span></td>
                                <td><?php echo $g['total_gastos']; ?></td>
                                <td><strong style="color: var(--primary);"><?php echo formato_peso($g['total']); ?></strong></td>
                                <td><?php echo $porcentaje; ?>%</td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <script>
                    const ctxG = document.getElementById('graficaGastos').getContext('2d');
                    const categorias = [<?php 
                        $gastos_categoria->data_seek(0);
                        $cat_labels = [];
                        $cat_valores = [];
                        while($g = $gastos_categoria->fetch_assoc()) {
                            $cat_labels[] = "'" . $g['categoria'] . "'";
                            $cat_valores[] = $g['total'];
                        }
                        echo implode(',', $cat_labels);
                    ?>];
                    const valoresCat = [<?php echo implode(',', $cat_valores); ?>];
                    
                    new Chart(ctxG, {
                        type: 'doughnut',
                        data: {
                            labels: categorias,
                            datasets: [{
                                data: valoresCat,
                                backgroundColor: ['#ff6b35', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { labels: { color: '#cbd5e1' } } }
                        }
                    });
                </script>
            <?php else: ?>
                <div class="table-container">
                    <div class="empty-data">
                        <i class="fa-solid fa-chart-pie" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No hay gastos en el período seleccionado</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- REPORTE COMPARATIVO -->
        <?php if ($tipo_reporte == 'comparativo'): ?>
            <div class="grafica-container">
                <h3><i class="fa-solid fa-chart-bar"></i> Comparativo últimos 6 meses</h3>
                <div class="grafica-wrapper">
                    <canvas id="graficaComparativo"></canvas>
                </div>
            </div>

            <div class="table-container">
                <h3><i class="fa-solid fa-table"></i> Ventas mensuales</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Pedidos</th>
                            <th>Ingresos</th>
                        </thead>
                    <tbody>
                        <?php if ($ventas_mensuales && $ventas_mensuales->num_rows > 0): ?>
                            <?php while($v = $ventas_mensuales->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $v['mes_nombre']; ?></td>
                                <td><?php echo $v['total_pedidos']; ?></td>
                                <td><strong style="color: var(--primary);"><?php echo formato_peso($v['ingresos']); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center" style="color: var(--text-muted);">No hay datos</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container" style="margin-top: 20px;">
                <h3><i class="fa-solid fa-table"></i> Gastos mensuales</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Gastos</th>
                            <th>Total</th>
                        </thead>
                    <tbody>
                        <?php if ($gastos_mensuales && $gastos_mensuales->num_rows > 0): ?>
                            <?php while($g = $gastos_mensuales->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $g['mes_nombre']; ?></td>
                                <td><?php echo $g['total_gastos']; ?></td>
                                <td><strong style="color: #ef4444;"><?php echo formato_peso($g['total']); ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center" style="color: var(--text-muted);">No hay datos</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <script>
                const ctxC = document.getElementById('graficaComparativo').getContext('2d');
                const meses = [<?php 
                    $ventas_mensuales->data_seek(0);
                    $mes_labels = [];
                    $ingresos_mes = [];
                    $gastos_mes = [];
                    
                    $gastos_data = [];
                    if ($gastos_mensuales && $gastos_mensuales->num_rows > 0) {
                        $gastos_mensuales->data_seek(0);
                        while($g = $gastos_mensuales->fetch_assoc()) {
                            $gastos_data[$g['mes']] = $g['total'];
                        }
                    }
                    
                    $ventas_mensuales->data_seek(0);
                    while($v = $ventas_mensuales->fetch_assoc()) {
                        $mes_labels[] = "'" . $v['mes_nombre'] . "'";
                        $ingresos_mes[] = $v['ingresos'];
                        $gastos_mes[] = $gastos_data[$v['mes']] ?? 0;
                    }
                    echo implode(',', $mes_labels);
                ?>];
                
                const ingresosData = [<?php echo implode(',', $ingresos_mes); ?>];
                const gastosData = [<?php echo implode(',', $gastos_mes); ?>];
                
                new Chart(ctxC, {
                    type: 'bar',
                    data: {
                        labels: meses,
                        datasets: [{
                            label: 'Ingresos',
                            data: ingresosData,
                            backgroundColor: '#10b981',
                            borderRadius: 8
                        }, {
                            label: 'Gastos',
                            data: gastosData,
                            backgroundColor: '#ef4444',
                            borderRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { labels: { color: '#cbd5e1' } } },
                        scales: {
                            y: { grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#94a3b8' } },
                            x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                        }
                    }
                });
            </script>
        <?php endif; ?>
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

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>