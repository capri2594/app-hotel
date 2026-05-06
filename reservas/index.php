<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

// PARCHE AUTOMÁTICO: Agregar columna confirmada_at si no existe
$conexion->query("ALTER TABLE reservas ADD COLUMN IF NOT EXISTS confirmada_at TIMESTAMP NULL AFTER created_at");

// LIMPIEZA AUTOMÁTICA: Cancelar reservas web pendientes con más de 12 horas
$conexion->query("UPDATE reservas SET estado = 'CANCELADA' WHERE estado = 'PENDIENTE' AND created_at <= NOW() - INTERVAL 12 HOUR");
// LIMPIEZA AUTOMÁTICA: Cancelar reservas confirmadas (Reservadas) que no hagan check-in en 12 horas
$conexion->query("UPDATE reservas SET estado = 'CANCELADA' WHERE estado = 'CONFIRMADA' AND confirmada_at <= NOW() - INTERVAL 12 HOUR");

// Obtener conteos para las pestañas
$sql_counts = "SELECT estado, COUNT(*) as total FROM reservas GROUP BY estado";
$res_counts = $conexion->query($sql_counts);
$counts = ['PENDIENTE' => 0, 'CONFIRMADA' => 0, 'HOSPEDADO' => 0, 'FINALIZADA' => 0, 'CANCELADA' => 0];
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
               COALESCE(SUM(t.capacidad), 1) as capacidad_total
        FROM reservas r 
        LEFT JOIN detalle_reserva dr ON r.id = dr.reserva_id
        LEFT JOIN habitacion h ON dr.habitacion_id = h.id_habitacion
        LEFT JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo
        GROUP BY r.id
        ORDER BY r.id DESC";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<!-- CSS de DataTables para Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />

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
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="PENDIENTE">
                        ⏳ Pendientes <span class="badge bg-primary text-white ms-1 rounded-pill"><?= $counts['PENDIENTE'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="CONFIRMADA">
                        ✅ Reservadas <span class="badge bg-warning text-dark ms-1 rounded-pill"><?= $counts['CONFIRMADA'] ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link bg-white border text-dark tab-filtro" href="#" data-estado="HOSPEDADO">
                        🛏️ Ocupadas <span class="badge bg-danger text-white ms-1 rounded-pill"><?= $counts['HOSPEDADO'] ?></span>
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
                    <td><?= !empty($fila['fecha_ingreso']) ? date('d/m/Y', strtotime($fila['fecha_ingreso'])) : 'N/A' ?></td>
                    <td><?= !empty($fila['fecha_salida']) ? date('d/m/Y', strtotime($fila['fecha_salida'])) : 'N/A' ?></td>
                    <td class="fw-bold text-success"><?= number_format((float)($fila['total'] ?? 0), 2) ?></td>
                    <td>
                      <?php 
                        $badge_class = 'bg-secondary';
                        $estado_mostrar = $fila['estado'];
                        if($fila['estado'] == 'PENDIENTE') $badge_class = 'bg-primary';
                        if($fila['estado'] == 'CONFIRMADA') { $badge_class = 'bg-warning text-dark'; $estado_mostrar = 'RESERVADA'; }
                        if($fila['estado'] == 'HOSPEDADO') { $badge_class = 'bg-danger'; $estado_mostrar = 'OCUPADA'; }
                        if($fila['estado'] == 'FINALIZADA') $badge_class = 'bg-success';
                        if($fila['estado'] == 'CANCELADA') $badge_class = 'bg-dark';
                      ?>
                      <span class="badge <?= $badge_class ?>"><?= $estado_mostrar ?></span>
                      <?php if($fila['estado'] == 'CONFIRMADA' && !empty($fila['confirmada_at'])): ?>
                          <?php $expira = strtotime($fila['confirmada_at']) + (12 * 3600); ?>
                          <div class="text-danger fw-bold mt-1 countdown-timer" style="font-size: 0.75rem;" data-expire="<?= $expira ?>">
                              <i class="lni lni-timer"></i> <span>Calculando...</span>
                          </div>
                      <?php endif; ?>
                    </td>
                    <td class="text-center pe-4">
                      <?php if ($fila['estado'] == 'PENDIENTE'): ?>
                          <!-- Botón Confirmar -->
                          <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalConfirmar<?= $fila['id'] ?>">
                            <i class="lni lni-checkmark"></i> Confirmar
                          </button>
                      <?php elseif ($fila['estado'] == 'CONFIRMADA'): ?>
                          <div class="d-flex justify-content-center gap-1">
                              <!-- Botón Check-in -->
                              <button type="button" class="btn btn-sm btn-info fw-bold shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalCheckin<?= $fila['id'] ?>">
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
                      <?php else: ?>
                          <button class="btn btn-sm btn-light text-muted" disabled>Sin acciones</button>
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
      <?php if ($fila['estado'] == 'PENDIENTE'): ?>
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
                                    <label class="form-check-label text-secondary small fw-bold">☕ Desayuno (+Bs. <?= $costo_desayuno ?>/noche)</label>
                                </div>
                                <div class="d-flex align-items-center">
                                    <label class="form-label text-secondary small fw-bold mb-0 me-2">🚗 Garages (+Bs. 20/noche)</label>
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
      <?php elseif ($fila['estado'] == 'CONFIRMADA'): ?>
      <!-- Modal de Check-in y Cobro -->
      <div class="modal fade" id="modalCheckin<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalCheckinLabel<?= $fila['id'] ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
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
                        <?php $noches_calculadas = max(1, (strtotime($fila['fecha_salida']) - strtotime($fila['fecha_ingreso'])) / 86400); ?>

                        <div class="alert alert-light border shadow-sm mb-4">
                            <h6 class="fw-bold mb-1"><i class="lni lni-user me-1"></i> Huésped: <?= htmlspecialchars($fila['nombre'] ?? '') ?></h6>
                            <p class="mb-0 small text-muted">Habitación(es) Asignada(s): <strong class="text-primary fs-6"><?= htmlspecialchars($fila['numeros_habitaciones'] ?? 'Sin Asignar') ?></strong> - <?= htmlspecialchars($fila['tipos_habitaciones'] ?? 'N/A') ?></p>
                        </div>

                        <div class="row">
                            <div class="col-md-6 border-end pe-4">
                                <h6 class="fw-bold mb-3 text-dark"><i class="lni lni-camera me-1"></i> 1. Documento Identidad</h6>
                                <div class="mb-3">
                                    <label class="form-label text-secondary small">Fotografía del CI / Pasaporte / DNI Extranjero</label>
                                    <input class="form-control" type="file" name="foto_ci" accept="image/*" required>
                                    <div class="form-text">Asegúrese de que los datos sean legibles.</div>
                                </div>
                                <h6 class="fw-bold mt-4 mb-2 text-dark"><i class="lni lni-star me-1 text-warning"></i> 2. Servicios Opcionales</h6>
                                <p class="text-muted small mb-2">Puede agregar o quitar servicios antes de cobrar:</p>
                                <div class="form-check form-switch mb-2">
                                    <?php 
                                      $capacidad_reserva = intval($fila['capacidad_total'] ?? 1);
                                      $costo_desayuno = 30 * $capacidad_reserva;
                                    ?>
                                    <input class="form-check-input" type="checkbox" name="desayuno" value="1" <?= $fila['desayuno'] ? 'checked' : '' ?> onchange="toggleServicio(<?= $fila['id'] ?>, <?= $costo_desayuno ?>, <?= $noches_calculadas ?>, this)">
                                    <label class="form-check-label text-secondary small fw-bold">☕ Desayuno (+Bs. <?= $costo_desayuno ?>/noche)</label>
                                </div>
                                <div class="d-flex align-items-center mt-2">
                                    <label class="form-label text-secondary small fw-bold mb-0 me-2">🚗 Garages (+Bs. 20/noche)</label>
                                    <input type="number" class="form-control form-control-sm text-center border-secondary" name="garage" id="garage_<?= $fila['id'] ?>" value="<?= $fila['garage'] ?? 0 ?>" min="0" max="10" style="width: 70px;" data-old-value="<?= $fila['garage'] ?? 0 ?>" onchange="actualizarCantidadServicio(<?= $fila['id'] ?>, 20, <?= $noches_calculadas ?>, this)">
                                </div>
                            </div>
                            <div class="col-md-6 ps-4">
                                <h6 class="fw-bold mb-3 text-dark"><i class="lni lni-wallet me-1"></i> 3. Registro de Pago</h6>
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
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label text-secondary small">Efectivo Recibido</label><input type="number" step="0.01" min="<?= $fila['total'] ?>" class="form-control text-success fw-bold" name="monto_recibido" id="recibido_<?= $fila['id'] ?>" placeholder="0.00" oninput="calcularCambio(<?= $fila['id'] ?>)" required></div>
                                    <div class="col-6"><label class="form-label text-secondary small">Cambio a Devolver</label><input type="text" class="form-control bg-light text-success fw-bold" name="cambio" id="cambio_<?= $fila['id'] ?>" value="0.00" readonly></div>
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
      <?php elseif ($fila['estado'] == 'HOSPEDADO'): ?>
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
      <?php endif; ?>
    <?php endwhile; ?>
  <?php endif; ?>

  <!-- JS de Bootstrap -->
  <script src="../assets/js/bootstrap.min.js"></script>
  
  <!-- JS de jQuery y DataTables -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="../assets/js/habitapp.js"></script>

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