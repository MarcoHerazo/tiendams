<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
include '../conexion.php';

// Obtener configuración básica (para el nombre de la tienda)
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('tienda_nombre', 'moneda_simbolo')");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Finanzas - <?php echo $config['tienda_nombre'] ?? 'Tienda MS'; ?></title>
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

        /* ===== TARJETAS DE MÓDULOS ===== */
        .modulos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .modulo-card {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .modulo-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .modulo-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--primary);
        }

        .modulo-card h2 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: white;
        }

        .modulo-card p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .btn-modulo {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 107, 53, 0.1);
            color: var(--primary);
            border: 1px solid rgba(255, 107, 53, 0.3);
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: var(--transition);
        }

        .btn-modulo:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
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
            
            .modulos-grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 1.5rem; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 12px; padding-top: 70px; }
            .modulo-card { padding: 18px; }
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
                <a href="finanzas.php" class="nav-item active"><i class="fa-solid fa-wallet"></i> Finanzas</a>
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
            <h1><i class="fa-solid fa-chart-line"></i> Módulo de Finanzas</h1>
            <p>Gestiona toda la información financiera de tu tienda</p>
        </div>

        <!-- Grid de módulos -->
        <div class="modulos-grid">
            <!-- Módulo 1: Panel Financiero -->
            <div class="modulo-card">
                <div class="modulo-icon"><i class="fa-solid fa-chart-pie"></i></div>
                <h2>📊 Panel Financiero</h2>
                <p>Vista general del negocio: ingresos, gastos, balance y productos más rentables.</p>
                <a href="finanzas_panel.php" class="btn-modulo">
                    <i class="fa-solid fa-eye"></i> Ver panel
                </a>
            </div>

            <!-- Módulo 2: Registro de Gastos -->
            <div class="modulo-card">
                <div class="modulo-icon"><i class="fa-solid fa-receipt"></i></div>
                <h2>💸 Registro de Gastos</h2>
                <p>Controla todas las salidas de dinero: compras, servicios, envíos, publicidad y más.</p>
                <a href="finanzas_gastos.php" class="btn-modulo">
                    <i class="fa-solid fa-pen"></i> Gestionar gastos
                </a>
            </div>

            <!-- Módulo 3: Rentabilidad por Producto -->
            <div class="modulo-card">
                <div class="modulo-icon"><i class="fa-solid fa-box"></i></div>
                <h2>📦 Rentabilidad por Producto</h2>
                <p>Analiza cuánto ganaste con cada producto: comprado vs vendido, precios y ganancias.</p>
                <a href="finanzas_rentabilidad.php" class="btn-modulo">
                    <i class="fa-solid fa-calculator"></i> Ver rentabilidad
                </a>
            </div>

            <!-- Módulo 4: Cuentas por Cobrar -->
            <div class="modulo-card">
                <div class="modulo-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <h2>💰 Cuentas por Cobrar</h2>
                <p>Controla los clientes que te deben plata, fechas de vencimiento y pagos pendientes.</p>
                <a href="finanzas_cobrar.php" class="btn-modulo">
                    <i class="fa-solid fa-clock"></i> Gestionar cobranzas
                </a>
            </div>

            <!-- Módulo 5: Reportes Financieros -->
            <div class="modulo-card">
                <div class="modulo-icon"><i class="fa-solid fa-chart-simple"></i></div>
                <h2>📈 Reportes Financieros</h2>
                <p>Reportes detallados por período, categoría de gastos y comparativas mensuales.</p>
                <a href="finanzas_reportes.php" class="btn-modulo">
                    <i class="fa-solid fa-file-lines"></i> Ver reportes
                </a>
            </div>

            <!-- Módulo 6: Descuentos -->
            <div class="modulo-card">
                <div class="modulo-icon"><i class="fa-solid fa-tag"></i></div>
                <h2>🏷️ Descuentos</h2>
                <p>Gestiona descuentos directos en productos y ofertas especiales.</p>
                <a href="descuentos.php" class="btn-modulo">
                    <i class="fa-solid fa-percent"></i> Ver descuentos
                </a>
            </div>

            <!-- Módulo 7: Cupones -->
            <div class="modulo-card">
                <div class="modulo-icon"><i class="fa-solid fa-ticket"></i></div>
                <h2>🎫 Cupones</h2>
                <p>Crea y administra códigos promocionales para tus clientes.</p>
                <a href="cupones.php" class="btn-modulo">
                    <i class="fa-solid fa-gift"></i> Gestionar cupones
                </a>
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