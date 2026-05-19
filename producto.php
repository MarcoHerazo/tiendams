<?php
include 'conexion.php';
session_start();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id == 0) {
    header("Location: index.php");
    exit;
}
// Obtener datos del producto
$producto = $conn->query("SELECT p.*, c.nombre as categoria_nombre 
                          FROM productos p 
                          LEFT JOIN categorias c ON p.categoria_id = c.id 
                          WHERE p.id = $id");

if ($producto->num_rows == 0) {
    header("Location: index.php");
    exit;
}

$prod = $producto->fetch_assoc();

// ===== OBTENER TIPO DE TALLA DEL PRODUCTO =====
$tipo_talla_producto = $prod['tipo_talla'] ?? 'ambos';

// ===== OBTENER DESCUENTO ACTIVO DEL PRODUCTO =====
$hoy = date('Y-m-d');
$descuento = $conn->query("
    SELECT * FROM productos_descuento 
    WHERE producto_id = $id 
    AND activo = 1 
    AND (fecha_inicio IS NULL OR fecha_inicio <= '$hoy')
    AND (fecha_fin IS NULL OR fecha_fin >= '$hoy')
    ORDER BY id DESC LIMIT 1
");
$desc = $descuento->fetch_assoc();

// Calcular precios con descuento
$precio_original = $prod['precio'];
$precio_descuento = $precio_original;
$tipo_descuento = '';
$valor_descuento = 0;
$porcentaje_ahorro = 0;

if ($desc) {
    $tipo_descuento = $desc['tipo_descuento'];
    $valor_descuento = $desc['valor_descuento'];
    
    if ($tipo_descuento == 'porcentaje') {
        $precio_descuento = $precio_original - ($precio_original * $valor_descuento / 100);
        $porcentaje_ahorro = $valor_descuento;
    } else {
        $precio_descuento = $precio_original - $valor_descuento;
        $porcentaje_ahorro = round(($valor_descuento / $precio_original) * 100);
    }
    
    if ($precio_descuento < 0) $precio_descuento = 0;
}

// ===== CONTADOR DE VISITAS =====
$conn->query("UPDATE productos SET visitas = visitas + 1 WHERE id = $id");

// Obtener imágenes adicionales
$imagenes = $conn->query("SELECT * FROM producto_imagenes WHERE producto_id = $id ORDER BY orden");

// ===== OBTENER COLORES DISPONIBLES (con stock) =====
$colores_disponibles = $conn->query("
    SELECT DISTINCT c.id, c.nombre, c.codigo_hex, SUM(pv.stock) as stock_total
    FROM producto_variantes pv
    JOIN colores c ON pv.color_id = c.id
    WHERE pv.producto_id = $id
    GROUP BY c.id, c.nombre, c.codigo_hex
    HAVING stock_total > 0
    ORDER BY c.nombre
");

// ===== OBTENER TALLAS CON STOCK (para botones) - FILTRADAS POR TIPO =====
$sql_tallas_stock = "
    SELECT DISTINCT t.id, t.talla, t.tipo, SUM(pv.stock) as stock_total
    FROM producto_variantes pv
    JOIN tallas t ON pv.talla_id = t.id
    WHERE pv.producto_id = $id
";
if ($tipo_talla_producto == 'zapatos') {
    $sql_tallas_stock .= " AND t.tipo = 'numerica'";
} elseif ($tipo_talla_producto == 'ropa') {
    $sql_tallas_stock .= " AND t.tipo = 'letra'";
} elseif ($tipo_talla_producto == 'ninguna') {
    $sql_tallas_stock .= " AND 1=0"; // No mostrar ninguna
}
$sql_tallas_stock .= " GROUP BY t.id, t.talla, t.tipo HAVING stock_total > 0 ORDER BY FIELD(t.tipo, 'numerica', 'letra'), CAST(t.talla AS UNSIGNED), t.talla";
$tallas_con_stock = $conn->query($sql_tallas_stock);

// ===== OBTENER TODAS LAS TALLAS (para el selector desplegable) - FILTRADAS POR TIPO =====
if ($tipo_talla_producto == 'zapatos') {
    $sql_todas_tallas = "SELECT id, talla, tipo FROM tallas WHERE tipo = 'numerica'";
} elseif ($tipo_talla_producto == 'ropa') {
    $sql_todas_tallas = "SELECT id, talla, tipo FROM tallas WHERE tipo = 'letra'";
} elseif ($tipo_talla_producto == 'ninguna') {
    $sql_todas_tallas = "SELECT id, talla, tipo FROM tallas WHERE 1=0"; // No mostrar ninguna
} else {
    $sql_todas_tallas = "SELECT id, talla, tipo FROM tallas";
}
$sql_todas_tallas .= " ORDER BY FIELD(tipo, 'numerica', 'letra'), CAST(talla AS UNSIGNED), talla";
$todas_tallas = $conn->query($sql_todas_tallas);

// Array para el selector desplegable
$tallas_selector = [];
while ($t = $todas_tallas->fetch_assoc()) {
    $tallas_selector[] = [
        'id' => $t['id'],
        'talla' => $t['talla'],
        'tipo' => $t['tipo']
    ];
}

// Agrupar colores disponibles
$colores = [];
if ($colores_disponibles && $colores_disponibles->num_rows > 0) {
    while ($c = $colores_disponibles->fetch_assoc()) {
        $colores[$c['id']] = [
            'nombre' => $c['nombre'],
            'hex' => $c['codigo_hex'] ?: '#cccccc'
        ];
    }
}

// Obtener configuración
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('tienda_nombre', 'tienda_whatsapp', 'moneda_simbolo')");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Productos relacionados
$relacionados = $conn->query("
    SELECT p.id, p.nombre, p.precio, p.imagen,
           (SELECT d.tipo_descuento FROM productos_descuento d 
            WHERE d.producto_id = p.id AND d.activo = 1 
            AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= '$hoy')
            AND (d.fecha_fin IS NULL OR d.fecha_fin >= '$hoy')
            LIMIT 1) as tiene_descuento,
           (SELECT d.valor_descuento FROM productos_descuento d 
            WHERE d.producto_id = p.id AND d.activo = 1 
            AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= '$hoy')
            AND (d.fecha_fin IS NULL OR d.fecha_fin >= '$hoy')
            LIMIT 1) as valor_desc,
           (SELECT d.tipo_descuento FROM productos_descuento d 
            WHERE d.producto_id = p.id AND d.activo = 1 
            AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= '$hoy')
            AND (d.fecha_fin IS NULL OR d.fecha_fin >= '$hoy')
            LIMIT 1) as tipo_desc
    FROM productos p 
    WHERE p.categoria_id = " . $prod['categoria_id'] . " 
    AND p.id != $id 
    LIMIT 4
");

function contar_colores_producto($producto_id, $conn) {
    $result = $conn->query("SELECT COUNT(DISTINCT color_id) as total FROM producto_variantes WHERE producto_id = $producto_id");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title><?php echo $prod['nombre']; ?> - <?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #000000;
            --primary-light: #333333;
            --accent: #ff6b35;
            --accent-light: #ff8c5a;
            --accent-dark: #e55a2b;
            --whatsapp: #25d366;
            --whatsapp-dark: #128C7E;
            --fb: #1877f2;
            --bg: #f8fafc;
            --card: #ffffff;
            --text-dark: #000000;
            --text-light: #333333;
            --text-muted: #666666;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.05);
            --shadow-lg: 0 15px 25px -8px rgba(0,0,0,0.1);
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-full: 999px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --discount-badge: #ff6b35;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(50deg, #c4d2da, #99b8ccd2);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-dark);
            line-height: 1.4;
            min-height: 100vh;
        }

        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 5px; 
        }

        .discount-badge {
            position: absolute;
            top: 7px;
            left: 15px;
            background: #ff6b35;  /* ← cambia el color aquí si quieres */
            color: white;
            padding: 8px 15px;
            border-radius: var(--radius-full);
            font-weight: 800;
            font-size: 14px;
            z-index: 10;
            box-shadow: 0 4px 10px rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .discount-badge i { font-size: 12px; }
        
        .original-price {
            font-size: 16px;
            color: var(--text-muted);
            text-decoration: line-through;
            margin-right: 8px;
        }
        
        .price-wrapper {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin: 4px 0 8px;
        }
        
        .current-price {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }
        
        .current-price.with-discount { color: var(--discount-badge); }
        
        .saving-badge {
            background: rgba(238, 68, 6, 0.1);
            color: var(--discount-badge);
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            border: 1px solid rgba(255, 107, 53, 0.2);
            margin-left: 8px;
        }
        
        .related-discount {
            position: absolute;
            top: 8px;
            left: 8px;
            background: var(--discount-badge);
            color: white;
            padding: 3px 8px;
            border-radius: var(--radius-full);
            font-size: 10px;
            font-weight: 700;
            z-index: 5;
        }
        
        .size-opt, .mobile-size {
            padding: 8px 18px;
            border: 1px solid var(--border);
            border-radius: var(--radius-full);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            background: white;
            color: var(--text-dark);
            box-shadow: var(--shadow-sm);
        }
        
        .size-opt:hover, .mobile-size:hover { 
            border-color: var(--accent);
            color: var(--accent);
            transform: translateY(-1px);
        }
        
        .size-opt.selected, .mobile-size.selected { 
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .selector-encargo {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius-full);
            font-size: 14px;
            background: white;
            cursor: pointer;
            color: var(--text-dark);
        }
        
        .selector-encargo:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        .info-encargo {
            font-size: 12px;
            color: var(--accent);
            background: rgba(255, 107, 53, 0.1);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            margin-top: 10px;
            display: inline-block;
        }
        
        .desktop-view { display: block; }
        .mobile-view { display: none; }

        .desktop-view .nav-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            margin-bottom: 20px;
        }

        .desktop-view .back-link {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--card);
            padding: 8px 16px;
            border-radius: var(--radius-full);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            border: 1px solid rgba(255,255,255,0.8);
            font-size: 14px;
        }

        .desktop-view .back-link:hover { 
            transform: translateX(-3px); 
            color: var(--accent);
        }

        .desktop-view .cart-trigger {
            background: var(--primary);
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            position: relative;
            box-shadow: 0 8px 15px -5px rgba(0, 0, 0, 0.3);
            transition: var(--transition);
            border: 2px solid rgba(255,255,255,0.2);
        }

        .desktop-view .cart-trigger:hover { 
            transform: scale(1.03) translateY(-1px);
            background: var(--accent);
        }

        .desktop-view .badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--accent);
            color: white;
            font-size: 10px;
            font-weight: 800;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg);
        }

        .desktop-view .product-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: -26px; 
            background: var(--card);
            padding: 30px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 30px;
            border: 1px solid rgba(255,255,255,0.8);
        }

        .desktop-view .gallery-container { 
            position: sticky; 
            top: 16px; 
            height: fit-content;
        }

