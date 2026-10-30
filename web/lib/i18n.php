<?php
declare(strict_types=1);

/*
 * Idiomas de la web pública: español (original), inglés, portugués y gallego.
 * - Textos fijos de la web: t('Texto en español') busca la traducción en lib/lang/textos.php.
 * - Textos del restaurante (carta, frase, presentación, políticas): tr($texto) usa la tabla translations,
 *   que se rellena sola con un traductor automático (lib/traductor.php). Los datos de contacto no se traducen.
 * - El panel de gestión sigue en español.
 */

const LANGS = [
    'es' => ['name' => 'Español', 'locale' => 'es-ES'],
    'en' => ['name' => 'English', 'locale' => 'en-GB'],
    'pt' => ['name' => 'Português', 'locale' => 'pt-PT'],
    'gl' => ['name' => 'Galego', 'locale' => 'gl-ES'],
];
const LANG_COOKIE = 'idioma';

/** Idioma actual ('es' por defecto: API del panel, emails al restaurante…). */
function lang(?string $set = null): string
{
    static $cur = 'es';
    if ($set !== null && isset(LANGS[$set])) $cur = $set;
    return $cur;
}

/** Idiomas que el restaurante tiene activados (Ajustes → Idiomas). El español siempre. */
function langs_on(): array
{
    $on = function_exists('settings') && installed() ? (array)(settings()['langs'] ?? []) : array_keys(LANGS);
    return array_values(array_unique(array_merge(['es'], array_intersect(array_keys(LANGS), $on))));
}

/**
 * Elige el idioma de la visita: ?lang=xx (y se recuerda en una cookie de preferencia), la cookie,
 * o el idioma del navegador. Si no coincide ninguno, español.
 */
function detect_lang(): string
{
    $on = langs_on();
    $q = (string)($_GET['lang'] ?? '');
    if ($q !== '' && in_array($q, $on, true)) {
        if (!headers_sent()) {
            setcookie(LANG_COOKIE, $q, ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
        }
        return lang($q);
    }
    $c = (string)($_COOKIE[LANG_COOKIE] ?? '');
    if (in_array($c, $on, true)) return lang($c);
    foreach (explode(',', strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))) as $part) {
        $code = substr(trim(explode(';', $part)[0]), 0, 2);
        if ($code !== '' && in_array($code, $on, true)) return lang($code);
        if ($code === 'es') break;
    }
    return lang('es');
}

/** Diccionario de textos fijos: ['texto en español' => ['en' => …, 'pt' => …, 'gl' => …]] */
function i18n_dict(): array
{
    static $d = null;
    if ($d === null) {
        $d = require __DIR__ . '/lang/textos.php';
        // Textos propios del tema: tema/lang.php devuelve ['dict' => ['texto en español' => ['en' => …, 'pt' => …, 'gl' => …]], 'js' => ['texto usado desde JavaScript', …]]
        $d = array_merge($d, (array)(tema_lang()['dict'] ?? []));
    }
    return $d;
}

/** Textos y claves de JavaScript del tema (tema/lang.php), o vacío si el tema no traduce nada propio. */
function tema_lang(): array
{
    static $t = null;
    if ($t === null) {
        $f = dirname(__DIR__) . '/tema/lang.php';
        $t = is_file($f) ? (array)(require $f) : [];
    }
    return $t;
}

/** Traduce un texto fijo de la web. Admite huecos de sprintf: t('Guardamos la mesa %d minutos', 15). */
function t(string $s, ...$args): string
{
    $l = lang();
    $x = $l === 'es' ? $s : (i18n_dict()[$s][$l] ?? $s);
    return $args ? vsprintf($x, $args) : $x;
}

/**
 * Traduce mensajes con números o nombres dentro (los errores de la API): busca el texto con los
 * números cambiados por %d y vuelve a ponerlos. Si no está en el diccionario, queda en español.
 */
function t_msg(string $msg): string
{
    if (lang() === 'es') return $msg;
    $d = i18n_dict();
    if (isset($d[$msg][lang()])) return $d[$msg][lang()];
    preg_match_all('/\d+/', $msg, $m);
    $key = preg_replace('/\d+/', '%d', $msg);
    return isset($d[$key][lang()]) ? vsprintf($d[$key][lang()], $m[0]) : $msg;
}

/** Ejecuta algo en otro idioma (por ejemplo, el email o el WhatsApp de un cliente que reservó en inglés). */
function with_lang(string $l, callable $fn)
{
    $prev = lang();
    lang(isset(LANGS[$l]) ? $l : 'es');
    try { return $fn(); } finally { lang($prev); }
}

/** Dirección de la página actual en otro idioma. */
function lang_url(string $l): string
{
    $q = $_GET;
    $q['lang'] = $l;
    unset($q['activar'], $q['c'], $q['t'], $q['r']);
    $path = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    return ($path === 'index.php' ? './' : $path) . '?' . http_build_query($q);
}

/* ---------- Fechas ---------- */

const I18N_DAYS = [
    'es' => ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'],
    'en' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
    'pt' => ['segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado', 'domingo'],
    'gl' => ['luns', 'martes', 'mércores', 'xoves', 'venres', 'sábado', 'domingo'],
];
const I18N_MONTHS = [
    'es' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'],
    'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    'pt' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
    'gl' => ['xaneiro', 'febreiro', 'marzo', 'abril', 'maio', 'xuño', 'xullo', 'agosto', 'setembro', 'outubro', 'novembro', 'decembro'],
];

/** Nombre del día de la semana (1 = lunes … 7 = domingo) en el idioma actual. */
function day_name(int $n, bool $cap = false): string
{
    $d = I18N_DAYS[lang()][$n - 1] ?? '';
    return $cap ? mb_strtoupper(mb_substr($d, 0, 1)) . mb_substr($d, 1) : $d;
}

function month_name(int $n): string
{
    return I18N_MONTHS[lang()][$n - 1] ?? '';
}

/** «sábado 24 de octubre» / «Saturday 24 October» / «sábado, 24 de outubro» / «sábado 24 de outubro» */
function fmt_date_l(string $date): string
{
    $ts = strtotime($date);
    $d = day_name((int)date('N', $ts));
    $j = (int)date('j', $ts);
    $m = month_name((int)date('n', $ts));
    return match (lang()) {
        'en' => "$d $j $m",
        'pt' => "$d, $j de $m",
        default => "$d $j de $m",
    };
}

/* ---------- Textos del restaurante (traducción automática) ---------- */

function tr_hash(string $text): string
{
    return sha1(trim($text));
}

/** Traducción de un texto del restaurante en el idioma actual; si aún no está, el original. */
function tr(string $text): string
{
    static $cache = [];
    $l = lang();
    if ($l === 'es' || trim($text) === '') return $text;
    if (!isset($cache[$l])) {
        $cache[$l] = [];
        try {
            foreach (q('SELECT h, txt FROM translations WHERE lang = ?', [$l])->fetchAll() as $row) $cache[$l][$row['h']] = $row['txt'];
        } catch (Throwable $e) { /* tabla aún no creada */ }
    }
    return $cache[$l][tr_hash($text)] ?? $text;
}
