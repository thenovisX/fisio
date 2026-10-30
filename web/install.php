<?php
declare(strict_types=1);

/*
 * Instalador: se abre una sola vez en el navegador (tudominio.com/install.php).
 * Crea config.php, las tablas, el usuario de la app y una carta de ejemplo.
 * Cuando termine, BORRA este archivo desde el administrador de archivos de Hostinger.
 */

require __DIR__ . '/lib/core.php';
require __DIR__ . '/lib/cifrado.php';
require __DIR__ . '/lib/schema.php';
date_default_timezone_set('Europe/Madrid');

$done = false;
$error = '';
$configText = '';
// Con config.php ya creado no se puede volver a instalar, aunque la base de datos falle en ese momento:
// si no, cualquiera podría reinstalar encima, crear su propio administrador y cambiar la clave de cifrado.
$already = is_file(__DIR__ . '/config.php');

if (!$already && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $f = fn($k) => trim((string)($_POST[$k] ?? ''));
    try {
        $driver = $f('db_driver') === 'sqlite' ? 'sqlite' : 'mysql';
        $c = [
            'db_driver' => $driver,
            'db_host'   => $f('db_host') ?: 'localhost',
            'db_name'   => $f('db_name'),
            'db_user'   => $f('db_user'),
            'db_pass'   => (string)($_POST['db_pass'] ?? ''),
            'db_path'   => __DIR__ . '/data/fisio.sqlite',
            'timezone'  => 'Europe/Madrid',
            'secret'    => bin2hex(random_bytes(32)),
            // Dirección fija de la web: los enlaces de los emails no dependen de la cabecera Host
            'base_url'  => base_url(),
            // Peticiones por minuto y por IP a la API (0 = sin límite)
            'rate_limit' => 240,
            'debug'     => false,
        ];
        if ($driver === 'mysql' && ($c['db_name'] === '' || $c['db_user'] === '')) throw new RuntimeException('Rellena los datos de la base de datos.');
        $user = $f('admin_user');
        $pass = (string)($_POST['admin_pass'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,60}$/', $user)) throw new RuntimeException('El usuario solo puede llevar letras, números, punto o guion (mínimo 3).');
        if (strlen($pass) < 8) throw new RuntimeException('La contraseña de la app necesita al menos 8 caracteres.');

        if ($driver === 'sqlite' && !is_dir(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0755, true);
        try {
            $pdo = make_pdo($c);
        } catch (PDOException $e) {
            throw new RuntimeException('No se pudo conectar a la base de datos. Revisa nombre, usuario y contraseña. (' . $e->getMessage() . ')');
        }
        foreach (schema_sql($driver === 'sqlite') as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { if (!str_starts_with($sql, 'CREATE INDEX')) throw $e; }
        }

        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM users WHERE username = ?')->execute([$user]);
        $pdo->prepare('INSERT INTO users (username, pass_hash, created_at) VALUES (?, ?, ?)')
            ->execute([$user, pass_hash($pass), date('Y-m-d H:i:s')]);
        $set = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?)');
        $pdo->exec('DELETE FROM settings');
        $initial = SETTINGS_DEFAULTS;
        $initial['name'] = $f('name') ?: 'Mi centro de fisioterapia';
        $initial['email'] = $f('email');
        $initial['notify_email'] = $f('email');
        foreach ($initial as $k => $v) $set->execute([$k, json_encode($v, JSON_UNESCAPED_UNICODE)]);
        $set->execute(['_schema', (string)SCHEMA_VERSION]);

        if (!empty($_POST['sample']) && !(int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn()) {
            $ci = $pdo->prepare('INSERT INTO categories (name, position) VALUES (?, ?)');
            $di = $pdo->prepare('INSERT INTO dishes (category_id, name, description, price, allergens, photo, available, featured, position, duration) VALUES (?,?,?,?,?,?,1,?,?,?)');
            $cp = 0;
            foreach (sample_menu() as $cat => $dishes) {
                $ci->execute([$cat, ++$cp]);
                $cid = (int)$pdo->lastInsertId();
                foreach ($dishes as $i => [$n, $d, $p, $a, $feat, $min]) $di->execute([$cid, $n, $d, $p, $a, '', $feat, $i + 1, $min]);
            }
        }
        $pdo->commit();

        $configText = "<?php\nreturn " . var_export($c, true) . ";\n";
        if (@file_put_contents(__DIR__ . '/config.php', $configText) === false) {
            $error = 'Todo creado, pero no se pudo guardar config.php. Créalo tú a mano con el contenido de abajo.';
        } else {
            @chmod(__DIR__ . '/config.php', 0640);
            $configText = '';
        }
        $done = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalar la web del centro de fisioterapia</title>
<style>
  body { font: 16px/1.5 system-ui, sans-serif; background: #F2F4F3; color: #14313F; margin: 0; padding: 24px 16px; }
  main { max-width: 560px; margin: 0 auto; background: #fff; padding: 28px; border-radius: 14px; border: 1px solid #D6DBD8; }
  h1 { font-size: 1.5rem; margin: 0 0 8px; }
  fieldset { border: 1px solid #D6DBD8; border-radius: 10px; margin: 20px 0; padding: 12px 16px 16px; }
  legend { font-weight: 600; padding: 0 6px; }
  label { display: block; margin-top: 12px; font-size: .92rem; }
  input, select { width: 100%; box-sizing: border-box; font: inherit; padding: 10px 12px; border: 1px solid #B9C1BD; border-radius: 8px; margin-top: 4px; }
  input[type=checkbox] { width: auto; margin-right: 8px; }
  button { font: inherit; font-weight: 600; background: #B3362B; color: #fff; border: 0; border-radius: 999px; padding: 12px 24px; cursor: pointer; }
  .err { background: #FBEAE8; color: #8A2219; padding: 12px; border-radius: 8px; }
  .ok { background: #E7F1EC; color: #1F4A3C; padding: 12px; border-radius: 8px; }
  .hint { color: #5E6468; font-size: .88rem; }
  pre { background: #14313F; color: #fff; padding: 12px; border-radius: 8px; overflow: auto; font-size: .8rem; }
</style>
</head>
<body>
<main>
<?php if ($already): ?>
  <h1>La web ya está instalada</h1>
  <p>Por seguridad, <strong>borra el archivo <code>install.php</code></strong> desde el administrador de archivos de Hostinger.</p>
  <p class="hint">Para instalarla de nuevo desde cero, borra antes <code>config.php</code> (sin él no se pueden leer los datos cifrados).</p>
  <p><a href="./">Ver la web</a> · <a href="panel/">Abrir el panel</a></p>
<?php elseif ($done): ?>
  <h1>Instalación terminada</h1>
  <?php if ($error): ?><p class="err"><?= h($error) ?></p><pre><?= h($configText) ?></pre><?php endif; ?>
  <p class="ok">Ya puedes entrar en la app con el usuario <strong><?= h($_POST['admin_user'] ?? '') ?></strong>.</p>
  <p>Dirección de la web para configurar la app: <strong><?= h(base_url()) ?></strong></p>
  <p><strong>Ahora borra el archivo <code>install.php</code></strong> desde el administrador de archivos de Hostinger.</p>
  <p><a href="./">Ver la web</a> · <a href="panel/">Abrir el panel</a></p>
<?php else: ?>
  <h1>Instalar la web del centro de fisioterapia</h1>
  <p class="hint">Con SQLite no tienes que crear nada: los datos se guardan en un archivo protegido dentro de la web. Si prefieres MySQL, créala antes en hPanel → Bases de datos y copia aquí sus datos.</p>
  <?php if ($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?>
  <form method="post">
    <fieldset>
      <legend>Base de datos</legend>
      <label>Tipo
        <select name="db_driver">
          <option value="sqlite">SQLite: sin configurar nada (recomendado)</option>
          <option value="mysql" <?= ($_POST['db_driver'] ?? '') === 'mysql' ? 'selected' : '' ?>>MySQL (base de datos creada en hPanel)</option>
        </select>
      </label>
      <label>Servidor <input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>"></label>
      <label>Nombre de la base de datos <input name="db_name" placeholder="u123456789_fisio" value="<?= h($_POST['db_name'] ?? '') ?>"></label>
      <label>Usuario <input name="db_user" placeholder="u123456789_admin" value="<?= h($_POST['db_user'] ?? '') ?>"></label>
      <label>Contraseña <input name="db_pass" type="password"></label>
    </fieldset>
    <fieldset>
      <legend>Centro</legend>
      <label>Nombre <input name="name" required placeholder="Fisio Arena" value="<?= h($_POST['name'] ?? '') ?>"></label>
      <label>Email donde avisar de las citas <input name="email" type="email" value="<?= h($_POST['email'] ?? '') ?>"></label>
      <label><input type="checkbox" name="sample" value="1" checked>Cargar tratamientos de ejemplo (se editan luego desde la app)</label>
    </fieldset>
    <fieldset>
      <legend>Acceso a la app</legend>
      <label>Usuario <input name="admin_user" required autocomplete="username" value="<?= h($_POST['admin_user'] ?? 'admin') ?>"></label>
      <label>Contraseña (mínimo 8 caracteres) <input name="admin_pass" type="password" required minlength="8" autocomplete="new-password"></label>
    </fieldset>
    <button type="submit">Instalar</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
