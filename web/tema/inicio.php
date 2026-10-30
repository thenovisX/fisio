<?php
/*
 * Portada del tema Base. Variables: $s (ajustes), $cliente (o null), $carta (categorías con platos y alérgenos), $menus (lista, puede estar vacía), $carta_on, $menus_button.
 * Funciones útiles: photo_img(), horario_hoy(), politicas(), al_icon(), precio(). Ver TEMA.md.
 */
require __DIR__ . '/partes.php';
$alNames = array_column($carta['allergens'], 'name', 'key');
$usados = [];
foreach ($carta['categories'] as $cat) foreach ($cat['dishes'] as $d) foreach ($d['allergens'] as $k) $usados[$k] = true;
$menuSinAlg = false; // ¿algún plato de los menús sin alérgenos puestos?
foreach ($menus as $m) foreach ($m['courses'] as $c) foreach ($c['dishes'] as $d) if (!$d['allergens']) $menuSinAlg = true;
abrir($s['name'] . ($s['tagline'] ? ' · ' . $s['tagline'] : ''), 'inicio');
?>
<main id="main">
  <section class="portada">
    <div class="portada-foto"><?= photo_img('portada', 1600, '100vw', 'fetchpriority="high"') ?></div>
    <div class="cont portada-txt">
      <p class="etiqueta"><?= h(in_array(horario_hoy(), [t('Cerrado'), t('Cerrado hoy')], true) ? t('Hoy cerrado') : t('Hoy') . ': ' . horario_hoy()) ?></p>
      <h1><?= h($s['name']) ?></h1>
      <?php if ($s['tagline']): ?><p class="portada-frase"><?= h($s['tagline']) ?></p><?php endif; ?>
      <div class="botones">
        <a class="boton boton-acento" href="#reservar" data-book><?= t('Reservar mesa') ?></a>
        <?php if ($carta_on): ?><a class="boton boton-claro" href="#carta"><?= t('Ver la carta') ?></a><?php endif; ?>
        <?php if ($menus && $menus_button): ?><a class="boton boton-claro" href="#menus"><?= t('Ver menús') ?></a><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="seccion" id="nosotros">
    <div class="cont dos">
      <div>
        <p class="etiqueta"><?= t('Nosotros') ?></p>
        <h2 class="titulo"><?= h($s['tagline'] ?: $s['name']) ?></h2>
        <p class="texto-grande"><?= nl2br(h($s['description'] ?: t('Escribe aquí la presentación del restaurante desde la app: Ajustes → Datos del restaurante.'))) ?></p>
      </div>
      <figure class="foto-marco"><?= photo_img('sala', 1000, '(max-width: 800px) 100vw, 45vw', 'loading="lazy" data-zoom') ?></figure>
    </div>
  </section>

  <?php if ($menus): ?>
  <section class="seccion" id="menus">
    <div class="cont">
      <p class="etiqueta"><?= t('Menús') ?></p>
      <h2 class="titulo"><?= t('Precio cerrado, platos a elegir') ?></h2>
        <?php if ($menus): ?>
          <?php if (count($menus) > 1): ?>
            <nav class="carta-nav" aria-label="<?= t('Menús') ?>"><?php foreach ($menus as $m): ?><a href="#menu-<?= h($m['id']) ?>"><?= h($m['title']) ?></a><?php endforeach; ?></nav>
          <?php endif; ?>
          <?php foreach ($menus as $m): ?>
          <article class="menu-dia" id="menu-<?= h($m['id']) ?>" aria-labelledby="menu-t-<?= h($m['id']) ?>">
            <div class="menu-dia-cab">
              <h3 id="menu-t-<?= h($m['id']) ?>"><?= h($m['title']) ?></h3>
              <p class="menu-dia-precio"><?= precio($m['price']) ?></p>
              <p class="menu-dia-dias"><?= h(mb_strtoupper(mb_substr($m['days_text'], 0, 1)) . mb_substr($m['days_text'], 1)) ?><?php if (!$m['every_day']): ?> · <strong><?= $m['today'] ? t('Hoy sí') : t('Hoy no') ?></strong><?php endif; ?></p>
            </div>
            <div class="menu-dia-cursos">
              <?php foreach ($m['courses'] as $c): ?>
                <section>
                  <h4><?= h($c['name']) ?><?php if (count($c['dishes']) > 1): ?> <small><?= t('a elegir') ?></small><?php endif; ?></h4>
                  <ul>
                    <?php foreach ($c['dishes'] as $d): ?>
                      <li<?= $d['photo'] ? ' class="con-foto"' : '' ?>><?php if ($d['photo']): ?><img class="menu-dia-foto" src="<?= h($d['photo']) ?>" alt="" loading="lazy" data-zoom data-zoom-group="menu-<?= h($m['id']) ?>" data-zoom-caption="<?= h($d['name']) ?>"><?php endif; ?><span><?= h($d['name']) ?><?php if ($d['extra'] > 0): ?> <span class="menu-dia-sup" title="<?= t('Suplemento') ?>">+<?= euros($d['extra']) ?> €</span><?php endif; ?><?php if ($d['desc'] !== ''): ?><small><?= h($d['desc']) ?></small><?php endif; ?><?php if ($d['allergens']): ?><span class="plato-al"><?php foreach ($d['allergens'] as $k): ?><?= al_icon($k, 20, $alNames[$k] ?? $k) ?><?php endforeach; ?></span><?php endif; ?></span></li>
                    <?php endforeach; ?>
                  </ul>
                </section>
              <?php endforeach; ?>
            </div>
            <?php if ($m['note'] !== ''): ?><p class="menu-dia-nota"><?= nl2br(h($m['note'])) ?></p><?php endif; ?>
            <p class="nota"><?= $menuSinAlg ? t('Pregúntanos por los alérgenos de los platos del menú.') : t('Si tienes alguna alergia o intolerancia, dínoslo antes de pedir: te diremos qué platos puedes tomar o cómo adaptarlos.') ?></p>
          </article>
          <?php endforeach; ?>
        <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($carta_on || !$menus): ?>
  <section class="seccion seccion-papel" id="carta">
    <div class="cont">
      <p class="etiqueta"><?= t('La carta') ?></p>
      <h2 class="titulo"><?= t('Para compartir o para ti solo') ?></h2>
      <?php if ($carta_on): ?>
      <?php if (!$carta['categories']): ?>
        <?php if (!$menus): ?><p><?= t('La carta aparecerá aquí en cuanto se añadan platos desde la app.') ?></p><?php endif; ?>
      <?php else: ?>
        <nav class="carta-nav" aria-label="<?= t('Secciones de la carta') ?>">
          <?php foreach ($carta['categories'] as $cat): ?><a href="#cat-<?= $cat['id'] ?>"><?= h($cat['name']) ?></a><?php endforeach; ?>
        </nav>
        <div class="carta">
          <?php foreach ($carta['categories'] as $ci => $cat): ?>
            <details class="carta-sec" id="cat-<?= $cat['id'] ?>"<?= $ci === 0 ? ' open' : '' ?>>
              <summary><h3><?= h($cat['name']) ?></h3><span class="carta-sec-n"><?= count($cat['dishes']) ?></span></summary>
              <ul>
                <?php foreach ($cat['dishes'] as $d): ?>
                  <li class="plato<?= $d['featured'] ? ' plato-destacado' : '' ?>">
                    <?php if ($d['photo']): ?><img class="plato-foto" src="<?= h($d['photo']) ?>" alt="" loading="lazy" data-zoom data-zoom-group="platos" data-zoom-caption="<?= h($d['name']) ?>"><?php endif; ?>
                    <div class="plato-txt">
                      <p class="plato-linea"><span class="plato-nombre"><?= h($d['name']) ?></span><span class="plato-precio"><?= precio($d['price']) ?></span></p>
                      <?php if ($d['featured']): ?><p class="plato-marca"><?= t('Recomendado de la casa') ?></p><?php endif; ?>
                      <?php if ($d['description']): ?><p class="plato-desc"><?= h($d['description']) ?></p><?php endif; ?>
                      <?php if ($d['allergens']): ?><p class="plato-al"><?php foreach ($d['allergens'] as $k): ?><?= al_icon($k, 22, $alNames[$k] ?? $k) ?><?php endforeach; ?></p><?php endif; ?>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endforeach; ?>
        </div>
        <?php if ($usados): ?>
          <details class="alergenos">
            <summary><?= t('Qué significa cada icono de alérgenos') ?></summary>
            <ul>
              <?php foreach ($carta['allergens'] as $a): if (empty($usados[$a['key']])) continue; ?>
                <li><?= al_icon($a['key'], 28) ?><span><strong><?= h($a['name']) ?></strong> <?= h($a['desc']) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <p class="nota"><?= t('Si tienes alguna alergia o intolerancia, díselo siempre al personal antes de pedir.') ?></p>
          </details>
        <?php endif; ?>
      <?php endif; ?>
      <?php endif; /* carta_on */ ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="seccion" id="reservar">
    <div class="cont dos dos-centro">
      <div>
        <p class="etiqueta"><?= t('Reservas') ?></p>
        <h2 class="titulo"><?= t('Reserva tu mesa en un minuto') ?></h2>
        <p><?= t('Elige día, hora y cuántos sois. Te enviaremos un WhatsApp para confirmar la reserva. Cancelar es siempre gratis.') ?></p>
        <a class="boton boton-acento" href="#reservar" data-book><?= t('Reservar mesa') ?></a>
      </div>
      <div class="politicas">
        <?php foreach (politicas() as $p): ?>
          <details><summary><?= h($p['titulo'] ?: t('Información')) ?></summary><p><?= h($p['texto']) ?></p></details>
        <?php endforeach; ?>
        <details><summary><?= t('Si llegas tarde') ?></summary><p><?= t('Guardamos la mesa %d minutos desde la hora reservada.', (int)$s['late_minutes']) ?></p></details>
      </div>
    </div>
  </section>

  <section class="seccion seccion-papel app" id="app">
    <div class="cont">
      <p class="etiqueta"><?= t('En tu móvil') ?></p>
      <h2 class="titulo"><?= t('Ten %s a un toque', h($s['name'])) ?></h2>
      <p><?= t('Instala la web como una app para reservar y ver la carta más rápido.') ?></p>
      <button class="boton boton-acento" type="button" data-install hidden><?= t('Instalar la app') ?></button>
      <p data-install-help class="nota"><?= t('En Android o en el ordenador, abre el menú del navegador y elige «Instalar».') ?></p>
      <p data-install-ios hidden class="nota"><?= t('En iPhone: pulsa Compartir y luego «Añadir a pantalla de inicio».') ?></p>
      <p data-install-done hidden class="nota"><?= t('Ya la tienes instalada.') ?></p>
    </div>
  </section>
</main>
<?php cerrar();
