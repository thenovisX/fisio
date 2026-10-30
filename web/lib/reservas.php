<?php
declare(strict_types=1);

/*
 * Estados de una reserva:
 *  pendiente  → petición nueva desde la web; el restaurante aún no la ha revisado
 *  esperando  → el restaurante la aceptó y se envió el WhatsApp (solo, con la API de Meta, o a mano); falta que el cliente toque el enlace
 *  confirmada → el cliente tocó el enlace (o el restaurante la confirmó a mano)
 *  rechazada / cancelada
 */
const RES_STATUSES = ['pendiente', 'esperando', 'confirmada', 'rechazada', 'cancelada'];
const RES_ACTIVE = ['pendiente', 'esperando', 'confirmada']; // ocupan sitio

function fmt_date_es(string $date): string
{
    $ts = strtotime($date);
    $dias = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int)date('N', $ts)] . ' ' . (int)date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)];
}

/**
 * Horas disponibles de un día con las plazas libres en cada una.
 * $ignoreRules = true lo usa el panel: deja apuntar reservas aunque esté lleno o fuera de plazo.
 */
function day_slots(string $date, bool $ignoreRules = false, int $excludeId = 0): array
{
    if (!is_date($date)) throw new ValidationError('Fecha no válida');
    $s = settings();
    $res = ['date' => $date, 'open' => false, 'reason' => '', 'slots' => []];

    if (!$ignoreRules) {
        if ($date < today()) return ['reason' => t('Esa fecha ya ha pasado')] + $res;
        $limit = date('Y-m-d', strtotime('+' . (int)$s['days_ahead'] . ' days'));
        if ($date > $limit) return ['reason' => t('Solo se puede reservar con %d días de antelación como máximo', (int)$s['days_ahead'])] + $res;
    }
    if (in_array($date, $s['closed_dates'], true)) return ['reason' => t('Ese día el restaurante está cerrado')] + $res;

    $ranges = $s['hours'][date('N', strtotime($date))] ?? [];
    if (!$ranges) return ['reason' => t('Ese día el restaurante cierra')] + $res;
    // Con horario de cocina, solo se puede reservar mientras la cocina está abierta
    if ($s['kitchen_on']) {
        $ranges = $s['kitchen_hours'][date('N', strtotime($date))] ?? [];
        if (!$ranges) return ['reason' => t('Ese día la cocina no abre')] + $res;
    }

    // Reservas del día que ocupan sitio: [inicio en segundos, personas]
    $ph = implode(',', array_fill(0, count(RES_ACTIVE), '?'));
    $rows = q("SELECT rtime, SUM(people) AS p FROM reservations WHERE rdate = ? AND id <> ? AND status IN ($ph) GROUP BY rtime", array_merge([$date, $excludeId], RES_ACTIVE))->fetchAll();
    $booked = array_map(fn($r) => [strtotime("$date {$r['rtime']}"), (int)$r['p']], $rows);
    // Tiempo de mesa: una reserva ocupa su sitio desde su hora hasta que pasa este tiempo (0 = solo su hora)
    $dur = max(0, (int)$s['table_minutes']) * 60;
    $occupied = function (int $x) use ($booked, $dur): int {
        $n = 0;
        foreach ($booked as [$start, $p]) if ($dur > 0 ? ($start <= $x && $x < $start + $dur) : $start === $x) $n += $p;
        return $n;
    };
    // Plazas libres a la hora $t: lo que cabe sin pasar del aforo en ningún momento de la comida
    $free = function (int $t) use ($booked, $dur, $occupied, $s): int {
        $peak = $occupied($t);
        if ($dur > 0) foreach ($booked as [$start]) if ($start > $t && $start < $t + $dur) $peak = max($peak, $occupied($start));
        return max(0, (int)$s['capacity'] - $peak);
    };

    $step = max(5, (int)$s['slot_minutes']) * 60;
    $earliest = time() + (int)$s['min_notice'] * 60;
    foreach ($ranges as [$a, $b]) {
        $t = strtotime("$date $a");
        $end = strtotime("$date $b") - (int)$s['last_before'] * 60;
        if ($end < $t) $end = $t;
        for (; $t <= $end; $t += $step) {
            if (!$ignoreRules && $t < $earliest) continue;
            $hm = date('H:i', $t);
            $res['slots'][] = ['time' => $hm, 'left' => $free($t)];
        }
    }
    $res['open'] = (bool)array_filter($res['slots'], fn($x) => $x['left'] > 0);
    if (!$res['open']) $res['reason'] = $res['slots'] ? t('No quedan mesas libres ese día') : t('Ya no se puede reservar para hoy');
    return $res;
}

