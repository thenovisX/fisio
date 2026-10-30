<?php
/*
 * Portada del tema Fibra. Variables: $s (ajustes), $cliente (o null), $carta (categorías con tratamientos),
 * $menus (bonos o packs con precio cerrado; puede estar vacía), $carta_on, $menus_button.
 * Cada tratamiento lleva su botón con data-book="ID": abre la ventana de cita con ese tratamiento elegido.
 * La animación (tema.js) solo añade movimiento: sin JavaScript todo se ve y se puede reservar igual.
 */
require __DIR__ . '/partes.php';
$tratamientos = [];
foreach ($carta['categories'] as $cat) foreach ($cat['dishes'] as $d) $tratamientos[] = $d + ['cat' => $cat['name']];
$hoy = horario_hoy();
$cerradoHoy = in_array($hoy, [t('Cerrado'), t('Cerrado hoy')], true);
$cinta = array_values(array_filter(array_map('trim', explode(',', $s['ribbon'] ?: $s['tagline']))));
$manifiesto = $s['description'] ?: t('Escuchamos tu historia, exploramos cómo te mueves y te explicamos qué te pasa. Después trabajamos juntos, con las manos y con ejercicio, hasta que vuelvas a hacer lo que te gusta sin pensar en el dolor.');
abrir($s['name'] . ($s['tagline'] ? ' · ' . $s['tagline'] : ''), 'inicio');
?>
<canvas class="fibras" id="fibras" aria-hidden="true"></canvas>
<?= fibras_svg('fibras-fallback') ?>

