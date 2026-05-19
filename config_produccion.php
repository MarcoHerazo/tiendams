<?php
// config_produccion.php - Configuración para el hosting
// ====================================================

// █████████████████████████████████████████████████████
// ██  DATOS DE LA BASE DE DATOS EN EL HOSTING       ██
// ██  (Estos datos te los da tu proveedor de hosting)██
// █████████████████████████████████████████████████████

// Host de MySQL (casi siempre es 'localhost')
define('DB_HOST', 'localhost');

// Usuario de la base de datos (ej: u123456789_tienda)
define('DB_USER', 'AQUI_TU_USUARIO');

// Contraseña de la base de datos
define('DB_PASS', 'AQUI_TU_CONTRASEÑA');

// Nombre de la base de datos (ej: u123456789_tienda)
define('DB_NAME', 'AQUI_TU_BD');

// █████████████████████████████████████████████████████
// ██  CONFIGURACIÓN ADICIONAL (opcional)            ██
// █████████████████████████████████████████████████████

// URL completa de tu sitio web (sin / al final)
define('URL_SITIO', 'https://tudominio.com');

// Modo producción: true = ocultar errores, false = mostrar errores
define('MODO_PRODUCCION', true);

// █████████████████████████████████████████████████████
// ██  NO MODIFICAR NADA DEBAJO DE ESTA LÍNEA        ██
// █████████████████████████████████████████████████████

// Verificar que todos los datos estén configurados
if (DB_USER == 'AQUI_TU_USUARIO' || DB_PASS == 'AQUI_TU_CONTRASEÑA' || DB_NAME == 'AQUI_TU_BD') {
    die('❌ ERROR: Debes configurar config_produccion.php con tus datos reales del hosting');
}

// Mostrar errores según el modo
if (MODO_PRODUCCION) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Zona horaria (ajusta según tu ubicación)
date_default_timezone_set('America/Bogota'); // Para Colombia

// █████████████████████████████████████████████████████
// ██  FIN DEL ARCHIVO                               ██
// █████████████████████████████████████████████████████
?>