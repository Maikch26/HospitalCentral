
<?php
require_once "config/conexion.php";

echo "<h1>Hospital Central de Neiva</h1>";
echo "<h2>Conexión exitosa con MySQL</h2>";

try {
    $consulta = $conexion->query("SHOW TABLES");
    $tablas = $consulta->fetchAll();

    echo "<h3>Tablas encontradas:</h3>";

    echo "<ul>";

    foreach ($tablas as $tabla) {
        echo "<li>" . htmlspecialchars(
            array_values($tabla)[0]
        ) . "</li>";
    }

    echo "</ul>";

    echo "<p>Total de tablas: " . count($tablas) . "</p>";

} catch (PDOException $error) {
    echo "Error al consultar las tablas: " .
         htmlspecialchars($error->getMessage());
}
?>
