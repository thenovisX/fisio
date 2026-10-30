<?php /* Textos legais en galego (tradución; prevalece a versión en castelán). Variables: $s, $titular, $contacto, $google_mail, $analytics, $mapa */ ?>
  <h2 id="aviso">Aviso legal</h2>
  <p>En cumprimento da Lei 34/2002 de servizos da sociedade da información (LSSI-CE): esta web pertence a <strong><?= h($titular) ?></strong><?= $s['owner_nif'] ? ', con NIF ' . h($s['owner_nif']) : '' ?>,
    con domicilio en <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?: '—' ?>.<?= $s['owner_registry'] ? ' ' . h($s['owner_registry']) . '.' : '' ?>
    Correo electrónico: <?= $s['email'] ? '<a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '—' ?><?= $s['phone'] ? ' · Teléfono: ' . h($s['phone']) : '' ?>.</p>
  <p>Os textos, fotos e prezos son orientativos e poden cambiar sen aviso previo. A carta do local é a que vale.</p>

  <h2 id="politicas">Políticas de reserva</h2>
  <p>Ao reservar na web envías unha petición. O restaurante revísaa e envíache un WhatsApp ao teléfono que indicaches cunha ligazón: ábrea e preme «Confirmar a miña reserva». Desde esa mesma ligazón tamén podes cancelala. Cancelar é sempre gratis. Gardamos a mesa <?= (int)$s['late_minutes'] ?> minutos desde a hora reservada.</p>
  <?php foreach (legal_policies() as $line): ?><p><?= h($line) ?></p><?php endforeach; ?>

  <h2 id="condiciones">Condicións de uso</h2>
  <p>Estas condicións regulan o uso desta web e do seu servizo de reservas. Ao reservar ou crear unha conta acéptalas. Versión do <?= h(date('j/n/Y', strtotime(LEGAL_VERSION))) ?>.</p>
  <p><strong>O servizo:</strong> a web permite consultar a carta e solicitar reservas de mesa en <?= h($s['name']) ?>. Reservar é gratis e non se cobra nada a través da web.</p>
  <p><strong>Reservas:</strong> a reserva é unha petición ata que o restaurante che envía o WhatsApp coa ligazón e ti premes «Confirmar a miña reserva». Se te equivocaches nalgún dato, fai unha reserva nova.
    Podes cancelala gratis en calquera momento. Gardamos a mesa <?= (int)$s['late_minutes'] ?> minutos desde a hora reservada.
    O restaurante pode rexeitar ou cancelar unha reserva se non hai dispoñibilidade ou por causas alleas á súa vontade, e avisarate canto antes.</p>
  <p><strong>A túa conta:</strong> é persoal. Mantén o teu contrasinal en segredo. Podemos suspender unha conta que se use para facer reservas falsas ou de forma abusiva. Podes borrala cando queiras desde «A miña conta».</p>
  <p><strong>Carta e prezos:</strong> son orientativos e poden cambiar. Os alérxenos indícanse coa maior exactitude posible, pero avísanos sempre de calquera alerxia antes de pedir.</p>
  <p><strong>Responsabilidade:</strong> facemos o posible para que a web funcione ben e sen interrupcións, pero non respondemos de fallos técnicos alleos a nós. Nada nestas condicións limita os dereitos que che recoñece a lei como persoa consumidora.</p>
  <p><strong>Lei e tribunais:</strong> aplícase a lei española. Se houbese un conflito, serán competentes os xulgados do teu domicilio como persoa consumidora.</p>

  <h2 id="privacidad">Política de privacidade</h2>
  <p><strong>Responsable:</strong> <?= h($titular) ?><?= $s['owner_nif'] ? ', NIF ' . h($s['owner_nif']) : '' ?>. <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?>. <strong>Contacto:</strong> <?= h($contacto ?: '—') ?>.</p>
  <p><strong>Que datos tratamos e para que:</strong></p>
  <ul>
    <li><strong>Reservas:</strong> nome, teléfono, correo electrónico (opcional), día, hora, persoas e notas. Serven para xestionar a reserva e avisarte de cambios. Base legal: a xestión do que nos pides (art. 6.1.b RXPD). O teléfono é obrigatorio porque che enviamos por WhatsApp a ligazón para confirmar a reserva; só se usa para iso e para avisarte de cambios. Nunca enviamos publicidade nin facemos chamadas comerciais.</li>
    <li><strong>Conta (opcional):</strong> nome, correo electrónico, teléfono (opcional) e unha pegada do teu contrasinal que non se pode reverter. Serve para que reserves sen escribir os teus datos e vexas e canceles as túas reservas. Base legal: as condicións de uso da conta que aceptas ao creala (art. 6.1.b RXPD). Na túa conta aparecen as reservas feitas con ela ou co mesmo correo; as feitas sen correo non se poden engadir.</li>
    <li><strong>Estatísticas e publicidade:</strong> só se aceptas esas cookies. Base legal: o teu consentimento, que podes retirar en calquera momento.</li>
    <li><strong>Rexistro de seguridade:</strong> cando aceptas estas condicións ou cambias o teu contrasinal ou os teus datos, coa IP (cifrada) e o navegador. Base legal: o noso interese lexítimo en poder demostrar o teu consentimento e protexer a túa conta.</li>
  </ul>
  <p><strong>Datos obrigatorios:</strong> para reservar fan falta o nome e o teléfono; sen eles non podemos enviarche a confirmación. O correo electrónico é opcional.</p>
  <p><strong>Publicidade e perfís:</strong> non che enviamos correos promocionais nin compartimos os teus datos para que outros o fagan. Os correos que recibes son só sobre a túa reserva ou a túa conta. Non tomamos decisións automatizadas sobre ti nin elaboramos perfís.</p>
  <p><strong>Cesións:</strong> non cedemos os teus datos a terceiros, salvo que unha lei nos obrigue (por exemplo, por petición dun xuíz).</p>
  <p><strong>Provedores que tratan datos pola nosa conta (encargados):</strong> Hostinger (Hostinger International Ltd.), que aloxa a web e a base de datos en servidores da Unión Europea; WhatsApp (WhatsApp Ireland Ltd., do grupo Meta), polo que che chega a mensaxe de confirmación desde o WhatsApp do restaurante<?php if ($google_mail): ?>; Google (Google Ireland Ltd., Gmail), polo que che enviamos os correos da túa reserva ou da túa conta<?php endif; ?><?php if ($analytics): ?>; Google (Analytics), só se aceptas as cookies de análise<?php endif; ?><?php if ($s['pixel_id'] !== ''): ?>; Meta Platforms Ireland Ltd. (Facebook e Instagram), só se aceptas as cookies de publicidade<?php endif; ?>. Todos teñen asinado connosco o contrato que esixe o art. 28 RXPD (as súas condicións de tratamento de datos).</p>
  <?php $tr = array_merge(['WhatsApp'], $google_mail || $analytics ? ['Google'] : [], $s['pixel_id'] !== '' ? ['Meta'] : []);
        $trTxt = count($tr) > 1 ? implode(', ', array_slice($tr, 0, -1)) . ' e ' . end($tr) . ' poden' : 'WhatsApp pode'; ?>
  <p><strong>Transferencias fóra da UE:</strong> <?= $trTxt ?> tratar datos nos Estados Unidos. Esas transferencias están amparadas polo Marco de Privacidade de Datos UE-EUA (decisión de adecuación da Comisión Europea de xullo de 2023) e por cláusulas contractuais tipo.<?php if ($analytics || $s['pixel_id'] !== ''): ?> Se non aceptas as cookies de análise e publicidade, a Google e a Meta non se lles envía nada desde esta web.<?php endif; ?></p>
  <p><strong>Canto tempo:</strong> o nome, o teléfono, o correo electrónico e as notas de cada reserva bórranse soas 24 horas despois da hora reservada. O aviso de cada reserva que recibe o restaurante por correo só leva o nome, o día e as persoas, e o restaurante borra periodicamente o chat de WhatsApp da confirmación. Os datos da túa conta gárdanse ata que a borres; se creas unha conta e non a activas, bórrase aos 7 días. O rexistro de seguridade gárdase 3 anos, o prazo no que se podería ter que demostrar o teu consentimento ante a Axencia Española de Protección de Datos, e despois bórrase.</p>
  <p><strong>Seguridade:</strong> a conexión vai sempre cifrada (https). Os teus datos persoais gárdanse cifrados na base de datos e o teu contrasinal non se garda nunca, só unha pegada que non se pode reverter. Se houbese unha brecha de seguridade que afectase aos teus datos, avisariamos á Axencia Española de Protección de Datos en menos de 72 horas e a ti sen demora.</p>
  <p><strong>Os teus dereitos:</strong> podes acceder aos teus datos, corrixilos, borralos, opoñerte ao seu uso, limitalo e levalos a outro servizo (portabilidade), e retirar en calquera momento os consentimentos que deses (por exemplo, o das cookies) sen que afecte ao anterior. Desde «A miña conta» podes descargar todos os teus datos e borrar a conta ti mesmo; para todo o demais escribe a <?= h($contacto ?: 'o noso contacto') ?> indicando que dereito queres exercer. Responderémosche nun mes como máximo. Se cres que non tratamos ben os teus datos, podes reclamar ante a Axencia Española de Protección de Datos (<a href="https://www.aepd.es" target="_blank" rel="noopener">aepd.es</a>).</p>
  <p><strong>Menores:</strong> a web non está dirixida a menores de 14 anos. Se tes menos de 14, pídelles aos teus pais ou titores que fagan a reserva.</p>

  <h2 id="cookies">Cookies</h2>
  <p>As cookies son pequenos arquivos que a web garda no teu navegador. As necesarias úsanse sempre; as demais só se as aceptas no aviso de cookies. Podes cambiar a túa elección cando queiras: <a href="#cookies" data-cookies>configurar cookies</a>.</p>
  <table class="ck-tabla">
    <thead><tr><th>Cookie</th><th>Para que</th><th>De quen</th><th>Duración</th></tr></thead>
    <tbody>
      <tr><td>cookie_consent</td><td>Lembrar a túa elección sobre as cookies (necesaria). Pasado ese prazo volvemos preguntarche.</td><td>Esta web</td><td>13 meses</td></tr>
      <tr><td>cliente_sesion</td><td>Manter aberta a túa conta se entras (necesaria)</td><td>Esta web</td><td>60 días</td></tr>
      <tr><td>idioma</td><td>Lembrar o idioma que escolles coas bandeiras (necesaria, só se cambias de idioma)</td><td>Esta web</td><td>1 ano</td></tr>
      <?php if ($s['ga_id'] !== '' || $s['gtm_id'] !== ''): ?>
      <tr><td>_ga, _ga_*</td><td>Estatísticas anónimas de visitas (análise, só se o aceptas)</td><td>Google Analytics</td><td>2 anos</td></tr>
      <?php endif; ?>
      <?php if ($s['gtm_id'] !== ''): ?>
      <tr><td>_gcl_*</td><td>Medir anuncios de Google (publicidade, só se o aceptas)</td><td>Google</td><td>3 meses</td></tr>
      <?php endif; ?>
      <?php if ($s['pixel_id'] !== ''): ?>
      <tr><td>_fbp, _fbc</td><td>Medir anuncios en Facebook e Instagram (publicidade, só se o aceptas)</td><td>Meta</td><td>3 meses</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($mapa): ?><p>Se premes «Mostrar o mapa», cárgase Google Maps, que pode usar as súas propias cookies; mentres non o premas non se conecta con Google.</p><?php endif; ?>
  <p>As tipografías e as fotos sérvense desde o noso propio servidor, así que o teu navegador non se conecta a outras empresas para cargalas.</p>
  <p>Se non aceptas as cookies opcionais, non volveremos preguntarche ata que pase o prazo indicado, e a web funciona igual.</p>
  <p>Tamén podes borrar ou bloquear as cookies desde a configuración do teu navegador.</p>