function reservation_fields(array $in, bool $fromPanel): array
{
    $d = [
        'name'   => str_in($in, 'name', 80, true, 'nombre'),
        'phone'  => str_in($in, 'phone', 30, true, 'teléfono'),
        'email'  => str_in($in, 'email', 120),
        'rdate'  => str_in($in, 'date', 10, true, 'fecha'),
        'rtime'  => str_in($in, 'time', 5, true, 'hora'),
        'people' => (int)($in['people'] ?? 0),
        'notes'  => mb_substr(trim((string)($in['notes'] ?? '')), 0, 500),
    ];
    if (mb_strlen($d['name']) < 2) throw new ValidationError('Escribe un nombre');
    if (strlen(preg_replace('/\D/', '', $d['phone'])) < 6) throw new ValidationError('El teléfono no parece correcto');
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    if (!is_date($d['rdate'])) throw new ValidationError('Fecha no válida');
    if (!is_time($d['rtime'])) throw new ValidationError('Hora no válida');
    $max = $fromPanel ? 200 : (int)settings()['max_people'];
    if ($d['people'] < 1 || $d['people'] > $max) {
        throw new ValidationError($fromPanel ? 'Número de personas no válido' : "Para grupos de más de $max personas, llámanos por teléfono");
    }
    return $d;
}

function create_public_reservation(array $in): array
{
    if (!empty($in['website'])) throw new ValidationError('No se pudo enviar'); // trampa anti-bots
    if (empty($in['privacy'])) throw new ValidationError('Tienes que aceptar las políticas de reserva y de privacidad');

    $customer = current_customer();
    if ($customer) {
        // Con cuenta, los datos de contacto salen de la cuenta si no se escriben otros
        foreach (['name', 'email', 'phone'] as $k) if (trim((string)($in[$k] ?? '')) === '') $in[$k] = $customer[$k];
    }
    $d = reservation_fields($in, false);
    $ip = client_ip();
    reservation_spam_guard($ip, $d, $customer);

    // Dos peticiones a la vez para la última mesa: la segunda espera a que termine la primera y ya ve
    // ocupadas sus plazas (sin el bloqueo, las dos verían sitio y se pasaría del aforo).
    $pdo = db();
    reservation_lock();
    $slots = day_slots($d['rdate']);
    $slot = null;
    foreach ($slots['slots'] as $x) if ($x['time'] === $d['rtime']) $slot = $x;
    if (!$slot) {
        $pdo->rollBack();
        throw new ValidationError($slots['reason'] ?: t('Esa hora no está disponible'));
    }
    if ($slot['left'] < $d['people']) {
        $pdo->rollBack();
        throw new ValidationError($slot['left'] > 0 ? t('A esa hora solo quedan %d plazas', $slot['left']) : t('Esa hora ya está completa, prueba otra'));
    }

    $s = settings();
    $status = 'pendiente';
    $token = bin2hex(random_bytes(16));
    // Si no ha entrado pero el email es de una cuenta ya activada, la reserva aparece en esa cuenta
    $cid = $customer ? (int)$customer['id'] : 0;
    if (!$cid && $d['email'] !== '') {
        $cid = (int)(q('SELECT id FROM customers WHERE email_idx = ? AND verified_at IS NOT NULL', [email_idx($d['email'])])->fetchColumn() ?: 0);
    }
    q('INSERT INTO reservations (name, phone, email, email_idx, rdate, rtime, people, notes, status, source, ip, created_at, updated_at, customer_id, verify_token, seq, lang)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        enc($d['name']), enc($d['phone']), enc($d['email']), email_idx($d['email']), $d['rdate'], $d['rtime'], $d['people'], enc($d['notes']),
        $status, 'web', $ip, now(), now(), $cid, enc($token), next_seq(), lang(),
    ]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    audit('reserva', $cid, "Reserva #$id: acepta políticas de reserva y privacidad, versión " . LEGAL_VERSION);

    $when = fmt_date_es($d['rdate']) . ' a las ' . $d['rtime'];
    restaurant_mail(
        "Petición de reserva: {$d['name']}, {$d['people']} pers., $when",
        "Nueva petición de reserva desde la web\n\nNombre: {$d['name']}\nDía: $when\nPersonas: {$d['people']}\n\n"
        . "Confírmala desde la pestaña Peticiones de la app: allí están el teléfono y las notas, y se abrirá WhatsApp con el mensaje para el cliente.\n"
        . "(Por privacidad, el teléfono y el email del cliente no van en este aviso.)"
    );

    return [
        'id' => $id,
        'status' => $status,
        'people' => $d['people'],
        'account' => (bool)$customer,
        'linked' => $cid > 0,
        'email' => $d['email'] !== '',
        'when' => fmt_date_l($d['rdate']) . ' · ' . $d['rtime'],
        'message' => t('Hemos recibido tu petición para el %s a las %s (%s).', fmt_date_l($d['rdate']), $d['rtime'], $d['people'] . ' ' . ($d['people'] === 1 ? t('persona') : t('personas')))
            . ' ' . t('El restaurante la revisará y te enviará un WhatsApp al %s con un enlace para confirmarla.', $d['phone']),
    ];
}