.desktop-view .main-stage {
    background: #f5f5f5;  /* ← puedes cambiar este color de fondo */
    border-radius: var(--radius-lg);
    aspect-ratio: 1/1;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    margin-bottom: 15px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-md);
    position: relative;
}

.desktop-view .main-stage img {
    width: 100%;          /* ← cambiar de 80% a 100% */
    height: 100%;         /* ← cambiar de 80% a 100% */
    object-fit: contain;  /* mantiene la imagen completa sin recortar */
    transition: transform 0.4s ease;
    filter: drop-shadow(0 8px 12px rgba(0,0,0,0.1));
    /* quita o comenta la línea: mix-blend-mode: multiply; */
}

        .desktop-view .main-stage:hover img { transform: scale(1.05); }

        .desktop-view .thumbnails { 
            display: flex; 
            gap: 10px; 
            overflow-x: auto; 
            padding: 5px 0;
        }

        .desktop-view .thumb {
            width: 65px;
            height: 65px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            border: 2px solid transparent;
            background: #f8fafc;
            object-fit: cover;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .desktop-view .thumb:hover { 
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .desktop-view .thumb.active { 
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(255, 107, 53, 0.2);
        }

        .desktop-view .product-info { 
            display: flex; 
            flex-direction: column;
            gap: 16px;
        }

        .desktop-view .brand-path {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffffff;
            letter-spacing: 1px;
            background: rgb(245, 71, 7);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            display: inline-block;
            width: fit-content;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .desktop-view .title { 
            font-size: 28px; 
            font-weight: 800; 
            line-height: 1.2; 
            color: var(--primary);
            letter-spacing: -0.5px;
        }

        .desktop-view .price-wrapper {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin: 4px 0 8px;
            flex-wrap: wrap;
        }

        .desktop-view .current-price { 
            font-size: 32px; 
            font-weight: 800; 
            color: var(--text-dark);
            line-height: 1;
        }
        
        .desktop-view .current-price.with-discount { color: #000000; }
        
        .desktop-view .original-price { 
            font-size: 20px; 
            color: #535252;
            text-decoration: line-through;
            font-weight: 400;
        }
        
        .desktop-view .saving-badge {
            background: rgb(245, 71, 7);
            color: #ffffff;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 13px;
            font-weight: 700;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }

        .desktop-view .selection-group {
            margin-bottom: 12px;
        }

        .desktop-view .selection-label {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .desktop-view .options-box { 
            display: flex; 
            gap: 8px; 
            flex-wrap: wrap; 
        }

        .desktop-view .color-opt {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 0 0 1px var(--border);
            transition: var(--transition);
        }

        .desktop-view .color-opt.selected { 
            transform: scale(1.05);
            box-shadow: 0 0 0 2px var(--accent);
        }

        .desktop-view .color-opt:hover { transform: scale(1.05); }

        .desktop-view .action-area { 
            margin: 16px 0 12px; 
        }

        .desktop-view .qty-selector {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            background: #f8fafc;
            padding: 6px 12px;
            border-radius: var(--radius-full);
            border: 1px solid var(--border);
            width: fit-content;
        }

        .desktop-view .qty-selector input {
            width: 50px;
            padding: 8px;
            border-radius: var(--radius-sm);
            border: 1px solid white;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            background: white;
            box-shadow: var(--shadow-sm);
            color: var(--text-dark);
        }

        .desktop-view .btn-main {
            width: 100%;
            padding: 14px 20px;
            border-radius: var(--radius-full);
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .desktop-view .btn-cart { 
            background: var(--primary);
            color: white; 
            margin-bottom: 10px;
            box-shadow: 0 8px 15px -6px rgba(0,0,0,0.4);
        }

        .desktop-view .btn-cart:hover { 
            background: var(--primary-light);
            transform: translateY(-2px);
        }

        .desktop-view .btn-wa { 
            background: var(--whatsapp); 
            color: white; 
            text-decoration: none;
            box-shadow: 0 8px 15px -6px rgba(37,211,102,0.3);
        }

        .desktop-view .btn-wa:hover { 
            background: var(--whatsapp-dark); 
            transform: translateY(-2px);
        }

        .desktop-view .share-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .desktop-view .share-bar {
            display: flex;
            gap: 8px;
            background: #f8fafc;
            padding: 8px;
            border-radius: var(--radius-full);
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border);
        }

        .desktop-view .share-left {
            display: flex;
            gap: 6px;
        }

        .desktop-view .share-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            font-size: 16px;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .desktop-view .share-icon:hover { 
            transform: scale(1.05) translateY(-1px);
        }

        .desktop-view .description-section {
            background: #f8fafc;
            padding: 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            margin-top: 12px;
        }

        .desktop-view .description-text {
            color: var(--text-light);
            line-height: 1.5;
            font-size: 13px;
        }

        .desktop-view .related-section { 
            margin-top: 40px; 
        }

        .desktop-view .related-title { 
            font-size: 22px; 
            font-weight: 800; 
            margin-bottom: 24px; 
            color: var(--primary);
        }
        
        .desktop-view .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .desktop-view .related-card {
            background: var(--card);
            padding: 15px 12px;
            border-radius: var(--radius-md);
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            text-align: center;
            position: relative;
        }

        .desktop-view .related-card:hover { 
            transform: translateY(-5px); 
            border-color: var(--accent);
        }

        .desktop-view .related-img {
            width: 100%;
            height: 140px;
            object-fit: contain;
            margin-bottom: 10px;
        }

        .desktop-view .related-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary);
        }
        
        .desktop-view .related-price {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
        }
        
        .desktop-view .related-price small {
            font-size: 12px;
            font-weight: 400;
            color: var(--text-muted);
            text-decoration: line-through;
            margin-right: 5px;
        }

        /* ===== ESTILOS MOBILE ===== */
        .mobile-view {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .mobile-view .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            margin-top: -10px;
            padding: 16px;
        }

        .mobile-view .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .mobile-view .back-btn {
            color: var(--text-dark);
            font-size: 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 700;
            padding: 8px 12px;
            border-radius: 30px;
            background: #ffffff;
            transition: var(--transition);
            border: 1px solid #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .mobile-view .back-btn:hover {
            color: var(--accent);
            transform: translateX(-3px);
        }

        .mobile-view .mobile-cart {
            position: relative;
            color: var(--text-dark);
            font-size: 20px;
            text-decoration: none;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mobile-view .cart-badge-mobile {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--accent);
            color: white;
            font-size: 10px;
            font-weight: 700;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg);
        }

.mobile-view .mobile-gallery {
    margin-bottom: 20px;
    background: #f5f5f5;  /* ← agregar fondo */
    border-radius: 24px;
    padding: 0;  /* ← cambiar de 16px a 0 */
    position: relative;
    overflow: hidden;  /* ← agregar */
}

.mobile-view .product-image-main {
    width: 100%;
    aspect-ratio: 1/1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0;  /* ← cambiar de 15px a 0 */
    position: relative;
    background: #f5f5f5;
}

.mobile-view .product-image-main img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

        .mobile-view .mobile-thumbnails {
    overflow-x: auto;
    padding: 12px;  /* ← agregar padding */
    -webkit-overflow-scrolling: touch;
    background: white;
}

.mobile-view .thumb-row {
    display: flex;
    gap: 10px;
}

.mobile-view .mobile-thumb {
    width: 55px;  /* ← aumentar de 60px a 70px */
    height: 55px; /* ← aumentar de 60px a 70px */
    border-radius: 12px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid transparent;
    transition: var(--transition);
    background: white;
    flex-shrink: 0;
}
        .mobile-view .mobile-thumb.active {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px rgba(255, 69, 2, 0.79);
        }

        .mobile-view .product-info {
            margin-top: 16px;
        }

        .mobile-view .mobile-category {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffffff;
            letter-spacing: 1px;
            background: rgb(255, 81, 0);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            display: inline-block;
            width: fit-content;
            margin-bottom: 8px;
        }

        .mobile-view .mobile-title {
            font-size: 24px;
            font-weight: 800;
            line-height: 1.2;
            color: #111827;
            margin-bottom: 8px;
        }

        .mobile-view .price-wrapper {
            display: flex;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .mobile-view .mobile-price {
            font-size: 28px;
            font-weight: 800;
            color: #000000;
        }
        
        .mobile-view .mobile-price.with-discount { color: #000000; }
        
        .mobile-view .original-price-mobile {
            font-size: 18px;
            color: #1f2329;
            text-decoration: line-through;
        }
        
        .mobile-view .saving-badge-mobile {
            background: rgb(250, 72, 7);
            color:#ffffff;
            padding: 4px 10px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(255, 107, 53, 0.2);
        }

        .mobile-view .section-label {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .mobile-view .colors-row {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .mobile-view .mobile-color {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid white;
            box-shadow: 0 0 0 1px var(--border);
            transition: var(--transition);
        }

        .mobile-view .mobile-color.selected { 
            box-shadow: 0 0 0 2px var(--accent);
        }

        .mobile-view .sizes-row {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .mobile-view .mobile-size {
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 30px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            background: #ffffff;
            color: #000000;
        }

        .mobile-view .mobile-size.selected {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .mobile-view .selector-encargo-mobile {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 30px;
            font-size: 15px;
            background: white;
            cursor: pointer;
            color: var(--text-dark);
            margin-bottom: 16px;
        }

        .mobile-view .qty-mobile {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .mobile-view .qty-mobile input {
            width: 60px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            color: var(--text-dark);
        }

        .mobile-view .btn-mobile {
            width: 100%;
            padding: 16px;
            border-radius: 30px;
            border: none;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .mobile-view .btn-cart-mobile {
            background: var(--primary);
            color: white;
        }

        .mobile-view .btn-wa-mobile {
            background: var(--whatsapp);
            color: white;
            text-decoration: none;
        }

        .mobile-view .description-mobile {
            background: #ffffff;
            padding: 16px;
            border-radius: 14px;
            margin: 20px 0;
        }

        .mobile-view .share-mobile {
            display: flex;
            gap: 8px;
            background: #f8fafc;
            padding: 8px;
            border-radius: 30px;
            justify-content: space-between;
            margin-top: 16px;
        }

        .mobile-view .share-left {
            display: flex;
            gap: 6px;
        }

        .mobile-view .share-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            font-size: 16px;
        }

        .mobile-view .related-mobile {
            margin-top: 30px;
        }

        .mobile-view .related-title-mobile {
            font-size: 18px;
            font-weight: 800;
            margin-bottom: 16px;
            color: #000000;
        }

        .mobile-view .related-grid-mobile {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .mobile-view .related-card-mobile {
            background: var(--card);
            padding: 12px;
            border-radius: 16px;
            text-decoration: none;
            color: inherit;
            border: 1px solid var(--border);
            text-align: center;
            position: relative;
        }

        .mobile-view .related-img-mobile {
            width: 100%;
            height: 100px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .mobile-view .related-name-mobile {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--primary);
        }

        .mobile-view .related-price-mobile {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .mobile-view .related-price-mobile small {
            font-size: 11px;
            font-weight: 400;
            color: var(--text-muted);
            text-decoration: line-through;
            margin-right: 5px;
        }

        @media (min-width: 769px) {
            .desktop-view { display: block; }
            .mobile-view { display: none; }
        }

        @media (max-width: 768px) {
            .desktop-view { display: none; }
            .mobile-view { display: block; }
        }

        /* Ocultar categoría en desktop */
.desktop-view .brand-path {
    display: none !important;
}

/* Ocultar categoría en mobile */
.mobile-view .mobile-category {
    display: none !important;
}
.notificacion-pedido {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    animation: slideIn 0.3s ease-out;
}

.notificacion-pedido-contenido {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    padding: 16px 24px;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(34,197,94,0.3);
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 320px;
    border-left: 5px solid #15803d;
}

.notificacion-pedido-contenido i {
    font-size: 28px;
}

.notificacion-pedido-contenido strong {
    font-size: 16px;
    display: block;
    margin-bottom: 4px;
}

.notificacion-pedido-contenido p {
    font-size: 14px;
    margin: 0;
    opacity: 0.9;
}

.notificacion-pedido.fade-out {
    animation: slideOut 0.3s ease-out forwards;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
}

@keyframes slideOut {
    from { opacity: 1; transform: translateX(0); }
    to { opacity: 0; transform: translateX(20px); }
}
    </style>
</head>
<body>

<!-- ===== VERSIÓN DESKTOP ===== -->
<div class="desktop-view">
    <div class="container">
        <nav class="nav-bar">
            <a href="index.php" class="back-link">
                <i class="fa-solid fa-chevron-left"></i>
                <span>Explorar Tienda</span>
            </a>

            <?php
            require_once 'carrito/funciones_carrito.php';
            $total_carrito = contar_carrito();
            ?>
            <a href="carrito/carrito.php" class="cart-trigger">
                <i class="fa-solid fa-bag-shopping"></i>
                <?php if ($total_carrito > 0): ?>
                    <span class="badge"><?php echo $total_carrito; ?></span>
                <?php endif; ?>
            </a>
        </nav>

        <main class="product-grid">
            <section class="gallery-container">
                <div class="main-stage">
                    <?php if ($desc): ?>
                        <div class="discount-badge">
                            <i class="fa-solid fa-tag"></i>
                            <?php if ($tipo_descuento == 'porcentaje'): ?>
                                🔥 <?php echo $valor_descuento; ?>% OFF
                            <?php else: ?>
                                🔥 -<?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($valor_descuento, 0); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <img src="img/<?php echo $prod['imagen']; ?>" id="imagenPrincipal" alt="<?php echo $prod['nombre']; ?>">
                </div>
                
                <div class="thumbnails">
                    <img src="img/<?php echo $prod['imagen']; ?>" class="thumb active" onclick="cambiarImagenDesktop('img/<?php echo $prod['imagen']; ?>', this)" alt="Vista principal">
                    <?php if ($imagenes): while($img = $imagenes->fetch_assoc()): ?>
                        <img src="img/<?php echo $img['imagen']; ?>" class="thumb" onclick="cambiarImagenDesktop('img/<?php echo $img['imagen']; ?>', this)" alt="Vista alternativa">
                    <?php endwhile; endif; ?>
                </div>
            </section>

            <section class="product-info">
                <span class="brand-path"><?php echo strtoupper($prod['categoria_nombre'] ?? 'NUEVA COLECCIÓN'); ?></span>
                <h1 class="title"><?php echo $prod['nombre']; ?></h1>
                
                <div class="price-wrapper">
                    <?php if ($desc): ?>
                        <span class="current-price with-discount">
                            <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_descuento, 0); ?>
                        </span>
                        <span class="original-price">
                            <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_original, 0); ?>
                        </span>
                        <span class="saving-badge">
                            <?php if ($tipo_descuento == 'porcentaje'): ?>
                                Ahorras <?php echo $valor_descuento; ?>%
                            <?php else: ?>
                                Ahorras <?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($valor_descuento, 0); ?>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="current-price">
                            <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_original, 0); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <form method="POST" action="carrito/agregar_carrito.php" id="formCarritoDesktop">
                    <input type="hidden" name="producto_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="talla_id" id="talla_desktop">
                    <input type="hidden" name="color_id" id="color_desktop">
                    <input type="hidden" name="precio_final" value="<?php echo $precio_descuento; ?>">
                    <input type="hidden" name="precio_original" value="<?php echo $precio_original; ?>">
                    <input type="hidden" name="tipo_descuento" value="<?php echo $tipo_descuento; ?>">
                    <input type="hidden" name="valor_descuento" value="<?php echo $valor_descuento; ?>">

                    <!-- COLORES -->
                    <?php if (!empty($colores)): ?>
                    <div class="selection-group">
                        <span class="selection-label">🎨 Selecciona el Color</span>
                        <div class="options-box">
                            <?php foreach($colores as $cid => $c): ?>
                                <div class="color-opt" 
                                     style="background: <?php echo $c['hex']; ?>" 
                                     onclick="seleccionarColorDesktop(<?php echo $cid; ?>, this)"
                                     title="<?php echo $c['nombre']; ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- TALLAS DISPONIBLES (ENTREGA INMEDIATA) -->
                    <?php if ($tallas_con_stock && $tallas_con_stock->num_rows > 0): ?>
                    <div class="selection-group">
                        <span class="selection-label">👟 Tallas disponibles (entrega inmediata)</span>
                        <div class="options-box">
                            <?php while($talla = $tallas_con_stock->fetch_assoc()): ?>
                                <div class="size-opt" 
                                     onclick="seleccionarTallaDesktop(<?php echo $talla['id']; ?>, this)"
                                     data-talla-id="<?php echo $talla['id']; ?>">
                                    <?php echo $talla['talla']; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SELECTOR DE TALLAS POR ENCARGO -->
                    <div class="selection-group">
                        <span class="selection-label">📦 Elige la tuya (por encargo)</span>
                        <select id="selector_talla_encargo" class="selector-encargo" onchange="seleccionarTallaEncargo(this)">
                            <option value="">-- Selecciona tu talla --</option>
                            <?php foreach($tallas_selector as $talla): ?>
                                <option value="<?php echo $talla['id']; ?>">
                                    <?php echo $talla['talla']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="action-area">
                        <button type="submit" class="btn-main btn-cart">
                            <i class="fa-solid fa-cart-plus"></i> Añadir al carrito
                        </button>
                    </div>
                </form>

                <a href="javascript:void(0)" onclick="comprarProductoDesktop()" class="btn-main btn-wa">
                    <i class="fa-brands fa-whatsapp"></i> Comprar por WhatsApp
                </a>

                <?php if (!empty($prod['descripcion'])): ?>
                <div class="description-section">
                    <span class="selection-label">📝 Descripción</span>
                    <p class="description-text"><?php echo nl2br(htmlspecialchars($prod['descripcion'])); ?></p>
                </div>
                <?php endif; ?>

                <div class="share-section">
                    <span class="selection-label">📢 Compartir producto</span>
                    <div class="share-bar">
                        <div class="share-left">
                            <a href="https://api.whatsapp.com/send?text=<?php echo urlencode('¡Mira esto! ' . $prod['nombre'] . ' 🔥 ' . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" 
                               target="_blank" class="share-icon" style="background:#25d366" title="WhatsApp">
                               <i class="fa-brands fa-whatsapp"></i>
                            </a>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" 
                               target="_blank" class="share-icon" style="background:#1877f2" title="Facebook">
                               <i class="fa-brands fa-facebook-f"></i>
                            </a>
                        </div>
                        <a href="javascript:void(0)" onclick="copiarLinkDesktop(this)" class="share-icon" style="background: var(--primary);" title="Copiar enlace">
                            <i class="fa-solid fa-link"></i>
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <?php if ($relacionados && $relacionados->num_rows > 0): ?>
        <section class="related-section">
            <h2 class="related-title">También te puede gustar</h2>
            <div class="related-grid">
                <?php while($r = $relacionados->fetch_assoc()): 
                    $r_precio_original = $r['precio'];
                    $r_precio_final = $r_precio_original;
                    $r_tiene_descuento = false;
                    
                    if ($r['tiene_descuento']) {
                        $r_tiene_descuento = true;
                        if ($r['tipo_desc'] == 'porcentaje') {
                            $r_precio_final = $r_precio_original - ($r_precio_original * $r['valor_desc'] / 100);
                        } else {
                            $r_precio_final = $r_precio_original - $r['valor_desc'];
                        }
                    }
                ?>
                    <a href="producto.php?id=<?php echo $r['id']; ?>" class="related-card">
                        <?php if ($r_tiene_descuento): ?>
                            <div class="related-discount">
                                <?php if ($r['tipo_desc'] == 'porcentaje'): ?>
                                    -<?php echo $r['valor_desc']; ?>%
                                <?php else: ?>
                                    -<?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r['valor_desc'], 0); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <img src="img/<?php echo $r['imagen']; ?>" class="related-img" alt="<?php echo $r['nombre']; ?>">
                        <h4 class="related-name"><?php echo $r['nombre']; ?></h4>
                        <span class="related-price">
                            <?php if ($r_tiene_descuento): ?>
                                <small><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r_precio_original, 0); ?></small>
                                <?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r_precio_final, 0); ?>
                            <?php else: ?>
                                <?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r_precio_original, 0); ?>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<!-- ===== VERSIÓN MOBILE ===== -->
<div class="mobile-view">
    <div class="mobile-container">
        <div class="mobile-header">
            <a href="index.php" class="back-btn">
                <i class="fa-solid fa-chevron-left"></i> Explorar Tienda
            </a>
            <a href="carrito/carrito.php" class="mobile-cart">
                <i class="fa-solid fa-bag-shopping"></i>
                <?php if ($total_carrito > 0): ?>
                    <span class="cart-badge-mobile"><?php echo $total_carrito; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="mobile-gallery" style="margin-top: -15px;">
            <div class="product-image-main">
                <?php if ($desc): ?>
                    <div class="discount-badge" style="left: 15px; top: 15px;">
                        <i class="fa-solid fa-tag"></i>
                        <?php if ($tipo_descuento == 'porcentaje'): ?>
                            🔥 <?php echo $valor_descuento; ?>% OFF
                        <?php else: ?>
                            🔥 -<?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($valor_descuento, 0); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <img src="img/<?php echo $prod['imagen']; ?>" alt="<?php echo $prod['nombre']; ?>" id="imagenMobile">
            </div>
            
            <?php if ($imagenes && $imagenes->num_rows > 0): ?>
            <div class="mobile-thumbnails">
                <div class="thumb-row">
                    <img src="img/<?php echo $prod['imagen']; ?>" class="mobile-thumb active" onclick="cambiarImagenMobile('img/<?php echo $prod['imagen']; ?>', this)" alt="Principal">
                    <?php 
                    $imagenes->data_seek(0);
                    while($img = $imagenes->fetch_assoc()): 
                    ?>
                        <img src="img/<?php echo $img['imagen']; ?>" class="mobile-thumb" onclick="cambiarImagenMobile('img/<?php echo $img['imagen']; ?>', this)" alt="Vista">
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="product-info">
            <span class="mobile-category"><?php echo strtoupper($prod['categoria_nombre'] ?? 'NUEVA COLECCIÓN'); ?></span>
            <h1 class="mobile-title"><?php echo $prod['nombre']; ?></h1>
            
            <div class="price-wrapper">
                <?php if ($desc): ?>
                    <div class="mobile-price with-discount">
                        <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_descuento, 0); ?>
                    </div>
                    <div class="original-price-mobile">
                        <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_original, 0); ?>
                    </div>
                    <div class="saving-badge-mobile">
                        <?php if ($tipo_descuento == 'porcentaje'): ?>
                            Ahorras <?php echo $valor_descuento; ?>%
                        <?php else: ?>
                            Ahorras <?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($valor_descuento, 0); ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mobile-price">
                        <?php echo $config['moneda_simbolo'] ?? '$'; ?> <?php echo number_format($precio_original, 0); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="carrito/agregar_carrito.php" id="formCarritoMobile">
                <input type="hidden" name="producto_id" value="<?php echo $id; ?>">
                <input type="hidden" name="talla_id" id="talla_mobile">
                <input type="hidden" name="color_id" id="color_mobile">
                <input type="hidden" name="precio_final" value="<?php echo $precio_descuento; ?>">
                <input type="hidden" name="precio_original" value="<?php echo $precio_original; ?>">
                <input type="hidden" name="tipo_descuento" value="<?php echo $tipo_descuento; ?>">
                <input type="hidden" name="valor_descuento" value="<?php echo $valor_descuento; ?>">

                <!-- COLORES MOBILE -->
                <?php if (!empty($colores)): ?>
                <div class="section-label">🎨 Color</div>
                <div class="colors-row">
                    <?php foreach($colores as $cid => $c): ?>
                        <div class="mobile-color" 
                             style="background: <?php echo $c['hex']; ?>" 
                             onclick="seleccionarColorMobile(<?php echo $cid; ?>, this)"
                             title="<?php echo $c['nombre']; ?>"></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- TALLAS DISPONIBLES MOBILE (ENTREGA INMEDIATA) -->
                <?php if ($tallas_con_stock && $tallas_con_stock->num_rows > 0): 
                    $tallas_con_stock->data_seek(0);
                ?>
                <div class="section-label">👟 Tallas disponibles (entrega inmediata)</div>
                <div class="sizes-row">
                    <?php while($talla = $tallas_con_stock->fetch_assoc()): ?>
                        <div class="mobile-size" 
                             onclick="seleccionarTallaMobile(<?php echo $talla['id']; ?>, this)"
                             data-talla-id="<?php echo $talla['id']; ?>">
                            <?php echo $talla['talla']; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>

                <!-- SELECTOR DE TALLAS POR ENCARGO MOBILE -->
                <div class="section-label">📦 Elige la tuya (por encargo)</div>
                <select id="selector_talla_encargo_mobile" class="selector-encargo-mobile" onchange="seleccionarTallaEncargoMobile(this)">
                    <option value="">-- Selecciona tu talla --</option>
                    <?php foreach($tallas_selector as $talla): ?>
                        <option value="<?php echo $talla['id']; ?>">
                            <?php echo $talla['talla']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-mobile btn-cart-mobile">
                    <i class="fa-solid fa-cart-plus"></i> Añadir al carrito
                </button>
            </form>

            <a href="javascript:void(0)" onclick="comprarProductoMobile()" class="btn-mobile btn-wa-mobile">
                <i class="fa-brands fa-whatsapp"></i> Comprar por WhatsApp
            </a>

            <?php if (!empty($prod['descripcion'])): ?>
            <div class="description-mobile">
                <span class="section-label">📝 Descripción</span>
                <p style="color: var(--text-light); font-size: 13px;"><?php echo nl2br(htmlspecialchars($prod['descripcion'])); ?></p>
            </div>
            <?php endif; ?>

            <div class="share-mobile">
                <div class="share-left">
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode('¡Mira esto! ' . $prod['nombre'] . ' 🔥 ' . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" 
                       target="_blank" class="share-icon" style="background:#25d366">
                       <i class="fa-brands fa-whatsapp"></i>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"); ?>" 
                       target="_blank" class="share-icon" style="background:#1877f2">
                       <i class="fa-brands fa-facebook-f"></i>
                    </a>
                </div>
                <a href="javascript:void(0)" onclick="copiarLinkMobile(this)" class="share-icon" style="background: var(--primary);">
                    <i class="fa-solid fa-link"></i>
                </a>
            </div>
        </div>

        <?php if ($relacionados && $relacionados->num_rows > 0): ?>
        <div class="related-mobile">
            <h3 class="related-title-mobile">También te puede gustar</h3>
            <div class="related-grid-mobile">
                <?php 
                $relacionados->data_seek(0);
                while($r = $relacionados->fetch_assoc()): 
                    $r_precio_original = $r['precio'];
                    $r_precio_final = $r_precio_original;
                    $r_tiene_descuento = false;
                    
                    if ($r['tiene_descuento']) {
                        $r_tiene_descuento = true;
                        if ($r['tipo_desc'] == 'porcentaje') {
                            $r_precio_final = $r_precio_original - ($r_precio_original * $r['valor_desc'] / 100);
                        } else {
                            $r_precio_final = $r_precio_original - $r['valor_desc'];
                        }
                    }
                ?>
                <a href="producto.php?id=<?php echo $r['id']; ?>" class="related-card-mobile">
                    <?php if ($r_tiene_descuento): ?>
                        <div class="related-discount" style="top: 5px; left: 5px; padding: 2px 6px; font-size: 9px;">
                            <?php if ($r['tipo_desc'] == 'porcentaje'): ?>
                                -<?php echo $r['valor_desc']; ?>%
                            <?php else: ?>
                                -<?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r['valor_desc'], 0); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <img src="img/<?php echo $r['imagen']; ?>" class="related-img-mobile" alt="<?php echo $r['nombre']; ?>">
                    <div class="related-name-mobile"><?php echo $r['nombre']; ?></div>
                    <div class="related-price-mobile">
                        <?php if ($r_tiene_descuento): ?>
                            <small><?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r_precio_original, 0); ?></small>
                            <?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r_precio_final, 0); ?>
                        <?php else: ?>
                            <?php echo $config['moneda_simbolo'] ?? '$'; ?><?php echo number_format($r_precio_original, 0); ?>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // ===== FUNCIONES PARA DESKTOP =====
    function cambiarImagenDesktop(src, el) {
        document.getElementById('imagenPrincipal').src = src;
        document.querySelectorAll('.desktop-view .thumb').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }

    function seleccionarColorDesktop(id, el) {
        document.getElementById('color_desktop').value = id;
        document.querySelectorAll('.desktop-view .color-opt').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
    }

    function seleccionarTallaDesktop(id, el) {
        document.getElementById('talla_desktop').value = id;
        document.querySelectorAll('.desktop-view .size-opt').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');
        // Limpiar selector de encargo
        document.getElementById('selector_talla_encargo').value = '';
    }

    function seleccionarTallaEncargo(select) {
        const id = select.value;
        if (id) {
            document.getElementById('talla_desktop').value = id;
            // Limpiar selección de botones
            document.querySelectorAll('.desktop-view .size-opt').forEach(s => s.classList.remove('selected'));
        }
    }

    function copiarLinkDesktop(btn) {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            const icon = btn.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fa-solid fa-check';
            btn.style.background = '#25d366';
            setTimeout(() => {
                icon.className = originalClass;
                btn.style.background = 'var(--primary)';
            }, 2000);
        });
    }

    function comprarProductoDesktop() {
        abrirModalPedido();
    }

    // ===== FUNCIONES PARA MOBILE =====
    function cambiarImagenMobile(src, el) {
        document.getElementById('imagenMobile').src = src;
        document.querySelectorAll('.mobile-view .mobile-thumb').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }

    function seleccionarColorMobile(id, el) {
        document.getElementById('color_mobile').value = id;
        document.querySelectorAll('.mobile-view .mobile-color').forEach(c => c.classList.remove('selected'));
        el.classList.add('selected');
    }

    function seleccionarTallaMobile(id, el) {
        document.getElementById('talla_mobile').value = id;
        document.querySelectorAll('.mobile-view .mobile-size').forEach(s => s.classList.remove('selected'));
        el.classList.add('selected');
        // Limpiar selector de encargo
        document.getElementById('selector_talla_encargo_mobile').value = '';
    }

    function seleccionarTallaEncargoMobile(select) {
        const id = select.value;
        if (id) {
            document.getElementById('talla_mobile').value = id;
            // Limpiar selección de botones
            document.querySelectorAll('.mobile-view .mobile-size').forEach(s => s.classList.remove('selected'));
        }
    }

    function copiarLinkMobile(btn) {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            const icon = btn.querySelector('i');
            const originalClass = icon.className;
            icon.className = 'fa-solid fa-check';
            btn.style.background = '#25d366';
            setTimeout(() => {
                icon.className = originalClass;
                btn.style.background = 'var(--primary)';
            }, 2000);
        });
    }

    function comprarProductoMobile() {
        abrirModalPedido();
    }

    // ===== VALIDACIONES =====
    document.getElementById('formCarritoDesktop')?.addEventListener('submit', function(e) {
        <?php if (!empty($colores)): ?>
        if (!document.querySelector('.desktop-view .color-opt.selected')) {
            e.preventDefault();
            alert('⚠️ Por favor selecciona un color');
            return false;
        }
        <?php endif; ?>
        if (!document.getElementById('talla_desktop').value) {
            e.preventDefault();
            alert('⚠️ Por favor selecciona una talla (en botones o en el selector de encargo)');
            return false;
        }
    });

    document.getElementById('formCarritoMobile')?.addEventListener('submit', function(e) {
        <?php if (!empty($colores)): ?>
        if (!document.querySelector('.mobile-view .mobile-color.selected')) {
            e.preventDefault();
            alert('⚠️ Por favor selecciona un color');
            return false;
        }
        <?php endif; ?>
        if (!document.getElementById('talla_mobile').value) {
            e.preventDefault();
            alert('⚠️ Por favor selecciona una talla (en botones o en el selector de encargo)');
            return false;
        }
    });
