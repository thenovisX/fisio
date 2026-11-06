<?php
declare(strict_types=1);

/*
 * Cuentas de clientes de la web: registro, acceso, sus reservas y cancelación.
 * Usa una sesión PHP propia guardada en data/sesiones (protegida y fuera del alcance de otras webs del hosting).
 * La cookie solo se crea cuando alguien entra o se registra: quien solo mira la web no recibe ninguna.
 */

const CUSTOMER_COOKIE = 'cliente_sesion';
const CUSTOMER_DAYS = 60;

function customer_session(bool $create): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) return true;
    if (!$create && empty($_COOKIE[CUSTOMER_COOKIE])) return false;
    $dir = dirname(__DIR__) . '/data/sesiones';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', (string)(CUSTOMER_DAYS * 86400));
    ini_set('session.use_strict_mode', '1');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name(CUSTOMER_COOKIE);
    session_set_cookie_params([
        'lifetime' => CUSTOMER_DAYS * 86400, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    return session_start();
}

function current_customer(): ?array
{
    static $c = false;
    if ($c !== false) return $c;
    if (!customer_session(false) || empty($_SESSION['cid'])) return $c = null;
    $row = q('SELECT id, name, email, phone, created_at, pass_hash FROM customers WHERE id = ?', [(int)$_SESSION['cid']])->fetch();
    if (!$row) return $c = null;
    // Si la contraseña ha cambiado desde que se abrió esta sesión (en otro dispositivo o con «¿Olvidaste
    // la contraseña?»), la sesión deja de valer. Las sesiones de antes de este cambio se adoptan.
    $fp = pass_fingerprint($row['pass_hash']);
    if (!isset($_SESSION['pv'])) $_SESSION['pv'] = $fp;
    if (!hash_equals((string)$_SESSION['pv'], $fp)) {
        customer_logout();
        return $c = null;
    }
    unset($row['pass_hash']);
    return $c = customer_plain($row);
}

/** Huella corta de la huella de la contraseña: cambia cuando cambia la contraseña. */
function pass_fingerprint(string $passHash): string
{
    return substr(hash_hmac('sha256', $passHash, 'sesion|' . (cfg()['secret'] ?? '')), 0, 32);
}

/** Fila de cliente con los datos descifrados. */
function customer_plain(array $row): array
{
    foreach (['name', 'email', 'phone'] as $k) if (isset($row[$k])) $row[$k] = dec($row[$k]);
    return $row;
}

function customer_out(?array $c): ?array
{
    return $c ? ['id' => (int)$c['id'], 'name' => $c['name'], 'email' => $c['email'], 'phone' => $c['phone']] : null;
}

/**
 * Bloquea peticiones con cookie que vengan de otra web (protección CSRF, además de SameSite=Lax).
 * Mira la cabecera Origin y, si el navegador no la manda, Sec-Fetch-Site.
 */
function same_origin_only(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        fail('Petición no permitida', 403);
    }
    if ($origin === '' && in_array($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '', ['cross-site', 'same-site'], true)) {
        fail('Petición no permitida', 403);
    }
}

/** Fallos de contraseña de clientes: 10 por IP cada 15 minutos. */
function customer_attempts_guard(): string
{
    return throttle_check('cliente-ip|' . client_ip(), 10, 900);
}

function customer_login_session(int $id): void
{
    customer_session(true);
    session_regenerate_id(true);
    $_SESSION['cid'] = $id;
    $_SESSION['pv'] = pass_fingerprint((string)q('SELECT pass_hash FROM customers WHERE id = ?', [$id])->fetchColumn());
    q('UPDATE customers SET last_login = ? WHERE id = ?', [now(), $id]);
}

