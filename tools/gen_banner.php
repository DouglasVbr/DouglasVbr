<?php
/**
 * Gera dark.svg e light.svg — banner "terminal" animado para o perfil do GitHub.
 *
 * Painel esquerdo (VISUAL.MAP): retrato do avatar em bitmap 1-bit com dithering
 * Floyd-Steinberg. Tentei ASCII antes: em ~4-6px por caractere os glifos da rampa
 * viram borrao e o rosto some. O dithering resolve o meio-tom com densidade de
 * pixel, que e exatamente o que o repo de referencia faz (la sao ~30k <rect>).
 * Aqui as linhas viram um unico <path> com subpaths horizontais: mesmo resultado,
 * ~15x menos bytes.
 *
 * Painel direito (SYSTEM.INFO): linhas monoespacadas com leaders pontilhados.
 */

$OUT    = dirname(__DIR__);
$AVATAR = __DIR__ . '/avatar.png';

/* ---------- 1. Retrato: bitmap 1-bit com dithering ---------- */

const BMP_W = 180;   // resolucao nativa do bitmap
const BMP_H = 223;   // BMP_W / BMP_H tem que bater com o aspecto do painel

/**
 * Recorta o avatar no aspecto do painel, converte para tons de cinza com
 * normalizacao por percentil e aplica Floyd-Steinberg.
 *
 * @return array<int,array<int,bool>> [y][x] => pixel aceso
 */
function ditherPortrait(string $file): array
{
    $src = imagecreatefromstring(file_get_contents($file));
    $w = imagesx($src);
    $h = imagesy($src);

    // achata sobre preto (o avatar ja tem fundo preto; evita halo se houver alpha)
    $flat = imagecreatetruecolor($w, $h);
    imagefill($flat, 0, 0, imagecolorallocate($flat, 0, 0, 0));
    imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);

    // recorta centralizado no aspecto do painel para preencher sem distorcer
    $target = BMP_W / BMP_H;
    if ($w / $h > $target) {
        $cw = (int) round($h * $target);
        $ch = $h;
    } else {
        $cw = $w;
        $ch = (int) round($w / $target);
    }
    $cx = (int) (($w - $cw) / 2);
    $cy = (int) (($h - $ch) / 2);

    $small = imagecreatetruecolor(BMP_W, BMP_H);
    imagecopyresampled($small, $flat, 0, 0, $cx, $cy, BMP_W, BMP_H, $cw, $ch);

    // luminancia
    $g = [];
    $all = [];
    for ($y = 0; $y < BMP_H; $y++) {
        for ($x = 0; $x < BMP_W; $x++) {
            $rgb = imagecolorat($small, $x, $y);
            $v = 0.2126 * (($rgb >> 16) & 0xFF)
               + 0.7152 * (($rgb >> 8) & 0xFF)
               + 0.0722 * ($rgb & 0xFF);
            $g[$y][$x] = $v;
            $all[] = $v;
        }
    }

    // estica o contraste entre os percentis 2 e 98
    sort($all);
    $lo = $all[(int) (count($all) * 0.02)];
    $hi = $all[(int) (count($all) * 0.98)];
    $span = max(1.0, $hi - $lo);
    for ($y = 0; $y < BMP_H; $y++) {
        for ($x = 0; $x < BMP_W; $x++) {
            $n = ($g[$y][$x] - $lo) / $span;
            $n = min(1.0, max(0.0, $n));
            // gamma < 1 abre as sombras: sem isso o rosto perde volume e o
            // dithering so acende os realces
            $g[$y][$x] = pow($n, 0.78) * 255.0;
        }
    }

    // Floyd-Steinberg
    $on = [];
    for ($y = 0; $y < BMP_H; $y++) {
        for ($x = 0; $x < BMP_W; $x++) {
            $old = $g[$y][$x];
            $new = $old < 128 ? 0.0 : 255.0;
            $on[$y][$x] = $new > 0;
            $err = $old - $new;

            if ($x + 1 < BMP_W)                 $g[$y][$x + 1]     += $err * 7 / 16;
            if ($y + 1 < BMP_H) {
                if ($x > 0)                     $g[$y + 1][$x - 1] += $err * 3 / 16;
                                                $g[$y + 1][$x]     += $err * 5 / 16;
                if ($x + 1 < BMP_W)             $g[$y + 1][$x + 1] += $err * 1 / 16;
            }
        }
    }
    return $on;
}

