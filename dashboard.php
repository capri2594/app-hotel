<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

// Evitar que el navegador guarde caché de esta página
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include 'conexion.php';
$stmt = $conexion->prepare("SELECT usuario FROM usuario WHERE id = ?");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$username_actual = $user_data['usuario'] ?? '';
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
<body class="bg-light d-flex flex-column vh-100 overflow-hidden">
  
  <!-- Barra de Navegación del Dashboard -->
  <nav class="navbar navbar-expand-lg navbar-dark shadow-sm" style="background-color: #680202;">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="#" onclick="showDashboardHome()">HabitApp Admin</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link active" id="nav-dashboard" href="#" onclick="showDashboardHome()">📊 Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="nav-habitaciones" href="habitacion/index.php" target="content_frame" onclick="showIframe('nav-habitaciones')">🏨 Habitaciones</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="nav-reservas" href="reservas/index.php" target="content_frame" onclick="showIframe('nav-reservas')">📅 Reservas</a>
          </li>
          <!-- Restricción visual de acuerdo al rol -->
          <?php if ($_SESSION['rol'] == 'SuperAdmin' || $_SESSION['rol'] == 'Administrador'): ?>
          <li class="nav-item">
            <a class="nav-link" id="nav-funcionarios" href="funcionario/index.php" target="content_frame" onclick="showIframe('nav-funcionarios')">👥 Funcionarios</a>
          </li>
          <?php endif; ?> 
        </ul>
        <div class="dropdown">
          <a class="btn btn-outline-light dropdown-toggle fw-bold" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
            👤 Hola, <?= htmlspecialchars($_SESSION['nombre_completo']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow mt-2" aria-labelledby="userMenu">
            <li><h6 class="dropdown-header text-dark fw-bold">Rol: <?= htmlspecialchars($_SESSION['rol']) ?></h6></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalUsuario">⚙️ Cambiar Usuario</a></li>
            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalPassword">🔒 Cambiar Contraseña</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger fw-bold" href="logout.php">🚪 Cerrar Sesión</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <!-- Vista de Tarjetas (Inicio) -->
  <div id="dashboard-home" class="container mt-5 flex-grow-1 overflow-auto">
    <!-- Alertas de éxito o error del perfil -->
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
            <a href="habitacion/index.php" target="content_frame" onclick="showIframe('nav-habitaciones')" class="btn btn-outline-primary mt-2 w-100 fw-bold">Ir a Habitaciones</a>
          </div>
        </div>
      </div>

      <!-- Tarjeta Reservas -->
      <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100 rounded-3">
          <div class="card-body text-center p-4">
            <div class="mb-3"><span class="badge bg-success rounded-circle p-3 fs-3">📅</span></div>
            <h5 class="card-title fw-bold">Gestión de Reservas</h5>
            <p class="card-text text-muted">Aprobar solicitudes web, realizar Check-in y registrar pagos.</p>
            <a href="reservas/index.php" target="content_frame" onclick="showIframe('nav-reservas')" class="btn btn-outline-success mt-2 w-100 fw-bold">Ir a Reservas</a>
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
            <a href="funcionario/index.php" target="content_frame" onclick="showIframe('nav-funcionarios')" class="btn btn-outline-warning mt-2 w-100 text-dark fw-bold">Ir a Funcionarios</a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Contenedor del Iframe -->
  <div id="iframe-container" class="flex-grow-1 w-100" style="display: none;">
    <iframe name="content_frame" id="content_frame" class="w-100 h-100" style="border: none;"></iframe>
  </div>

  <!-- JS de Bootstrap -->
  <script src="assets/js/bootstrap.min.js"></script>
  
  <!-- Modal Cambiar Usuario -->
  <div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalUsuarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold text-dark" id="modalUsuarioLabel">⚙️ Cambiar Nombre de Usuario</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="perfil/update.php" method="POST">
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="update_user">
            <div class="mb-3">
                <label for="usuario" class="form-label text-dark fw-bold">Nuevo Nombre de Usuario</label>
                <input type="text" class="form-control form-control-lg bg-light" id="usuario" name="usuario" value="<?= htmlspecialchars($username_actual) ?>" required>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary fw-bold">Guardar Usuario</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Cambiar Contraseña -->
  <div class="modal fade" id="modalPassword" tabindex="-1" aria-labelledby="modalPasswordLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-light">
          <h5 class="modal-title fw-bold text-dark" id="modalPasswordLabel">🔒 Cambiar Contraseña</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="perfil/update.php" method="POST">
          <div class="modal-body p-4">
            <input type="hidden" name="action" value="update_password">
            <div class="mb-3">
                <label for="password" class="form-label text-dark fw-bold">Nueva Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label text-dark fw-bold">Confirmar Contraseña</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary fw-bold">Actualizar Contraseña</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Script para alternar vistas sin recargar la página -->
  <script src="assets/js/habitapp.js"></script>
</body>
</html>