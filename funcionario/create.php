<?php
session_start();

// Validar si está logueado y es administrador
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['SuperAdmin', 'Administrador'])) {
    header("Location: ../dashboard.php");
    exit;
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../conexion.php';

// Obtener los roles disponibles para el select
$sql_roles = "SELECT * FROM rol ORDER BY id ASC";
$res_roles = $conexion->query($sql_roles);
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<body class="bg-light py-4">
  <div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="fw-bold text-dark mb-0">👥 Registrar Nuevo Funcionario</h3>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm fw-bold shadow-sm">⬅ Volver a la Lista</a>
                    </div>
                </div>
                <div class="card-body p-4">
                    
                    <!-- Formulario que envía datos a store.php -->
                    <form action="store.php" method="POST">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nombres" class="form-label fw-bold text-dark">Nombres</label>
                                <input type="text" class="form-control bg-light" id="nombres" name="nombres" required>
                            </div>
                            <div class="col-md-6">
                                <label for="apellidos" class="form-label fw-bold text-dark">Apellidos</label>
                                <input type="text" class="form-control bg-light" id="apellidos" name="apellidos" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ci" class="form-label fw-bold text-dark">Carnet de Identidad (CI)</label>
                                <input type="text" class="form-control bg-light" id="ci" name="ci" required>
                            </div>
                            <div class="col-md-6">
                                <label for="telefono" class="form-label fw-bold text-dark">Teléfono</label>
                                <input type="text" class="form-control bg-light" id="telefono" name="telefono">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="rol_id" class="form-label fw-bold text-dark">Rol / Cargo</label>
                                <select class="form-select bg-light" id="rol_id" name="rol_id" required>
                                    <option value="" disabled selected>Seleccione un rol...</option>
                                    <?php while($rol = $res_roles->fetch_assoc()): ?>
                                        <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="salario" class="form-label fw-bold text-dark">Salario Base (Bs.)</label>
                                <input type="number" step="0.01" class="form-control bg-light" id="salario" name="salario" required>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_contratacion" class="form-label fw-bold text-dark">Fecha de Contratación</label>
                                <input type="date" class="form-control bg-light" id="fecha_contratacion" name="fecha_contratacion" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <hr class="my-4">
                        
                        <!-- Opcional: Crear cuenta de acceso al sistema -->
                        <h5 class="fw-bold fs-6 mb-3 text-dark">🔐 Cuenta de Acceso al Sistema</h5>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="usuario" class="form-label text-dark">Nombre de Usuario</label>
                                <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Dejar en blanco si no requiere acceso">
                            </div>
                            <div class="col-md-6">
                                <label for="password" class="form-label text-dark">Contraseña Inicial</label>
                                <input type="text" class="form-control" id="password" name="password" placeholder="Requerido si asigna usuario">
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success btn-lg fw-bold shadow-sm">Registrar Funcionario</button>
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