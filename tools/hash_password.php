<?php
// Uso: php tools/hash_password.php "contraseña"
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if ($argc < 2 || $argv[1] === '') {
    fwrite(STDERR, "Uso: php tools/hash_password.php \"contraseña\"\n");
    exit(1);
}
echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;
