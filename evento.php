<?php
/**
 * Ficha pública de un evento. Con ?ics=1 descarga el evento en formato iCalendar.
 */
require __DIR__ . '/inc/bootstrap.php';

$ev = evento_obtener((int)($_GET['id'] ?? 0));
if (!$ev || !$ev['publicado']) {
    http_response_code(404);
    exit('Evento no encontrado.');
}
$embed = !empty($_GET['embed']);

if (!empty($_GET['ics'])) {
    $esc = fn($s) => str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n"], (string)$s);
    $tz = config('timezone', 'Europe/Madrid');
    if ($ev['hora_inicio']) {
        $ini = date('Ymd\THis', strtotime($ev['fecha'] . ' ' . $ev['hora_inicio']));
        $finFecha = $ev['fecha_fin'] ?: $ev['fecha'];
        $finHora = $ev['hora_fin'] ?: date('H:i:s', strtotime($ev['hora_inicio'] . ' +1 hour'));
        $fin = date('Ymd\THis', strtotime("$finFecha $finHora"));
        $dt = "DTSTART;TZID=$tz:$ini\r\nDTEND;TZID=$tz:$fin";
    } else {
        $fin = date('Ymd', strtotime(($ev['fecha_fin'] ?: $ev['fecha']) . ' +1 day'));
        $dt = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($ev['fecha'])) . "\r\nDTEND;VALUE=DATE:$fin";
    }
    $cartel = url_absoluta('cartel.php?id=' . $ev['id']);
    $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'suap');
    $lineas = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//SUAP//Eventos//ES', 'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        'UID:evento-' . $ev['id'] . '@' . $host,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        $dt,
        'SUMMARY:' . $esc($ev['titulo']),
        'DESCRIPTION:' . $esc(trim($ev['descripcion']
            . ($ev['url'] ? "\n\nMás información: " . $ev['url'] : '')
            . "\n\nCartel: " . $cartel)),
        'LOCATION:' . $esc($ev['lugar']),
        'CATEGORIES:' . $esc(tipo_nombre($ev['tipo'])),
    ];
    $lineas[] = 'URL:' . ($ev['url'] ?: url_absoluta('evento.php?id=' . $ev['id']));
    $lineas[] = 'ATTACH;FMTTYPE=application/pdf:' . $cartel;
    $lineas[] = 'END:VEVENT';
    $lineas[] = 'END:VCALENDAR';
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="evento-' . $ev['id'] . '.ics"');
    // RFC 5545: líneas de máx. 75 octetos, continuadas con un espacio
    $plegar = function (string $l): string {
        $out = '';
        $max = 75;
        while (strlen($l) > $max) {
            $corte = $max;
            while ($corte > 0 && (ord($l[$corte]) & 0xC0) === 0x80) {
                $corte--; // no partir caracteres UTF-8
            }
            $out .= substr($l, 0, $corte) . "\r\n ";
            $l = substr($l, $corte);
            $max = 74; // las líneas de continuación empiezan por un espacio
        }
        return $out . $l;
    };
    echo implode("\r\n", array_map($plegar, $lineas)) . "\r\n";
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ev['titulo']) ?> · <?= e(config('organizacion')) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#b91c1c">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Agenda SUAP">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<?= favicon_html() ?>
<style><?= tipos_css() ?></style>
</head>
<body class="publico<?= $embed ? ' embed' : '' ?>">
<main class="contenedor estrecho">
  <p><a href="index.php<?= $embed ? '?embed=1' : '' ?>">← Volver a la agenda</a></p>
  <article class="ficha tarjeta t-<?= e($ev['tipo']) ?>">
    <span class="etiqueta"><?= e(tipo_nombre($ev['tipo'])) ?></span>
    <h1><?= e($ev['titulo']) ?></h1>
    <ul class="datos">
      <li><b>Fecha</b><?= e(evento_fechas($ev)) ?></li>
      <?php if ($h = evento_horario($ev)): ?><li><b>Horario</b><?= e($h) ?></li><?php endif; ?>
      <?php if ($ev['duracion']): ?><li><b>Duración</b><?= e($ev['duracion']) ?></li><?php endif; ?>
      <?php if ($ev['lugar']): ?><li><b>Lugar</b><?= e($ev['lugar']) ?></li><?php endif; ?>
    </ul>
    <?php if ($ev['descripcion']): ?>
      <div class="descripcion"><?= nl2br(e($ev['descripcion'])) ?></div>
    <?php endif; ?>
    <p class="acciones">
      <?php if ($ev['url']): ?><a class="btn" href="<?= e($ev['url']) ?>" target="_blank" rel="noopener">Más información ↗</a><?php endif; ?>
      <a class="btn sec" href="cartel.php?id=<?= (int)$ev['id'] ?>" target="_blank">Ver cartel (PDF)</a>
      <a class="btn sec" href="evento.php?id=<?= (int)$ev['id'] ?>&amp;ics=1">Añadir a mi calendario</a>
    </p>
  </article>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
