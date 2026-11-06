<?php
declare(strict_types=1);

const TOKEN_DAYS = 90;      // la sesión del panel caduca si no se usa en este tiempo…
const TOKEN_MAX_DAYS = 180; // …y, aunque se use, como mucho dura esto (luego hay que volver a entrar)
/** Permisos de los empleados. Ver las reservas y la carta lo pueden hacer todos. */
const PERMS = [
    'reservas'  => 'Gestionar citas: aceptar, rechazar, apuntar, editar y borrar',
    'pacientes' => 'Ver y editar las fichas de pacientes, notas de sesión y bonos',
    'carta'     => 'Cambiar los tratamientos y precios',
    'ajustes'   => 'Cambiar los ajustes de la web',
];

function user_perms(array $u): array
{
    if (($u['role'] ?? 'admin') === 'admin') return array_keys(PERMS);
    return array_values(array_intersect(array_keys(PERMS), array_filter(explode(',', (string)($u['perms'] ?? '')))));
}

function user_can(array $u, string $perm): bool
{
    return in_array($perm, user_perms($u), true);
}

/** Corta la petición si el usuario no tiene ese permiso. */
function require_perm(array $u, string $perm): void
{
    if (!user_can($u, $perm)) fail('No tienes permiso para hacer esto. Pídeselo al administrador.', 403);
}

function require_admin(array $u): void
{
    if (($u['role'] ?? '') !== 'admin') fail('Solo un administrador puede hacer esto.', 403);
}

function user_out(array $u): array
{
    return ['id' => (int)$u['id'], 'username' => $u['username'], 'name' => (string)($u['name'] ?? ''), 'role' => $u['role'] ?? 'admin',
        'perms' => user_perms($u), 'active' => (bool)($u['active'] ?? 1), 'created_at' => $u['created_at'] ?? '',
        'email' => ($u['email'] ?? '') !== '' ? dec($u['email']) : ''];
}

function users_list(): array
{
    return array_map('user_out', q('SELECT * FROM users ORDER BY role, username')->fetchAll());
}

function active_admins(int $exceptId = 0): int
{
    return (int)q("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?", [$exceptId])->fetchColumn();
}

