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

// 3. Funciones Helper para Auditoría y Control de Caja
if (!function_exists('registrarAuditoria')) {
    function registrarAuditoria($conexion, $accion, $tabla_afectada, $registro_id, $detalles) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        if (!$usuario_id) return false;
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt = $conexion->prepare("INSERT INTO auditoria_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $usuario_id, $accion, $tabla_afectada, $registro_id, $detalles, $ip);
        return $stmt->execute();
    }
}

if (!function_exists('obtenerCajaActiva')) {
    function obtenerCajaActiva($conexion) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $usuario_id = $_SESSION['usuario_id'] ?? null;
        if (!$usuario_id) return null;
        
        $stmt = $conexion->prepare("SELECT * FROM control_caja WHERE usuario_id = ? AND estado = 'ABIERTA' ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}
?>
