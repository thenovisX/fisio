<?php
declare(strict_types=1);

/*
 * Traducción automática de los textos del restaurante (carta, frase corta, presentación, políticas…).
 * Cada texto se traduce una sola vez y se guarda en la tabla translations; si el texto cambia, se traduce
 * de nuevo. Las traducciones corregidas a mano en el panel no se vuelven a pisar.
 *
 * Traductores:
 * - MyMemory (por defecto): gratis y sin cuenta, unos 50.000 caracteres al día usando el email del restaurante.
 * - Google Cloud Translation: con clave de API; 500.000 caracteres gratis al mes y mejor calidad.
 * Los datos de contacto (dirección, teléfono, emails, nombre del restaurante) nunca se traducen.
 */

const TR_TARGETS = ['en' => ['mymemory' => 'en-GB', 'google' => 'en'], 'pt' => ['mymemory' => 'pt-PT', 'google' => 'pt-PT'], 'gl' => ['mymemory' => 'gl-ES', 'google' => 'gl']];

/** Todos los textos del restaurante que se ven en la web y hay que traducir: [texto => dónde sale] */
function translatable_texts(): array
{
    $s = settings();
    $out = [];
    $add = function (string $t, string $where) use (&$out) {
        $t = trim($t);
        if ($t !== '' && mb_strlen($t) <= 2000 && !isset($out[$t])) $out[$t] = $where;
    };
    foreach (q('SELECT name FROM categories')->fetchAll() as $c) $add($c['name'], 'Sección de la carta');
    foreach (q('SELECT name, description FROM dishes')->fetchAll() as $d) {
        $add($d['name'], 'Plato');
        $add($d['description'], 'Descripción de «' . $d['name'] . '»');
    }
    foreach (menus_all() as $dm) {
        if (!$dm['on']) continue;
        $add($dm['title'], 'Nombre de menú');
        $add($dm['note'], 'Nota de «' . $dm['title'] . '»');
        foreach ($dm['courses'] as $c) {
            $add($c['name'], 'Apartado de «' . $dm['title'] . '»');
            foreach ($c['dishes'] as $d) {
                $add($d['name'], 'Plato de «' . $dm['title'] . '»');
                $add($d['desc'], 'Descripción de «' . $d['name'] . '» (' . $dm['title'] . ')');
            }
        }
    }
    $add($s['tagline'], 'Frase corta');
    $add($s['description'], 'Presentación');
    $add($s['footer_title'], 'Título del pie');
    foreach (array_filter(array_map('trim', explode(',', $s['ribbon']))) as $r) $add($r, 'Frase destacada');
    foreach (preg_split('/,| y /u', $s['tagline']) as $r) $add($r, 'Frase destacada');
    foreach (preg_split('/\R/', (string)$s['policies']) as $line) {
        [$t, $txt] = array_pad(explode(':', trim($line), 2), 2, '');
        $add($t, 'Política (título)');
        $add($txt, 'Política');
    }
    return $out;
}

/** Cuántos textos faltan por traducir en cada idioma activado. */
function translation_status(): array
{
    $texts = array_keys(translatable_texts());
    $hashes = array_map('tr_hash', $texts);
    $out = ['total' => count($texts), 'langs' => [], 'error' => (string)(q("SELECT v FROM settings WHERE k = '_tr_error'")->fetchColumn() ?: '')];
    foreach (array_diff(langs_on(), ['es']) as $l) {
        $have = array_flip(q('SELECT h FROM translations WHERE lang = ?', [$l])->fetchAll(PDO::FETCH_COLUMN));
        $out['langs'][$l] = count(array_filter($hashes, fn($h) => isset($have[$h])));
    }
    return $out;
}

/**
 * Traduce lo que falte durante como mucho $seconds segundos. Devuelve cuántos textos ha traducido.
 * Si el traductor falla (sin conexión o sin cupo), se espera 15 minutos antes de volver a intentarlo
 * salvo que se fuerce desde el panel.
 */
function translate_pending(float $seconds = 6, bool $force = false): int
{
    $start = microtime(true);
    $s = settings();
    $last = (int)(q("SELECT v FROM settings WHERE k = '_tr_wait'")->fetchColumn() ?: 0);
    if (!$force && $last > time()) return 0;
    $texts = translatable_texts();
    $done = 0;
    // Borrar traducciones de textos que ya no existen (platos borrados o cambiados)
    $valid = array_flip(array_map('tr_hash', array_keys($texts)));
    foreach (q('SELECT DISTINCT h FROM translations')->fetchAll(PDO::FETCH_COLUMN) as $h) {
        if (!isset($valid[$h])) q('DELETE FROM translations WHERE h = ?', [$h]);
    }
    foreach (array_diff(langs_on(), ['es']) as $l) {
        $have = array_flip(q('SELECT h FROM translations WHERE lang = ?', [$l])->fetchAll(PDO::FETCH_COLUMN));
        $todo = array_values(array_filter(array_keys($texts), fn($t) => !isset($have[tr_hash($t)])));
        // Google traduce de 50 en 50; MyMemory, de uno en uno
        $chunks = $s['tr_provider'] === 'google' ? array_chunk($todo, 50) : array_map(fn($t) => [$t], $todo);
        foreach ($chunks as $chunk) {
            if (microtime(true) - $start > $seconds) return $done;
            try {
                $res = translate_batch($chunk, $l);
            } catch (Throwable $e) {
                tr_set('_tr_error', date('j/n H:i') . ' · ' . $e->getMessage());
                tr_set('_tr_wait', (string)(time() + 900));
                return $done;
            }
            foreach ($chunk as $i => $src) {
                $txt = trim((string)($res[$i] ?? ''));
                if ($txt === '') continue;
                q('DELETE FROM translations WHERE h = ? AND lang = ?', [tr_hash($src), $l]);
                q('INSERT INTO translations (h, lang, src, txt, manual, at) VALUES (?, ?, ?, ?, 0, ?)', [tr_hash($src), $l, $src, $txt, now()]);
                $done++;
            }
        }
    }
    q("DELETE FROM settings WHERE k IN ('_tr_error', '_tr_wait')");
    return $done;
}

