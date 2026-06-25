<?php
session_start();

// Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado.");
}

require_once '../../conexion.php';
require_once '../../config.php';

// Incluir el autoloader de DomPDF
require_once '../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Capturar la fecha del filtro (por defecto hoy)
$fecha_filtro = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');

// Definir inicio y fin del periodo de 24 horas con corte a las 08:00 AM
$start_timestamp = $fecha_filtro . ' 08:00:00';
$end_timestamp = date('Y-m-d H:i:s', strtotime($start_timestamp . ' +24 hours'));

// Helper para obtener pasajeros de un query
function obtenerHuespedes($conexion, $sql, $start, $end) {
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ss", $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    $lista = [];
    while ($row = $result->fetch_assoc()) {
        $lista[] = $row;
    }
    return $lista;
}

// 1. QUERY: Pasajeros que Llegaron (Entradas en las últimas 24 horas)
$sql_llegaron = "SELECT h.*, r.checkin_at, r.checkout_at, r.nro_voucher, hab.numero as habitacion_numero, th.codigo as habitacion_tipo
                 FROM huesped h
                 JOIN reservas r ON h.reserva_id = r.id
                 JOIN habitacion hab ON h.habitacion_id = hab.id_habitacion
                 JOIN tipo_habitacion th ON hab.id_tipo = th.id_tipo
                 WHERE r.checkin_at BETWEEN ? AND ?
                 ORDER BY hab.numero ASC, h.es_principal DESC, h.id ASC";

$llegaron = obtenerHuespedes($conexion, $sql_llegaron, $start_timestamp, $end_timestamp);

// 2. QUERY: Pasajeros que Salieron (Salidas en las últimas 24 horas)
$sql_salieron = "SELECT h.*, r.checkin_at, r.checkout_at, r.nro_voucher, hab.numero as habitacion_numero, th.codigo as habitacion_tipo
                 FROM huesped h
                 JOIN reservas r ON h.reserva_id = r.id
                 JOIN habitacion hab ON h.habitacion_id = hab.id_habitacion
                 JOIN tipo_habitacion th ON hab.id_tipo = th.id_tipo
                 WHERE r.checkout_at BETWEEN ? AND ?
                 ORDER BY hab.numero ASC, h.es_principal DESC, h.id ASC";

$salieron = obtenerHuespedes($conexion, $sql_salieron, $start_timestamp, $end_timestamp);

// 3. QUERY: Pasajeros que Quedaron (Ocupación activa previa al corte que continuó o no salió antes del corte)
$sql_quedaron = "SELECT h.*, r.checkin_at, r.checkout_at, r.nro_voucher, hab.numero as habitacion_numero, th.codigo as habitacion_tipo
                 FROM huesped h
                 JOIN reservas r ON h.reserva_id = r.id
                 JOIN habitacion hab ON h.habitacion_id = hab.id_habitacion
                 JOIN tipo_habitacion th ON hab.id_tipo = th.id_tipo
                 WHERE r.checkin_at < ? 
                   AND (r.checkout_at IS NULL OR r.checkout_at >= ?)
                 ORDER BY hab.numero ASC, h.es_principal DESC, h.id ASC";

// Pasamos $start_timestamp para ambos argumentos (quienes hicieron check-in antes del inicio y no salieron antes del inicio)
$quedaron = obtenerHuespedes($conexion, $sql_quedaron, $start_timestamp, $start_timestamp);

// Función Helper para convertir imágenes a Base64
function imageToBase64($path) {
    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return '';
}

