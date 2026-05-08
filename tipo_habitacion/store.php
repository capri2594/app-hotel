<?php
session_start();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['SuperAdmin', 'Administrador'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $nombre = trim($_POST['nombre'] ?? '');
    $capacidad = intval($_POST['capacidad'] ?? 1);
    $precio = floatval($_POST['precio'] ?? 0);

    if (!empty($codigo) && !empty($nombre) && $capacidad > 0 && $precio >= 0) {
        // Verificar si el código o nombre ya existen
        $stmt_check = $conexion->prepare("SELECT id_tipo FROM tipo_habitacion WHERE codigo = ? OR nombre = ?");
        $stmt_check->bind_param("ss", $codigo, $nombre);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            header("Location: index.php?error=" . urlencode("El código '$codigo' o el nombre '$nombre' ya están en uso."));
            exit;
        }

        try {
            $stmt = $conexion->prepare("INSERT INTO tipo_habitacion (codigo, nombre, precio, capacidad) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssdi", $codigo, $nombre, $precio, $capacidad);
            $stmt->execute();
            header("Location: index.php?msg=" . urlencode("Tipo de habitación creado exitosamente."));
        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode("Error al guardar: " . $e->getMessage()));
        }
        exit;
    }
}
header("Location: index.php?error=Faltan completar datos obligatorios.");