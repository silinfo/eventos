<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';
require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errores = [];

if ($id) {
    $ev = evento_obtener($id);
    if (!$ev) {
        flash('El evento no existe.', 'error');
        redirect('index.php');
    }
} elseif (!empty($_GET['copiar']) && ($orig = evento_obtener((int)$_GET['copiar']))) {
    // Duplicar: mismos datos, fecha por definir
    $ev = $orig;
    $ev['id'] = null;
    $ev['fecha'] = $ev['fecha_fin'] = '';
    $ev['publicado'] = 0;
} else {
    $ev = array_fill_keys(CAMPOS, '');
    $ev['publicado'] = 1;
    $ev['tipo'] = (string)($_GET['tipo'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$datos, $errores] = evento_validar($_POST);
    if (!$errores) {
        $nuevoId = evento_guardar($datos, $id ?: null);
        flash($id ? 'Evento actualizado.' : 'Evento creado.');
        redirect(!empty($_POST['y_cartel']) ? "cartel.php?id=$nuevoId" : 'index.php');
    }
    $ev = array_merge($ev, array_map(fn($v) => $v ?? '', $datos));
}

$campo = function (string $k) use ($errores): string {
    return 'campo' . (isset($errores[$k]) ? ' con-error' : '');
};
$err = fn(string $k) => isset($errores[$k]) ? '<span class="err">' . e($errores[$k]) . '</span>' : '';

admin_cabecera($id ? 'Modificar evento' : 'Nuevo evento');
?>
<div class="panel">
  <h2 style="margin-top:0"><?= $id ? 'Modificar evento' : 'Nuevo evento' ?></h2>
  <?php if ($errores): ?><p class="aviso error">Revisa los campos marcados.</p><?php endif; ?>

  <form method="post" class="formulario" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <div class="<?= $campo('tipo') ?> ancho">
      <label>Tipo de evento *</label>
      <div class="tipos-radio">
        <?php foreach (TIPOS as $k => $t): ?>
          <label class="t-<?= $k ?>"><input type="radio" name="tipo" value="<?= $k ?>" <?= $ev['tipo'] === $k ? 'checked' : '' ?> required>
            <span class="badge"><?= e($t['corto']) ?></span> <?= e($t['nombre']) ?></label>
        <?php endforeach; ?>
      </div>
      <?= $err('tipo') ?>
    </div>

    <div class="<?= $campo('titulo') ?> ancho">
      <label for="titulo">Título *</label>
      <input type="text" id="titulo" name="titulo" maxlength="200" required value="<?= e($ev['titulo']) ?>">
      <?= $err('titulo') ?>
    </div>

    <div class="<?= $campo('descripcion') ?> ancho">
      <label for="descripcion">Descripción</label>
      <textarea id="descripcion" name="descripcion"><?= e($ev['descripcion']) ?></textarea>
      <span class="ayuda">Objetivos, programa, a quién va dirigido, inscripción…</span>
    </div>

    <div class="<?= $campo('fecha') ?>">
      <label for="fecha">Fecha *</label>
      <input type="date" id="fecha" name="fecha" required value="<?= e($ev['fecha']) ?>">
      <?= $err('fecha') ?>
    </div>
    <div class="<?= $campo('fecha_fin') ?>">
      <label for="fecha_fin">Fecha de fin</label>
      <input type="date" id="fecha_fin" name="fecha_fin" value="<?= e($ev['fecha_fin']) ?>">
      <span class="ayuda">Solo si dura varios días.</span>
      <?= $err('fecha_fin') ?>
    </div>

    <div class="<?= $campo('hora_inicio') ?>">
      <label for="hora_inicio">Hora de inicio</label>
      <input type="time" id="hora_inicio" name="hora_inicio" value="<?= e(hora_corta($ev['hora_inicio'])) ?>">
      <?= $err('hora_inicio') ?>
    </div>
    <div class="<?= $campo('hora_fin') ?>">
      <label for="hora_fin">Hora de fin</label>
      <input type="time" id="hora_fin" name="hora_fin" value="<?= e(hora_corta($ev['hora_fin'])) ?>">
      <?= $err('hora_fin') ?>
    </div>

    <div class="<?= $campo('duracion') ?>">
      <label for="duracion">Duración</label>
      <input type="text" id="duracion" name="duracion" maxlength="60" value="<?= e($ev['duracion']) ?>" placeholder="Ej.: 2 h, 20 horas lectivas">
      <span class="ayuda">Si la dejas vacía se calcula con el horario.</span>
      <?= $err('duracion') ?>
    </div>
    <div class="<?= $campo('lugar') ?>">
      <label for="lugar">Lugar</label>
      <input type="text" id="lugar" name="lugar" maxlength="200" value="<?= e($ev['lugar']) ?>" placeholder="Aula, centro, dirección u online">
      <?= $err('lugar') ?>
    </div>

    <div class="<?= $campo('url') ?> ancho">
      <label for="url">Enlace a más información</label>
      <input type="url" id="url" name="url" maxlength="500" value="<?= e($ev['url']) ?>" placeholder="https://…">
      <span class="ayuda">Opcional. En el cartel se imprime como código QR.</span>
      <?= $err('url') ?>
    </div>

    <div class="campo ancho">
      <label><input type="checkbox" name="publicado" value="1" <?= $ev['publicado'] ? 'checked' : '' ?>> Publicado en el portal</label>
      <span class="ayuda">Desmárcalo para guardarlo como borrador.</span>
    </div>

    <div class="campo ancho acciones">
      <div>
        <button class="btn" type="submit">Guardar</button>
        <button class="btn sec" type="submit" name="y_cartel" value="1">Guardar y generar cartel</button>
        <a class="btn sec" href="index.php">Cancelar</a>
      </div>
    </div>
  </form>
</div>
<script>
// Recalcula la duración al cambiar el horario, salvo que se haya escrito a mano
(function () {
  var ini = document.getElementById('hora_inicio'), fin = document.getElementById('hora_fin'),
      dur = document.getElementById('duracion'), auto = /^(\d+ h)?( ?\d+ min)?$/;
  function calc() {
    if (!ini.value || !fin.value || !auto.test(dur.value.trim())) return;
    var a = ini.value.split(':'), b = fin.value.split(':');
    var m = (b[0] * 60 + +b[1]) - (a[0] * 60 + +a[1]);
    if (m <= 0) m += 1440;
    var h = Math.floor(m / 60), r = m % 60;
    dur.value = h && r ? h + ' h ' + r + ' min' : (h ? h + ' h' : r + ' min');
  }
  ini.addEventListener('change', calc);
  fin.addEventListener('change', calc);
})();
</script>
<?php admin_pie();
