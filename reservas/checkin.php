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
    $desayuno = isset($_POST['desayuno']) ? 1 : 0;
    $garage = intval($_POST['garage'] ?? 0);
    
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
            // Calcular correlativo de voucher de forma segura
            $res_v = $conexion->query("SELECT COALESCE(MAX(nro_voucher), 0) + 1 AS next_voucher FROM reservas FOR UPDATE");
            $v_row = $res_v->fetch_assoc();
            $nro_voucher = intval($v_row['next_voucher']);

            // A. Actualizar estado de reserva, foto, checkin_at y nro_voucher
            $stmt_reserva = $conexion->prepare("UPDATE reservas SET estado = 'OCUPADA', foto_ci = ?, desayuno = ?, garage = ?, total = ?, checkin_at = NOW(), nro_voucher = ? WHERE id = ?");
            $stmt_reserva->bind_param("siidii", $foto_ruta, $desayuno, $garage, $total_pagar, $nro_voucher, $id_reserva);
            $stmt_reserva->execute();

            // B. Registrar el Pago Completo con Detalle Automático
            $stmt_habs = $conexion->prepare("SELECT GROUP_CONCAT(h.numero SEPARATOR ', ') as numeros FROM detalle_reserva dr JOIN habitacion h ON dr.habitacion_id = h.id_habitacion WHERE dr.reserva_id = ?");
            $stmt_habs->bind_param("i", $id_reserva);
            $stmt_habs->execute();
            $habs_result = $stmt_habs->get_result()->fetch_assoc();
            $detalle_pago = "Pago de Estadía - Hab: " . ($habs_result['numeros'] ?? 'S/A') . " (Voucher #" . $nro_voucher . ")";
            
            $stmt_pago = $conexion->prepare("INSERT INTO pagos (reserva_id, tipo_pago, monto, monto_recibido, cambio, detalle) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_pago->bind_param("isddds", $id_reserva, $tipo_pago, $total_pagar, $monto_recibido, $cambio, $detalle_pago);
            $stmt_pago->execute();

            // C. Registrar Huéspedes (Titular y Acompañantes) en la tabla huesped
            $titular_nombre = trim($_POST['titular_nombre'] ?? '');
            $titular_documento = trim($_POST['titular_documento'] ?? '');
            $titular_procedencia = trim($_POST['titular_procedencia'] ?? '');
            $titular_nacionalidad = trim($_POST['titular_nacionalidad'] ?? 'Boliviana');
            $titular_profesion = trim($_POST['titular_profesion'] ?? '');
            $titular_edad = intval($_POST['titular_edad'] ?? 0);
            $titular_estado_civil = trim($_POST['titular_estado_civil'] ?? '');
            $titular_habitacion = intval($_POST['titular_habitacion'] ?? 0);

            if ($titular_edad < 18) {
                $titular_edad = 18;
            }

            $es_principal = 1;
            $stmt_huesped = $conexion->prepare("INSERT INTO huesped (reserva_id, habitacion_id, nombre_completo, documento, procedencia, nacionalidad, profesion, edad, estado_civil, es_principal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_huesped->bind_param("iisssssisi", $id_reserva, $titular_habitacion, $titular_nombre, $titular_documento, $titular_procedencia, $titular_nacionalidad, $titular_profesion, $titular_edad, $titular_estado_civil, $es_principal);
            $stmt_huesped->execute();

            if (isset($_POST['acomp_nombre']) && is_array($_POST['acomp_nombre'])) {
                $acomp_nombres = $_POST['acomp_nombre'];
                $acomp_documentos = $_POST['acomp_documento'] ?? [];
                $acomp_procedencias = $_POST['acomp_procedencia'] ?? [];
                $acomp_nacionalidades = $_POST['acomp_nacionalidad'] ?? [];
                $acomp_profesiones = $_POST['acomp_profesion'] ?? [];
                $acomp_edades = $_POST['acomp_edad'] ?? [];
                $acomp_estados_civiles = $_POST['acomp_estado_civil'] ?? [];
                $acomp_habitaciones = $_POST['acomp_habitacion'] ?? [];
                
                $es_principal_acomp = 0;
                
                for ($i = 0; $i < count($acomp_nombres); $i++) {
                    $a_nombre = trim($acomp_nombres[$i]);
                    if (empty($a_nombre)) continue;
                    
                    $a_doc = trim($acomp_documentos[$i] ?? '');
                    $a_proc = trim($acomp_procedencias[$i] ?? '');
                    $a_nac = trim($acomp_nacionalidades[$i] ?? 'Boliviana');
                    $a_prof = trim($acomp_profesiones[$i] ?? '');
                    $a_edad = intval($acomp_edades[$i] ?? 0);
                    $a_civil = trim($acomp_estados_civiles[$i] ?? '');
                    $a_hab = intval($acomp_habitaciones[$i] ?? $titular_habitacion);
                    
                    if ($a_edad < 18) {
                        $a_prof = null;
                        $a_civil = null;
                    }
                    
                    $stmt_huesped->bind_param("iisssssisi", $id_reserva, $a_hab, $a_nombre, $a_doc, $a_proc, $a_nac, $a_prof, $a_edad, $a_civil, $es_principal_acomp);
                    $stmt_huesped->execute();
                }
            }

            // D. Cambiar estado físico de las habitaciones en el mapa a 'OCUPADA'
            $stmt_habitacion = $conexion->prepare("UPDATE habitacion SET estado = 'OCUPADA' WHERE id_habitacion IN (SELECT habitacion_id FROM detalle_reserva WHERE reserva_id = ?)");
            $stmt_habitacion->bind_param("i", $id_reserva);
            $stmt_habitacion->execute();

            // Confirmar todo
            $conexion->commit();
            header("Location: index.php?msg=" . urlencode("¡Check-in Consolidado! Las habitaciones ahora están OCUPADAS y el pago ha sido registrado en caja. Voucher #" . $nro_voucher));
        } catch (Exception $e) {
            $conexion->rollback();
            header("Location: index.php?error=" . urlencode("Error crítico en base de datos: " . $e->getMessage() . ". Se han revertido los cambios."));
        }
        exit;
    }
}
?>