function customer_register(array $in): array
{
    if (!empty($in['website'])) throw new ValidationError('No se pudo crear la cuenta');
    $name = str_in($in, 'name', 80, true, 'nombre');
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    $phone = str_in($in, 'phone', 30);
    $pass = (string)($in['password'] ?? '');
    if (mb_strlen($name) < 2) throw new ValidationError('Escribe tu nombre');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 6) throw new ValidationError('El teléfono no parece correcto');
    if (strlen($pass) < 8) throw new ValidationError('La contraseña necesita al menos 8 caracteres');
    if (empty($in['privacy'])) throw new ValidationError('Tienes que aceptar las condiciones de uso y la política de privacidad');
    // Cada alta envía un email: como mucho 5 por IP cada hora (evita usar la web para bombardear buzones)
    throttle_hit(throttle_check('registro-ip|' . client_ip(), 5, 3600, 'Demasiados intentos. Espera una hora.'));
    $old = q('SELECT id, verified_at, name FROM customers WHERE email_idx = ?', [email_idx($email)])->fetch();
    if ($old && $old['verified_at']) {
        // Misma respuesta que un alta nueva, para no revelar qué emails tienen cuenta. Al dueño del email
        // se le avisa por si quería entrar y no se acordaba.
        $s = settings();
        send_mail($email, t('Ya tienes una cuenta en %s', $s['name']),
            t('Hola %s:', dec($old['name'])) . "\n\n" . t('Alguien (seguramente tú) ha intentado crear una cuenta con este email, pero ya tienes una. Entra con tu contraseña o, si no la recuerdas, pide una nueva aquí:') . "\n\n" . base_url() . '/recuperar.php'
            . "\n\n" . t('Si no has sido tú, ignora este email: tu cuenta sigue igual.'));
        return ['pending' => true, 'email' => $email];
    }
    if ($old) {
        // Cuenta sin activar con ese email: se sustituye (quien la creó no demostró que el email era suyo)
        q('DELETE FROM customers WHERE id = ?', [$old['id']]);
    }
    q('INSERT INTO customers (name, email, email_idx, phone, pass_hash, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [enc($name), enc($email), email_idx($email), enc($phone), pass_hash($pass), now()]);
    $id = (int)db()->lastInsertId();
    audit('registro', $id, 'Acepta condiciones de uso y privacidad, versión ' . LEGAL_VERSION);
    send_activation($id, $email, $name);
    return ['pending' => true, 'email' => $email];
}

/** Doble opt-in: envía el enlace para activar la cuenta (válido 48 horas). */
function send_activation(int $id, string $email, string $name): void
{
    $token = bin2hex(random_bytes(24));
    q('UPDATE customers SET verify_hash = ?, verify_expires = ? WHERE id = ?', [hash('sha256', $token), date('Y-m-d H:i:s', time() + 48 * 3600), $id]);
    $url = base_url() . '/cuenta.php?activar=' . $token . '&c=' . $id;
    $s = settings();
    send_mail($email, t('Activa tu cuenta en %s', $s['name']),
        t('Hola %s:', $name) . "\n\n" . t('Para activar tu cuenta en %s abre este enlace (caduca en 48 horas):', $s['name']) . "\n\n$url\n\n" . t('Si no has creado tú esta cuenta, ignora este email y no se activará.'));
    dev_log("enlace de activación: $url");
}

function customer_activate(int $id, string $token): bool
{
    $c = q('SELECT * FROM customers WHERE id = ?', [$id])->fetch();
    if (!$c || $c['verified_at'] || $c['verify_hash'] === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) return (bool)($c['verified_at'] ?? false);
    if (!hash_equals($c['verify_hash'], hash('sha256', $token)) || strtotime((string)$c['verify_expires']) < time()) return false;
    q("UPDATE customers SET verified_at = ?, verify_hash = '', verify_expires = NULL WHERE id = ?", [now(), $id]);
    // Ahora que el email está comprobado, sus reservas anteriores pasan a su cuenta
    q('UPDATE reservations SET customer_id = ? WHERE customer_id = 0 AND email_idx = ?', [$id, $c['email_idx']]);
    audit('email_verificado', $id);
    customer_login_session($id);
    return true;
}