/**
 * Abre una transacción y coge el «candado» de las reservas: una fila de settings que se modifica al empezar.
 * En MySQL (InnoDB) bloquea esa fila hasta el commit; en SQLite la escritura coge el bloqueo de escritura de
 * la base de datos. Así, comprobar el aforo y guardar la reserva es una operación atómica.
 */
function reservation_lock(): void
{
    if (!q("SELECT 1 FROM settings WHERE k = '_res_lock'")->fetchColumn()) {
        try { q("INSERT INTO settings (k, v) VALUES ('_res_lock', '0')"); } catch (Throwable $e) { /* la creó otra petición */ }
    }
    db()->beginTransaction();
    q("UPDATE settings SET v = ? WHERE k = '_res_lock'", [(string)time()]);
}

/**
 * Límites contra reservas falsas que bloquean mesas (las peticiones pendientes ocupan sitio):
 * 5 por hora y 10 al día desde la misma IP, y como mucho 3 reservas activas a la vez por email o cuenta.
 */
const RES_MAX_ACTIVE = 3;
function reservation_spam_guard(string $ip, array $d, ?array $customer): void
{
    $since = fn(int $s) => date('Y-m-d H:i:s', time() - $s);
    $msg = 'Has hecho demasiadas reservas seguidas. Llámanos por teléfono.';
    if ((int)q('SELECT COUNT(*) FROM reservations WHERE ip = ? AND created_at > ?', [$ip, $since(3600)])->fetchColumn() >= 5) throw new ValidationError($msg);
    if ((int)q('SELECT COUNT(*) FROM reservations WHERE ip = ? AND created_at > ?', [$ip, $since(86400)])->fetchColumn() >= 10) throw new ValidationError($msg);
    $ph = implode(',', array_fill(0, count(RES_ACTIVE), '?'));
    $who = [];
    if ($d['email'] !== '') $who[] = ['email_idx = ?', email_idx($d['email'])];
    if ($customer) $who[] = ['customer_id = ?', (int)$customer['id']];
    foreach ($who as [$cond, $val]) {
        $n = (int)q("SELECT COUNT(*) FROM reservations WHERE $cond AND deleted = 0 AND rdate >= ? AND status IN ($ph)", array_merge([$val, today()], RES_ACTIVE))->fetchColumn();
        if ($n >= RES_MAX_ACTIVE) {
            throw new ValidationError(t('Ya tienes %d reservas pendientes o confirmadas. Para hacer otra, cancela alguna o llámanos.', RES_MAX_ACTIVE));
        }
    }
}

function next_seq(): int
{
    return (int)q('SELECT COALESCE(MAX(seq), 0) + 1 FROM reservations')->fetchColumn();
}

