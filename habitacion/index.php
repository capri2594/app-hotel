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

// LIMPIEZA AUTOMÁTICA Y LIBERACIÓN DE HABITACIONES (Reservas > 12 horas sin consolidar)
$sql_expiring = "SELECT id FROM reservas WHERE (estado = 'SOLICITADA' AND created_at <= NOW() - INTERVAL 12 HOUR) OR (estado = 'RESERVADA' AND confirmada_at <= NOW() - INTERVAL 12 HOUR)";
$res_expiring = $conexion->query($sql_expiring);
if ($res_expiring && $res_expiring->num_rows > 0) {
    while($row = $res_expiring->fetch_assoc()) {
        $id_res = $row['id'];
        $conexion->query("UPDATE habitacion SET estado = 'DISPONIBLE' WHERE id_habitacion IN (SELECT habitacion_id FROM detalle_reserva WHERE reserva_id = $id_res)");
        $conexion->query("UPDATE reservas SET estado = 'EXPIRADA' WHERE id = $id_res");
    }
}
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

// Obtener los tipos de habitaciones disponibles para edición
$sql_tipos = "SELECT * FROM tipo_habitacion ORDER BY id_tipo ASC";
$res_tipos = $conexion->query($sql_tipos);
$tipos_habitacion = [];
if ($res_tipos) {
    while($t = $res_tipos->fetch_assoc()) {
        $tipos_habitacion[] = $t;
    }
}

// Obtener métricas globales para la leyenda
$sql_stats = "SELECT estado, COUNT(*) as total FROM habitacion GROUP BY estado";
$res_stats = $conexion->query($sql_stats);
$stats = ['DISPONIBLE' => 0, 'OCUPADA' => 0, 'RESERVADA' => 0, 'MANTENIMIENTO' => 0];
if ($res_stats) {
    while($row = $res_stats->fetch_assoc()) {
        $stats[$row['estado']] = $row['total'];
    }
}

// Consulta para obtener TODAS las habitaciones + Nombre del huésped
$sql = "SELECT h.id_habitacion, h.numero, h.id_tipo, h.piso, t.codigo, t.nombre, h.estado,
               MAX(r.nombre) as huesped,
               MAX(r.id) as reserva_id
        FROM habitacion h 
        INNER JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo 
        LEFT JOIN detalle_reserva dr ON dr.habitacion_id = h.id_habitacion
        LEFT JOIN reservas r ON dr.reserva_id = r.id AND r.estado IN ('OCUPADA', 'RESERVADA')
        GROUP BY h.id_habitacion
        ORDER BY h.piso ASC, h.numero ASC";
$resultado = $conexion->query($sql);

// Agrupar habitaciones por piso en un array multidimensional
$habitaciones_por_piso = [];
if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $habitaciones_por_piso[$fila['piso']][] = $fila;
    }
}

