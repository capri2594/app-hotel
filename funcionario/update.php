<?php
session_start();

// Validar seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['SuperAdmin', 'Administrador'])) {
    header("Location: ../dashboard.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Limpiar y recuperar datos
    $id = intval($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $paterno = trim($_POST['paterno'] ?? '');
    $materno = trim($_POST['materno'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $rol_id = intval($_POST['rol_id'] ?? 0);

    // Validaciones básicas
    if ($id === 0 || empty($nombre) || empty($paterno) || empty($ci) || !$rol_id) {
        header("Location: index.php?error=Faltan completar datos obligatorios.");
        exit;
    }

    try {
        $sql = "UPDATE funcionario SET nombre = ?, paterno = ?, materno = ?, ci = ?, telefono = ?, rol_id = ? WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("sssssii", $nombre, $paterno, $materno, $ci, $telefono, $rol_id, $id);
        $stmt->execute();

        header("Location: index.php?msg=Funcionario actualizado correctamente.");
    } catch (Exception $e) {
        header("Location: index.php?error=Ocurrió un error al actualizar. Asegúrese de que el CI no esté duplicado.");
    }
    exit;
}
?>