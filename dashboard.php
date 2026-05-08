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

// ==========================================
// CÁLCULOS ESTADÍSTICOS PARA EL DASHBOARD
// ==========================================

// 1. Ingresos de Hoy
$sql_ingresos = "SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE DATE(fecha) = CURDATE()";
$ingresos_hoy = $conexion->query($sql_ingresos)->fetch_assoc()['total'];

// 2. Estadísticas de Habitaciones (Ocupación y Gráfico)
$sql_habs = "SELECT estado, COUNT(*) as cantidad FROM habitacion GROUP BY estado";
$res_habs = $conexion->query($sql_habs);
$habs_stats = ['DISPONIBLE' => 0, 'OCUPADA' => 0, 'RESERVADA' => 0, 'MANTENIMIENTO' => 0];
$total_habs = 0;
while ($row = $res_habs->fetch_assoc()) {
    $habs_stats[$row['estado']] = $row['cantidad'];
    $total_habs += $row['cantidad'];
}
$ocupacion_pct = ($total_habs > 0) ? round(($habs_stats['OCUPADA'] / $total_habs) * 100) : 0;

// 3. Llegadas (Check-ins Hoy) y Salidas (Check-outs Hoy)
$llegadas_hoy = $conexion->query("SELECT COUNT(*) as total FROM reservas WHERE estado = 'RESERVADA' AND fecha_ingreso <= CURDATE()")->fetch_assoc()['total'];
$salidas_hoy = $conexion->query("SELECT COUNT(*) as total FROM reservas WHERE estado = 'OCUPADA' AND fecha_salida = CURDATE()")->fetch_assoc()['total'];

// + Nueva métrica: Reservas Solicitadas (Web)
$solicitadas_web = $conexion->query("SELECT COUNT(*) as total FROM reservas WHERE estado = 'SOLICITADA'")->fetch_assoc()['total'];

// 4. Gráfico: Evolución de Ingresos (Últimos 7 días)
$res_graf_ingresos = $conexion->query("SELECT DATE(fecha) as fecha_dia, SUM(monto) as total_dia FROM pagos WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(fecha) ORDER BY fecha_dia ASC");
$labels_ingresos = []; $data_ingresos = [];
for ($i = 6; $i >= 0; $i--) { // Rellenar 7 días (incluso si hay días con 0 ingresos)
    $fecha_label = date('Y-m-d', strtotime("-$i days"));
    $labels_ingresos[$fecha_label] = date('d/m', strtotime("-$i days"));
    $data_ingresos[$fecha_label] = 0;
}
while ($row = $res_graf_ingresos->fetch_assoc()) {
    $data_ingresos[$row['fecha_dia']] = $row['total_dia'];
}

