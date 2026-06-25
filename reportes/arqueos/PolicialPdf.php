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

// Capturar la fecha del filtro (por defecto la fecha actual)
$fecha_filtro = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');

// Consulta para obtener huéspedes que estuvieron hospedados en la fecha seleccionada
$sql = "SELECT h.*, r.checkin_at, r.checkout_at, r.nro_voucher, hab.numero as habitacion_numero, th.codigo as habitacion_tipo
        FROM huesped h
        JOIN reservas r ON h.reserva_id = r.id
        JOIN habitacion hab ON h.habitacion_id = hab.id_habitacion
        JOIN tipo_habitacion th ON hab.id_tipo = th.id_tipo
        WHERE DATE(r.checkin_at) <= ? 
          AND (r.checkout_at IS NULL OR DATE(r.checkout_at) >= ?)
        ORDER BY hab.numero ASC, h.es_principal DESC, h.id ASC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $fecha_filtro, $fecha_filtro);
$stmt->execute();
$resultado = $stmt->get_result();

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
    <title>Parte Policial - ' . date('d/m/Y', strtotime($fecha_filtro)) . '</title>
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
            margin-bottom: 15px; 
        }
        .header-table td {
            vertical-align: middle;
        }
        .title-block {
            text-align: center;
            margin-bottom: 15px;
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
            font-size: 9pt;
            color: #555;
        }
        .report-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
        }
        .report-table th { 
            background-color: #f0f0f0; 
            color: #111; 
            font-weight: bold; 
            border: 1px solid #ccc; 
            padding: 5px; 
            font-size: 7.5pt;
            text-align: center;
            text-transform: uppercase;
        }
        .report-table td { 
            border: 1px solid #ccc; 
            padding: 5px; 
            vertical-align: middle; 
            font-size: 7.5pt;
        }
        .row-companion {
            background-color: #fafafa;
        }
        .row-titular {
            background-color: #ffffff;
        }
        .badge-minor {
            color: #d9534f;
            font-weight: bold;
            font-size: 6.5pt;
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
            border-top: 1px solid #ccc; 
            padding-top: 4px; 
            line-height: 1.3;
        }
        .signature-table { 
            width: 100%; 
            margin-top: 30px; 
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
    <!-- Encabezado Oficial con Logos Triples -->
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
        <h2>Parte Policial de Control de Huéspedes</h2>
        <p><strong>Fecha del Reporte:</strong> ' . date('d/m/Y', strtotime($fecha_filtro)) . '</p>
    </div>

    <!-- Tabla de Reporte -->
    <table class="report-table">
        <thead>
            <tr>
                <th width="7%">H. Ingreso</th>
                <th width="20%">Nombre y Apellidos</th>
                <th width="11%">Procedencia</th>
                <th width="10%">Nacionalidad</th>
                <th width="12%">Profesión</th>
                <th width="9%">Nº Carnet</th>
                <th width="5%">Edad</th>
                <th width="8%">Estado Civil</th>
                <th width="5%">Pieza</th>
                <th width="5%">Tipo</th>
                <th width="8%">Nº Voucher</th>
                <th width="7%">H. Salida</th>
            </tr>
        </thead>
        <tbody>';

if ($resultado && $resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        $es_menor = ($fila['edad'] !== null && $fila['edad'] < 18);
        
        $hora_ingreso = !empty($fila['checkin_at']) ? date('H:i', strtotime($fila['checkin_at'])) : '-';
        $hora_salida = !empty($fila['checkout_at']) ? date('H:i', strtotime($fila['checkout_at'])) : '-';
        
        $profesion = $es_menor ? '' : htmlspecialchars($fila['profesion'] ?? '');
        $estado_civil = $es_menor ? '' : htmlspecialchars($fila['estado_civil'] ?? '');
        
        $edad_mostrar = htmlspecialchars($fila['edad'] ?? '');
        if ($es_menor) {
            $edad_mostrar .= ' <span class="badge-minor">(Menor)</span>';
        }
        
        $row_class = $fila['es_principal'] ? 'row-titular' : 'row-companion';
        $nombre_prefijo = '';
        
        $html .= '<tr class="' . $row_class . '">
            <td class="text-center">' . $hora_ingreso . '</td>
            <td>' . $nombre_prefijo . htmlspecialchars($fila['nombre_completo']) . '</td>
            <td>' . htmlspecialchars($fila['procedencia'] ?? '') . '</td>
            <td>' . htmlspecialchars($fila['nacionalidad'] ?? '') . '</td>
            <td>' . $profesion . '</td>
            <td>' . htmlspecialchars($fila['documento'] ?? '') . '</td>
            <td class="text-center">' . $edad_mostrar . '</td>
            <td class="text-center">' . $estado_civil . '</td>
            <td class="text-center fw-bold">' . htmlspecialchars($fila['habitacion_numero']) . '</td>
            <td class="text-center">' . htmlspecialchars($fila['habitacion_tipo']) . '</td>
            <td class="text-center">' . str_pad($fila['nro_voucher'] ?? '', 5, '0', STR_PAD_LEFT) . '</td>
            <td class="text-center">' . $hora_salida . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="12" class="text-center text-muted" style="padding: 15px;">No se registraron huéspedes hospedados en esta fecha.</td></tr>';
}

$html .= '
        </tbody>
    </table>

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
$dompdf->stream("parte-policial-" . $fecha_filtro . ".pdf", array("Attachment" => false));
?>