</script>

<!-- ===== MODAL PARA PEDIDO RÁPIDO ===== -->
<div id="modalPedido" class="modal-pedido" style="display: none;">
    <div class="modal-contenido">
        <span class="cerrar-modal" onclick="cerrarModalPedido()">&times;</span>
        
        <h2 style="margin-bottom: 20px; color: #333;">🛍️ Completar pedido</h2>
        
        <form id="formPedido" onsubmit="enviarPedido(event)">
            <input type="hidden" id="modal_producto_id" name="producto_id" value="<?php echo $id; ?>">
            <input type="hidden" id="modal_talla_id" name="talla_id">
            <input type="hidden" id="modal_talla_nombre" name="talla_nombre">
            <input type="hidden" id="modal_color_id" name="color_id">
            <input type="hidden" id="modal_color_nombre" name="color_nombre">
            <input type="hidden" id="modal_cantidad" name="cantidad" value="1">
            <input type="hidden" id="modal_precio" name="precio" value="<?php echo $precio_descuento; ?>">
            
            <div class="campo">
                <label>👤 Tu nombre *</label>
                <input type="text" id="modal_nombre" required placeholder="Ej: Juan Pérez">
            </div>

            <div class="campo">
                <label>📍 Municipio / Ciudad *</label>
                <input type="text" id="modal_ciudad" required 
                       placeholder="Ej: Montería, Sahagún, Planeta Rica, Ayapel...">
                <small>¿En qué municipio o ciudad de Córdoba recibirás tu pedido?</small>
            </div>
            
            <div class="resumen-pedido">
                <h3 style="margin-bottom: 10px;">📦 Resumen</h3>
                <p><strong>Producto:</strong> <span id="resumen_producto"><?php echo $prod['nombre']; ?></span></p>
                <p><strong>Talla:</strong> <span id="resumen_talla">-</span></p>
                <p><strong>Color:</strong> <span id="resumen_color">-</span></p>
                <p><strong>Precio:</strong> $<span id="resumen_precio"><?php echo number_format($precio_descuento, 0); ?></span></p>
                <p><strong>Total:</strong> $<span id="resumen_total"><?php echo number_format($precio_descuento, 0); ?></span></p>
            </div>
            
            <button type="submit" class="btn-confirmar">
                ✅ Confirmar pedido
            </button>
        </form>
    </div>
