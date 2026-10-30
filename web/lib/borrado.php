<?php
declare(strict_types=1);

/*
 * Derecho de supresión (RGPD art. 17) sin tener cuenta: el cliente escribe su email o su teléfono en la web
 * y borra todo lo que guardamos con ese dato (cuenta, reservas y registro de actividad).
 *
 * Para que nadie pueda borrar los datos de otro, siempre hay que confirmar con un enlace:
 * - Con email: el enlace llega a ese email.
 * - Con teléfono: la petición aparece en la app y el restaurante le manda el enlace por WhatsApp a ese número.
 * La web responde siempre lo mismo, haya datos o no, para no desvelar quién es cliente.
 * También aquí está la lista de cuentas de clientes para la app.
 */

const ERASE_HOURS = 72; // lo que dura el enlace

/** Teléfono solo con cifras y sin el prefijo de España, para comparar. */
function phone_norm(string $p): string
{
    $d = preg_replace('/\D/', '', $p);
    if (str_starts_with($d, '0034')) $d = substr($d, 4);
    elseif (strlen($d) === 11 && str_starts_with($d, '34')) $d = substr($d, 2);
    return $d;
}

/** Lo que tenemos guardado con un email o un teléfono: [ids de cuentas, ids de reservas]. */
function erase_find(string $kind, string $contact): array
{
    $cus = $res = [];
    if ($kind === 'email') {
        $idx = email_idx($contact);
        $cus = q('SELECT id FROM customers WHERE email_idx = ?', [$idx])->fetchAll(PDO::FETCH_COLUMN);
        $res = q('SELECT id FROM reservations WHERE email_idx = ?', [$idx])->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $n = phone_norm($contact);
        if (strlen($n) < 6) return [[], []];
        foreach (q("SELECT id, phone FROM customers WHERE phone <> ''")->fetchAll() as $r) if (phone_norm(dec($r['phone'])) === $n) $cus[] = $r['id'];
        foreach (q("SELECT id, phone FROM reservations WHERE phone <> ''")->fetchAll() as $r) if (phone_norm(dec($r['phone'])) === $n) $res[] = $r['id'];
    }
    // Las reservas de esas cuentas, aunque se hicieran con otro teléfono o email
    if ($cus) {
        $ph = implode(',', array_fill(0, count($cus), '?'));
        $res = array_merge($res, q("SELECT id FROM reservations WHERE customer_id IN ($ph)", $cus)->fetchAll(PDO::FETCH_COLUMN));
    }
    return [array_values(array_unique(array_map('intval', $cus))), array_values(array_unique(array_map('intval', $res)))];
}

/** El cliente pide borrar sus datos desde la web. */
function erase_request(array $in): array
{
    if (trim((string)($in['website'] ?? '')) !== '') return ['ok' => true]; // robots
    $v = trim((string)($in['contact'] ?? ''));
    if ($v === '') throw new ValidationError(t('Escribe tu email o tu teléfono.'));
    $kind = str_contains($v, '@') ? 'email' : 'phone';
    if ($kind === 'email') {
        $v = mb_strtolower($v);
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) throw new ValidationError(t('Ese email no parece correcto.'));
    } elseif (strlen(phone_norm($v)) < 9) {
        throw new ValidationError(t('Ese teléfono no parece correcto.'));
    }
    $ip = hash('sha256', 'borrar' . client_ip());
    $n = (int)q('SELECT COUNT(*) FROM erase_requests WHERE ip = ? AND created_at > ?', [$ip, date('Y-m-d H:i:s', time() - 3600)])->fetchColumn();
    if ($n >= 5) throw new ValidationError(t('Demasiados intentos. Espera una hora.'));
    q("DELETE FROM erase_requests WHERE created_at < ?", [date('Y-m-d H:i:s', time() - 30 * 86400)]);

    [$cus, $res] = erase_find($kind, $v);
    $msg = $kind === 'email'
        ? t('Si tenemos datos con ese email, te hemos enviado un enlace para confirmar el borrado. Mira también en spam.')
        : t('Si tenemos datos con ese teléfono, te enviaremos por WhatsApp un enlace para confirmar el borrado.');
    if (!$cus && !$res) {
        q('INSERT INTO erase_requests (kind, contact, status, ip, created_at) VALUES (?, ?, ?, ?, ?)', [$kind, '', 'vacia', $ip, now()]);
        return ['ok' => true, 'message' => $msg];
    }
    if ($kind === 'email') {
        $token = bin2hex(random_bytes(32));
        q('INSERT INTO erase_requests (kind, contact, token_hash, status, ip, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$kind, enc($v), hash('sha256', $token), 'enviada', $ip, now(), date('Y-m-d H:i:s', time() + ERASE_HOURS * 3600)]);
        $s = settings();
        send_mail($v, t('Borrar tus datos de %s', $s['name']),
            t('Hola:') . "\n\n" . t('Hemos recibido una petición para borrar todos los datos que %s guarda con este email (cuenta, reservas y registro de actividad). Si has sido tú, abre este enlace y pulsa «Borrar mis datos»:', $s['name'])
            . "\n\n" . erase_url($token) . "\n\n" . t('El enlace caduca en %d horas. Si no has sido tú, ignora este mensaje: no se borrará nada.', ERASE_HOURS) . "\n\n" . $s['name']);
    } else {
        q('INSERT INTO erase_requests (kind, contact, status, ip, created_at) VALUES (?, ?, ?, ?, ?)', [$kind, enc($v), 'nueva', $ip, now()]);
        restaurant_mail('Petición para borrar datos de un cliente',
            "Un cliente ha pedido desde la web borrar sus datos con el teléfono acabado en " . substr(phone_norm($v), -3) . ".\n\nÁbrela en la app (Ajustes → Clientes con cuenta) y pulsa «Enviar enlace por WhatsApp». El cliente confirmará el borrado desde ese enlace.");
    }
    return ['ok' => true, 'message' => $msg];
}