function tr_set(string $k, string $v): void
{
    q('DELETE FROM settings WHERE k = ?', [$k]);
    q('INSERT INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
}

/** Traduce una lista de textos del español a $l. Devuelve las traducciones en el mismo orden. */
function translate_batch(array $texts, string $l): array
{
    $s = settings();
    if ($s['tr_provider'] === 'google' && $s['tr_google_key'] !== '') {
        $body = http_build_query(['source' => 'es', 'target' => TR_TARGETS[$l]['google'], 'format' => 'text', 'key' => dec($s['tr_google_key'])])
            . '&' . implode('&', array_map(fn($t) => 'q=' . rawurlencode($t), $texts));
        $j = tr_http('https://translation.googleapis.com/language/translate/v2', $body);
        if (isset($j['error'])) throw new RuntimeException('Google: ' . ($j['error']['message'] ?? 'error'));
        return array_map(fn($x) => html_entity_decode($x['translatedText'] ?? '', ENT_QUOTES, 'UTF-8'), $j['data']['translations'] ?? []);
    }
    // MyMemory: hasta 500 bytes por consulta, así que los textos largos se parten por frases
    $out = [];
    foreach ($texts as $t) {
        $parts = [];
        foreach (tr_split($t, 450) as $piece) {
            $url = (cfg()['tr_url'] ?? 'https://api.mymemory.translated.net/get') . '?' . http_build_query(['q' => $piece, 'langpair' => 'es|' . TR_TARGETS[$l]['mymemory'], 'de' => $s['notify_email'] ?: $s['email']]);
            $j = tr_http($url);
            $status = (int)($j['responseStatus'] ?? 0);
            $txt = (string)($j['responseData']['translatedText'] ?? '');
            if ($status !== 200 || $txt === '' || stripos($txt, 'MYMEMORY WARNING') !== false) {
                throw new RuntimeException('MyMemory: ' . ($status === 429 || stripos($txt, 'WARNING') !== false ? 'se ha acabado el cupo gratuito de hoy; se seguirá traduciendo mañana' : ($j['responseDetails'] ?? 'no responde')));
            }
            $parts[] = html_entity_decode($txt, ENT_QUOTES, 'UTF-8');
        }
        $out[] = implode(' ', $parts);
    }
    return $out;
}

/** Parte un texto en trozos de como mucho $max bytes sin cortar frases (si se puede). */
function tr_split(string $t, int $max): array
{
    if (strlen($t) <= $max) return [$t];
    $out = [];
    $cur = '';
    foreach (preg_split('/(?<=[.!?;:])\s+/u', $t) as $sentence) {
        if (strlen($cur . ' ' . $sentence) > $max && $cur !== '') { $out[] = $cur; $cur = ''; }
        $cur = trim($cur . ' ' . $sentence);
        while (strlen($cur) > $max) { $cut = mb_strcut($cur, 0, $max); $out[] = $cut; $cur = substr($cur, strlen($cut)); }
    }
    if ($cur !== '') $out[] = $cur;
    return $out;
}

function tr_http(string $url, ?string $post = null): array
{
    $ctx = stream_context_create(['http' => [
        'method' => $post === null ? 'GET' : 'POST',
        'header' => "User-Agent: restaurante-web\r\n" . ($post === null ? '' : "Content-Type: application/x-www-form-urlencoded\r\n"),
        'content' => $post ?? '',
        'timeout' => 6,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) throw new RuntimeException('no se puede conectar con el traductor');
    $j = json_decode($raw, true);
    if (!is_array($j)) throw new RuntimeException('el traductor no responde bien');
    return $j;
}

/** Tras guardar un plato, una sección o los ajustes: traduce lo nuevo enseguida (como mucho 6 segundos). */
function tr_after_save(): void
{
    try { translate_pending(6); } catch (Throwable $e) { /* se reintenta más tarde */ }
}

/** Para el panel: todos los textos con sus traducciones, para revisarlas o corregirlas a mano. */
function translations_list(): array
{
    $rows = [];
    foreach (q('SELECT h, lang, txt, manual FROM translations')->fetchAll() as $r) $rows[$r['h']][$r['lang']] = ['txt' => $r['txt'], 'manual' => (bool)$r['manual']];
    $out = [];
    foreach (translatable_texts() as $text => $where) {
        $h = tr_hash($text);
        $out[] = ['h' => $h, 'src' => $text, 'where' => $where, 'tr' => $rows[$h] ?? (object)[]];
    }
    return $out;
}

/** Guarda una traducción corregida a mano (no se vuelve a traducir automáticamente). */
function translation_save(array $in): void
{
    $h = (string)($in['h'] ?? '');
    $l = (string)($in['lang'] ?? '');
    $txt = trim((string)($in['txt'] ?? ''));
    if (!isset(TR_TARGETS[$l])) throw new ValidationError('Idioma no válido');
    $src = null;
    foreach (array_keys(translatable_texts()) as $t) if (tr_hash($t) === $h) $src = $t;
    if ($src === null) throw new ValidationError('Ese texto ya no está en la web');
    q('DELETE FROM translations WHERE h = ? AND lang = ?', [$h, $l]);
    if ($txt !== '') q('INSERT INTO translations (h, lang, src, txt, manual, at) VALUES (?, ?, ?, ?, 1, ?)', [$h, $l, $src, mb_substr($txt, 0, 4000), now()]);
}
