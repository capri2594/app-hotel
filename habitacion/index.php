<?php include '../conexion.php'; ?>
<!DOCTYPE html>
<html class="no-js" lang="zxx">

<!-- Start Header Area -->
<?php
include '../header.php'; 

// Validar el piso seleccionado (por defecto 4, permitido de 4 a 8)
$piso_actual = isset($_GET['piso']) && in_array($_GET['piso'], [4, 5, 6, 7, 8]) ? intval($_GET['piso']) : 4;
$piso_base = $piso_actual * 100;

// Consulta segura con prepared statements (incluimos id_habitacion para reservar)
$sql = "SELECT h.id_habitacion, h.numero, t.codigo, t.nombre, h.estado
        FROM habitacion h 
        INNER JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo 
        WHERE h.piso = ? 
        ORDER BY h.numero ASC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $piso_actual);
$stmt->execute();
$resultado = $stmt->get_result();

// Clasificar habitaciones dinámicamente según el piso
$superior = []; 
$inferior = []; 

if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $num = intval($fila['numero']);
        if ($num >= ($piso_base + 9) && $num <= ($piso_base + 18)) {
            $superior[$num] = $fila;
        } elseif ($num >= ($piso_base + 1) && $num <= ($piso_base + 8)) {
            $inferior[$num] = $fila;
        }
    }
}
?>
<body>
  <?php include '../nav.php'; ?>
    <!-- Start Hero Area -->
    <section id="home" class="hero-area">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-5 col-md-12 col-12">
                    <div class="hero-content">
                        <h1 class="wow fadeInLeft" data-wow-delay=".4s">A powerful app for your business.</h1>
                        <p class="wow fadeInLeft" data-wow-delay=".6s">From open source to pro services, Piqes helps you
                            to build, deploy, test, and monitor apps.</p>
                        <div class="button wow fadeInLeft" data-wow-delay=".8s">
                            <a href="javascript:void(0)" class="btn"><i class="lni lni-apple"></i> App Store</a>
                            <a href="javascript:void(0)" class="btn btn-alt"><i class="lni lni-play-store"></i> Google
                                Play</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 col-md-12 col-12">
                    <div class="hero-image wow fadeInRight" data-wow-delay=".4s">
                        <img src="../assets/images/hero/phone.png" alt="#">
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- End Hero Area -->
    <!-- Start Features Area -->
    <section id="features" class="features section">
      <div class="container">
        <div class="row">
          <div class="col-12">
            <div class="section-title">
              <h2 class="wow zoomIn" data-wow-delay=".2s">HABITACIONES Piso <?= $piso_actual ?></h2>
              <h3 class="wow fadeInUp" data-wow-delay=".4s">Habitaciones del <?= $piso_base + 1 ?> al <?= $piso_base + 18 ?></h3>
            </div>
          </div>
        </div>

        <!-- Selector de Pisos -->
        <div class="row mb-4">
          <div class="col-12 text-center">
            <div class="btn-group" role="group" aria-label="Selector de Pisos">
              <?php for($i = 4; $i <= 8; $i++): ?>
                <a href="?piso=<?= $i ?>" class="btn <?= $i == $piso_actual ? 'btn-primary' : 'btn-outline-primary' ?>">
                  Piso <?= $i ?>
                </a>
              <?php endfor; ?>
            </div>
          </div>
        </div>

                        <div class="container my-4">
                <table class="table table-bordered text-center align-middle" style="table-layout: fixed; width: 100%;">
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
                      <td>
                        <img src="../assets/images/DISPONIBLE.gif" alt="Disponible" style="width: 40px; height: 40px;">
                      </td>
                      <td>
                        <img src="../assets/images/OCUPADA.gif" alt="Ocupado" style="width: 40px; height: 40px;">
                      </td>
                      <td>
                        <img src="../assets/images/RESERVADA.gif" alt="Reservado" style="width: 40px; height: 40px;">
                      </td>
                      <td>
                        <img src="../assets/images/MANTENIMIENTO.gif" alt="Mantenimiento" style="width: 40px; height: 40px;">
                      </td>
                    </tr>
                  </tbody>
                </table>
