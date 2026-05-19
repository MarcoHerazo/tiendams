<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos del destacado
$destacado = $conn->query("SELECT d.*, p.nombre as producto_nombre 
                           FROM productos_destacados d
                           JOIN productos p ON d.producto_id = p.id
                           WHERE d.id = $id");

if ($destacado->num_rows == 0) {
    header("Location: destacar.php");
    exit;
}

$data = $destacado->fetch_assoc();

// Actualizar destacado
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar'])) {
    $tipo = $_POST['tipo'];
    $badge_texto = trim($_POST['badge_texto']);
    $badge_color = $_POST['badge_color'] ?? '#ff6b35';
    $fecha_fin = !empty($_POST['fecha_fin']) ? "'".$_POST['fecha_fin']."'" : "NULL";
    $orden = intval($_POST['orden']);
    $activo = isset($_POST['activo']) ? 1 : 0;

    $sql = "UPDATE productos_destacados SET 
            tipo_destacado = '$tipo',
            badge_texto = '$badge_texto',
            badge_color = '$badge_color',
            fecha_fin = $fecha_fin,
            orden = $orden,
            activo = $activo
            WHERE id = $id";
    
    if ($conn->query($sql)) {
        header("Location: destacar.php?editado=1");
        exit;
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Obtener productos para el select
$productos = $conn->query("SELECT id, nombre FROM productos ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Editar Destacado - Tienda MS</title>
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

        .form-group input[type="color"] {
            width: 100%;
            height: 45px;
            padding: 5px;
            cursor: pointer;
        }

        .form-group input[type="datetime-local"] {
            color-scheme: dark;
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

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 12px 24px;
            border: 1px solid var(--border);
            border-radius: 30px;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: none;
        }

        .error-message {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            .header h1 { font-size: 1.5rem; }
            .form-container { padding: 18px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .form-actions { flex-direction: column; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
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
            <h1><i class="fa-solid fa-pen-to-square"></i> Editar Destacado</h1>
            <p>Producto: <strong><?php echo htmlspecialchars($data['producto_nombre']); ?></strong></p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario de edición -->
        <div class="form-container">
            <h2><i class="fa-solid fa-pen"></i> Editar Destacado</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-box"></i> Producto</label>
                        <select name="producto_id" required>
                            <?php while($p = $productos->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $data['producto_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-tag"></i> Tipo</label>
                        <select name="tipo" required>
                            <option value="nuevo" <?php echo $data['tipo_destacado'] == 'nuevo' ? 'selected' : ''; ?>>🔥 Nuevo</option>
                            <option value="vendido" <?php echo $data['tipo_destacado'] == 'vendido' ? 'selected' : ''; ?>>⭐ Más vendido</option>
                            <option value="exclusivo" <?php echo $data['tipo_destacado'] == 'exclusivo' ? 'selected' : ''; ?>>🎯 Exclusivo</option>
                            <option value="oferta" <?php echo $data['tipo_destacado'] == 'oferta' ? 'selected' : ''; ?>>⚡ Oferta</option>
                            <option value="personalizado" <?php echo $data['tipo_destacado'] == 'personalizado' ? 'selected' : ''; ?>>✨ Personalizado</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-pen"></i> Texto del badge (opcional)</label>
                        <input type="text" name="badge_texto" value="<?php echo htmlspecialchars($data['badge_texto']); ?>" placeholder="Ej: 🔥 Oferta especial">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-palette"></i> Color</label>
                        <input type="color" name="badge_color" value="<?php echo $data['badge_color'] ?: '#ff6b35'; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar"></i> Fecha fin (solo para ofertas)</label>
                        <input type="datetime-local" name="fecha_fin" 
                               value="<?php echo $data['fecha_fin'] ? date('Y-m-d\TH:i', strtotime($data['fecha_fin'])) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-sort"></i> Orden</label>
                        <input type="number" name="orden" value="<?php echo $data['orden']; ?>" min="0">
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="activo" id="activo" <?php echo $data['activo'] ? 'checked' : ''; ?>>
                    <label for="activo">Destacado activo</label>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 25px;">
                    <button type="submit" name="guardar" class="btn-primary">
                        <i class="fa-solid fa-save"></i> Guardar cambios
                    </button>
                    <a href="destacar.php" class="btn-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Cancelar
                    </a>
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
    </script>
</body>
</html>