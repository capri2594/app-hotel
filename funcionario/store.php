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
    $nombre = trim($_POST['nombre'] ?? '');
    $paterno = trim($_POST['paterno'] ?? '');
    $materno = trim($_POST['materno'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $rol_id = intval($_POST['rol_id'] ?? 0);
    
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validaciones básicas
    if (empty($nombre) || empty($paterno) || empty($ci) || !$rol_id) {
        header("Location: index.php?error=Faltan completar datos obligatorios.");
        exit;
    }

    // Iniciar Transacción (Si falla algo, no se guarda nada)
    $conexion->begin_transaction();

    try {
        // 1. Guardar datos en tabla "funcionario"
        $sql_func = "INSERT INTO funcionario (nombre, paterno, materno, ci, telefono, rol_id, estado) 
                     VALUES (?, ?, ?, ?, ?, ?, 'ACTIVO')";
        $stmt = $conexion->prepare($sql_func);
        $stmt->bind_param("sssssi", $nombre, $paterno, $materno, $ci, $telefono, $rol_id);
        $stmt->execute();
        
        $funcionario_id = $conexion->insert_id; // Obtener el ID del funcionario recién creado

        // 2. Si se escribió un usuario y contraseña, guardar en tabla "usuario"
        if (!empty($usuario) && !empty($password)) {
            
            // Verificar que el usuario no se repita, agregarle un random si existe
            $usuario_final = $usuario;
            $existe = true;
            while ($existe) {
                $stmt_check = $conexion->prepare("SELECT id FROM usuario WHERE usuario = ?");
                $stmt_check->bind_param("s", $usuario_final);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    $usuario_final = $usuario . rand(10, 999);
                } else {
                    $existe = false;
                }
            }

            $password_hashed = password_hash($password, PASSWORD_BCRYPT);
            $sql_user = "INSERT INTO usuario (funcionario_id, usuario, password) VALUES (?, ?, ?)";
            $stmt_user = $conexion->prepare($sql_user);
            $stmt_user->bind_param("iss", $funcionario_id, $usuario_final, $password_hashed);
            $stmt_user->execute();
        }

        $conexion->commit(); // Confirmar la transacción
        header("Location: index.php?msg=Funcionario registrado correctamente.");
        exit;

    } catch (Exception $e) {
        $conexion->rollback(); // Deshacer cambios si hay error (ej. CI duplicado o usuario duplicado)
        header("Location: index.php?error=Ocurrió un error al guardar. Verifique que el CI o el Usuario no estén repetidos.");
        exit;
    }
}
?>