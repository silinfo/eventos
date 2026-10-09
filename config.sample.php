<?php
/**
 * Copia este fichero como config.php y ajusta los valores.
 * config.php NO se sube al repositorio.
 */
return [
    // Base de datos MySQL
    'db' => [
        'host'    => 'localhost',
        'name'    => 'suap_eventos',
        'user'    => 'suap',
        'pass'    => 'cambiar',
        'charset' => 'utf8mb4',
        // 'dsn' => 'sqlite:/ruta/eventos.sqlite',  // opcional: DSN completo (pruebas)
    ],

    // Hash de la contraseña de la zona de gestión.
    // Genéralo con:  php tools/hash_password.php "MiContraseñaSegura"
    'admin_password_hash' => '',

    // Textos que aparecen en el portal y en los PDF
    'organizacion' => 'Servicio de Urgencias de Atención Primaria (SUAP)',
    'titulo_portal' => 'Agenda de eventos',

    // Logo opcional para carteles (PNG o JPG, ruta absoluta o relativa a la raíz)
    'logo' => '',

    // URL pública de la aplicación (para el QR de los carteles cuando el evento
    // no tiene enlace propio). Ej: https://intranet.ejemplo.es/suap/eventos/
    'url_base' => '',

    'timezone' => 'Europe/Madrid',
];
