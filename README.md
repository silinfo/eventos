# Agenda de eventos del SUAP

Aplicación PHP + MySQL para publicar y gestionar los eventos del servicio.
No necesita Composer ni frameworks: las dos librerías que usa (FPDF y phpqrcode) van incluidas en `lib/`.

## Tipos de evento

| Clave          | Nombre en el portal        | Color   |
|----------------|----------------------------|---------|
| `formacion`    | Formación y docencia       | Azul    |
| `organizacion` | Reuniones y organización   | Naranja |
| `convivencia`  | Convivencia y ocio         | Verde   |

Para cambiar nombres o colores, edita la constante `TIPOS` en `inc/eventos.php`.

## Funcionalidades

**Parte pública** (`index.php`)
- Vista de **lista** (próximos eventos agrupados por mes) y de **calendario** mensual.
- Filtro por tipo de evento.
- Ficha de cada evento en ventana emergente, con «Más información» y «Añadir a mi calendario» (.ics).
- Página propia por evento: `evento.php?id=N`.
- API JSON de solo lectura: `api/eventos.php?desde=AAAA-MM-DD&hasta=AAAA-MM-DD&tipo=…`

**Zona de gestión** (`admin/`, protegida por contraseña)
- Alta, modificación, duplicado y baja de eventos.
- Borradores (eventos no publicados que no se ven en el portal).
- Filtros: próximos, pasados, todos o entre fechas; por tipo y por texto.
- **Cartel en PDF** (A4) de cada evento, con código QR al enlace de más información.
- **Listado en PDF** (A4 horizontal) con los filtros que haya en pantalla.

Cada evento tiene: tipo, título, descripción, fecha (y fecha de fin opcional si dura varios días),
hora de inicio y fin, duración (si se deja vacía se calcula con el horario), lugar y enlace opcional.

## Instalación

Requisitos: PHP 7.4 o superior (probado con 8.3) con `pdo_mysql`, `mbstring`, `iconv` y `gd` (para el QR), y MySQL/MariaDB.

1. Copia la carpeta en el servidor web (por ejemplo, `/var/www/portal/eventos/`).
2. Crea la base de datos y la tabla:
   ```sql
   CREATE DATABASE suap_eventos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
   ```bash
   mysql -u root -p suap_eventos < sql/schema.sql
   ```
3. Copia `config.sample.php` como `config.php` y rellena los datos de conexión.
4. Genera el hash de la contraseña de gestión y pégalo en `admin_password_hash`:
   ```bash
   php tools/hash_password.php "UnaContraseñaLarga"
   ```
5. Opcional: indica la `url_base` pública (se usa para el QR de los eventos que no tienen enlace propio).

### Logos

En `config.php` hay dos logos:

| Clave        | Uso                                                                 | Valor por defecto |
|--------------|---------------------------------------------------------------------|-------------------|
| `logo`       | Extendido: cabecera del portal, acceso, cartel y listado PDF        | `assets/img/logo_600.png` |
| `logo_corto` | Breve: cabecera de gestión, portal en móvil, pie del cartel, favicon | `assets/img/logo_eg.jpg` |

Los dos logos van incluidos en `assets/img/`. Para cambiarlos, sustituye esos ficheros.
También se puede poner una URL: en ese caso la primera vez se descarga y se guarda en `assets/logos/`
(la carpeta necesita permiso de escritura para el usuario del servidor web). Si el servidor
no tiene salida a Internet, usa rutas locales.
Para forzar una nueva descarga tras cambiar el logo, vacía `assets/logos/`.

Con Apache, los `.htaccess` incluidos bloquean el acceso web a `config.php`, `inc/`, `lib/`, `sql/` y `tools/`.
Con Nginx, añade una regla equivalente:
```nginx
location ~ ^/eventos/(config\.php|config\.sample\.php|inc/|lib/|sql/|tools/) { deny all; }
```

## Integración en la página inicial del portal SUAP

**Opción 1: iframe** (la más sencilla). `embed=1` quita la cabecera y el fondo:

```html
<iframe id="agenda-suap" src="/eventos/index.php?embed=1" title="Agenda de eventos"
        style="width:100%;height:800px;border:0"></iframe>
<script>
  // Ajusta la altura del iframe a su contenido
  window.addEventListener('message', function (e) {
    if (e.data && e.data.suapEventosAltura) {
      document.getElementById('agenda-suap').style.height = e.data.suapEventosAltura + 'px';
    }
  });
</script>
```

Parámetros útiles: `vista=calendario` para abrir directamente el calendario y `tipo=formacion` para mostrar un solo tipo.

**Opción 2: API JSON.** Si el portal ya tiene su propio diseño, puede leer `api/eventos.php`
y pintar los eventos como quiera (con PHP en servidor o con `fetch` en JavaScript).

## Estructura

```
index.php            Portal público (lista + calendario)
evento.php           Ficha pública del evento y descarga .ics
api/eventos.php      API JSON
admin/               Zona de gestión (login, listado, edición, borrado, PDFs)
inc/bootstrap.php    Configuración, conexión PDO, sesión, CSRF
inc/eventos.php      Tipos de evento, consultas, validación y formato de fechas
inc/pdf.php          Generación del cartel y del listado en PDF
assets/              CSS y JavaScript
lib/                 FPDF 1.9 y phpqrcode (incluidas)
sql/schema.sql       Esquema de la base de datos
```

## Seguridad

- Contraseña guardada como hash (`password_hash`), nunca en claro.
- Sesión con cookie `HttpOnly`/`SameSite`, regeneración de ID al entrar y caducidad tras 2 h de inactividad.
- Espera creciente tras intentos fallidos de acceso.
- Tokens CSRF en todos los formularios; el borrado solo acepta POST.
- Consultas preparadas con PDO y escape de toda la salida HTML.
