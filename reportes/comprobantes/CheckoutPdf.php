<?php
session_start();

// Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado.");
}

// Validar que la petición sea POST estricto
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id'])) {
    die("Solicitud inválida. El comprobante solo puede generarse desde el sistema.");
}

$id_reserva = intval($_POST['id']);

require_once '../../conexion.php';
require_once '../../config.php';

// Incluir el autoloader de DomPDF
require_once '../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// 1. Obtener datos de la reserva y las habitaciones
$sql_reserva = "SELECT r.*, 
               GROUP_CONCAT(h.numero SEPARATOR ', ') as numeros_habitaciones,
               GROUP_CONCAT(DISTINCT t.nombre SEPARATOR ', ') as tipos_habitaciones
        FROM reservas r 
        LEFT JOIN detalle_reserva dr ON r.id = dr.reserva_id
        LEFT JOIN habitacion h ON dr.habitacion_id = h.id_habitacion
        LEFT JOIN tipo_habitacion t ON h.id_tipo = t.id_tipo
        WHERE r.id = ?
        GROUP BY r.id";

$stmt = $conexion->prepare($sql_reserva);
$stmt->bind_param("i", $id_reserva);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();

if (!$reserva) {
    die("Reserva no encontrada en la base de datos.");
}

// 2. Obtener el historial de pagos de esta reserva
$sql_pagos = "SELECT * FROM pagos WHERE reserva_id = ? ORDER BY fecha ASC";
$stmt_pagos = $conexion->prepare($sql_pagos);
$stmt_pagos->bind_param("i", $id_reserva);
$stmt_pagos->execute();
$pagos = $stmt_pagos->get_result();

$lista_pagos = [];
$total_pagado = 0;
while ($pago = $pagos->fetch_assoc()) {
    $lista_pagos[] = $pago;
    $total_pagado += $pago['monto'];
}

// Calcular noches de estadía
$fecha_in = new DateTime($reserva['fecha_ingreso']);
$fecha_out = new DateTime($reserva['fecha_salida']);
$noches = max(1, $fecha_in->diff($fecha_out)->days);

// 3. Función Helper para convertir imágenes a Base64 (Previene errores de carga local)
function imageToBase64($path) {
    if (file_exists($path)) {
        $type = pathinfo($path, PATHINFO_EXTENSION);
        $data = file_get_contents($path);
        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }
    return '';
}

$logo1 = imageToBase64('../../assets/images/logo/logo_1.png');
$logo2 = imageToBase64('../../assets/images/logo/logo_2.png');
$logo3 = imageToBase64('../../assets/images/logo/logo_3.png');
$logo4 = imageToBase64('../../assets/images/logo/logo_4.png');

