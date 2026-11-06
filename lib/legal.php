<?php
declare(strict_types=1);

/*
 * Textos legales: aviso legal (LSSI-CE), políticas de reserva, condiciones de uso, privacidad (RGPD y
 * LOPDGDD) y cookies. Se rellenan solos con los datos del panel y salen en el idioma de la visita
 * (lib/legal/es.php, en.php, pt.php y gl.php; la versión en español es la original). El tema solo decide
 * cómo se ven: recorre legal_secciones() y pinta el título y el HTML de cada una.
 * Revisados en octubre de 2026. Si cambias el contenido, sube LEGAL_VERSION en core.php.
 */

/** Normas de reserva del restaurante (cada línea del panel), traducidas. */
function legal_policies(): array
{
    $out = [];
    foreach (preg_split('/\R/', (string)settings()['policies']) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        [$t, $txt] = array_pad(explode(':', $line, 2), 2, '');
        $out[] = $txt === '' ? tr($t) : tr(trim($t)) . ': ' . tr(trim($txt));
    }
    return $out;
}

/** Secciones legales: [['id' => 'aviso', 'titulo' => 'Aviso legal', 'html' => '…'], …] en el idioma actual. */
function legal_secciones(): array
{
    $s = settings();
    $titular = $s['owner_name'] ?: $s['name'];
    $contacto = $s['email'] ?: $s['phone'];
    $google_mail = mail_via_google();
    $analytics = $s['ga_id'] !== '' || $s['gtm_id'] !== '';
    $mapa = defined('TEMA_GOOGLE_MAPS') && TEMA_GOOGLE_MAPS;
    ob_start();
    require __DIR__ . '/legal/' . lang() . '.php';
    $html = ob_get_clean();
    // Cada apartado empieza con <h2 id="aviso">Título</h2>
    $parts = preg_split('#<h2 id="([a-z]+)">(.*?)</h2>#s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = [];
    for ($i = 1; $i + 2 < count($parts) + 1; $i += 3) {
        $out[] = ['id' => $parts[$i], 'titulo' => trim($parts[$i + 1]), 'html' => trim($parts[$i + 2] ?? '')];
    }
    return $out;
}

/** Aviso que el tema puede mostrar en la página legal cuando el texto no está en español. */
function legal_aviso_traduccion(): string
{
    return lang() === 'es' ? '' : t('Traducción de los textos legales originales en español. En caso de duda, prevalece la versión en español.');
}
