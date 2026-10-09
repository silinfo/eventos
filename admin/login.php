<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/_layout.php';

start_session();
if (is_admin()) {
    redirect('index.php');
}

$error = '';
$hash = (string)config('admin_password_hash', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // Freno a ataques de fuerza bruta: espera creciente tras fallos
    $fallos = $_SESSION['fallos'] ?? 0;
    if ($fallos > 0) {
        sleep(min($fallos, 5));
    }
    if ($hash !== '' && password_verify((string)($_POST['password'] ?? ''), $hash)) {
        session_regenerate_id(true);
        $_SESSION = ['admin' => true, 'last' => time()];
        redirect('index.php');
    }
    $_SESSION['fallos'] = $fallos + 1;
    $error = 'Contraseña incorrecta.';
}

admin_cabecera('Acceso', false);
?>
<div class="panel login">
  <?php if ($l = logo_web('logo')): ?><p class="login-logo"><img src="<?= e($l) ?>" alt="<?= e(config('organizacion')) ?>"></p><?php endif; ?>
  <h2>Acceso a la gestión</h2>
  <?php if ($hash === ''): ?>
    <p class="aviso error">No hay contraseña configurada. Genera una con
      <code>php tools/hash_password.php "tu-contraseña"</code> y ponla en
      <code>admin_password_hash</code> de <code>config.php</code>.</p>
  <?php endif; ?>
  <?php if (!empty($_GET['caducada'])): ?><p class="aviso error">La sesión ha caducado.</p><?php endif; ?>
  <?php if ($error): ?><p class="aviso error"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <p><label for="password">Contraseña</label><br>
      <input type="password" id="password" name="password" required autofocus autocomplete="current-password"></p>
    <p><button class="btn" type="submit">Entrar</button></p>
  </form>
  <p><a href="../index.php">← Volver a la agenda</a></p>
</div>
<?php admin_pie();
