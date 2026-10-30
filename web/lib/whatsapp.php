<?php
declare(strict_types=1);

/*
 * Envío automático de WhatsApp con la API oficial de Meta (WhatsApp Business Cloud API).
 *
 * Cuando el restaurante acepta una reserva (pasa a «esperando»), el servidor manda al cliente el mensaje con el
 * enlace de confirmación sin abrir WhatsApp en el móvil. Si la API no está configurada o Meta da error, la app
 * sigue ofreciendo el enlace wa.me de siempre para mandarlo a mano.
 *
 * Meta solo deja escribir primero a un cliente con una plantilla aprobada (categoría «Utilidad»). La plantilla
 * tiene que llevar 5 variables en el cuerpo, en este orden:
 *   {{1}} nombre del cliente · {{2}} día · {{3}} hora · {{4}} personas · {{5}} enlace para confirmar
 * Sin plantilla se manda texto libre, que Meta solo entrega si el cliente ha escrito al restaurante en las últimas 24 h.
 *
 * Los recordatorios (24 h y 12 h antes de una reserva confirmada) usan otra plantilla con 4 variables:
 *   {{1}} nombre del cliente · {{2}} día · {{3}} hora · {{4}} personas
 */

/** Error de la API de Meta: el código es el de Meta (190, 131047…). */
class WaError extends RuntimeException {}

const WA_GRAPH = 'https://graph.facebook.com/v23.0/';

/** Código de idioma de Meta para cada idioma de la web (el gallego no existe en Meta: va con la plantilla por defecto). */
const WA_LANGS = ['es' => 'es', 'en' => 'en', 'pt' => 'pt_PT'];

/** Número en formato internacional sin «+» ni espacios (los de 9 cifras se entienden españoles). */
function wa_number(string $phone): string
{
    $n = preg_replace('/\D/', '', $phone);
    if (strlen($n) === 9) $n = '34' . $n;
    return $n;
}

/** ¿Están puestos los datos de Meta? (Lo que se envía solo depende además de su interruptor.) */
function wa_creds_ready(): bool
{
    $s = settings();
    return $s['wa_phone_id'] !== '' && $s['wa_token'] !== '' && function_exists('curl_init');
}

/** ¿Sale solo el WhatsApp de confirmación al aceptar una reserva? */
function wa_api_ready(): bool
{
    return settings()['wa_on'] && wa_creds_ready();
}

/** ¿Salen solos los recordatorios antes de la reserva? */
function wa_remind_ready(): bool
{
    return settings()['wa_remind_on'] && wa_creds_ready();
}

/**
 * Manda al cliente el WhatsApp con el enlace para confirmar la reserva.
 * Devuelve '' si Meta lo ha aceptado o el motivo del error (que también queda guardado para verlo en Ajustes).
 */
function wa_send_confirmation(array $row): string
{
    $r = res_out($row);
    if (!wa_api_ready()) return 'El envío automático de WhatsApp no está activado';
    $url = confirm_url($row);
    return wa_deliver($r, settings()['wa_template'],
        fn() => [wa_first_name($r), fmt_date_l($r['date']), $r['time'], wa_people($r), $url . (lang() !== 'es' ? '&lang=' . lang() : '')],
        fn() => whatsapp_text($r, $url));
}

/**
 * Envía a la reserva $r la plantilla $template con las variables que da $vars() (se llama ya en el idioma del
 * mensaje), o el texto libre de $text() si no hay plantilla. Devuelve '' o el motivo del error, que queda guardado.
 */
