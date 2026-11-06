<?php
declare(strict_types=1);
/*
 * Tareas periódicas para una tarea programada del hosting (Hostinger → Avanzado → Cron Jobs), cada 5–15 minutos:
 *   php /ruta/a/public_html/cron.php
 * o, si el hosting solo deja abrir una dirección, la que sale en la app (Ajustes → WhatsApp automático):
 *   https://tu-dominio/cron.php?key=…
 * No es imprescindible: sin cron, los recordatorios salen igual con las visitas a la web y el uso de la app.
 */
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
if (PHP_SAPI !== 'cli' && !hash_equals(cron_key(), (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Clave incorrecta']));
}
if (!installed()) {
    http_response_code(503);
    exit(json_encode(['ok' => false, 'error' => 'La web aún no está instalada']));
}
$purged = purge_old_reservations(true);
$sent = wa_reminders_run(true);
echo json_encode(['ok' => true, 'reminders' => $sent, 'purged' => $purged]);
