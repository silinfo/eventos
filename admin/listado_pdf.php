<?php
/** Listado de eventos en PDF con los mismos filtros que la pantalla de gestión */
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/pdf.php';
require_admin();

[$f, $sub] = filtros_gestion($_GET);
$eventos = eventos_listar($f);
$titulo = config('titulo_portal', 'Agenda de eventos');
$pdf = pdf_listado($eventos, $titulo, $sub);
$pdf->Output('I', 'eventos-' . date('Ymd') . '.pdf', true);