</div>

<style>
.modal-pedido {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.8);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-contenido {
    background: white;
    padding: 30px;
    border-radius: 20px;
    max-width: 450px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.cerrar-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 28px;
    cursor: pointer;
    color: #666;
    transition: color 0.3s;
}

.cerrar-modal:hover {
    color: var(--accent);
}

.campo {
    margin-bottom: 20px;
}

.campo label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.campo input {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: border-color 0.3s;
}

.campo input:focus {
    border-color: var(--accent);
    outline: none;
}

.campo small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 12px;
}

.resumen-pedido {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 12px;
    margin: 20px 0;
    border: 1px solid #e0e0e0;
}

.resumen-pedido p {
    margin: 8px 0;
    color: #555;
    font-size: 14px;
}

.btn-confirmar {
    width: 100%;
    padding: 16px;
    background: #25D366;
    color: white;
    border: none;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-confirmar:hover {
    background: #128C7E;
}

@media (max-width: 480px) {
    .modal-contenido {
        padding: 20px;
    }
}
</style>

<script>
let tallaSeleccionada = '';
let colorSeleccionado = '';
let tallaIdSeleccionada = 0;
let colorIdSeleccionado = 0;

function abrirModalPedido() {
    if (window.innerWidth > 768) {
        const tallaElem = document.querySelector('.desktop-view .size-opt.selected');
        const colorElem = document.querySelector('.desktop-view .color-opt.selected');
        const selectorEncargo = document.getElementById('selector_talla_encargo');
        
        if (tallaElem) {
            tallaSeleccionada = tallaElem.innerText;
            tallaIdSeleccionada = document.getElementById('talla_desktop').value;
        } else if (selectorEncargo && selectorEncargo.value) {
            const option = selectorEncargo.options[selectorEncargo.selectedIndex];
            tallaSeleccionada = option.text;
            tallaIdSeleccionada = selectorEncargo.value;
        }
        
        if (colorElem) {
            colorSeleccionado = colorElem.title;
            colorIdSeleccionado = document.getElementById('color_desktop').value;
        }
    } else {
        const tallaElem = document.querySelector('.mobile-view .mobile-size.selected');
        const colorElem = document.querySelector('.mobile-view .mobile-color.selected');
        const selectorEncargo = document.getElementById('selector_talla_encargo_mobile');
        
        if (tallaElem) {
            tallaSeleccionada = tallaElem.innerText;
            tallaIdSeleccionada = document.getElementById('talla_mobile').value;
        } else if (selectorEncargo && selectorEncargo.value) {
            const option = selectorEncargo.options[selectorEncargo.selectedIndex];
            tallaSeleccionada = option.text;
            tallaIdSeleccionada = selectorEncargo.value;
        }
        
        if (colorElem) {
            colorSeleccionado = colorElem.title;
            colorIdSeleccionado = document.getElementById('color_mobile').value;
        }
    }
    
    <?php if (!empty($colores)): ?>
    if (!colorSeleccionado) {
        alert('⚠️ Por favor selecciona un color');
        return;
    }
    <?php endif; ?>
    
    if (!tallaSeleccionada) {
        alert('⚠️ Por favor selecciona una talla (en botones o en el selector de encargo)');
        return;
    }
    
    document.getElementById('modal_talla_id').value = tallaIdSeleccionada;
    document.getElementById('modal_talla_nombre').value = tallaSeleccionada;
    document.getElementById('modal_color_id').value = colorIdSeleccionado;
    document.getElementById('modal_color_nombre').value = colorSeleccionado;
    
    document.getElementById('resumen_talla').innerText = tallaSeleccionada || '-';
    document.getElementById('resumen_color').innerText = colorSeleccionado || '-';
    
    document.getElementById('modalPedido').style.display = 'flex';
}

function cerrarModalPedido() {
    document.getElementById('modalPedido').style.display = 'none';
}

async function enviarPedido(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('nombre', document.getElementById('modal_nombre').value);
    formData.append('ciudad', document.getElementById('modal_ciudad').value);
    formData.append('producto_id', document.getElementById('modal_producto_id').value);
    formData.append('talla_id', document.getElementById('modal_talla_id').value);
    formData.append('talla_nombre', document.getElementById('modal_talla_nombre').value);
    formData.append('color_id', document.getElementById('modal_color_id').value);
    formData.append('color_nombre', document.getElementById('modal_color_nombre').value);
    formData.append('cantidad', 1);
    formData.append('precio', document.getElementById('modal_precio').value);
    
    try {
        const response = await fetch('guardar_pedido.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
if (data.success) {
    let mensaje = `¡Hola! Gracias por tu pedido en Tienda MS\n\n`;
    mensaje += `*CONFIRMACIÓN DE PEDIDO*\n\n`;
    mensaje += `*PRODUCTO*\n`;
    mensaje += `▸ ${data.producto_nombre}\n`;
    if (data.talla) mensaje += `  Talla: ${data.talla}\n`;
    if (data.color) mensaje += `  Color: ${data.color}\n`;
    mensaje += `  Cantidad: 1\n`;
    mensaje += `  Precio: $${new Intl.NumberFormat().format(data.precio)}\n\n`;
    mensaje += `*TOTAL: $${new Intl.NumberFormat().format(data.total)}*\n\n`;
    mensaje += `Te contactaremos en breve para confirmar tu pedido.`;
    
    const tel = data.tienda_whatsapp;
    if (tel) {
        window.open(`https://wa.me/${tel}?text=${encodeURIComponent(mensaje)}`, '_blank');
    }
    
cerrarModalPedido();

// Mostrar notificación elegante
const notificacion = document.createElement('div');
notificacion.className = 'notificacion-pedido';
notificacion.innerHTML = `
    <div class="notificacion-pedido-contenido">
        <i class="fa-solid fa-circle-check"></i>
        <div>
            <strong>✅ ¡Pedido confirmado!</strong>
            <p>Te contactaremos por WhatsApp en breve</p>
            <a href="index.php" style="color: white; text-decoration: underline; font-size: 12px; margin-top: 5px; display: block;">
                ← Seguir comprando
            </a>
        </div>
    </div>
`;
document.body.appendChild(notificacion);

// Animación de entrada
setTimeout(() => {
    notificacion.style.display = 'block';
}, 100);

// Desaparece después de 5 segundos
setTimeout(() => {
    notificacion.classList.add('fade-out');
    setTimeout(() => {
        notificacion.remove();
    }, 300);
}, 5000);
}
    } catch (error) {
        alert('Error al enviar el pedido');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    window.comprarProductoDesktop = function() {
        abrirModalPedido();
    };
    
    window.comprarProductoMobile = function() {
        abrirModalPedido();
    };
});

window.onclick = function(event) {
    const modal = document.getElementById('modalPedido');
    if (event.target == modal) {
        cerrarModalPedido();
    }
}
</script>
</body>
</html>