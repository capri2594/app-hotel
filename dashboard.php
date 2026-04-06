<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="ie=edge" />
    <title>Dashboard - HabitApp Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico" />
    <!-- CSS de Bootstrap 5 -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/main.css" />
</head>
<body class="bg-light">
  
  <!-- Barra de Navegación del Dashboard -->
  <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: #680202;">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="dashboard.php">HabitApp Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link active" href="dashboard.php">📊 Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="habitacion/index.php">🏨 Habitaciones</a>
          </li>
          <!-- Restricción visual de acuerdo al rol -->
          <?php if ($_SESSION['rol'] == 'SuperAdmin' || $_SESSION['rol'] == 'Administrador'): ?>
          <li class="nav-item">
            <a class="nav-link" href="funcionario/index.php">👥 Funcionarios</a>
          </li>
          <?php endif; ?>
        </ul>
        <div class="d-flex align-items-center text-white me-4">
            <span class="me-2">Hola, <strong><?= htmlspecialchars($_SESSION['nombre_completo']) ?></strong> (<?= htmlspecialchars($_SESSION['rol']) ?>)</span>
        </div>
        <div class="d-flex">
            <a href="logout.php" class="btn btn-outline-light btn-sm fw-bold">Cerrar Sesión</a>
        </div>
      </div>
    </div>
  </nav>

  <div class="container mt-5">
    <div class="row mb-4">
      <div class="col-12">
        <h2 class="fw-bold text-dark">Bienvenido al Panel de Control</h2>
        <p class="text-muted">Selecciona un módulo para comenzar a administrar el hotel.</p>
      </div>
    </div>

    <div class="row g-4">
      <!-- Tarjeta Habitaciones -->
      <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3">
          <div class="card-body text-center p-4">
            <div class="mb-3"><span class="badge bg-primary rounded-circle p-3 fs-3">🏨</span></div>
            <h5 class="card-title fw-bold">Gestión de Habitaciones</h5>
            <p class="card-text text-muted">Ver estado, disponibilidad en tiempo real y mapa de pisos.</p>
            <a href="habitacion/index.php" class="btn btn-outline-primary mt-2 w-100 fw-bold">Ir a Habitaciones</a>
          </div>
        </div>
      </div>

      <!-- Tarjeta Funcionarios (Restringido por Rol) -->
      <?php if ($_SESSION['rol'] == 'SuperAdmin' || $_SESSION['rol'] == 'Administrador'): ?>
      <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3">
          <div class="card-body text-center p-4">
            <div class="mb-3"><span class="badge bg-warning rounded-circle p-3 fs-3 text-dark">👥</span></div>
            <h5 class="card-title fw-bold">Personal del Hotel</h5>
            <p class="card-text text-muted">Administrar, registrar o dar de baja a los funcionarios y recepcionistas.</p>
            <a href="funcionario/index.php" class="btn btn-outline-warning mt-2 w-100 text-dark fw-bold">Ir a Funcionarios</a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- JS de Bootstrap -->
  <script src="assets/js/bootstrap.min.js"></script>
</body>
</html>
