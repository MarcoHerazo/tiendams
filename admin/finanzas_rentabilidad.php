<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// ============================================
// PROCESAR ACTUALIZACIÓN DE COSTO
// ============================================
if (isset($_POST['actualizar_costo'])) {
    $producto_id = intval($_POST['producto_id']);
    $nuevo_costo = floatval($_POST['nuevo_costo']);
    
    $stmt = $conn->prepare("UPDATE productos SET costo = ? WHERE id = ?");
    $stmt->bind_param("di", $nuevo_costo, $producto_id);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Costo actualizado correctamente";
        $tipo_mensaje = "success";
    } else {
        $mensaje = "❌ Error al actualizar el costo";
        $tipo_mensaje = "error";
    }
}

// ============================================
// OBTENER DATOS DE RENTABILIDAD
// ============================================

// Productos con sus ventas y costos
$productos = $conn->query("
    SELECT 
        p.id,
        p.nombre,
        p.precio,
        p.costo,
        p.imagen,
        c.nombre as categoria_nombre,
        COALESCE(SUM(dp.cantidad), 0) as vendidos,
        COALESCE(SUM(dp.cantidad * p.precio), 0) as ingreso_total,
        COALESCE(SUM(dp.cantidad * p.costo), 0) as costo_total,
        COALESCE(SUM(dp.cantidad * p.precio) - SUM(dp.cantidad * p.costo), 0) as ganancia_total
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    LEFT JOIN detalle_pedidos dp ON p.id = dp.producto_id
    LEFT JOIN pedidos ped ON dp.pedido_id = ped.id AND ped.estado = 'entregado'
    GROUP BY p.id
    ORDER BY ganancia_total DESC
");

// Totales generales
$total_ingresos = 0;
$total_costos = 0;
$total_ganancias = 0;
$total_vendidos = 0;
$productos_array = [];

if ($productos && $productos->num_rows > 0) {
    while ($p = $productos->fetch_assoc()) {
        $productos_array[] = $p;
        $total_ingresos += $p['ingreso_total'];
        $total_costos += $p['costo_total'];
        $total_ganancias += $p['ganancia_total'];
        $total_vendidos += $p['vendidos'];
    }
    $productos->data_seek(0);
}

// Margen general
$margen_general = $total_ingresos > 0 ? round(($total_ganancias / $total_ingresos) * 100, 1) : 0;

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

// Función para clase de margen
function clase_margen($margen) {
    if ($margen >= 50) return 'success';
    if ($margen >= 30) return 'warning';
    return 'danger';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Rentabilidad - <?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></title>
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

        /* ===== MENSAJES ===== */
        .mensaje {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mensaje.success {
            background: rgba(16,185,129,0.1);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.3);
        }

        .mensaje.error {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }

        /* ===== TARJETAS DE RESUMEN ===== */
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
        }

        .resumen-card .value {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            line-height: 1.2;
        }

        .resumen-card .sub {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 5px;
        }

        .resumen-card.ingresos .value { color: #10b981; }
        .resumen-card.costos .value { color: #ef4444; }
        .resumen-card.ganancias .value { color: var(--primary); }
        .resumen-card.margen .value { color: #f59e0b; }

        /* ===== TABLA DE RENTABILIDAD ===== */
        .table-container {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            text-align: left;
            padding: 14px 12px;
            background: rgba(0,0,0,0.2);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            border-bottom: 2px solid var(--primary);
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .producto-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .producto-imagen {
            width: 45px;
            height: 45px;
            background: var(--dark);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .producto-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .producto-imagen i {
            font-size: 1.2rem;
            color: var(--text-muted);
        }

        .producto-detalle h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge.success {
            background: rgba(16,185,129,0.15);
            color: #34d399;
        }

        .badge.warning {
            background: rgba(245,158,11,0.15);
            color: #fbbf24;
        }

        .badge.danger {
            background: rgba(239,68,68,0.15);
            color: #f87171;
        }

        .ganancia-positiva {
            color: #10b981;
            font-weight: 600;
        }

        .ganancia-negativa {
            color: #ef4444;
            font-weight: 600;
        }

        .total-row {
            background: rgba(0,0,0,0.2);
        }

        .total-row td {
            color: white;
            border-top: 2px solid var(--primary);
            font-weight: 700;
        }

        /* ===== BOTÓN DE EDICIÓN ===== */
        .btn-editar-costo {
            background: rgba(255,107,53,0.1);
            color: var(--primary);
            border: 1px solid rgba(255,107,53,0.3);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-editar-costo:hover {
            background: var(--primary);
            color: white;
        }

        .costo-container {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .costo-valor {
            font-weight: 600;
            color: var(--text-secondary);
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

        .modal.active {
            display: flex;
        }

        .modal-contenido {
            background: var(--card);
            padding: 30px;
            border-radius: 20px;
            max-width: 450px;
            width: 90%;
            border: 1px solid var(--border);
        }

        .modal-contenido h3 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-info {
            background: rgba(255,107,53,0.05);
            border-left: 3px solid var(--primary);
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .modal-info p {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        .campo {
            margin-bottom: 20px;
        }

        .campo label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .campo input {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }

        .campo input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-guardar, .btn-cancelar {
            flex: 1;
            padding: 12px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-guardar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-guardar:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,107,53,0.3);
        }

        .btn-cancelar {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid var(--border);
        }

        .btn-cancelar:hover {
            background: rgba(255,255,255,0.2);
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
            .resumen-grid { grid-template-columns: repeat(2, 1fr); }
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
            
            .resumen-grid { grid-template-columns: 1fr; gap: 12px; }
            .resumen-card .value { font-size: 1.4rem; }
            .table-container { padding: 15px; }
            table { min-width: 900px; }
            th, td { padding: 10px 8px; font-size: 0.8rem; }
            .costo-container { flex-direction: column; align-items: flex-start; gap: 5px; }
            .btn-editar-costo { margin-left: 0; }
            .modal-contenido { padding: 20px; }
            .modal-actions { flex-direction: column; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .header h1 { font-size: 1.5rem; }
            table { min-width: 800px; }
            th, td { padding: 8px 4px; font-size: 0.75rem; }
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
            <h1><i class="fa-solid fa-chart-line"></i> Rentabilidad por Producto</h1>
            <p>Análisis de ganancias y márgenes por producto</p>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="mensaje <?php echo $tipo_mensaje; ?>">
                <i class="fa-solid <?php echo $tipo_mensaje == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <!-- Tarjetas de resumen -->
        <div class="resumen-grid">
            <div class="resumen-card ingresos">
                <span class="label">💰 Ingresos totales</span>
                <div class="value"><?php echo formato_peso($total_ingresos); ?></div>
                <div class="sub"><?php echo $total_vendidos; ?> productos vendidos</div>
            </div>
            <div class="resumen-card costos">
                <span class="label">📦 Costos totales</span>
                <div class="value"><?php echo formato_peso($total_costos); ?></div>
                <div class="sub">Inversión en productos</div>
            </div>
            <div class="resumen-card ganancias">
                <span class="label">📈 Ganancias totales</span>
                <div class="value"><?php echo formato_peso($total_ganancias); ?></div>
                <div class="sub">Ingresos - Costos</div>
            </div>
            <div class="resumen-card margen">
                <span class="label">📊 Margen promedio</span>
                <div class="value"><?php echo $margen_general; ?>%</div>
                <div class="sub">Rentabilidad general</div>
            </div>
        </div>

        <!-- Tabla de rentabilidad -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Costo</th>
                        <th>Ganancia/unidad</th>
                        <th>Vendidos</th>
                        <th>Ingreso total</th>
                        <th>Costo total</th>
                        <th>Ganancia total</th>
                        <th>Margen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($productos_array)): ?>
                        <?php foreach($productos_array as $p): 
                            $ganancia_unidad = $p['precio'] - $p['costo'];
                            $margen = $p['precio'] > 0 ? round(($ganancia_unidad / $p['precio']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="producto-info">
                                    <div class="producto-imagen">
                                        <?php if (!empty($p['imagen']) && file_exists("../img/".$p['imagen'])): ?>
                                            <img src="../img/<?php echo $p['imagen']; ?>" alt="<?php echo $p['nombre']; ?>">
                                        <?php else: ?>
                                            <i class="fa-solid fa-box"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="producto-detalle">
                                        <h4><?php echo htmlspecialchars($p['nombre']); ?></h4>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo $p['categoria_nombre'] ?? '-'; ?></td>
                            <td><strong><?php echo formato_peso($p['precio']); ?></strong></td>
                            <td>
                                <div class="costo-container">
                                    <span class="costo-valor"><?php echo formato_peso($p['costo']); ?></span>
                                    <button onclick="abrirModal(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['nombre']); ?>', <?php echo $p['costo']; ?>)" class="btn-editar-costo">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </button>
                                </div>
                            </td>
                            <td class="<?php echo $ganancia_unidad > 0 ? 'ganancia-positiva' : 'ganancia-negativa'; ?>">
                                <?php echo formato_peso($ganancia_unidad); ?>
                            </td>
                            <td><?php echo $p['vendidos']; ?></td>
                            <td><?php echo formato_peso($p['ingreso_total']); ?></td>
                            <td><?php echo formato_peso($p['costo_total']); ?></td>
                            <td class="<?php echo $p['ganancia_total'] > 0 ? 'ganancia-positiva' : 'ganancia-negativa'; ?>">
                                <?php echo formato_peso($p['ganancia_total']); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo clase_margen($margen); ?>">
                                    <?php echo $margen; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Fila de totales -->
                        <tr class="total-row">
                            <td colspan="5"><strong>TOTALES</strong></td>
                            <td><strong><?php echo $total_vendidos; ?></strong></td>
                            <td><strong><?php echo formato_peso($total_ingresos); ?></strong></td>
                            <td><strong><?php echo formato_peso($total_costos); ?></strong></td>
                            <td><strong><?php echo formato_peso($total_ganancias); ?></strong></td>
                            <td><strong><?php echo $margen_general; ?>%</strong></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 60px; color: var(--text-muted);">
                                <i class="fa-solid fa-chart-line" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                                <p>No hay datos de rentabilidad disponibles</p>
                                <p style="font-size: 0.9rem; margin-top: 8px;">Agrega productos y ventas para ver el análisis</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL DE EDICIÓN DE COSTO -->
    <div id="modalEditarCosto" class="modal">
        <div class="modal-contenido">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h3><i class="fa-solid fa-pen"></i> Editar costo</h3>
            
            <div class="modal-info">
                <i class="fa-solid fa-shield-halved"></i>
                <p><strong>El costo solo es visible para administradores</strong> y se usa para calcular la rentabilidad. Los clientes no ven este valor.</p>
            </div>
            
            <form method="POST" id="formEditarCosto">
                <input type="hidden" name="producto_id" id="edit_producto_id">
                
                <div class="campo">
                    <label><i class="fa-regular fa-box"></i> Producto</label>
                    <input type="text" id="edit_producto_nombre" readonly disabled>
                </div>
                
                <div class="campo">
                    <label><i class="fa-solid fa-dollar-sign"></i> Nuevo costo</label>
                    <input type="number" name="nuevo_costo" id="edit_nuevo_costo" step="0.01" min="0" required placeholder="0.00">
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="actualizar_costo" class="btn-guardar">
                        <i class="fa-solid fa-save"></i> Guardar cambios
                    </button>
                    <button type="button" onclick="cerrarModal()" class="btn-cancelar">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
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

        function abrirModal(id, nombre, costoActual) {
            document.getElementById('edit_producto_id').value = id;
            document.getElementById('edit_producto_nombre').value = nombre;
            document.getElementById('edit_nuevo_costo').value = costoActual;
            document.getElementById('modalEditarCosto').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal() {
            document.getElementById('modalEditarCosto').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModal();
            }
        });

        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('modalEditarCosto');
            if (e.target === modal) {
                cerrarModal();
            }
        });

        // Ajustar al redimensionar
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebarOverlay').classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html>
