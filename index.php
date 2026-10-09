<?php
/**
 * Portal público: agenda de eventos del SUAP en formato lista y calendario.
 * Para incrustarlo en la página inicial del portal:
 *   <iframe src="https://.../eventos/index.php?embed=1" style="width:100%;height:900px;border:0"></iframe>
 * Parámetros: embed=1 (sin cabecera), vista=lista|calendario, tipo=formacion|organizacion|convivencia
 */
require __DIR__ . '/inc/bootstrap.php';

$embed = !empty($_GET['embed']);
$vista = ($_GET['vista'] ?? '') === 'calendario' ? 'calendario' : 'lista';
$tipo  = isset(TIPOS[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : '';

$proximos = eventos_listar([
    'desde'           => date('Y-m-d'),
    'tipo'            => $tipo,
    'solo_publicados' => true,
]);

// Agrupar por mes
$porMes = [];
foreach ($proximos as $ev) {
    $clave = substr(max($ev['fecha'], date('Y-m-d')), 0, 7);
    $porMes[$clave][] = $ev;
}

$titulo = config('titulo_portal', 'Agenda de eventos');
$qs = fn(array $extra) => '?' . http_build_query(array_filter(array_merge(
    ['embed' => $embed ? 1 : null, 'vista' => $vista, 'tipo' => $tipo], $extra
), fn($v) => $v !== null && $v !== ''));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · <?= e(config('organizacion')) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
<style><?= tipos_css() ?></style>
</head>
<body class="publico<?= $embed ? ' embed' : '' ?>">
<?php if (!$embed): ?>
<header class="cabecera">
  <div class="contenedor">
    <div>
      <p class="org"><?= e(config('organizacion')) ?></p>
      <h1><?= e($titulo) ?></h1>
    </div>
  </div>
</header>
<?php endif; ?>

<main class="contenedor">
  <div class="barra">
    <nav class="filtros" aria-label="Filtrar por tipo">
      <a href="<?= e($qs(['tipo' => null])) ?>" class="chip<?= $tipo === '' ? ' activo' : '' ?>">Todos</a>
      <?php foreach (TIPOS as $k => $t): ?>
        <a href="<?= e($qs(['tipo' => $k])) ?>" class="chip t-<?= $k ?><?= $tipo === $k ? ' activo' : '' ?>">
          <span class="punto"></span><?= e($t['nombre']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="vistas" role="tablist">
      <a href="<?= e($qs(['vista' => 'lista'])) ?>" data-vista="lista" role="tab" class="<?= $vista === 'lista' ? 'activo' : '' ?>">☰ Lista</a>
      <a href="<?= e($qs(['vista' => 'calendario'])) ?>" data-vista="calendario" role="tab" class="<?= $vista === 'calendario' ? 'activo' : '' ?>">▦ Calendario</a>
    </div>
  </div>

  <section id="vista-lista" <?= $vista !== 'lista' ? 'hidden' : '' ?>>
    <?php if (!$proximos): ?>
      <p class="vacio">No hay próximos eventos programados<?= $tipo ? ' de este tipo' : '' ?>.</p>
    <?php endif; ?>
    <?php foreach ($porMes as $ym => $evs): ?>
      <h2 class="mes"><?= e(ucfirst(MESES[(int)substr($ym, 5, 2) - 1]) . ' ' . substr($ym, 0, 4)) ?></h2>
      <ul class="lista">
        <?php foreach ($evs as $ev): $ts = strtotime($ev['fecha']); ?>
          <li class="evento t-<?= e($ev['tipo']) ?>">
            <a href="evento.php?id=<?= (int)$ev['id'] ?><?= $embed ? '&embed=1' : '' ?>" data-id="<?= (int)$ev['id'] ?>" class="evento-enlace">
              <div class="dia">
                <span class="dsem"><?= e(mb_substr(DIAS[(int)date('w', $ts)], 0, 3)) ?></span>
                <span class="dnum"><?= date('j', $ts) ?></span>
                <span class="dmes"><?= e(mb_substr(MESES[(int)date('n', $ts) - 1], 0, 3)) ?></span>
              </div>
              <div class="info">
                <span class="etiqueta"><?= e(tipo_nombre($ev['tipo'])) ?></span>
                <h3><?= e($ev['titulo']) ?></h3>
                <p class="meta">
                  <?php if (!empty($ev['fecha_fin'])): ?><span>📅 <?= e(evento_fechas($ev)) ?></span><?php endif; ?>
                  <?php if ($h = evento_horario($ev)): ?><span>🕒 <?= e($h) ?></span><?php endif; ?>
                  <?php if ($ev['duracion']): ?><span>⏱ <?= e($ev['duracion']) ?></span><?php endif; ?>
                  <?php if ($ev['lugar']): ?><span>📍 <?= e($ev['lugar']) ?></span><?php endif; ?>
                </p>
              </div>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </section>

  <section id="vista-calendario" <?= $vista !== 'calendario' ? 'hidden' : '' ?>>
    <div class="cal-cab">
      <button type="button" class="btn-icono" data-cal="prev" aria-label="Mes anterior">‹</button>
      <h2 id="cal-titulo"></h2>
      <button type="button" class="btn-icono" data-cal="next" aria-label="Mes siguiente">›</button>
      <button type="button" class="btn sec" data-cal="hoy">Hoy</button>
    </div>
    <div id="calendario" class="calendario" aria-live="polite"></div>
    <noscript><p class="vacio">El calendario necesita JavaScript. Usa la vista de lista.</p></noscript>
  </section>
</main>

<?php if (!$embed): ?>
<footer class="pie contenedor"><a href="admin/">Zona de gestión</a></footer>
<?php endif; ?>

<dialog id="modal" class="modal">
  <form method="dialog"><button class="cerrar" aria-label="Cerrar">×</button></form>
  <div id="modal-cuerpo"></div>
</dialog>

<script>
window.AGENDA = {
  api: 'api/eventos.php',
  tipo: <?= json_encode($tipo) ?>,
  embed: <?= $embed ? 'true' : 'false' ?>,
  eventos: <?= json_encode(array_map('evento_publico', $proximos), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
};
</script>
<script src="assets/js/agenda.js"></script>
</body>
</html>