// Obtener cantidad de reservas solicitadas
$sql_solicitadas = "SELECT COUNT(*) as total FROM reservas WHERE estado = 'SOLICITADA'";
$res_solicitadas = $conexion->query($sql_solicitadas);
$total_solicitadas = $res_solicitadas->fetch_assoc()['total'] ?? 0;
?>
<body class="bg-light py-4">
    <div class="container">
        
        <!-- Alerta de Reservas Web Pendientes -->
        <?php if ($total_solicitadas > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-warning mt-2 d-flex align-items-center justify-content-between" role="alert">
            <div>
                <i class="lni lni-alarm-clock fs-4 text-danger me-2 align-middle"></i>
                <span class="align-middle fs-6 text-dark"><strong>¡Alerta de Recepción!</strong> Tienes <strong><?= $total_solicitadas ?></strong> nueva(s) reserva(s) solicitada(s) desde la web.</span>
            </div>
            <a href="../reservas/index.php" class="btn btn-danger btn-sm fw-bold shadow-sm">Atender Reservas ➡</a>
            <button type="button" class="btn-close position-relative top-0 end-0 ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Cabecera Principal -->
        <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
            <h2 class="fw-bold text-dark mb-0"><i class="lni lni-map me-2"></i>Mapa de Habitaciones</h2>
            <div class="d-flex gap-2">
                <a href="history.php" class="btn btn-outline-secondary fw-bold shadow-sm">
                    <i class="lni lni-timer"></i> Historial Mantenimiento
                </a>
                <a href="../reservas/index.php" class="btn btn-outline-primary fw-bold shadow-sm">
                    <i class="lni lni-agenda"></i> Ir a Gestión de Reservas
                </a>
            </div>
        </div>

        <!-- Selector de Pisos (Pestañas de Bootstrap sin recargar la página) -->
        <div class="row mb-4 justify-content-center">
          <div class="col-12 text-center">
            <ul class="nav nav-pills justify-content-center gap-2" id="pisoTabs" role="tablist">
              <?php foreach($pisos as $index => $piso): ?>
                <li class="nav-item" role="presentation">
              <button class="nav-link fw-bold px-4 shadow-sm <?= $index === 0 ? 'active' : '' ?>" id="tab-piso-<?= $piso ?>" data-bs-toggle="pill" data-bs-target="#piso-<?= $piso ?>" type="button" role="tab" aria-controls="piso-<?= $piso ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
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
                  <th>Disponible <span class="badge bg-success ms-1"><?= $stats['DISPONIBLE'] ?></span></th>
                  <th>Ocupado <span class="badge bg-danger ms-1"><?= $stats['OCUPADA'] ?></span></th>
                  <th>Reservado <span class="badge bg-warning text-dark ms-1"><?= $stats['RESERVADA'] ?></span></th>
                  <th>Mantenimiento <span class="badge bg-secondary ms-1"><?= $stats['MANTENIMIENTO'] ?></span></th>
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
                                  $foquito = '';
                                  switch ($estado) {
                                    case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; $bg_td = 'background-color: #d1e7dd;'; break;
                                    case 'OCUPADA':        $gif = 'OCUPADA.gif'; $bg_td = 'background-color: #f8d7da;'; break;
                                    case 'RESERVADA':      $gif = 'RESERVADA.gif'; $bg_td = 'background-color: #fff3cd;'; $foquito = '<div class="foquito bg-warning"></div>'; break;
                                    case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; $bg_td = 'background-color: #e2e3e5;'; break;
                                    default:               $gif = 'default.gif'; $bg_td = ''; break;
                                  }
                                echo '<td style="position: relative; height: 120px; overflow: hidden; '.$bg_td.'">';
                                  echo $foquito;
                                  
                                  // Botón "Poner en Mantenimiento" (Engranaje)
                                  if ($estado == 'DISPONIBLE') { echo '<button type="button" class="btn btn-sm text-secondary border-0 p-1" style="position: absolute; top: 5px; left: 5px; z-index: 20; margin: 0;" title="Poner en Mantenimiento" data-bs-toggle="modal" data-bs-target="#modalMantenimientoStart'.$hab['id_habitacion'].'"><i class="lni lni-cog"></i></button>'; }
                                  
                                  // Botón "Editar Habitación" (Lápiz)
                                  if ($estado == 'DISPONIBLE' || $estado == 'MANTENIMIENTO') { echo '<button type="button" class="btn btn-sm text-primary border-0 p-1" style="position: absolute; top: 5px; right: 5px; z-index: 20; margin: 0;" title="Editar Habitación" data-bs-toggle="modal" data-bs-target="#modalEditarHab'.$hab['id_habitacion'].'"><i class="lni lni-pencil-alt"></i></button>'; }

                                  // Botón Principal de la celda (Reservar o Habilitar)
                                  if ($estado == 'DISPONIBLE') {
                                      echo '<form action="create.php" method="POST" style="margin:0; height:100%;"><input type="hidden" name="id_habitacion" value="'.$hab['id_habitacion'].'"><button type="submit" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;">'; 
                                  } elseif ($estado == 'MANTENIMIENTO') { 
                                      echo '<button type="button" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;" title="Habilitar Habitación" data-bs-toggle="modal" data-bs-target="#modalMantenimientoEnd'.$hab['id_habitacion'].'">'; 
                                  } elseif ($estado == 'OCUPADA' || $estado == 'RESERVADA') {
                                      $link_url = "../reservas/index.php";
                                      if ($estado == 'RESERVADA' && !empty($hab['reserva_id'])) $link_url .= "?open_modal=checkin&id=" . $hab['reserva_id'];
                                      elseif ($estado == 'OCUPADA' && !empty($hab['reserva_id'])) $link_url .= "?open_modal=checkout&id=" . $hab['reserva_id'];
                                      echo '<a href="'.$link_url.'" style="display:block; width:100%; height:100%; color:inherit; text-decoration:none;">';
                                  }
                                  
                                  $badge_huesped = '';
                                  if ($estado == 'OCUPADA' || $estado == 'RESERVADA') {
                                      $nombre_mostrar = !empty($hab['huesped']) ? htmlspecialchars($hab['huesped']) : 'Sin registro';
                                      $badge_huesped = '<div class="mt-1"><span class="badge bg-dark text-wrap shadow-sm" style="font-size:0.65rem; line-height:1.1;">'.$nombre_mostrar.'</span></div>';
                                  }
                                  echo '<div style="padding-bottom: 30px;"><strong>'.htmlspecialchars($hab['numero']).'</strong><br><span style="font-size:0.75rem;">'.htmlspecialchars($hab['nombre']).'</span>'.$badge_huesped.'</div><img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
                                  if ($estado == 'DISPONIBLE') { echo '</button></form>'; }
                                  elseif ($estado == 'MANTENIMIENTO') { echo '</button>'; }
                                  elseif ($estado == 'OCUPADA' || $estado == 'RESERVADA') { echo '</a>'; }
                                  echo '</td>';
                                } else {
                                echo '<td style="position: relative; height: 120px; overflow: hidden;">' . $n . '</td>';
                                }
                              }
                              ?>
                            </tr>

                            <!-- 🟨 Fila central: PASILLO -->
                            <tr>
                              <td colspan="10" class="text-white fw-bold shadow-sm" style="background: linear-gradient(90deg, #680202 0%, #3a0d0d 100%); height: 40px; letter-spacing: 5px; border: none;">PASILLO PRINCIPAL PISO <?= $piso_actual ?></td>
                            </tr>

                            <!-- 🔽 Fila inferior dinámica con INGRESO -->
                            <tr>
                              <?php
                              $primeras = [$piso_base+8, $piso_base+7, $piso_base+6, $piso_base+5];
                              foreach ($primeras as $n) {
                                if (isset($inferior[$n])) {
                                  $hab = $inferior[$n];
                                  $estado = strtoupper($hab['estado']);
                                  $foquito = '';
                                  switch ($estado) {
                                    case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; $bg_td = 'background-color: #d1e7dd;'; break;
                                    case 'OCUPADA':        $gif = 'OCUPADA.gif'; $bg_td = 'background-color: #f8d7da;'; break;
                                    case 'RESERVADA':      $gif = 'RESERVADA.gif'; $bg_td = 'background-color: #fff3cd;'; $foquito = '<div class="foquito bg-warning"></div>'; break;
                                    case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; $bg_td = 'background-color: #e2e3e5;'; break;
                                    default:               $gif = 'default.gif'; $bg_td = ''; break;
                                  }
                                echo '<td style="position: relative; height: 120px; overflow: hidden; '.$bg_td.'">';
                                  echo $foquito;
                                  
                                  // Botón "Poner en Mantenimiento" (Engranaje)
                                  if ($estado == 'DISPONIBLE') { echo '<button type="button" class="btn btn-sm text-secondary border-0 p-1" style="position: absolute; top: 5px; left: 5px; z-index: 20; margin: 0;" title="Poner en Mantenimiento" data-bs-toggle="modal" data-bs-target="#modalMantenimientoStart'.$hab['id_habitacion'].'"><i class="lni lni-cog"></i></button>'; }
                                  
                                  // Botón "Editar Habitación" (Lápiz)
                                  if ($estado == 'DISPONIBLE' || $estado == 'MANTENIMIENTO') { echo '<button type="button" class="btn btn-sm text-primary border-0 p-1" style="position: absolute; top: 5px; right: 5px; z-index: 20; margin: 0;" title="Editar Habitación" data-bs-toggle="modal" data-bs-target="#modalEditarHab'.$hab['id_habitacion'].'"><i class="lni lni-pencil-alt"></i></button>'; }

                                  // Botón Principal de la celda (Reservar o Habilitar)
                                  if ($estado == 'DISPONIBLE') {
                                      echo '<form action="create.php" method="POST" style="margin:0; height:100%;"><input type="hidden" name="id_habitacion" value="'.$hab['id_habitacion'].'"><button type="submit" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;">'; 
                                  } elseif ($estado == 'MANTENIMIENTO') { 
                                      echo '<button type="button" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;" title="Habilitar Habitación" data-bs-toggle="modal" data-bs-target="#modalMantenimientoEnd'.$hab['id_habitacion'].'">'; 
                                  } elseif ($estado == 'OCUPADA' || $estado == 'RESERVADA') {
                                      $link_url = "../reservas/index.php";
                                      if ($estado == 'RESERVADA' && !empty($hab['reserva_id'])) $link_url .= "?open_modal=checkin&id=" . $hab['reserva_id'];
                                      elseif ($estado == 'OCUPADA' && !empty($hab['reserva_id'])) $link_url .= "?open_modal=checkout&id=" . $hab['reserva_id'];
                                      echo '<a href="'.$link_url.'" style="display:block; width:100%; height:100%; color:inherit; text-decoration:none;">';
                                  }
                                  
                                  $badge_huesped = '';
                                  if ($estado == 'OCUPADA' || $estado == 'RESERVADA') {
                                      $nombre_mostrar = !empty($hab['huesped']) ? htmlspecialchars($hab['huesped']) : 'Sin registro';
                                      $badge_huesped = '<div class="mt-1"><span class="badge bg-dark text-wrap shadow-sm" style="font-size:0.65rem; line-height:1.1;">'.$nombre_mostrar.'</span></div>';
                                  }
                                  echo '<div style="padding-bottom: 30px;"><strong>'.htmlspecialchars($hab['numero']).'</strong><br><span style="font-size:0.75rem;">'.htmlspecialchars($hab['nombre']).'</span>'.$badge_huesped.'</div><img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
                                  if ($estado == 'DISPONIBLE') { echo '</button></form>'; }
                                  elseif ($estado == 'MANTENIMIENTO') { echo '</button>'; }
                                  elseif ($estado == 'OCUPADA' || $estado == 'RESERVADA') { echo '</a>'; }
                                  echo '</td>';
                                } else {
                                echo '<td style="position: relative; height: 120px; overflow: hidden;">' . $n . '</td>';
                                }
                              }

                              // INGRESO
                              echo '<td colspan="2" class="text-white fw-bold shadow-sm" style="background: linear-gradient(90deg, #680202 0%, #3a0d0d 100%); height: 120px; letter-spacing: 3px; border: none;">INGRESO</td>';

                              $ultimas = [$piso_base+4, $piso_base+3, $piso_base+2, $piso_base+1];
                              foreach ($ultimas as $n) {
                                if (isset($inferior[$n])) {
                                  $hab = $inferior[$n];
                                  $estado = strtoupper($hab['estado']);
                                  $foquito = '';
                                  switch ($estado) {
                                    case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; $bg_td = 'background-color: #d1e7dd;'; break;
                                    case 'OCUPADA':        $gif = 'OCUPADA.gif'; $bg_td = 'background-color: #f8d7da;'; break;
                                    case 'RESERVADA':      $gif = 'RESERVADA.gif'; $bg_td = 'background-color: #fff3cd;'; $foquito = '<div class="foquito bg-warning"></div>'; break;
                                    case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; $bg_td = 'background-color: #e2e3e5;'; break;
                                    default:               $gif = 'default.gif'; $bg_td = ''; break;
                                  }
                                  
                                  echo '<td style="position: relative; height: 120px; overflow: hidden; '.$bg_td.'">';
                                  echo $foquito;
                                  
                                  // Botón "Poner en Mantenimiento" (Engranaje)
                                  if ($estado == 'DISPONIBLE') { echo '<button type="button" class="btn btn-sm text-secondary border-0 p-1" style="position: absolute; top: 5px; left: 5px; z-index: 20; margin: 0;" title="Poner en Mantenimiento" data-bs-toggle="modal" data-bs-target="#modalMantenimientoStart'.$hab['id_habitacion'].'"><i class="lni lni-cog"></i></button>'; }
                                  
                                  // Botón "Editar Habitación" (Lápiz)
                                  if ($estado == 'DISPONIBLE' || $estado == 'MANTENIMIENTO') { echo '<button type="button" class="btn btn-sm text-primary border-0 p-1" style="position: absolute; top: 5px; right: 5px; z-index: 20; margin: 0;" title="Editar Habitación" data-bs-toggle="modal" data-bs-target="#modalEditarHab'.$hab['id_habitacion'].'"><i class="lni lni-pencil-alt"></i></button>'; }

                                  // Botón Principal de la celda (Reservar o Habilitar)
                                  if ($estado == 'DISPONIBLE') {
                                      echo '<form action="create.php" method="POST" style="margin:0; height:100%;"><input type="hidden" name="id_habitacion" value="'.$hab['id_habitacion'].'"><button type="submit" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;">'; 
                                  } elseif ($estado == 'MANTENIMIENTO') { 
                                      echo '<button type="button" style="background:none; border:none; padding:0; margin:0; display:block; width:100%; height:100%; cursor:pointer; color:inherit; text-align:center;" title="Habilitar Habitación" data-bs-toggle="modal" data-bs-target="#modalMantenimientoEnd'.$hab['id_habitacion'].'">'; 
                                  } elseif ($estado == 'OCUPADA' || $estado == 'RESERVADA') {
                                      $link_url = "../reservas/index.php";
                                      if ($estado == 'RESERVADA' && !empty($hab['reserva_id'])) $link_url .= "?open_modal=checkin&id=" . $hab['reserva_id'];
                                      elseif ($estado == 'OCUPADA' && !empty($hab['reserva_id'])) $link_url .= "?open_modal=checkout&id=" . $hab['reserva_id'];
                                      echo '<a href="'.$link_url.'" style="display:block; width:100%; height:100%; color:inherit; text-decoration:none;">';
                                  }
                                  
                                  $badge_huesped = '';
                                  if ($estado == 'OCUPADA' || $estado == 'RESERVADA') {
                                      $nombre_mostrar = !empty($hab['huesped']) ? htmlspecialchars($hab['huesped']) : 'Sin registro';
                                      $badge_huesped = '<div class="mt-1"><span class="badge bg-dark text-wrap shadow-sm" style="font-size:0.65rem; line-height:1.1;">'.$nombre_mostrar.'</span></div>';
                                  }
                                  echo '<div style="padding-bottom: 30px;"><strong>'.htmlspecialchars($hab['numero']).'</strong><br><span style="font-size:0.75rem;">'.htmlspecialchars($hab['nombre']).'</span>'.$badge_huesped.'</div><img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
                                  if ($estado == 'DISPONIBLE') { echo '</button></form>'; }
                                  elseif ($estado == 'MANTENIMIENTO') { echo '</button>'; }
                                  elseif ($estado == 'OCUPADA' || $estado == 'RESERVADA') { echo '</a>'; }
                                  echo '</td>';
                                } else {
                                  echo '<td style="position: relative; height: 120px; overflow: hidden;">' . $n . '</td>';
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

    <!-- Modales de Mantenimiento Generados Dinámicamente -->
    <?php if ($resultado && $resultado->num_rows > 0): ?>
        <?php $resultado->data_seek(0); ?>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <?php if ($fila['estado'] == 'DISPONIBLE'): ?>
                <!-- Modal Poner en Mantenimiento -->
                <div class="modal fade" id="modalMantenimientoStart<?= $fila['id_habitacion'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-secondary text-white">
                                <h5 class="modal-title fw-bold"><i class="lni lni-cog me-1"></i> Mantenimiento Hab. <?= $fila['numero'] ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="maintenance.php" method="POST">
                                <div class="modal-body p-4 text-start">
                                    <input type="hidden" name="accion" value="start">
                                    <input type="hidden" name="id_habitacion" value="<?= $fila['id_habitacion'] ?>">
                                    <p class="text-muted small mb-3">La habitación cambiará su estado y no podrá ser reservada hasta ser habilitada nuevamente.</p>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark small">Motivo del Mantenimiento:</label>
                                        <textarea class="form-control bg-light" name="motivo" rows="3" placeholder="Ej. Fuga de agua, pintura, cama en mal estado..." required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light mt-0">
                                    <button type="button" class="btn btn-light fw-bold text-secondary border" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-secondary fw-bold shadow-sm">Confirmar Bloqueo</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php elseif ($fila['estado'] == 'MANTENIMIENTO'): ?>
                <!-- Modal Finalizar Mantenimiento -->
                <div class="modal fade" id="modalMantenimientoEnd<?= $fila['id_habitacion'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title fw-bold"><i class="lni lni-checkmark-circle me-1"></i> Habilitar Hab. <?= $fila['numero'] ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="maintenance.php" method="POST">
                                <div class="modal-body p-4 text-start">
                                    <input type="hidden" name="accion" value="end">
                                    <input type="hidden" name="id_habitacion" value="<?= $fila['id_habitacion'] ?>">
                                    <p class="text-muted small mb-3">Registra los detalles de la resolución antes de liberar la habitación para su uso.</p>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark small">Detalles de la Resolución (Opcional):</label>
                                        <textarea class="form-control bg-light" name="detalle_resolucion" rows="3" placeholder="Ej. Tubería reparada, limpieza profunda realizada..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light mt-0">
                                    <button type="button" class="btn btn-light fw-bold text-secondary border" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-success fw-bold shadow-sm">Habilitar Habitación</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($fila['estado'] == 'DISPONIBLE' || $fila['estado'] == 'MANTENIMIENTO'): ?>
                <!-- Modal Editar Habitación -->
                <div class="modal fade" id="modalEditarHab<?= $fila['id_habitacion'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title fw-bold"><i class="lni lni-pencil-alt me-1"></i> Editar Habitación <?= $fila['numero'] ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="update.php" method="POST">
                                <div class="modal-body p-4 text-start">
                                    <input type="hidden" name="id_habitacion" value="<?= $fila['id_habitacion'] ?>">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold text-dark small">Número de Habitación:</label>
                                            <input type="number" class="form-control bg-light" name="numero" value="<?= htmlspecialchars($fila['numero']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label fw-bold text-dark small">Piso:</label>
                                            <input type="number" class="form-control bg-light" name="piso" value="<?= htmlspecialchars($fila['piso']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold text-dark small">Tipo de Habitación:</label>
                                        <select class="form-select bg-light" name="id_tipo" required>
                                            <?php foreach($tipos_habitacion as $tipo): ?>
                                                <option value="<?= $tipo['id_tipo'] ?>" <?= ($tipo['id_tipo'] == $fila['id_tipo']) ? 'selected' : '' ?>><?= htmlspecialchars($tipo['nombre']) ?> (Cap: <?= $tipo['capacidad'] ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer bg-light mt-0">
                                    <button type="button" class="btn btn-light fw-bold text-secondary border" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary fw-bold shadow-sm">Guardar Cambios</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endwhile; ?>
    <?php endif; ?>

    <script src="../assets/js/bootstrap.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="../assets/js/habitapp.js"></script>
</body>

</html>