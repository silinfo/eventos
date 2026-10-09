<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$periodo = (string)($_GET['periodo'] ?? 'proximos');
$desde = (string)($_GET['desde'] ?? '');
$hasta = (string)($_GET['hasta'] ?? '');
[$filtros] = filtros_gestion($_GET);
$eventos = eventos_listar($filtros);
$hoy = date('Y-m-d');
$qsPdf = http_build_query(array_filter([
    'periodo' => $periodo, 'tipo' => $filtros['tipo'], 'q' => $filtros['q'], 'desde' => $desde, 'hasta' => $hasta,
]));

admin_cabecera('Eventos');
?>
<div class="panel">
  <form class="form-filtros" method="get">
    <label>Periodo
      <select name="periodo" onchange="document.getElementById('rango').hidden = this.value !== 'rango'">
        <option value="proximos" <?= $periodo === 'proximos' ? 'selected' : '' ?>>Próximos</option>
        <option value="pasados" <?= $periodo === 'pasados' ? 'selected' : '' ?>>Pasados</option>
        <option value="todos" <?= $periodo === 'todos' ? 'selected' : '' ?>>Todos</option>
        <option value="rango" <?= $periodo === 'rango' ? 'selected' : '' ?>>Entre fechas…</option>
      </select>
    </label>
    <span id="rango" class="form-filtros" <?= $periodo !== 'rango' ? 'hidden' : '' ?>>
      <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
      <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    </span>
    <label>Tipo
      <select name="tipo">
        <option value="">Todos</option>
        <?php foreach (TIPOS as $k => $t): ?>
          <option value="<?= $k ?>" <?= $filtros['tipo'] === $k ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Buscar <input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Título, lugar…"></label>
    <button class="btn sec" type="submit">Filtrar</button>
    <a class="btn" href="listado_pdf.php?<?= e($qsPdf) ?>" target="_blank">⬇ Listado PDF</a>
    <a class="btn" href="editar.php">+ Nuevo evento</a>
  </form>
</div>

<div class="panel tabla-wrap">
  <?php if (!$eventos): ?>
    <p class="vacio">No hay eventos con estos filtros.</p>
  <?php else: ?>
  <p class="ayuda" style="margin-top:0;color:var(--suave)"><?= count($eventos) ?> evento(s)</p>
  <table class="tabla">
    <thead>
      <tr><th>Fecha</th><th>Horario</th><th>Evento</th><th class="col-opc">Lugar</th><th>Acciones</th></tr>
    </thead>
    <tbody>
    <?php foreach ($eventos as $ev): ?>
      <tr class="t-<?= e($ev['tipo']) ?><?= ($ev['fecha_fin'] ?: $ev['fecha']) < $hoy ? ' pasado' : '' ?>">
        <td style="white-space:nowrap">
          <?= date('d/m/Y', strtotime($ev['fecha'])) ?>
          <?php if ($ev['fecha_fin']): ?><br><small>al <?= date('d/m/Y', strtotime($ev['fecha_fin'])) ?></small><?php endif; ?>
        </td>
        <td style="white-space:nowrap">
          <?= e(hora_corta($ev['hora_inicio'])) ?><?= $ev['hora_fin'] ? '–' . e(hora_corta($ev['hora_fin'])) : '' ?>
          <?php if ($ev['duracion']): ?><br><small><?= e($ev['duracion']) ?></small><?php endif; ?>
        </td>
        <td>
          <span class="badge"><?= e(TIPOS[$ev['tipo']]['corto'] ?? $ev['tipo']) ?></span>
          <?php if (!$ev['publicado']): ?><span class="badge borrador">Borrador</span><?php endif; ?>
          <br><strong><?= e($ev['titulo']) ?></strong>
        </td>
        <td class="col-opc"><?= e($ev['lugar']) ?></td>
        <td>
          <div class="acc">
            <a class="btn sec peq" href="editar.php?id=<?= (int)$ev['id'] ?>" title="Modificar">✎ Editar</a>
            <a class="btn sec peq" href="editar.php?copiar=<?= (int)$ev['id'] ?>" title="Crear uno nuevo a partir de este">⧉</a>
            <a class="btn sec peq" href="cartel.php?id=<?= (int)$ev['id'] ?>" target="_blank" title="Cartel en PDF">🖨 Cartel</a>
            <form method="post" action="borrar.php" onsubmit="return confirm('¿Eliminar definitivamente «<?= e(addslashes($ev['titulo'])) ?>»?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
              <button class="btn peligro peq" type="submit" title="Eliminar">🗑</button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_pie();