/** Enlace que recibe el cliente para confirmar su reserva. */
function confirm_url(array $row): string
{
    $t = dec($row['verify_token'] ?? '');
    if ($t === '') {
        $t = bin2hex(random_bytes(16));
        q('UPDATE reservations SET verify_token = ? WHERE id = ?', [enc($t), $row['id']]);
    }
    return base_url() . '/confirmar.php?r=' . (int)$row['id'] . '&t=' . $t;
}

/** Texto del WhatsApp que el restaurante envía al cliente. */
function whatsapp_text(array $r, string $url): string
{
    // En el idioma en el que reservó el cliente
    return with_lang($r['lang'] ?? 'es', function () use ($r, $url) {
        $s = settings();
        $first = explode(' ', trim($r['name']))[0];
        $people = $r['people'] . ' ' . ($r['people'] === 1 ? t('persona') : t('personas'));
        $lq = lang() !== 'es' ? '?lang=' . lang() : '';
        return t('Hola %s, confirma tu reserva en %s para el %s a las %s (%s). Abre este enlace y pulsa «Confirmar mi reserva»:', $first, $s['name'], fmt_date_l($r['date']), $r['time'], $people)
            . "\n$url" . ($lq ? '&lang=' . lang() : '') . "\n\n"
            . t('Si te has equivocado en algún dato, vuelve a hacer la reserva en la web:') . ' ' . base_url() . '/reservar.php' . $lq;
    });
}

/** Reserva con lo que necesita el panel: enlace de confirmación, enlace de WhatsApp listo para enviar y si se manda solo. */
function res_admin(array $row): array
{
    $r = res_out($row);
    if ($r['source'] === 'web' && in_array($r['status'], ['pendiente', 'esperando'], true) && $r['phone'] !== '') {
        $url = confirm_url($row);
        $r['confirm_url'] = $url;
        // wa_url queda siempre como plan B para mandarlo a mano si el envío automático falla
        $r['wa_url'] = 'https://wa.me/' . wa_number($r['phone']) . '?text=' . rawurlencode(whatsapp_text($r, $url));
        $r['wa_auto'] = wa_api_ready();
    }
    return $r;
}

function get_reservation_admin(int $id): array
{
    $r = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
    if (!$r) throw new ValidationError('La reserva no existe');
    return res_admin($r);
}

/** El cliente toca el enlace del WhatsApp. Devuelve [estado, reserva]. */
/**
 * Mira el estado de la reserva del enlace SIN cambiar nada. Devuelve [estado, reserva]:
 * 'pendiente' (falta que el cliente pulse «Confirmar»), 'ya', 'cancelada', 'rechazada', 'pasada' o 'invalido'.
 * Abrir el enlace nunca confirma: WhatsApp, Telegram o los antivirus del correo abren los enlaces
 * solos para hacer la vista previa, y confirmarían reservas sin que nadie las viera.
 */
function peek_by_customer(int $id, string $token): array
{
    $row = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
    if (!$row || !preg_match('/^[a-f0-9]{32}$/', $token) || !hash_equals(dec($row['verify_token']), $token)) return ['invalido', null];
    $r = res_out($row);
    if ($r['status'] === 'confirmada') return ['ya', $r];
    if (in_array($r['status'], ['rechazada', 'cancelada'], true)) return [$r['status'], $r];
    if (strtotime($r['date'] . ' ' . $r['time']) < time()) return ['pasada', $r];
    return ['pendiente', $r];
}

/** El cliente pulsa «Confirmar mi reserva» (solo por POST). Devuelve [estado, reserva]. */
function confirm_by_customer(int $id, string $token): array
{
    [$state, $r] = peek_by_customer($id, $token);
    if ($state !== 'pendiente') return [$state, $r];
    $row = q('SELECT customer_id FROM reservations WHERE id = ?', [$id])->fetch();
    q("UPDATE reservations SET status = 'confirmada', updated_at = ?, seq = ? WHERE id = ?", [now(), next_seq(), $id]);
    audit('reserva_confirmada_cliente', (int)$row['customer_id'], "Reserva #$id confirmada desde el enlace");
    $r = get_reservation($id);
    $s = settings();
    restaurant_mail("Reserva confirmada por el cliente: {$r['name']}, " . fmt_date_es($r['date']) . " {$r['time']}",
        "{$r['name']} ha confirmado su reserva desde el enlace del WhatsApp.\n\nDía: " . fmt_date_es($r['date']) . " a las {$r['time']}\nPersonas: {$r['people']}");
    notify_customer($r);
    return ['confirmada', $r];
}

