<?php
session_start();
include __DIR__ . '/../conexion.php';
include __DIR__ . '/funciones_carrito.php';

if (!isset($_SESSION['carrito_session'])) {
    $total_carrito = 0;
} else {
    $session_id = $_SESSION['carrito_session'];
    $total_carrito = contar_carrito();
}

$productos_carrito = [];
if ($total_carrito > 0) {
    // Consulta mejorada con información de descuentos
    $sql = "SELECT c.*, p.nombre, p.precio as precio_original, p.imagen, 
                   t.talla, col.nombre as color_nombre,
                   c.precio as precio_con_descuento,
                   (SELECT d.tipo_descuento FROM productos_descuento d 
                    WHERE d.producto_id = p.id AND d.activo = 1 
                    AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= CURDATE())
                    AND (d.fecha_fin IS NULL OR d.fecha_fin >= CURDATE())
                    LIMIT 1) as tipo_descuento_activo,
                   (SELECT d.valor_descuento FROM productos_descuento d 
                    WHERE d.producto_id = p.id AND d.activo = 1 
                    AND (d.fecha_inicio IS NULL OR d.fecha_inicio <= CURDATE())
                    AND (d.fecha_fin IS NULL OR d.fecha_fin >= CURDATE())
                    LIMIT 1) as valor_descuento_activo
            FROM carrito c
            JOIN productos p ON c.producto_id = p.id
            LEFT JOIN tallas t ON c.talla_id = t.id
            LEFT JOIN colores col ON c.color_id = col.id
            WHERE c.session_id = '$session_id'
            ORDER BY c.fecha_agregado DESC";
    
    $productos_carrito = $conn->query($sql);
}

