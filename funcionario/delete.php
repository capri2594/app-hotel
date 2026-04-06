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
        // Cambiar estado a INACTIVO y registrar la fecha/hora en deleted_at
        $sql = "UPDATE funcionario SET estado = 'INACTIVO', deleted_at = CURRENT_TIMESTAMP() WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();

        header("Location: index.php?msg=El funcionario ha sido desactivado exitosamente.");
    } catch (Exception $e) {
        header("Location: index.php?error=Ocurrió un error al intentar eliminar el registro.");
    }
    exit;
}
?>