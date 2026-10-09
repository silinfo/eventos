<?php
// Genera los iconos de la app (PWA) a partir del logo breve.
// Uso: php tools/generar_iconos.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$raiz = dirname(__DIR__);
$completo = imagecreatefromstring(file_get_contents($raiz . '/assets/img/logo_eg.jpg'));

// Para el icono solo se usa la palabra "suap" (a la izquierda de la barra vertical),
// que se lee bien a tamaño pequeño.
$w = imagesx($completo);
$h = imagesy($completo);
$barra = 0;
$max = 0;
for ($x = 0; $x < $w; $x++) {
    $n = 0;
    for ($y = 0; $y < $h; $y += 2) {
        if (((imagecolorat($completo, $x, $y) >> 16) & 255) < 150) {
            $n++;
        }
    }
    if ($n > $max) {
        $max = $n;
        $barra = $x;
    }
}
$logo = imagecrop($completo, ['x' => 0, 'y' => 0, 'width' => max(1, $barra - 12), 'height' => $h]);
$logo = imagecropauto($logo, IMG_CROP_THRESHOLD, 0.25, imagecolorallocate($logo, 255, 255, 255)) ?: $logo;

/** Logo centrado sobre fondo blanco; $margen = fracción libre a cada lado */
function icono($logo, int $lado, float $margen, string $destino): void
{
    $lw = imagesx($logo);
    $lh = imagesy($logo);
    $img = imagecreatetruecolor($lado, $lado);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    $ancho = (int)round($lado * (1 - 2 * $margen));
    $alto = (int)round($lh * $ancho / $lw);
    imagecopyresampled($img, $logo, (int)(($lado - $ancho) / 2), (int)(($lado - $alto) / 2), 0, 0, $ancho, $alto, $lw, $lh);
    imagepng($img, $destino, 9);
    imagedestroy($img);
}

$dir = $raiz . '/assets/icons';
icono($logo, 192, 0.10, "$dir/icon-192.png");
icono($logo, 512, 0.10, "$dir/icon-512.png");
icono($logo, 512, 0.20, "$dir/icon-maskable-512.png"); // zona segura para iconos adaptativos
icono($logo, 180, 0.10, "$dir/apple-touch-icon.png");
echo "Iconos generados en assets/icons/\n";
