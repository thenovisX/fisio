<?php /* Textos legales en español (versión que prevalece). Variables: $s, $titular, $contacto, $google_mail, $analytics, $mapa */ ?>
  <h2 id="aviso">Aviso legal</h2>
  <p>En cumplimiento de la Ley 34/2002 de servicios de la sociedad de la información (LSSI-CE): esta web pertenece a <strong><?= h($titular) ?></strong><?= $s['owner_nif'] ? ', con NIF ' . h($s['owner_nif']) : '' ?>,
    con domicilio en <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?: '—' ?>.<?= $s['owner_registry'] ? ' ' . h($s['owner_registry']) . '.' : '' ?>
    Email: <?= $s['email'] ? '<a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '—' ?><?= $s['phone'] ? ' · Teléfono: ' . h($s['phone']) : '' ?>.</p>
  <p>Los textos, fotos y precios son orientativos y pueden cambiar sin previo aviso. La carta del local es la que vale.</p>

  <h2 id="politicas">Políticas de reserva</h2>
  <p>Al reservar en la web envías una petición. El restaurante la revisa y te envía un WhatsApp al teléfono que indicaste con un enlace: ábrelo y pulsa «Confirmar mi reserva». Desde ese mismo enlace también puedes cancelarla. Cancelar es siempre gratis. Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos desde la hora reservada.</p>
  <?php foreach (legal_policies() as $line): ?><p><?= h($line) ?></p><?php endforeach; ?>

  <h2 id="condiciones">Condiciones de uso</h2>
  <p>Estas condiciones regulan el uso de esta web y de su servicio de reservas. Al reservar o crear una cuenta las aceptas. Versión del <?= h(date('j/n/Y', strtotime(LEGAL_VERSION))) ?>.</p>
  <p><strong>El servicio:</strong> la web permite consultar la carta y solicitar reservas de mesa en <?= h($s['name']) ?>. Reservar es gratis y no se cobra nada a través de la web.</p>
  <p><strong>Reservas:</strong> la reserva es una petición hasta que el restaurante te envía el WhatsApp con el enlace y tú pulsas «Confirmar mi reserva». Si te equivocaste en algún dato, haz una reserva nueva.
    Puedes cancelarla gratis en cualquier momento. Guardamos la mesa <?= (int)$s['late_minutes'] ?> minutos desde la hora reservada.
    El restaurante puede rechazar o cancelar una reserva si no hay disponibilidad o por causas ajenas a su voluntad, y te avisará lo antes posible.</p>
  <p><strong>Tu cuenta:</strong> es personal. Mantén tu contraseña en secreto. Podemos suspender una cuenta que se use para hacer reservas falsas o de forma abusiva. Puedes borrarla cuando quieras desde «Mi cuenta».</p>
  <p><strong>Carta y precios:</strong> son orientativos y pueden cambiar. Los alérgenos se indican con la mayor exactitud posible, pero avísanos siempre de cualquier alergia antes de pedir.</p>
  <p><strong>Responsabilidad:</strong> hacemos lo posible para que la web funcione bien y sin interrupciones, pero no respondemos de fallos técnicos ajenos a nosotros. Nada en estas condiciones limita los derechos que te reconoce la ley como consumidor.</p>
  <p><strong>Ley y tribunales:</strong> se aplica la ley española. Si hubiera un conflicto, serán competentes los juzgados de tu domicilio como consumidor.</p>

  <h2 id="privacidad">Política de privacidad</h2>
  <p><strong>Responsable:</strong> <?= h($titular) ?><?= $s['owner_nif'] ? ', NIF ' . h($s['owner_nif']) : '' ?>. <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?>. <strong>Contacto:</strong> <?= h($contacto ?: '—') ?>.</p>
  <p><strong>Qué datos tratamos y para qué:</strong></p>
  <ul>
    <li><strong>Reservas:</strong> nombre, teléfono, email (opcional), día, hora, personas y notas. Sirven para gestionar la reserva y avisarte de cambios. Base legal: la gestión de lo que nos pides (art. 6.1.b RGPD). El teléfono es obligatorio porque te enviamos por WhatsApp el enlace para confirmar la reserva; solo se usa para eso y para avisarte de cambios. Nunca enviamos publicidad ni hacemos llamadas comerciales.</li>
    <li><strong>Cuenta (opcional):</strong> nombre, email, teléfono (opcional) y una huella de tu contraseña que no se puede revertir. Sirve para que reserves sin escribir tus datos y veas y canceles tus reservas. Base legal: las condiciones de uso de la cuenta que aceptas al crearla (art. 6.1.b RGPD). En tu cuenta aparecen las reservas hechas con ella o con su mismo email; las hechas sin email no se pueden añadir.</li>
    <li><strong>Estadísticas y publicidad:</strong> solo si aceptas esas cookies. Base legal: tu consentimiento, que puedes retirar en cualquier momento.</li>
    <li><strong>Registro de seguridad:</strong> cuándo aceptas estas condiciones, cambias tu contraseña o tus datos, con la IP (cifrada) y el navegador. Base legal: nuestro interés legítimo en poder demostrar tu consentimiento y proteger tu cuenta.</li>
  </ul>
  <p><strong>Datos obligatorios:</strong> para reservar hacen falta el nombre y el teléfono; sin ellos no podemos enviarte la confirmación. El email es opcional.</p>
  <p><strong>Publicidad y perfiles:</strong> no te enviamos emails promocionales ni compartimos tus datos para que otros lo hagan. Los emails que recibes son solo sobre tu reserva o tu cuenta. No tomamos decisiones automatizadas sobre ti ni elaboramos perfiles.</p>
  <p><strong>Cesiones:</strong> no cedemos tus datos a terceros, salvo que una ley nos obligue (por ejemplo, a petición de un juez).</p>
  <p><strong>Proveedores que tratan datos por cuenta nuestra (encargados):</strong> Hostinger (Hostinger International Ltd.), que aloja la web y la base de datos en servidores de la Unión Europea; WhatsApp (WhatsApp Ireland Ltd., del grupo Meta), por el que te llega el mensaje de confirmación desde el WhatsApp del restaurante<?php if ($google_mail): ?>; Google (Google Ireland Ltd., Gmail), por el que te enviamos los emails de tu reserva o tu cuenta<?php endif; ?><?php if ($analytics): ?>; Google (Analytics), solo si aceptas las cookies de análisis<?php endif; ?><?php if ($s['pixel_id'] !== ''): ?>; Meta Platforms Ireland Ltd. (Facebook e Instagram), solo si aceptas las cookies de publicidad<?php endif; ?>. Todos tienen firmado con nosotros el contrato que exige el art. 28 RGPD (sus condiciones de tratamiento de datos).</p>
  <?php $tr = array_merge(['WhatsApp'], $google_mail || $analytics ? ['Google'] : [], $s['pixel_id'] !== '' ? ['Meta'] : []);
        $trTxt = count($tr) > 1 ? implode(', ', array_slice($tr, 0, -1)) . ' y ' . end($tr) . ' pueden' : 'WhatsApp puede'; ?>
  <p><strong>Transferencias fuera de la UE:</strong> <?= $trTxt ?> tratar datos en Estados Unidos. Esas transferencias están amparadas por el Marco de Privacidad de Datos UE-EE. UU. (decisión de adecuación de la Comisión Europea de julio de 2023) y por cláusulas contractuales tipo.<?php if ($analytics || $s['pixel_id'] !== ''): ?> Si no aceptas las cookies de análisis y publicidad, a Google y Meta no se les envía nada desde esta web.<?php endif; ?></p>
  <p><strong>Cuánto tiempo:</strong> el nombre, el teléfono, el email y las notas de cada reserva se borran solos 24 horas después de la hora reservada. El aviso de cada reserva que recibe el restaurante por email solo lleva el nombre, el día y las personas, y el chat de WhatsApp de la confirmación lo borra el restaurante periódicamente. Los datos de tu cuenta se guardan hasta que la borres; si creas una cuenta y no la activas, se borra a los 7 días. El registro de seguridad se guarda 3 años, el plazo en que se podría tener que demostrar tu consentimiento ante la Agencia de Protección de Datos, y después se borra.</p>
  <p><strong>Seguridad:</strong> la conexión va siempre cifrada (https). Tus datos personales se guardan cifrados en la base de datos y tu contraseña no se guarda nunca, solo una huella que no se puede revertir. Si hubiera una brecha de seguridad que afectara a tus datos, avisaríamos a la Agencia Española de Protección de Datos en menos de 72 horas y a ti sin demora.</p>
  <p><strong>Tus derechos:</strong> puedes acceder a tus datos, corregirlos, borrarlos, oponerte a su uso, limitarlo y llevártelos a otro servicio (portabilidad), y retirar en cualquier momento los consentimientos que hayas dado (por ejemplo, el de las cookies) sin que afecte a lo anterior. Desde «Mi cuenta» puedes descargar todos tus datos y borrar la cuenta tú mismo; para todo lo demás escribe a <?= h($contacto ?: 'nuestro contacto') ?> indicando qué derecho quieres ejercer. Te responderemos en un mes como máximo. Si crees que no tratamos bien tus datos, puedes reclamar ante la Agencia Española de Protección de Datos (<a href="https://www.aepd.es" target="_blank" rel="noopener">aepd.es</a>).</p>
  <p><strong>Menores:</strong> la web no está dirigida a menores de 14 años. Si tienes menos de 14, pide a tus padres o tutores que hagan la reserva.</p>

  <h2 id="cookies">Cookies</h2>
  <p>Las cookies son pequeños archivos que la web guarda en tu navegador. Las necesarias se usan siempre; las demás solo si las aceptas en el aviso de cookies. Puedes cambiar tu elección cuando quieras: <a href="#cookies" data-cookies>configurar cookies</a>.</p>
  <table class="ck-tabla">
    <thead><tr><th>Cookie</th><th>Para qué</th><th>De quién</th><th>Duración</th></tr></thead>
    <tbody>
      <tr><td>cookie_consent</td><td>Recordar tu elección sobre las cookies (necesaria). Pasado ese plazo te volvemos a preguntar.</td><td>Esta web</td><td>13 meses</td></tr>
      <tr><td>cliente_sesion</td><td>Mantener abierta tu cuenta si entras (necesaria)</td><td>Esta web</td><td>60 días</td></tr>
      <tr><td>idioma</td><td>Recordar el idioma que eliges con las banderas (necesaria, solo si cambias de idioma)</td><td>Esta web</td><td>1 año</td></tr>
      <?php if ($s['ga_id'] !== '' || $s['gtm_id'] !== ''): ?>
      <tr><td>_ga, _ga_*</td><td>Estadísticas anónimas de visitas (análisis, solo si lo aceptas)</td><td>Google Analytics</td><td>2 años</td></tr>
      <?php endif; ?>
      <?php if ($s['gtm_id'] !== ''): ?>
      <tr><td>_gcl_*</td><td>Medir anuncios de Google (publicidad, solo si lo aceptas)</td><td>Google</td><td>3 meses</td></tr>
      <?php endif; ?>
      <?php if ($s['pixel_id'] !== ''): ?>
      <tr><td>_fbp, _fbc</td><td>Medir anuncios en Facebook e Instagram (publicidad, solo si lo aceptas)</td><td>Meta</td><td>3 meses</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($mapa): ?><p>Si pulsas «Mostrar el mapa», se carga Google Maps, que puede usar sus propias cookies; hasta que no lo pulses no se conecta con Google.</p><?php endif; ?>
  <p>Las tipografías y las fotos se sirven desde nuestro propio servidor, así que tu navegador no se conecta a otras empresas para cargarlas.</p>
  <p>Si no aceptas las cookies opcionales, no te volveremos a preguntar hasta que pase el plazo indicado, y la web funciona igual.</p>
  <p>También puedes borrar o bloquear las cookies desde la configuración de tu navegador.</p>