function erase_url(string $token): string
{
    return base_url() . '/borrar-datos.php?t=' . $token . (lang() !== 'es' ? '&lang=' . lang() : '');
}

/** La petición de un enlace, si es válida. */
function erase_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $r = q("SELECT * FROM erase_requests WHERE token_hash = ? AND status = 'enviada'", [hash('sha256', $token)])->fetch();
    if (!$r || ($r['expires_at'] && strtotime($r['expires_at']) < time())) return null;
    $r['contact'] = dec($r['contact']);
    return $r;
}

/** Lo que se va a borrar (para enseñarlo antes de confirmar). */
function erase_summary(array $req): array
{
    [$cus, $res] = erase_find($req['kind'], $req['contact']);
    $future = 0;
    foreach ($res as $id) {
        $r = q('SELECT rdate, rtime, status FROM reservations WHERE id = ?', [$id])->fetch();
        if ($r && in_array($r['status'], RES_ACTIVE, true) && strtotime($r['rdate'] . ' ' . $r['rtime']) > time()) $future++;
    }
    return ['accounts' => count($cus), 'reservations' => count($res), 'future' => $future];
}

/**
 * Borra de verdad. Las reservas que aún no han pasado se cancelan (el restaurante recibe el aviso) y de todas se
 * borran nombre, teléfono, email y notas; se quedan solo el día, la hora y las personas, sin nada que identifique.
 */
function erase_confirm(string $token): ?array
{
    $req = erase_by_token($token);
    if (!$req) return null;
    [$cus, $res] = erase_find($req['kind'], $req['contact']);
    $cancelled = 0;
    foreach ($res as $id) {
        $r = q('SELECT * FROM reservations WHERE id = ?', [$id])->fetch();
        if (!$r) continue;
        $active = in_array($r['status'], RES_ACTIVE, true) && strtotime($r['rdate'] . ' ' . $r['rtime']) > time();
        if ($active) {
            $cancelled++;
            $info = res_out($r);
            restaurant_mail("Reserva cancelada: el cliente ha borrado sus datos, " . fmt_date_es($info['date']) . " {$info['time']}",
                "{$info['name']} ha borrado sus datos desde la web y su reserva se ha cancelado.\n\nDía: " . fmt_date_es($info['date']) . " a las {$info['time']}\nPersonas: {$info['people']}");
        }
        q("UPDATE reservations SET name = ?, phone = '', email = '', email_idx = '', notes = '', verify_token = '', ip = '', customer_id = 0, updated_at = ?"
            . ($active ? ", status = 'cancelada', cancel_by = 'cliente', seq = " . next_seq() : '') . ' WHERE id = ?',
            [enc('Datos borrados'), now(), $id]);
    }
    if ($cus) {
        $ph = implode(',', array_fill(0, count($cus), '?'));
        q("DELETE FROM audit_log WHERE customer_id IN ($ph)", $cus);
        q("DELETE FROM customers WHERE id IN ($ph)", $cus);
    }
    q("UPDATE erase_requests SET status = 'hecha', contact = '', token_hash = '' WHERE id = ?", [$req['id']]);
    audit('datos_borrados', 0, count($cus) . ' cuentas, ' . count($res) . ' reservas (' . $cancelled . ' canceladas)');
    if (current_customer() && in_array((int)current_customer()['id'], $cus, true)) customer_logout();
    return ['accounts' => count($cus), 'reservations' => count($res), 'cancelled' => $cancelled];
}

