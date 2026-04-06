<?php
session_start();
include 'conexion.php';

// Recibir datos
$usuario = $_POST['usuario'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($usuario) || empty($password)) {
    echo "⚠️ Todos los campos son requeridos.";
    exit;
}

// Encriptar la contraseña ingresada para compararla con la base de datos
$password_md5 = md5($password);

// Buscar en la base de datos haciendo JOIN para obtener datos del funcionario y su rol
$sql = "SELECT u.id as usuario_id, f.id as funcionario_id, f.nombres, f.apellidos, r.nombre as rol_nombre 
        FROM usuario u 
        INNER JOIN funcionario f ON u.funcionario_id = f.id 
        INNER JOIN rol r ON f.rol_id = r.id 
        WHERE u.usuario = ? AND u.password = ? AND f.estado = 'ACTIVO'";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $usuario, $password_md5);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    // Login exitoso
    $datos_usuario = $resultado->fetch_assoc();
    $_SESSION['usuario_id'] = $datos_usuario['usuario_id'];
    $_SESSION['funcionario_id'] = $datos_usuario['funcionario_id'];
    $_SESSION['rol'] = $datos_usuario['rol_nombre'];
    $_SESSION['nombre_completo'] = $datos_usuario['nombres'] . ' ' . $datos_usuario['apellidos'];

    // Redireccionar
    header("Location: funcionario/index.php");
    exit;
} else {
    // Datos incorrectos
    echo "<script>
        alert('❌ Usuario o contraseña incorrecto, o cuenta inactiva');
        window.location.href = 'index.php'; // Vuelve al formulario
    </script>";
}
?>