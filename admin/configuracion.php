<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Crear tabla de configuración si no existe
$conn->query("
    CREATE TABLE IF NOT EXISTS configuracion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(50) UNIQUE NOT NULL,
        valor TEXT,
        tipo VARCHAR(20) DEFAULT 'texto',
        descripcion TEXT
    )
");

// Insertar valores por defecto si la tabla está vacía
$check = $conn->query("SELECT COUNT(*) as total FROM configuracion");
$total = $check->fetch_assoc()['total'];

if ($total == 0) {
    $config_default = [
        ['tienda_nombre', 'Tienda MS', 'texto', 'Nombre de la tienda'],
        ['tienda_descripcion', 'Los mejores zapatos al mejor precio', 'textarea', 'Descripción de la tienda'],
        ['tienda_email', 'contacto@tiendams.com', 'email', 'Email de contacto'],
        ['tienda_telefono', '573001234567', 'telefono', 'Teléfono principal'],
        ['tienda_whatsapp', '573001234567', 'telefono', 'Número de WhatsApp para pedidos'],
        ['tienda_direccion', 'Calle Principal #123', 'texto', 'Dirección física'],
        ['tienda_horario', 'Lunes a Viernes 9am - 6pm, Sábados 9am - 2pm', 'textarea', 'Horario de atención'],
        ['moneda_simbolo', '$', 'texto', 'Símbolo de moneda'],
        ['moneda_codigo', 'COP', 'texto', 'Código de moneda'],
        ['envio_costo', '10000', 'numero', 'Costo de envío por defecto'],
        ['envio_gratis_desde', '200000', 'numero', 'Envío gratis a partir de'],
        ['whatsapp_mensaje', 'Hola, quiero hacer un pedido:', 'textarea', 'Mensaje predeterminado para WhatsApp'],
        ['pago_efectivo', '1', 'booleano', 'Aceptar pago en efectivo'],
        ['pago_transferencia', '1', 'booleano', 'Aceptar transferencia bancaria'],
        ['pago_nequi', '1', 'booleano', 'Aceptar Nequi'],
        ['pago_daviplata', '1', 'booleano', 'Aceptar Daviplata'],
        ['nequi_numero', '3001234567', 'telefono', 'Número de Nequi'],
        ['bancolombia_cuenta', '123456789', 'texto', 'Cuenta de Bancolombia'],
        ['bancolombia_titular', 'Tienda MS', 'texto', 'Titular de la cuenta'],
        ['redes_facebook', 'https://facebook.com/tiendams', 'url', 'URL de Facebook'],
        ['redes_instagram', 'https://instagram.com/tiendams', 'url', 'URL de Instagram'],
        ['redes_tiktok', 'https://tiktok.com/@tiendams', 'url', 'URL de TikTok'],
        ['impuesto_porcentaje', '19', 'numero', 'Porcentaje de impuesto'],
        ['limite_productos_destacados', '8', 'numero', 'Número de productos destacados'],
        ['modo_mantenimiento', '0', 'booleano', 'Modo mantenimiento'],
        ['color_primario', '#2c3e50', 'color', 'Color principal'],
        ['color_secundario', '#34495e', 'color', 'Color secundario']
    ];
    
    $stmt = $conn->prepare("INSERT INTO configuracion (clave, valor, tipo, descripcion) VALUES (?, ?, ?, ?)");
    foreach ($config_default as $conf) {
        $stmt->bind_param("ssss", $conf[0], $conf[1], $conf[2], $conf[3]);
        $stmt->execute();
    }
}

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST as $clave => $valor) {
        if ($clave != 'guardar') {
            $valor = is_array($valor) ? implode(',', $valor) : trim($valor);
            $stmt = $conn->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
            $stmt->bind_param("ss", $valor, $clave);
            $stmt->execute();
        }
    }
    $mensaje = "✅ Configuración guardada exitosamente";
}

