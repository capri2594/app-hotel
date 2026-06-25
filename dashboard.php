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

date_default_timezone_set('America/La_Paz');
$mostrar_alerta_camara = (date('H:i') >= '08:00' && date('H:i') <= '10:00');

include 'conexion.php';

// ==========================================
// CONTROL DE CAJA Y TURNOS (ACCIONES POST)
// ==========================================
$caja_activa = obtenerCajaActiva($conexion);
$efectivo_esperado = 0.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_caja'])) {
        $action = $_POST['action_caja'];
        $usuario_id = $_SESSION['usuario_id'];
        
        if ($action === 'apertura') {
            $monto_apertura = floatval($_POST['monto_apertura'] ?? 0);
            $stmt = $conexion->prepare("INSERT INTO control_caja (usuario_id, monto_apertura, estado) VALUES (?, ?, 'ABIERTA')");
            $stmt->bind_param("id", $usuario_id, $monto_apertura);
            $stmt->execute();
            
            $caja_id = $conexion->insert_id;
            registrarAuditoria($conexion, 'APERTURA_CAJA', 'control_caja', $caja_id, "Se abrió la caja con un monto inicial de: " . $monto_apertura . " Bs.");
            
            $_SESSION['msg'] = "Turno de caja iniciado y abierto con éxito.";
            header("Location: dashboard.php");
            exit;
        }
        
        if ($action === 'retiro' && $caja_activa) {
            $monto_retiro = floatval($_POST['monto_retiro'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? 'Recojo de Dinero (10:00 AM)');
            
            $stmt = $conexion->prepare("INSERT INTO retiros_caja (caja_id, usuario_id, monto, observaciones) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iids", $caja_activa['id'], $usuario_id, $monto_retiro, $observaciones);
            $stmt->execute();
            
            registrarAuditoria($conexion, 'RETIRO_CAJA', 'retiros_caja', $conexion->insert_id, "Retiro de caja (Recojo de dinero): " . $monto_retiro . " Bs. Detalle: " . $observaciones);
            
            $_SESSION['msg'] = "Retiro de dinero registrado exitosamente en caja.";
            header("Location: dashboard.php");
            exit;
        }
        
        if ($action === 'cierre' && $caja_activa) {
            $monto_cierre_real = floatval($_POST['monto_cierre_real'] ?? 0);
            $observaciones = trim($_POST['observaciones'] ?? '');
            
            $monto_apertura = $caja_activa['monto_apertura'];
            
            // Suma pagos efectivos
            $sql_pagos = "SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE caja_id = ? AND tipo_pago = 'EFECTIVO'";
            $stmt_p = $conexion->prepare($sql_pagos);
            $stmt_p->bind_param("i", $caja_activa['id']);
            $stmt_p->execute();
            $pagos_efectivo = $stmt_p->get_result()->fetch_assoc()['total'];
            
            // Suma retiros
            $sql_retiros = "SELECT COALESCE(SUM(monto), 0) as total FROM retiros_caja WHERE caja_id = ?";
            $stmt_r = $conexion->prepare($sql_retiros);
            $stmt_r->bind_param("i", $caja_activa['id']);
            $stmt_r->execute();
            $retiros = $stmt_r->get_result()->fetch_assoc()['total'];
            
            $monto_cierre_sistema = $monto_apertura + $pagos_efectivo - $retiros;
            $diferencia = $monto_cierre_real - $monto_cierre_sistema;
            
            $stmt = $conexion->prepare("UPDATE control_caja SET fecha_cierre = NOW(), monto_cierre_sistema = ?, monto_cierre_real = ?, diferencia = ?, observaciones = ?, estado = 'CERRADA' WHERE id = ?");
            $stmt->bind_param("dddsi", $monto_cierre_sistema, $monto_cierre_real, $diferencia, $observaciones, $caja_activa['id']);
            $stmt->execute();
            
            registrarAuditoria($conexion, 'CIERRE_CAJA', 'control_caja', $caja_activa['id'], "Cierre de caja. Esperado: " . $monto_cierre_sistema . " Bs. Real: " . $monto_cierre_real . " Bs. Dif: " . $diferencia . " Bs.");
            
            $_SESSION['msg'] = "Caja cerrada y turno finalizado exitosamente. Diferencia: " . number_format($diferencia, 2) . " Bs.";
            header("Location: dashboard.php");
            exit;
        }
    }
}

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

        /* Estilos del Sidebar */
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7) !important;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
        }
        .sidebar .nav-link.active {
            background-color: #680202 !important;
            color: #fff !important;
            box-shadow: 0 4px 10px rgba(104, 2, 2, 0.3);
        }
        .sidebar-body::-webkit-scrollbar {
            width: 5px;
        }
        .sidebar-body::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }
        
        /* Animación para Campanilla de Alerta */
        @keyframes bell-blink {
            0% { opacity: 1; transform: scale(1); color: #dc3545; }
            50% { opacity: 0.3; transform: scale(1.1); color: #ffc107; }
            100% { opacity: 1; transform: scale(1); color: #dc3545; }
        }
        .bell-flash {
            animation: bell-blink 1s infinite;
            cursor: pointer;
            font-size: 1.35rem;
            display: inline-block;
            vertical-align: middle;
        }
    </style>
</head>
<body class="bg-light d-flex vh-100 overflow-hidden">

  <!-- Menú Lateral (Sidebar) -->
  <aside class="sidebar d-flex flex-column flex-shrink-0 text-white bg-dark shadow" style="width: 250px; height: 100vh; background-color: #1a0000 !important; border-right: 1px solid rgba(255,255,255,0.08); z-index: 1000;">
    <div class="sidebar-header p-4 border-bottom border-secondary border-opacity-25 text-center">
      <h4 class="fw-bold mb-0 text-white d-flex align-items-center justify-content-center gap-2" style="font-size: 1.25rem;">
        <i class="lni lni-apartment text-danger"></i> HabitApp Admin
      </h4>
    </div>
    <div class="sidebar-body flex-grow-1 overflow-y-auto py-3">
      <ul class="nav nav-pills flex-column mb-auto px-3 gap-1">
        <li class="nav-item">
          <a class="nav-link text-white active d-flex align-items-center gap-2 py-2 px-3 rounded-3" id="nav-dashboard" href="#" onclick="showDashboardHome()">
            <span class="fs-5">📊</span> Dashboard
          </a>
        </li>
        
        <li class="sidebar-category mt-4 mb-2 text-uppercase fs-7 text-muted fw-bold px-3" style="letter-spacing: 1px; font-size: 0.75rem;">Operaciones</li>
        <li class="nav-item">
          <a class="nav-link text-white d-flex align-items-center gap-2 py-2 px-3 rounded-3" id="nav-habitaciones" href="habitacion/index.php" target="content_frame" onclick="showIframe('nav-habitaciones')">
            <span class="fs-5">🏨</span> Habitaciones
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white d-flex align-items-center gap-2 py-2 px-3 rounded-3" id="nav-reservas" href="reservas/index.php" target="content_frame" onclick="showIframe('nav-reservas')">
            <span class="fs-5">📅</span> Reservas
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white d-flex align-items-center gap-2 py-2 px-3 rounded-3" id="nav-reportes" href="reportes/reconciliation.php" target="content_frame" onclick="showIframe('nav-reportes')">
            <span class="fs-5">📈</span> Reportes
          </a>
        </li>
        
        <?php if ($_SESSION['rol'] == 'SuperAdmin' || $_SESSION['rol'] == 'Administrador'): ?>
        <li class="sidebar-category mt-4 mb-2 text-uppercase fs-7 text-muted fw-bold px-3" style="letter-spacing: 1px; font-size: 0.75rem;">Administración</li>
        <li class="nav-item">
          <a class="nav-link text-white d-flex align-items-center gap-2 py-2 px-3 rounded-3" id="nav-funcionarios" href="funcionario/index.php" target="content_frame" onclick="showIframe('nav-funcionarios')">
            <span class="fs-5">👥</span> Funcionarios
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white d-flex align-items-center gap-2 py-2 px-3 rounded-3" id="nav-tipos" href="tipo_habitacion/index.php" target="content_frame" onclick="showIframe('nav-tipos')">
            <span class="fs-5">🏷️</span> Tipos de Hab.
          </a>
        </li>
        <?php endif; ?>
        <?php if ($_SESSION['rol'] == 'SuperAdmin'): ?>
        <li class="nav-item">
          <a class="nav-link text-white d-flex align-items-center gap-2 py-2 px-3 rounded-3" id="nav-auditoria" href="reportes/auditoria.php" target="content_frame" onclick="showIframe('nav-auditoria')">
            <span class="fs-5">📜</span> Bitácora Auditoría
          </a>
        </li>
        <?php endif; ?>
      </ul>
    </div>
    
    <div class="sidebar-footer p-3 border-top border-secondary border-opacity-25 bg-black bg-opacity-25">
      <div class="dropdown">
        <a class="btn btn-outline-light w-100 dropdown-toggle fw-bold d-flex align-items-center justify-content-between px-3 py-2" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="text-truncate me-1">👤 <?= htmlspecialchars($_SESSION['nombre_completo']) ?></span>
        </a>
        <ul class="dropdown-menu dropdown-menu-dark shadow w-100" aria-labelledby="userMenu">
          <li><h6 class="dropdown-header text-muted fw-bold" style="font-size: 0.75rem;">Rol: <?= htmlspecialchars($_SESSION['rol']) ?></h6></li>
          <li><hr class="dropdown-divider border-secondary"></li>
          <li><a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#modalUsuario"><i class="lni lni-user me-2"></i> Cambiar Usuario</a></li>
          <li><a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#modalPassword"><i class="lni lni-lock me-2"></i> Cambiar Contraseña</a></li>
          <li><hr class="dropdown-divider border-secondary"></li>
          <li><a class="dropdown-item text-danger fw-bold py-2" href="logout.php"><i class="lni lni-exit me-2"></i> Cerrar Sesión</a></li>
        </ul>
      </div>
    </div>
  </aside>

  <!-- Contenedor Principal (Derecha) -->
  <main class="d-flex flex-column flex-grow-1 h-100 overflow-hidden">
    
    <!-- Barra Superior (Top Bar) -->
    <header class="navbar navbar-light bg-white border-bottom shadow-sm px-4 py-2.5 d-flex align-items-center justify-content-between flex-shrink-0" style="height: 60px;">
      <div>
        <h5 class="fw-bold text-dark mb-0" id="current-section-title">📊 Resumen General</h5>
      </div>
      <div class="d-flex align-items-center gap-3">
        <!-- Campanilla parpadeante para Cámara Hotelera (08:00 AM - 10:00 AM) -->
        <?php if ($mostrar_alerta_camara): ?>
          <a href="#" data-bs-toggle="modal" data-bs-target="#modalAlertaCamara" title="Recordatorio: Generar Parte Cámara Hotelera" class="me-2">
            <i class="lni lni-alarm bell-flash"></i>
          </a>
        <?php endif; ?>

        <!-- Botón rápido para copiar link de reservas -->
        <button class="btn btn-outline-danger btn-sm fw-bold d-flex align-items-center gap-2 shadow-sm py-1.5 px-3" onclick="copiarEnlaceReservas(this)">
          <i class="lni lni-link"></i> <span>Copiar Link de Reservas</span>
        </button>
        <div class="text-muted d-none d-md-block" style="font-size: 0.85rem;">
          <i class="lni lni-calendar me-1"></i><?= date('d/m/Y') ?>
        </div>
      </div>
    </header>

  <!-- Vista de Tarjetas (Inicio) -->
  <div id="dashboard-home" class="container-fluid pt-4 pb-4 px-4 flex-grow-1 overflow-auto">
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

    <!-- SECCIÓN: CONTROL DE CAJA Y TURNO ACTIVO -->
    <?php if ($_SESSION['rol'] !== 'SuperAdmin'): ?>
    <div class="row mb-4">
        <div class="col-12">
            <?php if ($caja_activa === null): ?>
                <div class="card border-0 shadow-sm p-3 mb-4" style="background-color: #f8d7da; border-left: 5px solid #dc3545 !important;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h5 class="fw-bold mb-1" style="color: #842029;"><i class="lni lni-lock me-1"></i> Turno de Caja Cerrado</h5>
                            <p class="mb-0 small text-dark">Para comenzar a operar, recibir pagos de huéspedes y consolidar Check-ins/Check-outs, debe abrir su caja ingresando el fondo de apertura inicial.</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAbrirCaja">
                                <i class="lni lni-key"></i> Abrir Caja / Iniciar Turno
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php
                    // Cálculos de la caja activa
                    $caja_id = $caja_activa['id'];
                    $efectivo_pagado = $conexion->query("SELECT COALESCE(SUM(monto), 0) as total FROM pagos WHERE caja_id = $caja_id AND tipo_pago = 'EFECTIVO'")->fetch_assoc()['total'];
                    $otros_pagos = $conexion->query("SELECT COALESCE(SUM(CASE WHEN tipo_pago='QR' THEN monto ELSE 0 END), 0) as qr, COALESCE(SUM(CASE WHEN tipo_pago='DEPOSITO' THEN monto ELSE 0 END), 0) as dep FROM pagos WHERE caja_id = $caja_id")->fetch_assoc();
                    $total_retiros = $conexion->query("SELECT COALESCE(SUM(monto), 0) as total FROM retiros_caja WHERE caja_id = $caja_id")->fetch_assoc()['total'];
                    
                    $efectivo_esperado = $caja_activa['monto_apertura'] + $efectivo_pagado - $total_retiros;
                ?>
                <div class="card border shadow-sm p-3 mb-4" style="background-color: #d1e7dd; border-color: #badbcc; border-left: 5px solid #198754 !important;">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div class="flex-grow-1">
                            <h5 class="fw-bold mb-2" style="color: #0f5132;"><i class="lni lni-unlock me-1"></i> Turno de Caja Abierto</h5>
                            <div class="row g-2 text-dark font-monospace small">
                                <div class="col-6 col-sm-3">
                                    <span class="d-block small" style="color: #145a32; font-weight: 600;">Fondo de Apertura</span>
                                    <strong class="text-dark fs-6">Bs. <?= number_format($caja_activa['monto_apertura'], 2) ?></strong>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <span class="d-block small" style="color: #145a32; font-weight: 600;">Cobros en Efectivo</span>
                                    <strong class="fs-6" style="color: #0f5132;">+Bs. <?= number_format($efectivo_pagado, 2) ?></strong>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <span class="d-block small" style="color: #145a32; font-weight: 600;">Retiros (Recojo 10 AM)</span>
                                    <strong class="fs-6" style="color: #842029;">-Bs. <?= number_format($total_retiros, 2) ?></strong>
                                </div>
                                <div class="col-6 col-sm-3 bg-white p-2 rounded shadow-sm border border-success">
                                    <span class="text-secondary d-block small fw-bold">Efectivo en Caja (Esperado)</span>
                                    <strong class="text-dark fs-6">Bs. <?= number_format($efectivo_esperado, 2) ?></strong>
                                </div>
                            </div>
                            <div class="mt-2 small d-flex flex-wrap gap-3" style="color: #0f5132; font-weight: 500;">
                                <span><i class="lni lni-timer me-1"></i>Apertura: <?= date('d/m/Y H:i', strtotime($caja_activa['fecha_apertura'])) ?></span>
                                <span><i class="lni lni-empty-file me-1"></i>Cobros Otros Métodos: QR (Bs. <?= number_format($otros_pagos['qr'], 2) ?>) | Depósito (Bs. <?= number_format($otros_pagos['dep'], 2) ?>)</span>
                            </div>
                        </div>
                        <div class="d-flex flex-column gap-2" style="min-width: 220px;">
                            <button type="button" class="btn btn-outline-danger btn-sm fw-bold bg-white" data-bs-toggle="modal" data-bs-target="#modalRetiroCaja">
                                <i class="lni lni-coin"></i> Registrar Recojo (10:00 AM)
                            </button>
                            <button type="button" class="btn btn-success btn-sm fw-bold shadow-sm text-white" data-bs-toggle="modal" data-bs-target="#modalCerrarCaja">
                                <i class="lni lni-lock"></i> Cerrar Turno y Caja
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
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

  </main>

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

  <!-- Modal Abrir Caja -->
  <div class="modal fade" id="modalAbrirCaja" tabindex="-1" aria-labelledby="modalAbrirCajaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title fw-bold" id="modalAbrirCajaLabel"><i class="lni lni-key me-1"></i> Abrir Turno de Caja</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="dashboard.php" method="POST">
          <input type="hidden" name="action_caja" value="apertura">
          <div class="modal-body p-4">
              <div class="mb-3 text-center">
                  <span class="fs-1">💵</span>
                  <p class="text-muted small mt-2">Por favor, ingrese el saldo inicial (dinero en efectivo) que recibe en caja chica para iniciar operaciones.</p>
              </div>
              <div class="mb-3">
                  <label class="form-label text-dark fw-bold small">Monto de Apertura (Bs.)</label>
                  <input type="number" step="0.01" min="0" max="10000" class="form-control form-control-lg text-center font-monospace fw-bold text-primary" name="monto_apertura" value="0.00" required>
              </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-danger fw-bold shadow-sm">Iniciar Turno / Abrir Caja</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Registrar Retiro -->
  <div class="modal fade" id="modalRetiroCaja" tabindex="-1" aria-labelledby="modalRetiroCajaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title fw-bold" id="modalRetiroCajaLabel"><i class="lni lni-coin me-1"></i> Registrar Recojo de Dinero (Retiro)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="dashboard.php" method="POST">
          <input type="hidden" name="action_caja" value="retiro">
          <div class="modal-body p-4">
              <div class="mb-3 text-center">
                  <span class="fs-1">💰</span>
                  <p class="text-muted small mt-2">Use esta opción para registrar retiros de caja chica en efectivo (por ejemplo, el recojo programado de dinero a las 10:00 AM).</p>
              </div>
              <div class="mb-3">
                  <label class="form-label text-dark fw-bold small">Monto a Retirar (Bs.)</label>
                  <input type="number" step="0.01" min="0.01" class="form-control form-control-lg text-center font-monospace fw-bold text-danger" name="monto_retiro" placeholder="0.00" required>
              </div>
              <div class="mb-3">
                  <label class="form-label text-dark fw-bold small">Concepto / Observaciones</label>
                  <input type="text" class="form-control" name="observaciones" value="Recojo de Dinero (10:00 AM)" required>
              </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-warning fw-bold shadow-sm text-dark">Registrar Retiro</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Cerrar Turno -->
  <div class="modal fade" id="modalCerrarCaja" tabindex="-1" aria-labelledby="modalCerrarCajaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title fw-bold" id="modalCerrarCajaLabel"><i class="lni lni-lock me-1"></i> Cerrar Caja y Finalizar Turno</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form action="dashboard.php" method="POST">
          <input type="hidden" name="action_caja" value="cierre">
          <div class="modal-body p-4">
              <div class="mb-3 text-center">
                  <span class="fs-1">🔒</span>
                  <p class="text-muted small mt-2">Proceda al arqueo físico. Cuente todo el efectivo existente en el cajón de la recepción y declare el monto real abajo.</p>
              </div>
              
              <div class="alert alert-light border font-monospace text-center py-2 mb-3">
                  <span class="small text-muted d-block">Efectivo Esperado (Sistema)</span>
                  <strong class="fs-4 text-secondary">Bs. <?= number_format($efectivo_esperado ?? 0, 2) ?></strong>
              </div>
              
              <div class="mb-3">
                  <label class="form-label text-dark fw-bold small">Monto Declarado (Efectivo Real Contado) (Bs.)</label>
                  <input type="number" step="0.01" min="0" class="form-control form-control-lg text-center font-monospace fw-bold text-success" name="monto_cierre_real" placeholder="0.00" required>
              </div>
              <div class="mb-3">
                  <label class="form-label text-dark fw-bold small">Observaciones del Cierre</label>
                  <textarea class="form-control small" name="observaciones" placeholder="Indique observaciones si existiera faltante/sobrante..." rows="2"></textarea>
              </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-success fw-bold shadow-sm text-white">Proceder con el Cierre de Caja</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Recordatorio Cámara Hotelera (08:00 AM - 10:00 AM) -->
  <div class="modal fade" id="modalAlertaCamara" tabindex="-1" aria-labelledby="modalAlertaCamaraLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header text-white" style="background-color: #6c5ce7;">
          <h5 class="modal-title fw-bold" id="modalAlertaCamaraLabel">⚠️ Recordatorio: Cámara Hotelera</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4 text-center">
            <div class="mb-3 text-warning">
                <i class="lni lni-alarm bell-flash fs-1" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold text-dark mb-2">¡Es Hora del Reporte Diario!</h5>
            <p class="text-muted small">Le recordamos que se encuentra en el horario establecido (<strong>08:00 AM a 10:00 AM</strong>) para realizar el envío y generación del Parte Diario para la Cámara Hotelera.</p>
            <p class="text-muted small">Por favor, presione el botón de abajo para ir directamente al panel de reportes.</p>
        </div>
        <div class="modal-footer bg-light d-flex justify-content-between">
          <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cerrar Recordatorio</button>
          <button type="button" class="btn text-white fw-bold shadow-sm" style="background-color: #6c5ce7;" onclick="showIframe('nav-reportes'); window.frames['content_frame'].location='reportes/reconciliation.php'; bootstrap.Modal.getInstance(document.getElementById('modalAlertaCamara')).hide();">
            <i class="lni lni-printer me-1"></i> Ir a Reportes
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function copiarEnlaceReservas(btn) {
        // Calcular el enlace de la carpeta /cliente/ de forma dinámica
        let path = window.location.pathname;
        let dir = path.substring(0, path.lastIndexOf('/'));
        let url = window.location.origin + dir + '/cliente/';

        // Intentar copiar al portapapeles
        navigator.clipboard.writeText(url).then(function() {
            let origText = btn.innerHTML;
            btn.innerHTML = '<i class="lni lni-checkmark"></i> <span>¡Copiado!</span>';
            btn.classList.remove('btn-outline-danger', 'btn-outline-light');
            btn.classList.add('btn-success');
            
            setTimeout(function() {
                btn.innerHTML = origText;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-outline-danger');
            }, 2500);
        }).catch(function(err) {
            console.error('Error al copiar el enlace: ', err);
            alert('No se pudo copiar el enlace de manera automática. URL: ' + url);
        });
    }
  </script>
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

        // 4. Mostrar alerta de Cámara Hotelera automáticamente una vez por sesión
        if (<?= $mostrar_alerta_camara ? 'true' : 'false' ?>) {
            if (!sessionStorage.getItem('alerta_camara_mostrada')) {
                const modalEl = document.getElementById('modalAlertaCamara');
                if (modalEl) {
                    const alertModal = new bootstrap.Modal(modalEl);
                    alertModal.show();
                    sessionStorage.setItem('alerta_camara_mostrada', 'true');
                }
            }
        }
    });
  </script>
</body>
</html>