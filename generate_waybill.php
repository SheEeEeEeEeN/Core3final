<?php
require_once('fpdf/fpdf.php');
require_once('connection.php');
require_once('session.php');
require_once('shipment_helpers.php');

requireLogin();

class WaybillPDF extends FPDF
{
    public function Code39(float $x, float $y, string $code, float $narrow = 0.45, float $height = 16): void
    {
        $wide = $narrow * 3;
        $gap = $narrow;

        $patterns = [
            '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
            '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
            '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
            'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
            'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
            'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
            'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
            'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
            'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
            '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
            '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn',
        ];

        $code = '*' . strtoupper($code) . '*';

        for ($index = 0, $length = strlen($code); $index < $length; $index++) {
            $char = $code[$index];
            if (!isset($patterns[$char])) {
                continue;
            }

            $sequence = $patterns[$char];
            for ($bar = 0; $bar < 9; $bar++) {
                $lineWidth = $sequence[$bar] === 'n' ? $narrow : $wide;
                if ($bar % 2 === 0) {
                    $this->Rect($x, $y, $lineWidth, $height, 'F');
                }
                $x += $lineWidth;
            }
            $x += $gap;
        }
    }
}

$shipmentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($shipmentId <= 0) {
    exit('Missing shipment id.');
}

$whereExtra = '';
if (($_SESSION['role'] ?? '') === 'user') {
    $whereExtra = ' AND user_id = ' . (int) $_SESSION['user_id'];
}

$query = mysqli_query($conn, "SELECT * FROM shipments WHERE id = '{$shipmentId}'{$whereExtra} LIMIT 1");
$shipment = $query ? mysqli_fetch_assoc($query) : null;

if (!$shipment) {
    exit('Shipment not found.');
}

$trackingNo = getShipmentTrackingNo($shipment);
$logoPath = file_exists(__DIR__ . '/slate.png')
    ? __DIR__ . '/slate.png'
    : (file_exists(__DIR__ . '/Remorig.png') ? __DIR__ . '/Remorig.png' : null);

$pdf = new WaybillPDF('L', 'mm', [101.6, 152.4]);
$pdf->SetMargins(6, 6, 6);
$pdf->AddPage();
$pdf->SetAutoPageBreak(false);

$pageWidth = 152.4;
$pageHeight = 101.6;

// Background
$pdf->SetFillColor(248, 250, 252);
$pdf->Rect(0, 0, $pageWidth, $pageHeight, 'F');

// Header band
$pdf->SetFillColor(15, 23, 42);
$pdf->Rect(0, 0, $pageWidth, 20, 'F');

if ($logoPath) {
    $pdf->Image($logoPath, 7, 5, 22, 10);
}

$pdf->SetTextColor(255, 255, 255);
$pdf->SetXY(32, 5);
$pdf->SetFont('Helvetica', 'B', 18);
$pdf->Cell(60, 6, 'SLATE WAYBILL', 0, 2);
$pdf->SetFont('Helvetica', '', 8);
$pdf->SetTextColor(191, 219, 254);
$pdf->Cell(60, 4, 'Freight Management Label', 0, 0);

$pdf->SetXY(104, 5);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->Cell(42, 5, 'Tracking No.', 0, 2, 'R');
$pdf->SetFont('Helvetica', '', 11);
$pdf->Cell(42, 6, $trackingNo, 0, 0, 'R');

// Barcode section
$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor(203, 213, 225);
$pdf->Rect(6, 24, 140.4, 20, 'DF');
$pdf->SetFillColor(17, 24, 39);
$pdf->Code39(14, 29, $trackingNo, 0.45, 10);
$pdf->SetXY(10, 40);
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(132, 3, $trackingNo, 0, 0, 'C');

// Status chip
$statusLabel = match (normalizeShipmentStatus((string) ($shipment['status'] ?? 'PENDING'))) {
    'DELIVERED' => 'DELIVERED',
    'CANCELLED' => 'CANCELLED',
    'ARCHIVED' => 'ARCHIVED',
    'CONSOLIDATED' => 'CONSOLIDATED',
    'READY_TO_DISPATCH', 'IN_TRANSIT', 'ARRIVED', 'OUT_FOR_DELIVERY' => 'IN TRANSIT',
    default => 'PENDING',
};

$statusColor = match ($statusLabel) {
    'DELIVERED' => [16, 185, 129],
    'CANCELLED' => [239, 68, 68],
    'ARCHIVED' => [100, 116, 139],
    'CONSOLIDATED', 'IN TRANSIT' => [59, 130, 246],
    default => [245, 158, 11],
};

$pdf->SetFillColor($statusColor[0], $statusColor[1], $statusColor[2]);
$pdf->Rect(114, 46.5, 32, 8, 'F');
$pdf->SetXY(114, 48.5);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(32, 4, $statusLabel, 0, 0, 'C');

// Sender / Receiver cards
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetDrawColor(203, 213, 225);
$pdf->Rect(6, 48, 68, 33, 'DF');
$pdf->Rect(78, 48, 68, 33, 'DF');

$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetXY(9, 51);
$pdf->Cell(25, 5, 'Sender', 0, 0);
$pdf->SetXY(81, 51);
$pdf->Cell(30, 5, 'Receiver', 0, 0);

$pdf->SetFont('Helvetica', '', 8.5);
$pdf->SetXY(9, 57);
$pdf->MultiCell(61, 4.5, trim(($shipment['sender_name'] ?? 'N/A') . "\n" . ($shipment['sender_contact'] ?? '') . "\n" . ($shipment['origin_address'] ?? '')), 0, 'L');
$pdf->SetXY(81, 57);
$pdf->MultiCell(61, 4.5, trim(($shipment['receiver_name'] ?? 'N/A') . "\n" . ($shipment['receiver_contact'] ?? '') . "\n" . ($shipment['destination_address'] ?? '')), 0, 'L');

// Metrics strip
$pdf->SetFillColor(15, 23, 42);
$pdf->Rect(6, 84, 140, 11, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', 'B', 9);
$pdf->SetXY(10, 87);
$pdf->Cell(30, 4, 'Weight');
$pdf->Cell(30, 4, 'Price');
$pdf->Cell(35, 4, 'Payment');
$pdf->Cell(35, 4, 'Booked');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetXY(10, 91);
$pdf->Cell(30, 4, number_format((float) ($shipment['weight'] ?? 0), 2) . ' kg');
$pdf->Cell(30, 4, 'PHP ' . number_format((float) ($shipment['price'] ?? 0), 2));
$pdf->Cell(35, 4, strtoupper((string) ($shipment['payment_method'] ?? 'N/A')));
$pdf->Cell(35, 4, !empty($shipment['created_at']) ? date('M d, Y', strtotime($shipment['created_at'])) : 'N/A');

// Footer note
$notes = trim((string) ($shipment['specific_address'] ?? ($shipment['address'] ?? '')));
$packageDescription = trim((string) ($shipment['package_description'] ?? ''));
$footerText = 'Package: ' . ($packageDescription !== '' ? $packageDescription : 'General cargo');
if ($notes !== '') {
    $footerText .= ' | Notes: ' . $notes;
}

$pdf->SetTextColor(71, 85, 105);
$pdf->SetFont('Helvetica', '', 7.5);
$pdf->SetXY(6, 96.5);
$pdf->MultiCell(140, 3, $footerText, 0, 'L');

$pdf->Output('I', 'Waybill_' . preg_replace('/[^A-Za-z0-9\\-_]/', '_', $trackingNo) . '.pdf');
