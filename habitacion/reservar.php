<?php
session_start();

// Validar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../index.php");
    exit;
}

// Seguridad: Solo permitir el acceso mediante POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id_habitacion'])) {
    header("Location: index.php");
    exit;
}

include '../conexion.php';

$id_habitacion = intval($_POST['id_habitacion']);

// Obtener los detalles de la habitación
$sql = "SELECT h.numero, h.piso, t.nombre as tipo_nombre 
        FROM habitacion h 
        INNER JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo 
        WHERE h.id_habitacion = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id_habitacion);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$habitacion = $resultado->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<body class="bg-light py-4">
  <div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-primary text-white border-0 pt-4 pb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="fw-bold text-white mb-0"><i class="lni lni-calendar me-2"></i>Nueva Reserva</h3>
                        <a href="index.php" class="btn btn-light btn-sm fw-bold shadow-sm">⬅ Volver al Mapa</a>
                    </div>
                </div>
                <div class="card-body p-4 p-md-5">
                    
                    <!-- Ficha resumen de la habitación -->
                    <div class="alert alert-primary mb-4 border-0 shadow-sm text-center">
                        <span class="fs-6 text-dark opacity-75 d-block mb-1">Estás registrando a un cliente en la:</span>
                        <strong class="fs-4 text-primary d-block">Habitación <?= htmlspecialchars($habitacion['numero']) ?></strong>
                        <span class="badge bg-primary mt-2 px-3 py-2"><?= htmlspecialchars($habitacion['tipo_nombre']) ?> (Piso <?= htmlspecialchars($habitacion['piso']) ?>)</span>
                    </div>

                    <!-- Formulario de Reserva -->
                    <form action="store_reserva.php" method="POST">
                        <input type="hidden" name="habitacion_id" value="<?= $id_habitacion ?>">
                        
                        <h5 class="fw-bold fs-6 mb-3 text-dark">📝 Datos del Cliente</h5>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Nombre Completo</label>
                            <input type="text" class="form-control bg-light" name="nombre" required placeholder="Ej. Juan Pérez">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Carnet de Identidad (CI)</label>
                                <input type="text" class="form-control bg-light" name="ci" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Teléfono / Celular</label>
                                <input type="text" class="form-control bg-light" name="telefono">
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        <h5 class="fw-bold fs-6 mb-3 text-dark"><i class="lni lni-alarm-clock me-1 text-primary"></i> Detalles de Estancia</h5>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Fecha de Ingreso (Check-in)</label>
                                <input type="date" class="form-control bg-light" name="fecha_ingreso" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Fecha de Salida (Check-out)</label>
                                <!-- El campo fecha_salida debe ser al menos el día siguiente a hoy -->
                                <input type="date" class="form-control bg-light" name="fecha_salida" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm">Confirmar y Registrar Reserva</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </div>

  <script src="../assets/js/bootstrap.min.js"></script>
</body>
</html>