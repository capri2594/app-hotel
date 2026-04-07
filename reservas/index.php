<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

include '../conexion.php';

// Obtener todas las reservas ordenadas por las más recientes
$sql = "SELECT r.*, 
               GROUP_CONCAT(h.numero SEPARATOR ', ') as numeros_habitaciones,
               GROUP_CONCAT(DISTINCT t.nombre SEPARATOR ', ') as tipos_habitaciones,
               COUNT(dr.habitacion_id) as total_habitaciones
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

    <div class="card shadow-sm border-0 rounded-3">
      <div class="card-body p-4">
        <div class="table-responsive">
          <table id="tablaReservas" class="table table-hover align-middle mb-0" style="width: 100%;">
            <thead class="table-light text-secondary">
              <tr>
                <th class="ps-4">ID</th>
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
                    <td class="ps-4 fw-bold text-muted">#<?= $fila['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($fila['nombre']) ?></strong><br>
                        <small class="text-muted"><i class="lni lni-phone me-1"></i><?= htmlspecialchars($fila['telefono']) ?></small>
                    </td>
                    <td>
                        <span class="badge bg-dark fs-6"><?= htmlspecialchars($fila['numeros_habitaciones']) ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars($fila['tipos_habitaciones']) ?> (<?= $fila['total_habitaciones'] ?> habs)</small>
                    </td>
                    <td><?= date('d/m/Y', strtotime($fila['fecha_ingreso'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($fila['fecha_salida'])) ?></td>
                    <td class="fw-bold text-success"><?= number_format($fila['total'], 2) ?></td>
                    <td>
                      <?php 
                        $badge_class = 'bg-secondary';
                        if($fila['estado'] == 'PENDIENTE') $badge_class = 'bg-warning text-dark';
                        if($fila['estado'] == 'CONFIRMADA') $badge_class = 'bg-primary';
                        if($fila['estado'] == 'FINALIZADA') $badge_class = 'bg-success';
                        if($fila['estado'] == 'CANCELADA') $badge_class = 'bg-danger';
                      ?>
                      <span class="badge <?= $badge_class ?>"><?= $fila['estado'] ?></span>
                    </td>
                    <td class="text-center pe-4">
                      <?php if ($fila['estado'] == 'PENDIENTE'): ?>
                          <!-- Botón Confirmar -->
                          <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalConfirmar<?= $fila['id'] ?>">
                            <i class="lni lni-checkmark"></i> Confirmar
                          </button>
                      <?php else: ?>
                          <button class="btn btn-sm btn-light text-muted" disabled>Sin acciones</button>
                      <?php endif; ?>
                    </td>
                  </tr>

                  <?php if ($fila['estado'] == 'PENDIENTE'): ?>
                  <!-- Modal de Confirmación y Pago -->
                  <div class="modal fade" id="modalConfirmar<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalConfirmarLabel<?= $fila['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold text-white" id="modalConfirmarLabel<?= $fila['id'] ?>"><i class="lni lni-checkmark-circle me-1"></i> Confirmar Reserva #<?= $fila['id'] ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-start">
                                <form action="confirmar.php" method="POST">
                                    <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                                    
                                    <div class="alert alert-warning mb-4">
                                        <i class="lni lni-warning me-1"></i> Al confirmar, las habitaciones <strong><?= htmlspecialchars($fila['numeros_habitaciones']) ?></strong> pasarán a estado <strong>RESERVADA</strong> en el mapa.
                                    </div>

                                    <div class="alert alert-info mb-4">
                                        <h6 class="fw-bold mb-1"><i class="lni lni-invest-monitor me-1"></i> Resumen de Estadía:</h6>
                                        <p class="mb-0 small text-dark">El costo total calculado por <strong><?= $fila['total_habitaciones'] ?> hab(s)</strong> es de <strong class="fs-6 text-primary">Bs. <?= number_format($fila['total'], 2) ?></strong>.</p>
                                    </div>

                                    <h5 class="fw-bold fs-6 mb-3 text-dark"><i class="lni lni-wallet text-success me-1"></i> Registro de Pago (Adelanto/Total)</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-secondary">Método de Pago</label>
                                        <select class="form-select bg-light" name="tipo_pago" required>
                                            <option value="EFECTIVO" selected>💵 Efectivo</option>
                                            <option value="DEPOSITO">🏦 Depósito Bancario</option>
                                            <option value="QR">📱 Transferencia QR</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold text-secondary">Monto a Registrar (Bs.)</label>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-lg bg-light text-success fw-bold" name="monto" value="0.00" required>
                                        <div class="form-text">Si no deja adelanto, mantenga el monto en 0.00</div>
                                    </div>
                                    
                                    <div class="modal-footer px-0 pb-0 mt-4">
                                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-success fw-bold shadow-sm">Confirmar y Guardar</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                  </div>
                  <?php endif; ?>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">No hay reservas registradas.</td>
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
  
  <!-- JS de jQuery y DataTables -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="../assets/js/habitapp.js"></script>
  
  <script>
    $(document).ready(function() {
        $('#tablaReservas').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            },
            "order": [[ 0, "desc" ]], // Ordenar por ID descendente (las más nuevas primero)
            "columnDefs": [{ "orderable": false, "targets": 7 }]
        });
    });
  </script>
</body>
</html>