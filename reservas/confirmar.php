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
            
            // === PREPARAR MENSAJE DE WHATSAPP ===
            $stmt_info = $conexion->prepare("SELECT nombre, telefono FROM reservas WHERE id = ?");
            $stmt_info->bind_param("i", $id_reserva);
            $stmt_info->execute();
            $info = $stmt_info->get_result()->fetch_assoc();
            
            $wa_url = "";
            if ($info && !empty($info['telefono'])) {
                $tel_limpio = preg_replace('/[^0-9]/', '', $info['telefono']); // Extraer solo números
                $mensaje = "🏨 *HabitApp - Confirmación de Pre-Reserva*\n\nHola *" . trim($info['nombre']) . "*,\n\nTu solicitud de reserva *Nº " . $id_reserva . "* ha sido *APROBADA* ✅.\n\nTe recordamos que esta es una Pre-Reserva válida por 12 horas. El pago y registro final se realizarán en Recepción al momento de tu llegada.\n\n¡Te esperamos!";
                $wa_url = "&wa=" . urlencode("https://api.whatsapp.com/send?phone=" . $tel_limpio . "&text=" . urlencode($mensaje));
            }

            header("Location: index.php?msg=" . urlencode("La reserva web fue aprobada. Las habitaciones ya figuran como RESERVADA en el mapa.") . $wa_url);
        } catch (Exception $e) {
            $conexion->rollback();
            header("Location: index.php?error=Ocurrió un error al procesar la confirmación.");
        }
        exit;
    }
}
?>