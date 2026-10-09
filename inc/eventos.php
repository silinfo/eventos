<?php
declare(strict_types=1);

/** Tipos de evento: clave => [nombre, color, descripción corta] */
const TIPOS = [
    'formacion' => [
        'nombre' => 'Formación y docencia',
        'corto'  => 'Formación',
        'color'  => '#1f6fb2',
        'rgb'    => [31, 111, 178],
    ],
    'organizacion' => [
        'nombre' => 'Reuniones y organización',
        'corto'  => 'Organización',
        'color'  => '#c2410c',
        'rgb'    => [194, 65, 12],
    ],
    'convivencia' => [
        'nombre' => 'Convivencia y ocio',
        'corto'  => 'Convivencia',
        'color'  => '#15803d',
        'rgb'    => [21, 128, 61],
    ],
];

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
    'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

function tipo_nombre(string $tipo): string
{
    return TIPOS[$tipo]['nombre'] ?? $tipo;
}

/** CSS con el color de cada tipo: .t-formacion{--tc:#...} */
function tipos_css(): string
{
    $css = '';
    foreach (TIPOS as $k => $t) {
        $css .= ".t-$k{--tc:{$t['color']}}";
    }
    return $css;
}

/* ---------- Consultas ---------- */

/**
 * Lista eventos.
 * $f: desde (Y-m-d), hasta (Y-m-d), tipo, q (texto), solo_publicados (bool), orden ('asc'|'desc')
 * Un evento entra en el rango si se solapa con él (útil para eventos de varios días).
 */