$logo1 = imageToBase64('../../assets/images/logo/logo_1.png');
$logo2 = imageToBase64('../../assets/images/logo/logo_pie.png');
$logo3 = imageToBase64('../../assets/images/logo/logo_3.png');

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Parte Cámara Hotelera - ' . date('d/m/Y', strtotime($fecha_filtro)) . '</title>
    <style>
        @page { 
            margin: 1.2cm 1cm 2.2cm 1cm; 
        }
        body { 
            font-family: "Helvetica", "Arial", sans-serif; 
            font-size: 8pt; 
            color: #222; 
            margin: 0; 
            padding: 0; 
        }
        .header-table { 
            width: 100%; 
            border-bottom: 2px solid #555; 
            padding-bottom: 8px; 
            margin-bottom: 12px; 
        }
        .header-table td {
            vertical-align: middle;
        }
        .title-block {
            text-align: center;
            margin-bottom: 10px;
        }
        .title-block h2 {
            margin: 0;
            font-size: 13pt;
            color: #111;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .title-block p {
            margin: 3px 0 0 0;
            font-size: 8.5pt;
            color: #555;
        }
        .section-header {
            background-color: #4a5568;
            color: #fff;
            font-weight: bold;
            padding: 4px 8px;
            font-size: 8pt;
            text-transform: uppercase;
            margin-top: 15px;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }
        .report-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 10px; 
        }
        .report-table th { 
            background-color: #f7fafc; 
            color: #2d3748; 
            font-weight: bold; 
            border: 1px solid #cbd5e0; 
            padding: 4px; 
            font-size: 7.5pt;
            text-align: center;
            text-transform: uppercase;
        }
        .report-table td { 
            border: 1px solid #cbd5e0; 
            padding: 4px; 
            vertical-align: middle; 
            font-size: 7.5pt;
        }
        .badge-minor {
            color: #e53e3e;
            font-weight: bold;
            font-size: 6.5pt;
        }
        .row-companion {
            background-color: #f7fafc;
        }
        .row-titular {
            background-color: #ffffff;
        }
        .footer { 
            position: fixed; 
            bottom: -1.4cm; 
            left: 0; 
            right: 0; 
            height: 1.3cm; 
            text-align: center; 
            font-size: 6.5pt; 
            color: #555; 
            border-top: 1px solid #cbd5e0; 
            padding-top: 4px; 
            line-height: 1.3;
        }
        .signature-table { 
            width: 100%; 
            margin-top: 25px; 
            border: 0; 
        }
        .signature-table td { 
            border: 0; 
            font-size: 8.5pt;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <!-- Encabezado Oficial -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td width="20%" align="left">
                ' . ($logo1 ? '<img src="'.$logo1.'" height="45">' : '') . '
            </td>
            <td width="60%" align="center">
                ' . ($logo2 ? '<img src="'.$logo2.'" style="max-height: 45px; max-width: 100%;">' : '<h3 style="margin:0;">HOTEL TERMINAL EPDEOR</h3>') . '
            </td>
            <td width="20%" align="right">
                ' . ($logo3 ? '<img src="'.$logo3.'" height="45">' : '') . '
            </td>
        </tr>
    </table>

    <div class="title-block">
        <h2>Parte Diario de Movimiento de Pasajeros</h2>
        <p>Cámara Departamental de Hotelería de Oruro</p>
        <p><strong>Corte de 24 Horas:</strong> Desde ' . date('d/m/Y H:i', strtotime($start_timestamp)) . ' hasta ' . date('d/m/Y H:i', strtotime($end_timestamp)) . '</p>
    </div>';

// Función para renderizar una tabla de sección
function renderSeccionTabla($titulo, $datos, $tipo) {
    $html = '<div class="section-header">' . $titulo . ' (' . count($datos) . ' personas)</div>';
    
    // Configurar columnas y anchos según el tipo
    $th_extra = '';
    $col_span = 8;
    if ($tipo === 'llegaron') {
        $th_extra = '<th width="8%">H. Ingreso</th>';
        $col_span = 9;
        $w_pieza = "5%";
        $w_nombre = "21%";
        $w_procedencia = "12%";
        $w_nacionalidad = "9%";
        $w_profesion = "12%";
        $w_doc = "9%";
        $w_edad = "5%";
        $w_civil = "11%";
    } elseif ($tipo === 'salieron') {
        $th_extra = '<th width="8%">H. Salida</th>';
        $col_span = 9;
        $w_pieza = "5%";
        $w_nombre = "21%";
        $w_procedencia = "12%";
        $w_nacionalidad = "9%";
        $w_profesion = "12%";
        $w_doc = "9%";
        $w_edad = "5%";
        $w_civil = "11%";
    } else { // quedaron
        $th_extra = '<th width="13%">F. y H. Llegada</th>';
        $col_span = 9;
        $w_pieza = "5%";
        $w_nombre = "18%";
        $w_procedencia = "11%";
        $w_nacionalidad = "8%";
        $w_profesion = "11%";
        $w_doc = "9%";
        $w_edad = "5%";
        $w_civil = "10%";
    }
    
    $html .= '<table class="report-table">
        <thead>
            <tr>
                <th width="' . $w_pieza . '">Pieza</th>' . 
                $th_extra . '
                <th width="' . $w_nombre . '">Nombre y Apellidos</th>
                <th width="' . $w_procedencia . '">Procedencia</th>
                <th width="' . $w_nacionalidad . '">Nacionalidad</th>
                <th width="' . $w_profesion . '">Profesión</th>
                <th width="' . $w_doc . '">Nº Documento</th>
                <th width="' . $w_edad . '">Edad</th>
                <th width="' . $w_civil . '">Estado Civil</th>
            </tr>
        </thead>
        <tbody>';
    
    if (count($datos) > 0) {
        foreach ($datos as $fila) {
            $es_menor = ($fila['edad'] !== null && $fila['edad'] < 18);
            
            $profesion = $es_menor ? '' : htmlspecialchars($fila['profesion'] ?? '');
            $estado_civil = $es_menor ? '' : htmlspecialchars($fila['estado_civil'] ?? '');
            
            $edad_mostrar = htmlspecialchars($fila['edad'] ?? '');
            if ($es_menor) {
                $edad_mostrar .= ' <span class="badge-minor">(Menor)</span>';
            }
            
            $row_class = $fila['es_principal'] ? 'row-titular' : 'row-companion';
            $nombre_prefijo = '';
            
            // Determinar contenido extra de la fila
            $td_extra = '';
            if ($tipo === 'llegaron') {
                $hora_ingreso = !empty($fila['checkin_at']) ? date('H:i', strtotime($fila['checkin_at'])) : '-';
                $td_extra = '<td class="text-center">' . $hora_ingreso . '</td>';
            } elseif ($tipo === 'salieron') {
                $hora_salida = !empty($fila['checkout_at']) ? date('H:i', strtotime($fila['checkout_at'])) : '-';
                $td_extra = '<td class="text-center">' . $hora_salida . '</td>';
            } else { // quedaron
                $fh_llegada = !empty($fila['checkin_at']) ? date('d/m/Y H:i', strtotime($fila['checkin_at'])) : '-';
                $td_extra = '<td class="text-center">' . $fh_llegada . '</td>';
            }
            
            $html .= '<tr class="' . $row_class . '">
                <td class="text-center fw-bold">' . htmlspecialchars($fila['habitacion_numero']) . '</td>' . 
                $td_extra . '
                <td>' . $nombre_prefijo . htmlspecialchars($fila['nombre_completo']) . '</td>
                <td>' . htmlspecialchars($fila['procedencia'] ?? '') . '</td>
                <td>' . htmlspecialchars($fila['nacionalidad'] ?? '') . '</td>
                <td>' . $profesion . '</td>
                <td>' . htmlspecialchars($fila['documento'] ?? '') . '</td>
                <td class="text-center">' . $edad_mostrar . '</td>
                <td class="text-center">' . $estado_civil . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="' . $col_span . '" class="text-center text-muted" style="padding: 10px;">Ningún pasajero registrado en esta sección.</td></tr>';
    }
    
    $html .= '</tbody></table>';
    return $html;
}

// Renderizar las 3 secciones
$html .= renderSeccionTabla("1. Pasajeros que Llegaron (Entradas)", $llegaron, 'llegaron');
$html .= renderSeccionTabla("2. Pasajeros que Salieron (Salidas)", $salieron, 'salieron');
$html .= renderSeccionTabla("3. Pasajeros que se Quedaron (Pernoctaron)", $quedaron, 'quedaron');

$html .= '
    <!-- Bloque de Firmas -->
    <table class="signature-table">
        <tr>
            <td width="40%" align="center">
                <br><br><br>
                ______________________________________<br>
                <strong>Firma Recepción</strong><br>
                <span>Hotel Terminal Oruro</span>
            </td>
            <td width="20%"></td>
            <td width="40%" align="center">
                <br><br><br>
                ______________________________________<br>
                <strong>Firma Administración</strong><br>
                <span>EPDEOR</span>
            </td>
        </tr>
    </table>

    <!-- Pie de Página Oficial (EPDEOR) -->
    <div class="footer">
        Dirección: Rajka Bacovick entre Aroma y Villarroel – Edificio Empresa Pública Departamental Hotel Terminal-Terminal de Buses de Oruro “EPDEOR”<br>
        Teléfonos: (591-2) 576389 – 579535 *Hotel Terminal de Oruro (591-2) 576227 | Correo: epdeororuro@gmail.com<br>
        Oruro – Bolivia
    </div>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream("parte-camara-hotelera-" . $fecha_filtro . ".pdf", array("Attachment" => false));
?>
