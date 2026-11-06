<?php
/*
 * Ventana para pedir cita en 2 pasos (la controla sdk/restaurante.js): 1) tratamiento y hora (con «lo antes posible»
 * y, debajo, cualquier día con sus horas), 2) nombre y móvil. Tocar una hora ya pasa al paso 2.
 * Se abre con cualquier elemento que tenga el atributo data-book; con data-book="ID" sale ya elegido
 * ese tratamiento. Sus colores y tipografías se cambian desde el tema con variables CSS
 * (--r-bg, --r-text, --r-accent…; ver TEMA.md). Todas sus clases empiezan por bk- para no chocar con el tema.
 */
$s = settings();
$c = current_customer();
$wa = wa_number($s['whatsapp']);
$hayOpc = $s['phone'] || $wa;
$tratamientos = carta_publica()['categories'];
?>
<dialog class="bk" id="bk" aria-labelledby="bk-title">
  <form class="bk-in" id="bk-form" method="dialog" novalidate>
    <header class="bk-head">
      <div>
        <p class="bk-step" id="bk-step"><?= t('Paso %d de 2', 1) ?></p>
        <h2 class="bk-h" id="bk-title"><?= t('Pedir cita') ?></h2>
      </div>
      <button class="bk-close" type="button" data-close aria-label="<?= t('Cerrar') ?>">×</button>
    </header>

    <section class="bk-pane" data-pane="1">
      <?php if ($tratamientos): ?>
      <fieldset class="bk-svc-box" id="bk-svc-box">
        <legend><?= t('¿Qué necesitas?') ?></legend>
        <button type="button" class="bk-svc-chosen" id="bk-svc-chosen" hidden><span><b id="bk-svc-name"></b><small id="bk-svc-meta"></small></span><em><?= t('Cambiar') ?></em></button>
        <div class="bk-svcs" id="bk-svcs">
          <?php foreach ($tratamientos as $cat): ?>
            <p class="bk-svc-cat"><?= h($cat['name']) ?></p>
            <?php foreach ($cat['dishes'] as $d): ?>
              <label class="bk-svc"><input type="radio" name="service" value="<?= (int)$d['id'] ?>" data-min="<?= (int)$d['duration'] ?>" data-name="<?= h($d['name']) ?>" data-meta="<?= (int)$d['duration'] ?> min<?= $d['price'] > 0 ? ' · ' . h(precio($d['price'])) : '' ?>"><span><b><?= h($d['name']) ?></b><small><?= (int)$d['duration'] ?> min<?= $d['price'] > 0 ? ' · ' . precio($d['price']) : '' ?></small></span></label>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
        <p class="bk-help"><?= t('¿No sabes cuál? Elige la primera valoración y te lo decimos en consulta.') ?></p>
      </fieldset>
      <?php endif; ?>

      <div class="bk-when" id="bk-when" <?= $tratamientos ? 'hidden' : '' ?>>
        <fieldset>
          <legend><?= t('Lo antes posible') ?></legend>
          <div class="bk-soon" id="bk-soon" aria-live="polite"></div>
        </fieldset>
        <fieldset>
          <legend><?= t('O elige día') ?></legend>
          <div class="bk-days" id="bk-days" role="radiogroup" aria-label="<?= t('Día') ?>"></div>
          <label class="bk-other"><?= t('Otra fecha') ?> <input type="date" id="bk-date"></label>
          <div id="bk-slots" class="bk-slots" aria-live="polite"></div>
        </fieldset>
      </div>

      <?php if ($hayOpc): ?>
      <p class="bk-alt">
        <?php if ($s['phone']): ?><a id="bk-call" href="<?= h(tel_href($s['phone'])) ?>" data-href="<?= h(tel_href($s['phone'])) ?>"><?= t('Llamar') ?> · <span id="bk-call-txt"><?= h($s['phone']) ?></span></a><?php endif; ?>
        <?php if ($wa): ?><a href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener"><?= t('Escribir por WhatsApp') ?></a><?php endif; ?>
      </p>
      <?php endif; ?>
    </section>

    <section class="bk-pane" data-pane="2" hidden>
      <button type="button" class="bk-pick" data-next="1"><span><b id="bk-pick-when"></b><small id="bk-pick-what"></small></span><em><?= t('Cambiar') ?></em></button>
      <?php if ($c): ?>
        <p class="bk-who"><?= t('Pides cita como') ?> <strong><?= h($c['name']) ?></strong> · <?= h($c['phone'] ?: $c['email']) ?>. <button type="button" class="bk-link" id="bk-edit-who"><?= t('Usar otros datos') ?></button></p>
      <?php endif; ?>
      <div class="bk-fields" id="bk-fields" <?= $c ? 'hidden' : '' ?>>
        <label><?= t('Nombre') ?> <input name="name" maxlength="80" autocomplete="name" value="<?= h($c['name'] ?? '') ?>"></label>
        <label><?= t('Teléfono móvil') ?> <span><?= t('te enviaremos un WhatsApp para confirmar') ?></span><input name="phone" type="tel" inputmode="tel" maxlength="30" autocomplete="tel" required value="<?= h($c['phone'] ?? '') ?>"></label>
        <details class="bk-more" <?= $c ? 'open' : '' ?>>
          <summary><?= t('Añadir email o un comentario') ?> <span><?= t('opcional') ?></span></summary>
          <label>Email <input name="email" type="email" maxlength="120" autocomplete="email" aria-describedby="bk-email-help" value="<?= h($c['email'] ?? '') ?>"></label>
          <p class="bk-help" id="bk-email-help"><?= $c ? t('Ponlo si quieres recibir la confirmación por email.') : t('Ponlo si quieres recibir la confirmación por email o ver esta cita en una cuenta: solo aparecerá en la cuenta que crees con este mismo email. Sin email, la cita no se puede añadir a una cuenta.') ?></p>
          <label><?= t('Algo que debamos saber') ?> <textarea name="notes" rows="2" maxlength="500" placeholder="<?= t('Dónde te duele, desde cuándo, si vienes de parte de tu médico…') ?>"></textarea></label>
        </details>
      </div>
      <label class="bk-hp" aria-hidden="true"><?= t('No rellenar') ?> <input name="website" tabindex="-1" autocomplete="off"></label>
      <div class="bk-policy">
        <label class="bk-check"><input type="checkbox" name="privacy" value="1"><span><?= t('Acepto las <a href="legal.php#politicas" target="_blank">normas del centro</a> y las <a href="legal.php#condiciones" target="_blank">condiciones de uso</a>, y he leído la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>.') ?></span></label>
        <?php if (!$c): ?><label class="bk-check"><input type="checkbox" id="bk-remember" checked><span><?= t('Recordar mis datos en este dispositivo') ?></span></label><?php endif; ?>
        <p class="bk-info"><?= t('Cancelar desde el enlace es gratis. Si no puedes venir, avísanos con tiempo para dar tu hueco a otra persona.') ?> <?= t('<strong>Protección de datos.</strong> Responsable: %s. Finalidad: gestionar tu cita; el teléfono solo se usa para enviarte el WhatsApp de confirmación. Los datos de la petición se borran 24 horas después de la cita y no se ceden a nadie; si eres paciente del centro, tu ficha clínica se guarda aparte el tiempo que marca la ley. Puedes acceder a ellos, corregirlos o borrarlos escribiendo a %s.', h($s['owner_name'] ?: $s['name']), h($s['email'] ?: $s['phone'] ?: t('nuestro contacto'))) ?> <a href="legal.php#privacidad" target="_blank"><?= t('Más información') ?></a>.</p>
      </div>
      <p class="bk-err" id="bk-err" role="alert"></p>
      <div class="bk-nav"><button class="bk-btn bk-btn-main bk-btn-wide" type="button" id="bk-send"><?= t('Pedir la cita') ?></button></div>
    </section>

    <section class="bk-pane bk-done" data-pane="3" hidden>
      <p class="bk-done-mark" aria-hidden="true">✓</p>
      <h3 class="bk-h" id="bk-done-t"></h3>
      <p id="bk-done-msg"></p>
      <p class="bk-done-more" id="bk-done-more"></p>
      <div class="bk-nav"><button class="bk-btn bk-btn-main" type="button" data-close><?= t('Hecho') ?></button></div>
    </section>
  </form>
</dialog>