function wa_deliver(array $r, string $template, callable $vars, callable $text): string
{
    if ($r['phone'] === '') return 'La reserva no tiene teléfono';
    $to = wa_number($r['phone']);
    $s = settings();
    try {
        if ($template === '') {
            wa_post(['to' => $to, 'type' => 'text', 'text' => ['preview_url' => false, 'body' => with_lang($r['lang'] ?: 'es', $text)]]);
        } else {
            // Primero en el idioma del cliente; si la plantilla no está traducida a ese idioma, en el de por defecto
            $def = $s['wa_template_lang'] ?: 'es';
            $mine = WA_LANGS[$r['lang']] ?? '';
            $tries = $mine !== '' && $mine !== $def ? [[$r['lang'], $mine], ['es', $def]] : [['es', $def]];
            foreach ($tries as $i => [$l, $code]) {
                try {
                    wa_post(wa_template_message($to, $template, $code, with_lang($l, $vars)));
                    break;
                } catch (WaError $e) {
                    if ($e->getCode() !== 132001 || $i === count($tries) - 1) throw $e; // 132001: no existe en ese idioma
                }
            }
        }
        q("DELETE FROM settings WHERE k = '_wa_error'");
        return '';
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        q("DELETE FROM settings WHERE k = '_wa_error'");
        q("INSERT INTO settings (k, v) VALUES ('_wa_error', ?)", [date('j/n H:i') . ' · ' . $msg]);
        error_log('[restaurante] WhatsApp: ' . $msg);
        return $msg;
    }
}

function wa_first_name(array $r): string
{
    return explode(' ', trim($r['name']))[0] ?: $r['name'];
}

function wa_people(array $r): string
{
    return $r['people'] . ' ' . ($r['people'] === 1 ? t('persona') : t('personas'));
}

/** Mensaje de plantilla con sus variables de cuerpo. */
function wa_template_message(string $to, string $template, string $code, array $vars): array
{
    return [
        'to' => $to,
        'type' => 'template',
        'template' => [
            'name' => $template,
            'language' => ['code' => $code],
            'components' => [[
                'type' => 'body',
                'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => (string)$v], $vars),
            ]],
        ],
    ];
}

/* ---------- Recordatorios antes de la reserva ---------- */

/** Recordatorios: horas antes de la reserva => bit en la columna reminders (para no mandarlo dos veces). */
const WA_REMINDERS = [24 => 1, 12 => 2];

/**
 * Manda los recordatorios que toquen a las reservas confirmadas. Se ejecuta sola con las visitas a la web y a la
 * app (como mucho cada 5 minutos) y también desde cron.php si el hosting tiene tareas programadas.
 * Cada aviso se marca en la base de datos ANTES de enviarlo, así dos visitas a la vez no lo mandan dos veces.
 */
function wa_reminders_run(bool $force = false): int
{
    if (!wa_remind_ready()) return 0;
    $last = (int)(q("SELECT v FROM settings WHERE k = '_wa_remind'")->fetchColumn() ?: 0);
    if (!$force && $last > time() - 300) return 0;
    q("DELETE FROM settings WHERE k = '_wa_remind'");
    q("INSERT INTO settings (k, v) VALUES ('_wa_remind', ?)", [(string)time()]);

    $now = time();
    $all = array_sum(WA_REMINDERS);
    $rows = q("SELECT * FROM reservations WHERE status = 'confirmada' AND deleted = 0 AND phone <> '' AND reminders < ? AND rdate BETWEEN ? AND ?",
        [$all, date('Y-m-d', $now), date('Y-m-d', $now + 2 * 86400)])->fetchAll();
    $sent = $tried = 0;
    foreach ($rows as $row) {
        $start = strtotime($row['rdate'] . ' ' . $row['rtime']);
        if ($start <= $now) continue;
        // El aviso más cercano que ya toca; los anteriores se dan por pasados
        $due = 0;
        foreach (WA_REMINDERS as $h => $bit) if ($now >= $start - $h * 3600) $due = $h;
        if (!$due) continue;
        $mask = 0;
        foreach (WA_REMINDERS as $h => $bit) if ($h >= $due) $mask |= $bit;
        $bit = WA_REMINDERS[$due];
        if (((int)$row['reminders'] & $bit) !== 0) {
            q('UPDATE reservations SET reminders = ? WHERE id = ?', [(int)$row['reminders'] | $mask, $row['id']]);
            continue;
        }
        $claimed = q('UPDATE reservations SET reminders = ? WHERE id = ? AND reminders = ?', [(int)$row['reminders'] | $mask, $row['id'], (int)$row['reminders']])->rowCount();
        if (!$claimed) continue; // otra visita se ha adelantado
        // Si se confirmó (o se apuntó) cuando ya tocaba este aviso, no hace falta recordárselo
        if (strtotime($row['updated_at']) >= $start - $due * 3600) continue;
        if (wa_send_reminder($row) === '') $sent++;
        if (++$tried >= 10) break; // el resto, en la siguiente vuelta: así una visita nunca espera demasiado
    }
    return $sent;
}

