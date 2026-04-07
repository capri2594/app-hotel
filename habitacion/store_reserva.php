<?php
include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $nombre = trim($_POST['nombre'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $habitacion_id = intval($_POST['habitacion_id'] ?? 0);
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
    $fecha_salida = $_POST['fecha_salida'] ?? '';

    // Validar que la fecha de salida sea mayor a la de ingreso
    if (strtotime($fecha_salida) <= strtotime($fecha_ingreso)) {
        header("Location: index.php?error=La fecha de salida debe ser posterior a la fecha de llegada.");
        exit;
    }

    if ($habitacion_id === 0) {
        header("Location: index.php?error=No se seleccionó una habitación válida.");
        exit;
    }

    $conexion->begin_transaction();

    try {
        // Calcular noches y total
        $noches = max(1, round((strtotime($fecha_salida) - strtotime($fecha_ingreso)) / 86400));
        $stmt_precio = $conexion->prepare("SELECT t.precio FROM habitacion h INNER JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo WHERE h.id_habitacion = ?");
        $stmt_precio->bind_param("i", $habitacion_id);
        $stmt_precio->execute();
        $precio = $stmt_precio->get_result()->fetch_assoc()['precio'];
        $total_reserva = $precio * $noches;

        // 1. Guardar CABECERA
        $sql_reserva = "INSERT INTO reservas (nombre, ci, telefono, fecha_ingreso, fecha_salida, estado, total) VALUES (?, ?, ?, ?, ?, 'PENDIENTE', ?)";
        $stmt_reserva = $conexion->prepare($sql_reserva);
        $stmt_reserva->bind_param("sssssd", $nombre, $ci, $telefono, $fecha_ingreso, $fecha_salida, $total_reserva);
        $stmt_reserva->execute();
        $reserva_id = $conexion->insert_id;

        // 2. Guardar DETALLE
        $sql_detalle = "INSERT INTO detalle_reserva (reserva_id, habitacion_id) VALUES (?, ?)";
        $stmt_detalle = $conexion->prepare($sql_detalle);
        $stmt_detalle->bind_param("ii", $reserva_id, $habitacion_id);
        $stmt_detalle->execute();
        
        $conexion->commit();
        header("Location: index.php?msg=Reserva registrada exitosamente en la habitación.");
    } catch (Exception $e) {
        $conexion->rollback();
        header("Location: index.php?error=Ocurrió un error al procesar la reserva.");
    }
    exit;
}
?>