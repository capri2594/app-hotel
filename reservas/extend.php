<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

$caja_activa = obtenerCajaActiva($conexion);
if (!$caja_activa) {
    header("Location: index.php?error=" . urlencode("Debe abrir un turno de caja para realizar operaciones."));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = intval($_POST['id'] ?? 0);
    $nueva_salida = $_POST['nueva_fecha_salida'];
    $monto_cobrar = floatval($_POST['monto_cobrar']);
    $monto_recibido = floatval($_POST['monto_recibido']);
    $tipo_pago = $_POST['tipo_pago'];
    $cambio = $monto_recibido - $monto_cobrar;

    if ($cambio < 0) {
        header("Location: index.php?error=El pago recibido es insuficiente.");
        exit;
    }

    if ($id_reserva > 0) {
        $conexion->begin_transaction();
        try {
            // 1. Obtener la fecha de salida actual
            $stmt_r = $conexion->prepare("SELECT fecha_salida FROM reservas WHERE id = ?");
            $stmt_r->bind_param("i", $id_reserva);
            $stmt_r->execute();
            $vieja_salida = $stmt_r->get_result()->fetch_assoc()['fecha_salida'];

            // 2. Verificar que las habitaciones no estén comprometidas para el periodo de extensión
            $sql_check = "
                SELECT r.id FROM reservas r
                INNER JOIN detalle_reserva dr ON r.id = dr.reserva_id
                WHERE r.id != ? 
                AND r.estado IN ('SOLICITADA', 'RESERVADA', 'OCUPADA')
                AND dr.habitacion_id IN (SELECT habitacion_id FROM detalle_reserva WHERE reserva_id = ?)
                AND (r.fecha_ingreso < ? AND r.fecha_salida > ?)
            ";
            $stmt_check = $conexion->prepare($sql_check);
            $stmt_check->bind_param("iiss", $id_reserva, $id_reserva, $nueva_salida, $vieja_salida);
            $stmt_check->execute();
            
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception("Operación denegada. Una o más habitaciones ya fueron reservadas por otro cliente para esas fechas. Debe procesar el Check-out y registrarlo en una habitación diferente.");
            }

            // 2.5 Obtener números de habitaciones para el detalle del pago
            $stmt_habs = $conexion->prepare("SELECT GROUP_CONCAT(h.numero SEPARATOR ', ') as numeros FROM detalle_reserva dr JOIN habitacion h ON dr.habitacion_id = h.id_habitacion WHERE dr.reserva_id = ?");
            $stmt_habs->bind_param("i", $id_reserva);
            $stmt_habs->execute();
            $numeros_habitaciones = $stmt_habs->get_result()->fetch_assoc()['numeros'] ?? '';

            // 3. Todo en orden -> Extender fechas, sumar el costo y registrar el pago
            $detalle = "Extensión de Estadía hasta " . date('d/m/Y', strtotime($nueva_salida)) . " - Hab: " . $numeros_habitaciones;
            
            $stmt_update_res = $conexion->prepare("UPDATE reservas SET fecha_salida = ?, total = total + ? WHERE id = ?");
            $stmt_update_res->bind_param("sdi", $nueva_salida, $monto_cobrar, $id_reserva);
            $stmt_update_res->execute();

            $caja_id = $caja_activa['id'];
            $stmt_pago = $conexion->prepare("INSERT INTO pagos (reserva_id, caja_id, tipo_pago, monto, monto_recibido, cambio, detalle) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_pago->bind_param("iisddds", $id_reserva, $caja_id, $tipo_pago, $monto_cobrar, $monto_recibido, $cambio, $detalle);
            $stmt_pago->execute();

            registrarAuditoria($conexion, 'EXTENDER_ESTADIA', 'reservas', $id_reserva, "Se extendió la estadía hasta " . date('d/m/Y', strtotime($nueva_salida)) . ". Monto cobrado: " . $monto_cobrar . " Bs.");

            $conexion->commit();
            header("Location: index.php?msg=" . urlencode("Estadía extendida exitosamente hasta el " . date('d/m/Y', strtotime($nueva_salida)) . "."));
        } catch (Exception $e) {
            $conexion->rollback();
            header("Location: index.php?error=" . urlencode($e->getMessage()));
        }
        exit;
    }
}