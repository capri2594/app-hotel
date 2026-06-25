<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Evitar que el navegador guarde caché (Protege el botón Atrás tras el Logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../conexion.php';

$caja_activa = obtenerCajaActiva($conexion);
$mostrar_bloqueo_caja = ($_SESSION['rol'] !== 'SuperAdmin' && $caja_activa === null);
$btn_disabled = $mostrar_bloqueo_caja ? 'disabled title="Debe abrir turno de caja para realizar esta operación"' : '';

// PARCHE AUTOMÁTICO: Agregar columna confirmada_at si no existe
$conexion->query("ALTER TABLE reservas ADD COLUMN IF NOT EXISTS confirmada_at TIMESTAMP NULL AFTER created_at");

// LIMPIEZA AUTOMÁTICA Y LIBERACIÓN DE HABITACIONES (Reservas > 12 horas sin consolidar)
$sql_expiring = "SELECT id FROM reservas WHERE 
                 (estado = 'SOLICITADA' AND created_at <= NOW() - INTERVAL 12 HOUR) OR 
                 (estado = 'RESERVADA' AND confirmada_at <= NOW() - INTERVAL 12 HOUR)";
$res_expiring = $conexion->query($sql_expiring);
if ($res_expiring && $res_expiring->num_rows > 0) {
    while($row = $res_expiring->fetch_assoc()) {
        $id_res = $row['id'];
        // 1. Liberar las habitaciones asociadas (Volver a DISPONIBLE en el mapa)
        $conexion->query("UPDATE habitacion SET estado = 'DISPONIBLE' WHERE id_habitacion IN (SELECT habitacion_id FROM detalle_reserva WHERE reserva_id = $id_res)");
        // 2. Marcar la reserva como EXPIRADA
        $conexion->query("UPDATE reservas SET estado = 'EXPIRADA' WHERE id = $id_res");
    }
}

// Obtener conteos para las pestañas
$sql_counts = "SELECT estado, COUNT(*) as total FROM reservas GROUP BY estado";
$res_counts = $conexion->query($sql_counts);
$counts = ['SOLICITADA' => 0, 'RESERVADA' => 0, 'OCUPADA' => 0, 'FINALIZADA' => 0, 'EXPIRADA' => 0];
$total_reservas = 0;
if ($res_counts) {
    while ($row = $res_counts->fetch_assoc()) {
        $counts[$row['estado']] = $row['total'];
        $total_reservas += $row['total'];
    }
}

// Obtener reservas filtradas
$sql = "SELECT r.*, 
               GROUP_CONCAT(h.numero SEPARATOR ', ') as numeros_habitaciones,
               GROUP_CONCAT(DISTINCT t.nombre SEPARATOR ', ') as tipos_habitaciones,
               COUNT(dr.habitacion_id) as total_habitaciones,
               COALESCE(SUM(t.capacidad), 1) as capacidad_total,
               COALESCE(SUM(t.precio), 0) as tarifa_noche,
               TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(r.confirmada_at, INTERVAL 12 HOUR)) as segundos_restantes
        FROM reservas r 
        LEFT JOIN detalle_reserva dr ON r.id = dr.reserva_id
        LEFT JOIN habitacion h ON dr.habitacion_id = h.id_habitacion
        LEFT JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo
        GROUP BY r.id
        ORDER BY FIELD(r.estado, 'SOLICITADA', 'RESERVADA', 'OCUPADA', 'FINALIZADA', 'EXPIRADA'), r.id DESC";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<!-- CSS de DataTables para Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
<!-- CSS para Botones de DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" />

<style>
    /* Estilo premium para las pestañas de filtro */
    .tab-filtro {
        font-size: 0.82rem !important;
        padding: 5px 10px !important;
        white-space: nowrap !important;
        border-radius: 8px !important;
        transition: all 0.2s ease-in-out;
    }
    .tab-filtro:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }
    
    /* Alinear input de búsqueda de Datatables */
    .dataTables_filter label {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin-bottom: 0 !important;
        font-weight: bold !important;
        font-size: 0.9rem !important;
        color: #555 !important;
    }
    .dataTables_filter input {
        margin-left: 0 !important;
        border-radius: 6px !important;
        border: 1px solid #ced4da !important;
        padding: 5px 10px !important;
    }
</style>

