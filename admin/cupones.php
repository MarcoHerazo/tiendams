<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

$mensaje = '';
$error = '';

// Procesar formularios
if (isset($_POST['agregar'])) {
    $codigo = strtoupper(trim($_POST['codigo']));
    $tipo = $_POST['tipo'];
    $valor = floatval($_POST['valor']);
    $minimo = floatval($_POST['monto_minimo'] ?? 0);
    $inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
    $usos_max = intval($_POST['usos_maximos'] ?? 1);
    
    $stmt = $conn->prepare("INSERT INTO cupones (codigo, tipo, valor, monto_minimo, fecha_inicio, fecha_fin, usos_maximos) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddssi", $codigo, $tipo, $valor, $minimo, $inicio, $fin, $usos_max);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Cupón agregado correctamente";
    } else {
        $error = "❌ Error al agregar el cupón";
    }
}

if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $conn->query("DELETE FROM cupones WHERE id = $id");
    $mensaje = "✅ Cupón eliminado correctamente";
}

// Obtener cupones
$cupones = $conn->query("SELECT * FROM cupones ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Cupones - Tienda MS</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
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

        .form-group small {
            display: block;
            margin-top: 5px;
            font-size: 0.7rem;
            color: var(--text-muted);
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

        /* ===== TABLA DE CUPONES ===== */
        .cupones-list {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }

        .cupones-list h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .cupones-list h2 i { color: var(--primary); }

        .table-responsive {
            overflow-x: auto;
        }

        .cupones-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cupones-table th {
            text-align: left;
            padding: 12px;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        .cupones-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .cupones-table tr:hover td {
            background: rgba(255, 255, 255, 0.02);
        }

        .badge-estado {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-estado.activo {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }

        .badge-estado.inactivo {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        .btn-eliminar {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 6px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-eliminar:hover {
            background: var(--danger);
            color: white;
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
            
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .cupones-table { min-width: 600px; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .form-container, .cupones-list { padding: 18px; }
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
                <a href="estadisticas.php" class="nav-item"><i class="fa-solid fa-chart-simple"></i> Estadísticas</a>
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
            <h1><i class="fa-solid fa-ticket"></i> Cupones</h1>
            <p>Crea y administra cupones de descuento para tus clientes</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert success"><i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario para agregar cupón -->
        <div class="form-container">
            <h2><i class="fa-solid fa-plus"></i> Agregar Cupón</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Código del cupón *</label>
                        <input type="text" name="codigo" required placeholder="Ej: VERANO2026" style="text-transform: uppercase;">
                        <small>Se guardará en mayúsculas automáticamente</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Tipo de descuento *</label>
                        <select name="tipo" required>
                            <option value="porcentaje">Porcentaje (%)</option>
                            <option value="fijo">Valor fijo ($)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Valor del descuento *</label>
                        <input type="number" name="valor" step="0.01" required placeholder="Ej: 10 o 5000">
                    </div>
                    
                    <div class="form-group">
                        <label>Monto mínimo de compra</label>
                        <input type="number" name="monto_minimo" step="0.01" value="0" placeholder="0 = sin mínimo">
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha inicio</label>
                        <input type="date" name="fecha_inicio">
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha fin</label>
                        <input type="date" name="fecha_fin">
                    </div>
                    
                    <div class="form-group">
                        <label>Usos máximos</label>
                        <input type="number" name="usos_maximos" value="1" min="1">
                    </div>
                </div>
                
                <button type="submit" name="agregar" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Agregar Cupón
                </button>
            </form>
        </div>

        <!-- Lista de cupones -->
        <div class="cupones-list">
            <h2><i class="fa-solid fa-list"></i> Cupones Existentes</h2>
            
            <?php if ($cupones->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-ticket"></i>
                    <p>No hay cupones registrados. Crea el primero.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cupones-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descuento</th>
                                <th>Mínimo</th>
                                <th>Vigencia</th>
                                <th>Usos</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($c = $cupones->fetch_assoc()): 
                                $hoy = date('Y-m-d');
                                $activo = $c['activo'] && 
                                         (!$c['fecha_inicio'] || $c['fecha_inicio'] <= $hoy) && 
                                         (!$c['fecha_fin'] || $c['fecha_fin'] >= $hoy) &&
                                         ($c['usos_maximos'] == 0 || $c['usos_actuales'] < $c['usos_maximos']);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($c['codigo']); ?></strong></td>
                                <td>
                                    <?php if ($c['tipo'] == 'porcentaje'): ?>
                                        <?php echo $c['valor']; ?>% OFF
                                    <?php else: ?>
                                        $<?php echo number_format($c['valor'], 0); ?> OFF
                                    <?php endif; ?>
                                </td>
                                <td>$<?php echo number_format($c['monto_minimo'], 0); ?></td>
                                <td>
                                    <?php if ($c['fecha_inicio']): ?>
                                        <?php echo date('d/m/Y', strtotime($c['fecha_inicio'])); ?>
                                    <?php endif; ?>
                                    <?php if ($c['fecha_fin']): ?>
                                        - <?php echo date('d/m/Y', strtotime($c['fecha_fin'])); ?>
                                    <?php endif; ?>
                                    <?php if (!$c['fecha_inicio'] && !$c['fecha_fin']): ?>
                                        Sin fecha límite
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $c['usos_actuales']; ?>/<?php echo $c['usos_maximos']; ?></td>
                                <td>
                                    <span class="badge-estado <?php echo $activo ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $activo ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?eliminar=<?php echo $c['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar este cupón?')">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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