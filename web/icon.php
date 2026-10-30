<?php
declare(strict_types=1);
/* Icono de la app: la inicial del restaurante con los colores del tema (TEMA_COLORES). Se genera solo y cambia si cambias el nombre. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
/** Si el tema trae su propio icono (tema/assets/icono.png, cuadrado), se usa ese. */
$custom = __DIR__ . '/tema/assets/icono.png';
$size = in_array((int)($_GET['s'] ?? 192), [180, 192, 512], true) ? (int)$_GET['s'] : 192;
$maskable = !empty($_GET['m']);
$name = installed() ? settings()['name'] : SETTINGS_DEFAULTS['name'];
$letter = mb_strtoupper(mb_substr(trim($name) ?: 'R', 0, 1));
$font = __DIR__ . '/lib/fuente-monograma.ttf';

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
$rgb = fn(string $hex) => sscanf($hex, '#%02x%02x%02x');
if (is_file($custom) && function_exists('imagecreatefrompng') && ($src = @imagecreatefrompng($custom))) {
    $out = imagecreatetruecolor($size, $size);
    [$r, $g, $b] = $rgb(tema_color('fondo'));
    imagefill($out, 0, 0, imagecolorallocate($out, $r, $g, $b));
    $pad = $maskable ? (int)($size * 0.12) : 0;
    imagecopyresampled($out, $src, $pad, $pad, 0, 0, $size - 2 * $pad, $size - 2 * $pad, imagesx($src), imagesy($src));
    imagepng($out);
    exit;
}
if (!function_exists('imagettftext') || !is_file($font)) {
    readfile(__DIR__ . '/panel/icons/icon-512.png');
    exit;
}
$k = 4; // dibujar grande y reducir: bordes suaves
$S = $size * $k;
$im = imagecreatetruecolor($S, $S);
[$r, $g, $b] = $rgb(tema_color('fondo', '#000000'));
imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
[$r, $g, $b] = $rgb(tema_color('texto', '#F2ECE4'));
$hueso = imagecolorallocate($im, $r, $g, $b);
[$r, $g, $b] = $rgb(tema_color('acento', '#E9B8AE'));
$rubor = imagecolorallocate($im, $r, $g, $b);
$fs = $S * ($maskable ? 0.42 : 0.56);
$box = imagettfbbox($fs, 0, $font, $letter);
$w = $box[2] - $box[0];
$hgt = $box[1] - $box[7];
imagettftext($im, $fs, 0, (int)(($S - $w) / 2 - $box[0]), (int)(($S + $hgt) / 2 - $box[1]), $hueso, $font, $letter);
// pequeño destello bajo la letra
$d = (int)($S * 0.035);
$cy = (int)(($S + $hgt) / 2 + $S * ($maskable ? 0.06 : 0.07));
imagefilledpolygon($im, [$S / 2, $cy - $d, $S / 2 + $d, $cy, $S / 2, $cy + $d, $S / 2 - $d, $cy], $rubor);
$out = imagecreatetruecolor($size, $size);
imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $S, $S);
imagepng($out);
