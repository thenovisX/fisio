<?php
declare(strict_types=1);

/*
 * Recuperar la contraseña por email, para clientes de la web y usuarios del panel.
 * El enlace lleva un token aleatorio de un solo uso que caduca en RESET_MINUTES. En la base de datos solo se guarda
 * su huella (sha256). Pedir uno nuevo anula los anteriores, y usarlo cierra las sesiones abiertas del panel.
 * La respuesta es la misma exista o no la cuenta, para no revelar qué emails o usuarios hay.
 */

const RESET_MINUTES = 60;

/** Límite por IP: 5 peticiones cada 15 minutos (cada una envía un email). */
function reset_guard(string $kind): void
{
    throttle_hit(throttle_check('reset-' . $kind . '|' . client_ip(), 5, 900));
}

/** Crea el token (anulando los anteriores de esa cuenta). Devuelve '' si se pidió otro hace menos de 2 minutos. */
function reset_create(string $kind, int $subject): string
{
    q('DELETE FROM password_resets WHERE expires_at < ? OR used_at IS NOT NULL', [now()]);
    $recent = q('SELECT COUNT(*) FROM password_resets WHERE kind = ? AND subject_id = ? AND created_at > ?',
        [$kind, $subject, date('Y-m-d H:i:s', time() - 120)])->fetchColumn();
    if ((int)$recent) return '';
    q('DELETE FROM password_resets WHERE kind = ? AND subject_id = ?', [$kind, $subject]);
    $token = bin2hex(random_bytes(32));
    q('INSERT INTO password_resets (kind, subject_id, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?)',
        [$kind, $subject, hash('sha256', $token), now(), date('Y-m-d H:i:s', time() + RESET_MINUTES * 60)]);
    return $token;
}

/** Fila del token si sigue valiendo (sin gastarlo). */
function reset_find(string $kind, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $row = q('SELECT * FROM password_resets WHERE token_hash = ? AND kind = ?', [hash('sha256', $token), $kind])->fetch();
    if (!$row || $row['used_at'] !== null || strtotime((string)$row['expires_at']) < time()) return null;
    return $row;
}

/** Gasta el token. Solo una petición puede hacerlo aunque lleguen dos a la vez. */
function reset_use(string $kind, string $token): int
{
    $row = reset_find($kind, $token);
    if (!$row) throw new ValidationError('El enlace para cambiar la contraseña no es válido o ha caducado. Pide otro.');
    if (q('UPDATE password_resets SET used_at = ? WHERE id = ? AND used_at IS NULL', [now(), $row['id']])->rowCount() !== 1) {
        throw new ValidationError('El enlace para cambiar la contraseña no es válido o ha caducado. Pide otro.');
    }
    q('DELETE FROM password_resets WHERE kind = ? AND subject_id = ?', [$kind, $row['subject_id']]);
    return (int)$row['subject_id'];
}

function reset_password_ok(string $pass): void
{
    if (strlen($pass) < 8) throw new ValidationError('La contraseña necesita al menos 8 caracteres');
}

/* ---------- Clientes de la web ---------- */

function customer_forgot(array $in): void
{
    $email = mb_strtolower(str_in($in, 'email', 120, true, 'email'));
    reset_guard('c');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return;
    $c = q('SELECT * FROM customers WHERE email_idx = ?', [email_idx($email)])->fetch();
    if (!$c) return;
    $name = dec($c['name']);
    // Si la cuenta aún no está activada, lo útil es el enlace de activación
    if (!$c['verified_at']) { send_activation((int)$c['id'], $email, $name); return; }
    $token = reset_create('customer', (int)$c['id']);
    if ($token === '') return;
    $url = base_url() . '/recuperar.php?t=' . $token . (lang() !== 'es' ? '&lang=' . lang() : '');
    $s = settings();
    send_mail($email, t('Cambia tu contraseña en %s', $s['name']),
        t('Hola %s:', $name) . "\n\n" . t('Para elegir una contraseña nueva abre este enlace (caduca en 1 hora y solo sirve una vez):') . "\n\n$url\n\n"
        . t('Si no lo has pedido tú, ignora este email: tu contraseña sigue igual.'));
    audit('recuperar_pedido', (int)$c['id']);
    dev_log("enlace para cambiar la contraseña: $url");
}