/** Recordatorio con el día, la hora y las personas. */
function wa_send_reminder(array $row): string
{
    $r = res_out($row);
    return wa_deliver($r, settings()['wa_remind_template'],
        fn() => [wa_first_name($r), fmt_date_l($r['date']), $r['time'], wa_people($r)],
        fn() => t('Hola %s, te recordamos tu reserva en %s el %s a las %s (%s). ¡Te esperamos!', wa_first_name($r), settings()['name'], fmt_date_l($r['date']), $r['time'], wa_people($r)));
}

/** Clave para llamar a cron.php desde una tarea programada del hosting. */
function cron_key(): string
{
    return substr(hash_hmac('sha256', 'cron', (string)(cfg()['secret'] ?? '')), 0, 32);
}

/** Mensaje de prueba al número que se indique, con datos de ejemplo. */
function wa_test(string $phone): string
{
    if (!wa_creds_ready()) throw new ValidationError('Pon el identificador del número y el token de Meta, y guarda');
    $to = wa_number($phone);
    if (strlen($to) < 10) throw new ValidationError('Escribe un número de móvil para la prueba');
    $s = settings();
    $r = ['name' => 'Prueba', 'date' => date('Y-m-d', strtotime('+1 day')), 'time' => '21:00', 'people' => 2, 'lang' => 'es'];
    $url = base_url() . '/reservar.php';
    try {
        wa_post($s['wa_template'] === ''
            ? ['to' => $to, 'type' => 'text', 'text' => ['body' => "Prueba de WhatsApp automático de {$s['name']}. ¡Funciona!"]]
            : wa_template_message($to, $s['wa_template'], $s['wa_template_lang'] ?: 'es', with_lang('es', fn() => [wa_first_name($r), fmt_date_l($r['date']), $r['time'], wa_people($r), $url])));
        if ($s['wa_remind_template'] !== '') {
            wa_post(wa_template_message($to, $s['wa_remind_template'], $s['wa_template_lang'] ?: 'es', with_lang('es', fn() => [wa_first_name($r), fmt_date_l($r['date']), $r['time'], wa_people($r)])));
        }
    } catch (WaError $e) {
        throw new ValidationError($e->getMessage());
    }
    q("DELETE FROM settings WHERE k = '_wa_error'");
    return $to;
}

/** Llamada a la Graph API de Meta. Lanza WaError con un mensaje entendible y el código de error de Meta. */
function wa_post(array $msg): array
{
    $s = settings();
    $token = dec($s['wa_token']);
    if ($token === '') throw new WaError('Falta el token de acceso de Meta');
    $ch = curl_init(WA_GRAPH . rawurlencode($s['wa_phone_id']) . '/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['messaging_product' => 'whatsapp', 'recipient_type' => 'individual'] + $msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) throw new WaError('No se puede conectar con WhatsApp (' . $err . ')');
    $res = json_decode((string)$body, true) ?: [];
    if ($http >= 200 && $http < 300 && !empty($res['messages'][0]['id'])) return $res;

    $e = $res['error'] ?? [];
    $code = (int)($e['code'] ?? 0);
    $known = [
        190    => 'El token de acceso de Meta no es válido o ha caducado. Crea uno permanente (usuario del sistema) y pégalo de nuevo.',
        131030 => 'Ese número no está en la lista de prueba de Meta. Añádelo o pasa la app de Meta a producción.',
        131047 => 'Han pasado más de 24 h desde que el cliente te escribió: hace falta una plantilla aprobada.',
        131026 => 'Ese teléfono no tiene WhatsApp o no puede recibir el mensaje.',
        132000 => 'La plantilla no tiene 5 variables: nombre, día, hora, personas y enlace.',
        132001 => 'La plantilla no existe con ese nombre e idioma, o aún no está aprobada.',
        133010 => 'El número del restaurante no está registrado en la API de WhatsApp.',
    ];
    $detail = (string)($e['error_data']['details'] ?? $e['message'] ?? ('HTTP ' . $http));
    throw new WaError(($known[$code] ?? 'WhatsApp respondió: ' . $detail) . ($code ? " (código $code)" : ''), $code);
}
