<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_habitacion = intval($_POST['id_habitacion'] ?? 0);
    $numero = intval($_POST['numero'] ?? 0);
    $piso = intval($_POST['piso'] ?? 0);
    $id_tipo = intval($_POST['id_tipo'] ?? 0);

    if ($id_habitacion > 0 && $numero > 0 && $id_tipo > 0 && $piso > 0) {
        
        // Verificar si el número de habitación ya está asignado a OTRA habitación distinta
        $stmt_check = $conexion->prepare("SELECT id_habitacion FROM habitacion WHERE numero = ? AND id_habitacion != ?");
        $stmt_check->bind_param("ii", $numero, $id_habitacion);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            header("Location: index.php?error=" . urlencode("El número de habitación $numero ya está en uso. Elige otro."));
            exit;
        }

        try {
            // Actualizar la habitación
            $stmt = $conexion->prepare("UPDATE habitacion SET numero = ?, piso = ?, id_tipo = ? WHERE id_habitacion = ?");
            $stmt->bind_param("iiii", $numero, $piso, $id_tipo, $id_habitacion);
            $stmt->execute();

            header("Location: index.php?msg=" . urlencode("Habitación actualizada exitosamente."));
        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode("Error al actualizar: " . $e->getMessage()));
        }
        exit;
    }
}
header("Location: index.php");