<?php
// Datos de conexión
$host = "localhost";
$usuario = "root";
$contrasena = "";
$base_datos = "habitapp";

// Crear conexión
$conexion = new mysqli($host, $usuario, $contrasena, $base_datos);

// Verificar conexión
if ($conexion->connect_error) {
    die("❌ Error al conectar: " . $conexion->connect_error);
}
?>
