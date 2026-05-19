<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Guardar destacado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    $producto_id = intval($_POST['producto_id']);
    $tipo = $_POST['tipo'];
    $badge_texto = trim($_POST['badge_texto']);
    $badge_color = $_POST['badge_color'] ?? '#ff6b35';
    $fecha_fin = !empty($_POST['fecha_fin']) ? "'".$_POST['fecha_fin']."'" : "NULL";
    $orden = intval($_POST['orden']);

    $sql = "INSERT INTO productos_destacados 
            (producto_id, tipo_destacado, badge_texto, badge_color, fecha_fin, orden) 
            VALUES 
            ($producto_id, '$tipo', '$badge_texto', '$badge_color', $fecha_fin, $orden)";
    
    if ($conn->query($sql)) {
        $mensaje = "✅ Producto destacado guardado";
    } else {
        $error = "❌ Error: " . $conn->error;
    }
}

// Eliminar destacado
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conn->query("DELETE FROM productos_destacados WHERE id = $id");
    header("Location: destacar.php");
    exit;
}

// Obtener productos para el select
$productos = $conn->query("SELECT id, nombre FROM productos ORDER BY nombre");

// Obtener destacados actuales
$destacados = $conn->query("
    SELECT d.*, p.nombre as producto_nombre, p.imagen 
    FROM productos_destacados d
    JOIN productos p ON d.producto_id = p.id
    ORDER BY d.orden, d.id DESC
");

// Obtener badges para móvil
$badges = $conn->query("SELECT * FROM badges WHERE activo = 1 ORDER BY orden");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Destacar Productos - Tienda MS</title>
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

        .form-group select, .form-group input {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }

        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .color-input-wrapper input[type="color"] {
            width: 100%;
            height: 45px;
            padding: 5px;
            cursor: pointer;
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

        /* ===== LISTA DE DESTACADOS ===== */
        .destacados-list {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .destacados-list h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .destacados-list h2 i { color: var(--primary); }

        .destacados-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .destacado-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .destacado-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .destacado-imagen {
            height: 160px;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .destacado-imagen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .destacado-imagen i {
            font-size: 3rem;
            color: var(--text-muted);
        }

        .destacado-info {
            padding: 16px;
        }

        .destacado-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
        }

        .badge-preview {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
        }

        .destacado-meta {
            display: flex;
            gap: 12px;
            font-size: 0.7rem;
            color: var(--text-muted);
        }

        .destacado-acciones {
            display: flex;
            gap: 8px;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.2);
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

        .btn-eliminar:hover {
            background: var(--danger);
            color: white;
        }

        /* ===== BADGES SECTION ===== */
        .badges-section {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }

        .badges-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .badges-header h3 {
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .badges-header h3 i { color: var(--primary); }

        .badges-link {
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary);
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .badges-link:hover {
            background: var(--primary);
            color: white;
        }

        .badges-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .badge-item {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badges-empty {
            color: var(--text-muted);
            text-align: center;
            padding: 20px;
        }

        .badges-empty a {
            color: var(--primary);
            text-decoration: none;
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
            .destacados-grid { grid-template-columns: 1fr; }
            .badges-header { flex-direction: column; align-items: flex-start; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .form-container, .destacados-list, .badges-section { padding: 18px; }
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
                <a href="destacar.php" class="nav-item active"><i class="fa-solid fa-star"></i> Destacados</a>
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
            <h1><i class="fa-solid fa-star"></i> Destacar Productos</h1>
            <p>Selecciona qué productos quieres destacar en la tienda</p>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="success-message"><i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario para agregar -->
        <div class="form-container">
            <h2><i class="fa-solid fa-plus"></i> Agregar producto destacado</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-box"></i> Producto</label>
                        <select name="producto_id" required>
                            <option value="">Seleccionar producto</option>
                            <?php 
                            $productos->data_seek(0);
                            while($p = $productos->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-tag"></i> Tipo</label>
                        <select name="tipo" required>
                            <option value="nuevo">🔥 Nuevo</option>
                            <option value="vendido">⭐ Más vendido</option>
                            <option value="exclusivo">🎯 Exclusivo</option>
                            <option value="oferta">⚡ Oferta</option>
                            <option value="personalizado">✨ Personalizado</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-pen"></i> Texto del badge</label>
                        <input type="text" name="badge_texto" placeholder="Ej: 🔥 Los más buscados">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-palette"></i> Color</label>
                        <div class="color-input-wrapper">
                            <input type="color" name="badge_color" value="#ff6b35">
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar"></i> Fecha fin (opcional)</label>
                        <input type="datetime-local" name="fecha_fin">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-sort"></i> Orden</label>
                        <input type="number" name="orden" value="0" min="0">
                    </div>
                </div>

                <button type="submit" name="guardar" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar destacado
                </button>
            </form>
        </div>

        <!-- Lista de destacados actuales -->
        <div class="destacados-list">
            <h2><i class="fa-solid fa-list"></i> Destacados actuales</h2>
            
            <?php if ($destacados->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-star"></i>
                    <p>No hay productos destacados aún</p>
                </div>
            <?php else: ?>
                <div class="destacados-grid">
                    <?php while($d = $destacados->fetch_assoc()): ?>
                        <div class="destacado-card">
                            <div class="destacado-imagen">
                                <?php if ($d['imagen'] && file_exists("../img/".$d['imagen'])): ?>
                                    <img src="../img/<?php echo $d['imagen']; ?>" alt="">
                                <?php else: ?>
                                    <i class="fa-solid fa-shirt"></i>
                                <?php endif; ?>
                            </div>
                            <div class="destacado-info">
                                <h4><?php echo htmlspecialchars($d['producto_nombre']); ?></h4>
                                <div class="badge-preview" style="background: <?php echo $d['badge_color']; ?>">
                                    <?php echo $d['badge_texto'] ?: $d['tipo_destacado']; ?>
                                </div>
                                <div class="destacado-meta">
                                    <span><i class="fa-solid fa-sort"></i> Orden: <?php echo $d['orden']; ?></span>
                                    <span><i class="fa-solid fa-tag"></i> <?php echo $d['tipo_destacado']; ?></span>
                                </div>
                            </div>
                            <div class="destacado-acciones">
                                <a href="destacar_editar.php?id=<?php echo $d['id']; ?>" class="btn-editar" title="Editar destacado">
                                    <i class="fa-solid fa-pen"></i> Editar
                                </a>
                                <a href="?eliminar=<?php echo $d['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar este producto destacado?')" title="Eliminar destacado">
                                    <i class="fa-solid fa-trash"></i> Eliminar
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sección de badges -->
        <div class="badges-section">
            <div class="badges-header">
                <h3><i class="fa-solid fa-tags"></i> Badges disponibles</h3>
                <div style="display: flex; gap: 10px;">
                    <a href="descuentos.php" class="badges-link">
                        <i class="fa-solid fa-percent"></i> Descuentos
                    </a>
                    <a href="badges.php" class="badges-link">
                        <i class="fa-solid fa-gear"></i> Gestionar badges
                    </a>
                </div>
            </div>
            
            <?php if ($badges && $badges->num_rows > 0): ?>
                <div class="badges-grid">
                    <?php while($b = $badges->fetch_assoc()): ?>
                        <span class="badge-item" style="background: <?php echo $b['color']; ?>">
                            <i class="fa-solid <?php echo $b['icono']; ?>"></i>
                            <?php echo htmlspecialchars($b['nombre']); ?>
                        </span>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="badges-empty">
                    No hay badges configurados. 
                    <a href="badges.php">Crear badges</a>
                </p>
            <?php endif; ?>
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