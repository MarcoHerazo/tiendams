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

// Agregar color
if (isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $codigo_hex = trim($_POST['codigo_hex']);
    
    if (!empty($nombre)) {
        $stmt = $conn->prepare("INSERT INTO colores (nombre, codigo_hex) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $codigo_hex);
        
        if ($stmt->execute()) {
            $mensaje = "Color agregado exitosamente";
        } else {
            $error = "Error al agregar el color";
        }
    } else {
        $error = "El nombre del color es obligatorio";
    }
}

// Editar color
if (isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $codigo_hex = trim($_POST['codigo_hex']);
    
    if (!empty($nombre)) {
        $stmt = $conn->prepare("UPDATE colores SET nombre = ?, codigo_hex = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nombre, $codigo_hex, $id);
        
        if ($stmt->execute()) {
            $mensaje = "Color actualizado exitosamente";
        } else {
            $error = "Error al actualizar el color";
        }
    } else {
        $error = "El nombre del color es obligatorio";
    }
}

// Eliminar color
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    // Verificar si hay variantes usando este color
    $check = $conn->query("SELECT COUNT(*) as total FROM producto_variantes WHERE color_id = $id");
    $result = $check->fetch_assoc();
    
    if ($result['total'] == 0) {
        $conn->query("DELETE FROM colores WHERE id = $id");
        $mensaje = "Color eliminado exitosamente";
    } else {
        $error = "No se puede eliminar: hay productos usando este color";
    }
}

// Obtener todos los colores
$colores = $conn->query("SELECT * FROM colores ORDER BY nombre");

// Función para obtener nombre sugerido del color basado en HEX
function obtenerNombreColor($hex) {
    $hex = strtoupper($hex);
    
    // Colores comunes predefinidos
    $colores_conocidos = [
        '#000000' => 'Negro',
        '#FFFFFF' => 'Blanco',
        '#FF0000' => 'Rojo',
        '#00FF00' => 'Verde',
        '#0000FF' => 'Azul',
        '#FFFF00' => 'Amarillo',
        '#FF00FF' => 'Magenta',
        '#00FFFF' => 'Cian',
        '#FFA500' => 'Naranja',
        '#800080' => 'Morado',
        '#FFC0CB' => 'Rosado',
        '#808080' => 'Gris',
        '#A52A2A' => 'Marrón',
        '#87CEEB' => 'Celeste',
        '#008000' => 'Verde oscuro',
        '#800000' => 'Borgoña',
        '#C0C0C0' => 'Plateado',
        '#FFD700' => 'Dorado',
        '#CD7F32' => 'Bronce'
    ];
    
    if (isset($colores_conocidos[$hex])) {
        return $colores_conocidos[$hex];
    }
    
    // Si no está en la lista, generar un nombre descriptivo
    $r = hexdec(substr($hex, 1, 2));
    $g = hexdec(substr($hex, 3, 2));
    $b = hexdec(substr($hex, 5, 2));
    
    $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
    
    if ($brightness > 200) return 'Muy claro';
    if ($brightness < 55) return 'Muy oscuro';
    
    // Determinar color dominante
    if ($r > $g && $r > $b) return 'Rojo' . ($brightness > 150 ? ' claro' : ($brightness < 80 ? ' oscuro' : ''));
    if ($g > $r && $g > $b) return 'Verde' . ($brightness > 150 ? ' claro' : ($brightness < 80 ? ' oscuro' : ''));
    if ($b > $r && $b > $g) return 'Azul' . ($brightness > 150 ? ' claro' : ($brightness < 80 ? ' oscuro' : ''));
    
    return 'Color personalizado';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Colores - Tienda MS</title>
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

        /* ===== FORMULARIO ===== */
        .form-container {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .form-container h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .form-container h2 i { color: var(--primary); }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .hex-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .hex-input-group input[type="color"] {
            width: 50px;
            height: 50px;
            padding: 0;
            cursor: pointer;
        }

        .hex-input-group input[type="text"] {
            flex: 1;
        }

        .color-preview {
            margin-bottom: 20px;
        }

        .preview-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .preview-box {
            width: 100%;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .preview-box span {
            font-weight: 600;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 30px;
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

        /* ===== LISTA DE COLORES ===== */
        .colores-list {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }

        .colores-list h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .colores-list h2 i { color: var(--primary); }

        .colores-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
        }

        .color-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .color-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .color-header {
            padding: 16px;
            text-align: center;
            transition: var(--transition);
        }

        .color-nombre {
            font-size: 1.1rem;
            font-weight: 700;
            display: block;
            margin-bottom: 5px;
        }

        .color-hex {
            font-size: 0.8rem;
            opacity: 0.8;
            font-family: monospace;
        }

        .color-info {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.2);
        }

        .info-item {
            display: flex;
            gap: 5px;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .info-label {
            color: var(--text-muted);
        }

        .info-value {
            color: var(--text-secondary);
            font-weight: 600;
        }

        .color-acciones {
            display: flex;
            gap: 10px;
            padding: 12px 16px;
        }

        .btn-editar, .btn-eliminar {
            flex: 1;
            padding: 8px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-editar {
            background: rgba(59, 130, 246, 0.1);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-editar:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-eliminar {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-eliminar:hover:not(.disabled) {
            background: var(--danger);
            color: white;
        }

        .btn-eliminar.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
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
        }

        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .close:hover { color: var(--primary); }

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

        .btn-secondary:hover { background: rgba(255,255,255,0.2); }

        .success-message, .error-message {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: rgba(16,185,129,0.1);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.3);
        }

        .error-message {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
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
            
            .form-row { grid-template-columns: 1fr; gap: 15px; }
            .colores-grid { grid-template-columns: 1fr; }
            .color-card { margin-bottom: 12px; }
            .modal-content { padding: 20px; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .modal-actions { flex-direction: column; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
            .hex-input-group { flex-direction: column; }
            .hex-input-group input[type="color"] { width: 100%; height: 45px; }
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
                <a href="colores.php" class="nav-item active"><i class="fa-solid fa-droplet"></i> Colores</a>
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
            <h1><i class="fa-solid fa-palette"></i> Colores</h1>
            <p>Administra los colores disponibles para tus productos</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="success-message"><i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario para nuevo color -->
        <div class="form-container">
            <h2><i class="fa-solid fa-plus"></i> Agregar Nuevo Color</h2>
            <form method="POST" id="colorForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre">Nombre del color *</label>
                        <input type="text" id="nombre" name="nombre" required 
                               placeholder="Ej: Negro, Blanco, Rojo..." 
                               onkeyup="actualizarPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="codigo_hex">Código HEX</label>
                        <div class="hex-input-group">
                            <input type="color" id="colorPicker" value="#000000" onchange="sincronizarColor()">
                            <input type="text" id="codigo_hex" name="codigo_hex" 
                                   placeholder="#000000" value="#000000"
                                   onchange="sincronizarPicker()">
                        </div>
                    </div>
                </div>

                <!-- Vista previa del color -->
                <div class="color-preview">
                    <div class="preview-label">Vista previa:</div>
                    <div class="preview-box" id="previewBox">
                        <span id="previewText">Negro</span>
                    </div>
                </div>
                
                <button type="submit" name="agregar" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Agregar Color
                </button>
            </form>
        </div>

        <!-- Lista de colores -->
        <div class="colores-list">
            <h2><i class="fa-solid fa-list"></i> Colores Existentes</h2>
            
            <?php if ($colores->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-palette"></i>
                    <p>No hay colores aún. Agrega el primero.</p>
                </div>
            <?php else: ?>
                <div class="colores-grid">
                    <?php 
                    $colores->data_seek(0);
                    while($row = $colores->fetch_assoc()): 
                        $count = $conn->query("SELECT COUNT(*) as total FROM producto_variantes WHERE color_id = " . $row['id']);
                        $total_usos = $count->fetch_assoc()['total'];
                        
                        $hex = $row['codigo_hex'] ?? '#cccccc';
                        $r = hexdec(substr($hex, 1, 2));
                        $g = hexdec(substr($hex, 3, 2));
                        $b = hexdec(substr($hex, 5, 2));
                        $brightness = ($r * 299 + $g * 587 + $b * 114) / 1000;
                        $text_color = $brightness > 128 ? '#000000' : '#ffffff';
                    ?>
                        <div class="color-card">
                            <div class="color-header" style="background-color: <?php echo $hex; ?>; color: <?php echo $text_color; ?>;">
                                <span class="color-nombre"><?php echo htmlspecialchars($row['nombre']); ?></span>
                                <span class="color-hex"><?php echo $hex; ?></span>
                            </div>
                            
                            <div class="color-info">
                                <div class="info-item">
                                    <span class="info-label">ID:</span>
                                    <span class="info-value">#<?php echo $row['id']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Usos:</span>
                                    <span class="info-value"><?php echo $total_usos; ?> productos</span>
                                </div>
                            </div>

                            <div class="color-acciones">
                                <button onclick="editarColor(<?php echo $row['id']; ?>)" class="btn-editar">
                                    <i class="fa-solid fa-pen"></i> Editar
                                </button>
                                
                                <?php if ($total_usos == 0): ?>
                                    <a href="?eliminar=<?php echo $row['id']; ?>" 
                                       class="btn-eliminar" 
                                       onclick="return confirm('¿Eliminar este color?')">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </a>
                                <?php else: ?>
                                    <button class="btn-eliminar disabled" disabled 
                                            title="No se puede eliminar: hay productos con este color">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para editar color -->
    <div id="editarModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fa-solid fa-pen"></i> Editar Color</h2>
            
            <form method="POST" id="editarForm">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_nombre">Nombre del color *</label>
                        <input type="text" id="edit_nombre" name="nombre" required 
                               onkeyup="actualizarEditPreview()">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_codigo_hex">Código HEX</label>
                        <div class="hex-input-group">
                            <input type="color" id="edit_colorPicker" value="#000000" onchange="sincronizarEditColor()">
                            <input type="text" id="edit_codigo_hex" name="codigo_hex" 
                                   placeholder="#000000" onchange="sincronizarEditPicker()">
                        </div>
                    </div>
                </div>

                <!-- Vista previa en edición -->
                <div class="color-preview">
                    <div class="preview-label">Vista previa:</div>
                    <div class="preview-box" id="editPreviewBox">
                        <span id="editPreviewText"></span>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="editar" class="btn-primary">
                        <i class="fa-solid fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const colores = <?php 
            $colores->data_seek(0);
            $colores_array = [];
            while($row = $colores->fetch_assoc()) {
                $colores_array[] = $row;
            }
            echo json_encode($colores_array); 
        ?>;

        // Función para obtener nombre sugerido del color
        function obtenerNombreSugerido(hex) {
            const coloresConocidos = {
                '#000000': 'Negro',
                '#FFFFFF': 'Blanco',
                '#FF0000': 'Rojo',
                '#00FF00': 'Verde',
                '#0000FF': 'Azul',
                '#FFFF00': 'Amarillo',
                '#FFA500': 'Naranja',
                '#800080': 'Morado',
                '#FFC0CB': 'Rosado',
                '#808080': 'Gris',
                '#8B4513': 'Marrón',
                '#87CEEB': 'Celeste'
            };
            
            hex = hex.toUpperCase();
            if (coloresConocidos[hex]) return coloresConocidos[hex];
            
            // Si no está en la lista, generar nombre descriptivo
            const r = parseInt(hex.substr(1, 2), 16);
            const g = parseInt(hex.substr(3, 2), 16);
            const b = parseInt(hex.substr(5, 2), 16);
            const brightness = (r * 299 + g * 587 + b * 114) / 1000;
            
            if (r > g && r > b) return brightness > 150 ? 'Rojo claro' : (brightness < 80 ? 'Rojo oscuro' : 'Rojo');
            if (g > r && g > b) return brightness > 150 ? 'Verde claro' : (brightness < 80 ? 'Verde oscuro' : 'Verde');
            if (b > r && b > g) return brightness > 150 ? 'Azul claro' : (brightness < 80 ? 'Azul oscuro' : 'Azul');
            
            if (r > 200 && g > 200 && b > 200) return 'Muy claro';
            if (r < 80 && g < 80 && b < 80) return 'Muy oscuro';
            
            return 'Color personalizado';
        }

        function sincronizarColor() {
            const picker = document.getElementById('colorPicker');
            const input = document.getElementById('codigo_hex');
            const nombreInput = document.getElementById('nombre');
            input.value = picker.value;
            
            // Sugerir nombre basado en el HEX
            const nombreSugerido = obtenerNombreSugerido(picker.value);
            if (nombreInput.value === '' || nombreInput.value === nombreSugerido || confirm('¿Deseas usar el nombre sugerido "' + nombreSugerido + '" para este color?')) {
                nombreInput.value = nombreSugerido;
            }
            
            actualizarPreview();
        }

        function sincronizarPicker() {
            const input = document.getElementById('codigo_hex');
            const picker = document.getElementById('colorPicker');
            if (/^#[0-9A-F]{6}$/i.test(input.value)) {
                picker.value = input.value;
                
                // Sugerir nombre basado en el HEX
                const nombreSugerido = obtenerNombreSugerido(input.value);
                const nombreInput = document.getElementById('nombre');
                if (nombreInput.value === '' || confirm('¿Deseas usar el nombre sugerido "' + nombreSugerido + '" para este color?')) {
                    nombreInput.value = nombreSugerido;
                }
            }
            actualizarPreview();
        }

        function actualizarPreview() {
            const nombre = document.getElementById('nombre').value || 'Color';
            const hex = document.getElementById('codigo_hex').value;
            const previewBox = document.getElementById('previewBox');
            const previewText = document.getElementById('previewText');
            
            if (/^#[0-9A-F]{6}$/i.test(hex)) {
                previewBox.style.backgroundColor = hex;
                previewText.textContent = nombre;
                
                const r = parseInt(hex.substr(1, 2), 16);
                const g = parseInt(hex.substr(3, 2), 16);
                const b = parseInt(hex.substr(5, 2), 16);
                const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                previewText.style.color = brightness > 128 ? '#000000' : '#ffffff';
            }
        }

        function editarColor(id) {
            const color = colores.find(c => c.id == id);
            
            if (color) {
                document.getElementById('edit_id').value = color.id;
                document.getElementById('edit_nombre').value = color.nombre;
                document.getElementById('edit_codigo_hex').value = color.codigo_hex || '#cccccc';
                document.getElementById('edit_colorPicker').value = color.codigo_hex || '#cccccc';
                
                actualizarEditPreview();
                
                document.getElementById('editarModal').style.display = 'flex';
            }
        }

        function sincronizarEditColor() {
            const picker = document.getElementById('edit_colorPicker');
            const input = document.getElementById('edit_codigo_hex');
            const nombreInput = document.getElementById('edit_nombre');
            input.value = picker.value;
            
            const nombreSugerido = obtenerNombreSugerido(picker.value);
            if (nombreInput.value === '' || confirm('¿Deseas cambiar el nombre a "' + nombreSugerido + '"?')) {
                nombreInput.value = nombreSugerido;
            }
            
            actualizarEditPreview();
        }

        function sincronizarEditPicker() {
            const input = document.getElementById('edit_codigo_hex');
            const picker = document.getElementById('edit_colorPicker');
            if (/^#[0-9A-F]{6}$/i.test(input.value)) {
                picker.value = input.value;
                
                const nombreSugerido = obtenerNombreSugerido(input.value);
                const nombreInput = document.getElementById('edit_nombre');
                if (confirm('¿Deseas cambiar el nombre a "' + nombreSugerido + '"?')) {
                    nombreInput.value = nombreSugerido;
                }
            }
            actualizarEditPreview();
        }

        function actualizarEditPreview() {
            const nombre = document.getElementById('edit_nombre').value || 'Color';
            const hex = document.getElementById('edit_codigo_hex').value;
            const previewBox = document.getElementById('editPreviewBox');
            const previewText = document.getElementById('editPreviewText');
            
            if (/^#[0-9A-F]{6}$/i.test(hex)) {
                previewBox.style.backgroundColor = hex;
                previewText.textContent = nombre;
                
                const r = parseInt(hex.substr(1, 2), 16);
                const g = parseInt(hex.substr(3, 2), 16);
                const b = parseInt(hex.substr(5, 2), 16);
                const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                previewText.style.color = brightness > 128 ? '#000000' : '#ffffff';
            }
        }

        function cerrarModal() {
            document.getElementById('editarModal').style.display = 'none';
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
            const modal = document.getElementById('editarModal');
            if (event.target == modal) {
                cerrarModal();
            }
        }

        window.onload = function() {
            actualizarPreview();
        };
    </script>
</body>
</html>