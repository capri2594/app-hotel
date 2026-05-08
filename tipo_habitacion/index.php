<?php
session_start();

// Validar si el usuario está logueado y tiene rol gerencial
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['SuperAdmin', 'Administrador'])) {
    header("Location: ../index.php");
    exit;
}

// Evitar que el navegador guarde caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../conexion.php';

// Obtener los tipos de habitación y contar cuartos físicos asignados
$sql = "SELECT t.*, (SELECT COUNT(*) FROM habitacion h WHERE h.id_tipo = t.id_tipo) as total_habitaciones 
        FROM tipo_habitacion t ORDER BY t.id_tipo ASC";
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
    
    <!-- Alertas Flash -->
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
      <h2 class="fw-bold text-dark"><i class="lni lni-tag me-2"></i>Tipos de Habitación</h2>
      <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearTipo">
        <i class="lni lni-circle-plus"></i> Nuevo Tipo
      </button>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
      <div class="card-body p-4">
        <div class="table-responsive">
          <table id="tablaTipos" class="table table-hover align-middle mb-0" style="width: 100%;">
            <thead class="table-light text-secondary">
              <tr>
                <th class="ps-4">Código</th>
                <th>Nombre del Tipo</th>
                <th class="text-center">Capacidad</th>
                <th class="text-end">Precio Base (Bs.)</th>
                <th class="text-center">Habs. Asignadas</th>
                <th class="text-center pe-4">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                  <tr>
                    <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($fila['codigo']) ?></td>
                    <td class="fw-bold text-dark"><?= htmlspecialchars($fila['nombre']) ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><i class="lni lni-users me-1"></i><?= htmlspecialchars($fila['capacidad']) ?> Pax</span>
                    </td>
                    <td class="text-end fw-bold text-success">Bs. <?= number_format($fila['precio'], 2) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $fila['total_habitaciones'] > 0 ? 'bg-info text-dark' : 'bg-light text-secondary border' ?>"><?= $fila['total_habitaciones'] ?> cuartos</span>
                    </td>
                    <td class="text-center pe-4">
                      <button type="button" class="btn btn-sm btn-warning fw-bold shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalEditarTipo<?= $fila['id_tipo'] ?>">
                        <i class="lni lni-pencil"></i> Editar
                      </button>
                      <button type="button" class="btn btn-sm btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEliminarTipo<?= $fila['id_tipo'] ?>">
                        <i class="lni lni-trash"></i> Eliminar
                      </button>
                    </td>
                  </tr>

                  <!-- Modal Editar Tipo -->
                  <div class="modal fade" id="modalEditarTipo<?= $fila['id_tipo'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title fw-bold"><i class="lni lni-pencil-alt me-1"></i> Editar Tipo de Habitación</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="update.php" method="POST">
                                <div class="modal-body p-4 text-start">
                                    <input type="hidden" name="id_tipo" value="<?= $fila['id_tipo'] ?>">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label fw-bold text-dark small">Código</label>
                                            <input type="text" class="form-control bg-light text-uppercase" name="codigo" value="<?= htmlspecialchars($fila['codigo']) ?>" maxlength="5" required>
                                        </div>
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label fw-bold text-dark small">Nombre</label>
                                            <input type="text" class="form-control bg-light" name="nombre" value="<?= htmlspecialchars($fila['nombre']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold text-dark small">Capacidad (Personas)</label>
                                            <input type="number" class="form-control bg-light" name="capacidad" min="1" max="20" value="<?= htmlspecialchars($fila['capacidad']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold text-dark small">Precio Base (Bs.)</label>
                                            <input type="number" step="0.01" min="0" class="form-control bg-light text-success fw-bold" name="precio" value="<?= htmlspecialchars($fila['precio']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="alert alert-info py-2 px-3 mt-2 mb-0 small border-info">
                                        <i class="lni lni-bulb me-1"></i> Si modificas el precio, solo afectará a las nuevas reservas.
                                    </div>
                                </div>
                                <div class="modal-footer bg-light mt-0">
                                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-warning fw-bold text-dark shadow-sm">Guardar Cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                  </div>

                  <!-- Modal Eliminar Tipo -->
                  <div class="modal fade" id="modalEliminarTipo<?= $fila['id_tipo'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title fw-bold"><i class="lni lni-trash me-1"></i> Confirmar Eliminación</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <i class="lni lni-warning text-danger mb-3" style="font-size: 4rem;"></i>
                                <p class="fs-5 text-dark mb-1">¿Estás seguro de eliminar el tipo <br><strong><?= htmlspecialchars($fila['nombre']) ?></strong>?</p>
                                <?php if($fila['total_habitaciones'] > 0): ?>
                                    <div class="alert alert-danger mt-3 border-danger text-start">
                                        <i class="lni lni-ban me-1"></i> <strong>¡Atención!</strong> Hay <strong><?= $fila['total_habitaciones'] ?> habitaciones</strong> usando este tipo. Debes reasignarlas a otra categoría en el mapa antes de poder eliminar esta.
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small mt-2">Esta acción no se puede deshacer.</p>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer bg-light justify-content-center mt-0">
                                <form action="delete.php" method="POST">
                                    <input type="hidden" name="id_tipo" value="<?= $fila['id_tipo'] ?>">
                                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                                    <?php if($fila['total_habitaciones'] == 0): ?>
                                    <button type="submit" class="btn btn-danger fw-bold shadow-sm">Sí, Eliminar</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                  </div>

                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Crear Tipo -->
  <div class="modal fade" id="modalCrearTipo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="lni lni-circle-plus me-1"></i> Crear Tipo de Habitación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="store.php" method="POST">
                <div class="modal-body p-4 text-start">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold text-dark small">Código</label>
                            <input type="text" class="form-control bg-light text-uppercase" name="codigo" placeholder="Ej. SGL" maxlength="5" required>
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label fw-bold text-dark small">Nombre</label>
                            <input type="text" class="form-control bg-light" name="nombre" placeholder="Ej. Habitación Simple" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-dark small">Capacidad (Personas)</label>
                            <input type="number" class="form-control bg-light" name="capacidad" min="1" max="20" value="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-dark small">Precio Base (Bs.)</label>
                            <input type="number" step="0.01" min="0" class="form-control bg-light text-success fw-bold" name="precio" placeholder="0.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light mt-0">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm">Guardar Tipo</button>
                </div>
            </form>
        </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  
  <!-- Librerías para Exportar a Excel -->
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

  <script src="../assets/js/bootstrap.min.js"></script>
  <script src="../assets/js/habitapp.js?v=<?= time() ?>"></script>

  <script>
    $(document).ready(function() {
        $('#tablaTipos').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
            "columnDefs": [{ "orderable": false, "targets": 5 }], // Excluir "Acciones" de ordenamiento
            "dom": "<'row mb-3'<'col-md-6 d-flex align-items-center'B><'col-md-6'f>>" +
                   "<'row'<'col-sm-12'tr>>" +
                   "<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
            "buttons": [{
                extend: 'excelHtml5',
                text: '<i class="lni lni-empty-file"></i> Exportar a Excel',
                className: 'btn btn-success btn-sm fw-bold shadow-sm',
                title: 'Tipos_Habitacion_HabitApp',
                exportOptions: { columns: [0, 1, 2, 3, 4] } // Excluye la columna de acciones
            }]
        });
    });
  </script>
</body>
</html>