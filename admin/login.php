<?php
session_start();
include '../conexion.php';

// Redirigir si ya está logueado
if (isset($_SESSION['admin'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    $usuario = trim($_POST['usuario']);
    $contraseña = $_POST['contraseña'];
    
    // Validar que no estén vacíos
    if (empty($usuario) || empty($contraseña)) {
        $error = "Todos los campos son obligatorios";
    } else {
        // Usar consultas preparadas para prevenir SQL injection
        $sql = "SELECT * FROM admin WHERE usuario = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $row = $result->fetch_assoc();
            // Aquí idealmente deberías usar password_hash() y password_verify()
            if ($contraseña === $row['contraseña']) {
                $_SESSION['admin'] = $row['usuario'];
                $_SESSION['admin_id'] = $row['id']; // Si tienes campo id
                $_SESSION['login_time'] = time();
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Usuario no encontrado";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrador - Tienda MS</title>
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <input 
                type="text" 
                name="usuario" 
                placeholder="Usuario" 
                value="<?php echo isset($_POST['usuario']) ? htmlspecialchars($_POST['usuario']) : ''; ?>"
                required
                autofocus
            >
            <input 
                type="password" 
                name="contraseña" 
                placeholder="Contraseña" 
                required
            >
            <button type="submit" name="login" id="loginBtn">Ingresar</button>
        </form>

        <!-- Opcional: Enlace para recuperar contraseña -->
        <div style="margin-top: 20px; font-size: 0.9rem;">
            <a href="#" style="color: var(--text-secondary); text-decoration: none;">
                ¿Olvidaste tu contraseña?
            </a>
        </div>
    </div>

    <script>
        // Prevenir doble envío del formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            if (btn.classList.contains('loading')) {
                e.preventDefault();
                return;
            }
            btn.classList.add('loading');
            btn.textContent = 'Ingresando...';
        });
    </script>
</body>
</html>