</div>
      </div>
    </section>
  <div class="table-responsive">
    <table class="table table-bordered table-sm text-center align-middle" style="table-layout: fixed;">
      <tbody>
        <!-- 🔼 Fila superior dinámica -->
        <tr>
          <?php
          for ($n = $piso_base + 9; $n <= $piso_base + 18; $n++) {
            echo '<td style="position: relative; height: 120px;">';
            if (isset($superior[$n])) {
              $hab = $superior[$n];
              $estado = strtoupper($hab['estado']);
              switch ($estado) {
                case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; break;
                case 'OCUPADA':        $gif = 'OCUPADA.gif'; break;
                case 'RESERVADA':      $gif = 'RESERVADA.gif'; break;
                case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; break;
                default:               $gif = 'default.gif'; break;
              }
              
              // Si está disponible, la envolvemos en un enlace para reservar
              if ($estado == 'DISPONIBLE') { echo '<a href="reservar.php?id='.$hab['id_habitacion'].'" style="display:block; color:inherit;">'; }
              
              echo '<div style="padding-bottom: 30px;">'
                  .'<strong>'.htmlspecialchars($hab['numero']).'</strong><br>'
                  .htmlspecialchars($hab['nombre']).'</div>'
                  .'<img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
              
              if ($estado == 'DISPONIBLE') { echo '</a>'; }
            } else {
              echo $n;
            }
            echo '</td>';
          }
          ?>
        </tr>

        <!-- 🟨 Fila central: PASILLO -->
        <tr>
          <td colspan="10" class="bg-warning fw-bold" style="height: 40px;">PASILLO</td>
        </tr>

        <!-- 🔽 Fila inferior dinámica con INGRESO -->
        <tr>
          <?php
          $primeras = [$piso_base+8, $piso_base+7, $piso_base+6, $piso_base+5];
          foreach ($primeras as $n) {
            echo '<td style="position: relative; height: 120px;">';
            if (isset($inferior[$n])) {
              $hab = $inferior[$n];
              $estado = strtoupper($hab['estado']);
              switch ($estado) {
                case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; break;
                case 'OCUPADA':        $gif = 'OCUPADA.gif'; break;
                case 'RESERVADA':      $gif = 'RESERVADA.gif'; break;
                case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; break;
                default:               $gif = 'default.gif'; break;
              }
              if ($estado == 'DISPONIBLE') { echo '<a href="reservar.php?id='.$hab['id_habitacion'].'" style="display:block; color:inherit;">'; }
              
              echo '<div style="padding-bottom: 30px;">'
                  .'<strong>'.htmlspecialchars($hab['numero']).'</strong><br>'
                  .htmlspecialchars($hab['nombre']).'</div>'
                  .'<img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
              
              if ($estado == 'DISPONIBLE') { echo '</a>'; }
            } else {
              echo $n;
            }
            echo '</td>';
          }

          // INGRESO
          echo '<td colspan="2" class="bg-primary text-white fw-bold" style="height: 120px;">INGRESO</td>';

          $ultimas = [$piso_base+4, $piso_base+3, $piso_base+2, $piso_base+1];
          foreach ($ultimas as $n) {
            echo '<td style="position: relative; height: 120px;">';
            if (isset($inferior[$n])) {
              $hab = $inferior[$n];
              $estado = strtoupper($hab['estado']);
              switch ($estado) {
                case 'DISPONIBLE':     $gif = 'DISPONIBLE.gif'; break;
                case 'OCUPADA':        $gif = 'OCUPADA.gif'; break;
                case 'RESERVADA':      $gif = 'RESERVADA.gif'; break;
                case 'MANTENIMIENTO':  $gif = 'MANTENIMIENTO.gif'; break;
                default:               $gif = 'default.gif'; break;
              }
              if ($estado == 'DISPONIBLE') { echo '<a href="reservar.php?id='.$hab['id_habitacion'].'" style="display:block; color:inherit;">'; }
              
              echo '<div style="padding-bottom: 30px;">'
                  .'<strong>'.htmlspecialchars($hab['numero']).'</strong><br>'
                  .htmlspecialchars($hab['nombre']).'</div>'
                  .'<img src="../assets/images/'.$gif.'" alt="'.$estado.'" style="position: absolute; bottom: 5px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px;">';
                  
              if ($estado == 'DISPONIBLE') { echo '</a>'; }
            } else {
              echo $n;
            }
            echo '</td>';
          }
          ?>
        </tr>
      </tbody>
    </table>
  </div>
</div>

    
    <!-- End Features Area -->
    <?php include '../footer.php'; ?>
</body>

</html>