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
?>
<!DOCTYPE html>
<html lang="es">

<?php include '../header.php'; ?>

<body class="bg-light py-4">
  <div class="container">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="fw-bold text-dark"><i class="lni lni-stats-up me-2"></i>Conciliación y Reportes</h2>
    </div>

    <div class="row g-4">
        
      <!-- TARJETA 1: Arqueo Diario (Para Recepcionistas) -->
      <div class="col-md-4">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header bg-success text-white border-0 py-3">
                <h5 class="mb-0 fw-bold fs-6"><i class="lni lni-coin me-2"></i>Arqueo Diario de Caja</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column">
                <p class="text-muted small mb-4">Muestra los ingresos reales y físicos de una fecha específica. Obligatorio para el cierre de turno.</p>
                
                <form action="arqueos/ArqueoDiarioPdf.php" method="POST" target="_blank" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Seleccionar Fecha de Arqueo</label>
                        <input type="date" class="form-control bg-light border-secondary" name="fecha_arqueo" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-success fw-bold w-100 shadow-sm"><i class="lni lni-printer me-1"></i> Imprimir Diario</button>
                </form>
            </div>
        </div>
      </div>

      <!-- TARJETA 2: Ingresos Mensuales (Para Gerencia) -->
      <div class="col-md-4">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header bg-primary text-white border-0 py-3">
                <h5 class="mb-0 fw-bold fs-6"><i class="lni lni-calendar me-2"></i>Reporte Mensual</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column">
                <p class="text-muted small mb-4">Totaliza los ingresos reales agrupados por mes. Ideal para enviar informes a contabilidad general.</p>
                
                <form action="arqueos/ArqueoMensualPdf.php" method="POST" target="_blank" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Seleccionar Mes y Año</label>
                        <input type="month" class="form-control bg-light border-secondary" name="mes_arqueo" value="<?= date('Y-m') ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold w-100 shadow-sm"><i class="lni lni-printer me-1"></i> Imprimir Mensual</button>
                </form>
            </div>
        </div>
      </div>

      <!-- TARJETA 3: Proyección Operativa (Para Administración) -->
      <div class="col-md-4">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header text-dark border-0 py-3" style="background-color: #ffc107;">
                <h5 class="mb-0 fw-bold fs-6"><i class="lni lni-eye me-2"></i>Proyección Operativa</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column">
                <p class="text-muted small mb-4">Visualiza las habitaciones actualmente Ocupadas y las Reservas confirmadas para organizar la logística.</p>
                
                <form action="arqueos/ProyeccionPdf.php" method="POST" target="_blank" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Proyección a futuro (Días)</label>
                        <select class="form-select bg-light border-secondary" name="dias_proyeccion">
                            <option value="7">Próxima Semana (7 días)</option>
                            <option value="15">Próxima Quincena (15 días)</option>
                            <option value="30">Próximo Mes (30 días)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-warning text-dark fw-bold w-100 shadow-sm"><i class="lni lni-printer me-1"></i> Imprimir Proyección</button>
                </form>
            </div>
        </div>
      </div>

      <!-- TARJETA 4: Parte Policial -->
      <div class="col-md-4">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header text-white border-0 py-3" style="background-color: #680202;">
                <h5 class="mb-0 fw-bold fs-6 text-white"><i class="lni lni-shield me-2 text-white"></i>Parte Policial de Control</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column">
                <p class="text-muted small mb-4">Genera el listado oficial de control policial de todos los huéspedes (titulares y acompañantes) hospedados en una fecha específica.</p>
                
                <form action="arqueos/PolicialPdf.php" method="POST" target="_blank" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Seleccionar Fecha del Control</label>
                        <input type="date" class="form-control bg-light border-secondary" name="fecha" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button type="submit" class="btn fw-bold w-100 shadow-sm text-white" style="background-color: #680202; border-color: #680202;"><i class="lni lni-printer me-1 text-white"></i> Imprimir Parte Policial</button>
                </form>
            </div>
        </div>
      </div>

      <!-- TARJETA 5: Parte de Cámara Hotelera -->
      <div class="col-md-4">
        <div class="card shadow-sm border-0 rounded-3 h-100">
            <div class="card-header text-white border-0 py-3" style="background-color: #6c5ce7;">
                <h5 class="mb-0 fw-bold fs-6 text-white"><i class="lni lni-home me-2 text-white"></i>Parte Cámara Hotelera</h5>
            </div>
            <div class="card-body p-4 d-flex flex-column">
                <p class="text-muted small mb-4">Genera el movimiento diario (Llegadas, Salidas y Pernoctaciones) con corte de 24 horas a las 08:00 AM para la Cámara Hotelera.</p>
                
                <form action="arqueos/CamaraPdf.php" method="POST" target="_blank" class="mt-auto">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Seleccionar Fecha del Parte</label>
                        <input type="date" class="form-control bg-light border-secondary" name="fecha" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <button type="submit" class="btn fw-bold w-100 shadow-sm text-white" style="background-color: #6c5ce7; border-color: #6c5ce7;"><i class="lni lni-printer me-1 text-white"></i> Imprimir Parte Diario</button>
                </form>
            </div>
        </div>
      </div>

    </div>
  </div>

  <script src="../assets/js/bootstrap.min.js"></script>
</body>
</html>