function get_reservation(int $id): array
{
    $r = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
    if (!$r) throw new ValidationError('La reserva no existe');
    return res_out($r);
}

function res_out(array $r): array
{
    return [
        'id' => (int)$r['id'], 'name' => dec($r['name']), 'phone' => dec($r['phone']), 'email' => dec($r['email']),
        'date' => $r['rdate'], 'time' => $r['rtime'], 'people' => (int)$r['people'], 'notes' => dec($r['notes']),
        'status' => $r['status'], 'source' => $r['source'], 'created_at' => $r['created_at'],
        'customer' => (int)($r['customer_id'] ?? 0) > 0,
        'cancel_by' => (string)($r['cancel_by'] ?? ''),   // cliente o restaurante
        'lang' => (string)($r['lang'] ?? 'es'),           // idioma en el que reservó
    ];
}

function list_reservations(string $from, string $to): array
{
    if (!is_date($from) || !is_date($to)) throw new ValidationError('Fechas no válidas');
    $rows = q('SELECT * FROM reservations WHERE deleted = 0 AND rdate BETWEEN ? AND ? ORDER BY rdate, rtime, id', [$from, $to])->fetchAll();
    return array_map('res_admin', $rows);
}

/** Peticiones para la pestaña del panel: nuevas y las que esperan a que el cliente confirme. */
function list_requests(): array
{
    $rows = q("SELECT * FROM reservations WHERE deleted = 0 AND status IN ('pendiente', 'esperando') AND rdate >= ? ORDER BY rdate, rtime, id", [today()])->fetchAll();
    $o = ['nuevas' => [], 'esperando' => []];
    foreach ($rows as $row) {
        $r = res_admin($row);
        if ($r['status'] === 'pendiente') $o['nuevas'][] = $r; else $o['esperando'][] = $r;
    }
    return $o;
}

/** Resumen por día de un mes para el calendario. */
function month_summary(string $month): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) throw new ValidationError('Mes no válido');
    $from = "$month-01";
    $to = date('Y-m-t', strtotime($from));
    $rows = q('SELECT rdate, status, people FROM reservations WHERE deleted = 0 AND rdate BETWEEN ? AND ?', [$from, $to])->fetchAll();
    $days = [];
    foreach ($rows as $r) {
        if (!in_array($r['status'], RES_ACTIVE, true)) continue;
        $d = &$days[$r['rdate']];
        $d ??= ['count' => 0, 'people' => 0, 'pending' => 0];
        $d['count']++;
        $d['people'] += (int)$r['people'];
        if ($r['status'] !== 'confirmada') $d['pending']++;
        unset($d);
    }
    return ['month' => $month, 'days' => $days, 'closed_dates' => settings()['closed_dates']];
}

/**
 * Novedades desde la última vez que miró la app: peticiones nuevas y reservas que el cliente acaba de confirmar.
 * Usa un contador propio (seq) que avanza con cada petición y con cada confirmación.
 */
function changes(int $since): array
{
    $last = (int)q('SELECT COALESCE(MAX(seq), 0) FROM reservations')->fetchColumn();
    $pending = (int)q("SELECT COUNT(*) FROM reservations WHERE status = 'pendiente' AND rdate >= ?", [today()])->fetchColumn();
    $waiting = (int)q("SELECT COUNT(*) FROM reservations WHERE status = 'esperando' AND rdate >= ?", [today()])->fetchColumn();
    $new = [];
    if ($since > 0) {
        $new = array_map('res_admin', q("SELECT * FROM reservations WHERE seq > ? AND deleted = 0 AND source = 'web' AND (status IN ('pendiente', 'confirmada') OR (status = 'cancelada' AND cancel_by = 'cliente')) ORDER BY seq", [$since])->fetchAll());
    }
    return ['last_id' => $last, 'pending' => $pending, 'waiting' => $waiting, 'new' => $new, 'erase' => erase_pending_count()];
}

