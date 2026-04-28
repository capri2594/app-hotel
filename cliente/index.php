<?php
include '../conexion.php';

// Obtener los tipos de habitaciones disponibles
$sql_tipos = "SELECT * FROM tipo_habitacion ORDER BY id_tipo ASC";
$res_tipos = $conexion->query($sql_tipos);
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<body class="bg-light py-5">
  <!-- CSS de Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <!-- Tema Bootstrap 5 para Select2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

  <div class="container">
    
    <!-- Logo o Encabezado para el cliente -->
    <div class="text-center mb-5">
        <h1 class="fw-bold text-danger"><i class="lni lni-apartment"></i> HabitApp</h1>
        <p class="text-muted fs-5">Reserva tu habitación ideal en segundos</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-7">
            
            <!-- Alertas de éxito o error -->
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success shadow-sm border-0 mb-4 text-center">
                    <i class="lni lni-checkmark-circle fs-2 d-block mb-2 text-success"></i>
                    <strong class="fs-5 d-block">¡Reserva Solicitada con Éxito!</strong>
                    <?= htmlspecialchars($_GET['msg']) ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger shadow-sm border-0 mb-4 text-center">
                    <i class="lni lni-warning fs-2 d-block mb-2 text-danger"></i>
                    <strong class="fs-5 d-block">Lo sentimos...</strong>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-header text-white border-0 pt-4 pb-3 text-center bg-brand">
                    <h3 class="fw-bold text-white mb-0"><i class="lni lni-calendar me-2"></i>Formulario de Reserva</h3>
                </div>
                <div class="card-body p-4 p-md-5 bg-white">
                    
                    <form action="store_reserva.php" method="POST">
                        
                        <h5 class="fw-bold fs-6 mb-3 text-dark text-uppercase border-bottom pb-2">1. Tus Datos Personales</h5>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-secondary">Nombre Completo</label>
                            <input type="text" class="form-control bg-light" name="nombre" required placeholder="Ej. Juan Pérez">
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Documento de Identidad</label>
                                <input type="text" class="form-control bg-light" name="ci" minlength="5" maxlength="20" required placeholder="Ej. 1234567 o AB123456">
                                <div class="form-text mt-1"><i class="lni lni-bulb me-1"></i>Válido para CI local o pasaporte extranjero (Mín. 5 caracteres).</div>
                            </div>
                            <div class="col-md-6 mt-3 mt-md-0">
                                <label class="form-label fw-bold text-secondary">Celular (WhatsApp)</label>
                                <div class="row g-2">
                                    <div class="col-auto" style="width: 130px;">
                                        <select class="form-control select2-country bg-light" name="codigo_pais"></select>
                                    </div>
                                    <div class="col">
                                        <input type="tel" class="form-control bg-light" name="telefono" required placeholder="Número" pattern="[0-9]{6,15}">
                                    </div>
                                </div>
                                <div class="form-text mt-1"><i class="lni lni-whatsapp me-1 text-success"></i>Para enviarte la confirmación.</div>
                            </div>
                        </div>
                        
                        <h5 class="fw-bold fs-6 mb-3 text-dark text-uppercase border-bottom pb-2 mt-5">2. Habitaciones a Reservar</h5>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-secondary mb-3">Indica la cantidad que necesitas de cada tipo:</label>
                            <?php while($tipo = $res_tipos->fetch_assoc()): ?>
                                <div class="row mb-3 align-items-center bg-light p-2 rounded mx-0 border border-light shadow-sm">
                                    <div class="col-8">
                                        <strong class="text-dark fs-6"><i class="lni lni-home me-2 text-danger"></i><?= htmlspecialchars($tipo['nombre']) ?></strong>
                                    </div>
                                    <div class="col-4">
                                        <input type="number" class="form-control text-center border-secondary" name="cantidades[<?= $tipo['id_tipo'] ?>]" value="0" min="0" max="10">
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-secondary">Fecha de Llegada <small>(Check-in)</small></label>
                                <input type="date" class="form-control form-control-lg bg-light" name="fecha_ingreso" min="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mt-3 mt-md-0">
                                <label class="form-label fw-bold text-secondary">Fecha de Salida <small>(Check-out)</small></label>
                                <input type="date" class="form-control form-control-lg bg-light" name="fecha_salida" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                            </div>
                        </div>

                        <div class="form-check mb-4 mt-2 bg-light p-3 rounded border border-light shadow-sm">
                            <input class="form-check-input ms-1 border-secondary" type="checkbox" id="terminos" required style="transform: scale(1.3);">
                            <label class="form-check-label ms-2 fw-bold text-dark" for="terminos">
                                Comprendo que esta solicitud genera una <span class="text-danger">Pre-Reserva válida por 12 horas</span>. El pago y confirmación final se realizará presencialmente en Recepción.
                            </label>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold shadow-sm py-3" style="font-size: 1.1rem;">
                                Solicitar Reserva Ahora
                            </button>
                            <small class="text-muted text-center mt-3"><i class="lni lni-lock me-1"></i>Tus datos están protegidos. El pago se coordinará en recepción.</small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </div>

  <!-- JS de Bootstrap -->
  <script src="../assets/js/bootstrap.min.js"></script>
  
  <!-- JS de jQuery y Select2 -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  
  <!-- Script principal de la aplicación -->
  <script src="../assets/js/habitapp.js"></script>
</body>
</html>