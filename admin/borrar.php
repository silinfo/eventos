<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();
$ev = evento_obtener((int)($_POST['id'] ?? 0));
if ($ev) {
    evento_borrar((int)$ev['id']);
    flash('Evento «' . $ev['titulo'] . '» eliminado.');
} else {
    flash('El evento no existe.', 'error');
}
redirect('index.php');
