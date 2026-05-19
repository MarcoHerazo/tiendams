<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Procesar formularios
$mensaje = '';
$error = '';

// Agregar descuento
if (isset($_POST['agregar'])) {
    $producto_id = $_POST['producto_id'];
    $tipo = $_POST['tipo'];
    $valor = $_POST['valor'];
    $inicio = $_POST['fecha_inicio'] ?: NULL;
    $fin = $_POST['fecha_fin'] ?: NULL;
    
    $stmt = $conn->prepare("INSERT INTO productos_descuento (producto_id, tipo_descuento, valor_descuento, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdss", $producto_id, $tipo, $valor, $inicio, $fin);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Descuento agregado correctamente";
    } else {
        $error = "❌ Error al agregar el descuento";
    }
}

// Editar descuento
if (isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $producto_id = $_POST['producto_id'];
    $tipo = $_POST['tipo'];
    $valor = $_POST['valor'];
    $inicio = $_POST['fecha_inicio'] ?: NULL;
    $fin = $_POST['fecha_fin'] ?: NULL;
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE productos_descuento SET producto_id = ?, tipo_descuento = ?, valor_descuento = ?, fecha_inicio = ?, fecha_fin = ?, activo = ? WHERE id = ?");
    $stmt->bind_param("isdssii", $producto_id, $tipo, $valor, $inicio, $fin, $activo, $id);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Descuento actualizado correctamente";
    } else {
        $error = "❌ Error al actualizar el descuento";
    }
}

// Eliminar descuento
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $conn->query("DELETE FROM productos_descuento WHERE id = $id");
    $mensaje = "✅ Descuento eliminado correctamente";
}

// Obtener productos para el select
$productos = $conn->query("SELECT id, nombre FROM productos ORDER BY nombre");

