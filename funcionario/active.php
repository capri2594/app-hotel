<?php
session_start();

// Validar seguridad
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['SuperAdmin', 'Administrador'])) {
    header("Location: ../dashboard.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    if ($id === 0) {
        header("Location: index.php?error=El ID del funcionario no es válido.");
        exit;
    }

    try {
        // Cambiar estado a ACTIVO y limpiar la fecha de eliminación (deleted_at)
        $sql = "UPDATE funcionario SET estado = 'ACTIVO', deleted_at = NULL WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        header("Location: index.php?msg=El funcionario ha sido reactivado exitosamente.");
    } catch (Exception $e) {
        header("Location: index.php?error=Ocurrió un error al intentar reactivar el registro.");
    }
    exit;
}
?>