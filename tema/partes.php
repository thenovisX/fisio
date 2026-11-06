<?php
/*
 * Piezas comunes del tema Fibra: abrir() pinta el <head> y la cabecera; cerrar(), el pie.
 * Lo único obligatorio en cualquier tema: llamar a head_tags() dentro de <head> y a body_end() antes de </body>,
 * y tener en el pie los enlaces legales y cookies_link().
 */

function abrir(string $titulo, string $clase = ''): void
{
    $s = settings();
    $c = current_customer();
    ?>
<!doctype html>
<html lang="<?= html_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($titulo) ?></title>
<?php head_tags(); ?>
<link rel="preload" href="tema/assets/fonts/fraunces-latin-wght-italic.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="tema/assets/fonts/hanken-grotesk-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('tema.css') ?>">
</head>
<body class="<?= h($clase) ?>">
<a class="skip" href="#main"><?= t('Saltar al contenido') ?></a>
<div class="cursor" aria-hidden="true"><span></span></div>
<header class="cab">
  <a class="cab-marca" href="./" aria-label="<?= h($s['name']) ?>">
    <svg class="cab-signo" viewBox="0 0 40 40" aria-hidden="true"><path d="M6 30C12 8 18 8 20 20s8 12 14-10" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
    <span><?= h($s['name']) ?></span>
  </a>
  <nav class="cab-nav" id="menu" aria-label="<?= t('Principal') ?>">
    <a href="./#tratamientos"><?= t('Tratamientos') ?></a>
    <a href="./#centro"><?= t('El centro') ?></a>
    <a href="./#visitanos"><?= t('Visítanos') ?></a>
    <a href="cuenta.php"><?= $c ? t('Mi cuenta') : t('Entrar') ?></a>
  </nav>
  <a class="pildora cab-cita" href="./#reservar" data-book data-magnet><?= t('Pedir cita') ?></a>
  <button class="cab-menu" type="button" aria-expanded="false" aria-controls="menu" aria-label="<?= t('Menú') ?>" data-menu><span></span><span></span></button>
</header>
    <?php
}

function cerrar(): void
{
    $s = settings();
    ?>
<footer class="pie">
  <div class="pie-cta">
    <p class="pie-pregunta" data-split><?= h($s['footer_title'] ?: t('¿Empezamos?')) ?></p>
    <a class="pie-boton" href="#reservar" data-book data-magnet><span><?= t('Pedir cita') ?></span></a>
  </div>
  <div class="pie-datos">
    <div>
      <p class="pie-marca"><?= h($s['name']) ?></p>
      <?php if ($s['address']): ?><p><?= h($s['address']) ?><?= $s['city'] ? '<br>' . h($s['city']) : '' ?></p><?php endif; ?>
      <?php if (redes()): ?><p class="pie-redes"><?php foreach (redes() as $n => $u): ?><a href="<?= h($u) ?>" target="_blank" rel="noopener"><?= $n ?></a><?php endforeach; ?></p><?php endif; ?>
    </div>
    <p class="pie-links"><a href="cuenta.php"><?= t('Mi cuenta') ?></a><a href="legal.php#politicas"><?= t('Normas del centro') ?></a><a href="legal.php#aviso"><?= t('Aviso legal') ?></a><a href="legal.php#privacidad"><?= t('Privacidad') ?></a><a href="borrar-datos.php"><?= t('Borrar mis datos') ?></a><a href="legal.php#cookies">Cookies</a><?= cookies_link() ?></p>
  </div>
  <?= selector_idioma('pie-idiomas') ?>
  <p class="pie-legal">© <?= date('Y') ?> <?= h($s['name']) ?><?= $s['owner_name'] && $s['owner_name'] !== $s['name'] ? ' · ' . h($s['owner_name']) : '' ?></p>
</footer>
<?php body_end(); ?>
<script src="tema/assets/vendor/gsap.min.js?v=3.12.5" defer></script>
<script src="tema/assets/vendor/ScrollTrigger.min.js?v=3.12.5" defer></script>
<script src="tema/assets/vendor/lenis.min.js?v=1.1.20" defer></script>
<script src="tema/assets/vendor/split-type.min.js?v=0.3.4" defer></script>
<script src="<?= asset('tema.js') ?>" defer></script>
</body>
</html>
    <?php
}

/** Fibras de colores pintadas con SVG: imagen de reserva cuando el centro aún no ha subido su foto. */
function fibras_svg(string $clase = ''): string
{
    $p = '';
    for ($i = 0; $i < 18; $i++) {
        $y = 20 + $i * 22;
        $a = 60 + ($i % 5) * 18;
        $p .= '<path d="M-20 ' . $y . ' C 120 ' . ($y - $a) . ', 260 ' . ($y + $a) . ', 420 ' . ($y - $a / 2) . ' S 640 ' . ($y + $a) . ', 820 ' . $y . '" />';
    }
    return '<svg class="fibras-svg ' . h($clase) . '" viewBox="0 0 800 440" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><g fill="none" stroke-width="1.4">' . $p . '</g></svg>';
}
