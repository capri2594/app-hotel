<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

// Consulta para obtener los funcionarios
$sql = "SELECT * FROM funcionario ORDER BY id DESC";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<body class="bg-light">
  
  <!-- Barra de Navegación del Dashboard Administrativo -->
  <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: #680202;">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="#">HabitApp Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link" href="../habitacion/index.php">🏨 Habitaciones</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="index.php">👥 Funcionarios</a>
          </li>
        </ul>
        <div class="d-flex">
            <!-- Asumiendo que luego crearemos logout.php -->
            <a href="../logout.php" class="btn btn-outline-light btn-sm fw-bold">Cerrar Sesión</a>
        </div>
      </div>
    </div>
  </nav>

  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold text-dark">Gestión de Funcionarios</h2>
      <a href="create.php" class="btn btn-success fw-bold shadow-sm">
        + Nuevo Funcionario
      </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light text-secondary">
              <tr>
                <th class="ps-4">ID</th>
                <th>Nombres</th>
                <th>Apellidos</th>
                <th>CI</th>
                <th>Cargo</th>
                <th>Estado</th>
                <th class="text-center pe-4">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                  <tr>
                    <td class="ps-4 fw-bold"><?= $fila['id'] ?></td>
                    <td><?= htmlspecialchars($fila['nombres']) ?></td>
                    <td><?= htmlspecialchars($fila['apellidos']) ?></td>
                    <td><?= htmlspecialchars($fila['ci']) ?></td>
                    <td><?= htmlspecialchars($fila['cargo']) ?></td>
                    <td>
                      <?php if ($fila['estado'] == 'ACTIVO'): ?>
                        <span class="badge bg-success text-white">ACTIVO</span>
                      <?php else: ?>
                        <span class="badge bg-danger text-white">INACTIVO</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center pe-4">
                      <!-- Botones de Acción (Update / Delete) -->
                      <a href="edit.php?id=<?= $fila['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                      <a href="delete.php?id=<?= $fila['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de cambiar el estado de este funcionario a INACTIVO?');">Eliminar</a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No se encontraron funcionarios registrados en el sistema.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- JS de Bootstrap -->
  <script src="../assets/js/bootstrap.min.js"></script>
</body>
</html>