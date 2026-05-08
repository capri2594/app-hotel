<?php
session_start();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['SuperAdmin', 'Administrador'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo = intval($_POST['id_tipo'] ?? 0);
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $nombre = trim($_POST['nombre'] ?? '');
    $capacidad = intval($_POST['capacidad'] ?? 1);
    $precio = floatval($_POST['precio'] ?? 0);

    if ($id_tipo > 0 && !empty($codigo) && !empty($nombre) && $capacidad > 0 && $precio >= 0) {
        
        // Verificar duplicados en otros registros
        $stmt_check = $conexion->prepare("SELECT id_tipo FROM tipo_habitacion WHERE (codigo = ? OR nombre = ?) AND id_tipo != ?");
        $stmt_check->bind_param("ssi", $codigo, $nombre, $id_tipo);
        $stmt_check->execute();
        
        if ($stmt_check->get_result()->num_rows > 0) {
            header("Location: index.php?error=" . urlencode("El código o nombre ya están en uso por otra categoría."));
            exit;
        }

        try {
            $stmt = $conexion->prepare("UPDATE tipo_habitacion SET codigo = ?, nombre = ?, precio = ?, capacidad = ? WHERE id_tipo = ?");
            $stmt->bind_param("ssdii", $codigo, $nombre, $precio, $capacidad, $id_tipo);
            $stmt->execute();
            header("Location: index.php?msg=" . urlencode("Los datos y el precio fueron actualizados correctamente."));
        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode("Error al actualizar: " . $e->getMessage()));
        }
        exit;
    }
}
header("Location: index.php?error=Datos inválidos.");