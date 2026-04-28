<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_habitacion = intval($_POST['id_habitacion'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($id_habitacion > 0) {
        $nuevo_estado = ($accion === 'mantenimiento') ? 'MANTENIMIENTO' : 'DISPONIBLE';

        // Capa de Seguridad: Verificar que no intenten alterar una habitación Ocupada o Reservada
        $stmt_check = $conexion->prepare("SELECT estado FROM habitacion WHERE id_habitacion = ?");
        $stmt_check->bind_param("i", $id_habitacion);
        $stmt_check->execute();
        $estado_actual = $stmt_check->get_result()->fetch_assoc()['estado'];

        if ($estado_actual === 'OCUPADA' || $estado_actual === 'RESERVADA') {
            header("Location: index.php?error=No puedes modificar el estado de una habitación que está actualmente Ocupada o Reservada.");
            exit;
        }

        try {
            $stmt = $conexion->prepare("UPDATE habitacion SET estado = ? WHERE id_habitacion = ?");
            $stmt->bind_param("si", $nuevo_estado, $id_habitacion);
            $stmt->execute();
            
            $msg = ($nuevo_estado === 'MANTENIMIENTO') ? "La habitación ha sido puesta en Mantenimiento." : "La habitación ha sido habilitada y ahora está Disponible.";
            header("Location: index.php?msg=" . urlencode($msg));
        } catch (Exception $e) {
            header("Location: index.php?error=Ocurrió un error al actualizar la base de datos.");
        }
        exit;
    }
}
header("Location: index.php");
?>