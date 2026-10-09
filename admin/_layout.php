<?php
/** Cabecera y pie comunes de la zona de gestión */
function admin_cabecera(string $titulo, bool $menu = true): void
{
    ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titulo) ?> · Gestión de eventos</title>
<link rel="stylesheet" href="../assets/css/app.css">
<style><?= tipos_css() ?></style>
</head>
<body class="admin">
<header class="cabecera">
  <div class="contenedor">
    <div>
      <p class="org"><?= e(config('organizacion')) ?></p>
      <h1>Gestión de eventos</h1>
    </div>
    <?php if ($menu): ?>
    <nav class="menu">
      <a href="index.php">Eventos</a>
      <a href="editar.php">+ Nuevo evento</a>
      <a href="../index.php" target="_blank">Ver portal ↗</a>
      <a href="logout.php">Salir</a>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main class="contenedor">
<?php
    if ($f = flash()) {
        echo '<p class="aviso ' . e($f['type']) . '">' . e($f['msg']) . '</p>';
    }
}

function admin_pie(): void
{
    echo "</main>\n</body>\n</html>\n";
}
