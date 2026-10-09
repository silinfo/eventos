<?php
/** Cartel en PDF de un evento. ?descargar=1 fuerza la descarga. */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/pdf.php';
require_admin();

$ev = evento_obtener((int)($_GET['id'] ?? 0));
if (!$ev) {
    http_response_code(404);
    exit('Evento no encontrado.');
}
$pdf = pdf_cartel($ev);
$pdf->Output(!empty($_GET['descargar']) ? 'D' : 'I', 'cartel-' . nombre_fichero($ev['titulo']) . '.pdf', true);
