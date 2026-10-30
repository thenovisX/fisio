<?php
declare(strict_types=1);

/*
 * Herramientas para los temas (el diseño de cada web, en web/tema/).
 * El tema pinta el HTML como quiera; desde aquí se ponen las piezas que la web necesita para
 * funcionar y cumplir la ley: SEO, aviso de cookies, iconos de la app, ventana de reserva y SDK.
 */


// Idioma de la visita (las páginas cargan este archivo antes de pintar nada)
detect_lang();
// Content-Security-Policy: los <script> en línea necesitan el atributo nonce con csp_nonce()
send_csp();

/** Versión del SDK: súbela si cambias algo de web/sdk/ para que los navegadores lo descarguen de nuevo. */
const SDK_V = '7';

/** Versión de los archivos del tema (la define el tema en tema.php; si no, 1). */
function tema_v(): string
{
    return defined('TEMA_VERSION') ? (string)TEMA_VERSION : '1';
}

/** Ruta de un archivo del tema con su versión, para usar en href/src: asset('estilo.css') → tema/assets/estilo.css?v=… */
function asset(string $file): string
{
    return 'tema/assets/' . ltrim($file, '/') . '?v=' . tema_v();
}

/** Color de un rol del tema (fondo, texto, acento…), con un valor por defecto. */
function tema_color(string $rol, string $def = '#000000'): string
{
    $c = defined('TEMA_COLORES') ? TEMA_COLORES : [];
    return preg_match('/^#[0-9a-f]{6}$/i', $c[$rol] ?? '') ? $c[$rol] : $def;
}

/** ¿Tiene el tema esta página opcional (tema/carta.php, tema/reservar.php…)? */
function tema_tiene(string $vista): bool
{
    return preg_match('/^[a-z0-9_-]+$/', $vista) === 1 && is_file(dirname(__DIR__) . '/tema/' . $vista . '.php');
}

/**
 * Pinta una página del tema: carga web/tema/<vista>.php con las variables que se le pasen.
 * Siempre están disponibles $s (ajustes del restaurante) y $cliente (cliente con sesión o null).
 */
function tema_render(string $vista, array $vars = []): never
{
    if (!preg_match('/^[a-z0-9_-]+$/', $vista)) throw new RuntimeException('Vista no válida');
    $file = dirname(__DIR__) . '/tema/' . $vista . '.php';
    if (!is_file($file)) throw new RuntimeException("Falta el archivo del tema: tema/$vista.php");
    if (!headers_sent()) header('Cache-Control: no-cache, must-revalidate, max-age=0');
    $s = settings_tr();
    $cliente = current_customer();
    extract($vars, EXTR_SKIP);
    require $file;
    exit;
}

/**
 * Los ajustes del restaurante con los textos propios ya traducidos al idioma de la visita (frase corta, presentación,
 * título del pie, frases destacadas y políticas). Es lo que recibe el tema en $s: imprimes $s['tagline'] y sale en
 * el idioma que toca. Los datos de contacto y el nombre nunca se traducen.
 */
function settings_tr(): array
{
    $s = settings();
    if (lang() === 'es') return $s;
    foreach (['tagline', 'description', 'footer_title'] as $k) $s[$k] = tr((string)$s[$k]);
    if ($s['ribbon'] !== '') {
        $s['ribbon'] = implode(', ', array_map(fn($r) => tr(trim($r)), array_filter(array_map('trim', explode(',', $s['ribbon'])))));
    }
    $s['policies'] = implode("\n", array_map(function ($line) {
        $line = trim($line);
        if ($line === '') return '';
        [$t, $txt] = array_pad(explode(':', $line, 2), 2, '');
        return $txt === '' ? tr(trim($t)) : tr(trim($t)) . ': ' . tr(trim($txt));
    }, preg_split('/\R/', (string)$s['policies'])));
    return $s;
}

/** La carta pública con los nombres y descripciones en el idioma de la visita. */
function carta_publica(): array
{
    $m = menu_data(false);
    if (lang() === 'es') return $m;
    $m['allergens'] = array_map(fn($a) => ['name' => t($a['name']), 'desc' => t($a['desc'])] + $a, $m['allergens']);
    foreach ($m['categories'] as &$c) {
        $c['name'] = tr($c['name']);
        foreach ($c['dishes'] as &$d) {
            $d['name'] = tr($d['name']);
            $d['description'] = tr($d['description']);
        }
        unset($d);
    }
    unset($c);
    return $m;
}

