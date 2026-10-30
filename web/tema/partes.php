<?php
/*
 * Piezas comunes del tema Base: abrir() pinta el <head> y la cabecera; cerrar(), el pie.
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
<link rel="preload" href="tema/assets/fonts/hanken-grotesk-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('tema.css') ?>">
</head>
<body class="<?= h($clase) ?>">
<a class="skip" href="#main"><?= t('Saltar al contenido') ?></a>
<header class="cab">
  <div class="cab-in">
    <a class="cab-marca" href="./"><?= h($s['name']) ?></a>
    <nav class="cab-nav" id="menu" aria-label="<?= t('Principal') ?>">
      <?php if (carta_on() || !menus_public()): ?><a href="./#carta"><?= t('Carta') ?></a><?php endif; ?>
      <?php if (menus_public()): ?><a href="./#menus"><?= t('Menús') ?></a><?php endif; ?>
      <a href="./#nosotros"><?= t('Nosotros') ?></a>
      <a href="./#visitanos"><?= t('Visítanos') ?></a>
      <a href="cuenta.php"><?= $c ? t('Mi cuenta') : t('Entrar') ?></a>
    </nav>
    <?= selector_idioma('cab-idiomas') ?>
    <a class="boton boton-acento cab-reservar" href="./#reservar" data-book><?= t('Reservar') ?></a>
    <button class="cab-menu" type="button" aria-expanded="false" aria-controls="menu" aria-label="<?= t('Menú') ?>" data-menu><span></span><span></span></button>
  </div>
</header>
    <?php
}

function cerrar(): void
{
    $s = settings();
    ?>
<footer class="pie" id="visitanos">
  <div class="cont pie-grid">
    <div>
      <p class="pie-marca"><?= h($s['name']) ?></p>
      <?php if ($s['address']): ?><p><?= h($s['address']) ?><?= $s['city'] ? '<br>' . h($s['city']) : '' ?><br><a href="<?= h(maps_href()) ?>" target="_blank" rel="noopener"><?= t('Cómo llegar') ?></a></p><?php endif; ?>
      <p>
        <?php if ($s['phone']): ?><a href="<?= h(tel_href($s['phone'])) ?>"><?= h($s['phone']) ?></a><br><?php endif; ?>
        <?php if ($s['email']): ?><a href="mailto:<?= h($s['email']) ?>"><?= h($s['email']) ?></a><br><?php endif; ?>
        <?php if (wa_href()): ?><a href="<?= h(wa_href()) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      </p>
      <?php if (redes()): ?><p class="pie-redes"><?php foreach (redes() as $n => $u): ?><a href="<?= h($u) ?>" target="_blank" rel="noopener"><?= $n ?></a><?php endforeach; ?></p><?php endif; ?>
    </div>
    <div>
      <h2><?= t('Horario') ?></h2>
      <ul class="horario">
        <?php foreach (horario() as $d): ?><li<?= $d['hoy'] ? ' class="hoy"' : '' ?>><span><?= $d['dia'] ?></span><span><?= h($d['texto']) ?><?php if ($d['cocina']): ?><small class="cocina"><?= t('Cocina') ?>: <?= h($d['cocina']) ?></small><?php endif; ?></span></li><?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h2><?= t('Información') ?></h2>
      <p class="pie-links"><a href="cuenta.php"><?= t('Mi cuenta') ?></a><a href="legal.php#politicas"><?= t('Políticas de reserva') ?></a><a href="legal.php#aviso"><?= t('Aviso legal') ?></a><a href="legal.php#privacidad"><?= t('Privacidad') ?></a><a href="borrar-datos.php"><?= t('Borrar mis datos') ?></a><a href="legal.php#cookies">Cookies</a><?= cookies_link() ?></p>
    </div>
  </div>
  <p class="cont pie-legal">© <?= date('Y') ?> <?= h($s['name']) ?>.<?php $cr = photo_credits(); if ($cr): ?> <?= t('Fotos de') ?> <?php $i = 0; foreach ($cr as $p): ?><?= $i++ ? ', ' : '' ?><a href="<?= h($p['url']) ?>" target="_blank" rel="noopener"><?= h($p['by']) ?></a><?php endforeach; ?> <?= t('en Unsplash') ?>.<?php endif; ?></p>
</footer>
<?php body_end(); ?>
<script src="<?= asset('tema.js') ?>" defer></script>
</body>
</html>
    <?php
}
