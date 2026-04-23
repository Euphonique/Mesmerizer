<?php
/**
 * One-time script: generates SVG thumbnails for all presets
 * that have a .json but no .svg file yet.
 *
 * Run once via: php seed-presets.php
 * Or visit in browser: http://yoursite/seed-presets.php
 */

$dir = __DIR__ . '/presets';
if (!is_dir($dir)) { echo "No presets/ directory found.\n"; exit(1); }

$segments = 120;

function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    return [
        'r' => intval(substr($hex, 0, 2), 16),
        'g' => intval(substr($hex, 2, 2), 16),
        'b' => intval(substr($hex, 4, 2), 16),
    ];
}

function lightenChannel(int $c, float $amount): int {
    return (int) round($c + (255 - $c) * $amount);
}

function lightenHex(string $hex, float $amount): string {
    $rgb = hexToRgb($hex);
    return sprintf('#%02x%02x%02x',
        lightenChannel($rgb['r'], $amount),
        lightenChannel($rgb['g'], $amount),
        lightenChannel($rgb['b'], $amount)
    );
}

function generateSVG(array $cfg): string {
    global $segments;
    $count     = $cfg['count'] ?? 6;
    $lobes     = $cfg['lobes'] ?? 3;
    $radius    = $cfg['radius'] ?? 175;
    $spacing   = $cfg['spacing'] ?? 0;
    $amp       = $cfg['amp'] ?? 38;
    $phaseStep = ($cfg['phaseStep'] ?? 6) * M_PI / 180;
    $stroke    = $cfg['stroke'] ?? 2.4;
    $bg        = $cfg['bg'] ?? '#0a1a28';
    $ringA     = $cfg['ringA'] ?? '#78b4ff';
    $ringB     = $cfg['ringB'] ?? '#78f0c8';

    $paths = '';
    for ($i = 0; $i < $count; $i++) {
        $isA   = ($i % 2 === 0);
        $col   = lightenHex($isA ? $ringA : $ringB, 0.75);
        $baseR = $radius + $i * $spacing;
        $phase = $i * $phaseStep;

        $d = '';
        for ($j = 0; $j <= $segments; $j++) {
            $theta = ($j / $segments) * M_PI * 2;
            $r = $baseR + $amp * cos($lobes * ($theta - $phase));
            $x = $r * cos($theta);
            $y = $r * sin($theta);
            $d .= ($j === 0 ? 'M' : 'L') . round($x, 1) . ',' . round($y, 1);
        }
        $d .= 'Z';
        $paths .= sprintf(
            '<path d="%s" fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round" opacity="0.95"/>',
            $d, $col, $stroke
        ) . "\n";
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="-250 -250 500 500">
<rect x="-250" y="-250" width="500" height="500" fill="{$bg}"/>
{$paths}</svg>
SVG;
}

$count = 0;
foreach (glob($dir . '/*.json') as $jsonFile) {
    $name    = basename($jsonFile, '.json');
    $svgFile = $dir . '/' . $name . '.svg';

    if (file_exists($svgFile)) {
        echo "SKIP  $name (SVG exists)\n";
        continue;
    }

    $cfg = json_decode(file_get_contents($jsonFile), true);
    if (!$cfg) {
        echo "ERROR $name (invalid JSON)\n";
        continue;
    }

    file_put_contents($svgFile, generateSVG($cfg));
    echo "OK    $name\n";
    $count++;
}

echo "\nDone. Generated $count SVG(s).\n";