/** Código del idioma para <html lang="…">: es-ES, en-GB, pt-PT, gl-ES. */
function html_lang(): string
{
    return LANGS[lang()]['locale'];
}

/** Bandera circular de cada idioma (dibujada en SVG: Windows no muestra emojis de banderas y Galicia no tiene emoji). */
function flag_svg(string $l): string
{
    $f = [
        'es' => '<rect width="60" height="60" fill="#AA151B"/><rect y="15" width="60" height="30" fill="#F1BF00"/>',
        'en' => '<rect width="60" height="60" fill="#012169"/><path d="M0 0L60 60M60 0L0 60" stroke="#fff" stroke-width="12"/><path d="M0 0L60 60M60 0L0 60" stroke="#C8102E" stroke-width="4"/><path d="M30 0V60M0 30H60" stroke="#fff" stroke-width="18"/><path d="M30 0V60M0 30H60" stroke="#C8102E" stroke-width="10"/>',
        'pt' => '<rect width="60" height="60" fill="#DA291C"/><rect width="24" height="60" fill="#046A38"/><circle cx="24" cy="30" r="10" fill="none" stroke="#FFE900" stroke-width="3"/><path d="M19 24h10v8a5 5 0 0 1-10 0z" fill="#fff" stroke="#DA291C" stroke-width="1.5"/>',
        'gl' => '<rect width="60" height="60" fill="#fff"/><path d="M-6 6L54 66L66 54L6 -6z" fill="#0099CC"/>',
    ];
    return '<svg viewBox="0 0 60 60" aria-hidden="true" focusable="false">' . ($f[$l] ?? '') . '</svg>';
}

/**
 * Selector de idioma con banderas circulares: <?= selector_idioma() ?> en la cabecera. Devuelve '' si el restaurante
 * solo tiene un idioma activado. Clases: .r-langs (contenedor), .r-lang (cada una, .is-on la actual), .r-flag.
 */
function selector_idioma(string $clase = ''): string
{
    $on = langs_on();
    if (count($on) < 2) return '';
    $cur = lang();
    $o = '<div class="r-langs' . ($clase !== '' ? ' ' . h($clase) : '') . '" role="group" aria-label="' . h(t('Idioma')) . '">';
    foreach ($on as $l) {
        $o .= '<a class="r-lang' . ($l === $cur ? ' is-on' : '') . '" href="' . h(lang_url($l)) . '" hreflang="' . LANGS[$l]['locale'] . '" lang="' . LANGS[$l]['locale'] . '"'
            . ($l === $cur ? ' aria-current="true"' : '') . ' title="' . h(LANGS[$l]['name']) . '"><span class="r-flag">' . flag_svg($l) . '</span><span class="r-sr">' . h(LANGS[$l]['name']) . '</span></a>';
    }
    return $o . '</div>';
}

/** Textos del JavaScript (SDK y tema) en el idioma actual: es lo que va en window.I18N. */
function js_i18n(): array
{
    if (lang() === 'es') return [];
    $keys = array_merge(require __DIR__ . '/lang/js-keys.php', (array)(tema_lang()['js'] ?? []));
    $out = [];
    foreach ($keys as $k) $out[$k] = t($k);
    $out['_days'] = I18N_DAYS[lang()];
    $out['_months'] = I18N_MONTHS[lang()];
    return $out;
}

/* ---------- Enlaces útiles ---------- */

function tel_href(string $t): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $t);
}

function maps_href(): string
{
    $s = settings();
    if ($s['maps_url'] !== '') return social_url('', $s['maps_url']);
    return 'https://www.google.com/maps/search/?api=1&query=' . urlencode(trim($s['name'] . ' ' . $s['address'] . ' ' . $s['city']));
}