<body class="bg-light py-4">
  <div class="container">
    
    <!-- Alertas de éxito o error -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="lni lni-checkmark-circle me-1"></i> <?= htmlspecialchars($_GET['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="lni lni-warning me-1"></i> <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Alerta para Imprimir Comprobante de Check-out -->
    <?php if (isset($_SESSION['last_checkout_id'])): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm border-info d-flex justify-content-between align-items-center" role="alert">
            <div>
                <i class="lni lni-printer fs-4 me-2 align-middle"></i>
                <span class="align-middle">Check-out finalizado. ¿Desea generar el comprobante de pago?</span>
            </div>
            <form action="../reportes/comprobantes/CheckoutPdf.php" method="POST" target="_blank">
                <input type="hidden" name="id" value="<?= intval($_SESSION['last_checkout_id']) ?>">
                <button type="submit" class="btn btn-primary fw-bold shadow-sm"><i class="lni lni-printer me-1"></i> Imprimir Comprobante</button>
            </form>
        </div>
        <?php unset($_SESSION['last_checkout_id']); // Limpiar la sesión para que no vuelva a aparecer ?>
    <?php endif; ?>

    <!-- Alerta de Caja Cerrada -->
    <?php if ($mostrar_bloqueo_caja): ?>
        <div class="alert alert-danger border shadow-sm mb-4 d-flex align-items-center gap-3 py-3" role="alert">
            <div class="fs-2 text-danger" style="font-size: 1.8rem;"><i class="lni lni-warning"></i></div>
            <div>
                <h6 class="alert-heading fw-bold mb-1">Operaciones Transaccionales Bloqueadas</h6>
                <p class="mb-0 small">No se detectó un turno de caja activo para su usuario. Para consolidar Check-in, Check-out o registrar extensiones y cobros, debe abrir una caja en el <a href="../dashboard.php" class="alert-link fw-bold text-decoration-underline">Dashboard</a>.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold text-dark"><i class="lni lni-agenda me-2"></i>Gestión de Reservas</h2>
      <a href="../habitacion/index.php" class="btn btn-outline-primary fw-bold shadow-sm">
        <i class="lni lni-map"></i> Ver Mapa de Habitaciones
      </a>
    </div>

    <!-- Pestañas de Filtro Inteligente -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-pills gap-2">
                <li class="nav-item">
                    <a class="nav-link active shadow-sm fw-bold tab-filtro" href="#" data-estado="">
                        📋 Todas <span class="badge bg-secondary text-white ms-1 rounded-pill"><?= $total_reservas ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="SOLICITADA">
                        ⏳ Solicitadas <span class="badge bg-primary text-white ms-1 rounded-pill"><?= $counts['SOLICITADA'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="RESERVADA">
                        ✅ Reservadas <span class="badge bg-warning text-dark ms-1 rounded-pill"><?= $counts['RESERVADA'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="OCUPADA">
                        🛏️ Ocupadas <span class="badge bg-danger text-white ms-1 rounded-pill"><?= $counts['OCUPADA'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="FINALIZADA">
                        🏁 Finalizadas <span class="badge bg-success text-white ms-1 rounded-pill"><?= $counts['FINALIZADA'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="EXPIRADA">
                        ❌ Expiradas <span class="badge bg-dark text-white ms-1 rounded-pill"><?= $counts['EXPIRADA'] ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
      <div class="card-body p-4">
        <div class="table-responsive">
          <table id="tablaReservas" class="table table-hover align-middle mb-0" style="width: 100%;">
            <thead class="table-light text-secondary">
              <tr>
                <th class="ps-4">CI / DNI</th>
                <th>Cliente</th>
                <th>Habitación</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Total (Bs.)</th>
                <th>Estado</th>
                <th class="text-center pe-4">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                  <tr>
                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($fila['ci'] ?? '') ?></td>
                    <td>
                        <strong><?= htmlspecialchars($fila['nombre'] ?? '') ?></strong><br>
                        <small class="text-muted">
                            <i class="lni lni-phone me-1"></i><?= htmlspecialchars($fila['telefono'] ?? '') ?>
                            <?php if(!empty($fila['telefono'])): ?>
                                <?php $tel_limpio = preg_replace('/[^0-9]/', '', $fila['telefono']); ?>
                                <a href="https://wa.me/<?= $tel_limpio ?>?text=<?= rawurlencode('Hola ' . trim($fila['nombre']) . ', nos comunicamos de la Recepción del hotel HabitApp...') ?>" target="_blank" class="text-success ms-1 fs-6" title="Abrir Chat"><i class="lni lni-whatsapp"></i></a>
                            <?php endif; ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge bg-dark fs-6"><?= htmlspecialchars($fila['numeros_habitaciones'] ?? 'Sin Asignar') ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars($fila['tipos_habitaciones'] ?? 'N/A') ?> (<?= $fila['total_habitaciones'] ?? 0 ?> habs)</small>
                    </td>
                    <td>
                        <?= !empty($fila['fecha_ingreso']) ? date('d/m/Y', strtotime($fila['fecha_ingreso'])) : 'N/A' ?>
                        <?= $fila['estado'] == 'EXPIRADA' ? '<br><small class="text-danger" style="font-size: 0.65rem;">No consumado</small>' : '' ?>
                    </td>
                    <td>
                        <?= !empty($fila['fecha_salida']) ? date('d/m/Y', strtotime($fila['fecha_salida'])) : 'N/A' ?>
                        <?= $fila['estado'] == 'EXPIRADA' ? '<br><small class="text-danger" style="font-size: 0.65rem;">No consumado</small>' : '' ?>
                    </td>
                    <td class="fw-bold text-success"><?= number_format((float)($fila['total'] ?? 0), 2) ?></td>
                    <td data-search="<?= htmlspecialchars($fila['estado']) ?>">
                      <?php 
                        $badge_class = 'bg-secondary';
                        $estado_mostrar = $fila['estado'];
                        if($fila['estado'] == 'SOLICITADA') $badge_class = 'bg-primary';
                        if($fila['estado'] == 'RESERVADA') $badge_class = 'bg-warning text-dark';
                        if($fila['estado'] == 'OCUPADA') $badge_class = 'bg-danger';
                        if($fila['estado'] == 'FINALIZADA') $badge_class = 'bg-success';
                        if($fila['estado'] == 'EXPIRADA') $badge_class = 'bg-dark';
                      ?>
                      <span class="badge <?= $badge_class ?>"><?= $estado_mostrar ?></span>
                      <?php if($fila['estado'] == 'RESERVADA' && !empty($fila['confirmada_at'])): ?>
                          <?php $restantes = (int)$fila['segundos_restantes']; ?>
                          <div class="text-danger fw-bold mt-1 countdown-timer" style="font-size: 0.75rem;" data-remaining="<?= $restantes ?>">
                              <i class="lni lni-timer"></i> <span>Calculando...</span>
                          </div>
                      <?php endif; ?>
                    </td>
                     <td class="text-center pe-4 text-nowrap">
                      <?php if ($fila['estado'] == 'SOLICITADA'): ?>
                          <!-- Botón Confirmar -->
                          <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalConfirmar<?= $fila['id'] ?>">
                            <i class="lni lni-checkmark"></i> Confirmar
                          </button>
                      <?php elseif ($fila['estado'] == 'RESERVADA'): ?>
                          <div class="d-flex justify-content-center gap-1">
                              <!-- Botón Check-in -->
                              <button type="button" class="btn btn-sm btn-info fw-bold shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalCheckin<?= $fila['id'] ?>" <?= $btn_disabled ?>>
                                <i class="lni lni-enter"></i> Check-in
                              </button>
                              <!-- Botón Reenviar WA -->
                              <?php if (!empty($fila['telefono'])): ?>
                                  <?php 
                                    $tel_limpio = preg_replace('/[^0-9]/', '', $fila['telefono']);
                                    
                                    // Incluir servicios adicionales si existen
                                    $servicios_wa = "";
                                    if (!empty($fila['desayuno']) || !empty($fila['garage'])) {
                                        $servs = [];
                                        if (!empty($fila['desayuno'])) $servs[] = "Desayuno";
                                        if (!empty($fila['garage'])) $servs[] = $fila['garage'] . "x Garage";
                                        $servicios_wa = "\n✨ Servicios Extras: *" . implode(" y ", $servs) . "*";
                                    }
                                    
                                    $hora_limite = !empty($fila['confirmada_at']) ? date('d/m/Y \a \l\a\s H:i', strtotime($fila['confirmada_at']) + (12 * 3600)) : date('d/m/Y \a \l\a\s H:i', strtotime($fila['created_at']) + (12 * 3600));
                                    $mensaje_wa = "🏨 *HabitApp de EPDEOR - Confirmación de Pre-Reserva*\n\nHola *" . trim($fila['nombre']) . "*,\n\nTu solicitud de reserva ha sido *APROBADA* ✅.\n\n*Detalles de tu asignación:*\n🛏️ Habitación(es): *" . ($fila['numeros_habitaciones'] ?? 'N/A') . "* (" . ($fila['tipos_habitaciones'] ?? 'N/A') . ")" . $servicios_wa . "\n💰 Total a Pagar: *Bs. " . number_format((float)($fila['total'] ?? 0), 2) . "*\n\nLe recomendamos apersonarse antes del *" . $hora_limite . "*, por lo que de no hacerlo hasta ese tiempo su reserva va a expirar. El pago y registro final se realizarán en Recepción al momento de tu llegada. Por favor, indícanos tu número de *CI/Pasaporte* o *Celular* en mostrador para ubicar tu reserva rápidamente.\n\n¡Te esperamos!";
                                    $wa_url = "https://api.whatsapp.com/send?phone=" . $tel_limpio . "&text=" . rawurlencode($mensaje_wa);
                                  ?>
                                  <a href="<?= $wa_url ?>" target="_blank" class="btn btn-sm btn-success fw-bold shadow-sm" title="Reenviar Confirmación por WhatsApp"><i class="lni lni-whatsapp"></i></a>
                              <?php endif; ?>
                          </div>
                      <?php elseif ($fila['estado'] == 'OCUPADA'): ?>
                          <div class="d-flex justify-content-center gap-1">
                              <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm" title="Extender Estadía" data-bs-toggle="modal" data-bs-target="#modalExtender<?= $fila['id'] ?>" <?= $btn_disabled ?>>
                                <i class="lni lni-calendar"></i> Extender
                              </button>
                              <button type="button" class="btn btn-sm btn-warning fw-bold shadow-sm text-dark" title="Finalizar Estadía" data-bs-toggle="modal" data-bs-target="#modalCheckout<?= $fila['id'] ?>" <?= $btn_disabled ?>>
                                <i class="lni lni-exit"></i> Check-out
                              </button>
                          </div>
                      <?php elseif ($fila['estado'] == 'FINALIZADA'): ?>
                          <form action="../reportes/comprobantes/CheckoutPdf.php" method="POST" target="_blank" style="display:inline;">
                              <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-info fw-bold shadow-sm" title="Imprimir Comprobante"><i class="lni lni-printer"></i> Recibo</button>
                          </form>
                      <?php else: // EXPIRADA ?>
                          <!-- Sin acciones para estos estados -->
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================= MODALES (Fuera de la tabla) ========================= -->
  <?php if ($resultado && $resultado->num_rows > 0): ?>
    <?php $resultado->data_seek(0); // Reiniciamos el puntero de base de datos ?>
    <?php while ($fila = $resultado->fetch_assoc()): ?>
      <?php if ($fila['estado'] == 'SOLICITADA'): ?>
      <!-- Modal de Confirmación y Pago -->
      <div class="modal fade" id="modalConfirmar<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalConfirmarLabel<?= $fila['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold text-white" id="modalConfirmarLabel<?= $fila['id'] ?>"><i class="lni lni-checkmark-circle me-1"></i> Confirmar Reserva</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-start">
                    <?php 
                    $onsubmit = "";
                    if (!empty($fila['telefono'])) {
                        $tel_limpio = preg_replace('/[^0-9]/', '', $fila['telefono']);
                        
                        // Incluir servicios adicionales si existen
                        $servicios_wa = "";
                        if (!empty($fila['desayuno']) || !empty($fila['garage'])) {
                            $servs = [];
                            if (!empty($fila['desayuno'])) $servs[] = "Desayuno";
                            if (!empty($fila['garage'])) $servs[] = $fila['garage'] . "x Garage";
                            $servicios_wa = "\n✨ Servicios Extras: *" . implode(" y ", $servs) . "*";
                        }
                        
                        $hora_limite = date('d/m/Y \a \l\a\s H:i', time() + (12 * 3600));
                        $mensaje_wa = "🏨 *HabitApp de EPDEOR - Confirmación de Pre-Reserva*\n\nHola *" . trim($fila['nombre']) . "*,\n\nTu solicitud de reserva ha sido *APROBADA* ✅.\n\n*Detalles de tu asignación:*\n🛏️ Habitación(es): *" . ($fila['numeros_habitaciones'] ?? 'N/A') . "* (" . ($fila['tipos_habitaciones'] ?? 'N/A') . ")" . $servicios_wa . "\n💰 Total a Pagar: *Bs. " . number_format((float)($fila['total'] ?? 0), 2) . "*\n\nLe recomendamos apersonarse antes del *" . $hora_limite . "*, por lo que de no hacerlo hasta ese tiempo su reserva va a expirar. El pago y registro final se realizarán en Recepción al momento de tu llegada. Por favor, indícanos tu número de *CI/Pasaporte* o *Celular* en mostrador para ubicar tu reserva rápidamente.\n\n¡Te esperamos!";
                        $wa_url = "https://web.whatsapp.com/send?phone=" . $tel_limpio . "&text=" . rawurlencode($mensaje_wa);
                        $onsubmit = "onsubmit=\"event.preventDefault(); window.open('" . $wa_url . "', '_blank'); setTimeout(() => { this.submit(); }, 500);\"";
                    }
                    ?>
                    <form action="approve.php" method="POST" <?= $onsubmit ?>>
                        <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                        
                        <div class="alert alert-warning mb-4">
                            <i class="lni lni-warning me-1"></i> Al confirmar, las habitaciones <strong><?= htmlspecialchars($fila['numeros_habitaciones'] ?? 'Sin Asignar') ?></strong> pasarán a estado <strong>RESERVADA</strong> en el mapa.
                        </div>

                        <input type="hidden" name="total_pagar" id="total_pagar_<?= $fila['id'] ?>" value="<?= $fila['total'] ?>">
                        <?php $noches_calculadas = max(1, (strtotime($fila['fecha_salida']) - strtotime($fila['fecha_ingreso'])) / 86400); ?>

                        <div class="alert alert-info mb-4 border-info">
                            <h6 class="fw-bold mb-2"><i class="lni lni-invest-monitor me-1"></i> Resumen y Edición de Servicios:</h6>
                            <p class="mb-3 small text-dark">Costo base por <strong><?= $fila['total_habitaciones'] ?? 0 ?> hab(s)</strong>. Puede modificar los servicios extra antes de aprobar.</p>
                            
                            <div class="bg-white p-3 rounded border mb-3">
                                <div class="form-check form-switch mb-2">
                                    <?php 
                                      $capacidad_reserva = intval($fila['capacidad_total'] ?? 1);
                                      $costo_desayuno = 30 * $capacidad_reserva;
                                    ?>
                                    <input class="form-check-input" type="checkbox" name="desayuno" value="1" <?= $fila['desayuno'] ? 'checked' : '' ?> onchange="toggleServicio(<?= $fila['id'] ?>, <?= $costo_desayuno ?>, <?= $noches_calculadas ?>, this)">
                                <label class="form-check-label text-secondary small fw-bold">☕ Desayuno (+Bs. <?= $costo_desayuno ?>/noche para <?= $capacidad_reserva ?> pers.)</label>
                                </div>
                                <div class="d-flex align-items-center">
                                <label class="form-label text-secondary small fw-bold mb-0 me-2">🚗 Garages (+Bs. 20/auto por noche)</label>
                                    <input type="number" class="form-control form-control-sm text-center border-secondary" name="garage" id="garage_<?= $fila['id'] ?>" value="<?= $fila['garage'] ?? 0 ?>" min="0" max="10" style="width: 70px;" data-old-value="<?= $fila['garage'] ?? 0 ?>" onchange="actualizarCantidadServicio(<?= $fila['id'] ?>, 20, <?= $noches_calculadas ?>, this)">
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2">
                                <span class="fw-bold text-dark">Total a Cobrar:</span>
                                <strong class="fs-4 text-primary" id="display_total_<?= $fila['id'] ?>">Bs. <?= number_format((float)($fila['total'] ?? 0), 2) ?></strong>
                            </div>
                        </div>

                        <p class="text-muted small mb-0"><i class="lni lni-bulb me-1"></i> <strong>Nota para Recepción:</strong> Al aprobar esta reserva solo confirmas la disponibilidad de los cuartos. El cobro, el cálculo de cambio y la foto del documento se realizarán en el paso de <strong>Check-in</strong> (cuando el cliente llegue al hotel).</p>
                        
                        <div class="modal-footer px-0 pb-0 mt-4">
                            <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success fw-bold shadow-sm">Aprobar Reserva</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
      </div>
      <?php elseif ($fila['estado'] == 'RESERVADA'): ?>
      <!-- Modal de Check-in y Cobro -->
      <div class="modal fade" id="modalCheckin<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalCheckinLabel<?= $fila['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-info text-dark">
                    <h5 class="modal-title fw-bold" id="modalCheckinLabel<?= $fila['id'] ?>"><i class="lni lni-enter me-1"></i> Consolidar Check-in </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- enctype multipart es VITAL para poder subir fotos -->
                <form action="checkin.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4 text-start">
                        <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                        <input type="hidden" name="total_pagar" id="total_pagar_<?= $fila['id'] ?>" value="<?= $fila['total'] ?>">
                        <?php 
                        $noches_calculadas = max(1, (strtotime($fila['fecha_salida']) - strtotime($fila['fecha_ingreso'])) / 86400); 
                        $id_reserva = $fila['id'];
                        $sql_habs = "SELECT h.id_habitacion, h.numero, t.nombre as tipo 
                                     FROM detalle_reserva dr 
                                     JOIN habitacion h ON dr.habitacion_id = h.id_habitacion 
                                     JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo 
                                     WHERE dr.reserva_id = $id_reserva";
                        $res_habs = $conexion->query($sql_habs);
                        $habitaciones_opciones = [];
                        if ($res_habs) {
                            while($h_row = $res_habs->fetch_assoc()) {
                                $habitaciones_opciones[] = $h_row;
                            }
                        }
                        $habitaciones_json = json_encode($habitaciones_opciones);
                        ?>

                        <div class="alert alert-light border shadow-sm mb-4">
                            <h6 class="fw-bold mb-1"><i class="lni lni-user me-1"></i> Pre-Reserva de: <?= htmlspecialchars($fila['nombre'] ?? '') ?></h6>
                            <p class="mb-0 small text-muted">Habitación(es) Asignada(s): <strong class="text-primary fs-6"><?= htmlspecialchars($fila['numeros_habitaciones'] ?? 'Sin Asignar') ?></strong> - <?= htmlspecialchars($fila['tipos_habitaciones'] ?? 'N/A') ?></p>
                        </div>

                        <div class="row">
                            <!-- Columna Izquierda: Registro de Huéspedes -->
                            <div class="col-lg-8 border-end pe-lg-4">
                                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">
                                    <i class="lni lni-users"></i> 1. Registro de Huéspedes para Parte Policial
                                </h5>
                                
                                <!-- Huésped Principal (Titular) -->
                                <div class="card border border-primary-subtle shadow-sm mb-4">
                                    <div class="card-header bg-primary bg-opacity-10 text-primary py-2 fw-bold small">
                                        <i class="lni lni-crown text-warning"></i> Huésped Principal (Titular)
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label text-secondary small fw-bold mb-1">Nombre Completo</label>
                                                <input type="text" class="form-control" name="titular_nombre" value="<?= htmlspecialchars($fila['nombre'] ?? '') ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label text-secondary small fw-bold mb-1">CI / Pasaporte</label>
                                                <input type="text" class="form-control" name="titular_documento" value="<?= htmlspecialchars($fila['ci'] ?? '') ?>" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-secondary small fw-bold mb-1">Edad</label>
                                                <input type="number" id="titular_edad_<?= $fila['id'] ?>" class="form-control" name="titular_edad" min="18" max="100" step="1" onchange="validarMenor(<?= $fila['id'] ?>, 'titular')" onblur="validarMenor(<?= $fila['id'] ?>, 'titular')" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-secondary small fw-bold mb-1">Procedencia</label>
                                                <input type="text" class="form-control" name="titular_procedencia" placeholder="Ej. Cochabamba" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-secondary small fw-bold mb-1">Nacionalidad</label>
                                                <input type="text" class="form-control" name="titular_nacionalidad" value="Boliviana" required>
                                            </div>
                                            <div class="col-md-6" id="titular_profesion_group_<?= $fila['id'] ?>">
                                                 <label class="form-label text-secondary small fw-bold mb-1">Profesión</label>
                                                 <input type="text" id="titular_profesion_<?= $fila['id'] ?>" class="form-control" name="titular_profesion" required>
                                             </div>
                                             <div class="col-md-6" id="titular_civil_group_<?= $fila['id'] ?>">
                                                 <label class="form-label text-secondary small fw-bold mb-1">Estado Civil</label>
                                                 <select id="titular_civil_<?= $fila['id'] ?>" class="form-select" name="titular_estado_civil" required>
                                                     <option value="">-- Seleccionar --</option>
                                                     <option value="SOLTERO(A)">Soltero(a)</option>
                                                     <option value="CASADO(A)">Casado(a)</option>
                                                     <option value="DIVORCIADO(A)">Divorciado(a)</option>
                                                     <option value="VIUDO(A)">Viudo(a)</option>
                                                 </select>
                                             </div>
                                             <?php if (count($habitaciones_opciones) > 1): ?>
                                             <div class="col-12">
                                                 <label class="form-label text-secondary small fw-bold mb-1">Habitación Asignada (Pieza)</label>
                                                 <select class="form-select" name="titular_habitacion" required>
                                                     <?php foreach ($habitaciones_opciones as $hab): ?>
                                                         <option value="<?= $hab['id_habitacion'] ?>">Hab. <?= $hab['numero'] ?> (<?= $hab['tipo'] ?>)</option>
                                                     <?php endforeach; ?>
                                                 </select>
                                             </div>
                                             <?php else: ?>
                                                 <input type="hidden" name="titular_habitacion" value="<?= $habitaciones_opciones[0]['id_habitacion'] ?>">
                                             <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Acompañantes -->
                                <h6 class="fw-bold text-dark d-flex justify-content-between align-items-center mb-3">
                                    <span><i class="lni lni-users me-1"></i> Acompañantes</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary shadow-sm fw-bold" onclick='agregarAcompanante(<?= $fila['id'] ?>, <?= htmlspecialchars($habitaciones_json, ENT_QUOTES, "UTF-8") ?>)'>
                                        <i class="lni lni-plus"></i> Agregar Acompañante
                                    </button>
                                </h6>
                                
                                <div id="acomp-container-<?= $fila['id'] ?>">
                                    <!-- Se insertarán aquí los acompañantes dinámicamente -->
                                </div>
                            </div>
                            
                            <!-- Columna Derecha: Control y Pago -->
                            <div class="col-lg-4 ps-lg-4">
                                <h5 class="fw-bold text-primary mb-3 border-bottom pb-2">
                                    <i class="lni lni-cog"></i> 2. Control y Pago
                                </h5>
                                
                                <h6 class="fw-bold text-dark mb-2"><i class="lni lni-camera text-info"></i> Documento Identidad</h6>
                                <div class="mb-3">
                                    <label class="form-label text-secondary small">Fotografía del CI / Pasaporte</label>
                                    <input class="form-control" type="file" name="foto_ci" accept="image/*" required>
                                    <div class="form-text text-muted small">Cargue una captura o foto clara.</div>
                                </div>
                                
                                <hr class="my-3">
                                
                                <h6 class="fw-bold text-dark mb-2"><i class="lni lni-coffee text-warning"></i> Servicios Opcionales</h6>
                                <div class="bg-light p-3 rounded border mb-3">
                                    <div class="form-check form-switch mb-2">
                                        <?php 
                                          $capacidad_reserva = intval($fila['capacidad_total'] ?? 1);
                                          $costo_desayuno = 30 * $capacidad_reserva;
                                        ?>
                                        <input class="form-check-input" type="checkbox" name="desayuno" value="1" <?= $fila['desayuno'] ? 'checked' : '' ?> onchange="toggleServicio(<?= $fila['id'] ?>, <?= $costo_desayuno ?>, <?= $noches_calculadas ?>, this)">
                                        <label class="form-check-label text-secondary small fw-bold">☕ Desayuno (+Bs. <?= $costo_desayuno ?>/noche)</label>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <label class="form-label text-secondary small fw-bold mb-0 me-2">🚗 Garages (Bs. 20/noche)</label>
                                        <input type="number" class="form-control form-control-sm text-center border-secondary" name="garage" id="garage_<?= $fila['id'] ?>" value="<?= $fila['garage'] ?? 0 ?>" min="0" max="10" style="width: 70px;" data-old-value="<?= $fila['garage'] ?? 0 ?>" onchange="actualizarCantidadServicio(<?= $fila['id'] ?>, 20, <?= $noches_calculadas ?>, this)">
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                
                                <h6 class="fw-bold text-dark mb-2"><i class="lni lni-wallet text-success"></i> Registro de Pago</h6>
                                <div class="alert alert-light border shadow-sm text-center py-2 mb-3">
                                    <span class="d-block small text-muted">Total a Cobrar</span>
                                    <strong class="fs-3 text-primary" id="display_total_<?= $fila['id'] ?>">Bs. <?= number_format((float)($fila['total'] ?? 0), 2) ?></strong>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-secondary small">Método de Pago</label>
                                    <select class="form-select bg-light" name="tipo_pago" required>
                                        <option value="EFECTIVO" selected>💵 Efectivo</option>
                                        <option value="QR">📱 Transferencia QR</option>
                                        <option value="DEPOSITO">🏦 Depósito Bancario</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label text-secondary small fw-bold"><i class="lni lni-empty-file text-primary"></i> Nº de Factura Externa</label>
                                    <input type="text" class="form-control fw-bold border-primary" name="num_factura" placeholder="Ej. F-10023" required>
                                </div>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label text-secondary small">Recibido (Bs.)</label>
                                        <input type="number" step="0.01" min="<?= $fila['total'] ?>" class="form-control text-success fw-bold" name="monto_recibido" id="recibido_<?= $fila['id'] ?>" placeholder="0.00" oninput="calcularCambio(<?= $fila['id'] ?>)" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-secondary small">Cambio (Bs.)</label>
                                        <input type="text" class="form-control bg-light text-success fw-bold" name="cambio" id="cambio_<?= $fila['id'] ?>" value="0.00" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light mt-2">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-info text-dark fw-bold shadow-sm"><i class="lni lni-key me-1"></i> Finalizar Check-in y Entregar Llaves</button>
                    </div>
                </form>
            </div>
        </div>
      </div>
      <?php elseif ($fila['estado'] == 'OCUPADA'): ?>
      <!-- Modal de Check-out -->
      <div class="modal fade" id="modalCheckout<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalCheckoutLabel<?= $fila['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="modalCheckoutLabel<?= $fila['id'] ?>"><i class="lni lni-exit me-1"></i> Procesar Check-out</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="checkout.php" method="POST">
                    <div class="modal-body p-4 text-start">
                        <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                        
                        <div class="alert alert-light border shadow-sm mb-4">
                            <h6 class="fw-bold mb-1"><i class="lni lni-user me-1"></i> Huésped: <?= htmlspecialchars($fila['nombre']) ?></h6>
                            <p class="mb-0 small text-muted">Habitación(es): <strong><?= htmlspecialchars($fila['numeros_habitaciones']) ?></strong></p>
                        </div>

                        <h6 class="fw-bold text-dark mb-3"><i class="lni lni-cart me-1"></i> Consumos Extra / Minibar</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Monto Extra a Cobrar (Bs.)</label>
                                <input type="number" step="0.01" min="0" class="form-control bg-light text-danger fw-bold" name="monto_extra" value="0.00" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small">Método de Pago</label>
                                <select class="form-select bg-light" name="tipo_pago_extra">
                                    <option value="EFECTIVO" selected>💵 Efectivo</option>
                                    <option value="QR">📱 Transferencia QR</option>
                                    <option value="DEPOSITO">🏦 Depósito Bancario</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Detalle de los extras (Opcional)</label>
                            <textarea class="form-control bg-light" name="detalle_extra" rows="2" placeholder="Ej. 2x Botellas de agua, Snacks, Daño en toalla..."></textarea>
                        </div>

                        <p class="text-muted small mb-0"><i class="lni lni-bulb text-warning me-1"></i> Al finalizar, la reserva se cerrará y las habitaciones volverán a estar <strong>DISPONIBLES</strong> de inmediato en el mapa.</p>
                    </div>
                    <div class="modal-footer bg-light mt-2">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning text-dark fw-bold shadow-sm"><i class="lni lni-exit me-1"></i> Finalizar Estadía</button>
                    </div>
                </form>
            </div>
        </div>
      </div>

      <!-- Modal Extender Estadía -->
      <div class="modal fade" id="modalExtender<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalExtenderLabel<?= $fila['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="modalExtenderLabel<?= $fila['id'] ?>"><i class="lni lni-calendar me-1"></i> Extender Estadía</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="extend.php" method="POST">
                    <div class="modal-body p-4 text-start">
                        <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                        <?php
                           // Calcular tarifa diaria incluyendo extras (desayuno y garage)
                           $capacidad_reserva = intval($fila['capacidad_total'] ?? 1);
                           $costo_desayuno = $fila['desayuno'] ? (30 * $capacidad_reserva) : 0;
                           $costo_garage = ($fila['garage'] ?? 0) * 20;
                           $tarifa_diaria = $fila['tarifa_noche'] + $costo_desayuno + $costo_garage;
                        ?>
                        <input type="hidden" id="tarifa_diaria_<?= $fila['id'] ?>" value="<?= $tarifa_diaria ?>">
                        
                        <div class="alert alert-light border shadow-sm mb-4">
                            <h6 class="fw-bold mb-1"><i class="lni lni-user me-1"></i> Huésped: <?= htmlspecialchars($fila['nombre']) ?></h6>
                            <p class="mb-0 small text-muted">Habitaciones: <strong><?= htmlspecialchars($fila['numeros_habitaciones']) ?></strong></p>
                            <hr class="my-2">
                            <p class="mb-0 small">Check-out actual: <strong class="text-danger"><?= date('d/m/Y', strtotime($fila['fecha_salida'])) ?></strong></p>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary fw-bold small">Nueva Fecha de Salida</label>
                            <input type="date" class="form-control bg-light input-extender" name="nueva_fecha_salida" data-id="<?= $fila['id'] ?>" data-old-date="<?= $fila['fecha_salida'] ?>" min="<?= date('Y-m-d', strtotime($fila['fecha_salida'] . ' +1 day')) ?>" required>
                        </div>

                        <div class="alert alert-info border-info text-center py-2 mb-3">
                            <span class="d-block small text-muted mb-1">Monto a cobrar por las noches extra:</span>
                            <strong class="fs-3 text-primary" id="display_extra_<?= $fila['id'] ?>">Bs. 0.00</strong>
                            <input type="hidden" name="monto_cobrar" id="monto_cobrar_<?= $fila['id'] ?>" value="0">
                        </div>

                        <div class="row g-2">
                            <div class="col-12"><label class="form-label text-secondary small">Método de Pago</label><select class="form-select bg-light" name="tipo_pago"><option value="EFECTIVO" selected>💵 Efectivo</option><option value="QR">📱 Transferencia QR</option><option value="DEPOSITO">🏦 Depósito Bancario</option></select></div>
                            <div class="col-6"><label class="form-label text-secondary small">Efectivo Recibido</label><input type="number" step="0.01" class="form-control text-success fw-bold" name="monto_recibido" id="recibido_ext_<?= $fila['id'] ?>" placeholder="0.00" oninput="calcularCambioExt(<?= $fila['id'] ?>)" required></div>
                            <div class="col-6"><label class="form-label text-secondary small">Cambio a Devolver</label><input type="text" class="form-control bg-light text-success fw-bold" name="cambio" id="cambio_ext_<?= $fila['id'] ?>" value="0.00" readonly></div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light mt-2">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm"><i class="lni lni-save me-1"></i> Cobrar y Extender</button>
                    </div>
                </form>
            </div>
        </div>
      </div>
      <?php endif; ?>
    <?php endwhile; ?>
  <?php endif; ?>

  <!-- JS de Bootstrap -->
  <script src="../assets/js/bootstrap.min.js"></script>
  
  <!-- JS de jQuery y DataTables -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  
  <!-- Librerías para Exportar a Excel -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

  <script src="../assets/js/habitapp.js?v=<?= time() ?>"></script>

  <!-- Auto-abrir modal desde el Mapa de Habitaciones -->
  <?php if (isset($_GET['open_modal']) && isset($_GET['id'])): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        let modalId = '';
        <?php if ($_GET['open_modal'] == 'checkin'): ?> modalId = '#modalCheckin<?= intval($_GET['id']) ?>'; <?php endif; ?>
        <?php if ($_GET['open_modal'] == 'checkout'): ?> modalId = '#modalCheckout<?= intval($_GET['id']) ?>'; <?php endif; ?>
        
        if (modalId && document.querySelector(modalId)) {
            new bootstrap.Modal(document.querySelector(modalId)).show();
        }
    });
  </script>
  <?php endif; ?>
</body>
</html>