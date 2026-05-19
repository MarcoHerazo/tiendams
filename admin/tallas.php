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

// Agregar talla
if (isset($_POST['agregar'])) {
    $talla = trim($_POST['talla']);
    $tipo = $_POST['tipo'];
    
    if (!empty($talla)) {
        $stmt = $conn->prepare("INSERT INTO tallas (talla, tipo) VALUES (?, ?)");
        $stmt->bind_param("ss", $talla, $tipo);
        
        if ($stmt->execute()) {
            $mensaje = "Talla agregada exitosamente";
        } else {
            $error = "Error al agregar la talla";
        }
    } else {
        $error = "La talla es obligatoria";
    }
}

// Editar talla
if (isset($_POST['editar'])) {
    $id = intval($_POST['id']);
    $talla = trim($_POST['talla']);
    $tipo = $_POST['tipo'];
    
    if (!empty($talla)) {
        $stmt = $conn->prepare("UPDATE tallas SET talla = ?, tipo = ? WHERE id = ?");
        $stmt->bind_param("ssi", $talla, $tipo, $id);
        
        if ($stmt->execute()) {
            $mensaje = "Talla actualizada exitosamente";
        } else {
            $error = "Error al actualizar la talla";
        }
    } else {
        $error = "La talla es obligatoria";
    }
}

// Eliminar talla
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    // Verificar si hay variantes usando esta talla
    $check = $conn->query("SELECT COUNT(*) as total FROM producto_variantes WHERE talla_id = $id");
    $result = $check->fetch_assoc();
    
    if ($result['total'] == 0) {
        $conn->query("DELETE FROM tallas WHERE id = $id");
        $mensaje = "Talla eliminada exitosamente";
    } else {
        $error = "No se puede eliminar: hay productos usando esta talla";
    }
}

// Obtener todas las tallas
$tallas = $conn->query("SELECT * FROM tallas ORDER BY 
                        FIELD(tipo, 'numerica', 'letra'), 
                        CAST(talla AS UNSIGNED), talla");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Tallas - Tienda MS</title>
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

        /* ===== LISTA DE TALLAS ===== */
        .tallas-list {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }

        .tallas-list h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .tallas-list h2 i { color: var(--primary); }

        .tallas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .talla-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 14px;
            padding: 16px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .talla-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            background: rgba(255, 107, 53, 0.05);
        }

        .talla-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .talla-valor {
            font-size: 1.3rem;
            font-weight: 800;
            color: white;
        }

        .talla-tipo {
            font-size: 1rem;
            opacity: 0.7;
        }

        .talla-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding: 8px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
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

        .talla-acciones {
            display: flex;
            gap: 10px;
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
            .tallas-grid { grid-template-columns: 1fr; }
            .talla-card { padding: 14px; }
            .modal-content { padding: 20px; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .modal-actions { flex-direction: column; }
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
                <a href="tallas.php" class="nav-item active"><i class="fa-solid fa-ruler-combined"></i> Tallas</a>
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
            <h1><i class="fa-solid fa-ruler"></i> Tallas</h1>
            <p>Administra las tallas disponibles para tus productos</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="success-message"><i class="fa-solid fa-circle-check"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario para nueva talla -->
        <div class="form-container">
            <h2><i class="fa-solid fa-plus"></i> Agregar Nueva Talla</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="talla">Talla *</label>
                        <input type="text" id="talla" name="talla" required placeholder="Ej: 38, 39, S, M, L...">
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo">Tipo de talla</label>
                        <select id="tipo" name="tipo">
                            <option value="numerica">🔢 Numérica (35-45)</option>
                            <option value="letra">📝 Letra (XS, S, M, L, XL)</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="agregar" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Agregar Talla
                </button>
            </form>
        </div>

        <!-- Lista de tallas -->
        <div class="tallas-list">
            <h2><i class="fa-solid fa-list"></i> Tallas Existentes</h2>
            
            <?php if ($tallas->num_rows == 0): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-ruler"></i>
                    <p>No hay tallas aún. Agrega la primera.</p>
                </div>
            <?php else: ?>
                <div class="tallas-grid">
                    <?php 
                    $tallas->data_seek(0);
                    while($row = $tallas->fetch_assoc()): 
                        $count = $conn->query("SELECT COUNT(*) as total FROM producto_variantes WHERE talla_id = " . $row['id']);
                        $total_usos = $count->fetch_assoc()['total'];
                    ?>
                        <div class="talla-card">
                            <div class="talla-header">
                                <span class="talla-valor"><?php echo htmlspecialchars($row['talla']); ?></span>
                                <span class="talla-tipo">
                                    <?php echo $row['tipo'] == 'numerica' ? '🔢' : '📝'; ?>
                                </span>
                            </div>
                            
                            <div class="talla-info">
                                <div class="info-item">
                                    <span class="info-label">ID:</span>
                                    <span class="info-value">#<?php echo $row['id']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Usos:</span>
                                    <span class="info-value"><?php echo $total_usos; ?></span>
                                </div>
                            </div>

                            <div class="talla-acciones">
                                <button onclick="editarTalla(<?php echo $row['id']; ?>)" class="btn-editar">
                                    <i class="fa-solid fa-pen"></i> Editar
                                </button>
                                
                                <?php if ($total_usos == 0): ?>
                                    <a href="?eliminar=<?php echo $row['id']; ?>" 
                                       class="btn-eliminar" 
                                       onclick="return confirm('¿Eliminar esta talla?')">
                                        <i class="fa-solid fa-trash"></i> Eliminar
                                    </a>
                                <?php else: ?>
                                    <button class="btn-eliminar disabled" disabled 
                                            title="No se puede eliminar: hay productos con esta talla">
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

    <!-- Modal para editar talla -->
    <div id="editarModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fa-solid fa-pen"></i> Editar Talla</h2>
            
            <form method="POST" id="editarForm">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_talla">Talla *</label>
                        <input type="text" id="edit_talla" name="talla" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_tipo">Tipo de talla</label>
                        <select id="edit_tipo" name="tipo">
                            <option value="numerica">🔢 Numérica (35-45)</option>
                            <option value="letra">📝 Letra (XS, S, M, L, XL)</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" name="editar" class="btn-primary">
                        <i class="fa-solid fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn-secondary" onclick="cerrarModal()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const tallas = <?php 
            $tallas->data_seek(0);
            $tallas_array = [];
            while($row = $tallas->fetch_assoc()) {
                $tallas_array[] = $row;
            }
            echo json_encode($tallas_array); 
        ?>;

        function editarTalla(id) {
            const talla = tallas.find(t => t.id == id);
            
            if (talla) {
                document.getElementById('edit_id').value = talla.id;
                document.getElementById('edit_talla').value = talla.talla;
                document.getElementById('edit_tipo').value = talla.tipo;
                
                document.getElementById('editarModal').style.display = 'flex';
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
    </script>
</body>
</html>