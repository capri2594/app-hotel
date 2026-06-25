<?php
session_start();

// Validar si el usuario está logueado y es SuperAdmin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'SuperAdmin') {
    die("Acceso denegado. Este módulo es exclusivo para el SuperAdministrador.");
}

include '../conexion.php';

// Filtros
$where_clauses = [];
$params = [];
$types = "";

$filtro_usuario = trim($_POST['usuario'] ?? '');
if (!empty($filtro_usuario)) {
    $where_clauses[] = "u.usuario LIKE ?";
    $params[] = "%" . $filtro_usuario . "%";
    $types .= "s";
}

$filtro_accion = trim($_POST['accion'] ?? '');
if (!empty($filtro_accion)) {
    $where_clauses[] = "a.accion = ?";
    $params[] = $filtro_accion;
    $types .= "s";
}

$filtro_desde = trim($_POST['desde'] ?? '');
if (!empty($filtro_desde)) {
    $where_clauses[] = "DATE(a.fecha) >= ?";
    $params[] = $filtro_desde;
    $types .= "s";
}

$filtro_hasta = trim($_POST['hasta'] ?? '');
if (!empty($filtro_hasta)) {
    $where_clauses[] = "DATE(a.fecha) <= ?";
    $params[] = $filtro_hasta;
    $types .= "s";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

$sql = "SELECT a.*, u.usuario as operador 
        FROM auditoria_sistema a
        JOIN usuario u ON a.usuario_id = u.id
        $where_sql
        ORDER BY a.fecha DESC LIMIT 100";

$stmt = $conexion->prepare($sql);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();

// Obtener todas las acciones únicas para el selector de filtro
$acciones_result = $conexion->query("SELECT DISTINCT accion FROM auditoria_sistema ORDER BY accion ASC");
$acciones = [];
if ($acciones_result) {
    while ($row = $acciones_result->fetch_assoc()) {
        $acciones[] = $row['accion'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<?php include '../header.php'; ?>
<body class="bg-light py-4">
  <div class="container-fluid px-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
      <h2 class="fw-bold text-dark"><i class="lni lni-library me-2 text-danger"></i>Bitácora de Auditoría del Sistema</h2>
      <span class="badge bg-danger px-3 py-2 fw-bold text-white shadow-sm">Uso Exclusivo SuperAdmin</span>
    </div>

    <!-- Panel de Filtros -->
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="POST" action="auditoria.php" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-bold mb-1">Operador / Usuario</label>
                    <input type="text" class="form-control form-control-sm" name="usuario" value="<?= htmlspecialchars($filtro_usuario) ?>" placeholder="Buscar por usuario...">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-bold mb-1">Acción</label>
                    <select class="form-select form-select-sm" name="accion">
                        <option value="">-- Todas las acciones --</option>
                        <?php foreach ($acciones as $acc): ?>
                            <option value="<?= htmlspecialchars($acc) ?>" <?= $filtro_accion === $acc ? 'selected' : '' ?>><?= htmlspecialchars($acc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-bold mb-1">Desde</label>
                    <input type="date" class="form-control form-control-sm" name="desde" value="<?= htmlspecialchars($filtro_desde) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-bold mb-1">Hasta</label>
                    <input type="date" class="form-control form-control-sm" name="hasta" value="<?= htmlspecialchars($filtro_hasta) ?>">
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="lni lni-funnel"></i> Filtrar Logs</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de Resultados -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle small">
                    <thead class="table-dark">
                        <tr>
                            <th width="15%" class="py-3 px-3 text-center">Fecha y Hora</th>
                            <th width="12%" class="text-center">Operador</th>
                            <th width="15%" class="text-center">Acción</th>
                            <th width="12%" class="text-center">Tabla / ID</th>
                            <th width="36%">Detalles de la Operación</th>
                            <th width="10%" class="text-center">Dirección IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($row = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center px-3 font-monospace"><?= date('d/m/Y H:i:s', strtotime($row['fecha'])) ?></td>
                                    <td class="text-center fw-bold text-secondary"><?= htmlspecialchars($row['operador']) ?></td>
                                    <td class="text-center">
                                        <span class="badge py-1.5 px-2.5 rounded-2 fw-bold 
                                            <?php
                                                if (strpos($row['accion'], 'CIERRE') !== false || strpos($row['accion'], 'CHECKOUT') !== false) {
                                                    echo 'bg-success text-white';
                                                } elseif (strpos($row['accion'], 'APERTURA') !== false || strpos($row['accion'], 'CHECKIN') !== false) {
                                                    echo 'bg-primary text-white';
                                                } elseif (strpos($row['accion'], 'RETIRO') !== false) {
                                                    echo 'bg-warning text-dark';
                                                } elseif (strpos($row['accion'], 'EXTENDER') !== false) {
                                                    echo 'bg-info text-dark';
                                                } else {
                                                    echo 'bg-secondary text-white';
                                                }
                                            ?>
                                        ">
                                            <?= htmlspecialchars($row['accion']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-muted font-monospace">
                                        <?= htmlspecialchars($row['tabla_afectada']) ?> 
                                        <?= $row['registro_id'] ? '<span class="text-dark fw-bold">#' . $row['registro_id'] . '</span>' : '' ?>
                                    </td>
                                    <td class="text-dark py-2"><?= htmlspecialchars($row['detalles']) ?></td>
                                    <td class="text-center font-monospace text-secondary"><?= htmlspecialchars($row['ip']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No se encontraron registros de auditoría que coincidan con los filtros seleccionados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
  </div>
</body>
</html>