function set_reservation_status(int $id, string $status): array
{
    if (!in_array($status, RES_STATUSES, true)) throw new ValidationError('Estado no válido');
    $before = get_reservation($id);
    q('UPDATE reservations SET status = ?, cancel_by = ?, updated_at = ? WHERE id = ?', [$status, $status === 'cancelada' ? 'restaurante' : '', now(), $id]);
    $after = get_reservation($id);
    // Al aceptar una reserva de la web, el WhatsApp con el enlace sale solo si la API de Meta está configurada
    $wa = null;
    if ($status === 'esperando' && $before['status'] !== 'esperando' && $after['source'] === 'web' && wa_api_ready()) {
        $wa = wa_send_confirmation(q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch());
    }
    if ($before['status'] !== $status) notify_customer($after);
    return get_reservation_admin($id) + ($wa === null ? [] : ['wa_sent' => $wa === '', 'wa_error' => $wa]);
}

/** Reenvía por la API de Meta el WhatsApp de una reserva que espera al cliente. */
function resend_reservation_whatsapp(int $id): array
{
    $row = q('SELECT * FROM reservations WHERE id = ? AND deleted = 0', [$id])->fetch();
    if (!$row) throw new ValidationError('La reserva no existe');
    if ($row['status'] !== 'esperando' || $row['source'] !== 'web') throw new ValidationError('Esta reserva no está esperando al cliente');
    $wa = wa_send_confirmation($row);
    return get_reservation_admin($id) + ['wa_sent' => $wa === '', 'wa_error' => $wa];
}

/** Crear o editar una reserva desde el panel (p. ej. una llamada). */
function save_reservation(array $in): array
{
    $d = reservation_fields($in, true);
    day_slots($d['rdate'], true); // valida formato
    $id = (int)($in['id'] ?? 0);
    if ($id) {
        get_reservation($id);
        // Los recordatorios vuelven a empezar por si ha cambiado el día o la hora
        q('UPDATE reservations SET name=?, phone=?, email=?, email_idx=?, rdate=?, rtime=?, people=?, notes=?, updated_at=?, reminders=0 WHERE id=?', [
            enc($d['name']), enc($d['phone']), enc($d['email']), email_idx($d['email']), $d['rdate'], $d['rtime'], $d['people'], enc($d['notes']), now(), $id,
        ]);
        return get_reservation_admin($id);
    }
    q('INSERT INTO reservations (name, phone, email, email_idx, rdate, rtime, people, notes, status, source, ip, created_at, updated_at, seq)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        enc($d['name']), enc($d['phone']), enc($d['email']), email_idx($d['email']), $d['rdate'], $d['rtime'], $d['people'], enc($d['notes']),
        'confirmada', 'panel', '', now(), now(), next_seq(),
    ]);
    return get_reservation_admin((int)db()->lastInsertId());
}

/**
 * Borrar desde el panel: desaparece de la app, pero se guarda como cancelada hasta que pasa su hora para que
 * el enlace del cliente diga «reserva cancelada» y no «enlace no válido». Luego se borra del todo.
 */
function delete_reservation(int $id): void
{
    q("UPDATE reservations SET status = 'cancelada', cancel_by = 'restaurante', deleted = 1, updated_at = ? WHERE id = ?", [now(), $id]);
}

/** El cliente cancela desde el enlace del WhatsApp o del email (solo por POST). Devuelve [estado, reserva]. */
function cancel_by_link(int $id, string $token): array
{
    [$state, $r] = peek_by_customer($id, $token);
    if (!in_array($state, ['pendiente', 'ya'], true)) return [$state, $r];
    q("UPDATE reservations SET status = 'cancelada', cancel_by = 'cliente', updated_at = ?, seq = ? WHERE id = ?", [now(), next_seq(), $id]);
    $row = q('SELECT customer_id FROM reservations WHERE id = ?', [$id])->fetch();
    audit('reserva_cancelada_cliente', (int)$row['customer_id'], "Reserva #$id cancelada desde el enlace");
    $r = get_reservation($id);
    $s = settings();
    restaurant_mail("Reserva cancelada por el cliente: {$r['name']}, " . fmt_date_es($r['date']) . " {$r['time']}",
        "{$r['name']} ha cancelado su reserva desde el enlace.\n\nDía: " . fmt_date_es($r['date']) . " a las {$r['time']}\nPersonas: {$r['people']}");
    notify_customer($r);
    return ['cancelada', $r];
}

