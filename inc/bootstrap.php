<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// Búfer de salida: un espacio o BOM accidental antes de "<?php" (p. ej. en config.php)
// no debe impedir enviar la cookie de sesión ni las redirecciones.
ob_start();

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Falta config.php: copia config.sample.php como config.php y ajústalo.');
}
$GLOBALS['config'] = require $configFile;
date_default_timezone_set(config('timezone', 'Europe/Madrid'));

require_once __DIR__ . '/eventos.php';

function config(string $key, $default = null)
{
    return $GLOBALS['config'][$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = $c['dsn'] ?? sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset'] ?? 'utf8mb4');
        try {
            $pdo = new PDO($dsn, $c['user'] ?? null, $c['pass'] ?? null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $ex) {
            // El detalle va al error_log del servidor; al visitante, un mensaje genérico
            error_log('Eventos: no se puede conectar con la base de datos: ' . $ex->getMessage());
            http_response_code(503);
            exit('La agenda no está disponible en este momento (error de conexión con la base de datos).');
        }
    }
    return $pdo;
}

/** Escapa para HTML */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Ruta relativa a la raíz de la app, válida desde cualquier subcarpeta */
function url(string $path = ''): string
{
    static $depth = null;
    if ($depth === null) {
        // Profundidad del script actual respecto a la raíz (admin/ y api/ = 1)
        $dir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: APP_ROOT;
        $rel = trim(substr($dir, strlen(APP_ROOT)), '/');
        $depth = $rel === '' ? 0 : substr_count($rel, '/') + 1;
    }
    return str_repeat('../', $depth) . ltrim($path, '/');
}

/** URL absoluta de un fichero de la app (usa url_base si está configurada) */
function url_absoluta(string $path): string
{
    $base = trim((string)config('url_base', ''));
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = (es_https() ? 'https' : 'http') . '://' . $host . rtrim($dir, '/') . '/';
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ---------- Logos ---------- */

/**
 * Ruta local de un logo ('logo' = extendido, 'logo_corto' = breve), o null si no hay.
 * Si en la configuración es una URL, se descarga una vez a assets/logos/ y se
 * normaliza con GD (PNG no entrelazado / JPG) para que FPDF pueda usarlo.
 */
function logo_archivo(string $clave): ?string
{
    static $cache = [];
    if (array_key_exists($clave, $cache)) {
        return $cache[$clave];
    }
    $origen = trim((string)config($clave, ''));
    if ($origen === '') {
        return $cache[$clave] = null;
    }
    if (!preg_match('~^https?://~i', $origen)) {
        $f = $origen[0] === '/' ? $origen : APP_ROOT . '/' . $origen;
        return $cache[$clave] = is_file($f) ? $f : null;
    }

    $dir = APP_ROOT . '/assets/logos';
    $base = $dir . '/' . $clave . '-' . substr(md5($origen), 0, 10);
    foreach (['.png', '.jpg'] as $ext) {
        if (is_file($base . $ext)) {
            return $cache[$clave] = $base . $ext;
        }
    }
    // Tras un fallo, no reintentar durante una hora para no ralentizar las páginas
    if (is_file("$base.error") && time() - filemtime("$base.error") < 3600) {
        return $cache[$clave] = null;
    }
    $datos = @file_get_contents($origen, false, stream_context_create([
        'http' => ['timeout' => 5, 'follow_location' => 1, 'user_agent' => 'SUAP-Eventos'],
    ]));
    $info = $datos ? @getimagesizefromstring($datos) : false;
    $img = ($info && function_exists('imagecreatefromstring')) ? @imagecreatefromstring($datos) : false;
    if (!$img) {
        @file_put_contents("$base.error", $origen);
        return $cache[$clave] = null;
    }
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if ($info[2] === IMAGETYPE_JPEG) {
        $destino = "$base.jpg";
        $ok = imagejpeg($img, $destino, 92);
    } else {
        $destino = "$base.png";
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imageinterlace($img, false);
        $ok = imagepng($img, $destino);
    }
    imagedestroy($img);
    return $cache[$clave] = $ok ? $destino : null;
}

/** URL relativa del logo para usar en HTML, o '' si no hay */
function logo_web(string $clave): string
{
    $f = logo_archivo($clave);
    if ($f === null || strpos($f, APP_ROOT . '/') !== 0) {
        return '';
    }
    return url(substr($f, strlen(APP_ROOT) + 1)) . '?v=' . filemtime($f);
}

/**
 * Logo para cabeceras HTML. $preferido = 'logo' (extendido) o 'logo_corto'.
 * Con el extendido, en pantallas estrechas se muestra el breve.
 */
function logo_html(string $preferido = 'logo'): string
{
    $largo = logo_web('logo');
    $corto = logo_web('logo_corto');
    $alt = e(config('organizacion'));
    if ($preferido === 'logo_corto' && $corto !== '') {
        return '<span class="marca"><img src="' . e($corto) . '" alt="' . $alt . '"></span>';
    }
    if ($largo === '' && $corto === '') {
        return '';
    }
    if ($largo === '') {
        return '<span class="marca"><img src="' . e($corto) . '" alt="' . $alt . '"></span>';
    }
    $source = $corto !== '' ? '<source media="(max-width: 640px)" srcset="' . e($corto) . '">' : '';
    return '<span class="marca"><picture>' . $source . '<img src="' . e($largo) . '" alt="' . $alt . '"></picture></span>';
}

function favicon_html(): string
{
    $f = logo_web('logo_corto') ?: logo_web('logo');
    return $f !== '' ? '<link rel="icon" href="' . e($f) . '">' : '';
}

/* ---------- Sesión y autenticación (zona de gestión) ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    // Si la carpeta de sesiones del servidor no es escribible, usar una propia
    $ruta = session_save_path();
    $ruta = $ruta !== '' ? preg_replace('/^.*;/', '', $ruta) : sys_get_temp_dir();
    if (!@is_dir($ruta) || !@is_writable($ruta)) {
        $propia = APP_ROOT . '/sesiones';
        if (!is_dir($propia)) {
            @mkdir($propia, 0700, true);
        }
        if (is_writable($propia)) {
            session_save_path($propia);
        }
    }
    session_name('suap_eventos');
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => es_https(),
    ]);
    if (!session_start()) {
        error_log('Eventos: no se pudo iniciar la sesión (ruta: ' . session_save_path() . ')');
    }
    // Las páginas con formularios no deben quedar en caché (el token cambiaría)
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
}

function es_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['SERVER_PORT'] ?? '') == 443;
}

function is_admin(): bool
{
    start_session();
    return !empty($_SESSION['admin']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('login.php');
    }
    // Caducidad por inactividad: 2 horas
    if (isset($_SESSION['last']) && time() - $_SESSION['last'] > 7200) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php?caducada=1');
    }
    $_SESSION['last'] = time();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    start_session();
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        // Diagnóstico en el error_log del servidor
        if (empty($_COOKIE[session_name()])) {
            $motivo = 'el navegador no envió la cookie de sesión';
        } elseif (empty($_SESSION['csrf'])) {
            $motivo = 'la sesión llegó vacía (¿no se guardan las sesiones? ruta: ' . session_save_path() . ')';
        } else {
            $motivo = 'el token no coincide (formulario antiguo o en caché)';
        }
        error_log('Eventos: token CSRF no válido: ' . $motivo);
        http_response_code(400);
        exit('Petición no válida (token CSRF). Vuelve atrás y recarga la página.');
    }
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    start_session();
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
