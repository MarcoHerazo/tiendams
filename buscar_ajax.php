<?php
include 'conexion.php';

$termino = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($termino) < 2) {
    echo '<div class="no-resultados">Sigue escribiendo...</div>';
    exit;
}

$termino_seguro = $conn->real_escape_string($termino);

$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.nombre LIKE '%$termino_seguro%' 
           OR p.descripcion LIKE '%$termino_seguro%'
        LIMIT 5";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo '<div class="sugerencias-titulo">Sugerencias:</div>';
    while ($row = $result->fetch_assoc()) {
        echo '<a href="?buscar=' . urlencode($termino) . '" class="sugerencia-item">';
        echo '👟 ' . htmlspecialchars($row['nombre']);
        // 👇 PRECIO ELIMINADO
        echo '</a>';
    }
    echo '<a href="?buscar=' . urlencode($termino) . '" class="ver-todos">Ver todos los resultados →</a>';
} else {
    echo '<div class="no-resultados">No hay resultados para "' . htmlspecialchars($termino) . '"</div>';
}
?>