function notify_customer(array $r): void
{
    if ($r['email'] === '' || $r['source'] !== 'web') return;
    // En el idioma en el que reservó el cliente
    with_lang($r['lang'] ?? 'es', function () use ($r) {
        $s = settings();
        $when = t('%s a las %s', fmt_date_l($r['date']), $r['time']);
        $tel = $s['phone'] ? "\n" . t('Si necesitas cambiar algo, llámanos al %s.', $s['phone']) : '';
        $hi = t('Hola %s:', $r['name']) . "\n\n";
        $msgs = [
            'esperando'  => [t('Confirma tu reserva en %s', $s['name']), $hi . t('Tenemos mesa para %d personas el %s. Para confirmarla, abre este enlace y pulsa «Confirmar mi reserva»:', $r['people'], $when) . "\n\n" . confirm_url(q('SELECT * FROM reservations WHERE id = ?', [$r['id']])->fetch()) . (lang() !== 'es' ? '&lang=' . lang() : '') . "\n\n" . t('También te lo hemos enviado por WhatsApp. Si te has equivocado en algún dato, vuelve a hacer la reserva en la web.') . "\n\n{$s['name']}"],
            'confirmada' => [t('Reserva confirmada en %s', $s['name']), $hi . t('Tu reserva para %d personas el %s está confirmada. ¡Te esperamos!', $r['people'], $when) . "$tel\n\n{$s['name']}\n{$s['address']}"],
            'rechazada'  => [t('No podemos atender tu reserva en %s', $s['name']), $hi . t('Lo sentimos, no tenemos mesa para %d personas el %s.', $r['people'], $when) . "$tel\n\n{$s['name']}"],
            'cancelada'  => [t('Reserva cancelada en %s', $s['name']), $hi . t('Tu reserva del %s ha quedado cancelada.', $when) . "$tel\n\n{$s['name']}"],
        ];
        if (isset($msgs[$r['status']])) send_mail($r['email'], ...$msgs[$r['status']]);
    });
}

/**
 * Para rechazar una reserva con un WhatsApp: las horas de ese día en las que sí cabe ese grupo, cerca de la hora
 * que pidió (2 horas antes o después), y los textos del mensaje en el idioma del cliente.
 * 'text_slots' lleva {horas} donde van las horas que el restaurante elija; 'text_none' es para cuando no hay sitio.
 */
function reject_info(int $id): array
{
    $row = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
    if (!$row) throw new ValidationError('La reserva no existe');
    $r = res_out($row);
    $slots = [];
    try {
        $day = day_slots($r['date'], false, $id);
        $want = strtotime($r['date'] . ' ' . $r['time']);
        foreach ($day['slots'] as $sl) {
            $t = strtotime($r['date'] . ' ' . $sl['time']);
            if ($sl['time'] !== $r['time'] && $sl['left'] >= $r['people'] && abs($t - $want) <= 2 * 3600) $slots[] = $sl['time'];
        }
    } catch (Throwable $e) { /* día sin horario */ }
    $texts = with_lang($r['lang'] ?: 'es', function () use ($r) {
        $s = settings();
        $first = explode(' ', trim($r['name']))[0];
        $people = $r['people'] . ' ' . ($r['people'] === 1 ? t('persona') : t('personas'));
        $lq = lang() !== 'es' ? '?lang=' . lang() : '';
        $sorry = t('Hola %s, somos %s. Lo sentimos: no tenemos mesa para %s el %s a las %s.', $first, $s['name'], $people, fmt_date_l($r['date']), $r['time']);
        return [
            'text_slots' => $sorry . ' ' . t('Ese día sí tenemos sitio a estas horas: %s. Si te va bien alguna, haz la reserva en la web:', '{horas}') . ' ' . base_url() . '/reservar.php' . $lq,
            'text_none'  => $sorry . ' ' . t('Ese día ya no nos queda sitio. Esperamos verte otro día; puedes reservar en la web:') . ' ' . base_url() . '/reservar.php' . $lq,
        ];
    });
    return ['slots' => $slots, 'wa_number' => $r['phone'] !== '' ? wa_number($r['phone']) : ''] + $texts;
}