/* ---------- Para la app ---------- */

/** Peticiones por teléfono que esperan a que el restaurante mande el enlace. */
function erase_pending(): array
{
    $o = [];
    foreach (q("SELECT * FROM erase_requests WHERE kind = 'phone' AND status = 'nueva' ORDER BY id DESC")->fetchAll() as $r) {
        $o[] = ['id' => (int)$r['id'], 'phone' => dec($r['contact']), 'created_at' => $r['created_at']];
    }
    return $o;
}

function erase_pending_count(): int
{
    try { return (int)q("SELECT COUNT(*) FROM erase_requests WHERE kind = 'phone' AND status = 'nueva'")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

/** El restaurante pulsa «Enviar enlace por WhatsApp»: se crea el enlace y se devuelve el WhatsApp listo. */
function erase_whatsapp(int $id): array
{
    $r = q("SELECT * FROM erase_requests WHERE id = ? AND kind = 'phone' AND status = 'nueva'", [$id])->fetch();
    if (!$r) throw new ValidationError('Esa petición ya no está pendiente');
    $phone = dec($r['contact']);
    // El idioma: el de la última reserva con ese teléfono
    [, $res] = erase_find('phone', $phone);
    $lang = $res ? (string)(q('SELECT lang FROM reservations WHERE id = ?', [max($res)])->fetchColumn() ?: 'es') : 'es';
    $token = bin2hex(random_bytes(32));
    q("UPDATE erase_requests SET token_hash = ?, status = 'enviada', expires_at = ? WHERE id = ?", [hash('sha256', $token), date('Y-m-d H:i:s', time() + ERASE_HOURS * 3600), $id]);
    $text = with_lang($lang, function () use ($token) {
        $s = settings();
        return t('Hola, somos %s. Nos has pedido borrar todos tus datos. Para confirmarlo, abre este enlace y pulsa «Borrar mis datos»:', $s['name']) . "\n" . erase_url($token)
            . "\n\n" . t('El enlace caduca en %d horas.', ERASE_HOURS);
    });
    $n = phone_norm($phone);
    if (strlen($n) === 9) $n = '34' . $n;
    return ['wa_url' => 'https://wa.me/' . $n . '?text=' . rawurlencode($text)];
}

function erase_dismiss(int $id): void
{
    q("UPDATE erase_requests SET status = 'descartada', contact = '' WHERE id = ? AND status = 'nueva'", [$id]);
}

/** Todas las cuentas de clientes, con cuántas reservas tienen. */
function customers_list(): array
{
    $counts = [];
    foreach (q('SELECT customer_id, COUNT(*) AS n, MAX(rdate) AS last FROM reservations WHERE customer_id > 0 GROUP BY customer_id')->fetchAll() as $r) {
        $counts[(int)$r['customer_id']] = [(int)$r['n'], $r['last']];
    }
    $o = [];
    foreach (q('SELECT id, name, email, phone, created_at, last_login, verified_at FROM customers ORDER BY id DESC')->fetchAll() as $r) {
        $c = customer_plain($r);
        [$n, $last] = $counts[(int)$r['id']] ?? [0, null];
        $o[] = [
            'id' => (int)$c['id'], 'name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone'],
            'created_at' => $c['created_at'], 'last_login' => $c['last_login'], 'verified' => $c['verified_at'] !== null,
            'reservations' => $n, 'last_reservation' => $last,
        ];
    }
    return $o;
}

/** El administrador borra una cuenta desde la app (por ejemplo, si el cliente se lo pide en persona). */
function customer_delete_admin(int $id): void
{
    if (!q('SELECT id FROM customers WHERE id = ?', [$id])->fetch()) throw new ValidationError('Esa cuenta ya no existe');
    q('UPDATE reservations SET customer_id = 0 WHERE customer_id = ?', [$id]);
    q('DELETE FROM audit_log WHERE customer_id = ?', [$id]);
    q('DELETE FROM customers WHERE id = ?', [$id]);
    audit('cuenta_borrada_restaurante', 0, "Cuenta #$id borrada desde la app");
}
