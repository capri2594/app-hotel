<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reserva = intval($_POST['id'] ?? 0);
    $total_pagar = floatval($_POST['total_pagar'] ?? 0);
    $tipo_pago = $_POST['tipo_pago'] ?? 'EFECTIVO';
    $monto_recibido = floatval($_POST['monto_recibido'] ?? 0);
    
    // Calcular el cambio en backend para mayor seguridad
    $cambio = $monto_recibido - $total_pagar;
    if ($cambio < 0) {
        header("Location: index.php?error=El monto recibido no puede ser menor al total a cobrar.");
        exit;
    }

    if ($id_reserva > 0) {
        // === 1. PROCESAR SUBIDA DE IMAGEN ===
        $foto_ruta = null;
        if (isset($_FILES['foto_ci']) && $_FILES['foto_ci']['error'] === UPLOAD_ERR_OK) {
            $directorio_destino = '../uploads/ci/';
            
            // Crear el directorio si no existe
            if (!is_dir($directorio_destino)) {
                mkdir($directorio_destino, 0777, true);
            }

            // Generar nombre único para la foto
            $extension = pathinfo($_FILES['foto_ci']['name'], PATHINFO_EXTENSION);
            $nombre_foto = 'ci_reserva_' . $id_reserva . '_' . time() . '.' . $extension;
            $ruta_absoluta = $directorio_destino . $nombre_foto;

            if (move_uploaded_file($_FILES['foto_ci']['tmp_name'], $ruta_absoluta)) {
                $foto_ruta = 'uploads/ci/' . $nombre_foto; // Ruta relativa para la BD
            } else {
                header("Location: index.php?error=Error al intentar subir la fotografía del documento.");
                exit;
            }
        }

        // === 2. INICIAR TRANSACCIÓN SQL ===
        $conexion->begin_transaction();

        try {
            // A. Actualizar estado de reserva y foto
            $stmt_reserva = $conexion->prepare("UPDATE reservas SET estado = 'EN_CURSO', foto_ci = ? WHERE id = ?");
            $stmt_reserva->bind_param("si", $foto_ruta, $id_reserva);
            $stmt_reserva->execute();

            // B. Registrar el Pago Completo
            $stmt_pago = $conexion->prepare("INSERT INTO pagos (reserva_id, tipo_pago, monto, monto_recibido, cambio) VALUES (?, ?, ?, ?, ?)");
            $stmt_pago->bind_param("isddd", $id_reserva, $tipo_pago, $total_pagar, $monto_recibido, $cambio);
            $stmt_pago->execute();

            // C. Cambiar estado físico de las habitaciones en el mapa a 'OCUPADA'
            $stmt_habitacion = $conexion->prepare("UPDATE habitacion SET estado = 'OCUPADA' WHERE id_habitacion IN (SELECT habitacion_id FROM detalle_reserva WHERE reserva_id = ?)");
            $stmt_habitacion->bind_param("i", $id_reserva);
            $stmt_habitacion->execute();

            // Confirmar todo
            $conexion->commit();
            header("Location: index.php?msg=¡Check-in Consolidado! Las habitaciones ahora están OCUPADAS y el pago ha sido registrado en caja.");
        } catch (Exception $e) {
            $conexion->rollback();
            header("Location: index.php?error=Error crítico en base de datos. Se han revertido los cambios.");
        }
        exit;
    }
}
?>