<?php
declare(strict_types=1);

require_once APP_ROOT . '/lib/fpdf/fpdf.php';

spl_autoload_register(function (string $clase) {
    if (strpos($clase, 'PHPQRCode\\') === 0) {
        $f = APP_ROOT . '/lib/phpqrcode/' . str_replace('\\', '/', $clase) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});

/** Texto UTF-8 -> Windows-1252 (codificación de las fuentes estándar de FPDF) */
function pdf_txt(?string $s): string
{
    $s = str_replace(['–', '—', '“', '”', '‘', '’', '…', '•'], ['-', '-', '"', '"', "'", "'", '...', '·'], (string)$s);
    $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
    return $r === false ? mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : $r;
}

class PdfSuap extends FPDF
{
    public string $tituloListado = '';
    public string $subtituloListado = '';
    public bool $esListado = false;
    /** @var string[] ficheros temporales a borrar al final */
    private array $temporales = [];

    public function __construct(string $orientacion = 'P')
    {
        parent::__construct($orientacion, 'mm', 'A4');
        $this->SetAutoPageBreak(true, 15);
        $this->SetCreator('Agenda de eventos SUAP');
        $this->SetAuthor(pdf_txt(config('organizacion')));
        $this->AliasNbPages();
    }

    public function __destruct()
    {
        foreach ($this->temporales as $f) {
            @unlink($f);
        }
    }

    public function Header(): void
    {
        if (!$this->esListado) {
            return;
        }
        $this->SetFillColor(185, 28, 28);
        $this->Rect(0, 0, $this->GetPageWidth(), 4, 'F');
        pdf_logo($this, ['logo', 'logo_corto'], $this->GetPageWidth() - $this->rMargin - 70, 8, 70, 16, 'R');
        $this->SetY(9);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(100, 110, 120);
        $this->Cell(0, 5, pdf_txt(config('organizacion')), 0, 1);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(29, 39, 51);
        $this->Cell(0, 8, pdf_txt($this->tituloListado), 0, 1);
        if ($this->subtituloListado !== '') {
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(100, 110, 120);
            $this->Cell(0, 5, pdf_txt($this->subtituloListado), 0, 1);
        }
        $this->Ln(3);
    }

    public function Footer(): void
    {
        if (!$this->esListado) {
            return;
        }
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(120, 130, 140);
        $this->Cell(0, 5, pdf_txt('Generado el ' . date('d/m/Y H:i')), 0, 0, 'L');
        $this->Cell(0, 5, pdf_txt('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /** Nº de líneas que ocupará un texto en un MultiCell de ancho $w (fuente actual) */
    public function nbLineas(float $w, string $txt): int
    {
        $cw = $this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
            }
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    /** Recorta un texto para que quepa en $maxLineas líneas de ancho $w */
    public function recortar(float $w, string $txt, int $maxLineas): string
    {
        if ($this->nbLineas($w, $txt) <= $maxLineas) {
            return $txt;
        }
        $palabras = explode(' ', $txt);
        while (count($palabras) > 1) {
            array_pop($palabras);
            $t = rtrim(implode(' ', $palabras), " ,.;:") . '...';
            if ($this->nbLineas($w, $t) <= $maxLineas) {
                return $t;
            }
        }
        return '';
    }

    /** Inserta un código QR con la URL indicada */
    public function qr(string $url, float $x, float $y, float $lado): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }
        $f = tempnam(sys_get_temp_dir(), 'qr') . '.png';
        $this->temporales[] = $f;
        try {
            \PHPQRCode\QRcode::png($url, $f, 'M', 10, 1);
        } catch (\Throwable $e) {
            return false;
        }
        if (!is_file($f) || filesize($f) === 0) {
            return false;
        }
        $this->Image($f, $x, $y, $lado, $lado, 'PNG');
        return true;
    }
}

/* =====================================================================
 * Cartel de un evento (A4 vertical)
 * ===================================================================== */
function pdf_cartel(array $ev): PdfSuap
{
    $tipo = TIPOS[$ev['tipo']] ?? ['nombre' => $ev['tipo'], 'rgb' => [80, 80, 80]];
    [$r, $g, $b] = $tipo['rgb'];
    $pdf = new PdfSuap('P');
    $pdf->SetTitle(pdf_txt($ev['titulo']));
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $W = 210; $M = 18; $ancho = $W - 2 * $M;

    // Franja blanca con el logo extendido (o el breve si no hay extendido)
    $yBanda = 0;
    $wLogo = pdf_logo($pdf, ['logo', 'logo_corto'], $M, 7, 110, 18);
    if ($wLogo > 0) {
        $yBanda = 32;
    }

    // Banda de color con el tipo de evento
    $hBanda = $wLogo > 0 ? 26 : 58;
    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect(0, $yBanda, $W, $hBanda, 'F');
    $pdf->SetFillColor(min(255, $r + 40), min(255, $g + 40), min(255, $b + 40));
    $pdf->Rect(0, $yBanda + $hBanda, $W, 3, 'F');

    $pdf->SetTextColor(255, 255, 255);
    if ($wLogo === 0.0) {
        $pdf->SetXY($M, 14);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell($ancho, 5.5, pdf_txt(config('organizacion')), 0, 'L');
    }
    $pdf->SetXY($M, $yBanda + $hBanda - 22);
    $pdf->SetFont('Helvetica', 'B', 22);
    $pdf->Cell($ancho, 10, pdf_txt(mb_strtoupper($tipo['nombre'])), 0, 1, 'L');

    // Título: tamaño adaptado a su longitud (máx. 4 líneas)
    $titulo = pdf_txt($ev['titulo']);
    $tam = 38;
    do {
        $pdf->SetFont('Helvetica', 'B', $tam);
        $lineas = $pdf->nbLineas($ancho, $titulo);
        $tam -= 2;
    } while ($lineas > 4 && $tam > 16);
    $tam += 2;
    $alto = $tam * 0.45;
    $pdf->SetTextColor(29, 39, 51);
    $pdf->SetXY($M, $yBanda + $hBanda + 16);
    $pdf->MultiCell($ancho, $alto, $pdf->recortar($ancho, $titulo, 4), 0, 'L');

    // Bloque de fecha destacado
    $y = $pdf->GetY() + 8;
    $pdf->SetDrawColor($r, $g, $b);
    $pdf->SetLineWidth(1.2);
    $pdf->Line($M, $y, $M, $y + 18);
    $pdf->SetXY($M + 5, $y);
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->MultiCell($ancho - 5, 9, pdf_txt(evento_fechas($ev)), 0, 'L');
    if ($h = evento_horario($ev)) {
        $pdf->SetX($M + 5);
        $pdf->SetFont('Helvetica', '', 16);
        $pdf->Cell($ancho - 5, 9, pdf_txt($h), 0, 1, 'L');
    }
    $y = max($pdf->GetY(), $y + 18) + 8;

    // Datos
    $filas = [];
    if ($ev['duracion']) $filas[] = ['Duración', $ev['duracion']];
    if ($ev['lugar']) $filas[] = ['Lugar', $ev['lugar']];
    $pdf->SetTextColor(29, 39, 51);
    foreach ($filas as [$etq, $val]) {
        $pdf->SetXY($M, $y);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(100, 110, 120);
        $pdf->Cell(28, 7, pdf_txt(mb_strtoupper($etq)), 0, 0);
        $pdf->SetFont('Helvetica', '', 14);
        $pdf->SetTextColor(29, 39, 51);
        $pdf->MultiCell($ancho - 28, 7, pdf_txt($val), 0, 'L');
        $y = $pdf->GetY() + 2;
    }

    // Pie y QR
    $yPie = 297 - 22;
    $urlQr = $ev['url'] ?: (config('url_base') ? rtrim((string)config('url_base'), '/') . '/evento.php?id=' . $ev['id'] : '');
    $ladoQr = 38;
    $yQr = $yPie - $ladoQr - 8;
    $hayQr = false;
    if ($urlQr) {
        $hayQr = $pdf->qr($urlQr, $W - $M - $ladoQr, $yQr, $ladoQr);
        if ($hayQr) {
            $pdf->SetXY($W - $M - $ladoQr - 10, $yQr + $ladoQr);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(100, 110, 120);
            $pdf->Cell($ladoQr + 10, 5, pdf_txt('Más información'), 0, 0, 'C');
        }
    }

    // Descripción en el espacio restante
    if ($ev['descripcion']) {
        $y += 4;
        $pdf->SetDrawColor(221, 227, 234);
        $pdf->SetLineWidth(0.3);
        $pdf->Line($M, $y, $W - $M, $y);
        $y += 6;
        $anchoDesc = $ancho;
        $limite = ($hayQr ? $yQr - 4 : $yPie - 8);
        $desc = pdf_txt($ev['descripcion']);
        $tam = 14;
        do {
            $pdf->SetFont('Helvetica', '', $tam);
            $altoLinea = $tam * 0.5;
            $maxLineas = (int)floor(($limite - $y) / $altoLinea);
            $tam -= 1;
        } while ($pdf->nbLineas($anchoDesc, $desc) > $maxLineas && $tam >= 9);
        if ($maxLineas > 0) {
            $pdf->SetXY($M, $y);
            $pdf->SetTextColor(50, 60, 70);
            $pdf->MultiCell($anchoDesc, $altoLinea, $pdf->recortar($anchoDesc, $desc, $maxLineas), 0, 'L');
        }
    }

    if ($ev['url']) {
        $pdf->SetXY($M, $yPie - 7);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor($r, $g, $b);
        $u = pdf_txt($ev['url']);
        $pdf->Cell($hayQr ? $ancho - $ladoQr - 14 : $ancho, 5, $pdf->recortar($hayQr ? $ancho - $ladoQr - 14 : $ancho, $u, 1) ?: $u, 0, 0, 'L', false, $ev['url']);
    }

    $pdf->SetFillColor($r, $g, $b);
    $pdf->Rect(0, $yPie, $W, 22, 'F');
    // Logo breve sobre recuadro blanco
    $wPie = 0.0;
    if ($f = logo_archivo('logo_corto')) {
        [$lw, $lh] = pdf_ajustar($f, 34, 14);
        try {
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($M, $yPie + 4, $lw + 4, 14, 'F');
            $pdf->Image($f, $M + 2, $yPie + 4 + (14 - $lh) / 2, $lw, $lh);
            $wPie = $lw + 4;
        } catch (\Throwable $e) {
        }
    }
    $pdf->SetXY($M + $wPie, $yPie + 7);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell($ancho - 2 * $wPie, 8, pdf_txt(config('organizacion')), 0, 0, 'C');

    return $pdf;
}

/* =====================================================================
 * Listado de eventos (A4 horizontal)
 * ===================================================================== */
function pdf_listado(array $eventos, string $titulo, string $subtitulo = ''): PdfSuap
{
    $pdf = new PdfSuap('L');
    $pdf->esListado = true;
    $pdf->tituloListado = $titulo;
    $pdf->subtituloListado = $subtitulo;
    $pdf->SetTitle(pdf_txt($titulo));
    $pdf->SetMargins(12, 12, 12);
    $pdf->AddPage();

    // Anchos: Fecha, Horario, Tipo, Evento, Lugar  (total 273 mm)
    $w = [30, 28, 32, 123, 60];
    $cab = ['Fecha', 'Horario', 'Tipo', 'Evento', 'Lugar'];

    $cabecera = function () use ($pdf, $w, $cab) {
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(238, 241, 245);
        $pdf->SetTextColor(90, 100, 112);
        foreach ($cab as $i => $c) {
            $pdf->Cell($w[$i], 7, pdf_txt(mb_strtoupper($c)), 0, 0, 'L', true);
        }
        $pdf->Ln();
    };

    if (!$eventos) {
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(0, 10, pdf_txt('No hay eventos con los criterios seleccionados.'), 0, 1);
        return $pdf;
    }

    $cabecera();
    $mesActual = '';
    $limite = $pdf->GetPageHeight() - 18;

    foreach ($eventos as $ev) {
        $tipo = TIPOS[$ev['tipo']] ?? ['corto' => $ev['tipo'], 'rgb' => [80, 80, 80]];
        $mes = substr($ev['fecha'], 0, 7);

        // Contenido de cada celda
        $fecha = date('d/m/Y', strtotime($ev['fecha']));
        $dia = ucfirst(DIAS[(int)date('w', strtotime($ev['fecha']))]);
        if ($ev['fecha_fin']) {
            $fecha .= "\nal " . date('d/m/Y', strtotime($ev['fecha_fin']));
        } else {
            $fecha = $dia . "\n" . $fecha;
        }
        $horario = hora_corta($ev['hora_inicio']) . ($ev['hora_fin'] ? ' - ' . hora_corta($ev['hora_fin']) : '');
        if ($ev['duracion']) {
            $horario .= ($horario ? "\n" : '') . $ev['duracion'];
        }
        $tituloEv = pdf_txt($ev['titulo'] . (!$ev['publicado'] ? ' [BORRADOR]' : ''));
        $desc = $ev['descripcion'] ? pdf_txt(preg_replace('/\s+/', ' ', $ev['descripcion'])) : '';
        if ($ev['url']) {
            $desc .= ($desc ? "\n" : '') . pdf_txt($ev['url']);
        }

        // Altura de la fila
        $pdf->SetFont('Helvetica', 'B', 9);
        $hTit = $pdf->nbLineas($w[3], $tituloEv) * 4.5;
        $pdf->SetFont('Helvetica', '', 8);
        $desc = $desc !== '' ? $pdf->recortar($w[3], $desc, 4) : '';
        $hDesc = $desc !== '' ? $pdf->nbLineas($w[3], $desc) * 3.8 : 0;
        $pdf->SetFont('Helvetica', '', 9);
        $hOtras = max(
            $pdf->nbLineas($w[0], pdf_txt($fecha)),
            $pdf->nbLineas($w[1], pdf_txt($horario)),
            $pdf->nbLineas($w[4], pdf_txt((string)$ev['lugar']))
        ) * 4.5;
        $h = max($hTit + $hDesc, $hOtras) + 4;

        $hMes = $mes !== $mesActual ? 9 : 0;
        if ($pdf->GetY() + $h + $hMes > $limite) {
            $pdf->AddPage();
            $cabecera();
        }
        if ($hMes) {
            $mesActual = $mes;
            $pdf->Ln(2);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(185, 28, 28);
            $pdf->Cell(0, 7, pdf_txt(ucfirst(MESES[(int)substr($mes, 5, 2) - 1]) . ' ' . substr($mes, 0, 4)), 'B', 1);
        }

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        [$r, $g, $b] = $tipo['rgb'];
        $pdf->SetFillColor($r, $g, $b);
        $pdf->Rect($x, $y + 1, 1.2, $h - 2, 'F');

        $pdf->SetTextColor(29, 39, 51);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY($x + 2, $y + 2);
        $pdf->MultiCell($w[0] - 2, 4.5, pdf_txt($fecha), 0, 'L');
        $pdf->SetXY($x + $w[0], $y + 2);
        $pdf->MultiCell($w[1], 4.5, pdf_txt($horario), 0, 'L');
        $pdf->SetXY($x + $w[0] + $w[1], $y + 2);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->MultiCell($w[2], 4.5, pdf_txt($tipo['corto']), 0, 'L');
        $xEv = $x + $w[0] + $w[1] + $w[2];
        $pdf->SetXY($xEv, $y + 2);
        $pdf->SetTextColor(29, 39, 51);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->MultiCell($w[3], 4.5, $tituloEv, 0, 'L');
        if ($desc !== '') {
            $pdf->SetX($xEv);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(90, 100, 112);
            $pdf->MultiCell($w[3], 3.8, $desc, 0, 'L');
        }
        $pdf->SetXY($xEv + $w[3], $y + 2);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(29, 39, 51);
        $pdf->MultiCell($w[4], 4.5, pdf_txt((string)$ev['lugar']), 0, 'L');

        $pdf->SetDrawColor(221, 227, 234);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($x, $y + $h, $x + array_sum($w), $y + $h);
        $pdf->SetXY($x, $y + $h);
    }

    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(100, 110, 120);
    $pdf->Cell(0, 5, pdf_txt('Total: ' . count($eventos) . ' evento(s)'), 0, 1);
    return $pdf;
}

/** Tamaño (mm) de una imagen ajustada dentro de una caja de $maxW x $maxH */
function pdf_ajustar(string $f, float $maxW, float $maxH): array
{
    [$pw, $ph] = getimagesize($f);
    $k = min($maxW / $pw, $maxH / $ph);
    return [$pw * $k, $ph * $k];
}

/**
 * Dibuja el primer logo disponible de $claves dentro de la caja indicada.
 * Devuelve el ancho ocupado (0 si no hay logo).
 */
function pdf_logo(FPDF $pdf, array $claves, float $x, float $y, float $maxW, float $maxH, string $alinear = 'L'): float
{
    foreach ($claves as $c) {
        if ($f = logo_archivo($c)) {
            [$w, $h] = pdf_ajustar($f, $maxW, $maxH);
            if ($alinear === 'R') {
                $x += $maxW - $w;
            }
            try {
                $pdf->Image($f, $x, $y + ($maxH - $h) / 2, $w, $h);
            } catch (\Throwable $e) {
                continue;
            }
            return $w;
        }
    }
    return 0.0;
}

/** Nombre de fichero seguro a partir de un texto */
function nombre_fichero(string $s): string
{
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return substr($s, 0, 60) ?: 'evento';
}