$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('tienda_nombre', 'tienda_whatsapp', 'moneda_simbolo')");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Mensajes de éxito
$mensaje = '';
if (isset($_GET['agregado'])) $mensaje = '✅ Producto agregado al carrito';
if (isset($_GET['actualizado'])) $mensaje = '🔄 Cantidad actualizada';
if (isset($_GET['eliminado'])) $mensaje = '🗑️ Producto eliminado';
if (isset($_GET['vaciado'])) $mensaje = '🧹 Carrito vaciado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Mi Carrito - <?php echo $config['tienda_nombre'] ?? 'Tienda'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0f172a;
            --primary-light: #334155;
            --accent: #ff6b35;
            --accent-light: #ff8c5a;
            --accent-dark: #e55a2b;
            --whatsapp: #25d366;
            --whatsapp-dark: #128C7E;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.05);
            --shadow-lg: 0 15px 25px -8px rgba(0,0,0,0.1);
            --radius-sm: 10px;
            --radius-md: 14px;
            --radius-lg: 20px;
            --radius-full: 999px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: linear-gradient(50deg, #3A6073, #16222ad2);
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            line-height: 1.5;
        }

        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
        }

        /* ===== ESTILOS PARA DESCUENTOS EN CARRITO ===== */
        .discount-badge-small {
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
            margin-left: 8px;
            vertical-align: middle;
        }

        .original-price {
            text-decoration: line-through;
            color: #4a4b4b;
            font-size: 14px;
            margin-right: 8px;
        }

        .final-price {
            color: #000000;
            font-weight: 800;
            font-size: 18px;
        }

        .final-price-large {
            color: #ff6b35;
            font-weight: 800;
            font-size: 20px;
        }

        .price-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .saving-text {
            font-size: 11px;
            color: #10b981;
            background: rgba(16, 185, 129, 0.1);
            padding: 2px 8px;
            border-radius: 20px;
            display: inline-block;
        }

        /* ===== CONTROL DE VISTAS ===== */
        .desktop-view {
            display: block;
        }

        .mobile-view {
            display: none;
        }

        /* ===== NOTIFICACIÓN (compartida) ===== */
        .notificacion {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary);
            color: white;
            padding: 16px 24px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            animation: slideIn 0.3s ease-out;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid var(--accent);
        }

        .notificacion.fade-out {
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

        /* ===== NOTIFICACIÓN DE PEDIDO CONFIRMADO ===== */
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

        @media (max-width: 480px) {
            .notificacion-pedido {
                top: 10px;
                right: 10px;
                left: 10px;
            }
            
            .notificacion-pedido-contenido {
                min-width: auto;
                padding: 12px 16px;
            }
        }

        /* ===== ESTILOS DESKTOP ===== */
        .desktop-view .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .desktop-view .header-section h1 {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
        }

        .desktop-view .cart-counter {
            background: var(--accent);
            color: white;
            font-size: 14px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 30px;
            animation: pulse 2s infinite;
        }

        .desktop-view .btn-back {
            text-decoration: none;
            color: #ffffff;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }

        .desktop-view .btn-back:hover { 
            color: var(--accent); 
            transform: translateX(-3px); 
        }

        .desktop-view .cart-card {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255,255,255,0.7);
        }

        .desktop-view .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
            align-items: center;
            animation: slideIn 0.3s ease-out;
            transition: var(--transition);
        }

        .desktop-view .cart-item:last-child {
            border-bottom: none;
        }

        .desktop-view .cart-item:hover {
            transform: translateX(5px);
            background: rgba(255, 107, 53, 0.02);
            padding-left: 10px;
            border-radius: var(--radius-md);
        }

        .desktop-view .item-img {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-md);
            object-fit: cover;
            background: #f8fafc;
            transition: transform 0.3s;
        }

        .desktop-view .item-img:hover {
            transform: scale(1.05);
        }

        .desktop-view .item-img-placeholder {
            width: 100px;
            height: 100px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: var(--text-muted);
        }

        .desktop-view .item-info h3 {
            font-size: 18px;
            margin-bottom: 4px;
            color: var(--primary);
        }

        .desktop-view .item-meta {
            display: flex;
            gap: 14px;
            font-size: 13px;
            color: #000000;
            margin-bottom: 12px;
        }

        .desktop-view .meta-tag {
            background: #f1f5f9;
            padding: 2px 10px;
            border-radius: 20px;
        }

        .desktop-view .controls-wrapper {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .desktop-view .qty-selector {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border-radius: 12px;
            padding: 4px;
            border: 1px solid var(--border);
        }

        .desktop-view .qty-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .desktop-view .qty-btn:hover { 
            background: var(--accent); 
            color: white; 
            transform: scale(1.1); 
        }

        .desktop-view .qty-input {
            width: 40px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 700;
        }

        .desktop-view .item-price-block {
            text-align: right;
            min-width: 180px;
        }

        .desktop-view .unit-price {
            font-size: 12px;
            color: var(--text-muted);
        }

        .desktop-view .btn-remove {
            background: #fff1f1;
            color: var(--danger);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .desktop-view .btn-remove:hover { 
            background: var(--danger); 
            color: white; 
            transform: rotate(5deg) scale(1.1); 
        }

        .desktop-view .checkout-card {
            margin-top: 30px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .desktop-view .checkout-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .desktop-view .total-label { 
            color: #94a3b8; 
            font-size: 16px; 
        }

        .desktop-view .total-amount { 
            font-size: 32px; 
            font-weight: 800; 
            display: block; 
        }

        .desktop-view .actions-group { 
            display: flex; 
            gap: 16px; 
        }

        .desktop-view .btn-main {
            padding: 16px 32px;
            border-radius: 16px;
            font-weight: 700;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
        }

        .desktop-view .btn-whatsapp { 
            background: var(--whatsapp); 
            color: white; 
        }

        .desktop-view .btn-whatsapp:hover { 
            background: var(--whatsapp-dark); 
            transform: translateY(-3px); 
            box-shadow: 0 10px 20px rgba(37, 211, 102, 0.3); 
        }

        .desktop-view .btn-empty { 
            background: rgba(255,255,255,0.1); 
            color: white; 
        }

        .desktop-view .btn-empty:hover { 
            background: var(--danger); 
            transform: translateY(-3px); 
        }

        .desktop-view .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .desktop-view .empty-icon { 
            font-size: 64px; 
            margin-bottom: 20px; 
            display: block; 
            animation: float 3s ease-in-out infinite; 
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* ===== ESTILOS MOBILE ===== */
        .mobile-view {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .mobile-view .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            padding: 16px;
        }

        .mobile-view .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .mobile-view .back-btn {
            color: #ffffff;
            font-size: 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .mobile-view .back-btn i {
            font-size: 16px;
        }

        .mobile-view .cart-title {
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
        }

        .mobile-view .cart-badge {
            background: var(--accent);
            color: white;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 30px;
        }

        .mobile-view .productos-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 20px;
        }

        .mobile-view .producto-item {
            display: flex;
            gap: 12px;
            background: var(--card-bg);
            border-radius: 16px;
            padding: 12px;
            border: 1px solid var(--border);
            position: relative;
        }

        .mobile-view .item-img-mobile {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            background: #f8fafc;
        }

        .mobile-view .item-details {
            flex: 1;
        }

        .mobile-view .item-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #000000;
        }

        .mobile-view .item-meta-mobile {
            display: flex;
            gap: 8px;
            font-size: 13px;
            color: #000000;
            margin-bottom: 8px;
        }

        .mobile-view .meta-badge {
            background: #e6e3e3;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .mobile-view .price-wrapper-mobile {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .mobile-view .original-price-mobile {
            text-decoration: line-through;
            color: #313131;
            font-size: 12px;
        }

        .mobile-view .final-price-mobile {
            color: #000000;
            font-weight: 800;
            font-size: 16px;
        }

        .mobile-view .discount-badge-mobile {
            background: linear-gradient(135deg, #ff6b35, #ff8c5a);
            color: white;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 20px;
        }

        .mobile-view .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
        }

        .mobile-view .qty-mobile {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border-radius: 20px;
            padding: 2px;
            border: 1px solid var(--border);
            margin-bottom: 8px;
        }

        .mobile-view .qty-btn-mobile {
            width: 28px;
            height: 28px;
            border: none;
            background: white;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            box-shadow: var(--shadow-sm);
        }

        .mobile-view .qty-btn-mobile:hover {
            background: var(--accent);
            color: white;
        }

        .mobile-view .qty-input-mobile {
            width: 30px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 600;
            font-size: 13px;
        }

        .mobile-view .remove-mobile {
            color: var(--danger);
            background: #fff1f1;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
        }

        .mobile-view .remove-mobile:hover {
            background: var(--danger);
            color: white;
        }

        .mobile-view .resumen-mobile {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-radius: 20px;
            padding: 20px;
            margin-top: 20px;
        }

        .mobile-view .resumen-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .mobile-view .resumen-label {
            color: #ffffff;
            font-size: 14px;
        }

        .mobile-view .resumen-total {
            font-size: 24px;
            font-weight: 800;
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
            text-decoration: none;
            margin-bottom: 10px;
        }

        .mobile-view .btn-whatsapp-mobile {
            background: var(--whatsapp);
            color: white;
        }

        .mobile-view .btn-whatsapp-mobile:hover {
            background: var(--whatsapp-dark);
            transform: translateY(-2px);
        }

        .mobile-view .btn-empty-mobile {
            background: rgba(239, 68, 68, 0.1);
            color: white;
        }

        .mobile-view .btn-empty-mobile:hover {
            background: var(--danger);
            transform: translateY(-2px);
        }

        .mobile-view .empty-state-mobile {
            text-align: center;
            padding: 40px 20px;
            background: var(--card-bg);
            border-radius: 20px;
        }

        .mobile-view .empty-icon-mobile {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        /* ===== MODAL PEDIDO ===== */
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
            backdrop-filter: blur(5px);
        }

        .modal-contenido {
            background: white;
            padding: 35px;
            border-radius: 30px;
            max-width: 450px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 30px 60px -15px rgba(0,0,0,0.5);
        }

        .cerrar-modal {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .cerrar-modal:hover {
            color: var(--accent);
            background: #f1f5f9;
        }

        .modal-contenido h2 {
            margin-bottom: 25px;
            color: var(--primary);
            font-size: 26px;
            font-weight: 700;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 15px;
        }

        .campo {
            margin-bottom: 20px;
        }

        .campo label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }

        .campo input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 16px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .campo input:focus {
            border-color: var(--accent);
            outline: none;
            box-shadow: 0 0 0 4px rgba(255,107,53,0.15);
        }

        .campo small {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
        }

        .productos-detalle-modal {
            background: #f8fafc;
            border-radius: 20px;
            padding: 15px;
            margin: 25px 0 20px;
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
        }

        .producto-detalle-item {
            background: white;
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .producto-detalle-item:last-child {
            margin-bottom: 0;
        }

        .producto-detalle-item .producto-nombre {
            font-weight: 700;
            color: var(--primary);
            font-size: 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .producto-detalle-item .detalle-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }

        .producto-detalle-item .detalle-item {
            font-size: 14px;
            color: #334155;
        }

        .producto-detalle-item .detalle-item strong {
            color: #64748b;
            font-weight: 600;
            margin-right: 5px;
        }

        .producto-detalle-item .subtotal-line {
            border-top: 1px dashed #e2e8f0;
            padding-top: 10px;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .producto-detalle-item .subtotal-line span:last-child {
            font-weight: 700;
            color: var(--accent);
            font-size: 16px;
        }

        .total-general-modal {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 18px 20px;
            border-radius: 16px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 18px;
            font-weight: 700;
        }

        .total-general-modal span:last-child {
            font-size: 24px;
        }

        .btn-confirmar {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #25d366, #128C7E);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-confirmar:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(37,211,102,0.3);
        }

        .btn-confirmar i {
            font-size: 22px;
        }

        .modal-mas-pequeno {
            max-width: 480px !important;
            padding: 25px !important;
        }

        .boton-fijo-container {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 0px 0 5px 0;
            margin-top: 5px;
            z-index: 10;
            border-top: 1px solid #e2e8f0;
        }

        .btn-confirmar-fijo {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #25d366, #128C7E);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(37,211,102,0.3);
        }

        .btn-confirmar-fijo:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(37,211,102,0.4);
        }

        .productos-detalle-modal::-webkit-scrollbar {
            width: 6px;
        }

        .productos-detalle-modal::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .productos-detalle-modal::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .productos-detalle-modal::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        @media (min-width: 769px) {
            .desktop-view {
                display: block;
            }
            .mobile-view {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .desktop-view {
                display: none;
            }
            .mobile-view {
                display: block;
            }
            
            .modal-contenido {
                padding: 25px;
            }
            
            .producto-detalle-item .detalle-grid {
                grid-template-columns: 1fr;
                gap: 5px;
            }

            .modal-mas-pequeno {
                padding: 20px !important;
            }
            
            .productos-detalle-modal {
                max-height: 250px;
            }
            
            .total-general-modal span:last-child {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<?php if ($mensaje): ?>
<div class="notificacion" id="notificacion">
    <span><?php echo $mensaje; ?></span>
</div>
<script>
    setTimeout(() => {
        document.getElementById('notificacion').classList.add('fade-out');
        setTimeout(() => {
            document.getElementById('notificacion')?.remove();
        }, 300);
    }, 3000);
</script>
<?php endif; ?>

<!-- ===== NOTIFICACIÓN DE PEDIDO CONFIRMADO ===== -->
<div id="notificacionPedido" class="notificacion-pedido" style="display: none;">
    <div class="notificacion-pedido-contenido">
        <i class="fa-solid fa-circle-check"></i>
        <div>
            <strong>✅ ¡Pedido confirmado!</strong>
            <p>Te contactaremos por WhatsApp en breve</p>
            <a href="../index.php" style="color: white; text-decoration: underline; font-size: 12px; margin-top: 5px; display: block;">
                ← Seguir comprando
            </a>
        </div>
    </div>
</div>

<!-- ===== MODAL PARA PEDIDO DEL CARRITO ===== -->
<div id="modalPedidoCarrito" class="modal-pedido" style="display: none;">
    <div class="modal-contenido modal-mas-pequeno">
        <span class="cerrar-modal" onclick="cerrarModalCarrito()">&times;</span>
        
        <h2><i class="fa-solid fa-cart-shopping" style="color: var(--accent);"></i> Completar pedido</h2>
        
        <form id="formPedidoCarrito" onsubmit="enviarPedidoCarrito(event)">
            <div class="campo">
                <label><i class="fa-solid fa-user" style="margin-right: 5px; color: var(--accent);"></i> Tu nombre *</label>
                <input type="text" id="modal_carrito_nombre" required placeholder="Ej: Juan Pérez">
            </div>
            
            <div class="campo">
                <label><i class="fa-solid fa-location-dot" style="margin-right: 5px; color: var(--accent);"></i> Municipio / Ciudad *</label>
                <input type="text" id="modal_carrito_ciudad" required 
                       placeholder="Ej: Montería, Cereté, Sahagún, Lorica...">
                <small>¿En qué municipio o ciudad recibirás tu pedido?</small>
            </div>
            
            <div class="productos-detalle-modal" id="listaProductosResumen">
                <div style="text-align: center; padding: 30px; color: #94a3b8;">
                    <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px;"></i>
                    <p style="margin-top: 10px;">Cargando productos...</p>
                </div>
            </div>
            
            <div class="total-general-modal">
                <span>TOTAL:</span>
                <span id="resumen_total_final">$0</span>
            </div>
            
            <div class="boton-fijo-container">
                <button type="submit" class="btn-confirmar btn-confirmar-fijo">
                    <i class="fa-brands fa-whatsapp"></i> Confirmar pedido
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== VERSIÓN DESKTOP ===== -->
<div class="desktop-view">
    <div class="container">
        <div class="header-section">
            <a href="../index.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Volver a la tienda
            </a>
            <h1>
                Tu Carrito
                <?php if ($total_carrito > 0): ?>
                    <span class="cart-counter"><?php echo $total_carrito; ?></span>
                <?php endif; ?>
            </h1>
        </div>

        <?php if ($total_carrito > 0 && $productos_carrito && $productos_carrito->num_rows > 0): ?>
            
            <div class="cart-card">
                <div class="productos-lista">
                    <?php 
                    $total_general = 0;
                    $productos_carrito->data_seek(0);
                    while($item = $productos_carrito->fetch_assoc()): 
                        $precio_unitario = $item['precio_con_descuento'];
                        $precio_original = $item['precio_original'];
                        $tiene_descuento = ($precio_original > $precio_unitario);
                        $subtotal = $precio_unitario * $item['cantidad'];
                        $total_general += $subtotal;
                    ?>
                    <div class="cart-item" id="item-<?php echo $item['id']; ?>">
                        <?php if (!empty($item['imagen']) && file_exists("../img/".$item['imagen'])): ?>
                            <a href="../producto.php?id=<?php echo $item['producto_id']; ?>">
                                <img src="../img/<?php echo $item['imagen']; ?>" class="item-img" alt="<?php echo $item['nombre']; ?>">
                            </a>
                        <?php else: ?>
                            <a href="../producto.php?id=<?php echo $item['producto_id']; ?>">
                                <div class="item-img-placeholder">👟</div>
                            </a>
                        <?php endif; ?>
                        
                        <div class="item-info">
                            <a href="../producto.php?id=<?php echo $item['producto_id']; ?>" style="text-decoration: none; color: inherit;">
                                <h3><?php echo $item['nombre']; ?></h3>
                            </a>
                            
                            <div class="item-meta">
                                <?php if ($item['talla']): ?>
                                    <span class="meta-tag">📏 Talla <?php echo $item['talla']; ?></span>
                                <?php endif; ?>
                                <?php if ($item['color_nombre']): ?>
                                    <span class="meta-tag">🎨 <?php echo $item['color_nombre']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="controls-wrapper">
                                <form method="POST" action="actualizar_carrito.php" class="qty-selector">
                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                    <button type="button" class="qty-btn" onclick="this.nextElementSibling.stepDown(); this.form.submit();">−</button>
                                    <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" min="1" class="qty-input" readonly>
                                    <button type="button" class="qty-btn" onclick="this.previousElementSibling.stepUp(); this.form.submit();">+</button>
                                </form>
                                <a href="eliminar_carrito.php?id=<?php echo $item['id']; ?>" 
                                   class="btn-remove" 
                                   onclick="event.preventDefault(); document.getElementById('item-<?php echo $item['id']; ?>').classList.add('removing'); setTimeout(() => { window.location.href = this.href; }, 300);">
                                    ✕
                                </a>
                            </div>
                        </div>

                        <div class="item-price-block">
                            <div class="price-wrapper">
                                <?php if ($tiene_descuento): ?>
                                    <span class="original-price">$<?php echo number_format($precio_original, 0, ',', '.'); ?></span>
                                    <span class="final-price">$<?php echo number_format($precio_unitario, 0, ',', '.'); ?></span>
                                    <?php if ($item['tipo_descuento_activo'] == 'porcentaje' && $item['valor_descuento_activo'] > 0): ?>
                                        <span class="discount-badge-small">-<?php echo $item['valor_descuento_activo']; ?>%</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="final-price">$<?php echo number_format($precio_unitario, 0, ',', '.'); ?></span>
                                <?php endif; ?>
                                <span class="unit-price"></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="checkout-card">
                <div>
                    <span class="total-label">Total del pedido</span>
                    <span class="total-amount">$<?php echo number_format($total_general, 0, ',', '.'); ?></span>
                </div>
                <div class="actions-group">
                    <a href="vaciar_carrito.php" 
                       class="btn-main btn-empty" 
                       onclick="return confirm('¿Estás seguro de vaciar el carrito?')">Vaciar</a>
                    <a href="#" onclick="abrirModalCarrito(); return false;" class="btn-main btn-whatsapp">
                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                    </a>
                </div>
            </div>

        <?php else: ?>
            <div class="cart-card empty-state">
                <span class="empty-icon">🛒</span>
                <h2>Tu carrito está vacío</h2>
                <p style="color: var(--text-muted); margin-bottom: 32px;">Parece que aún no has añadido nada. ¡Tenemos excelentes productos esperándote!</p>
                <a href="../index.php" class="btn-main btn-whatsapp" style="display: inline-flex; background: var(--accent);">
                    <i class="fa-solid fa-store"></i> Ir a la tienda
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== VERSIÓN MOBILE ===== -->
<div class="mobile-view">
    <div class="mobile-container">
        <div class="mobile-header">
            <a href="../index.php" class="back-btn">
                <i class="fa-solid fa-chevron-left"></i> Tienda
            </a>
            <div class="cart-title">
                Mi Carrito
                <?php if ($total_carrito > 0): ?>
                    <span class="cart-badge"><?php echo $total_carrito; ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($total_carrito > 0 && $productos_carrito && $productos_carrito->num_rows > 0): ?>
            
            <div class="productos-list">
                <?php 
                $total_general = 0;
                $productos_carrito->data_seek(0);
                while($item = $productos_carrito->fetch_assoc()): 
                    $precio_unitario = $item['precio_con_descuento'];
                    $precio_original = $item['precio_original'];
                    $tiene_descuento = ($precio_original > $precio_unitario);
                    $subtotal = $precio_unitario * $item['cantidad'];
                    $total_general += $subtotal;
                ?>
                <div class="producto-item" id="mobile-item-<?php echo $item['id']; ?>">
                    <?php if (!empty($item['imagen']) && file_exists("../img/".$item['imagen'])): ?>
                        <a href="../producto.php?id=<?php echo $item['producto_id']; ?>">
                            <img src="../img/<?php echo $item['imagen']; ?>" class="item-img-mobile" alt="<?php echo $item['nombre']; ?>">
                        </a>
                    <?php else: ?>
                        <a href="../producto.php?id=<?php echo $item['producto_id']; ?>">
                            <div class="item-img-mobile" style="display: flex; align-items: center; justify-content: center;">👟</div>
                        </a>
                    <?php endif; ?>
                    
                    <div class="item-details">
                        <a href="../producto.php?id=<?php echo $item['producto_id']; ?>" style="text-decoration: none; color: inherit;">
                            <div class="item-name"><?php echo $item['nombre']; ?></div>
                        </a>
                        
                        <div class="item-meta-mobile">
                            <?php if ($item['talla']): ?>
                                <span class="meta-badge">Talla <?php echo $item['talla']; ?></span>
                            <?php endif; ?>
                            <?php if ($item['color_nombre']): ?>
                                <span class="meta-badge"><?php echo $item['color_nombre']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="price-wrapper-mobile">
                            <?php if ($tiene_descuento): ?>
                                <span class="original-price-mobile">$<?php echo number_format($precio_original, 0, ',', '.'); ?></span>
                                <span class="final-price-mobile">$<?php echo number_format($precio_unitario, 0, ',', '.'); ?></span>
                                <?php if ($item['tipo_descuento_activo'] == 'porcentaje' && $item['valor_descuento_activo'] > 0): ?>
                                    <span class="discount-badge-mobile">-<?php echo $item['valor_descuento_activo']; ?>%</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="final-price-mobile">$<?php echo number_format($precio_unitario, 0, ',', '.'); ?></span>
                            <?php endif; ?>
                            <span style="font-size: 11px; color: #64748b;"></span>
                        </div>
                    </div>

                    <div class="item-actions">
                        <form method="POST" action="actualizar_carrito.php" class="qty-mobile">
                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                            <button type="button" class="qty-btn-mobile" onclick="this.nextElementSibling.stepDown(); this.form.submit();">−</button>
                            <input type="number" name="cantidad" value="<?php echo $item['cantidad']; ?>" min="1" class="qty-input-mobile" readonly>
                            <button type="button" class="qty-btn-mobile" onclick="this.previousElementSibling.stepUp(); this.form.submit();">+</button>
                        </form>
                        <a href="eliminar_carrito.php?id=<?php echo $item['id']; ?>" 
                           class="remove-mobile"
                           onclick="return confirm('¿Eliminar este producto?')">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="resumen-mobile">
                <div class="resumen-row">
                    <span class="resumen-label">Productos:</span>
                    <span><?php echo $productos_carrito->num_rows; ?> items</span>
                </div>
                <div class="resumen-row">
                    <span class="resumen-label">Total a pagar:</span>
                    <span class="resumen-total">$<?php echo number_format($total_general, 0, ',', '.'); ?></span>
                </div>
                
                <a href="#" onclick="abrirModalCarrito(); return false;" class="btn-mobile btn-whatsapp-mobile">
                    <i class="fa-brands fa-whatsapp"></i> Completar pedido
                </a>
                <a href="vaciar_carrito.php" class="btn-mobile btn-empty-mobile" onclick="return confirm('¿Vaciar carrito?')">
                    Vaciar carrito
                </a>
            </div>

        <?php else: ?>
            <div class="empty-state-mobile">
                <span class="empty-icon-mobile">🛒</span>
                <h3>Tu carrito está vacío</h3>
                <p style="color: var(--text-muted); margin: 16px 0;">¡Agrega algunos productos para comenzar!</p>
                <a href="../index.php" class="btn-mobile btn-whatsapp-mobile" style="background: var(--accent);">
                    <i class="fa-solid fa-store"></i> Ver productos
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Función para abrir el modal del carrito
function abrirModalCarrito() {
    <?php if ($total_carrito == 0): ?>
        alert('🛒 Tu carrito está vacío');
        return;
    <?php endif; ?>
    
    actualizarResumenModal();
    document.getElementById('modalPedidoCarrito').style.display = 'flex';
}

function cerrarModalCarrito() {
    document.getElementById('modalPedidoCarrito').style.display = 'none';
}

// Función para actualizar el resumen detallado en el modal
function actualizarResumenModal() {
    const productos = <?php 
        $productos_carrito->data_seek(0);
        $items = [];
        while($item = $productos_carrito->fetch_assoc()) {
            $precio_unitario = $item['precio_con_descuento'];
            $precio_original = $item['precio_original'];
            $tiene_descuento = ($precio_original > $precio_unitario);
            $items[] = [
                'nombre' => $item['nombre'],
                'talla' => $item['talla'] ?? '',
                'color' => $item['color_nombre'] ?? '',
                'precio' => $precio_unitario,
                'precio_original' => $precio_original,
                'tiene_descuento' => $tiene_descuento,
                'descuento_porcentaje' => ($item['tipo_descuento_activo'] == 'porcentaje') ? $item['valor_descuento_activo'] : 0,
                'cantidad' => $item['cantidad'],
                'subtotal' => $precio_unitario * $item['cantidad']
            ];
        }
        echo json_encode($items);
    ?>;
    
    let html = '';
    let totalGeneral = 0;
    
    productos.forEach(p => {
        totalGeneral += p.subtotal;
        
        html += `<div class="producto-detalle-item">`;
        html += `<div class="producto-nombre"><i class="fa-solid fa-box" style="color: var(--accent);"></i> ${p.nombre}`;
        if (p.tiene_descuento && p.descuento_porcentaje > 0) {
            html += `<span class="discount-badge-small" style="margin-left: 8px;">🔥 -${p.descuento_porcentaje}%</span>`;
        }
        html += `</div>`;
        html += `<div class="detalle-grid">`;
        if (p.talla) html += `<div class="detalle-item"><strong>📏 Talla:</strong> ${p.talla}</div>`;
        if (p.color) html += `<div class="detalle-item"><strong>🎨 Color:</strong> ${p.color}</div>`;
        
        if (p.tiene_descuento) {
            html += `<div class="detalle-item"><strong>💰 Precio original:</strong> <span style="text-decoration: line-through; color: #94a3b8;">$${new Intl.NumberFormat().format(p.precio_original)}</span></div>`;
            html += `<div class="detalle-item"><strong>💸 Precio con descuento:</strong> <span style="color: #ff6b35; font-weight: bold;">$${new Intl.NumberFormat().format(p.precio)}</span></div>`;
        } else {
            html += `<div class="detalle-item"><strong>💰 Precio unitario:</strong> $${new Intl.NumberFormat().format(p.precio)}</div>`;
        }
        
        html += `<div class="detalle-item"><strong>✖️ Cantidad:</strong> ${p.cantidad}</div>`;
        html += `</div>`;
        html += `<div class="subtotal-line">`;
        html += `<span>Subtotal:</span>`;
        html += `<span>$${new Intl.NumberFormat().format(p.subtotal)}</span>`;
        html += `</div>`;
        html += `</div>`;
    });
    
    document.getElementById('listaProductosResumen').innerHTML = html;
    document.getElementById('resumen_total_final').innerText = '$' + new Intl.NumberFormat().format(totalGeneral);
}

// Enviar pedido del carrito
async function enviarPedidoCarrito(event) {
    event.preventDefault();
    
    const nombre = document.getElementById('modal_carrito_nombre').value;
    const ciudad = document.getElementById('modal_carrito_ciudad').value;
    
    if (!nombre || !ciudad) {
        alert('Por favor completa todos los campos');
        return;
    }
    
    try {
        const response = await fetch('guardar_pedido_carrito.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                nombre: nombre,
                ciudad: ciudad,
                session_id: '<?php echo $_SESSION['carrito_session'] ?? ''; ?>'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            cerrarModalCarrito();
            
            const noti = document.getElementById('notificacionPedido');
            noti.style.display = 'block';
            
let mensaje = `¡Hola! Gracias por tu pedido en Tienda MS\n\n`;
mensaje += `*CONFIRMACIÓN DE PEDIDO*\n\n`;
mensaje += `*PRODUCTOS*\n`;

data.productos.forEach(p => {
    mensaje += `▸ ${p.nombre}\n`;
    if (p.talla) mensaje += `  Talla: ${p.talla}\n`;
    if (p.color) mensaje += `  Color: ${p.color}\n`;
    mensaje += `  Cantidad: ${p.cantidad}\n`;
    mensaje += `  Precio: $${new Intl.NumberFormat().format(p.precio)}\n`;
    mensaje += `  Subtotal: $${new Intl.NumberFormat().format(p.subtotal)}\n\n`;
});

mensaje += `*TOTAL: $${new Intl.NumberFormat().format(data.total)}*\n\n`;
mensaje += `Te contactaremos en breve para confirmar tu pedido.`;
            
            const tel = data.tienda_whatsapp;
            if (tel) {
                setTimeout(() => {
                    window.open(`https://wa.me/${tel}?text=${encodeURIComponent(mensaje)}`, '_blank');
                    
                    noti.classList.add('fade-out');
                    setTimeout(() => {
                        noti.style.display = 'none';
                        noti.classList.remove('fade-out');
                    }, 300);
                }, 2000);
            }
            
            document.getElementById('formPedidoCarrito').reset();
            
        } else {
            alert('Error: ' + (data.error || 'No se pudo procesar el pedido'));
        }
    } catch (error) {
        alert('Error al enviar el pedido');
        console.error(error);
    }
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalPedidoCarrito');
    if (event.target == modal) {
        cerrarModalCarrito();
    }
}
</script>
</body>
</html>