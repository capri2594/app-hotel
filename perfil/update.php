<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$action = $_POST['action'] ?? '';

if ($action === 'update_user') {
    $nuevo_usuario = trim($_POST['usuario'] ?? '');
    if (empty($nuevo_usuario)) {
        header("Location: ../dashboard.php?error=El nombre de usuario no puede estar vacío.");
        exit;
    }

    // Verificar si el nombre de usuario ya está en uso por OTRA persona
    $sql_check = "SELECT id FROM usuario WHERE usuario = ? AND id != ?";
    $stmt_check = $conexion->prepare($sql_check);
    $stmt_check->bind_param("si", $nuevo_usuario, $usuario_id);
    $stmt_check->execute();

    if ($stmt_check->get_result()->num_rows > 0) {
        header("Location: ../dashboard.php?error=El nombre de usuario ya está siendo usado por otra cuenta.");
        exit;
    }

    $sql = "UPDATE usuario SET usuario = ? WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $nuevo_usuario, $usuario_id);
    
    if ($stmt->execute()) {
        header("Location: ../dashboard.php?msg=Nombre de usuario actualizado correctamente.");
    } else {
        header("Location: ../dashboard.php?error=Ocurrió un error al actualizar el usuario.");
    }
} elseif ($action === 'update_password') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || $password !== $confirm_password) {
        header("Location: ../dashboard.php?error=Las contraseñas no coinciden o están vacías.");
        exit;
    }
    
    $password_md5 = md5($password);
    $sql = "UPDATE usuario SET password = ? WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $password_md5, $usuario_id);
    
    if ($stmt->execute()) {
        header("Location: ../dashboard.php?msg=Contraseña actualizada correctamente.");
    } else {
        header("Location: ../dashboard.php?error=Ocurrió un error al actualizar la contraseña.");
    }
} else {
    header("Location: ../dashboard.php");
}
exit;
?>