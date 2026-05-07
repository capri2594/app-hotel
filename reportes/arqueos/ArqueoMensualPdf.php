<?php
session_start();
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado.");
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['mes_arqueo'])) die("Solicitud inválida.");

$mes_arqueo = $_POST['mes_arqueo']; // Formato YYYY-MM

require_once '../../conexion.php';
require_once '../../config.php';
require_once '../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Consultar pagos exactos de este mes
$sql = "SELECT p.*, r.nombre as huesped 
        FROM pagos p 
        LEFT JOIN reservas r ON p.reserva_id = r.id 
        WHERE DATE_FORMAT(p.fecha, '%Y-%m') = ? 
        ORDER BY p.fecha ASC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $mes_arqueo);
$stmt->execute();
$pagos = $stmt->get_result();

$lista_pagos = [];
$totales = ['EFECTIVO' => 0, 'QR' => 0, 'DEPOSITO' => 0];
$total_general = 0;

while ($pago = $pagos->fetch_assoc()) {
    $lista_pagos[] = $pago;
    $totales[$pago['tipo_pago']] += $pago['monto'];
    $total_general += $pago['monto'];
}

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
    <title>Reporte de Ingresos Mensuales</title>
    <style>
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 10pt; color: #333; margin: 0; }
        .container { padding: 30px; }
        .header { width: 100%; border-bottom: 2px solid #680202; padding-bottom: 10px; margin-bottom: 20px; }
        .company-details { text-align: center; line-height: 1.2; }
        .company-details h1 { margin: 0; color: #680202; font-size: 16pt; }
        .section-title { background-color: #f4f4f4; padding: 5px 10px; border-left: 4px solid #680202; font-weight: bold; margin-bottom: 10px; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }
        .data-table th { background-color: #680202; color: #fff; padding: 6px; text-align: left; border: 1px solid #500000; }
        .data-table td { border: 1px solid #ddd; padding: 6px; }
        .summary-table { width: 40%; float: right; border-collapse: collapse; font-size: 10pt; margin-bottom: 40px; }
        .summary-table th { background-color: #f4f4f4; padding: 6px; border: 1px solid #ddd; text-align: left;}
        .summary-table td { padding: 6px; border: 1px solid #ddd; text-align: right; }
        .total-row { font-weight: bold; font-size: 11pt; background-color: #e2e3e5; color: #000; }
        .signatures { clear: both; margin-top: 60px; width: 100%; text-align: center; }
        .signatures td { padding-top: 50px; border-top: 1px solid #333; width: 40%; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <table class="header" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td width="20%" class="text-center">' . ($logo1 ? '<img src="'.$logo1.'" style="max-height: 60px;">' : '') . '</td>
                <td width="60%" class="company-details">
                    ' . ($logo2 ? '<img src="'.$logo2.'" style="width: 100%; max-width: 400px; margin-bottom: 5px;"><br>' : '') . '
                    <h1>' . htmlspecialchars(HOTEL_NOMBRE) . '</h1>
                    <p>NIT: ' . htmlspecialchars(HOTEL_NIT) . '</p>
                </td>
                <td width="20%" class="text-center">' . ($logo3 ? '<img src="'.$logo3.'" style="max-height: 60px;">' : '') . '</td>
            </tr>
        </table>

        <div style="text-align: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: #680202;">REPORTE MENSUAL DE INGRESOS</h2>
            <p style="margin: 5px 0;"><strong>Mes de Arqueo:</strong> ' . date('m/Y', strtotime($mes_arqueo . '-01')) . ' | <strong>Emitido por:</strong> ' . htmlspecialchars($_SESSION['nombre_completo']) . '</p>
        </div>

        <div class="section-title">Historial de Transacciones del Mes</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="15%">Fecha/Hora</th>
                    <th width="28%">Huésped / Cliente</th>
                    <th width="32%">Concepto</th>
                    <th width="13%">Método</th>
                    <th width="12%" class="text-right">Monto (' . htmlspecialchars(MONEDA_SIMBOLO) . ')</th>
                </tr>
            </thead>
            <tbody>';

if (count($lista_pagos) > 0) {
    foreach ($lista_pagos as $pago) {
        $concepto = $pago['detalle'] ?? 'Abono de Estadía';
        $html .= '<tr>
            <td>' . date('d/m/Y H:i', strtotime($pago['fecha'])) . '</td>
            <td>' . htmlspecialchars($pago['huesped'] ?? 'N/A') . '</td>
            <td>' . htmlspecialchars($concepto) . '</td>
            <td>' . htmlspecialchars($pago['tipo_pago']) . '</td>
            <td class="text-right">' . number_format($pago['monto'], 2) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" class="text-center">No se registraron ingresos en este mes.</td></tr>';
}

$html .= '  </tbody>
        </table>

        <table class="summary-table">
            <tr><th>Total Efectivo:</th><td>' . number_format($totales['EFECTIVO'], 2) . '</td></tr>
            <tr><th>Total Transferencias QR:</th><td>' . number_format($totales['QR'], 2) . '</td></tr>
            <tr><th>Total Depósitos:</th><td>' . number_format($totales['DEPOSITO'], 2) . '</td></tr>
            <tr class="total-row"><th>INGRESO MENSUAL:</th><td>' . htmlspecialchars(MONEDA_SIMBOLO) . ' ' . number_format($total_general, 2) . '</td></tr>
        </table>

        <table class="signatures" border="0" align="center" style="border-collapse: separate; border-spacing: 50px 0;">
            <tr>
                <td>Generado por (Sistema HabitApp)</td>
                <td>Revisado por (Administración)</td>
            </tr>
        </table>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("arqueo-mensual-" . $mes_arqueo . ".pdf", array("Attachment" => false));