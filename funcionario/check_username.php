<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

include '../conexion.php';
$usuario = trim($_GET['u'] ?? '');

if (empty($usuario)) {
    echo json_encode(['existe' => false]);
    exit;
}

$stmt = $conexion->prepare("SELECT id FROM usuario WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

$existe = $resultado->num_rows > 0;
$response = ['existe' => $existe];

if ($existe) {
    $sugerencias = [];
    // Generar algunas variantes numéricas para sugerir
    $variantes = [1, 2, date('y'), rand(10, 99), rand(100, 999)];
    
    foreach ($variantes as $num) {
        if (count($sugerencias) >= 3) break; // Limitar a 3 sugerencias máximo
        $sug = $usuario . $num;
        $stmt_check = $conexion->prepare("SELECT id FROM usuario WHERE usuario = ?");
        $stmt_check->bind_param("s", $sug);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows == 0) {
            $sugerencias[] = $sug;
        }
    }
    $response['sugerencias'] = $sugerencias;
}

echo json_encode($response);
?>