/** Enlace de WhatsApp del restaurante ('' si no tiene). */
function wa_href(string $text = ''): string
{
    $n = wa_number(settings()['whatsapp']);
    return $n === '' ? '' : 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Redes sociales configuradas: ['Instagram' => url, …] (solo las que tienen valor). */
function redes(): array
{
    $s = settings();
    return array_filter([
        'Instagram' => social_url('instagram', $s['instagram']),
        'Facebook' => social_url('facebook', $s['facebook']),
        'TikTok' => social_url('tiktok', $s['tiktok']),
        'TripAdvisor' => social_url('tripadvisor', $s['tripadvisor']),
    ]);
}

/**
 * Horario para pintar: [['dia' => 'Lunes', 'texto' => '13:00–16:00', 'cocina' => '13:30–15:30', 'hoy' => bool], …]
 * 'cocina' solo viene si hay horario de cocina activado y ese día es distinto del de apertura ('' si no).
 */
function horario(): array
{
    $s = settings();
    $out = [];
    foreach (WEEKDAYS as $n => $dia) {
        $open = $s['hours'][$n] ?? [];
        $kit = $s['kitchen_hours'][$n] ?? [];
        $cocina = $s['kitchen_on'] && $open && $kit != $open ? ranges_text($kit) : '';
        $out[] = ['dia' => day_name((int)$n, true), 'texto' => ranges_text($open), 'cocina' => $cocina === t('Cerrado') ? t('cerrada') : $cocina, 'hoy' => (string)$n === date('N')];
    }
    return $out;
}

/** ¿Abre hoy? Devuelve el texto del horario de hoy o 'Cerrado'. */
function horario_hoy(): string
{
    $s = settings();
    if (in_array(today(), $s['closed_dates'], true)) return t('Cerrado hoy');
    return ranges_text($s['hours'][date('N')] ?? []);
}

/** Políticas de reserva como lista: [['titulo' => …, 'texto' => …], …] (cada línea del panel es «Título: texto»). */
function politicas(): array
{
    $out = [];
    foreach (preg_split('/\R/', (string)settings()['policies']) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        [$t, $txt] = array_pad(explode(':', $line, 2), 2, '');
        $out[] = $txt === '' ? ['titulo' => '', 'texto' => tr(trim($t))] : ['titulo' => tr(trim($t)), 'texto' => tr(trim($txt))];
    }
    return $out;
}

/** Icono de un alérgeno (dibujos en sdk/alergenos.svg). */
function al_icon(string $key, int $size = 22, string $label = ''): string
{
    $aria = $label === '' ? 'aria-hidden="true"' : 'role="img" aria-label="' . h($label) . '"';
    return '<svg class="al-ic" width="' . $size . '" height="' . $size . '" ' . $aria . '>'
        . ($label !== '' ? '<title>' . h($label) . '</title>' : '')
        . '<use href="sdk/alergenos.svg?v=' . SDK_V . '#al-' . h($key) . '"></use></svg>';
}

/* ---------- Piezas obligatorias de cada página ---------- */

/**
 * Va dentro de <head>, después de <title>. Pone: descripción y canonical para Google, verificación de
 * Search Console, datos estructurados del restaurante (en la portada), modo de consentimiento de Google,
 * aviso de cookies, manifest e iconos de la app, y los estilos base del SDK.
 */
function head_tags(string $description = ''): void
{
    $s = settings();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $home = $script === 'index.php';
    $hero = photo_abs(array_key_first(tema_fotos()) ?? '', 1200);
    $pageUrl = base_url() . ($home ? '/' : '/' . $script);
    $tagline = tr($s['tagline']);
    ?>
<meta name="description" content="<?= h($description ?: (lang() === 'es' && $s['seo_description'] ? $s['seo_description'] : trim($s['name'] . ' · ' . $tagline . ($s['city'] ? ' ' . t('en') . ' ' . $s['city'] : '') . '. ' . t('Reserva mesa online y consulta la carta.')))) ?>">
<link rel="canonical" href="<?= h($pageUrl . (lang() !== 'es' ? '?lang=' . lang() : '')) ?>">
<?php foreach (langs_on() as $l): ?><link rel="alternate" hreflang="<?= LANGS[$l]['locale'] ?>" href="<?= h($pageUrl . ($l !== 'es' ? '?lang=' . $l : '')) ?>">
<?php endforeach; ?><link rel="alternate" hreflang="x-default" href="<?= h($pageUrl) ?>">
<?php if ($s['gsc_verify'] !== ''): ?><meta name="google-site-verification" content="<?= h($s['gsc_verify']) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= h($s['name']) ?>">
<meta property="og:description" content="<?= h($tagline) ?>">
<meta property="og:locale" content="<?= str_replace('-', '_', LANGS[lang()]['locale']) ?>">
<?php if ($hero): ?><meta property="og:image" content="<?= h($hero) ?>">
<?php endif; ?>
<meta name="theme-color" content="<?= h(tema_color('fondo')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?= h($s['name']) ?>">
<link rel="manifest" href="manifest.php">
<link rel="icon" href="icon.php?s=192" type="image/png">
<link rel="apple-touch-icon" href="icon.php?s=180">
<script nonce="<?= csp_nonce() ?>">
  window.I18N = <?= json_encode(js_i18n(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  window.SITE_TRACKING = <?= json_encode(['ga' => $s['ga_id'], 'gtm' => $s['gtm_id'], 'pixel' => $s['pixel_id']], JSON_HEX_TAG) ?>;
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500 });
</script>
<script src="sdk/consent.js?v=<?= SDK_V ?>" nonce="<?= csp_nonce() ?>" defer></script>
<link rel="stylesheet" href="sdk/restaurante.css?v=<?= SDK_V ?>">
<?php if ($home): ?>
<script type="application/ld+json" nonce="<?= csp_nonce() ?>"><?= json_encode(array_filter([
    '@context' => 'https://schema.org', '@type' => 'Restaurant', 'name' => $s['name'],
    'address' => trim($s['address'] . ', ' . $s['city'], ', '), 'telephone' => $s['phone'],
    'acceptsReservations' => true, 'url' => base_url(), 'image' => $hero,
    'description' => $s['seo_description'] ?: $tagline,
    'openingHoursSpecification' => array_merge(...array_map(fn($d) => array_map(fn($r) => [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][(int)$d],
        'opens' => $r[0], 'closes' => $r[1],
    ], $s['hours'][$d] ?? []), array_keys(WEEKDAYS))),
    'sameAs' => array_values(redes()),
    'hasMenu' => base_url() . '/#carta',
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif;
}

/**
 * Va justo antes de </body>. Pone la ventana de reserva (se abre con cualquier elemento data-book),
 * los datos del restaurante para el JavaScript (window.RESTAURANT) y el SDK.
 * Opciones: ['reserva' => false] para no incluir la ventana de reserva (si el tema hace la suya).
 */
function body_end(array $opts = []): void
{
    $s = settings();
    $c = current_customer();
    if ($opts['reserva'] ?? true) include __DIR__ . '/reserva-dialogo.php';
    $data = ['today' => today(), 'daysAhead' => (int)$s['days_ahead'], 'maxPeople' => (int)$s['max_people'], 'name' => $s['name'],
        'hours' => $s['hours'], 'closed' => $s['closed_dates'], 'phone' => $s['phone'], 'now' => date('Y-m-d H:i'), 'customer' => customer_out($c)];
    ?>
<script nonce="<?= csp_nonce() ?>">window.RESTAURANT = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="sdk/restaurante.js?v=<?= SDK_V ?>" nonce="<?= csp_nonce() ?>" defer></script>
    <?php
}

/** Enlace que abre el aviso de cookies para cambiar la elección (obligatorio tenerlo en el pie). */
/**
 * Formulario «Borrar mis datos»: el cliente escribe su email o teléfono y recibe un enlace para confirmarlo.
 * Lo envía el SDK (form[data-erase]); el tema solo le da estilo (.r-erase…). $class se añade al botón.
 */
function borrar_form(string $class = ''): string
{
    return '<form class="r-erase" data-erase novalidate>'
        . '<label for="r-erase-c">' . t('Tu email o tu teléfono') . '</label>'
        . '<div class="r-erase-row"><input id="r-erase-c" name="contact" type="text" inputmode="email" autocomplete="email" maxlength="120" placeholder="' . h(t('ejemplo@correo.com o 600 123 456')) . '" required>'
        . '<button type="submit"' . ($class ? ' class="' . h($class) . '"' : '') . '>' . t('Borrar mis datos') . '</button></div>'
        . '<label class="r-hp" aria-hidden="true">' . t('No rellenar') . ' <input name="website" tabindex="-1" autocomplete="off"></label>'
        . '<p class="r-erase-help">' . t('Borraremos tu cuenta, tus reservas y el registro de actividad. Las reservas que aún no hayan pasado se cancelarán. Para que nadie pueda borrar los datos de otra persona, te enviaremos un enlace para confirmarlo: por email si escribes tu email, o por WhatsApp si escribes tu teléfono.') . '</p>'
        . '<p class="r-erase-msg" role="status" aria-live="polite"></p></form>';
}

function cookies_link(string $text = '', string $class = ''): string
{
    $text = $text !== '' ? $text : t('Configurar cookies');
    return '<a href="legal.php#cookies" data-cookies' . ($class ? ' class="' . h($class) . '"' : '') . '>' . h($text) . '</a>';
}

/** Precio en formato español: 12,50 € */
function precio(float $n): string
{
    return number_format($n, 2, ',', '.') . ' €';
}
