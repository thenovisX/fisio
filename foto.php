<?php
declare(strict_types=1);
/*
 * Fotos de ejemplo de Unsplash servidas desde este servidor. La primera vez se descargan y se guardan
 * en uploads/cache; después se sirven como archivos normales. Así el navegador del visitante no se
 * conecta a Unsplash (su IP no sale a un tercero) y no hace falta mencionarlo como destinatario.
 */
require __DIR__ . '/lib/bootstrap.php';

$key = (string)($_GET['k'] ?? '');
$w = (int)($_GET['w'] ?? 0);
$src = tema_fotos()[$key]['src'] ?? '';
if ($src === '' || !in_array($w, PHOTO_WIDTHS, true) || !is_unsplash($src)) {
    http_response_code(404);
    exit;
}
$file = photo_cache_file($key, $w);
if (!is_file($file)) {
    $url = $src . '?auto=format&fm=jpg&fit=crop&q=70&w=' . $w;
    $data = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'restaurante-web']);
        $data = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $data = false;
        curl_close($ch);
    } else {
        $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 20]]));
    }
    $info = $data ? @getimagesizefromstring($data) : false;
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_WEBP, IMAGETYPE_PNG], true)) {
        // Si el servidor no puede descargarla, que al menos se vea (en ese caso sí la sirve Unsplash)
        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit;
    }
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file . '.tmp', $data);
    rename($file . '.tmp', $file);
}
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . filesize($file));
readfile($file);