/** Guarda la contraseña nueva del cliente y le deja dentro de su cuenta. */
function customer_reset(array $in): array
{
    $pass = (string)($in['password'] ?? '');
    reset_password_ok($pass);
    $id = reset_use('customer', (string)($in['t'] ?? ''));
    $c = q('SELECT * FROM customers WHERE id = ?', [$id])->fetch();
    if (!$c) throw new ValidationError('El enlace para cambiar la contraseña no es válido o ha caducado. Pide otro.');
    q('UPDATE customers SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $id]);
    audit('recuperar_cambiada', $id);
    customer_login_session($id);
    return customer_out(customer_plain($c));
}

/* ---------- Usuarios del panel ---------- */

/** Email al que llega el enlace: el del usuario y, si es administrador y no tiene, el email del restaurante. */
function user_reset_email(array $u): string
{
    $mail = isset($u['email']) && $u['email'] !== '' ? dec($u['email']) : '';
    if ($mail === '' && ($u['role'] ?? 'admin') === 'admin') {
        $s = settings();
        $mail = $s['notify_email'] ?: $s['email'];
    }
    return filter_var($mail, FILTER_VALIDATE_EMAIL) ? $mail : '';
}

/** Petición desde la pantalla de entrada del panel: vale el usuario o su email. */
function user_forgot(array $in): void
{
    $who = mb_strtolower(str_in($in, 'username', 120, true, 'usuario'));
    reset_guard('u');
    $u = q('SELECT * FROM users WHERE username = ?', [$who])->fetch();
    if (!$u && filter_var($who, FILTER_VALIDATE_EMAIL)) $u = q('SELECT * FROM users WHERE email_idx = ?', [email_idx($who)])->fetch();
    if (!$u || !(int)($u['active'] ?? 1)) return;
    $mail = user_reset_email($u);
    if ($mail === '') return;
    $token = reset_create('user', (int)$u['id']);
    if ($token === '') return;
    $url = base_url() . '/panel/#reset=' . $token;
    with_lang('es', fn() => send_mail($mail, 'Cambia la contraseña de la app de ' . settings()['name'],
        "Hola:\n\nSe ha pedido cambiar la contraseña del usuario «{$u['username']}» en la app de gestión. Para elegir una nueva abre este enlace (caduca en 1 hora y solo sirve una vez):\n\n$url\n\n"
        . 'Si no lo has pedido tú, ignora este email: la contraseña sigue igual.'));
    audit('panel_recuperar_pedido', 0, 'Usuario del panel: ' . $u['username']);
    dev_log("enlace para cambiar la contraseña del panel: $url");
}

/** Comprueba el enlace antes de enseñar el formulario (no lo gasta). */
function user_reset_check(array $in): array
{
    $row = reset_find('user', (string)($in['t'] ?? ''));
    $u = $row ? q('SELECT username FROM users WHERE id = ?', [$row['subject_id']])->fetch() : null;
    if (!$u) throw new ValidationError('El enlace para cambiar la contraseña no es válido o ha caducado. Pide otro.');
    return ['username' => $u['username']];
}

function user_reset(array $in): array
{
    $pass = (string)($in['password'] ?? '');
    reset_password_ok($pass);
    $id = reset_use('user', (string)($in['t'] ?? ''));
    $u = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
    if (!$u) throw new ValidationError('El enlace para cambiar la contraseña no es válido o ha caducado. Pide otro.');
    q('UPDATE users SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $id]);
    // Quien tuviera la sesión abierta con la contraseña vieja tiene que volver a entrar
    q('DELETE FROM tokens WHERE user_id = ?', [$id]);
    audit('panel_recuperar_cambiada', 0, 'Usuario del panel: ' . $u['username']);
    return ['username' => $u['username']];
}

/** Email del propio usuario para recuperar la contraseña (vacío lo quita). */
function user_set_email(array $user, array $in): array
{
    $email = mb_strtolower(str_in($in, 'email', 120));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    user_store_email((int)$user['id'], $email);
    return user_out(q('SELECT * FROM users WHERE id = ?', [$user['id']])->fetch());
}

function user_store_email(int $id, string $email): void
{
    if ($email !== '' && q('SELECT id FROM users WHERE email_idx = ? AND id <> ?', [email_idx($email), $id])->fetch()) {
        throw new ValidationError('Ese email ya lo usa otro usuario');
    }
    q('UPDATE users SET email = ?, email_idx = ? WHERE id = ?', [$email === '' ? '' : enc($email), $email === '' ? '' : email_idx($email), $id]);
}