// Obtener descuentos activos
$descuentos = $conn->query("
    SELECT d.*, p.nombre as producto_nombre 
    FROM productos_descuento d
    JOIN productos p ON d.producto_id = p.id
    ORDER BY d.id DESC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Descuentos - Tienda MS</title>
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
            box-shadow: 0 5px 15px rgba(255,107,53,0.3);
        }

        /* ===== TABLA DE DESCUENTOS ===== */
        .tabla-container {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }

        .tabla-container h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .tabla-container h2 i { color: var(--primary); }

        .table-responsive {
            overflow-x: auto;
        }

        .descuentos-table {
            width: 100%;
            border-collapse: collapse;
        }

        .descuentos-table th {
            text-align: left;
            padding: 12px;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        .descuentos-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        .descuentos-table tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        .badge-activo {
            background: rgba(16,185,129,0.15);
            color: #34d399;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-inactivo {
            background: rgba(239,68,68,0.15);
            color: #f87171;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .acciones {
            display: flex;
            gap: 8px;
        }

        .btn-editar, .btn-eliminar {
            padding: 6px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            border: none;
        }

        .btn-editar {
            background: rgba(59,130,246,0.1);
            color: #60a5fa;
            border: 1px solid rgba(59,130,246,0.3);
        }

        .btn-editar:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-eliminar {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
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
            
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .table-responsive { overflow-x: auto; }
            .descuentos-table { min-width: 600px; }
            .header h1 { font-size: 1.5rem; }
            .modal-content { padding: 20px; }
            .modal-actions { flex-direction: column; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .form-container, .tabla-container { padding: 18px; }
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
                <a href="descuentos.php" class="nav-item active"><i class="fa-solid fa-tag"></i> Descuentos</a>
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
            <h1><i class="fa-solid fa-tag"></i> Descuentos</h1>
            <p>Gestiona los descuentos en productos</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="success-message"><i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario para agregar descuento -->
        <div class="form-container">
            <h2><i class="fa-solid fa-plus"></i> Agregar Descuento</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-box"></i> Producto</label>
                        <select name="producto_id" required>
                            <option value="">Seleccionar producto</option>
                            <?php while($p = $productos->fetch_assoc()): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-percent"></i> Tipo de descuento</label>
                        <select name="tipo" required>
                            <option value="porcentaje">📊 Porcentaje (%)</option>
                            <option value="fijo">💰 Valor fijo ($)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-chart-line"></i> Valor del descuento</label>
                        <input type="number" name="valor" step="0.01" required placeholder="Ej: 20 o 5000">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar"></i> Fecha inicio (opcional)</label>
                        <input type="date" name="fecha_inicio">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-check"></i> Fecha fin (opcional)</label>
                        <input type="date" name="fecha_fin">
                    </div>
                </div>

                <button type="submit" name="agregar" class="btn-primary">
                    <i class="fa-solid fa-save"></i> Agregar descuento
                </button>
            </form>
        </div>

        <!-- Lista de descuentos -->
        <div class="tabla-container">
            <h2><i class="fa-solid fa-list"></i> Descuentos activos</h2>
            
            <?php if ($descuentos->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-tag"></i>
                    <p>No hay descuentos registrados</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="descuentos-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Descuento</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </thead>
                        <tbody>
                            <?php while($d = $descuentos->fetch_assoc()): 
                                $hoy = date('Y-m-d');
                                $activo = $d['activo'] && 
                                         (!$d['fecha_inicio'] || $d['fecha_inicio'] <= $hoy) && 
                                         (!$d['fecha_fin'] || $d['fecha_fin'] >= $hoy);
                            ?>
                                <td><strong><?php echo htmlspecialchars($d['producto_nombre']); ?></strong>
                                <td>
                                    <?php if ($d['tipo_descuento'] == 'porcentaje'): ?>
                                        <span style="color: var(--primary);"><?php echo $d['valor_descuento']; ?>%</span>
                                    <?php else: ?>
                                        <span style="color: var(--primary);">$<?php echo number_format($d['valor_descuento'], 0); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['fecha_inicio']): ?>
                                        Desde <?php echo date('d/m/Y', strtotime($d['fecha_inicio'])); ?><br>
                                    <?php endif; ?>
                                    <?php if ($d['fecha_fin']): ?>
                                        Hasta <?php echo date('d/m/Y', strtotime($d['fecha_fin'])); ?>
                                    <?php endif; ?>
                                    <?php if (!$d['fecha_inicio'] && !$d['fecha_fin']): ?>
                                        <span class="badge-activo">Sin fecha límite</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($activo): ?>
                                        <span class="badge-activo">🟢 Activo</span>
                                    <?php else: ?>
                                        <span class="badge-inactivo">🔴 Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="acciones">
                                    <button class="btn-editar" onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($d)); ?>)">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </button>
                                    <a href="?eliminar=<?php echo $d['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar este descuento?')">
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

    <!-- Modal Editar Descuento -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="cerrarModal()">&times;</span>
            <h2><i class="fa-solid fa-pen"></i> Editar Descuento</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label><i class="fa-solid fa-box"></i> Producto</label>
                    <select name="producto_id" id="edit_producto_id" required>
                        <option value="">Seleccionar producto</option>
                        <?php 
                        $productos->data_seek(0);
                        while($p = $productos->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-percent"></i> Tipo de descuento</label>
                        <select name="tipo" id="edit_tipo" required>
                            <option value="porcentaje">📊 Porcentaje (%)</option>
                            <option value="fijo">💰 Valor fijo ($)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-chart-line"></i> Valor del descuento</label>
                        <input type="number" name="valor" id="edit_valor" step="0.01" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar"></i> Fecha inicio</label>
                        <input type="date" name="fecha_inicio" id="edit_fecha_inicio">
                    </div>

                    <div class="form-group">
                        <label><i class="fa-solid fa-calendar-check"></i> Fecha fin</label>
                        <input type="date" name="fecha_fin" id="edit_fecha_fin">
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="activo" id="edit_activo" value="1">
                    <label>Descuento activo</label>
                </div>

                <div class="modal-actions">
                    <button type="submit" name="editar" class="btn-modal primary">
                        <i class="fa-solid fa-save"></i> Guardar cambios
                    </button>
                    <button type="button" class="btn-modal secondary" onclick="cerrarModal()">
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

        function abrirModalEditar(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_producto_id').value = data.producto_id;
            document.getElementById('edit_tipo').value = data.tipo_descuento;
            document.getElementById('edit_valor').value = data.valor_descuento;
            document.getElementById('edit_fecha_inicio').value = data.fecha_inicio || '';
            document.getElementById('edit_fecha_fin').value = data.fecha_fin || '';
            document.getElementById('edit_activo').checked = data.activo == 1;
            
            document.getElementById('editModal').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                cerrarModal();
            }
        }
    </script>
</body>
</html>