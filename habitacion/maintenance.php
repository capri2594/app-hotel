<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id_habitacion = intval($_POST['id_habitacion'] ?? 0);
    $usuario_id = $_SESSION['usuario_id'];

    if ($id_habitacion > 0) {
        
        // ==========================================
        // ESCENARIO 1: PONER EN MANTENIMIENTO
        // ==========================================
        if ($accion === 'start') {
            $motivo = trim($_POST['motivo'] ?? '');
            $conexion->begin_transaction();
            try {
                $conexion->query("UPDATE habitacion SET estado = 'MANTENIMIENTO' WHERE id_habitacion = $id_habitacion");
                
                $stmt = $conexion->prepare("INSERT INTO historial_mantenimiento (habitacion_id, usuario_id, motivo) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $id_habitacion, $usuario_id, $motivo);
                $stmt->execute();
                
                $conexion->commit();
                header("Location: index.php?msg=" . urlencode("La habitación ha sido bloqueada por mantenimiento."));
            } catch (Exception $e) {
                $conexion->rollback();
                header("Location: index.php?error=" . urlencode("Error: " . $e->getMessage()));
            }
            exit;

        // ==========================================
        // ESCENARIO 2: HABILITAR HABITACIÓN
        // ==========================================
        } elseif ($accion === 'end') {
            $detalle_resolucion = trim($_POST['detalle_resolucion'] ?? '');
            $conexion->begin_transaction();
            try {
                $conexion->query("UPDATE habitacion SET estado = 'DISPONIBLE' WHERE id_habitacion = $id_habitacion");
                
                $stmt = $conexion->prepare("UPDATE historial_mantenimiento SET estado = 'FINALIZADO', fecha_fin = NOW(), detalle_resolucion = ? WHERE habitacion_id = ? AND estado = 'EN_PROCESO' ORDER BY id DESC LIMIT 1");
                $stmt->bind_param("si", $detalle_resolucion, $id_habitacion);
                $stmt->execute();
                
                $conexion->commit();
                header("Location: index.php?msg=" . urlencode("La habitación está disponible para su uso."));
            } catch (Exception $e) {
                $conexion->rollback();
                header("Location: index.php?error=" . urlencode("Error: " . $e->getMessage()));
            }
            exit;
        }
    }
}