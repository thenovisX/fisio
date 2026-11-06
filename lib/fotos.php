<?php
declare(strict_types=1);

/*
 * Fotos de la web. Cada tema declara sus huecos en TEMA_FOTOS (tema/tema.php): nombre en el panel,
 * foto de ejemplo, texto alternativo y autor. El cliente las cambia desde la app (Ajustes → Fotos).
 * Las fotos de ejemplo de Unsplash se sirven desde este servidor (foto.php): el navegador del
 * visitante nunca se conecta a Unsplash.
 */

/** Anchos permitidos para las fotos de ejemplo (se guardan en caché en el servidor). */
const PHOTO_WIDTHS = [480, 600, 640, 800, 900, 1000, 1024, 1200, 1300, 1600, 2200];

/** Huecos de foto del tema: clave => ['label', 'src', 'alt', 'by'?, 'url'?] */
function tema_fotos(): array
{
    return defined('TEMA_FOTOS') ? TEMA_FOTOS : [];
}

function photo_cache_file(string $key, int $w): string
{
    return dirname(__DIR__) . '/uploads/cache/' . $key . '-' . $w . '.jpg';
}

function is_unsplash(string $src): bool
{
    return str_starts_with($src, 'https://images.unsplash.com/');
}

/** URL (relativa) de una foto a un ancho concreto: la propia del cliente o la de ejemplo del tema. */
function photo(string $key, int $w = 1600): string
{
    $own = settings()['photos'][$key] ?? '';
    if ($own !== '') return $own;
    $src = tema_fotos()[$key]['src'] ?? '';
    if ($src === '') return '';
    if (is_unsplash($src)) {
        if (!in_array($w, PHOTO_WIDTHS, true)) $w = 1600;
        return is_file(photo_cache_file($key, $w)) ? "uploads/cache/$key-$w.jpg" : "foto.php?k=$key&w=$w";
    }
    return $src;
}

/** Igual que photo() pero con la dirección completa (para Google, redes sociales y emails). */
function photo_abs(string $key, int $w = 1600): string
{
    $p = $key === '' ? '' : photo($key, $w);
    if ($p === '' || preg_match('#^https?://#', $p)) return $p;
    return base_url() . '/' . $p;
}

/** srcset para <img> (solo fotos de ejemplo de Unsplash; las propias ya vienen a un tamaño). */
function photo_srcset(string $key, array $widths = [640, 1024, 1600, 2200]): string
{
    if (!empty(settings()['photos'][$key])) return '';
    if (!is_unsplash(tema_fotos()[$key]['src'] ?? '')) return '';
    return implode(', ', array_map(fn($w) => photo($key, $w) . " {$w}w", $widths));
}

/** Texto alternativo de una foto. */
function photo_alt(string $key): string
{
    return t(tema_fotos()[$key]['alt'] ?? '');
}

/** <img> listo para usar, con srcset, alt y carga diferida. */
function photo_img(string $key, int $w = 1200, string $sizes = '100vw', string $attrs = 'loading="lazy"'): string
{
    $src = photo($key, $w);
    if ($src === '') return '';
    $set = photo_srcset($key);
    return '<img src="' . h($src) . '"' . ($set ? ' srcset="' . h($set) . '" sizes="' . h($sizes) . '"' : '') . ' alt="' . h(photo_alt($key)) . '" ' . $attrs . '>';
}

/** Fotos de ejemplo que siguen en uso, para poner los créditos en el pie (lo pide Unsplash como cortesía). */
function photo_credits(): array
{
    $own = settings()['photos'];
    return array_filter(tema_fotos(), fn($p, $k) => empty($own[$k]) && !empty($p['by']), ARRAY_FILTER_USE_BOTH);
}
