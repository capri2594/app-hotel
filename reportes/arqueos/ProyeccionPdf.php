<?php
session_start();
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado.");
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['dias_proyeccion'])) die("Solicitud inválida.");

$dias_proyeccion = intval($_POST['dias_proyeccion']);

require_once '../../conexion.php';
require_once '../../config.php';
require_once '../libs/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Consultar reservas OCUPADAS actualmente y RESERVADAS que llegarán en X días
$sql = "SELECT r.*, 
        GROUP_CONCAT(h.numero SEPARATOR ', ') as numeros_habitaciones 
        FROM reservas r 
        LEFT JOIN detalle_reserva dr ON r.id = dr.reserva_id 
        LEFT JOIN habitacion h ON dr.habitacion_id = h.id_habitacion 
        WHERE r.estado IN ('OCUPADA', 'RESERVADA') 
        AND r.fecha_ingreso <= DATE_ADD(CURDATE(), INTERVAL ? DAY) 
        AND r.fecha_salida >= CURDATE()
        GROUP BY r.id 
        ORDER BY r.fecha_ingreso ASC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $dias_proyeccion);
$stmt->execute();
$reservas = $stmt->get_result();

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
    <title>Proyección Operativa - ' . $dias_proyeccion . ' Días</title>
    <style>
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 10pt; color: #333; margin: 0; }
        .container { padding: 30px; }
        .header { width: 100%; border-bottom: 2px solid #680202; padding-bottom: 10px; margin-bottom: 20px; }
        .company-details { text-align: center; line-height: 1.2; }
        .company-details h1 { margin: 0; color: #680202; font-size: 16pt; }
        .section-title { background-color: #f4f4f4; padding: 5px 10px; border-left: 4px solid #ffc107; color: #000; font-weight: bold; margin-bottom: 10px; text-transform: uppercase; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 9pt; }
        .data-table th { background-color: #333; color: #fff; padding: 8px; text-align: left; border: 1px solid #222; }
        .data-table td { border: 1px solid #ddd; padding: 8px; }
        .badge { padding: 3px 6px; color: white; border-radius: 4px; font-size: 8pt; font-weight: bold; }
        .badge-ocupada { background-color: #dc3545; }
        .badge-reservada { background-color: #ffc107; color: #000; }
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
            <h2 style="margin: 0; color: #333;">PROYECCIÓN OPERATIVA (' . $dias_proyeccion . ' DÍAS)</h2>
            <p style="margin: 5px 0;"><strong>Emitido el:</strong> ' . date('d/m/Y H:i') . ' | <strong>Por:</strong> ' . htmlspecialchars($_SESSION['nombre_completo']) . '</p>
            <p style="font-size: 8pt; color: #666;">Este reporte ayuda a prever salidas y llegadas para organizar Limpieza y Recepción.</p>
        </div>

        <div class="section-title">Habitaciones Comprometidas</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th width="15%">Estado</th>
                    <th width="15%">Habitaciones</th>
                    <th width="35%">Huésped / Titular</th>
                    <th width="15%">Ingreso</th>
                    <th width="20%">Salida (Check-out)</th>
                </tr>
            </thead>
            <tbody>';

if ($reservas->num_rows > 0) {
    while ($reserva = $reservas->fetch_assoc()) {
        $clase_badge = ($reserva['estado'] == 'OCUPADA') ? 'badge-ocupada' : 'badge-reservada';
        
        // Resaltar fechas de salida que son HOY
        $salida = date('d/m/Y', strtotime($reserva['fecha_salida']));
        if ($reserva['fecha_salida'] == date('Y-m-d')) {
            $salida = '<strong style="color: #dc3545;">' . $salida . ' (HOY)</strong>';
        }

        $html .= '<tr>
            <td><span class="badge ' . $clase_badge . '">' . $reserva['estado'] . '</span></td>
            <td style="font-weight: bold; color: #0056b3;">' . htmlspecialchars($reserva['numeros_habitaciones'] ?? 'S/A') . '</td>
            <td>' . htmlspecialchars($reserva['nombre']) . '<br><span style="font-size:8pt; color:#666;">CI: ' . htmlspecialchars($reserva['ci']) . '</span></td>
            <td>' . date('d/m/Y', strtotime($reserva['fecha_ingreso'])) . '</td>
            <td>' . $salida . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" class="text-center">No hay reservas programadas para los próximos ' . $dias_proyeccion . ' días.</td></tr>';
}

$html .= '  </tbody>
        </table>
        <p style="text-align: right; font-size: 8pt; color: #777;">Reporte generado automáticamente por HabitApp.</p>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();
$dompdf->stream("proyeccion-" . $dias_proyeccion . "-dias.pdf", array("Attachment" => false));