/** Crear o editar un usuario del panel (solo administradores). */
function user_save(array $me, array $in): array
{
    $id = (int)($in['id'] ?? 0);
    $username = mb_strtolower(trim((string)($in['username'] ?? '')));
    if (!preg_match('/^[a-z0-9._-]{3,40}$/', $username)) throw new ValidationError('El usuario tiene que tener de 3 a 40 letras sin espacios (puede llevar números, puntos y guiones)');
    $name = mb_substr(trim((string)($in['name'] ?? '')), 0, 80);
    $role = ($in['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';
    $perms = implode(',', array_intersect(array_keys(PERMS), (array)($in['perms'] ?? [])));
    $active = filter_var($in['active'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $pass = (string)($in['password'] ?? '');
    $email = mb_strtolower(str_in($in, 'email', 120));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    if ($pass !== '' && strlen($pass) < 8) throw new ValidationError('La contraseña necesita al menos 8 caracteres');
    if (q('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])->fetch()) throw new ValidationError('Ya hay un usuario con ese nombre');
    if ($email !== '' && q('SELECT id FROM users WHERE email_idx = ? AND id <> ?', [email_idx($email), $id])->fetch()) throw new ValidationError('Ese email ya lo usa otro usuario');

    if ($id) {
        $old = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
        if (!$old) throw new ValidationError('El usuario no existe');
        if ($old['role'] === 'admin' && ($role !== 'admin' || !$active) && !active_admins($id)) {
            throw new ValidationError('Tiene que quedar al menos un administrador activo');
        }
        q('UPDATE users SET username = ?, name = ?, role = ?, perms = ?, active = ? WHERE id = ?', [$username, $name, $role, $perms, $active, $id]);
        if ($pass !== '') q('UPDATE users SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $id]);
        // Si se desactiva o se le cambia la contraseña, se cierran sus sesiones (salvo la tuya)
        if (!$active || ($pass !== '' && $id !== (int)$me['id'])) q('DELETE FROM tokens WHERE user_id = ?', [$id]);
        audit('usuario_editado', 0, "Usuario del panel «{$username}» editado por {$me['username']}");
    } else {
        if ($pass === '') throw new ValidationError('Pon una contraseña para el usuario nuevo');
        q('INSERT INTO users (username, pass_hash, created_at, name, role, perms, active) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$username, pass_hash($pass), now(), $name, $role, $perms, $active]);
        $id = (int)db()->lastInsertId();
        audit('usuario_creado', 0, "Usuario del panel «{$username}» creado por {$me['username']}");
    }
    if (array_key_exists('email', $in)) user_store_email($id, $email);
    return user_out(q('SELECT * FROM users WHERE id = ?', [$id])->fetch());
}

function user_delete(array $me, int $id): void
{
    if ($id === (int)$me['id']) throw new ValidationError('No puedes borrar tu propio usuario');
    $u = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
    if (!$u) return;
    if ($u['role'] === 'admin' && !active_admins($id)) throw new ValidationError('Tiene que quedar al menos un administrador activo');
    q('DELETE FROM tokens WHERE user_id = ?', [$id]);
    q('DELETE FROM users WHERE id = ?', [$id]);
    audit('usuario_borrado', 0, "Usuario del panel «{$u['username']}» borrado por {$me['username']}");
}

function login(array $in): array
{
    $user = str_in($in, 'username', 60, true, 'usuario');
    $pass = (string)($in['password'] ?? '');

    // Fuerza bruta: 10 fallos por IP y 20 por usuario (aunque vengan de muchas IP) cada 15 minutos
    $byIp = throttle_check('login-ip|' . client_ip(), 10, 900);
    $byUser = throttle_check('login-user|' . mb_strtolower($user), 20, 900);

    $u = q('SELECT * FROM users WHERE username = ?', [$user])->fetch();
    if (!pass_verify($pass, $u ? $u['pass_hash'] : null)) {
        throttle_hit($byIp);
        throttle_hit($byUser);
        throw new ValidationError('Usuario o contraseña incorrectos');
    }
    if (isset($u['active']) && !(int)$u['active']) throw new ValidationError('Este usuario está desactivado. Habla con el administrador.');
    throttle_clear($byIp);
    throttle_clear($byUser);
    if (pass_needs_rehash($u['pass_hash'])) q('UPDATE users SET pass_hash = ? WHERE id = ?', [pass_hash($pass), $u['id']]);
    q('DELETE FROM tokens WHERE last_used < ? OR created_at < ?', [date('Y-m-d H:i:s', time() - TOKEN_DAYS * 86400), date('Y-m-d H:i:s', time() - TOKEN_MAX_DAYS * 86400)]);

    $token = bin2hex(random_bytes(32));
    q('INSERT INTO tokens (token, user_id, created_at, last_used) VALUES (?, ?, ?, ?)', [hash('sha256', $token), $u['id'], now(), now()]);
    return ['token' => $token, 'user' => $u['username'], 'restaurant' => settings()['name'], 'me' => user_out($u)];
}

function request_token(): string
{
    $t = $_SERVER['HTTP_X_TOKEN'] ?? '';
    if ($t === '' && preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'] ?? '', $m)) $t = $m[1];
    return preg_match('/^[a-f0-9]{64}$/', $t) ? $t : '';
}

function require_auth(): array
{
    $t = request_token();
    if ($t === '') fail('Inicia sesión', 401);
    $hash = hash('sha256', $t);
    $u = q('SELECT u.*, t.last_used, t.created_at AS token_created FROM tokens t JOIN users u ON u.id = t.user_id WHERE t.token = ?', [$hash])->fetch();
    if (!$u || strtotime($u['last_used']) < time() - TOKEN_DAYS * 86400 || strtotime($u['token_created']) < time() - TOKEN_MAX_DAYS * 86400) {
        if ($u) q('DELETE FROM tokens WHERE token = ?', [$hash]);
        fail('La sesión ha caducado. Vuelve a entrar.', 401);
    }
    if (isset($u['active']) && !(int)$u['active']) fail('Este usuario está desactivado. Habla con el administrador.', 401);
    unset($u['pass_hash']);
    if (strtotime($u['last_used']) < time() - 300) q('UPDATE tokens SET last_used = ? WHERE token = ?', [now(), $hash]);
    return $u;
}

function logout(): void
{
    $t = request_token();
    if ($t) q('DELETE FROM tokens WHERE token = ?', [hash('sha256', $t)]);
}

function change_password(array $user, array $in): void
{
    $cur = (string)($in['current'] ?? '');
    $new = (string)($in['new'] ?? '');
    $u = q('SELECT * FROM users WHERE id = ?', [$user['id']])->fetch();
    $guard = throttle_check('password-user|' . $user['id'], 5, 900);
    if (!pass_verify($cur, $u['pass_hash'])) {
        throttle_hit($guard);
        throw new ValidationError('La contraseña actual no es correcta');
    }
    if (strlen($new) < 8) throw new ValidationError('La nueva contraseña necesita al menos 8 caracteres');
    q('UPDATE users SET pass_hash = ? WHERE id = ?', [pass_hash($new), $user['id']]);
    audit('admin_cambio_contrasena', 0, 'Usuario del panel: ' . $user['username']);
    // Cierra las demás sesiones
    q('DELETE FROM tokens WHERE user_id = ? AND token <> ?', [$user['id'], hash('sha256', request_token())]);
}
