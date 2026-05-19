<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Obtener ID del producto
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Si no hay ID, redirigir al dashboard
if ($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Obtener datos del producto incluyendo variantes
$producto = $conn->query("SELECT p.*, c.nombre as categoria_nombre 
                          FROM productos p 
                          LEFT JOIN categorias c ON p.categoria_id = c.id 
                          WHERE p.id = $id");

if ($producto->num_rows == 0) {
    header("Location: dashboard.php");
    exit;
}
$producto_data = $producto->fetch_assoc();

// Obtener variantes del producto
$variantes = $conn->query("SELECT pv.*, t.talla, c.nombre as color_nombre, c.codigo_hex 
                           FROM producto_variantes pv
                           JOIN tallas t ON pv.talla_id = t.id
                           JOIN colores c ON pv.color_id = c.id
                           WHERE pv.producto_id = $id
                           ORDER BY t.talla, c.nombre");

$total_stock = 0;
$variantes_array = [];
while ($var = $variantes->fetch_assoc()) {
    $total_stock += $var['stock'];
    $variantes_array[] = $var;
}

// Obtener imágenes adicionales para eliminar también
$imagenes_extra = $conn->query("SELECT imagen FROM producto_imagenes WHERE producto_id = $id");

$confirmado = isset($_GET['confirmar']) && $_GET['confirmar'] == 'si';

if ($confirmado) {
    // Eliminar imagen principal si existe
    if ($producto_data['imagen'] && file_exists("../img/" . $producto_data['imagen'])) {
        unlink("../img/" . $producto_data['imagen']);
    }
    
    // Eliminar imágenes adicionales
    while ($img = $imagenes_extra->fetch_assoc()) {
        if (!empty($img['imagen']) && file_exists("../img/" . $img['imagen'])) {
            unlink("../img/" . $img['imagen']);
        }
    }
    
    // Eliminar producto (las variantes se eliminan automáticamente por ON DELETE CASCADE)
    $conn->query("DELETE FROM productos WHERE id = $id");
    
    header("Location: dashboard.php?eliminado=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Eliminar Producto - Tienda MS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f172a;
            --primary-dark: #020617;
            --primary-light: #1e293b;
            --accent: #ff6b35;
            --accent-light: #ff8c5a;
            --danger: #ef4444;
            --danger-dark: #dc2626;
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
            border-left: 4px solid var(--danger);
        }

        .header h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 4px; color: var(--danger); }
        .header p { color: var(--text-muted); font-size: 0.9rem; }

        /* ===== TARJETA DEL PRODUCTO ===== */
        .delete-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .producto-card {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }

        .producto-imagen {
            width: 200px;
            height: 200px;
            background: var(--primary-dark);
            border-radius: 16px;
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

        .no-image {
            text-align: center;
            color: var(--text-muted);
        }

        .no-image span {
            font-size: 3rem;
            display: block;
            margin-bottom: 8px;
        }

        .producto-info {
            flex: 1;
        }

        .producto-info h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 16px;
            color: white;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }

        .info-item {
            background: var(--primary-dark);
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .info-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: white;
        }

        .info-value.precio {
            color: var(--accent);
        }

        .info-value.stock-agotado {
            color: var(--danger);
        }

        .descripcion {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .descripcion h3 {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .descripcion p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        /* ===== VARIANTES ===== */
        .variantes-section {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .variantes-section h3 {
            font-size: 1.1rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .variantes-section h3 i {
            color: var(--accent);
        }

        .variantes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .variantes-table th {
            text-align: left;
            padding: 12px 8px;
            color: var(--text-muted);
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        .variantes-table td {
            padding: 12px 8px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .color-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            text-shadow: 0 1px 1px rgba(0,0,0,0.2);
        }

        /* ===== ADVERTENCIA ===== */
        .warning-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .warning-icon {
            font-size: 2rem;
            color: var(--danger);
        }

        .warning-text {
            flex: 1;
            color: #f87171;
            font-size: 0.9rem;
        }

        .warning-text strong {
            display: block;
            margin-bottom: 4px;
            font-size: 1rem;
        }

        /* ===== BOTONES ===== */
        .delete-actions {
            display: flex;
            gap: 16px;
        }

        .btn-delete {
            flex: 1;
            background: linear-gradient(135deg, var(--danger), var(--danger-dark));
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-cancel {
            flex: 1;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 14px 24px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* ===== MOBILE ===== */
        /* ===== ESTILOS MÓVIL COMPLETOS ===== */

/* Barra superior móvil con botón menú */
.menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    width: 45px;
    height: 45px;
    background: var(--accent);
    color: white;
    border: none;
    border-radius: 10px;
    z-index: 1100;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    transition: var(--transition);
}

.menu-toggle i {
    font-size: 1.2rem;
}

.menu-toggle:hover {
    transform: scale(1.05);
    background: var(--accent-dark);
}

/* Overlay para cerrar menú */
.overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 999;
    display: none;
}

.overlay.active {
    display: block;
}

/* Media Queries para móvil */
@media (max-width: 768px) {
    /* Sidebar móvil */
    .sidebar {
        transform: translateX(-100%);
        position: fixed;
        z-index: 1000;
        width: 280px;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    /* Contenido principal */
    .main-content {
        margin-left: 0;
        padding: 15px;
        padding-top: 75px;
    }
    
    /* Botón menú hamburguesa */
    .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        top: 12px;
        left: 12px;
        width: 44px;
        height: 44px;
    }
    
    /* Header */
    .header {
        padding: 16px;
        margin-bottom: 20px;
    }
    
    .header h1 {
        font-size: 1.4rem;
    }
    
    .header p {
        font-size: 0.85rem;
    }
    
    /* Contenedor principal */
    .delete-container {
        padding: 0;
    }
    
    /* Tarjeta del producto */
    .producto-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 20px;
        gap: 16px;
    }
    
    .producto-imagen {
        width: 150px;
        height: 150px;
    }
    
    .producto-info h2 {
        font-size: 1.3rem;
        margin-bottom: 12px;
    }
    
    /* CUADROS DE INFORMACIÓN - 2 COLUMNAS */
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-bottom: 16px;
    }
    
    .info-item {
        padding: 10px 8px;
        text-align: center;
    }
    
    .info-label {
        font-size: 0.65rem;
    }
    
    .info-value {
        font-size: 0.85rem;
    }
    
    /* Descripción */
    .descripcion {
        text-align: left;
    }
    
    .descripcion h3 {
        font-size: 0.75rem;
    }
    
    .descripcion p {
        font-size: 0.85rem;
    }
    
    /* Sección de variantes */
    .variantes-section {
        padding: 18px;
    }
    
    .variantes-section h3 {
        font-size: 1rem;
    }
    
    .variantes-table {
        display: block;
        overflow-x: auto;
        font-size: 0.85rem;
    }
    
    .variantes-table th,
    .variantes-table td {
        padding: 10px 6px;
    }
    
    .color-badge {
        padding: 3px 8px;
        font-size: 0.7rem;
    }
    
    /* Caja de advertencia */
    .warning-box {
        flex-direction: column;
        text-align: center;
        padding: 16px;
        gap: 12px;
    }
    
    .warning-icon {
        font-size: 1.8rem;
    }
    
    .warning-text {
        font-size: 0.85rem;
    }
    
    .warning-text strong {
        font-size: 0.9rem;
    }
    
    /* Botones de acción */
    .delete-actions {
        flex-direction: column;
        gap: 12px;
    }
    
    .btn-delete,
    .btn-cancel {
        padding: 12px 20px;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
    }
}

/* Pantallas muy pequeñas (480px y menos) */
@media (max-width: 480px) {
    .main-content {
        padding: 12px;
        padding-top: 70px;
    }
    
    .header {
        padding: 14px;
    }
    
    .header h1 {
        font-size: 1.3rem;
    }
    
    .producto-card {
        padding: 16px;
    }
    
    .producto-imagen {
        width: 120px;
        height: 120px;
    }
    
    .producto-info h2 {
        font-size: 1.2rem;
    }
    
    /* CUADROS DE INFORMACIÓN - MANTENER 2 COLUMNAS */
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    .info-item {
        padding: 8px 6px;
    }
    
    .info-label {
        font-size: 0.6rem;
    }
    
    .info-value {
        font-size: 0.8rem;
    }
    
    .variantes-section {
        padding: 14px;
    }
    
    .variantes-table th,
    .variantes-table td {
        padding: 8px 4px;
        font-size: 0.75rem;
    }
    
    .warning-box {
        padding: 14px;
    }
    
    .btn-delete,
    .btn-cancel {
        padding: 10px 16px;
        font-size: 0.85rem;
    }
}

/* Orientación horizontal en móvil (landscape) */
@media (max-width: 768px) and (orientation: landscape) {
    .producto-card {
        flex-direction: row;
        text-align: left;
    }
    
    .info-grid {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .producto-imagen {
        width: 120px;
        height: 120px;
    }
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
            <h1><i class="fa-solid fa-trash-can"></i> Eliminar Producto</h1>
            <p>Revisa la información antes de eliminar. Esta acción no se puede deshacer.</p>
        </div>

        <div class="delete-container">
            <!-- Tarjeta del producto -->
            <div class="producto-card">
                <div class="producto-imagen">
                    <?php if ($producto_data['imagen'] && file_exists("../img/" . $producto_data['imagen'])): ?>
                        <img src="../img/<?php echo $producto_data['imagen']; ?>" 
                             alt="<?php echo htmlspecialchars($producto_data['nombre']); ?>">
                    <?php else: ?>
                        <div class="no-image">
                            <span>📷</span>
                            <p>Sin imagen</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="producto-info">
                    <h2><?php echo htmlspecialchars($producto_data['nombre']); ?></h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">ID</span>
                            <span class="info-value">#<?php echo $producto_data['id']; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Categoría</span>
                            <span class="info-value"><?php echo $producto_data['categoria_nombre'] ?? 'Sin categoría'; ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Precio</span>
                            <span class="info-value precio">$<?php echo number_format($producto_data['precio'], 0); ?></span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Stock total</span>
                            <span class="info-value <?php echo $total_stock == 0 ? 'stock-agotado' : ''; ?>">
                                <?php echo $total_stock; ?> unidades
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Tipo de talla</span>
                            <span class="info-value">
                                <?php 
                                    $tipo_talla_texto = [
                                        'zapatos' => '👟 Zapatos',
                                        'ropa' => '👕 Ropa',
                                        'ambos' => '👟+👕 Ambos',
                                        'ninguna' => '❌ Sin talla'
                                    ];
                                    echo $tipo_talla_texto[$producto_data['tipo_talla'] ?? 'ambos'];
                                ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Fecha ingreso</span>
                            <span class="info-value">
                                <?php echo isset($producto_data['fecha_creacion']) ? date('d/m/Y', strtotime($producto_data['fecha_creacion'])) : 'No disponible'; ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($producto_data['descripcion'])): ?>
                        <div class="descripcion">
                            <h3><i class="fa-solid fa-align-left"></i> Descripción</h3>
                            <p><?php echo nl2br(htmlspecialchars($producto_data['descripcion'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Variantes del producto -->
            <?php if (!empty($variantes_array)): ?>
                <div class="variantes-section">
                    <h3><i class="fa-solid fa-cubes"></i> Variantes (Tallas y Colores)</h3>
                    <div style="overflow-x: auto;">
                        <table class="variantes-table">
                            <thead>
                                <tr>
                                    <th>Talla</th>
                                    <th>Color</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($variantes_array as $var): ?>
                                    <tr>
                                        <td><strong><?php echo $var['talla']; ?></strong></td>
                                        <td>
                                            <span class="color-badge" style="background-color: <?php echo $var['codigo_hex'] ?: '#6b7280'; ?>">
                                                <?php echo $var['color_nombre']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $var['stock']; ?> unidades</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Advertencia -->
            <div class="warning-box">
                <div class="warning-icon">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="warning-text">
                    <strong>¡Cuidado! Esta acción es irreversible</strong>
                    Se eliminará permanentemente el producto, todas sus variantes y las imágenes asociadas.
                </div>
            </div>

            <!-- Acciones -->
            <div class="delete-actions">
                <a href="eliminar_producto.php?id=<?php echo $id; ?>&confirmar=si" 
                   class="btn-delete" 
                   onclick="return confirm('⚠️ ¿Confirmas que deseas eliminar este producto?\n\nEsta acción no se puede deshacer y se eliminarán todas las imágenes asociadas.')">
                    <i class="fa-solid fa-trash-can"></i> Sí, eliminar producto
                </a>
                <a href="dashboard.php" class="btn-cancel">
                    <i class="fa-solid fa-arrow-left"></i> Cancelar
                </a>
            </div>
        </div>
    </div>

    <script>
        // Toggle menú móvil
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');

        function toggleMenu() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        menuToggle.onclick = toggleMenu;
        overlay.onclick = toggleMenu;

        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) toggleMenu();
            });
        });
    </script>
</body>
</html>