function customer_resend(array $in): void
{
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    throttle_hit(throttle_check('registro-ip|' . client_ip(), 5, 3600, 'Demasiados intentos. Espera una hora.'));
    $c = q('SELECT * FROM customers WHERE email_idx = ?', [email_idx($email)])->fetch();
    // Respuesta igual exista o no la cuenta, para no revelar qué emails están registrados
    if ($c && !$c['verified_at']) send_activation((int)$c['id'], $email, dec($c['name']));
}

function customer_login(array $in): array
{
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    $pass = (string)($in['password'] ?? '');
    $ip = customer_attempts_guard();
    $byEmail = throttle_check('cliente-email|' . email_idx($email), 20, 900);
    $c = q('SELECT * FROM customers WHERE email_idx = ?', [email_idx($email)])->fetch();
    if (!pass_verify($pass, $c ? $c['pass_hash'] : null)) {
        throttle_hit($ip);
        throttle_hit($byEmail);
        throw new ValidationError('Email o contraseña incorrectos');
    }
    if (!$c['verified_at']) {
        throw new ValidationError('Tu cuenta aún no está activada. Abre el enlace que te enviamos por email (mira también en spam).');
    }
    throttle_clear($ip);
    throttle_clear($byEmail);
    if (pass_needs_rehash($c['pass_hash'])) q('UPDATE customers SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $c['id']]);
    customer_login_session((int)$c['id']);
    return customer_out(customer_plain($c));
}

function customer_logout(): void
{
    if (!customer_session(false)) return;
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(CUSTOMER_COOKIE, '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) fail('Entra en tu cuenta para continuar', 401);
    return $c;
}

function customer_update(array $c, array $in): array
{
    $name = str_in($in, 'name', 80, true, 'nombre');
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    $phone = str_in($in, 'phone', 30);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    if ($email !== $c['email'] && q('SELECT id FROM customers WHERE email_idx = ? AND id <> ?', [email_idx($email), $c['id']])->fetch()) {
        throw new ValidationError('Ese email ya lo usa otra cuenta');
    }
    $new = (string)($in['new_password'] ?? '');
    $emailChanged = $email !== $c['email'];
    if ($new !== '' || $emailChanged) {
        $row = q('SELECT pass_hash FROM customers WHERE id = ?', [$c['id']])->fetch();
        $guard = throttle_check('password-cliente|' . $c['id'], 5, 900);
        if (!pass_verify((string)($in['password'] ?? ''), $row['pass_hash'])) {
            throttle_hit($guard);
            throw new ValidationError($emailChanged && $new === '' ? 'Para cambiar el email escribe tu contraseña actual (en «Cambiar contraseña»)' : 'La contraseña actual no es correcta');
        }
    }
    if ($new !== '') {
        if (strlen($new) < 8) throw new ValidationError('La nueva contraseña necesita al menos 8 caracteres');
        $hash = pass_hash($new);
        q('UPDATE customers SET pass_hash = ? WHERE id = ?', [$hash, $c['id']]);
        audit('cambio_contrasena', (int)$c['id']);
        // Las demás sesiones abiertas de este cliente dejan de valer; esta sigue, con un identificador nuevo
        session_regenerate_id(true);
        $_SESSION['pv'] = pass_fingerprint($hash);
    }
    if ($emailChanged) audit('cambio_email', (int)$c['id']);
    if ($name !== $c['name'] || $phone !== $c['phone']) audit('cambio_datos', (int)$c['id']);
    q('UPDATE customers SET name = ?, email = ?, email_idx = ?, phone = ? WHERE id = ?', [enc($name), enc($email), email_idx($email), enc($phone), $c['id']]);
    return customer_out(customer_plain(q('SELECT * FROM customers WHERE id = ?', [$c['id']])->fetch()));
}

function customer_delete(array $c, array $in): void
{
    $row = q('SELECT pass_hash FROM customers WHERE id = ?', [$c['id']])->fetch();
    $guard = throttle_check('password-cliente|' . $c['id'], 5, 900);
    if (!pass_verify((string)($in['password'] ?? ''), $row['pass_hash'])) {
        throttle_hit($guard);
        throw new ValidationError('La contraseña no es correcta');
    }
    // Las reservas se quedan para el restaurante, pero dejan de estar unidas a la cuenta
    q('UPDATE reservations SET customer_id = 0 WHERE customer_id = ?', [$c['id']]);
    q('DELETE FROM customers WHERE id = ?', [$c['id']]);
    audit('cuenta_borrada', (int)$c['id']);
    customer_logout();
}

function can_cancel(array $r): bool
{
    if (!in_array($r['status'], RES_ACTIVE, true)) return false;
    $start = strtotime($r['date'] . ' ' . $r['time']);
    return $start > time();
}

function customer_reservations(array $c): array
{
    $rows = q('SELECT * FROM reservations WHERE customer_id = ? ORDER BY rdate DESC, rtime DESC', [$c['id']])->fetchAll();
    $now = date('Y-m-d H:i');
    $up = $past = [];
    foreach ($rows as $row) {
        $r = res_out($row);
        $r['can_cancel'] = can_cancel($r);
        $r['when'] = fmt_date_l($r['date']) . ', ' . $r['time'];
        if ($r['date'] . ' ' . $r['time'] >= $now && in_array($r['status'], RES_ACTIVE, true)) array_unshift($up, $r);
        else $past[] = $r;
    }
    return ['upcoming' => $up, 'past' => $past];
}

function customer_cancel(array $c, int $id): array
{
    $row = q('SELECT * FROM reservations WHERE id = ? AND customer_id = ?', [$id, $c['id']])->fetch();
    if (!$row) throw new ValidationError('No encontramos esa reserva en tu cuenta');
    $r = res_out($row);
    if (!can_cancel($r)) {
        $s = settings();
        throw new ValidationError($s['phone'] ? t('Esta reserva ya no se puede cancelar desde la web. Llámanos al %s.', $s['phone']) : t('Esta reserva ya no se puede cancelar desde la web. Llámanos.'));
    }
    q("UPDATE reservations SET status = 'cancelada', cancel_by = 'cliente', updated_at = ?, seq = ? WHERE id = ?", [now(), next_seq(), $id]);
    $r = get_reservation($id);
    $s = settings();
    restaurant_mail(
        "Reserva cancelada por el cliente: {$r['name']}, " . fmt_date_es($r['date']) . " {$r['time']}",
        "El cliente ha cancelado su reserva desde su cuenta.\n\nNombre: {$r['name']}\nDía: " . fmt_date_es($r['date']) . " a las {$r['time']}\nPersonas: {$r['people']}"
    );
    notify_customer($r);
    return $r;
}

/** Portabilidad (RGPD art. 20): todos los datos del cliente en un archivo JSON. */
function customer_export(array $c): array
{
    $res = array_map(function ($r) {
        $o = res_out($r);
        unset($o['id'], $o['source'], $o['customer']);
        return $o;
    }, q('SELECT * FROM reservations WHERE customer_id = ? ORDER BY rdate, rtime', [$c['id']])->fetchAll());
    $log = q('SELECT at, event, detail FROM audit_log WHERE customer_id = ? ORDER BY id', [$c['id']])->fetchAll();
    audit('exportacion_datos', (int)$c['id']);
    return [
        'generado' => date('c'),
        'responsable' => settings()['owner_name'] ?: settings()['name'],
        'cuenta' => ['nombre' => $c['name'], 'email' => $c['email'], 'telefono' => $c['phone'], 'alta' => $c['created_at']],
        'reservas' => $res,
        'registro_de_actividad' => $log,
    ];
}

/** El cliente con sesión confirma su reserva desde «Mi cuenta» (igual que con el enlace del WhatsApp). */
function customer_confirm(array $c, int $id): array
{
    $row = q('SELECT * FROM reservations WHERE id = ? AND customer_id = ?', [$id, $c['id']])->fetch();
    if (!$row || $row['status'] !== 'esperando') throw new ValidationError('Esta reserva no está pendiente de tu confirmación');
    [$state] = confirm_by_customer($id, dec($row['verify_token']));
    if ($state !== 'confirmada') throw new ValidationError('No se pudo confirmar la reserva');
    return get_reservation($id);
}
