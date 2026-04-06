<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Evitar que el navegador guarde caché de esta página
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../conexion.php';

$usuario_id = $_SESSION['usuario_id'];

// Obtener los datos actuales del usuario
$sql = "SELECT usuario FROM usuario WHERE id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$user_data = $resultado->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<body class="bg-light py-4">
  <div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0 rounded-3 mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                    <h3 class="fw-bold text-dark">⚙️ Mi Perfil</h3>
                    <p class="text-muted">Actualiza tu nombre de usuario y contraseña</p>
                </div>
                <div class="card-body p-4">
                    <!-- Alertas de éxito o error -->
                    <?php if (isset($_GET['msg'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_GET['msg']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($_GET['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="update.php" method="POST">
                        <div class="mb-3">
                            <label for="usuario" class="form-label fw-bold text-dark">Nombre de Usuario de Acceso</label>
                            <input type="text" class="form-control form-control-lg bg-light" id="usuario" name="usuario" value="<?= htmlspecialchars($user_data['usuario']) ?>" required>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="fw-bold fs-6 mb-3 text-dark">Cambiar Contraseña <span class="text-muted fw-normal">(Opcional)</span></h5>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label text-dark">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Dejar en blanco para no cambiarla">
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label text-dark">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Repite la nueva contraseña">
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </div>

  <!-- JS de Bootstrap -->
  <script src="../assets/js/bootstrap.min.js"></script>
</body>
</html>