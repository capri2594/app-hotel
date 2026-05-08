<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Evitar que el navegador guarde caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../conexion.php';

// Consulta para obtener el historial
$sql = "SELECT hm.*, h.numero as numero_habitacion, u.usuario as registrado_por, f.nombre, f.paterno
        FROM historial_mantenimiento hm
        INNER JOIN habitacion h ON hm.habitacion_id = h.id_habitacion
        INNER JOIN usuario u ON hm.usuario_id = u.id
        INNER JOIN funcionario f ON u.funcionario_id = f.id
        ORDER BY hm.fecha_inicio DESC";
$resultado = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<!-- CSS de DataTables para Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
<!-- CSS para Botones de DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" />

<body class="bg-light py-4">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold text-dark"><i class="lni lni-timer me-2"></i>Historial de Mantenimientos</h2>
      <a href="index.php" class="btn btn-outline-primary fw-bold shadow-sm">
        <i class="lni lni-map"></i> Volver al Mapa
      </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
      <div class="card-body p-4">
        <div class="table-responsive">
          <table id="tablaHistorial" class="table table-hover align-middle mb-0" style="width: 100%;">
            <thead class="table-light text-secondary">
              <tr>
                <th class="ps-4">Habitación</th>
                <th>Motivo de Bloqueo</th>
                <th>Estado</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Detalle de Resolución</th>
                <th>Registrado Por</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                  <tr>
                    <td class="ps-4 fw-bold text-dark">Hab. <?= htmlspecialchars($fila['numero_habitacion']) ?></td>
                    <td><?= htmlspecialchars($fila['motivo']) ?></td>
                    <td>
                      <?php if ($fila['estado'] == 'EN_PROCESO'): ?>
                        <span class="badge bg-secondary">EN PROCESO</span>
                      <?php else: ?>
                        <span class="badge bg-success">FINALIZADO</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($fila['fecha_inicio'])) ?></td>
                    <td><?= $fila['fecha_fin'] ? date('d/m/Y H:i', strtotime($fila['fecha_fin'])) : '<span class="text-muted">Pendiente...</span>' ?></td>
                    <td><?= $fila['detalle_resolucion'] ? htmlspecialchars($fila['detalle_resolucion']) : '<span class="text-muted">N/A</span>' ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars($fila['nombre'] . ' ' . $fila['paterno']) ?></small></td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- JS de jQuery y DataTables con Botones -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="../assets/js/bootstrap.min.js"></script>
  <script src="../assets/js/habitapp.js?v=<?= time() ?>"></script>
  
  <script>
    $(document).ready(function() {
        $('#tablaHistorial').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
            "order": [[ 3, "desc" ]], // Ordenar por fecha de inicio descendente
            "dom": "<'row mb-3'<'col-md-6 d-flex align-items-center'B><'col-md-6'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
            "buttons": [{
                extend: 'excelHtml5',
                text: '<i class="lni lni-empty-file"></i> Exportar a Excel',
                className: 'btn btn-success btn-sm fw-bold shadow-sm',
                title: 'Historial_Mantenimiento_HabitApp'
            }]
        });
    });
  </script>
</body>
</html>