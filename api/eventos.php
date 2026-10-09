<?php
/**
 * API pública (solo lectura) de eventos publicados, en JSON.
 *   GET api/eventos.php?desde=2026-01-01&hasta=2026-01-31&tipo=formacion
 * Por defecto devuelve los próximos 12 meses.
 * Sirve al calendario y para integrar la agenda en otras páginas del portal.
 */
require __DIR__ . '/../inc/bootstrap.php';

$desde = (string)($_GET['desde'] ?? '');
$hasta = (string)($_GET['hasta'] ?? '');
if (!es_fecha($desde)) {
    $desde = date('Y-m-d');
}
if (!es_fecha($hasta)) {
    $hasta = date('Y-m-d', strtotime('+1 year'));
}

$eventos = eventos_listar([
    'desde'           => $desde,
    'hasta'           => $hasta,
    'tipo'            => (string)($_GET['tipo'] ?? ''),
    'solo_publicados' => true,
]);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'desde'   => $desde,
    'hasta'   => $hasta,
    'tipos'   => array_map(fn($t) => ['nombre' => $t['nombre'], 'color' => $t['color']], TIPOS),
    'eventos' => array_map('evento_publico', $eventos),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