// Obtener toda la configuración
$config = [];
$result = $conn->query("SELECT * FROM configuracion ORDER BY clave");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row;
}

// Agrupar configuración por secciones
$secciones = [
    'generales' => [
        'titulo' => 'Información General',
        'icono' => '🏪',
        'campos' => ['tienda_nombre', 'tienda_descripcion', 'tienda_email', 'tienda_telefono', 'tienda_whatsapp', 'tienda_direccion', 'tienda_horario']
    ],
    'moneda' => [
        'titulo' => 'Moneda y Precios',
        'icono' => '💰',
        'campos' => ['moneda_simbolo', 'moneda_codigo', 'impuesto_porcentaje']
    ],
    'envio' => [
        'titulo' => 'Envíos',
        'icono' => '🚚',
        'campos' => ['envio_costo', 'envio_gratis_desde']
    ],
    'pagos' => [
        'titulo' => 'Métodos de Pago',
        'icono' => '💳',
        'campos' => ['pago_efectivo', 'pago_transferencia', 'pago_nequi', 'pago_daviplata', 'nequi_numero', 'bancolombia_cuenta', 'bancolombia_titular']
    ],
    'whatsapp' => [
        'titulo' => 'WhatsApp',
        'icono' => '💬',
        'campos' => ['whatsapp_mensaje']
    ],
    'redes' => [
        'titulo' => 'Redes Sociales',
        'icono' => '📱',
        'campos' => ['redes_facebook', 'redes_instagram', 'redes_tiktok']
    ],
    'apariencia' => [
        'titulo' => 'Apariencia',
        'icono' => '🎨',
        'campos' => ['color_primario', 'color_secundario', 'limite_productos_destacados']
    ],
    'avanzado' => [
        'titulo' => 'Configuración Avanzada',
        'icono' => '⚙️',
        'campos' => ['modo_mantenimiento']
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Configuración - Tienda MS</title>
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

        /* ===== NAVEGACIÓN RÁPIDA ===== */
        .config-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .nav-link {
            background: rgba(255, 255, 255, 0.05);
            padding: 8px 16px;
            border-radius: 30px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.85rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .nav-link:hover {
            background: rgba(255, 107, 53, 0.15);
            color: var(--primary);
        }

        /* ===== SECCIONES ===== */
        .config-section {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }

        .section-icon {
            font-size: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
        }

        .section-body {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .config-item {
            margin-bottom: 5px;
        }

        .config-item label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .config-item input, .config-item select, .config-item textarea {
            width: 100%;
            padding: 10px 12px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: white;
            font-size: 0.9rem;
        }

        .config-item input:focus, .config-item select:focus, .config-item textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-switch input {
            width: 40px;
            height: 20px;
            appearance: none;
            background: #334155;
            border-radius: 20px;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
        }

        .toggle-switch input:checked {
            background: var(--primary);
        }

        .toggle-switch input::before {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: var(--transition);
        }

        .toggle-switch input:checked::before {
            left: 22px;
        }

        .toggle-text {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .color-input {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .color-input input[type="color"] {
            width: 50px;
            height: 45px;
            padding: 5px;
            cursor: pointer;
        }

        .color-input input[type="text"] {
            flex: 1;
        }

        /* ===== BOTONES ===== */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 28px;
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
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 28px;
            border: 1px solid var(--border);
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* ===== RESPALDO ===== */
        .backup-section {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            margin-top: 30px;
            border: 1px solid var(--border);
        }

        .backup-section h3 {
            font-size: 1.1rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
        }

        .backup-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
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
            
            .section-body { grid-template-columns: 1fr; }
            .config-nav { justify-content: center; }
            .form-actions { flex-direction: column; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
            .backup-actions { flex-direction: column; }
            .backup-actions .btn-secondary { width: 100%; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .config-section { padding: 18px; }
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
                <a href="cupones.php" class="nav-item"><i class="fa-solid fa-ticket"></i> Cupones</a>
                <a href="finanzas.php" class="nav-item"><i class="fa-solid fa-wallet"></i> Finanzas</a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">CONFIGURACIÓN</div>
                <a href="configuracion.php" class="nav-item active"><i class="fa-solid fa-gear"></i> Configuración</a>
                <a href="logout.php" class="nav-item logout"><i class="fa-solid fa-right-from-bracket"></i> Cerrar Sesión</a>
            </div>
        </nav>
    </div>

    <div class="main-content">
        <div class="header">
            <h1><i class="fa-solid fa-gear"></i> Configuración</h1>
            <p>Personaliza todos los aspectos de tu tienda</p>
        </div>

        <?php if (isset($mensaje)): ?>
            <div class="alert success">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Navegación rápida -->
            <div class="config-nav">
                <?php foreach ($secciones as $id => $seccion): ?>
                    <a href="#<?php echo $id; ?>" class="nav-link">
                        <?php echo $seccion['icono']; ?>
                        <span><?php echo $seccion['titulo']; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Secciones -->
            <?php foreach ($secciones as $id => $seccion): ?>
                <div id="<?php echo $id; ?>" class="config-section">
                    <div class="section-header">
                        <span class="section-icon"><?php echo $seccion['icono']; ?></span>
                        <h2><?php echo $seccion['titulo']; ?></h2>
                    </div>
                    
                    <div class="section-body">
                        <?php foreach ($seccion['campos'] as $clave): 
                            if (!isset($config[$clave])) continue;
                            $campo = $config[$clave];
                        ?>
                            <div class="config-item">
                                <label for="<?php echo $clave; ?>">
                                    <?php echo $campo['descripcion']; ?>
                                </label>
                                
                                <?php if ($campo['tipo'] == 'textarea'): ?>
                                    <textarea id="<?php echo $clave; ?>" 
                                              name="<?php echo $clave; ?>" 
                                              rows="3"><?php echo htmlspecialchars($campo['valor']); ?></textarea>
                                
                                <?php elseif ($campo['tipo'] == 'booleano'): ?>
                                    <div class="toggle-switch">
                                        <input type="checkbox" 
                                               id="<?php echo $clave; ?>" 
                                               name="<?php echo $clave; ?>" 
                                               value="1"
                                               <?php echo $campo['valor'] == '1' ? 'checked' : ''; ?>>
                                        <span class="toggle-text">
                                            <?php echo $campo['valor'] == '1' ? 'Activado' : 'Desactivado'; ?>
                                        </span>
                                    </div>
                                
                                <?php elseif ($campo['tipo'] == 'color'): ?>
                                    <div class="color-input">
                                        <input type="color" 
                                               id="<?php echo $clave; ?>" 
                                               name="<?php echo $clave; ?>" 
                                               value="<?php echo $campo['valor']; ?>">
                                        <input type="text" 
                                               value="<?php echo $campo['valor']; ?>" 
                                               onchange="this.previousElementSibling.value = this.value">
                                    </div>
                                
                                <?php elseif ($campo['tipo'] == 'numero'): ?>
                                    <input type="number" 
                                           id="<?php echo $clave; ?>" 
                                           name="<?php echo $clave; ?>" 
                                           value="<?php echo $campo['valor']; ?>" 
                                           step="0.01"
                                           min="0">
                                
                                <?php elseif ($campo['tipo'] == 'email'): ?>
                                    <input type="email" 
                                           id="<?php echo $clave; ?>" 
                                           name="<?php echo $clave; ?>" 
                                           value="<?php echo htmlspecialchars($campo['valor']); ?>">
                                
                                <?php elseif ($campo['tipo'] == 'url'): ?>
                                    <input type="url" 
                                           id="<?php echo $clave; ?>" 
                                           name="<?php echo $clave; ?>" 
                                           value="<?php echo htmlspecialchars($campo['valor']); ?>">
                                
                                <?php elseif ($campo['tipo'] == 'telefono'): ?>
                                    <input type="tel" 
                                           id="<?php echo $clave; ?>" 
                                           name="<?php echo $clave; ?>" 
                                           value="<?php echo htmlspecialchars($campo['valor']); ?>"
                                           placeholder="Ej: 573001234567">
                                
                                <?php else: ?>
                                    <input type="text" 
                                           id="<?php echo $clave; ?>" 
                                           name="<?php echo $clave; ?>" 
                                           value="<?php echo htmlspecialchars($campo['valor']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" name="guardar" class="btn-primary">
                    <i class="fa-solid fa-save"></i> Guardar Configuración
                </button>
                <button type="reset" class="btn-secondary" onclick="return confirm('¿Restaurar valores anteriores sin guardar?')">
                    <i class="fa-solid fa-undo"></i> Deshacer cambios
                </button>
            </div>
        </form>

        <!-- Sección de respaldo -->
        <div class="backup-section">
            <h3><i class="fa-solid fa-database"></i> Respaldo de Configuración</h3>
            <div class="backup-actions">
                <button onclick="exportarConfig()" class="btn-secondary">
                    <i class="fa-solid fa-download"></i> Exportar configuración
                </button>
                <input type="file" id="importFile" accept=".json" style="display: none;" onchange="importarConfig()">
                <button onclick="document.getElementById('importFile').click()" class="btn-secondary">
                    <i class="fa-solid fa-upload"></i> Importar configuración
                </button>
                <button onclick="restaurarDefault()" class="btn-secondary">
                    <i class="fa-solid fa-rotate-left"></i> Restaurar valores por defecto
                </button>
            </div>
        </div>
    </div>

    <script>
        // Sincronizar color picker con input text
        document.querySelectorAll('.color-input input[type="color"]').forEach(colorInput => {
            colorInput.addEventListener('change', function() {
                const textInput = this.nextElementSibling;
                if (textInput) textInput.value = this.value;
            });
        });

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

        // Smooth scroll para navegación
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Exportar configuración
        function exportarConfig() {
            const config = {};
            document.querySelectorAll('input, textarea, select').forEach(input => {
                if (input.name && input.name !== 'guardar') {
                    if (input.type === 'checkbox') {
                        config[input.name] = input.checked ? '1' : '0';
                    } else {
                        config[input.name] = input.value;
                    }
                }
            });
            
            const dataStr = JSON.stringify(config, null, 2);
            const dataUri = 'data:application/json;charset=utf-8,'+ encodeURIComponent(dataStr);
            
            const linkElement = document.createElement('a');
            linkElement.setAttribute('href', dataUri);
            linkElement.setAttribute('download', 'configuracion_tienda_ms.json');
            linkElement.click();
        }

        // Importar configuración
        function importarConfig() {
            const file = document.getElementById('importFile').files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const config = JSON.parse(e.target.result);
                    
                    Object.keys(config).forEach(key => {
                        const inputs = document.querySelectorAll(`[name="${key}"]`);
                        inputs.forEach(input => {
                            if (input.type === 'checkbox') {
                                input.checked = config[key] === '1';
                                const text = input.closest('.toggle-switch')?.querySelector('.toggle-text');
                                if (text) text.textContent = input.checked ? 'Activado' : 'Desactivado';
                            } else {
                                input.value = config[key];
                            }
                        });
                    });
                    
                    alert('✅ Configuración importada exitosamente. No olvides guardar los cambios.');
                } catch (error) {
                    alert('❌ Error al importar el archivo');
                }
            };
            reader.readAsText(file);
        }

        // Restaurar valores por defecto
        function restaurarDefault() {
            if (confirm('¿Estás seguro de restaurar todos los valores por defecto? Se perderán los cambios actuales.')) {
                window.location.href = 'configuracion.php?restaurar=1';
            }
        }
    </script>
</body>
</html>