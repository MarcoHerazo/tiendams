<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// ============================================
// OBTENER DATOS PARA EL PANEL
// ============================================

// 1. Estadísticas generales
$ingresos = $conn->query("SELECT SUM(total) as total FROM pedidos WHERE estado = 'entregado'")->fetch_assoc()['total'] ?? 0;
$gastos_totales = $conn->query("SELECT SUM(monto) as total FROM gastos")->fetch_assoc()['total'] ?? 0;
$inversion = $conn->query("SELECT SUM(p.costo * dp.cantidad) as total FROM detalle_pedidos dp JOIN productos p ON dp.producto_id = p.id")->fetch_assoc()['total'] ?? 0;
$utilidad = $ingresos - ($inversion + $gastos_totales);
$margen = $ingresos > 0 ? round(($utilidad / $ingresos) * 100, 1) : 0;

// 2. Cuentas por cobrar
$por_cobrar = $conn->query("SELECT SUM(monto_pendiente) as total FROM cuentas_cobrar WHERE estado != 'pagado'")->fetch_assoc()['total'] ?? 0;

// 3. Ingresos vs Gastos últimos 30 días (para la gráfica)
$fecha_inicio = date('Y-m-d', strtotime('-30 days'));
$fecha_fin = date('Y-m-d');

$ingresos_diarios = [];
$gastos_diarios = [];

