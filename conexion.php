<?php
// 1. Sincronizar el reloj interno de PHP con la hora de Bolivia
date_default_timezone_set('America/La_Paz');

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

// 2. Sincronizar el reloj interno de la Base de Datos MySQL (UTC-4)
$conexion->query("SET time_zone = '-04:00'");
?>