/**
 * Converte o bitmap em subpaths horizontais. Cada run vira "M x y.5 H x2",
 * desenhado com stroke-width 1 — muito mais compacto que um <rect> por run.
 *
 * @param array<int,array<int,bool>> $on
 * @return array<int,string> um path por faixa de animacao
 */
function bitmapPaths(array $on, int $bands): array
{
    $d = array_fill(0, $bands, '');
    $rowsPerBand = (int) ceil(BMP_H / $bands);

    for ($y = 0; $y < BMP_H; $y++) {
        $band = min($bands - 1, (int) ($y / $rowsPerBand));
        $x = 0;
        while ($x < BMP_W) {
            if (empty($on[$y][$x])) {
                $x++;
                continue;
            }
            $start = $x;
            while ($x < BMP_W && !empty($on[$y][$x])) {
                $x++;
            }
            $d[$band] .= 'M' . $start . ' ' . ($y + 0.5) . 'H' . $x;
        }
    }
    return $d;
}

/* ---------- 2. Linhas do painel SYSTEM.INFO ---------- */

const INFO_COLS = 83;

$info = [
    ['Subject',        'Douglas Vieira'],
    ['Role',           'Full-Stack & Mobile Developer'],
    ['Origin',         'Brasil'],
    ['Focus',          'Sistemas Web + Apps Mobile'],
    ['Status',         'Building + Learning + Shipping'],
    ['ToolChain',      'VS Code, PhpStorm, Git, Figma'],
    ['Core.Lang',      'PHP, JavaScript, TypeScript, Java'],
    ['Core.Frontend',  'React Native, Blade, HTML/CSS'],
    ['Core.Backend',   'Laravel, Node.js'],
    ['Core.Database',  'MySQL, MariaDB'],
    ['Core.Infra',     'Apache, XAMPP, Git, GitHub Actions'],
    ['---',            'Contact'],
    ['Grid.Mail',      'douglascanal1998@gmail.com'],
    ['Grid.LinkedIn',  'douglas-vieira-685764212'],
    ['Grid.GitHub',    '@DouglasVbr'],
    ['Grid.Instagram', '@douglasvbr_oficial'],
    ['Grid.YouTube',   '@detudoeparatodos9085'],
];

/* ---------- 3. Montagem do SVG ---------- */

