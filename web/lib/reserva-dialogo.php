<?php
/*
 * Ventana de reserva en 3 pasos (la controla sdk/restaurante.js). Se abre con cualquier elemento que
 * tenga el atributo data-book. Sus colores y tipografías se cambian desde el tema con variables CSS
 * (--r-bg, --r-text, --r-accent…; ver TEMA.md). Todas sus clases empiezan por bk- para no chocar con el tema.
 */
$s = settings();
$c = current_customer();
$wa = wa_number($s['whatsapp']);
$hayOpc = $s['phone'] || $wa;
?>
<dialog class="bk" id="bk" aria-labelledby="bk-title">
  <form class="bk-in" id="bk-form" method="dialog" novalidate>
    <header class="bk-head">
      <div>
        <p class="bk-step" id="bk-step"><?= t('Paso %d de 3', 1) ?></p>
        <h2 class="bk-h" id="bk-title"><?= t('Reservar mesa') ?></h2>
        <p class="bk-sum" id="bk-sum" aria-live="polite"></p>
      </div>
      <button class="bk-close" type="button" data-close aria-label="<?= t('Cerrar') ?>">×</button>
    </header>

    <?php if ($hayOpc): ?>
    <section class="bk-pane" data-pane="0" hidden>
      <div class="bk-opts">
        <button class="bk-opt" type="button" data-next="1">
          <b><?= t('Reservar online') ?></b>
          <span><?= t('Elige día y hora y te confirmamos por WhatsApp.') ?></span>
        </button>
        <?php if ($s['phone']): ?>
        <a class="bk-opt" id="bk-call" href="<?= h(tel_href($s['phone'])) ?>" data-href="<?= h(tel_href($s['phone'])) ?>">
          <b><?= t('Llamar por teléfono') ?></b>
          <span id="bk-call-txt"><?= h($s['phone']) ?></span>
        </a>
        <?php endif; ?>
        <?php if ($wa): ?>
        <a class="bk-opt" href="https://wa.me/<?= h($wa) ?>" target="_blank" rel="noopener">
          <b><?= t('Escribir por WhatsApp') ?></b>
          <span><?= t('Pregúntanos o reserva por mensaje.') ?></span>
        </a>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="bk-pane" data-pane="1">
      <fieldset>
        <legend><?= t('¿Cuántos sois?') ?></legend>
        <div class="bk-chips" id="bk-people">
          <?php for ($i = 1; $i <= min(8, (int)$s['max_people']); $i++): ?>
            <label><input type="radio" name="people" value="<?= $i ?>" <?= $i === 2 ? 'checked' : '' ?>><span><?= $i ?></span></label>
          <?php endfor; ?>
          <?php if ((int)$s['max_people'] > 8): ?>
            <label class="bk-more"><select name="people_more" aria-label="<?= t('Más personas') ?>"><option value=""><?= t('Más') ?></option><?php for ($i = 9; $i <= (int)$s['max_people']; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
          <?php endif; ?>
        </div>
      </fieldset>
      <fieldset>
        <legend><?= t('¿Qué día?') ?></legend>
        <div class="bk-days" id="bk-days" role="radiogroup" aria-label="<?= t('Día') ?>"></div>
        <label class="bk-other"><?= t('Otra fecha') ?> <input type="date" id="bk-date"></label>
      </fieldset>
      <div class="bk-nav"><?php if ($s['phone']): ?><button class="bk-btn" type="button" data-next="0"><?= t('Atrás') ?></button><?php endif; ?><button class="bk-btn bk-btn-main" type="button" data-next="2"><?= t('Ver horas libres') ?></button></div>
    </section>

    <section class="bk-pane" data-pane="2" hidden>
      <div id="bk-slots" class="bk-slots" aria-live="polite"></div>
      <div class="bk-nav"><button class="bk-btn" type="button" data-next="1"><?= t('Atrás') ?></button><button class="bk-btn bk-btn-main" type="button" data-next="3" id="bk-to3" disabled><?= t('Continuar') ?></button></div>
    </section>

    <section class="bk-pane" data-pane="3" hidden>
      <?php if ($c): ?>
        <p class="bk-who"><?= t('Reservas como') ?> <strong><?= h($c['name']) ?></strong> · <?= h($c['phone'] ?: $c['email']) ?>. <button type="button" class="bk-link" id="bk-edit-who"><?= t('Usar otros datos') ?></button></p>
      <?php else: ?>
        <p class="bk-who"><?= t('¿Tienes cuenta? <a href="cuenta.php">Entra</a> y no tendrás que escribir tus datos.') ?></p>
      <?php endif; ?>
      <div class="bk-fields" id="bk-fields" <?= $c ? 'hidden' : '' ?>>
        <label><?= t('Nombre') ?> <input name="name" maxlength="80" autocomplete="name" value="<?= h($c['name'] ?? '') ?>"></label>
        <label><?= t('Teléfono móvil') ?> <span><?= t('obligatorio: te enviaremos un WhatsApp para confirmar') ?></span><input name="phone" type="tel" maxlength="30" autocomplete="tel" required value="<?= h($c['phone'] ?? '') ?>"></label>
        <label>Email <span><?= t('opcional') ?></span><input name="email" type="email" maxlength="120" autocomplete="email" aria-describedby="bk-email-help" value="<?= h($c['email'] ?? '') ?>"></label>
        <p class="bk-help" id="bk-email-help"><?= $c ? t('Ponlo si quieres recibir la confirmación por email.') : t('Ponlo si quieres recibir la confirmación por email o ver esta reserva en una cuenta: solo aparecerá en la cuenta que crees con este mismo email. Sin email, la reserva no se puede añadir a una cuenta.') ?></p>
      </div>
      <label><?= t('Algo que debamos saber') ?> <span><?= t('opcional') ?></span><textarea name="notes" rows="2" maxlength="500" placeholder="<?= t('Alergias, celebración, trona…') ?>"></textarea></label>
      <label class="bk-hp" aria-hidden="true"><?= t('No rellenar') ?> <input name="website" tabindex="-1" autocomplete="off"></label>
      <div class="bk-policy">
        <p><?= t('Cancelar es siempre gratis. Guardamos la mesa %d minutos.', (int)$s['late_minutes']) ?></p>
        <label class="bk-check"><input type="checkbox" name="privacy" value="1"><span><?= t('Acepto las <a href="legal.php#politicas" target="_blank">políticas de reserva</a> y las <a href="legal.php#condiciones" target="_blank">condiciones de uso</a>, y he leído la <a href="legal.php#privacidad" target="_blank">política de privacidad</a>.') ?></span></label>
        <p class="bk-info"><?= t('<strong>Protección de datos.</strong> Responsable: %s. Finalidad: gestionar tu reserva; el teléfono solo se usa para enviarte el WhatsApp de confirmación. Tus datos se borran 24 horas después de la reserva y no se ceden a nadie. Puedes acceder a ellos, corregirlos o borrarlos escribiendo a %s.', h($s['owner_name'] ?: $s['name']), h($s['email'] ?: $s['phone'] ?: t('nuestro contacto'))) ?> <a href="legal.php#privacidad" target="_blank"><?= t('Más información') ?></a>.</p>
      </div>
      <p class="bk-err" id="bk-err" role="alert"></p>
      <div class="bk-nav"><button class="bk-btn" type="button" data-next="2"><?= t('Atrás') ?></button><button class="bk-btn bk-btn-main" type="button" id="bk-send"><?= t('Enviar petición') ?></button></div>
    </section>

    <section class="bk-pane bk-done" data-pane="4" hidden>
      <p class="bk-done-mark" aria-hidden="true">✓</p>
      <h3 class="bk-h" id="bk-done-t"></h3>
      <p id="bk-done-msg"></p>
      <p class="bk-done-more" id="bk-done-more"></p>
      <div class="bk-nav"><button class="bk-btn bk-btn-main" type="button" data-close><?= t('Hecho') ?></button></div>
    </section>
  </form>
</dialog>
