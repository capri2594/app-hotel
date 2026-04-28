<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Evitar que el navegador guarde caché de esta página
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../conexion.php';

// Consulta para obtener los funcionarios
$sql = "SELECT f.*, r.nombre as rol_nombre 
        FROM funcionario f 
        INNER JOIN rol r ON f.rol_id = r.id 
        WHERE r.nombre != 'SuperAdmin'
        ORDER BY f.id DESC";
$resultado = $conexion->query($sql);

// Obtener los roles disponibles para el modal de creación
$sql_roles = "SELECT * FROM rol WHERE nombre != 'SuperAdmin' ORDER BY id ASC";
$res_roles = $conexion->query($sql_roles);
$roles = [];
while ($r = $res_roles->fetch_assoc()) {
    $roles[] = $r;
}
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
            <?= htmlspecialchars($_GET['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold text-dark">Gestión de Funcionarios</h2>
      <a href="#" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearFuncionario">
        <i class="lni lni-circle-plus"></i> Nuevo Funcionario
      </a>
    </div>

    <div class="card shadow-sm border-0 rounded-3">
      <div class="card-body p-4">
        <div class="table-responsive">
          <table id="tablaFuncionarios" class="table table-hover align-middle mb-0" style="width: 100%;">
            <thead class="table-light text-secondary">
              <tr>
                <th class="ps-4">#</th>
                <th>Nombre</th>
                <th>Paterno</th>
                <th>Materno</th>
                <th>CI</th>
                <th>Cargo</th>
                <th>Estado</th>
                <th class="text-center pe-4">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php $contador = 1; ?>
                <?php while ($fila = $resultado->fetch_assoc()): ?>
                  <tr>
                    <td class="ps-4 fw-bold"><?= $contador++ ?></td>
                    <td><?= htmlspecialchars($fila['nombre']) ?></td>
                    <td><?= htmlspecialchars($fila['paterno']) ?></td>
                    <td><?= htmlspecialchars($fila['materno']) ?></td>
                    <td><?= htmlspecialchars($fila['ci']) ?></td>
                    <td>
                        <span class="badge bg-secondary"><?= htmlspecialchars($fila['rol_nombre']) ?></span>
                    </td>
                    <td>
                      <?php if ($fila['estado'] == 'ACTIVO'): ?>
                        <span class="badge bg-success text-white">ACTIVO</span>
                      <?php else: ?>
                        <span class="badge bg-danger text-white">INACTIVO</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center pe-4">
                      <!-- Botones de Acción (Update / Delete) -->
                      <button type="button" class="btn btn-sm btn-warning fw-bold text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEditarFuncionario<?= $fila['id'] ?>">
                        <i class="lni lni-pencil"></i> Editar
                      </button>
                      <?php if ($fila['estado'] == 'ACTIVO'): ?>
                          <!-- Botón Eliminar (Rojo) -->
                          <button type="button" class="btn btn-sm btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalEliminarFuncionario<?= $fila['id'] ?>">
                            <i class="lni lni-trash"></i> Eliminar
                          </button>
                      <?php else: ?>
                          <!-- Botón Activar (Verde) -->
                          <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalActivarFuncionario<?= $fila['id'] ?>">
                            <i class="lni lni-checkmark-circle"></i> Activar
                          </button>
                      <?php endif; ?>
                    </td>
                  </tr>

                  <!-- Modal para Editar Funcionario (Amarillo) -->
                  <div class="modal fade" id="modalEditarFuncionario<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalEditarLabel<?= $fila['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-warning text-dark">
                                <h5 class="modal-title fw-bold" id="modalEditarLabel<?= $fila['id'] ?>"><i class="lni lni-pencil-alt me-1"></i> Editar Funcionario</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-start">
                                <form action="update.php" method="POST">
                                    <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold text-dark">Nombre(s)</label>
                                            <input type="text" class="form-control bg-light" name="nombre" value="<?= htmlspecialchars($fila['nombre']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold text-dark">A. Paterno</label>
                                            <input type="text" class="form-control bg-light" name="paterno" value="<?= htmlspecialchars($fila['paterno']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold text-dark">A. Materno</label>
                                            <input type="text" class="form-control bg-light" name="materno" value="<?= htmlspecialchars($fila['materno']) ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold text-dark">Carnet de Identidad (CI)</label>
                                            <input type="text" class="form-control bg-light" name="ci" value="<?= htmlspecialchars($fila['ci']) ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold text-dark">Teléfono</label>
                                            <input type="text" class="form-control bg-light" name="telefono" value="<?= htmlspecialchars($fila['telefono']) ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-bold text-dark">Rol / Cargo</label>
                                            <select class="form-select bg-light" name="rol_id" required>
                                                <?php foreach($roles as $rol): ?>
                                                    <option value="<?= $rol['id'] ?>" <?= $rol['id'] == $fila['rol_id'] ? 'selected' : '' ?>><?= htmlspecialchars($rol['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer mt-4 pb-0 px-0">
                                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                                        <button type="submit" class="btn btn-warning fw-bold text-dark">Guardar Cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                  </div>

                  <?php if ($fila['estado'] == 'ACTIVO'): ?>
                  <!-- Modal para Eliminar Funcionario (Rojo) -->
                  <div class="modal fade" id="modalEliminarFuncionario<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalEliminarLabel<?= $fila['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title fw-bold text-white" id="modalEliminarLabel<?= $fila['id'] ?>"><i class="lni lni-trash me-1"></i> Confirmar Eliminación</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <i class="lni lni-warning text-danger mb-3" style="font-size: 4rem;"></i>
                                <p class="fs-5 text-dark">¿Estás seguro de que deseas desactivar al funcionario <br><strong><?= htmlspecialchars($fila['nombre'] . ' ' . $fila['paterno']) ?></strong>?</p>
                                <p class="text-muted small">Esta acción cambiará su estado a INACTIVO y guardará la fecha de eliminación en el sistema.</p>
                            </div>
                            <div class="modal-footer bg-light justify-content-center">
                                <form action="delete.php" method="POST">
                                    <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-danger fw-bold shadow-sm">Sí, Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                  </div>
                  <?php else: ?>
                  <!-- Modal para Activar Funcionario (Verde) -->
                  <div class="modal fade" id="modalActivarFuncionario<?= $fila['id'] ?>" tabindex="-1" aria-labelledby="modalActivarLabel<?= $fila['id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold text-white" id="modalActivarLabel<?= $fila['id'] ?>"><i class="lni lni-checkmark-circle me-1"></i> Confirmar Reactivación</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <i class="lni lni-checkmark-circle text-success mb-3" style="font-size: 4rem;"></i>
                                <p class="fs-5 text-dark">¿Estás seguro de que deseas reactivar al funcionario <br><strong><?= htmlspecialchars($fila['nombre'] . ' ' . $fila['paterno']) ?></strong>?</p>
                                <p class="text-muted small mb-2">Esta acción cambiará su estado a ACTIVO, restablecerá su acceso al sistema y anulará la fecha de eliminación.</p>
                                <span class="badge bg-danger bg-opacity-75 text-white px-3 py-2"><i class="lni lni-calendar me-1"></i> Inactivo desde: <?= $fila['deleted_at'] ? date('d/m/Y H:i', strtotime($fila['deleted_at'])) : 'Desconocido' ?></span>
                            </div>
                            <div class="modal-footer bg-light justify-content-center">
                                <form action="active.php" method="POST">
                                    <input type="hidden" name="id" value="<?= $fila['id'] ?>">
                                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success fw-bold shadow-sm">Sí, Activar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                  </div>
                  <?php endif; ?>

                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal para Crear Funcionario -->
  <div class="modal fade" id="modalCrearFuncionario" tabindex="-1" aria-labelledby="modalCrearLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold text-white" id="modalCrearLabel"><i class="lni lni-users me-1"></i> Registrar Nuevo Funcionario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form action="store.php" method="POST">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="nombre" class="form-label fw-bold text-dark">Nombre(s)</label>
                            <input type="text" class="form-control bg-light" id="nombre" name="nombre" oninput="sugerirCredenciales()" required>
                        </div>
                        <div class="col-md-4">
                            <label for="paterno" class="form-label fw-bold text-dark">A. Paterno</label>
                            <input type="text" class="form-control bg-light" id="paterno" name="paterno" oninput="sugerirCredenciales()" required>
                        </div>
                        <div class="col-md-4">
                            <label for="materno" class="form-label fw-bold text-dark">A. Materno</label>
                            <input type="text" class="form-control bg-light" id="materno" name="materno">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="ci" class="form-label fw-bold text-dark">Carnet de Identidad (CI)</label>
                            <input type="text" class="form-control bg-light" id="ci" name="ci" oninput="sugerirCredenciales()" required>
                        </div>
                        <div class="col-md-4">
                            <label for="telefono" class="form-label fw-bold text-dark">Teléfono</label>
                            <input type="text" class="form-control bg-light" id="telefono" name="telefono">
                        </div>
                        <div class="col-md-4">
                            <label for="rol_id" class="form-label fw-bold text-dark">Rol / Cargo</label>
                            <select class="form-select bg-light" id="rol_id" name="rol_id" required>
                                <option value="" disabled selected>Seleccione un rol...</option>
                                <?php foreach($roles as $rol): ?>
                                    <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <hr class="my-4">
                    <h5 class="fw-bold fs-6 mb-3 text-dark"><i class="lni lni-lock text-primary me-1"></i> Cuenta de Acceso al Sistema</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <label for="usuario" class="form-label text-dark">Nombre de Usuario</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Dejar en blanco si no requiere acceso" oninput="verificarUsuario(this.value)">
                            <div id="usuario_feedback" class="form-text mt-1"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label text-dark">Contraseña Inicial</label>
                            <input type="text" class="form-control" id="password" name="password" placeholder="Requerido si asigna usuario">
                        </div>
                    </div>
                    <div class="modal-footer mt-4 pb-0 px-0">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold">Registrar Funcionario</button>
                    </div>
                </form>
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
  <!-- Scripts personalizados de la aplicación -->
  <script src="../assets/js/habitapp.js"></script>
</body>
</html>