<?php
declare(strict_types=1);
/*
 * Recuperar la contraseña del cliente. Sin enlace: el formulario para pedirlo por email. Con enlace (?t=…): el
 * formulario para elegir la nueva. Abrir el enlace no gasta nada (el correo abre los enlaces solo para la vista
 * previa): el token se gasta al guardar la contraseña (api c_reset). El diseño está en tema/recuperar.php.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer'); // el token no sale en el Referer de los enlaces de la página

$token = (string)($_GET['t'] ?? '');
$estado = $token === '' ? 'pedir' : (reset_find('customer', $token) ? 'nueva' : 'invalido'); // pedir, nueva, invalido
$vars = ['estado' => $estado, 'token' => $estado === 'nueva' ? $token : ''];

if (is_file(__DIR__ . '/tema/recuperar.php')) tema_render('recuperar', $vars);

// Temas que aún no tienen tema/recuperar.php: página sencilla con los estilos del SDK
$s = settings_tr();
?>
<!doctype html>
<html lang="<?= html_lang() ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h(t('Recuperar la contraseña') . ' · ' . $s['name']) ?></title><?php head_tags(); ?>
<style>body{font-family:system-ui,sans-serif;max-width:28rem;margin:3rem auto;padding:0 16px;line-height:1.5}label{display:block;margin:1rem 0}input{display:block;width:100%;padding:.6rem;margin-top:.3rem;box-sizing:border-box}button{padding:.7rem 1.2rem;margin-top:.5rem}</style></head>
<body>
<p><a href="./"><?= h($s['name']) ?></a></p>
<?php if ($estado === 'nueva'): ?>
  <h1><?= t('Elige una contraseña nueva') ?></h1>
  <form data-api="c_reset" novalidate>
    <input type="hidden" name="t" value="<?= h($token) ?>">
    <label><?= t('Nueva contraseña') ?> (<?= t('mínimo 8 caracteres') ?>)<input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
    <p data-error role="alert"></p>
    <button type="submit"><?= t('Guardar y entrar') ?></button>
  </form>
<?php else: ?>
  <h1><?= $estado === 'invalido' ? t('Este enlace ya no funciona') : t('Recuperar la contraseña') ?></h1>
  <p><?= $estado === 'invalido' ? t('Puede que ya lo hayas usado o que haya caducado. Puedes pedir otro aquí abajo.') : t('Escribe el email de tu cuenta y te enviaremos un enlace para elegir una contraseña nueva.') ?></p>
  <form data-api="c_forgot" novalidate>
    <label>Email <input name="email" type="email" autocomplete="email" maxlength="120" required></label>
    <p data-error role="alert"></p>
    <button type="submit"><?= t('Enviar el enlace') ?></button>
  </form>
  <p data-forgot-ok hidden role="status"><?= t('Si hay una cuenta con ese email, te hemos enviado un enlace. Caduca en 1 hora. Si no lo ves, mira en la carpeta de spam.') ?></p>
<?php endif; ?>
<?php body_end(); ?>
</body>
</html>
