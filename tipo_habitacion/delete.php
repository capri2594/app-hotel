<?php
session_start();

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['SuperAdmin', 'Administrador'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo = intval($_POST['id_tipo'] ?? 0);

    if ($id_tipo > 0) {
        // Regla de Oro: Verificar habitaciones asignadas
        $stmt_check = $conexion->prepare("SELECT COUNT(*) as total FROM habitacion WHERE id_tipo = ?");
        $stmt_check->bind_param("i", $id_tipo);
        $stmt_check->execute();
        $total = $stmt_check->get_result()->fetch_assoc()['total'];

        if ($total > 0) {
            header("Location: index.php?error=" . urlencode("Operación denegada por seguridad. Esta categoría está asignada a $total cuarto(s)."));
            exit;
        }

        try {
            $stmt = $conexion->prepare("DELETE FROM tipo_habitacion WHERE id_tipo = ?");
            $stmt->bind_param("i", $id_tipo);
            $stmt->execute();
            header("Location: index.php?msg=" . urlencode("Tipo de habitación eliminado exitosamente."));
        } catch (Exception $e) {
            header("Location: index.php?error=" . urlencode("Error al eliminar: " . $e->getMessage()));
        }
        exit;
    }
}
header("Location: index.php");