// 5. Gráfico: Métodos de Pago (Mes Actual)
$res_pagos = $conexion->query("SELECT tipo_pago, SUM(monto) as total FROM pagos WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) GROUP BY tipo_pago");
$pagos_stats = ['EFECTIVO' => 0, 'QR' => 0, 'DEPOSITO' => 0];
while ($row = $res_pagos->fetch_assoc()) {
    $pagos_stats[$row['tipo_pago']] = $row['total'];
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
    <!-- Estilos Adicionales para el Dashboard -->
    <style>
        .card-kpi { transition: all 0.3s ease; border-left: 4px solid transparent; }
        .card-kpi:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; cursor: pointer; }
        .border-left-success { border-left-color: #198754 !important; }
        .border-left-primary { border-left-color: #0d6efd !important; }
        .border-left-warning { border-left-color: #ffc107 !important; }
        .border-left-danger { border-left-color: #dc3545 !important; }
        .border-left-info { border-left-color: #0dcaf0 !important; }
    </style>
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
          <li class="nav-item">
            <a class="nav-link" id="nav-reportes" href="reportes/reconciliation.php" target="content_frame" onclick="showIframe('nav-reportes')">📈 Reportes</a>
          </li>
          <!-- Restricción visual de acuerdo al rol -->
          <?php if ($_SESSION['rol'] == 'SuperAdmin' || $_SESSION['rol'] == 'Administrador'): ?>
          <li class="nav-item">
            <a class="nav-link" id="nav-funcionarios" href="funcionario/index.php" target="content_frame" onclick="showIframe('nav-funcionarios')">👥 Funcionarios</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" id="nav-tipos" href="tipo_habitacion/index.php" target="content_frame" onclick="showIframe('nav-tipos')">🏷️ Tipos de Hab.</a>
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
  <div id="dashboard-home" class="container pt-4 pb-4 flex-grow-1 overflow-auto">
    <!-- Alertas Flash de éxito o error -->
    <?php if (isset($_SESSION['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['msg']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- NIVEL 1: Acciones Rápidas -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-0">Resumen Operativo</h3>
            <p class="text-muted mb-0 small">Métricas en tiempo real al <?= date('d/m/Y') ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="habitacion/index.php" target="content_frame" onclick="showIframe('nav-habitaciones')" class="btn btn-primary fw-bold shadow-sm">🏨 Mapa</a>
            <a href="reservas/index.php" target="content_frame" onclick="showIframe('nav-reservas')" class="btn btn-success fw-bold shadow-sm">📅 Reservas</a>
            <a href="reportes/reconciliation.php" target="content_frame" onclick="showIframe('nav-reportes')" class="btn btn-info fw-bold shadow-sm">📈 Arqueos</a>
        </div>
    </div>

    <!-- NIVEL 2: Tarjetas de Métricas (KPIs Clickables) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl">
            <div class="card card-kpi border-0 shadow-sm border-left-info h-100 p-3" onclick="showIframe('nav-reservas'); window.frames['content_frame'].location='reservas/index.php';">
                <div class="d-flex justify-content-between align-items-center">
                    <div><p class="text-muted small mb-1 fw-bold text-uppercase">Solicitudes Web</p><h4 class="fw-bold text-info mb-0"><?= $solicitadas_web ?> Pendientes <?= $solicitadas_web > 0 ? '<span class="spinner-grow spinner-grow-sm text-info ms-1" style="animation-duration: 1.5s;" role="status"></span>' : '' ?></h4></div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle"><i class="lni lni-alarm-clock fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="card card-kpi border-0 shadow-sm border-left-success h-100 p-3" onclick="showIframe('nav-reportes'); window.frames['content_frame'].location='reportes/reconciliation.php';">
                <div class="d-flex justify-content-between align-items-center">
                    <div><p class="text-muted small mb-1 fw-bold text-uppercase">Ingresos Hoy</p><h4 class="fw-bold text-success mb-0">Bs. <?= number_format($ingresos_hoy, 2) ?></h4></div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle"><i class="lni lni-coin fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="card card-kpi border-0 shadow-sm border-left-primary h-100 p-3" onclick="showIframe('nav-habitaciones'); window.frames['content_frame'].location='habitacion/index.php';">
                <div class="d-flex justify-content-between align-items-center">
                    <div><p class="text-muted small mb-1 fw-bold text-uppercase">Ocupación Actual</p><h4 class="fw-bold text-primary mb-0"><?= $ocupacion_pct ?>%</h4></div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle"><i class="lni lni-stats-up fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="card card-kpi border-0 shadow-sm border-left-warning h-100 p-3" onclick="showIframe('nav-reservas'); window.frames['content_frame'].location='reservas/index.php';">
                <div class="d-flex justify-content-between align-items-center">
                    <div><p class="text-muted small mb-1 fw-bold text-uppercase">Check-ins Pendientes</p><h4 class="fw-bold text-warning mb-0"><?= $llegadas_hoy ?> LLegadas</h4></div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle"><i class="lni lni-enter fs-4"></i></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl">
            <div class="card card-kpi border-0 shadow-sm border-left-danger h-100 p-3" onclick="showIframe('nav-reservas'); window.frames['content_frame'].location='reservas/index.php';">
                <div class="d-flex justify-content-between align-items-center">
                    <div><p class="text-muted small mb-1 fw-bold text-uppercase">Salidas de Hoy</p><h4 class="fw-bold text-danger mb-0"><?= $salidas_hoy ?> Check-outs</h4></div>
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle"><i class="lni lni-exit fs-4"></i></div>
                </div>
            </div>
        </div>
    </div>

    <!-- NIVEL 3: Gráficos Estadísticos -->
    <div class="row g-4 mb-4">
        <!-- Gráfico Ancho: Ingresos 7 días -->
        <div class="col-md-12">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-4">Evolución de Ingresos (Últimos 7 Días)</h6>
                    <canvas id="chartIngresos" height="80"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Gráfico Mitad: Estado Físico -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-4 text-center">Estado del Hotel (Habitaciones)</h6>
                    <div style="height: 250px; display: flex; justify-content: center;"><canvas id="chartHabs"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Gráfico Mitad: Métodos de Pago -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-4 text-center">Preferencia de Pago (Este Mes)</h6>
                    <div style="height: 250px; display: flex; justify-content: center;"><canvas id="chartPagos"></canvas></div>
                </div>
            </div>
        </div>
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
  <!-- Librería Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <script>
    // Anti-Back-Forward Cache
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) { window.location.reload(); }
    });

    document.addEventListener("DOMContentLoaded", function() {
        // Configuración Global Chart.js
        Chart.defaults.font.family = "'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
        
        // 1. Gráfico de Ingresos (Líneas)
        const ctxIngresos = document.getElementById('chartIngresos').getContext('2d');
        new Chart(ctxIngresos, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_values($labels_ingresos)) ?>,
                datasets: [{
                    label: 'Ingresos (Bs.)',
                    data: <?= json_encode(array_values($data_ingresos)) ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#198754',
                    pointRadius: 4,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });

        // 2. Gráfico Estado Habitaciones (Anillo)
        const ctxHabs = document.getElementById('chartHabs').getContext('2d');
        new Chart(ctxHabs, {
            type: 'doughnut',
            data: {
                labels: ['Disponibles', 'Ocupadas', 'Reservadas', 'Mantenimiento'],
                datasets: [{
                    data: <?= json_encode([$habs_stats['DISPONIBLE'], $habs_stats['OCUPADA'], $habs_stats['RESERVADA'], $habs_stats['MANTENIMIENTO']]) ?>,
                    backgroundColor: ['#198754', '#dc3545', '#ffc107', '#6c757d'],
                    borderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } }, cutout: '70%' }
        });

        // 3. Gráfico Métodos de Pago (Pastel)
        const ctxPagos = document.getElementById('chartPagos').getContext('2d');
        new Chart(ctxPagos, {
            type: 'pie',
            data: {
                labels: ['Efectivo', 'Transferencia QR', 'Depósito Bancario'],
                datasets: [{
                    data: <?= json_encode([$pagos_stats['EFECTIVO'], $pagos_stats['QR'], $pagos_stats['DEPOSITO']]) ?>,
                    backgroundColor: ['#0d6efd', '#6f42c1', '#fd7e14'],
                    borderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
        });
    });
  </script>
</body>
</html>