// 4. Construcción del HTML
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Estadía</title>
    <style>
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 11pt; color: #333; margin: 0; padding: 0; }
        .container { padding: 30px; }
        .header { width: 100%; border-bottom: 2px solid #680202; padding-bottom: 15px; margin-bottom: 20px; }
        .company-details { text-align: center; line-height: 1.3; }
        .company-details h1 { margin: 0; color: #680202; font-size: 18pt; letter-spacing: 1px; }
        .company-details p { margin: 2px 0; font-size: 9pt; }
        .section-title { background-color: #f4f4f4; padding: 5px 10px; border-left: 4px solid #680202; font-weight: bold; margin-bottom: 10px; font-size: 10pt; text-transform: uppercase; }
        .info-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; font-size: 10pt; }
        .info-table td { padding: 4px; vertical-align: top; }
        .info-table strong { color: #555; }
        .details-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
        .details-table th { background-color: #680202; color: #fff; padding: 8px; text-align: left; }
        .details-table td { border-bottom: 1px solid #ddd; padding: 8px; }
        .totals-table { width: 45%; float: right; border-collapse: collapse; font-size: 11pt; margin-bottom: 30px; }
        .totals-table td { padding: 6px; }
        .totals-table .total-row { font-weight: bold; font-size: 13pt; color: #680202; border-top: 2px solid #680202; }
        .footer { clear: both; margin-top: 40px; text-align: center; border-top: 1px solid #ddd; padding-top: 20px; font-size: 8pt; color: #777; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <table class="header" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td width="20%" class="text-center">' . ($logo1 ? '<img src="'.$logo1.'" style="max-height: 70px;">' : '') . '</td>
                <td width="60%" class="company-details">
                    ' . ($logo2 ? '<img src="'.$logo2.'" style="max-height: 40px; margin-bottom: 5px;"><br>' : '') . '
                    <h1>' . htmlspecialchars(HOTEL_NOMBRE) . '</h1>
                    <p><strong>' . htmlspecialchars(HOTEL_RAZON_SOCIAL) . '</strong></p>
                    <p>NIT: ' . htmlspecialchars(HOTEL_NIT) . '</p>
                    <p>' . htmlspecialchars(HOTEL_DIRECCION) . ' | ' . htmlspecialchars(HOTEL_CIUDAD) . '</p>
                    <p>Tel: ' . htmlspecialchars(HOTEL_TELEFONO) . ' | WA: ' . htmlspecialchars(HOTEL_WHATSAPP) . '</p>
                </td>
                <td width="20%" class="text-center">' . ($logo3 ? '<img src="'.$logo3.'" style="max-height: 70px;">' : '') . '</td>
            </tr>
        </table>

        <table style="width: 100%; margin-bottom: 15px;">
            <tr>
                <td width="50%"><div class="section-title" style="margin: 0;">DATOS DEL HUÉSPED</div></td>
                <td width="50%" class="text-right">
                    <h2 style="margin:0; color:#680202; font-size: 14pt;">COMPROBANTE N° ' . str_pad($reserva['id'], 6, '0', STR_PAD_LEFT) . '</h2>
                    <p style="margin:2px 0; font-size: 9pt;"><strong>Fecha de Emisión:</strong> ' . date('d/m/Y H:i') . '</p>
                </td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <td width="15%"><strong>Nombre:</strong></td><td width="35%">' . htmlspecialchars($reserva['nombre']) . '</td>
                <td width="15%"><strong>CI / DNI:</strong></td><td width="35%">' . htmlspecialchars($reserva['ci']) . '</td>
            </tr>
            <tr>
                <td><strong>Teléfono:</strong></td><td>' . htmlspecialchars($reserva['telefono'] ?? 'No registrado') . '</td>
                <td><strong>Check-in:</strong></td><td>' . date('d/m/Y', strtotime($reserva['fecha_ingreso'])) . '</td>
            </tr>
            <tr>
                <td><strong>Habitación:</strong></td><td>' . htmlspecialchars($reserva['numeros_habitaciones']) . ' (' . htmlspecialchars($reserva['tipos_habitaciones']) . ')</td>
                <td><strong>Check-out:</strong></td><td>' . date('d/m/Y', strtotime($reserva['fecha_salida'])) . ' (' . $noches . ' noches)</td>
            </tr>
        </table>

        <div class="section-title">DESGLOSE DE CARGOS Y PAGOS</div>
        <table class="details-table">
            <thead><tr><th>Fecha</th><th>Concepto</th><th>Método</th><th class="text-right">Importe (' . htmlspecialchars(MONEDA_SIMBOLO) . ')</th></tr></thead>
            <tbody>';

foreach ($lista_pagos as $index => $pago) {
    $concepto = ($index === 0) ? "Cobro de Estadía y Servicios (Check-in)" : "Consumo Extra / Penalidad";
    if (!empty($pago['detalle'])) { $concepto .= " - " . $pago['detalle']; }
    $html .= '<tr><td>' . date('d/m/Y H:i', strtotime($pago['fecha'])) . '</td><td>' . htmlspecialchars($concepto) . '</td><td>' . htmlspecialchars($pago['tipo_pago']) . '</td><td class="text-right">' . number_format($pago['monto'], 2) . '</td></tr>';
}

$html .= '  </tbody>
        </table>
        <table class="totals-table">
            <tr class="total-row"><td class="text-right">TOTAL ABONADO:</td><td class="text-right">' . htmlspecialchars(MONEDA_SIMBOLO) . ' ' . number_format($total_pagado, 2) . '</td></tr>
        </table>
        <div class="footer">
            ' . ($logo4 ? '<img src="'.$logo4.'" style="max-height: 50px; margin-bottom: 10px;"><br>' : '') . '
            <p><strong>Gracias por elegir ' . htmlspecialchars(HOTEL_NOMBRE) . '</strong></p>
            <p>Este documento es un comprobante de ingresos por estadía y consumos internos, no representa una factura fiscal.</p>
        </div>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("comprobante-checkout-" . $id_reserva . ".pdf", array("Attachment" => false));
?>