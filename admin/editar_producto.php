<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Obtener ID del producto
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// Obtener datos del producto
$producto = $conn->query("SELECT p.*, c.nombre as categoria_nombre 
                          FROM productos p 
                          LEFT JOIN categorias c ON p.categoria_id = c.id 
                          WHERE p.id = $id");
if ($producto->num_rows == 0) {
    header("Location: dashboard.php");
    exit;
}
$producto_data = $producto->fetch_assoc();

// Obtener listados para selects
$categorias = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
$tallas = $conn->query("SELECT * FROM tallas ORDER BY FIELD(tipo, 'numerica', 'letra'), talla");
$colores = $conn->query("SELECT * FROM colores ORDER BY nombre");

// Obtener variantes del producto
$variantes = $conn->query("SELECT pv.*, t.talla, c.nombre as color_nombre, c.codigo_hex 
                           FROM producto_variantes pv
                           JOIN tallas t ON pv.talla_id = t.id
                           JOIN colores c ON pv.color_id = c.id
                           WHERE pv.producto_id = $id
                           ORDER BY t.talla, c.nombre");

// Obtener imágenes adicionales
$imagenes_extra = $conn->query("SELECT * FROM producto_imagenes WHERE producto_id = $id ORDER BY orden");

$mensaje = '';
$error = '';

// Función para limpiar nombre de archivo
function limpiar_nombre_archivo($nombre) {
    // Convertir a minúsculas
    $nombre = strtolower($nombre);
    // Reemplazar caracteres especiales y espacios por guiones
    $nombre = preg_replace('/[^a-z0-9áéíóúñ]/', '-', $nombre);
    // Eliminar guiones múltiples
    $nombre = preg_replace('/-+/', '-', $nombre);
    // Eliminar guiones al inicio y final
    $nombre = trim($nombre, '-');
    return $nombre;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $precio = floatval($_POST['precio']);
    $costo = floatval($_POST['costo'] ?? 0);
    $categoria_id = intval($_POST['categoria_id']);
    $tipo_talla = $_POST['tipo_talla'] ?? 'ambos';
    $imagen_actual = $producto_data['imagen'];
    
    // Limpiar nombre para archivos
    $nombre_limpio = limpiar_nombre_archivo($nombre);
    
    // Procesar nueva imagen principal
    $imagen = $imagen_actual;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $target_dir = "../img/";
        $extension = strtolower(pathinfo($_FILES["imagen"]["name"], PATHINFO_EXTENSION));
        $valid_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($extension, $valid_extensions)) {
            // Nuevo nombre: nombre_del_producto.ext
            $imagen = $nombre_limpio . '.' . $extension;
            $target_file = $target_dir . $imagen;
            
            // Si ya existe un archivo con el mismo nombre, agregar timestamp
            if (file_exists($target_file)) {
                $imagen = $nombre_limpio . '_' . time() . '.' . $extension;
                $target_file = $target_dir . $imagen;
            }
            
            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $target_file)) {
                // Eliminar imagen anterior si existe y es diferente
                if ($imagen_actual && $imagen_actual != $imagen && file_exists($target_dir . $imagen_actual)) {
                    unlink($target_dir . $imagen_actual);
                }
            } else {
                $error = "Error al subir la imagen principal";
                $imagen = $imagen_actual;
            }
        } else {
            $error = "Solo se permiten archivos JPG, JPEG, PNG y WEBP";
        }
    }
    
    if (empty($error)) {
        // Actualizar producto
        $sql = "UPDATE productos SET 
                nombre = ?, 
                descripcion = ?, 
                precio = ?, 
                costo = ?,
                categoria_id = ?, 
                imagen = ?,
                tipo_talla = ?
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdiissi", $nombre, $descripcion, $precio, $costo, $categoria_id, $imagen, $tipo_talla, $id);
        
        if ($stmt->execute()) {
            // Eliminar variantes existentes
            $conn->query("DELETE FROM producto_variantes WHERE producto_id = $id");
            
            // Insertar nuevas variantes (solo si no es "ninguna")
            if (isset($_POST['variantes']) && is_array($_POST['variantes']) && $tipo_talla != 'ninguna') {
                $sql_variante = "INSERT INTO producto_variantes (producto_id, talla_id, color_id, stock, precio_ajuste) 
                                 VALUES (?, ?, ?, ?, ?)";
                $stmt_variante = $conn->prepare($sql_variante);
                
                foreach ($_POST['variantes'] as $variante) {
                    if (!empty($variante['talla_id']) && !empty($variante['color_id'])) {
                        $stock = intval($variante['stock']);
                        $precio_ajuste = floatval($variante['precio_ajuste'] ?? 0);
                        
                        $stmt_variante->bind_param("iiiid", 
                            $id, 
                            $variante['talla_id'], 
                            $variante['color_id'], 
                            $stock,
                            $precio_ajuste
                        );
                        $stmt_variante->execute();
                    }
                }
            }
            
            // Procesar imágenes adicionales
            if (isset($_FILES['imagenes_extra'])) {
                $archivos = $_FILES['imagenes_extra'];
                $total = count($archivos['name']);
                $total = min($total, 5);
                
                // Eliminar imágenes marcadas para borrar
                if (isset($_POST['eliminar_imagenes']) && is_array($_POST['eliminar_imagenes'])) {
                    foreach ($_POST['eliminar_imagenes'] as $img_id) {
                        if (!empty($img_id) && is_numeric($img_id)) {
                            $result = $conn->query("SELECT imagen FROM producto_imagenes WHERE id = $img_id");
                            if ($result && $result->num_rows > 0) {
                                $img = $result->fetch_assoc();
                                if (!empty($img['imagen']) && file_exists("../img/" . $img['imagen'])) {
                                    unlink("../img/" . $img['imagen']);
                                }
                            }
                            $conn->query("DELETE FROM producto_imagenes WHERE id = $img_id");
                        }
                    }
                }
                
                // Obtener imágenes existentes para contar
                $existentes = $conn->query("SELECT COUNT(*) as total FROM producto_imagenes WHERE producto_id = $id");
                $contador = $existentes->fetch_assoc()['total'];
                
                // Subir nuevas imágenes
                for ($i = 0; $i < $total; $i++) {
                    if ($archivos['error'][$i] == 0) {
                        $extension = strtolower(pathinfo($archivos['name'][$i], PATHINFO_EXTENSION));
                        $valid_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                        
                        if (in_array($extension, $valid_extensions)) {
                            $contador++;
                            // Nombre: nombre_del_producto_1.ext, nombre_del_producto_2.ext, etc.
                            $nombre_archivo = $nombre_limpio . '_' . $contador . '.' . $extension;
                            $destino = "../img/" . $nombre_archivo;
                            
                            // Si ya existe, agregar timestamp
                            if (file_exists($destino)) {
                                $nombre_archivo = $nombre_limpio . '_' . $contador . '_' . time() . '.' . $extension;
                                $destino = "../img/" . $nombre_archivo;
                            }
                            
                            if (move_uploaded_file($archivos['tmp_name'][$i], $destino)) {
                                $sql_img = "INSERT INTO producto_imagenes (producto_id, imagen, orden) 
                                            VALUES ($id, '$nombre_archivo', $contador)";
                                $conn->query($sql_img);
                            }
                        }
                    }
                }
            }
            
            $mensaje = "✅ Producto actualizado exitosamente";
            
            // Refrescar datos
            $producto = $conn->query("SELECT p.*, c.nombre as categoria_nombre 
                                      FROM productos p 
                                      LEFT JOIN categorias c ON p.categoria_id = c.id 
                                      WHERE p.id = $id");
            $producto_data = $producto->fetch_assoc();
            
            $variantes = $conn->query("SELECT pv.*, t.talla, c.nombre as color_nombre, c.codigo_hex 
                                       FROM producto_variantes pv
                                       JOIN tallas t ON pv.talla_id = t.id
                                       JOIN colores c ON pv.color_id = c.id
                                       WHERE pv.producto_id = $id
                                       ORDER BY t.talla, c.nombre");
            
            $imagenes_extra = $conn->query("SELECT * FROM producto_imagenes WHERE producto_id = $id ORDER BY orden");
        } else {
            $error = "❌ Error al actualizar el producto: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Editar Producto - Tienda MS</title>
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
            border-left: 4px solid var(--accent);
        }

        .header h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 4px; }
        .header p { color: var(--text-muted); font-size: 0.9rem; }

        /* ===== FORMULARIO ===== */
        .form-card {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .form-card h3 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .form-card h3 i { color: var(--accent); }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            background: var(--primary-dark);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .full-width { grid-column: span 2; }

        /* Variantes */
        .variante-item {
            background: var(--primary-dark);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            border: 1px solid var(--border);
        }

        .variante-row {
            display: grid;
            grid-template-columns: 1fr 1fr 100px 100px auto;
            gap: 12px;
            align-items: center;
        }

        .variante-row select, .variante-row input {
            padding: 10px;
            background: var(--primary-light);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: white;
        }

        .remove-variante {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
        }

        .remove-variante:hover {
            background: var(--danger);
            color: white;
        }

        .add-variante-btn {
            background: rgba(255, 107, 53, 0.1);
            color: var(--accent);
            border: 1px solid rgba(255, 107, 53, 0.3);
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-top: 10px;
        }

        .add-variante-btn:hover {
            background: var(--accent);
            color: white;
        }

        /* Imágenes adicionales */
        .imagenes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .imagen-item {
            position: relative;
            background: var(--primary-dark);
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .imagen-item img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }

        .imagen-item .btn-remove-img {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            font-size: 12px;
        }

        .image-preview {
            margin-top: 10px;
        }

        .image-preview img {
            max-width: 150px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }

        .help-text {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 5px;
            display: block;
        }

        /* Botones */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 24px;
            border: 1px solid var(--border);
            border-radius: 10px;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Ocultar variantes si no hay tallas */
        .variantes-section.hidden {
            display: none;
        }

        /* MOBILE */
        .menu-toggle {
            display: none;
            position: fixed; top: 15px; left: 15px;
            width: 45px; height: 45px;
            background: var(--accent);
            color: white; border: none; border-radius: 10px;
            z-index: 1100;
            cursor: pointer;
        }

        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
            z-index: 999; display: none;
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
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .variante-row { grid-template-columns: 1fr; gap: 8px; }
        }
        /* ===== BOTÓN GUARDAR PRODUCTO ===== */
.btn-primary {
    background: linear-gradient(135deg, #ff6b35, #e55a2b);
    color: white;
    padding: 12px 28px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 10px rgba(255, 107, 53, 0.3);
}

.btn-primary i {
    font-size: 1rem;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
    background: linear-gradient(135deg, #ff7b4a, #ff5a25);
}

.btn-primary:active {
    transform: translateY(0);
    box-shadow: 0 2px 5px rgba(255, 107, 53, 0.3);
}

/* Botón deshabilitado */
.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Versión móvil */
@media (max-width: 768px) {
    .btn-primary {
        padding: 12px 20px;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
    }
}

/* Botón secundario (cancelar) */
.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-secondary);
    padding: 12px 28px;
    border: 1px solid var(--border);
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .btn-secondary {
        padding: 12px 20px;
        width: 100%;
        justify-content: center;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 12px;
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
                <a href="agregar_producto.php" class="nav-item active"><i class="fa-solid fa-circle-plus"></i> Nuevo Producto</a>
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
            <h1><i class="fa-solid fa-pen-to-square"></i> Editar Producto</h1>
            <p>ID: #<?php echo $id; ?> - <?php echo htmlspecialchars($producto_data['nombre']); ?></p>
        </div>

        <?php if ($mensaje): ?>
            <div class="success-message"><i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="productForm">
            <!-- Información Básica -->
            <div class="form-card">
                <h3><i class="fa-solid fa-info-circle"></i> Información Básica</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre del Producto *</label>
                        <input type="text" name="nombre" id="nombreProducto" required value="<?php echo htmlspecialchars($producto_data['nombre']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Categoría *</label>
                        <select name="categoria_id" required>
                            <option value="">Seleccionar</option>
                            <?php 
                            $categorias->data_seek(0);
                            while($cat = $categorias->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($cat['id'] == $producto_data['categoria_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Precio ($) *</label>
                        <input type="number" name="precio" step="0.01" min="0" required value="<?php echo $producto_data['precio']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Costo ($) (para rentabilidad)</label>
                        <input type="number" name="costo" step="0.01" min="0" value="<?php echo $producto_data['costo'] ?? 0; ?>">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Talla</label>
                        <select name="tipo_talla" id="tipoTalla" onchange="toggleVariantes()">
                            <option value="zapatos" <?php echo ($producto_data['tipo_talla'] ?? 'ambos') == 'zapatos' ? 'selected' : ''; ?>>👟 Zapatos (tallas numéricas)</option>
                            <option value="ropa" <?php echo ($producto_data['tipo_talla'] ?? '') == 'ropa' ? 'selected' : ''; ?>>👕 Ropa (tallas XS, S, M, L, XL)</option>
                            <option value="ambos" <?php echo ($producto_data['tipo_talla'] ?? 'ambos') == 'ambos' ? 'selected' : ''; ?>>👟+👕 Ambos (zapatos + ropa)</option>
                            <option value="ninguna" <?php echo ($producto_data['tipo_talla'] ?? '') == 'ninguna' ? 'selected' : ''; ?>>❌ Ninguna (accesorios, colonias, etc.)</option>
                        </select>
                        <small class="help-text">Define qué tipo de tallas se mostrarán. "Ninguna" para productos sin talla</small>
                    </div>
                    <div class="form-group full-width">
                        <label>Descripción *</label>
                        <textarea name="descripcion" rows="4" required><?php echo htmlspecialchars($producto_data['descripcion']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Imagen Principal -->
            <div class="form-card">
                <h3><i class="fa-solid fa-image"></i> Imagen Principal</h3>
                <div class="form-group">
                    <label>Cambiar imagen</label>
                    <input type="file" name="imagen" accept="image/*" id="imagenPrincipal">
                    <div class="image-preview" id="imagePreview">
                        <?php if ($producto_data['imagen'] && file_exists("../img/".$producto_data['imagen'])): ?>
                            <img src="../img/<?php echo $producto_data['imagen']; ?>" alt="Vista previa">
                        <?php endif; ?>
                    </div>
                    <small class="help-text">La imagen se renombrará automáticamente con el nombre del producto. Formatos: JPG, PNG, WEBP</small>
                </div>
            </div>

            <!-- Imágenes Adicionales -->
            <div class="form-card">
                <h3><i class="fa-solid fa-images"></i> Galería de Imágenes (máximo 5)</h3>
                <div class="form-group">
                    <label>Agregar nuevas imágenes</label>
                    <input type="file" name="imagenes_extra[]" accept="image/*" multiple id="imagenesExtra">
                    <small class="help-text">Las imágenes se renombrarán como: nombre_producto_1.jpg, nombre_producto_2.jpg, etc.</small>
                </div>
                
                <?php if ($imagenes_extra && $imagenes_extra->num_rows > 0): ?>
                <div class="imagenes-grid" id="imagenesGrid">
                    <?php while($img = $imagenes_extra->fetch_assoc()): ?>
                        <div class="imagen-item" data-id="<?php echo $img['id']; ?>">
                            <img src="../img/<?php echo $img['imagen']; ?>" alt="Imagen adicional">
                            <button type="button" class="btn-remove-img" onclick="marcarEliminarImagen(<?php echo $img['id']; ?>, this)">
                                <i class="fa-solid fa-times"></i>
                            </button>
                            <input type="hidden" name="eliminar_imagenes[]" class="eliminar-img-<?php echo $img['id']; ?>" value="">
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Variantes (solo si no es "ninguna") -->
            <div class="form-card variantes-section" id="variantesSection">
                <h3><i class="fa-solid fa-cubes"></i> Inventario por Talla y Color</h3>
                <div id="variantes-container">
                    <?php 
                    if ($variantes && $variantes->num_rows > 0 && ($producto_data['tipo_talla'] ?? 'ambos') != 'ninguna'):
                    $variantes->data_seek(0);
                    while($var = $variantes->fetch_assoc()): 
                    ?>
                    <div class="variante-item" id="variante-<?php echo $var['id']; ?>">
                        <div class="variante-row">
                            <select name="variantes[<?php echo $var['id']; ?>][talla_id]" required>
                                <option value="">Talla</option>
                                <?php 
                                $tallas->data_seek(0);
                                while($t = $tallas->fetch_assoc()): 
                                    $mostrar = true;
                                    $tipo_actual = $producto_data['tipo_talla'] ?? 'ambos';
                                    if ($tipo_actual == 'zapatos' && $t['tipo'] != 'numerica') $mostrar = false;
                                    if ($tipo_actual == 'ropa' && $t['tipo'] != 'letra') $mostrar = false;
                                    if ($mostrar):
                                ?>
                                    <option value="<?php echo $t['id']; ?>" <?php echo ($t['id'] == $var['talla_id']) ? 'selected' : ''; ?>>
                                        <?php echo $t['talla']; ?>
                                    </option>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </select>
                            
                            <select name="variantes[<?php echo $var['id']; ?>][color_id]" required>
                                <option value="">Color</option>
                                <?php 
                                $colores->data_seek(0);
                                while($c = $colores->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $var['color_id']) ? 'selected' : ''; ?>>
                                        <?php echo $c['nombre']; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            
                            <input type="number" name="variantes[<?php echo $var['id']; ?>][stock]" 
                                   placeholder="Stock" min="0" required value="<?php echo $var['stock']; ?>">
                            
                            <input type="number" name="variantes[<?php echo $var['id']; ?>][precio_ajuste]" 
                                   placeholder="Ajuste $" step="0.01" min="0" value="<?php echo $var['precio_ajuste']; ?>">
                            
                            <button type="button" class="remove-variante" onclick="eliminarVariante(<?php echo $var['id']; ?>)">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <button type="button" class="add-variante-btn" id="addVarianteBtn" onclick="agregarVariante()">
                    <i class="fa-solid fa-plus"></i> Agregar Talla/Color
                </button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-save"></i> Actualizar Producto</button>
                <a href="dashboard.php" class="btn-secondary"><i class="fa-solid fa-arrow-left"></i> Cancelar</a>
            </div>
        </form>
    </div>

    <script>
        const tallas = <?php 
            $tallas->data_seek(0);
            $tallas_array = [];
            while($t = $tallas->fetch_assoc()) {
                $tallas_array[] = $t;
            }
            echo json_encode($tallas_array); 
        ?>;
        
        const colores = <?php 
            $colores->data_seek(0);
            echo json_encode($colores->fetch_all(MYSQLI_ASSOC)); 
        ?>;

        let tipoTallaActual = '<?php echo $producto_data['tipo_talla'] ?? "ambos"; ?>';
        let varianteCount = <?php echo time(); ?>;

        function toggleVariantes() {
            const tipoTalla = document.getElementById('tipoTalla').value;
            const variantesSection = document.getElementById('variantesSection');
            
            if (tipoTalla === 'ninguna') {
                variantesSection.classList.add('hidden');
                document.getElementById('variantes-container').innerHTML = '';
            } else {
                variantesSection.classList.remove('hidden');
                tipoTallaActual = tipoTalla;
            }
        }

        function obtenerTallasFiltradas() {
            if (tipoTallaActual === 'zapatos') {
                return tallas.filter(t => t.tipo === 'numerica');
            } else if (tipoTallaActual === 'ropa') {
                return tallas.filter(t => t.tipo === 'letra');
            }
            return tallas;
        }

        function agregarVariante() {
            if (tipoTallaActual === 'ninguna') {
                alert('Este producto no requiere tallas. Cambia el tipo de talla si necesitas agregar inventario.');
                return;
            }
            
            const container = document.getElementById('variantes-container');
            const varianteId = varianteCount++;
            const tallasFiltradas = obtenerTallasFiltradas();
            
            if (tallasFiltradas.length === 0) {
                alert('No hay tallas disponibles para este tipo de producto. Agrega tallas en el panel de administración.');
                return;
            }
            
            const html = `
                <div class="variante-item" id="variante-${varianteId}">
                    <div class="variante-row">
                        <select name="variantes[${varianteId}][talla_id]" required>
                            <option value="">Talla</option>
                            ${tallasFiltradas.map(t => `<option value="${t.id}">${t.talla}</option>`).join('')}
                        </select>
                        <select name="variantes[${varianteId}][color_id]" required>
                            <option value="">Color</option>
                            ${colores.map(c => `<option value="${c.id}">${c.nombre}</option>`).join('')}
                        </select>
                        <input type="number" name="variantes[${varianteId}][stock]" placeholder="Stock" min="0" required>
                        <input type="number" name="variantes[${varianteId}][precio_ajuste]" placeholder="Ajuste $" step="0.01" min="0" value="0">
                        <button type="button" class="remove-variante" onclick="eliminarVariante(${varianteId})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function eliminarVariante(id) {
            if (confirm('¿Eliminar esta variante?')) {
                document.getElementById(`variante-${id}`).remove();
            }
        }

        // Vista previa imagen principal
        document.getElementById('imagenPrincipal').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Vista previa">`;
                }
                reader.readAsDataURL(file);
            }
        });

        // Marcar imágenes para eliminar
        function marcarEliminarImagen(id, btn) {
            const input = document.querySelector(`.eliminar-img-${id}`);
            const item = btn.closest('.imagen-item');
            if (input) {
                input.value = id;
                item.style.opacity = '0.5';
                item.style.textDecoration = 'line-through';
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            }
        }

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

        // Inicializar estado de variantes
        document.addEventListener('DOMContentLoaded', function() {
            toggleVariantes();
        });
    </script>
</body>
</html>