function eventos_listar(array $f = []): array
{
    $where = [];
    $p = [];
    if (!empty($f['desde'])) {
        $where[] = 'COALESCE(fecha_fin, fecha) >= :desde';
        $p[':desde'] = $f['desde'];
    }
    if (!empty($f['hasta'])) {
        $where[] = 'fecha <= :hasta';
        $p[':hasta'] = $f['hasta'];
    }
    if (!empty($f['tipo']) && isset(TIPOS[$f['tipo']])) {
        $where[] = 'tipo = :tipo';
        $p[':tipo'] = $f['tipo'];
    }
    if (!empty($f['q'])) {
        $where[] = '(titulo LIKE :q1 OR descripcion LIKE :q2 OR lugar LIKE :q3)';
        $like = '%' . $f['q'] . '%';
        $p[':q1'] = $p[':q2'] = $p[':q3'] = $like;
    }
    if (!empty($f['solo_publicados'])) {
        $where[] = 'publicado = 1';
    }
    $orden = (($f['orden'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
    $sql = 'SELECT * FROM eventos'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY fecha $orden, hora_inicio $orden, id $orden";
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

function evento_obtener(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM eventos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

const CAMPOS = ['tipo', 'titulo', 'descripcion', 'fecha', 'fecha_fin', 'hora_inicio',
    'hora_fin', 'duracion', 'lugar', 'url', 'publicado'];

function evento_guardar(array $d, ?int $id = null): int
{
    $vals = [];
    foreach (CAMPOS as $c) {
        $vals[":$c"] = $d[$c];
    }
    if ($id) {
        $set = implode(', ', array_map(fn($c) => "$c = :$c", CAMPOS));
        $vals[':id'] = $id;
        db()->prepare("UPDATE eventos SET $set WHERE id = :id")->execute($vals);
        return $id;
    }
    $cols = implode(', ', CAMPOS);
    $ph = implode(', ', array_map(fn($c) => ":$c", CAMPOS));
    db()->prepare("INSERT INTO eventos ($cols) VALUES ($ph)")->execute($vals);
    return (int)db()->lastInsertId();
}

function evento_borrar(int $id): void
{
    db()->prepare('DELETE FROM eventos WHERE id = ?')->execute([$id]);
}

/**
 * Valida y normaliza los datos de un formulario.
 * Devuelve [datos, errores].
 */
function evento_validar(array $in): array
{
    $t = fn($k) => trim((string)($in[$k] ?? ''));
    $d = [
        'tipo'        => $t('tipo'),
        'titulo'      => $t('titulo'),
        'descripcion' => $t('descripcion'),
        'fecha'       => $t('fecha'),
        'fecha_fin'   => $t('fecha_fin'),
        'hora_inicio' => $t('hora_inicio'),
        'hora_fin'    => $t('hora_fin'),
        'duracion'    => $t('duracion'),
        'lugar'       => $t('lugar'),
        'url'         => $t('url'),
        'publicado'   => !empty($in['publicado']) ? 1 : 0,
    ];
    $err = [];

    if (!isset(TIPOS[$d['tipo']])) {
        $err['tipo'] = 'Elige un tipo de evento.';
    }
    if ($d['titulo'] === '') {
        $err['titulo'] = 'El título es obligatorio.';
    } elseif (mb_strlen($d['titulo']) > 200) {
        $err['titulo'] = 'Máximo 200 caracteres.';
    }
    if (!es_fecha($d['fecha'])) {
        $err['fecha'] = 'Fecha no válida.';
    }
    if ($d['fecha_fin'] !== '') {
        if (!es_fecha($d['fecha_fin'])) {
            $err['fecha_fin'] = 'Fecha no válida.';
        } elseif (!isset($err['fecha']) && $d['fecha_fin'] < $d['fecha']) {
            $err['fecha_fin'] = 'Debe ser posterior a la fecha de inicio.';
        } elseif ($d['fecha_fin'] === $d['fecha']) {
            $d['fecha_fin'] = '';
        }
    }
    foreach (['hora_inicio', 'hora_fin'] as $h) {
        if ($d[$h] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $d[$h])) {
            $err[$h] = 'Hora no válida (HH:MM).';
        }
    }
    if ($d['hora_fin'] !== '' && $d['hora_inicio'] === '') {
        $err['hora_inicio'] = 'Indica la hora de inicio.';
    }
    if ($d['url'] !== '') {
        if (!preg_match('~^https?://~i', $d['url'])) {
            $d['url'] = 'https://' . $d['url'];
        }
        if (!filter_var($d['url'], FILTER_VALIDATE_URL) || mb_strlen($d['url']) > 500) {
            $err['url'] = 'Enlace no válido.';
        }
    }
    if (mb_strlen($d['lugar']) > 200) {
        $err['lugar'] = 'Máximo 200 caracteres.';
    }
    if (mb_strlen($d['duracion']) > 60) {
        $err['duracion'] = 'Máximo 60 caracteres.';
    }
    // Duración automática a partir del horario si no se indica
    if ($d['duracion'] === '' && $d['hora_inicio'] !== '' && $d['hora_fin'] !== '' && empty($err)) {
        $d['duracion'] = duracion_calcular($d['hora_inicio'], $d['hora_fin']);
    }

    // Campos opcionales vacíos -> NULL
    foreach (['descripcion', 'fecha_fin', 'hora_inicio', 'hora_fin', 'duracion', 'lugar', 'url'] as $k) {
        if ($d[$k] === '') {
            $d[$k] = null;
        }
    }
    foreach (['hora_inicio', 'hora_fin'] as $h) {
        if ($d[$h] !== null && strlen($d[$h]) === 5) {
            $d[$h] .= ':00';
        }
    }
    return [$d, $err];
}

function es_fecha(string $s): bool
{
    $dt = DateTime::createFromFormat('!Y-m-d', $s);
    return $dt && $dt->format('Y-m-d') === $s;
}

function duracion_calcular(string $ini, string $fin): string
{
    $a = strtotime("1970-01-01 $ini UTC");
    $b = strtotime("1970-01-01 $fin UTC");
    $min = intdiv($b - $a, 60);
    if ($min <= 0) {
        $min += 24 * 60; // termina al día siguiente
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h && $m) {
        return "{$h} h {$m} min";
    }
    return $h ? "{$h} h" : "{$m} min";
}

/* ---------- Formato ---------- */

function hora_corta(?string $h): string
{
    return $h ? substr($h, 0, 5) : '';
}

/** "lunes, 12 de marzo de 2026" */
function fecha_larga(string $ymd, bool $conDia = true): string
{
    $t = strtotime($ymd);
    $s = (int)date('j', $t) . ' de ' . MESES[(int)date('n', $t) - 1] . ' de ' . date('Y', $t);
    return $conDia ? DIAS[(int)date('w', $t)] . ', ' . $s : $s;
}

/** Texto de fechas, contemplando eventos de varios días */
function evento_fechas(array $ev): string
{
    if (!empty($ev['fecha_fin']) && $ev['fecha_fin'] !== $ev['fecha']) {
        return 'Del ' . fecha_larga($ev['fecha'], false) . ' al ' . fecha_larga($ev['fecha_fin'], false);
    }
    return ucfirst(fecha_larga($ev['fecha']));
}

function evento_horario(array $ev): string
{
    $i = hora_corta($ev['hora_inicio']);
    $f = hora_corta($ev['hora_fin']);
    if ($i && $f) {
        return "De $i a $f h";
    }
    return $i ? "A las $i h" : '';
}

/** Datos públicos para JSON (calendario / integración con el portal) */
function evento_publico(array $ev): array
{
    return [
        'id'          => (int)$ev['id'],
        'tipo'        => $ev['tipo'],
        'tipo_nombre' => tipo_nombre($ev['tipo']),
        'color'       => TIPOS[$ev['tipo']]['color'] ?? '#555',
        'titulo'      => $ev['titulo'],
        'descripcion' => $ev['descripcion'],
        'fecha'       => $ev['fecha'],
        'fecha_fin'   => $ev['fecha_fin'],
        'hora_inicio' => hora_corta($ev['hora_inicio']),
        'hora_fin'    => hora_corta($ev['hora_fin']),
        'duracion'    => $ev['duracion'],
        'lugar'       => $ev['lugar'],
        'url'         => $ev['url'],
        'fechas_txt'  => evento_fechas($ev),
        'horario_txt' => evento_horario($ev),
    ];
}

/**
 * Traduce los filtros de la zona de gestión (?periodo=&tipo=&q=&desde=&hasta=)
 * a filtros de eventos_listar(). Devuelve [filtros, descripción legible].
 */
function filtros_gestion(array $get): array
{
    $periodo = (string)($get['periodo'] ?? 'proximos');
    $f = [
        'tipo' => (string)($get['tipo'] ?? ''),
        'q'    => trim((string)($get['q'] ?? '')),
    ];
    $desde = (string)($get['desde'] ?? '');
    $hasta = (string)($get['hasta'] ?? '');
    $sub = [];
    switch ($periodo) {
        case 'pasados':
            $f['hasta'] = date('Y-m-d', strtotime('-1 day'));
            $f['orden'] = 'desc';
            $sub[] = 'Eventos pasados';
            break;
        case 'todos':
            $sub[] = 'Todos los eventos';
            break;
        case 'rango':
            if (es_fecha($desde)) {
                $f['desde'] = $desde;
                $sub[] = 'Desde el ' . date('d/m/Y', strtotime($desde));
            }
            if (es_fecha($hasta)) {
                $f['hasta'] = $hasta;
                $sub[] = 'hasta el ' . date('d/m/Y', strtotime($hasta));
            }
            break;
        default:
            $f['desde'] = date('Y-m-d');
            $sub[] = 'Próximos eventos a partir del ' . date('d/m/Y');
    }
    if (isset(TIPOS[$f['tipo']])) {
        $sub[] = 'Tipo: ' . tipo_nombre($f['tipo']);
    }
    if ($f['q'] !== '') {
        $sub[] = 'Búsqueda: "' . $f['q'] . '"';
    }
    return [$f, implode(' · ', $sub)];
}
