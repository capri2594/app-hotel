<?php
session_start();
include '../conexion.php';

// Detectar si es un Funcionario o un Cliente Web para redirigir correctamente
$is_admin = isset($_SESSION['usuario_id']);
$origen = $_POST['origen'] ?? '';

if ($origen === 'mapa') {
    $redirect_url = "index.php"; // Si viene del Mapa de Recepción, va a Gestión de Reservas
} else {
    $redirect_url = "../cliente/index.php"; // Si viene del formulario web, vuelve a la misma web
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = trim($_POST['nombre'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    if (isset($_POST['codigo_pais'])) {
        $telefono = trim($_POST['codigo_pais']) . ' ' . trim($_POST['telefono'] ?? '');
    } else {
        $telefono = trim($_POST['telefono'] ?? '');
    }
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
    $fecha_salida = $_POST['fecha_salida'] ?? '';
    $habitacion_id = intval($_POST['habitacion_id'] ?? 0);
    $cantidades = $_POST['cantidades'] ?? []; // Array con las cantidades pedidas
    $desayuno = isset($_POST['desayuno']) ? 1 : 0;
    $garage = intval($_POST['garage'] ?? 0);

    // Validar longitud del documento de identidad (Previene spam de 1 o 2 letras)
    if (strlen($ci) < 5) {
        header("Location: $redirect_url?error=" . urlencode("El documento de identidad o pasaporte debe tener al menos 5 caracteres."));
        exit;
    }

    // Validar que la fecha de ingreso no sea en el pasado (permite hoy)
    $hoy = date('Y-m-d');
    if (strtotime($fecha_ingreso) < strtotime($hoy)) {
        header("Location: $redirect_url?error=" . urlencode("La fecha de ingreso no puede estar en el pasado."));
        exit;
    }

    // Validar que la fecha de salida sea mayor a la de ingreso
    if (strtotime($fecha_salida) <= strtotime($fecha_ingreso)) {
        header("Location: $redirect_url?error=" . urlencode("La fecha de salida debe ser posterior a la fecha de llegada."));
        exit;
    }

    // CONTROL DE SPAM: Solo para clientes web (El recepcionista sí puede registrar múltiples reservas al mismo CI)
    if ($origen !== 'mapa') {
        $sql_check_ci = "SELECT id FROM reservas WHERE ci = ? AND estado IN ('SOLICITADA', 'RESERVADA')";
        $stmt_check_ci = $conexion->prepare($sql_check_ci);
        $stmt_check_ci->bind_param("s", $ci);
        $stmt_check_ci->execute();
        if ($stmt_check_ci->get_result()->num_rows > 0) {
            header("Location: $redirect_url?error=" . urlencode("Ya tienes una reserva en curso con este número de Carnet (CI). Si necesitas múltiples habitaciones, por favor comunícate directamente con recepción."));
            exit;
        }
    }

    // INICIAR TRANSACCIÓN: Si no hay disponibilidad de alguna, se cancela todo
    $conexion->begin_transaction();

    try {
        $habitaciones_a_reservar = [];
        $total_reserva = 0;
        $capacidad_total = 0;
        
        // Calcular cantidad de noches usando DateTime (Evita bugs por husos horarios)
        $fecha1 = new DateTime($fecha_ingreso);
        $fecha2 = new DateTime($fecha_salida);
        $diferencia = $fecha1->diff($fecha2);
        $noches = max(1, $diferencia->days);

        // MODO 1: Reserva de una habitación ESPECÍFICA (Desde el Mapa)
        if ($habitacion_id > 0) {
                $sql_buscar = "
                    SELECT h.id_habitacion, t.precio, t.capacidad FROM habitacion h
                INNER JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo
                WHERE h.id_habitacion = ? AND h.estado != 'MANTENIMIENTO'
                AND h.id_habitacion NOT IN (
                    SELECT dr.habitacion_id FROM detalle_reserva dr
                    INNER JOIN reservas r ON dr.reserva_id = r.id
                    WHERE r.estado IN ('SOLICITADA', 'RESERVADA', 'OCUPADA') 
                    AND (r.fecha_ingreso < ? AND r.fecha_salida > ?)
                )
            ";
            $stmt_buscar = $conexion->prepare($sql_buscar);
            $stmt_buscar->bind_param("iss", $habitacion_id, $fecha_salida, $fecha_ingreso);
            $stmt_buscar->execute();
            $res_buscar = $stmt_buscar->get_result();

            if ($res_buscar->num_rows === 0) {
                throw new Exception("La habitación seleccionada no está disponible para las fechas indicadas.");
            }

            $row = $res_buscar->fetch_assoc();
            $habitaciones_a_reservar[] = $row['id_habitacion'];
            $total_reserva = $row['precio'] * $noches;
            $capacidad_total = $row['capacidad'];
            $total_pedidas = 1;

        } 
        // MODO 2: Asignación AUTOMÁTICA por tipos (Desde la Web)
        else {
            $total_pedidas = array_sum($cantidades);
            if ($total_pedidas <= 0) {
                throw new Exception("Debes seleccionar al menos una habitación en el paso 2.");
            }

            foreach ($cantidades as $id_tipo => $cantidad) {
                $cantidad = intval($cantidad);
                if ($cantidad > 0) {
                    $sql_buscar = "
                        SELECT id_habitacion FROM habitacion 
                        WHERE id_tipo = ? AND estado != 'MANTENIMIENTO'
                        AND id_habitacion NOT IN (
                            SELECT dr.habitacion_id FROM detalle_reserva dr
                            INNER JOIN reservas r ON dr.reserva_id = r.id
                            WHERE r.estado IN ('SOLICITADA', 'RESERVADA', 'OCUPADA') 
                            AND (r.fecha_ingreso < ? AND r.fecha_salida > ?)
                        )
                        LIMIT ?
                    ";
                    $stmt_buscar = $conexion->prepare($sql_buscar);
                    $stmt_buscar->bind_param("issi", $id_tipo, $fecha_salida, $fecha_ingreso, $cantidad);
                    $stmt_buscar->execute();
                    $res_buscar = $stmt_buscar->get_result();

                    if ($res_buscar->num_rows < $cantidad) {
                        $msg_extra = "Lo sentimos, no hay suficientes habitaciones disponibles de los tipos solicitados para esas fechas.";
                        
                        if ($origen !== 'mapa') { // Solo mostrar la hora de expiración al cliente web
                            $sql_prox = "SELECT MIN(IF(estado = 'RESERVADA', DATE_ADD(confirmada_at, INTERVAL 12 HOUR), DATE_ADD(created_at, INTERVAL 12 HOUR))) as hora_liberacion FROM reservas WHERE estado IN ('SOLICITADA', 'RESERVADA')";
                            $res_prox = $conexion->query($sql_prox);
                            if ($res_prox && $res_prox->num_rows > 0) {
                                $row_prox = $res_prox->fetch_assoc();
                                if (!empty($row_prox['hora_liberacion'])) {
                                    $hora = date('H:i', strtotime($row_prox['hora_liberacion']));
                                    $msg_extra = "Lo sentimos, estamos al 100% de capacidad. Sin embargo, algunas reservas pendientes de pago podrían expirar hoy a las " . $hora . ". Te invitamos a reintentar tu reserva después de esa hora.";
                                }
                            }
                        }
                        throw new Exception($msg_extra);
                    }
                    
                    // Obtener el precio de este tipo de habitación
                    $stmt_precio = $conexion->prepare("SELECT precio, capacidad FROM tipo_habitacion WHERE id_tipo = ?");
                    $stmt_precio->bind_param("i", $id_tipo);
                    $stmt_precio->execute();
                    $row_precio = $stmt_precio->get_result()->fetch_assoc();
                    $precio = $row_precio['precio'];
                    $capacidad = $row_precio['capacidad'];
                    $total_reserva += ($precio * $cantidad * $noches);
                    $capacidad_total += ($capacidad * $cantidad);

                    while ($row = $res_buscar->fetch_assoc()) {
                        $habitaciones_a_reservar[] = $row['id_habitacion'];
                    }
                }
            }
        }

        // Sumar los servicios adicionales al total de la reserva
        $total_reserva += (($desayuno ? 30 * $capacidad_total : 0) + ($garage * 20)) * $noches;

        // 2. Guardar CABECERA (Reserva)
        $sql_reserva = "INSERT INTO reservas (nombre, ci, telefono, fecha_ingreso, fecha_salida, estado, desayuno, garage, total) VALUES (?, ?, ?, ?, ?, 'SOLICITADA', ?, ?, ?)";
        $stmt_reserva = $conexion->prepare($sql_reserva);
        $stmt_reserva->bind_param("sssssiid", $nombre, $ci, $telefono, $fecha_ingreso, $fecha_salida, $desayuno, $garage, $total_reserva);
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
        
        if ($origen === 'mapa') {
            $msg_exito = "Reserva registrada exitosamente. Ya puedes gestionarla en la tabla.";
        } else {
            $msg_exito = "¡Reserva procesada! Hemos asignado " . $total_pedidas . " habitación(es) a tu nombre. Te esperamos en recepción.";
        }
        
        header("Location: $redirect_url?msg=" . urlencode($msg_exito));
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: $redirect_url?error=" . urlencode($e->getMessage()));
    }
    exit;
}
?>