<main id="main">
  <section class="hero" data-hero>
    <p class="hero-kicker"><span><?= t('Fisioterapia') ?></span><?php if ($s['city']): ?><span><?= h($s['city']) ?></span><?php endif; ?><span class="hero-hoy"><?= h($cerradoHoy ? t('Hoy cerrado') : t('Hoy') . ' ' . $hoy) ?></span></p>
    <h1 class="hero-t" data-hero-title>
      <span class="hero-l1"><?= t('Vuelve a') ?></span>
      <em class="hero-l2"><?= t('moverte') ?></em>
    </h1>
    <p class="hero-frase"><?= h($s['tagline'] ?: $s['name']) ?></p>
    <a class="sello" href="#reservar" data-book data-magnet aria-label="<?= t('Pedir cita') ?>">
      <svg viewBox="0 0 200 200" aria-hidden="true"><defs><path id="sello-c" d="M100,100 m-74,0 a74,74 0 1,1 148,0 a74,74 0 1,1 -148,0"/></defs><text><textPath href="#sello-c"><?= h(t('Pide tu cita · en un minuto ·')) ?> <?= h(t('Pide tu cita · en un minuto ·')) ?></textPath></text></svg>
      <span class="sello-flecha">↗</span>
    </a>
    <p class="hero-scroll" aria-hidden="true"><span></span><?= t('Desliza') ?></p>
  </section>

  <?php if ($cinta): ?>
  <section class="cinta" aria-label="<?= t('Especialidades') ?>">
    <div class="cinta-pista" data-cinta>
      <?php for ($k = 0; $k < 3; $k++): ?><p<?= $k ? ' aria-hidden="true"' : '' ?>><?php foreach ($cinta as $c): ?><span><?= h($c) ?></span><i>✳</i><?php endforeach; ?></p><?php endfor; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="manifiesto" id="centro">
    <p class="etiqueta"><?= t('Cómo trabajamos') ?></p>
    <p class="manifiesto-t" data-words><?= h($manifiesto) ?></p>
    <div class="manifiesto-fotos">
      <figure class="foto-flota foto-a" data-depth="0.25"><?= photo('centro') ? photo_img('centro', 1000, '(max-width: 760px) 80vw, 34vw', 'loading="lazy" data-zoom') : fibras_svg('foto-vacia') ?><figcaption><?= t('El centro') ?></figcaption></figure>
      <figure class="foto-flota foto-b" data-depth="-0.18"><?= photo('equipo') ? photo_img('equipo', 800, '(max-width: 760px) 60vw, 24vw', 'loading="lazy" data-zoom') : fibras_svg('foto-vacia foto-vacia-b') ?><figcaption><?= t('El equipo') ?></figcaption></figure>
    </div>
  </section>

  <section class="trat" id="tratamientos" aria-labelledby="trat-t">
    <div class="trat-cab">
      <p class="etiqueta"><?= t('Tratamientos') ?></p>
      <h2 class="trat-t" id="trat-t" data-split><?= t('Elige y reserva') ?></h2>
      <p class="trat-sub"><?= t('Cada tratamiento con su duración y su precio. Toca uno y elige la hora: así de fácil.') ?></p>
    </div>
    <?php if ($tratamientos): ?>
    <div class="trat-pista" data-pista>
      <?php foreach ($tratamientos as $i => $d): ?>
      <article class="tarjeta<?= $d['featured'] ? ' es-destacado' : '' ?>" style="--i: <?= $i ?>">
        <p class="tarjeta-n"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?><span><?= h($d['cat']) ?></span></p>
        <?php if ($d['photo']): ?><img class="tarjeta-foto" src="<?= h($d['photo']) ?>" alt="" loading="lazy"><?php endif; ?>
        <h3><?= h($d['name']) ?></h3>
        <?php if ($d['description'] !== ''): ?><p class="tarjeta-desc"><?= h($d['description']) ?></p><?php endif; ?>
        <p class="tarjeta-datos"><span><?= (int)$d['duration'] ?> min</span><?php if ($d['price'] > 0): ?><span><?= precio($d['price']) ?></span><?php endif; ?></p>
        <a class="tarjeta-boton" href="#reservar" data-book="<?= (int)$d['id'] ?>"><?= t('Reservar') ?> <span aria-hidden="true">→</span></a>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
      <p class="trat-sub"><?= t('Los tratamientos aparecerán aquí en cuanto se añadan desde la app.') ?></p>
    <?php endif; ?>
  </section>

  <section class="pasos" aria-labelledby="pasos-t" data-pasos>
    <h2 class="visually-hidden" id="pasos-t"><?= t('Pedir cita en tres pasos') ?></h2>
    <div class="paso" data-paso><b>1</b><p><?= t('Elige el tratamiento') ?></p><small><?= t('Si no sabes cuál, pide una primera valoración.') ?></small></div>
    <div class="paso" data-paso><b>2</b><p><?= t('Elige día y hora') ?></p><small><?= t('Solo ves horas libres de verdad, con el tiempo justo para tu sesión.') ?></small></div>
    <div class="paso" data-paso><b>3</b><p><?= t('Confirma por WhatsApp') ?></p><small><?= t('Te llega un mensaje con un enlace. Un toque y listo. Cancelar es gratis.') ?></small></div>
    <a class="pildora pildora-grande" href="#reservar" data-book data-magnet><?= t('Pedir cita ahora') ?></a>
  </section>

  <?php if ($menus): ?>
  <section class="bonos" id="bonos" aria-labelledby="bonos-t">
    <p class="etiqueta"><?= t('Bonos') ?></p>
    <h2 class="bonos-t" id="bonos-t" data-split><?= t('Sesiones que cunden') ?></h2>
    <div class="bonos-lista">
      <?php foreach ($menus as $m): ?>
      <article class="bono">
        <h3><?= h($m['title']) ?></h3>
        <p class="bono-precio"><?= precio($m['price']) ?></p>
        <?php foreach ($m['courses'] as $c): ?><p class="bono-linea"><b><?= h($c['name']) ?></b> <?= h(implode(' · ', array_column($c['dishes'], 'name'))) ?></p><?php endforeach; ?>
        <?php if ($m['note'] !== ''): ?><p class="nota"><?= nl2br(h($m['note'])) ?></p><?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="visita" id="visitanos" aria-labelledby="visita-t">
    <div class="visita-col">
      <p class="etiqueta"><?= t('Visítanos') ?></p>
      <h2 class="visita-t" id="visita-t" data-split><?= t('Aquí estamos') ?></h2>
      <?php if ($s['address']): ?><p class="visita-dir"><?= h($s['address']) ?><?= $s['city'] ? '<br>' . h($s['city']) : '' ?></p><?php endif; ?>
      <p class="visita-botones">
        <?php if ($s['address']): ?><a class="pildora pildora-borde" href="<?= h(maps_href()) ?>" target="_blank" rel="noopener"><?= t('Cómo llegar') ?></a><?php endif; ?>
        <?php if ($s['phone']): ?><a class="pildora pildora-borde" href="<?= h(tel_href($s['phone'])) ?>"><?= h($s['phone']) ?></a><?php endif; ?>
        <?php if (wa_href()): ?><a class="pildora pildora-borde" href="<?= h(wa_href()) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      </p>
    </div>
    <ul class="horario" aria-label="<?= t('Horario') ?>">
      <?php foreach (horario() as $d): ?><li<?= $d['hoy'] ? ' class="hoy"' : '' ?>><span><?= $d['dia'] ?></span><span><?= h($d['texto']) ?></span></li><?php endforeach; ?>
    </ul>
    <?php if (politicas()): ?>
    <div class="normas">
      <h3><?= t('Antes de venir') ?></h3>
      <dl><?php foreach (politicas() as $p): ?><div><dt><?= h($p['titulo']) ?></dt><dd><?= h($p['texto']) ?></dd></div><?php endforeach; ?></dl>
    </div>
    <?php endif; ?>
  </section>
</main>
<?php cerrar();
