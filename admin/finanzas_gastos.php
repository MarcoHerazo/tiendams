<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// ============================================
// PROCESAR FORMULARIOS (AGREGAR GASTO)
// ============================================
if (isset($_POST['agregar_gasto'])) {
    $fecha = $_POST['fecha'];
    $concepto = trim($_POST['concepto']);
    $categoria = $_POST['categoria'];
    $proveedor = trim($_POST['proveedor']);
    $monto = $_POST['monto'];
    $notas = trim($_POST['notas']);
    
    $stmt = $conn->prepare("INSERT INTO gastos (fecha, concepto, categoria, proveedor, monto, notas) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssds", $fecha, $concepto, $categoria, $proveedor, $monto, $notas);
    
    if ($stmt->execute()) {
        $mensaje = "✅ Gasto agregado correctamente";
    } else {
        $error = "❌ Error al agregar el gasto: " . $conn->error;
    }
}

// ============================================
// PROCESAR ELIMINACIÓN
// ============================================
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $conn->query("DELETE FROM gastos WHERE id = $id");
    $mensaje = "✅ Gasto eliminado";
}

// ============================================
// OBTENER DATOS CON FILTROS
// ============================================
$filtro_fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';

$sql = "SELECT * FROM gastos WHERE 1=1";
if (!empty($filtro_fecha)) $sql .= " AND fecha = '$filtro_fecha'";
if (!empty($filtro_categoria)) $sql .= " AND categoria = '$filtro_categoria'";
$sql .= " ORDER BY fecha DESC, id DESC";
$gastos = $conn->query($sql);

// Totales
$total_general = 0;
$gastos->data_seek(0);
while ($g = $gastos->fetch_assoc()) $total_general += $g['monto'];
$gastos->data_seek(0);

// Configuración
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave = 'tienda_nombre'");
while ($row = $result->fetch_assoc()) $config[$row['clave']] = $row['valor'];