// Consultar ingresos por día
$result = $conn->query("
    SELECT DATE(fecha_pedido) as fecha, SUM(total) as total 
    FROM pedidos 
    WHERE estado = 'entregado' 
    AND fecha_pedido BETWEEN '$fecha_inicio 00:00:00' AND '$fecha_fin 23:59:59'
    GROUP BY DATE(fecha_pedido)
");
while ($row = $result->fetch_assoc()) {
    $ingresos_diarios[$row['fecha']] = $row['total'];
}

// Consultar gastos por día
$result = $conn->query("
    SELECT fecha, SUM(monto) as total 
    FROM gastos 
    WHERE fecha BETWEEN '$fecha_inicio' AND '$fecha_fin'
    GROUP BY fecha
");
while ($row = $result->fetch_assoc()) {
    $gastos_diarios[$row['fecha']] = $row['total'];
}

// 4. Top productos más rentables
$top_productos = $conn->query("
    SELECT p.nombre, 
           SUM(dp.cantidad) as vendidos,
           SUM(dp.cantidad * p.precio) as ingreso,
           SUM(dp.cantidad * p.costo) as inversion_total,
           (SUM(dp.cantidad * p.precio) - SUM(dp.cantidad * p.costo)) as ganancia
    FROM detalle_pedidos dp
    JOIN productos p ON dp.producto_id = p.id
    JOIN pedidos ped ON dp.pedido_id = ped.id
    WHERE ped.estado = 'entregado'
    GROUP BY p.id
    ORDER BY ganancia DESC
    LIMIT 5
");

// Obtener configuración básica
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('tienda_nombre', 'moneda_simbolo')");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Panel Financiero - <?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></title>
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
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .stat-card .label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: block;
        }

        .stat-card .value {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }

        .stat-card.ingresos .value { color: #34d399; }
        .stat-card.gastos .value { color: #f87171; }
        .stat-card.balance .value { color: var(--primary); }
        .stat-card.cobrar .value { color: #fbbf24; }

        /* ===== GRÁFICA ===== */
        .grafica-container {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .grafica-container h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .grafica-container h2 i { color: var(--primary); }

        .grafica-wrapper {
            height: 300px;
            position: relative;
        }

        /* ===== TOP PRODUCTOS ===== */
        .top-productos {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
        }

        .top-productos h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .top-productos h2 i { color: var(--primary); }

        .productos-lista {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .producto-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .producto-item:hover {
            background: rgba(255,107,53,0.05);
            border-color: var(--primary);
        }

        .producto-posicion {
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .producto-info {
            flex: 1;
        }

        .producto-nombre {
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
        }

        .producto-detalles {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .producto-detalles span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .producto-ganancia {
            font-weight: 600;
            color: #34d399;
        }

        .badge {
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge.success {
            background: rgba(16,185,129,0.15);
            color: #34d399;
        }

        .badge.warning {
            background: rgba(245,158,11,0.15);
            color: #fbbf24;
        }

        .empty-state {
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

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
            
            .stats-grid { grid-template-columns: 1fr; gap: 12px; }
            .header { flex-direction: column; align-items: flex-start; }
            .header h1 { font-size: 1.5rem; }
            .grafica-wrapper { height: 250px; }
            .producto-item { flex-direction: column; align-items: flex-start; }
            .producto-posicion { align-self: flex-start; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .stat-card .value { font-size: 1.3rem; }
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
            <h1><i class="fa-solid fa-chart-pie"></i> Panel Financiero</h1>
            <a href="finanzas.php"><i class="fa-solid fa-arrow-left"></i> Volver a Finanzas</a>
        </div>

        <!-- Tarjetas de resumen -->
        <div class="stats-grid">
            <div class="stat-card ingresos">
                <span class="label">💰 Ingresos totales</span>
                <div class="value"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($ingresos, 0); ?></div>
            </div>
            <div class="stat-card gastos">
                <span class="label">💸 Gastos totales</span>
                <div class="value"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($gastos_totales, 0); ?></div>
            </div>
            <div class="stat-card balance">
                <span class="label">📊 Balance</span>
                <div class="value"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($utilidad, 0); ?></div>
            </div>
            <div class="stat-card cobrar">
                <span class="label">💰 Por cobrar</span>
                <div class="value"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($por_cobrar, 0); ?></div>
            </div>
        </div>

        <!-- Gráfica de ingresos vs gastos -->
        <div class="grafica-container">
            <h2><i class="fa-solid fa-chart-line"></i> Ingresos vs Gastos (últimos 30 días)</h2>
            <div class="grafica-wrapper">
                <canvas id="graficaFinanzas"></canvas>
            </div>
        </div>

        <!-- Top productos más rentables -->
        <div class="top-productos">
            <h2><i class="fa-solid fa-crown"></i> Top productos más rentables</h2>
            <div class="productos-lista">
                <?php if ($top_productos->num_rows > 0): 
                    $posicion = 1;
                    while($p = $top_productos->fetch_assoc()): 
                        $margen_prod = $p['ingreso'] > 0 ? round(($p['ganancia'] / $p['ingreso']) * 100, 1) : 0;
                ?>
                <div class="producto-item">
                    <div class="producto-posicion"><?php echo $posicion; ?></div>
                    <div class="producto-info">
                        <div class="producto-nombre"><?php echo htmlspecialchars($p['nombre']); ?></div>
                        <div class="producto-detalles">
                            <span><i class="fa-solid fa-cube"></i> Vendidos: <?php echo $p['vendidos']; ?></span>
                            <span><i class="fa-solid fa-dollar-sign"></i> Ingreso: <?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($p['ingreso'], 0); ?></span>
                            <span><i class="fa-solid fa-chart-line"></i> Ganancia: <span class="producto-ganancia"><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($p['ganancia'], 0); ?></span></span>
                            <span><span class="badge <?php echo $margen_prod >= 50 ? 'success' : 'warning'; ?>"><?php echo $margen_prod; ?>%</span></span>
                        </div>
                    </div>
                </div>
                <?php 
                    $posicion++;
                    endwhile; 
                else: ?>
                <div class="empty-state">
                    <i class="fa-solid fa-chart-line"></i>
                    <p>No hay suficientes datos para mostrar productos rentables</p>
                </div>
                <?php endif; ?>
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

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Gráfica con Chart.js
        const ctx = document.getElementById('graficaFinanzas').getContext('2d');
        
        // Generar fechas de los últimos 30 días
        const fechas = [];
        const ingresosData = [];
        const gastosData = [];
        
        <?php
        for ($i = 30; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            echo "fechas.push('" . date('d/m', strtotime($fecha)) . "');\n";
            echo "ingresosData.push(" . ($ingresos_diarios[$fecha] ?? 0) . ");\n";
            echo "gastosData.push(" . ($gastos_diarios[$fecha] ?? 0) . ");\n";
        }
        ?>

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: fechas,
                datasets: [{
                    label: 'Ingresos',
                    data: ingresosData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Gastos',
                    data: gastosData,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { color: '#cbd5e1' }
                    }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255,255,255,0.1)' },
                        ticks: { color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8', maxRotation: 45, minRotation: 45 }
                    }
                }
            }
        });
    </script>
</body>
</html>
