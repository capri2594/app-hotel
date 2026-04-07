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
?>
<!DOCTYPE html>
<html class="no-js" lang="zxx">

<!-- Start Header Area -->
<?php
include '../header.php'; 

// Obtener los pisos disponibles de la base de datos
$sql_pisos = "SELECT DISTINCT piso FROM habitacion ORDER BY piso ASC";
$res_pisos = $conexion->query($sql_pisos);
$pisos = [];
while($p = $res_pisos->fetch_assoc()) {
    $pisos[] = $p['piso'];
}

// Consulta para obtener TODAS las habitaciones ordenadas por piso y número
$sql = "SELECT h.id_habitacion, h.numero, h.piso, t.codigo, t.nombre, h.estado
        FROM habitacion h 
        INNER JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo 
        ORDER BY h.piso ASC, h.numero ASC";
$resultado = $conexion->query($sql);

// Agrupar habitaciones por piso en un array multidimensional
$habitaciones_por_piso = [];
if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $habitaciones_por_piso[$fila['piso']][] = $fila;
    }
}

// Obtener cantidad de reservas pendientes
$sql_pendientes = "SELECT COUNT(*) as total FROM reservas WHERE estado = 'PENDIENTE'";
$res_pendientes = $conexion->query($sql_pendientes);
$total_pendientes = $res_pendientes->fetch_assoc()['total'] ?? 0;
?>
<body class="bg-light py-4">
    <div class="container">
        
        <!-- Alerta de Reservas Web Pendientes -->
        <?php if ($total_pendientes > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-warning mt-2 d-flex align-items-center justify-content-between" role="alert">
            <div>
                <i class="lni lni-alarm-clock fs-4 text-danger me-2 align-middle"></i>
                <span class="align-middle fs-6 text-dark"><strong>¡Alerta de Recepción!</strong> Tienes <strong><?= $total_pendientes ?></strong> nueva(s) reserva(s) pendiente(s) desde la web.</span>
            </div>
            <a href="../reservas/index.php" class="btn btn-danger btn-sm fw-bold shadow-sm">Atender Reservas ➡</a>
            <button type="button" class="btn-close position-relative top-0 end-0 ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Cabecera Principal -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <h2 class="fw-bold text-dark mb-0"><i class="lni lni-map me-2"></i>Mapa de Habitaciones</h2>
            <a href="../reservas/index.php" class="btn btn-outline-primary fw-bold shadow-sm">
                <i class="lni lni-agenda"></i> Ir a Gestión de Reservas
            </a>
        </div>

        <!-- Selector de Pisos (Pestañas de Bootstrap sin recargar la página) -->
        <div class="row mb-4 justify-content-center">
          <div class="col-12 text-center">
            <ul class="nav nav-pills justify-content-center gap-2" id="pisoTabs" role="tablist">
              <?php foreach($pisos as $index => $piso): ?>
                <li class="nav-item" role="presentation">
                  <button class="nav-link fw-bold px-4 <?= $index === 0 ? 'active shadow-sm' : 'bg-white text-secondary border' ?>" id="tab-piso-<?= $piso ?>" data-bs-toggle="pill" data-bs-target="#piso-<?= $piso ?>" type="button" role="tab" aria-controls="piso-<?= $piso ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                    <i class="lni lni-layers me-1"></i> Piso <?= $piso ?>
                  </button>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>

        <!-- Leyenda -->
        <div class="container mb-4">
            <table class="table table-bordered text-center align-middle bg-white shadow-sm" style="table-layout: fixed; width: 100%;">
              <thead class="table-light">
                <tr>
                  <th>Disponible</th>
                  <th>Ocupado</th>
                  <th>Reservado</th>
                  <th>Mantenimiento</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><img src="../assets/images/DISPONIBLE.gif" alt="Disponible" style="width: 40px; height: 40px;"></td>
                  <td><img src="../assets/images/OCUPADA.gif" alt="Ocupado" style="width: 40px; height: 40px;"></td>
                  <td><img src="../assets/images/RESERVADA.gif" alt="Reservado" style="width: 40px; height: 40px;"></td>
                  <td><img src="../assets/images/MANTENIMIENTO.gif" alt="Mantenimiento" style="width: 40px; height: 40px;"></td>
                </tr>
              </tbody>
            </table>
        </div>

        <!-- Estilos para el Foquito (Estilo Baliza / Sirena) -->
        <style>
            .foquito { position: absolute; top: 12px; right: 12px; width: 12px; height: 12px; border-radius: 50%; box-shadow: inset 0 0 4px rgba(0,0,0,0.5); z-index: 10; animation: estrobo 2s infinite; }
            .foquito::after { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; background-color: inherit; animation: onda 1.5s infinite ease-out; z-index: -1; }
            @keyframes estrobo { 0%, 10%, 20%, 100% { opacity: 1; } 5%, 15% { opacity: 0.1; } }
            @keyframes onda { 0% { transform: scale(1); opacity: 0.8; } 100% { transform: scale(4); opacity: 0; } }
        </style>

        <!-- Contenedor Dinámico de Mapas -->
        <div class="tab-content" id="pisoTabsContent">
            <?php foreach($pisos as $index => $piso_actual): ?>
                <?php
                    $piso_base = $piso_actual * 100;
                    $superior = []; 
                    $inferior = []; 
                    if (isset($habitaciones_por_piso[$piso_actual])) {
                        foreach ($habitaciones_por_piso[$piso_actual] as $fila) {
                            $num = intval($fila['numero']);
                            if ($num >= ($piso_base + 9) && $num <= ($piso_base + 18)) { $superior[$num] = $fila; } 
                            elseif ($num >= ($piso_base + 1) && $num <= ($piso_base + 8)) { $inferior[$num] = $fila; }
                        }
                    }
                ?>
                <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="piso-<?= $piso_actual ?>" role="tabpanel" aria-labelledby="tab-piso-<?= $piso_actual ?>">
                    
                    <!-- Título Interno de la Pestaña -->
                    <div class="section-title mb-4" style="padding: 0; text-align: center;">
                        <h3 class="wow fadeInUp text-muted" data-wow-delay=".2s">Piso <?= $piso_actual ?> (Hab. <?= $piso_base + 1 ?> al <?= $piso_base + 18 ?>)</h3>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm text-center align-middle bg-white shadow-sm" style="table-layout: fixed;">
                          <tbody>
                            <!-- 🔼 Fila superior dinámica -->
                            <tr>
                              <?php
                              for ($n = $piso_base + 9; $n <= $piso_base + 18; $n++) {
                                if (isset($superior[$n])) {
                                  $hab = $superior[$n];
                                  $estado = strtoupper($hab['estado']);
                                  $bg_td = ($estado == 'MANTENIMIENTO') ? 'background-color: #e2e3e5;' : '';
                                  $foquito = '';
                                  switch ($estado) {
                                    case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; $foquito = '<div class="foquito bg-success"></div>'; break;
                                    case 'OCUPADA':        $gif = 'OCUPADA.gif'; $foquito = '<div class="foquito bg-danger"></div>'; break;
                                    case 'RESERVADA':      $gif = 'RESERVADA.gif'; $foquito = '<div class="foquito bg-warning"></div>'; break;
                                    case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; break;
                                    default:               $gif = 'default.gif'; break;
                                  }
                                  echo '<td style="position: relative; height: 120px; '.$bg_td.'">';
                                  echo $foquito;
                                  if ($estado == 'DISPONIBLE') { echo '<form action="reservar.php" method="POST" style="margin:0; height:100%;"><input type="hidden" name="id_habitacion" value="'.$hab['id_habitacion'].'"><button type="submit" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;">'; }
                                  echo '<div style="padding-bottom: 30px;"><strong>'.htmlspecialchars($hab['numero']).'</strong><br>'.htmlspecialchars($hab['nombre']).'</div><img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
                                  if ($estado == 'DISPONIBLE') { echo '</button></form>'; }
                                  echo '</td>';
                                } else {
                                  echo '<td style="position: relative; height: 120px;">' . $n . '</td>';
                                }
                              }
                              ?>
                            </tr>

                            <!-- 🟨 Fila central: PASILLO -->
                            <tr>
                              <td colspan="10" class="bg-warning text-dark fw-bold border-warning" style="height: 40px; letter-spacing: 5px;">PASILLO PRINCIPAL PISO <?= $piso_actual ?></td>
                            </tr>

                            <!-- 🔽 Fila inferior dinámica con INGRESO -->
                            <tr>
                              <?php
                              $primeras = [$piso_base+8, $piso_base+7, $piso_base+6, $piso_base+5];
                              foreach ($primeras as $n) {
                                if (isset($inferior[$n])) {
                                  $hab = $inferior[$n];
                                  $estado = strtoupper($hab['estado']);
                                  $bg_td = ($estado == 'MANTENIMIENTO') ? 'background-color: #e2e3e5;' : '';
                                  $foquito = '';
                                  switch ($estado) {
                                    case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; $foquito = '<div class="foquito bg-success"></div>'; break;
                                    case 'OCUPADA':        $gif = 'OCUPADA.gif'; $foquito = '<div class="foquito bg-danger"></div>'; break;
                                    case 'RESERVADA':      $gif = 'RESERVADA.gif'; $foquito = '<div class="foquito bg-warning"></div>'; break;
                                    case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; break;
                                    default:               $gif = 'default.gif'; break;
                                  }
                                  echo '<td style="position: relative; height: 120px; '.$bg_td.'">';
                                  echo $foquito;
                                  if ($estado == 'DISPONIBLE') { echo '<form action="reservar.php" method="POST" style="margin:0; height:100%;"><input type="hidden" name="id_habitacion" value="'.$hab['id_habitacion'].'"><button type="submit" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;">'; }
                                  echo '<div style="padding-bottom: 30px;"><strong>'.htmlspecialchars($hab['numero']).'</strong><br>'.htmlspecialchars($hab['nombre']).'</div><img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
                                  if ($estado == 'DISPONIBLE') { echo '</button></form>'; }
                                  echo '</td>';
                                } else {
                                  echo '<td style="position: relative; height: 120px;">' . $n . '</td>';
                                }
                              }

                              // INGRESO
                              echo '<td colspan="2" class="bg-primary text-white fw-bold" style="height: 120px;">INGRESO</td>';

                              $ultimas = [$piso_base+4, $piso_base+3, $piso_base+2, $piso_base+1];
                              foreach ($ultimas as $n) {
                                if (isset($inferior[$n])) {
                                  $hab = $inferior[$n];
                                  $estado = strtoupper($hab['estado']);
                                  $bg_td = ($estado == 'MANTENIMIENTO') ? 'background-color: #e2e3e5;' : '';
                                  $foquito = '';
                                  switch ($estado) {
                                    case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; $foquito = '<div class="foquito bg-success"></div>'; break;
                                    case 'OCUPADA':        $gif = 'OCUPADA.gif'; $foquito = '<div class="foquito bg-danger"></div>'; break;
                                    case 'RESERVADA':      $gif = 'RESERVADA.gif'; $foquito = '<div class="foquito bg-warning"></div>'; break;
                                    case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; break;
                                    default:               $gif = 'default.gif'; break;
                                  }
                                  echo '<td style="position: relative; height: 120px; '.$bg_td.'">';
                                  echo $foquito;
                                  if ($estado == 'DISPONIBLE') { echo '<form action="reservar.php" method="POST" style="margin:0; height:100%;"><input type="hidden" name="id_habitacion" value="'.$hab['id_habitacion'].'"><button type="submit" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;">'; }
                                  echo '<div style="padding-bottom: 30px;"><strong>'.htmlspecialchars($hab['numero']).'</strong><br>'.htmlspecialchars($hab['nombre']).'</div><img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
                                  if ($estado == 'DISPONIBLE') { echo '</button></form>'; }
                                  echo '</td>';
                                } else {
                                  echo '<td style="position: relative; height: 120px;">' . $n . '</td>';
                                }
                              }
                              ?>
                            </tr>
                          </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
</div>

    <script src="../assets/js/bootstrap.min.js"></script>
</body>

</html>