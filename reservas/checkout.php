<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = intval($_POST['id'] ?? 0);
    $monto_extra = floatval($_POST['monto_extra'] ?? 0);
    $tipo_pago_extra = $_POST['tipo_pago_extra'] ?? 'EFECTIVO';
    $detalle_extra = trim($_POST['detalle_extra'] ?? '');

    if ($id_reserva > 0) {
        $conexion->begin_transaction();
        try {
            // 1. Si hay un monto extra, registrarlo en la tabla de pagos
            if ($monto_extra > 0) {
                // Asegurarnos que la columna 'detalle' exista en la tabla 'pagos'
                $conexion->query("ALTER TABLE pagos ADD COLUMN IF NOT EXISTS detalle VARCHAR(255) NULL AFTER cambio");
                
                $stmt_pago_extra = $conexion->prepare("INSERT INTO pagos (reserva_id, tipo_pago, monto, detalle) VALUES (?, ?, ?, ?)");
                $stmt_pago_extra->bind_param("isds", $id_reserva, $tipo_pago_extra, $monto_extra, $detalle_extra);
                $stmt_pago_extra->execute();
            }

            // 2. Actualizar el estado de la reserva a FINALIZADA y registrar la fecha de salida
            $conexion->query("ALTER TABLE reservas ADD COLUMN IF NOT EXISTS checkout_at TIMESTAMP NULL AFTER confirmada_at");
            $stmt_reserva = $conexion->prepare("UPDATE reservas SET estado = 'FINALIZADA', checkout_at = NOW() WHERE id = ?");
            $stmt_reserva->bind_param("i", $id_reserva);
            $stmt_reserva->execute();

            // 3. Liberar las habitaciones, poniéndolas en DISPONIBLE
            $stmt_habitacion = $conexion->prepare("UPDATE habitacion SET estado = 'DISPONIBLE' WHERE id_habitacion IN (SELECT habitacion_id FROM detalle_reserva WHERE reserva_id = ?)");
            $stmt_habitacion->bind_param("i", $id_reserva);
            $stmt_habitacion->execute();

            $conexion->commit();

            // 4. Guardar el ID en sesión para imprimir el PDF
            $_SESSION['last_checkout_id'] = $id_reserva;

            header("Location: index.php?msg=" . urlencode("Check-out exitoso. Las habitaciones han sido liberadas."));

        } catch (Exception $e) {
            $conexion->rollback();
            header("Location: index.php?error=" . urlencode("Error al procesar el check-out: " . $e->getMessage()));
        }
        exit;
    }
}
header("Location: index.php?error=ID de reserva no válido.");
exit;
?>