<?php
include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = trim($_POST['nombre'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
    $fecha_salida = $_POST['fecha_salida'] ?? '';
    $cantidades = $_POST['cantidades'] ?? []; // Array con las cantidades pedidas

    // Validar que la fecha de salida sea mayor a la de ingreso
    if (strtotime($fecha_salida) <= strtotime($fecha_ingreso)) {
        header("Location: index.php?error=La fecha de salida debe ser posterior a la fecha de llegada.");
        exit;
    }

    $total_pedidas = array_sum($cantidades);
    if ($total_pedidas <= 0) {
        header("Location: index.php?error=Debes seleccionar al menos una habitación en el paso 2.");
        exit;
    }

    // CONTROL DE SPAM: Evitar que una misma persona acapare reservas
    $sql_check_ci = "SELECT id FROM reservas WHERE ci = ? AND estado IN ('PENDIENTE', 'CONFIRMADA')";
    $stmt_check_ci = $conexion->prepare($sql_check_ci);
    $stmt_check_ci->bind_param("s", $ci);
    $stmt_check_ci->execute();
    if ($stmt_check_ci->get_result()->num_rows > 0) {
        header("Location: index.php?error=Ya tienes una reserva en curso con este número de Carnet (CI). Si necesitas múltiples habitaciones, por favor comunícate directamente con recepción.");
        exit;
    }

    // INICIAR TRANSACCIÓN: Si no hay disponibilidad de alguna, se cancela todo
    $conexion->begin_transaction();

    try {
        $habitaciones_a_reservar = [];
        $total_reserva = 0;
        
        // Calcular cantidad de noches (Mínimo 1 noche)
        $noches = max(1, round((strtotime($fecha_salida) - strtotime($fecha_ingreso)) / 86400));

        // 1. Verificar disponibilidad por cada tipo de habitación pedida
        foreach ($cantidades as $id_tipo => $cantidad) {
            $cantidad = intval($cantidad);
            if ($cantidad > 0) {
                $sql_buscar = "
                    SELECT id_habitacion FROM habitacion 
                    WHERE id_tipo = ? AND estado != 'MANTENIMIENTO'
                    AND id_habitacion NOT IN (
                        SELECT dr.habitacion_id FROM detalle_reserva dr
                        INNER JOIN reservas r ON dr.reserva_id = r.id
                        WHERE r.estado IN ('PENDIENTE', 'CONFIRMADA') 
                        AND (r.fecha_ingreso < ? AND r.fecha_salida > ?)
                    )
                    LIMIT ?
                ";
                $stmt_buscar = $conexion->prepare($sql_buscar);
                $stmt_buscar->bind_param("issi", $id_tipo, $fecha_salida, $fecha_ingreso, $cantidad);
                $stmt_buscar->execute();
                $res_buscar = $stmt_buscar->get_result();

                if ($res_buscar->num_rows < $cantidad) {
                    throw new Exception("Lo sentimos, no hay suficientes habitaciones disponibles de los tipos solicitados para esas fechas.");
                }
                
                // Obtener el precio de este tipo de habitación
                $stmt_precio = $conexion->prepare("SELECT precio FROM tipo_habitacion WHERE id_tipo = ?");
                $stmt_precio->bind_param("i", $id_tipo);
                $stmt_precio->execute();
                $precio = $stmt_precio->get_result()->fetch_assoc()['precio'];
                $total_reserva += ($precio * $cantidad * $noches);

                while ($row = $res_buscar->fetch_assoc()) {
                    $habitaciones_a_reservar[] = $row['id_habitacion'];
                }
            }
        }

        // 2. Guardar CABECERA (Reserva)
        $sql_reserva = "INSERT INTO reservas (nombre, ci, telefono, fecha_ingreso, fecha_salida, estado, total) VALUES (?, ?, ?, ?, ?, 'PENDIENTE', ?)";
        $stmt_reserva = $conexion->prepare($sql_reserva);
        $stmt_reserva->bind_param("sssssd", $nombre, $ci, $telefono, $fecha_ingreso, $fecha_salida, $total_reserva);
        $stmt_reserva->execute();
        $reserva_id = $conexion->insert_id;

        // 3. Guardar DETALLE (Vincular habitaciones)
        $sql_detalle = "INSERT INTO detalle_reserva (reserva_id, habitacion_id) VALUES (?, ?)";
        $stmt_detalle = $conexion->prepare($sql_detalle);
        foreach ($habitaciones_a_reservar as $hab_id) {
            $stmt_detalle->bind_param("ii", $reserva_id, $hab_id);
            $stmt_detalle->execute();
        }

        $conexion->commit();
        header("Location: index.php?msg=¡Reserva procesada! Hemos asignado " . $total_pedidas . " habitación(es) a tu nombre. Te esperamos en recepción.");
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: index.php?error=" . urlencode($e->getMessage()));
    }
    exit;
}
?>