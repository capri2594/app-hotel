<?php
session_start();
include '../conexion.php';

// Recibir datos
$usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';

if (empty($usuario) || empty($contrasena)) {
    echo "⚠️ Todos los campos son requeridos.";
    exit;
}
$password_md5 = md5($password
// Buscar en la base de datos
$sql = "SELECT * FROM usuario WHERE usuario = ? AND contrasena = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("si", $usuario, $contrasena);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    // Login exitoso
    $usuario = $resultado->fetch_assoc();
    $_SESSION['usuario_id'] = $usuario['id'];

    // Redireccionar
    header("Location: create.php");
    exit;
} else {
    // Datos incorrectos
    echo "<script>
        alert('❌ Usuario o contrasena incorrecto');
        window.location.href = 'index1.php'; // Vuelve al formulario
    </script>";
}
?>