// Función para formato pesos colombianos
function formato_peso($numero) {
    return '$' . number_format($numero, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Gastos - <?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,107,53,0.3);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.1);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        /* ===== MENSAJES ===== */
        .mensaje {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mensaje.success {
            background: rgba(16,185,129,0.1);
            color: #34d399;
            border: 1px solid rgba(16,185,129,0.3);
        }

        .mensaje.error {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
        }

        /* ===== FILTROS ACTIVOS ===== */
        .filtros-activos {
            background: var(--card);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border: 1px solid var(--border);
        }

        .badge-filtro {
            background: rgba(255,107,53,0.1);
            color: var(--primary);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-quitar-filtros {
            background: transparent;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
            transition: var(--transition);
        }

        .btn-quitar-filtros:hover {
            background: rgba(239,68,68,0.1);
            color: #f87171;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-submit {
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

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,107,53,0.3);
        }

        /* ===== TABLA ===== */
        .table-container {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th {
            text-align: left;
            padding: 12px;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }

        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        .badge-categoria {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,107,53,0.1);
            color: var(--primary);
            display: inline-block;
        }

        .btn-delete {
            background: rgba(239,68,68,0.1);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }

        .total-row {
            background: rgba(0,0,0,0.2);
        }

        .total-row td {
            color: white;
            font-weight: 700;
            border-top: 2px solid var(--primary);
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
            
            .header { flex-direction: column; align-items: flex-start; }
            .header-actions { width: 100%; }
            .btn-primary, .btn-secondary { flex: 1; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-submit { width: 100%; justify-content: center; }
            .filtros-activos { flex-direction: column; align-items: flex-start; }
            .btn-quitar-filtros { margin-left: 0; width: 100%; text-align: center; }
            .table-container { padding: 15px; }
            table { min-width: 500px; }
            th, td { padding: 10px 8px; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .header h1 { font-size: 1.5rem; }
            .form-container { padding: 18px; }
            table { min-width: 450px; }
            th, td { padding: 8px 6px; font-size: 0.8rem; }
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
            <h1><i class="fa-solid fa-receipt"></i> Gastos</h1>
            <div class="header-actions">
                <a href="finanzas_gastos_filtros.php" class="btn-primary">
                    <i class="fa-solid fa-sliders"></i> Filtrar
                </a>
                <a href="finanzas.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="mensaje success">
                <i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="mensaje error">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($filtro_fecha) || !empty($filtro_categoria)): ?>
        <div class="filtros-activos">
            <span><i class="fa-solid fa-filter"></i> Filtros activos:</span>
            <?php if (!empty($filtro_fecha)): ?>
                <span class="badge-filtro">
                    <i class="fa-regular fa-calendar"></i> <?php echo date('d/m/Y', strtotime($filtro_fecha)); ?>
                </span>
            <?php endif; ?>
            <?php if (!empty($filtro_categoria)): ?>
                <span class="badge-filtro">
                    <i class="fa-regular fa-folder"></i> <?php echo $filtro_categoria; ?>
                </span>
            <?php endif; ?>
            <a href="finanzas_gastos.php" class="btn-quitar-filtros">
                <i class="fa-solid fa-times"></i> Quitar filtros
            </a>
        </div>
        <?php endif; ?>

        <!-- Formulario de nuevo gasto -->
        <div class="form-container">
            <h2><i class="fa-solid fa-plus"></i> Nuevo gasto</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Fecha</label>
                        <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Categoría</label>
                        <select name="categoria" required>
                            <option value="Proveedor">📦 Proveedor</option>
                            <option value="Logística">🚚 Logística</option>
                            <option value="Publicidad">📢 Publicidad</option>
                            <option value="Servicios">💼 Servicios</option>
                            <option value="Empaque">📦 Empaque</option>
                            <option value="Nómina">👥 Nómina</option>
                            <option value="Arriendo">🏢 Arriendo</option>
                            <option value="Impuestos">🧾 Impuestos</option>
                            <option value="Otros">🎁 Otros</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Concepto</label>
                        <input type="text" name="concepto" placeholder="Ej: Compra Nike Air x5" required>
                    </div>
                    <div class="form-group">
                        <label>Proveedor</label>
                        <input type="text" name="proveedor" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label>Monto</label>
                        <input type="number" name="monto" step="0.01" placeholder="0.00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notas</label>
                    <textarea name="notas" rows="2" placeholder="Opcional"></textarea>
                </div>
                <button type="submit" name="agregar_gasto" class="btn-submit">
                    <i class="fa-solid fa-save"></i> Guardar gasto
                </button>
            </form>
        </div>

        <!-- Tabla de gastos -->
        <div class="table-container">
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Categoría</th>
                            <th>Concepto</th>
                            <th>Proveedor</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </thead>
                    <tbody>
                        <?php if ($gastos && $gastos->num_rows > 0): ?>
                            <?php while($g = $gastos->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($g['fecha'])); ?></td>
                                <td><span class="badge-categoria"><?php echo $g['categoria']; ?></span></td>
                                <td><?php echo htmlspecialchars($g['concepto']); ?></td>
                                <td><?php echo htmlspecialchars($g['proveedor'] ?: '-'); ?></td>
                                <td><strong style="color: var(--primary);"><?php echo formato_peso($g['monto']); ?></strong></td>
                                <td>
                                    <a href="?eliminar=<?php echo $g['id']; ?>&categoria=<?php echo urlencode($filtro_categoria); ?>&fecha=<?php echo urlencode($filtro_fecha); ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('¿Eliminar este gasto?')">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="total-row">
                                <td colspan="4" style="text-align: right;"><strong>TOTAL GENERAL:</strong></td>
                                <td><strong style="color: var(--primary);"><?php echo formato_peso($total_general); ?></strong></td>
                                <td></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <i class="fa-solid fa-receipt" style="font-size: 2.5rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                    <p>No hay gastos registrados</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
