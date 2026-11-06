<?php
declare(strict_types=1);
/* Manifiesto para instalar la web como app desde el navegador. Toma el nombre de los ajustes. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$s = installed() ? settings() : SETTINGS_DEFAULTS;
echo json_encode([
    'name' => $s['name'],
    'short_name' => mb_substr($s['name'], 0, 12),
    'description' => $s['tagline'] . '. Reserva mesa y consulta la carta.',
    'lang' => 'es',
    'start_url' => './?app=1',
    'scope' => './',
    'display' => 'standalone',
    'background_color' => tema_color('fondo'),
    'theme_color' => tema_color('fondo'),
    'icons' => [
        ['src' => 'icon.php?s=192', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => 'icon.php?s=512', 'sizes' => '512x512', 'type' => 'image/png'],
        ['src' => 'icon.php?s=512&m=1', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
    'shortcuts' => [
        ['name' => 'Reservar mesa', 'url' => './reservar.php?app=1#reservar'],
        ['name' => 'Mis reservas', 'url' => './cuenta.php'],
        ['name' => 'La carta', 'url' => './carta.php'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
