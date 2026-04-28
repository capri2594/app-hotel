<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = intval($_POST['id'] ?? 0);

    if ($id_reserva > 0) {
        
        // Iniciar transacción para asegurar que ambos cambios ocurran sí o sí
        $conexion->begin_transaction();

        try {
            // 1. Cambiar la reserva a CONFIRMADA
            $stmt_reserva = $conexion->prepare("UPDATE reservas SET estado = 'CONFIRMADA' WHERE id = ?");
            $stmt_reserva->bind_param("i", $id_reserva);
            $stmt_reserva->execute();

            // 2. Bloquear TODAS las habitaciones vinculadas pasándolas a RESERVADA
            $stmt_habitacion = $conexion->prepare("UPDATE habitacion SET estado = 'RESERVADA' WHERE id_habitacion IN (SELECT habitacion_id FROM detalle_reserva WHERE reserva_id = ?)");
            $stmt_habitacion->bind_param("i", $id_reserva);
            $stmt_habitacion->execute();

            $conexion->commit();
            header("Location: index.php?msg=La reserva web fue aprobada. Las habitaciones ya figuran como RESERVADA en el mapa.");
        } catch (Exception $e) {
            $conexion->rollback();
            header("Location: index.php?error=Ocurrió un error al procesar la confirmación.");
        }
        exit;
    }
}
?>