<?php
/**
 * Gera projects-dark.svg e projects-light.svg â€” cartao de projetos no mesmo
 * estilo de janela de terminal do banner.
 *
 * So entram dados reais da API do GitHub (nome, linguagem, ano do ultimo push).
 * Nenhuma descricao inventada: repo sem description fica sem a linha.
 */

$OUT = dirname(__DIR__);

$repos = [
    ['bingozen',            'PHP',        '2025', ''],
    ['mercado-livre',       'TypeScript', '2025', ''],
    ['portal-de-noticias',  'PHP',        '2025', ''],
    ['ProjetoMobileFinal',  'TypeScript', '2025', 'Barbearia'],
    ['GeradorDeRifa',       'PHP',        '2025', ''],
    ['DesignPatternsEmPhp', 'PHP',        '2025', ''],
];

// cores oficiais de linguagem do GitHub
$langColor = [
    'PHP'        => '#4F5D95',
    'TypeScript' => '#3178C6',
    'JavaScript' => '#F1E05A',
    'Java'       => '#B07219',
    'Blade'      => '#F7523F',
    'CSS'        => '#663399',
];

function e(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function buildProjects(array $repos, array $langColor, array $t): string
{
    $W = 1180;
    $barH = 46;
    $pad = 36;
    $cols = 3;
    $gap = 20;
    $cardW = (int) (($W - 2 * $pad - ($cols - 1) * $gap) / $cols);
    $cardH = 112;
    $rows = (int) ceil(count($repos) / $cols);
    $gridY = $barH + 46;
    $H = $gridY + $rows * $cardH + ($rows - 1) * $gap + 34;

    $o = [];
    $o[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $W . '" height="' . $H . '" viewBox="0 0 ' . $W . ' ' . $H . '" '
         . 'font-family="ui-monospace,SFMono-Regular,Menlo,Consolas,\'Liberation Mono\',monospace" role="img" '
         . 'aria-label="Projetos de Douglas Vieira">';

    $o[] = '<defs>'
         . '<linearGradient id="pPanel" x1="0" y1="0" x2="0" y2="1">'
         . '<stop offset="0" stop-color="' . $t['bg1'] . '"/><stop offset="1" stop-color="' . $t['bg2'] . '"/></linearGradient>'
         . '<clipPath id="pClip"><rect x="2" y="2" width="' . ($W - 4) . '" height="' . ($H - 4) . '" rx="18"/></clipPath>'
         . '</defs>';

    $o[] = '<rect x="2" y="2" width="' . ($W - 4) . '" height="' . ($H - 4) . '" rx="18" fill="' . $t['shell'] . '"/>';
    $o[] = '<g clip-path="url(#pClip)">';
    $o[] = '<rect x="2" y="2" width="' . ($W - 4) . '" height="' . ($H - 4) . '" fill="url(#pPanel)"/>';
    $o[] = '<rect x="2" y="2" width="' . ($W - 4) . '" height="' . $barH . '" fill="' . $t['bar'] . '"/>';
    $o[] = '<line x1="2" y1="' . ($barH + 2) . '" x2="' . ($W - 2) . '" y2="' . ($barH + 2) . '" stroke="' . $t['hair'] . '"/>';
    foreach ([[30, '#ff5f56'], [50, '#ffbd2e'], [70, '#27c93f']] as $dot) {
        $o[] = '<circle cx="' . $dot[0] . '" cy="25" r="5.5" fill="' . $dot[1] . '"/>';
    }
    $o[] = '<text x="' . ($W / 2) . '" y="29" text-anchor="middle" font-size="12" fill="' . $t['muted'] . '">'
         . 'DouglasVbr - % ls ~/projects</text>';
    $o[] = '<text x="' . $pad . '" y="' . ($barH + 28) . '" font-size="10" letter-spacing="3" fill="' . $t['dim'] . '">PROJECTS</text>';

    foreach ($repos as $i => $r) {
        [$name, $lang, $year, $desc] = $r;
        $cx = $pad + ($i % $cols) * ($cardW + $gap);
        $cy = $gridY + (int) ($i / $cols) * ($cardH + $gap);
        $color = $langColor[$lang] ?? $t['accent'];
        $begin = round($i * 0.09, 2);

        $o[] = '<g opacity="0"><animate attributeName="opacity" values="0;1" dur="0.45s" begin="' . $begin . 's" fill="freeze"/>';
        $o[] = '<rect x="' . $cx . '" y="' . $cy . '" width="' . $cardW . '" height="' . $cardH . '" rx="10" '
             . 'fill="' . $t['card'] . '" stroke="' . $t['accentSoft'] . '"/>';
        // faixa da linguagem
        $o[] = '<rect x="' . $cx . '" y="' . $cy . '" width="4" height="' . $cardH . '" rx="2" fill="' . $color . '"/>';

        $o[] = '<text x="' . ($cx + 20) . '" y="' . ($cy + 34) . '" font-size="15" font-weight="600" fill="' . $t['strong'] . '">'
             . e($name) . '</text>';

        if ($desc !== '') {
            $o[] = '<text x="' . ($cx + 20) . '" y="' . ($cy + 58) . '" font-size="12" fill="' . $t['muted'] . '">'
                 . e($desc) . '</text>';
        }

        $o[] = '<circle cx="' . ($cx + 25) . '" cy="' . ($cy + $cardH - 24) . '" r="5" fill="' . $color . '"/>';
        $o[] = '<text x="' . ($cx + 36) . '" y="' . ($cy + $cardH - 20) . '" font-size="12" fill="' . $t['muted'] . '">'
             . e($lang) . '</text>';
        $o[] = '<text x="' . ($cx + $cardW - 20) . '" y="' . ($cy + $cardH - 20) . '" font-size="11" text-anchor="end" fill="' . $t['dim'] . '">'
             . e($year) . '</text>';
        $o[] = '</g>';
    }

    $o[] = '</g></svg>';
    return implode("\n", $o);
}

$dark = [
    'shell' => '#070B16', 'bg1' => '#0A101F', 'bg2' => '#0C1426', 'bar' => '#0B1222',
    'card' => '#0C1426', 'hair' => 'rgba(255,255,255,0.10)',
    'accent' => '#22D3EE', 'accentSoft' => 'rgba(34,211,238,0.25)',
    'muted' => '#94A3B8', 'dim' => '#475569', 'strong' => '#F8FAFC',
];

$light = [
    'shell' => '#E2E8F0', 'bg1' => '#FFFFFF', 'bg2' => '#F1F5F9', 'bar' => '#E9EEF5',
    'card' => '#FFFFFF', 'hair' => 'rgba(15,23,42,0.12)',
    'accent' => '#0891B2', 'accentSoft' => 'rgba(8,145,178,0.25)',
    'muted' => '#475569', 'dim' => '#94A3B8', 'strong' => '#0F172A',
];

file_put_contents($OUT . '/projects-dark.svg', buildProjects($repos, $langColor, $dark));
file_put_contents($OUT . '/projects-light.svg', buildProjects($repos, $langColor, $light));

echo 'projects-dark.svg  : ' . number_format(filesize($OUT . '/projects-dark.svg')) . " bytes\n";
echo 'projects-light.svg : ' . number_format(filesize($OUT . '/projects-light.svg')) . " bytes\n";

