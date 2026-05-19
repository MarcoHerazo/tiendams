<?php
session_start();
if (!isset($_SESSION['admin'])) { 
    header("Location: login.php"); 
    exit; 
}
include '../conexion.php';

$mensaje = ''; 
$error = '';

// --- ACCIONES ---

// Agregar Badge
if (isset($_POST['agregar'])) {
    $nombre = trim($_POST['nombre']);
    $icono = trim($_POST['icono']);
    $color = $_POST['color'];
    $tipo_filtro = $_POST['tipo_filtro'];
    $orden = intval($_POST['orden']);
    
    $stmt = $conn->prepare("INSERT INTO badges (nombre, icono, color, tipo_filtro, orden) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $nombre, $icono, $color, $tipo_filtro, $orden);
    
    if ($stmt->execute()) { 
        $mensaje = "✅ Badge creado con éxito"; 
    } else { 
        $error = "❌ Error: " . $conn->error; 
    }
}

// Editar Badge
if (isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $nombre = trim($_POST['nombre']);
    $icono = trim($_POST['icono']);
    $color = $_POST['color'];
    $tipo_filtro = $_POST['tipo_filtro'];
    $orden = intval($_POST['orden']);
    $activo = isset($_POST['activo']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE badges SET nombre=?, icono=?, color=?, tipo_filtro=?, orden=?, activo=? WHERE id=?");
    $stmt->bind_param("ssssiii", $nombre, $icono, $color, $tipo_filtro, $orden, $activo, $id);
    
    if ($stmt->execute()) { 
        $mensaje = "✅ Badge actualizado correctamente"; 
    } else { 
        $error = "❌ Error en actualización: " . $conn->error; 
    }
}

// Eliminar
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conn->query("DELETE FROM badges WHERE id = $id");
    $mensaje = "✅ Registro eliminado";
}

$badges = $conn->query("SELECT * FROM badges ORDER BY orden ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Badges Móvil | Tienda MS</title>
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

        /* ===== NOTIFICACIONES ===== */
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.success {
            background: rgba(16,185,129,0.1);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.3);
        }

        .alert.error {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }

        /* ===== BOTONES ===== */
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

        /* ===== GRID DE BADGES ===== */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .badge-card {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .badge-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .badge-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .badge-preview {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-status.active {
            background: rgba(16,185,129,0.15);
            color: #34d399;
        }

        .badge-status.inactive {
            background: rgba(239,68,68,0.15);
            color: #f87171;
        }

        .badge-info {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 0.65rem;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .info-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
        }

        .badge-actions {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border: none;
            text-decoration: none;
        }

        .btn-icon.edit {
            background: rgba(59,130,246,0.1);
            color: #60a5fa;
            border: 1px solid rgba(59,130,246,0.3);
        }

        .btn-icon.edit:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-icon.delete {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }

        .btn-icon.delete:hover {
            background: var(--danger);
            color: white;
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
            max-width: 500px;
            width: 90%;
            border: 1px solid var(--border);
        }

        .modal-content h2 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: var(--transition);
        }

        .close:hover {
            color: var(--primary);
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

        .form-control {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-control[type="color"] {
            height: 45px;
            padding: 5px;
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary);
        }

        .checkbox-group label {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: none;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-modal {
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

        .btn-modal.primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-modal.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,107,53,0.3);
        }

        .btn-modal.secondary {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid var(--border);
        }

        .btn-modal.secondary:hover {
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
            
            .badges-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; gap: 15px; }
            .modal-content { padding: 20px; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .modal-actions { flex-direction: column; }
            .btn-modal { width: 100%; }
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
                <a href="badges.php" class="nav-item active"><i class="fa-solid fa-medal"></i> Badges</a>
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
            <h1><i class="fa-solid fa-tag"></i> Badges Móvil</h1>
            <p>Configura las etiquetas visuales que aparecerán en la versión celular</p>
        </div>

        <?php if($mensaje): ?>
            <div class="alert success"><i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
            <button class="btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
                <i class="fa-solid fa-plus"></i> Nuevo Badge
            </button>
        </div>

        <!-- Grid de Badges -->
        <div class="badges-grid">
            <?php if ($badges->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-tags"></i>
                    <h3>No hay badges creados</h3>
                    <p>Crea tu primer badge para la versión móvil</p>
                </div>
            <?php else: ?>
                <?php while($b = $badges->fetch_assoc()): ?>
                    <div class="badge-card">
                        <div class="badge-header">
                            <div class="badge-preview" style="background: <?php echo $b['color']; ?>;">
                                <i class="fa-solid <?php echo $b['icono']; ?>"></i>
                                <?php echo htmlspecialchars($b['nombre']); ?>
                            </div>
                            <span class="badge-status <?php echo $b['activo'] ? 'active' : 'inactive'; ?>">
                                <?php echo $b['activo'] ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>

                        <div class="badge-info">
                            <div class="info-item">
                                <span class="info-label">Tipo de filtro</span>
                                <span class="info-value"><?php echo $b['tipo_filtro']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Orden</span>
                                <span class="info-value">#<?php echo $b['orden']; ?></span>
                            </div>
                        </div>

                        <div class="badge-actions">
                            <button class="btn-icon edit" onclick='abrirEditar(<?php echo json_encode($b); ?>)'>
                                <i class="fa-solid fa-pen"></i> Editar
                            </button>
                            <a href="?eliminar=<?php echo $b['id']; ?>" class="btn-icon delete" onclick="return confirm('¿Estás seguro de eliminar este badge?')">
                                <i class="fa-solid fa-trash"></i> Eliminar
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Agregar Badge -->
    <div id="addModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
            <h2><i class="fa-solid fa-plus"></i> Nuevo Badge</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Nombre del badge</label>
                    <input type="text" name="nombre" class="form-control" required placeholder="Ej: Superventas">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Icono (FontAwesome)</label>
                        <input type="text" name="icono" class="form-control" required placeholder="fa-fire" value="fa-fire">
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" class="form-control" value="#ff6b35">
                    </div>
                </div>

                <div class="form-group">
                    <label>Tipo de filtro</label>
                    <select name="tipo_filtro" class="form-control" required>
                        <option value="destacado">Destacado</option>
                        <option value="nuevos">Nuevos (últimos 30 días)</option>
                        <option value="oferta">En Oferta</option>
                        <option value="altas_visitas">Altas visitas (tendencia)</option>
                        <option value="destacado_altas_visitas">Premium (destacado + tendencia)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Orden (menor número = más importante)</label>
                    <input type="number" name="orden" class="form-control" value="0" min="0">
                </div>

                <div class="modal-actions">
                    <button type="submit" name="agregar" class="btn-modal primary">
                        <i class="fa-solid fa-check"></i> Crear Badge
                    </button>
                    <button type="button" class="btn-modal secondary" onclick="document.getElementById('addModal').style.display='none'">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar Badge -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
            <h2><i class="fa-solid fa-pen"></i> Editar Badge</h2>
            <form method="POST">
                <input type="hidden" name="id" id="e_id">

                <div class="form-group">
                    <label>Nombre del badge</label>
                    <input type="text" name="nombre" id="e_nombre" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Icono (FontAwesome)</label>
                        <input type="text" name="icono" id="e_icono" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" id="e_color" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Tipo de filtro</label>
                    <select name="tipo_filtro" id="e_tipo" class="form-control" required>
                        <option value="destacado">Destacado</option>
                        <option value="nuevos">Nuevos</option>
                        <option value="oferta">En Oferta</option>
                        <option value="altas_visitas">Tendencia</option>
                        <option value="destacado_altas_visitas">Premium</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Orden</label>
                        <input type="number" name="orden" id="e_orden" class="form-control" min="0">
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="activo" id="e_activo">
                        <label>Badge activo</label>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="editar" class="btn-modal primary">
                        <i class="fa-solid fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn-modal secondary" onclick="document.getElementById('editModal').style.display='none'">
                        Cancelar
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

        function abrirEditar(data) {
            document.getElementById('e_id').value = data.id;
            document.getElementById('e_nombre').value = data.nombre;
            document.getElementById('e_icono').value = data.icono;
            document.getElementById('e_color').value = data.color;
            document.getElementById('e_tipo').value = data.tipo_filtro;
            document.getElementById('e_orden').value = data.orden;
            document.getElementById('e_activo').checked = (data.activo == 1);
            
            document.getElementById('editModal').style.display = 'flex';
        }

        // Cerrar modales al hacer clic fuera
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>