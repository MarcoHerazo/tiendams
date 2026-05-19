<?php
$servername = "localhost";
$username = "root";           // ← Esto lo cambiarás en el hosting
$password = "";               // ← Esto lo cambiarás en el hosting
$dbname = "tienda_ms";        // ← Esto lo cambiarás en el hosting

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>