function e(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function buildSvg(array $paths, array $info, array $t): string
{
    $W = 1180;
    $H = 610;

    $barH = 46;
    $lpX = 36;  $lpY = 84; $lpW = 400; $lpH = 492;
    $rpX = 456; $rpW = 688;

    // retrato: 384x476 dentro do painel de 400x492
    $artW = 384;
    $artH = 476;
    $artX = $lpX + ($lpW - $artW) / 2;
    $artY = $lpY + ($lpH - $artH) / 2;
    $scaleX = $artW / BMP_W;
    $scaleY = $artH / BMP_H;

    // info: fonte 13, avanco 7.8
    $infoFS  = 13;
    $infoAdv = 7.8;
    $infoX   = $rpX + 20;
    $infoW   = INFO_COLS * $infoAdv;
    $rowY0   = 168;
    $rowStep = 23;

    $o = [];
    $o[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $W . '" height="' . $H . '" viewBox="0 0 ' . $W . ' ' . $H . '" '
         . 'font-family="ui-monospace,SFMono-Regular,Menlo,Consolas,\'Liberation Mono\',monospace" role="img" '
         . 'aria-label="Douglas Vieira - profile.sh --live">';

    $o[] = '<defs>';
    $o[] = '<linearGradient id="portraitGrad" x1="0" y1="' . $lpY . '" x2="0" y2="' . ($lpY + $lpH) . '" gradientUnits="userSpaceOnUse">'
         . '<stop offset="0" stop-color="' . $t['a1'] . '"/><stop offset="0.45" stop-color="' . $t['a2'] . '"/><stop offset="1" stop-color="' . $t['a3'] . '"/>'
         . '<animateTransform attributeName="gradientTransform" type="translate" values="0 -110; 0 110; 0 -110" dur="9s" repeatCount="indefinite"/>'
         . '</linearGradient>';
    $o[] = '<linearGradient id="panelGrad" x1="0" y1="0" x2="0" y2="1">'
         . '<stop offset="0" stop-color="' . $t['bg1'] . '"/><stop offset="1" stop-color="' . $t['bg2'] . '"/></linearGradient>';
    $o[] = '<filter id="glow3" x="-60%" y="-60%" width="220%" height="220%"><feGaussianBlur stdDeviation="3"/></filter>';
    $o[] = '<clipPath id="winClip"><rect x="2" y="2" width="' . ($W - 4) . '" height="' . ($H - 4) . '" rx="18"/></clipPath>';
    $o[] = '<clipPath id="artClip"><rect x="' . $artX . '" y="' . $artY . '" width="' . $artW . '" height="' . $artH . '"/></clipPath>';
    $o[] = '</defs>';

    // janela
    $o[] = '<rect x="2" y="2" width="' . ($W - 4) . '" height="' . ($H - 4) . '" rx="18" fill="' . $t['shell'] . '"/>';
    $o[] = '<g clip-path="url(#winClip)">';
    $o[] = '<rect x="2" y="2" width="' . ($W - 4) . '" height="' . ($H - 4) . '" fill="url(#panelGrad)"/>';
    $o[] = '<rect x="2" y="2" width="' . ($W - 4) . '" height="' . $barH . '" fill="' . $t['bar'] . '"/>';
    $o[] = '<line x1="2" y1="' . ($barH + 2) . '" x2="' . ($W - 2) . '" y2="' . ($barH + 2) . '" stroke="' . $t['hair'] . '"/>';
    foreach ([[30, '#ff5f56'], [50, '#ffbd2e'], [70, '#27c93f']] as $dot) {
        $o[] = '<circle cx="' . $dot[0] . '" cy="25" r="5.5" fill="' . $dot[1] . '"/>';
    }
    $o[] = '<text x="' . ($W / 2) . '" y="29" text-anchor="middle" font-size="12" fill="' . $t['muted'] . '">'
         . 'douglascanal1998@gmail.com - % ./profile.sh --live</text>';

    /* --- painel esquerdo: retrato --- */
    $o[] = '<text x="' . ($lpX + 2) . '" y="74" font-size="10" letter-spacing="3" fill="' . $t['dim'] . '">VISUAL.MAP</text>';
    $o[] = '<rect x="' . $lpX . '" y="' . $lpY . '" width="' . $lpW . '" height="' . $lpH . '" rx="10" fill="none" '
         . 'stroke="' . $t['accent'] . '" stroke-width="2" opacity="0.45" filter="url(#glow3)"/>';
    $o[] = '<rect x="' . $lpX . '" y="' . $lpY . '" width="' . $lpW . '" height="' . $lpH . '" rx="10" fill="' . $t['panel'] . '" stroke="' . $t['accentSoft'] . '"/>';

    $o[] = '<g clip-path="url(#artClip)"><g transform="translate(' . $artX . ',' . $artY . ') scale(' . round($scaleX, 4) . ',' . round($scaleY, 4) . ')" '
         . 'stroke="url(#portraitGrad)" stroke-width="1" shape-rendering="crispEdges">';
    foreach ($paths as $i => $d) {
        if ($d === '') {
            continue;
        }
        $begin = round($i * 0.06, 3);
        $o[] = '<path d="' . $d . '" opacity="0">'
             . '<animate attributeName="opacity" values="0;1" dur="0.5s" begin="' . $begin . 's" fill="freeze"/></path>';
    }
    $o[] = '</g></g>';

    // varredura sutil de terminal por cima do retrato
    $o[] = '<g clip-path="url(#artClip)" opacity="' . $t['scan'] . '">';
    for ($sy = 0; $sy < $artH; $sy += 3) {
        $o[] = '<rect x="' . $artX . '" y="' . ($artY + $sy) . '" width="' . $artW . '" height="1" fill="' . $t['shell'] . '"/>';
    }
    $o[] = '</g>';

    /* --- painel direito: SYSTEM.INFO --- */
    $o[] = '<text x="' . ($rpX + 2) . '" y="74" font-size="10" letter-spacing="3" fill="' . $t['dim'] . '">SYSTEM.INFO</text>';
    $o[] = '<rect x="' . $rpX . '" y="' . $lpY . '" width="' . $rpW . '" height="' . $lpH . '" rx="10" fill="none" '
         . 'stroke="' . $t['accent'] . '" stroke-width="2" opacity="0.30" filter="url(#glow3)"/>';
    $o[] = '<rect x="' . $rpX . '" y="' . $lpY . '" width="' . $rpW . '" height="' . $lpH . '" rx="10" fill="' . $t['panel'] . '" stroke="' . $t['accentSoft'] . '"/>';

    $o[] = '<text x="' . ($rpX + 20) . '" y="118" font-size="13" fill="' . $t['accent'] . '">douglascanal1998@gmail.com</text>';
    $o[] = '<text x="' . ($rpX + $rpW - 20) . '" y="118" font-size="11" text-anchor="end" fill="' . $t['live'] . '">'
         . '<tspan>&#9679;</tspan> LIVE<animate attributeName="opacity" values="1;0.25;1" dur="1.6s" repeatCount="indefinite"/></text>';
    $o[] = '<line x1="' . ($rpX + 20) . '" y1="132" x2="' . ($rpX + $rpW - 20) . '" y2="132" stroke="' . $t['hair'] . '"/>';

    $o[] = '<g font-size="' . $infoFS . '" xml:space="preserve">';
    foreach ($info as $i => $row) {
        [$label, $value] = $row;
        $y = $rowY0 + $i * $rowStep;
        $begin = round(0.9 + $i * 0.11, 3);

        if ($label === '---') {
            $dots = str_repeat('-', max(0, INFO_COLS - strlen($value) - 3));
            $content = '<tspan fill="' . $t['muted'] . '">- ' . e($value) . ' </tspan>'
                     . '<tspan fill="' . $t['leader'] . '">' . $dots . '</tspan>';
        } else {
            $fill = max(1, INFO_COLS - strlen($label) - strlen($value) - 2);
            $content = '<tspan fill="' . $t['accent'] . '">' . e($label) . ' </tspan>'
                     . '<tspan fill="' . $t['leader'] . '">' . str_repeat('.', $fill) . '</tspan>'
                     . '<tspan fill="' . $t['strong'] . '" font-weight="600"> ' . e($value) . '</tspan>';
        }

        $o[] = '<text x="' . $infoX . '" y="' . $y . '" textLength="' . round($infoW, 2) . '" lengthAdjust="spacing" opacity="0">'
             . $content
             . '<animate attributeName="opacity" values="0;1" dur="0.3s" begin="' . $begin . 's" fill="freeze"/>'
             . '</text>';
    }
    $o[] = '</g>';

    /* --- rodape --- */
    $o[] = '<text x="' . ($rpX + 20) . '" y="' . ($lpY + $lpH + 22) . '" font-size="12" fill="' . $t['muted'] . '" opacity="0">'
         . '&#9656; Mais sobre mim e meus projetos no README abaixo &#8595; '
         . '<tspan fill="' . $t['accent'] . '">&#9608;<animate attributeName="fill-opacity" values="1;0;1" dur="1s" repeatCount="indefinite"/></tspan>'
         . '<animate attributeName="opacity" values="0;1" dur="0.4s" begin="2.9s" fill="freeze"/></text>';

    $o[] = '</g></svg>';

    return implode("\n", $o);
}

/* ---------- 4. Temas ---------- */

$dark = [
    'shell' => '#070B16', 'bg1' => '#0A101F', 'bg2' => '#0C1426', 'bar' => '#0B1222',
    'panel' => '#0A101F', 'hair' => 'rgba(255,255,255,0.10)',
    'accent' => '#22D3EE', 'accentSoft' => 'rgba(34,211,238,0.35)',
    'muted' => '#94A3B8', 'dim' => '#475569', 'strong' => '#F8FAFC',
    'leader' => 'rgba(148,163,184,0.35)', 'live' => '#10B981',
    'a1' => '#60A5FA', 'a2' => '#A78BFA', 'a3' => '#22D3EE',
    'scan' => '0.30',
];

$light = [
    'shell' => '#E2E8F0', 'bg1' => '#FFFFFF', 'bg2' => '#F1F5F9', 'bar' => '#E9EEF5',
    'panel' => '#FFFFFF', 'hair' => 'rgba(15,23,42,0.12)',
    'accent' => '#0891B2', 'accentSoft' => 'rgba(8,145,178,0.35)',
    'muted' => '#475569', 'dim' => '#94A3B8', 'strong' => '#0F172A',
    'leader' => 'rgba(71,85,105,0.35)', 'live' => '#059669',
    'a1' => '#2563EB', 'a2' => '#7C3AED', 'a3' => '#0891B2',
    'scan' => '0.22',
];

/* ---------- 5. Executa ---------- */

$on = ditherPortrait($AVATAR);
$paths = bitmapPaths($on, 24);

file_put_contents($OUT . '/dark.svg', buildSvg($paths, $info, $dark));
file_put_contents($OUT . '/light.svg', buildSvg($paths, $info, $light));

echo 'dark.svg  : ' . number_format(filesize($OUT . '/dark.svg')) . " bytes\n";
echo 'light.svg : ' . number_format(filesize($OUT . '/light.svg')) . " bytes\n";
