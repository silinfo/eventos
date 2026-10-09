<?php
/** Cartel público en PDF de un evento publicado (enlazado desde la ficha y el .ics) */
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/pdf.php';

$ev = evento_obtener((int)($_GET['id'] ?? 0));
if (!$ev || !$ev['publicado']) {
    http_response_code(404);
    exit('Evento no encontrado.');
}
pdf_cartel($ev)->Output('I', 'cartel-' . nombre_fichero($ev['titulo']) . '.pdf', true);
