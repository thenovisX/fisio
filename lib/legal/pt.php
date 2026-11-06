<?php /* Textos legais em português (tradução; prevalece a versão em espanhol). Variáveis: $s, $titular, $contacto, $google_mail, $analytics */ ?>
  <h2 id="aviso">Aviso legal</h2>
  <p>Em cumprimento da Lei espanhola 34/2002 de serviços da sociedade da informação (LSSI-CE): este site pertence a <strong><?= h($titular) ?></strong><?= $s['owner_nif'] ? ', com NIF ' . h($s['owner_nif']) : '' ?>,
    com sede em <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?: '—' ?>.<?= $s['owner_registry'] ? ' ' . h($s['owner_registry']) . '.' : '' ?>
    Email: <?= $s['email'] ? '<a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '—' ?><?= $s['phone'] ? ' · Telefone: ' . h($s['phone']) : '' ?>.</p>
  <p>Os textos, fotografias e preços são orientativos e podem mudar sem aviso prévio. A ementa válida é a do restaurante.</p>

  <h2 id="politicas">Políticas de reserva</h2>
  <p>Ao reservar no site envia um pedido. O restaurante analisa-o e envia uma mensagem de WhatsApp com uma ligação para o telefone que indicou: abra-a e toque em «Confirmar a minha reserva». Também pode cancelá-la a partir dessa mesma ligação. Cancelar é sempre gratuito. Guardamos a mesa durante <?= (int)$s['late_minutes'] ?> minutos a partir da hora reservada.</p>
  <?php foreach (legal_policies() as $line): ?><p><?= h($line) ?></p><?php endforeach; ?>

  <h2 id="condiciones">Condições de utilização</h2>
  <p>Estas condições regulam a utilização deste site e do seu serviço de reservas. Ao reservar ou criar uma conta, aceita-as. Versão de <?= h(date('j/n/Y', strtotime(LEGAL_VERSION))) ?>.</p>
  <p><strong>O serviço:</strong> o site permite consultar a ementa e pedir reservas de mesa em <?= h($s['name']) ?>. Reservar é gratuito e nada é cobrado através do site.</p>
  <p><strong>Reservas:</strong> a reserva é um pedido até que o restaurante lhe envie o WhatsApp com a ligação e toque em «Confirmar a minha reserva». Se se enganou em algum dado, faça uma nova reserva.
    Pode cancelá-la gratuitamente a qualquer momento. Guardamos a mesa durante <?= (int)$s['late_minutes'] ?> minutos a partir da hora reservada.
    O restaurante pode recusar ou cancelar uma reserva se não houver disponibilidade ou por motivos alheios à sua vontade, e avisá-lo-á o mais depressa possível.</p>
  <p><strong>A sua conta:</strong> é pessoal. Mantenha a sua palavra-passe em segredo. Podemos suspender uma conta utilizada para fazer reservas falsas ou abusivas. Pode eliminá-la quando quiser em «A minha conta».</p>
  <p><strong>Ementa e preços:</strong> são orientativos e podem mudar. Os alergénios são indicados com a maior exatidão possível, mas avise-nos sempre de qualquer alergia antes de pedir.</p>
  <p><strong>Responsabilidade:</strong> fazemos o possível para que o site funcione bem e sem interrupções, mas não respondemos por falhas técnicas alheias a nós. Nada nestas condições limita os direitos que a lei lhe reconhece enquanto consumidor.</p>
  <p><strong>Lei e tribunais:</strong> aplica-se a lei espanhola. Em caso de litígio, são competentes os tribunais do seu domicílio enquanto consumidor.</p>

  <h2 id="privacidad">Política de privacidade</h2>
  <p><strong>Responsável pelo tratamento:</strong> <?= h($titular) ?><?= $s['owner_nif'] ? ', NIF ' . h($s['owner_nif']) : '' ?>. <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?>. <strong>Contacto:</strong> <?= h($contacto ?: '—') ?>.</p>
  <p><strong>Que dados tratamos e para quê:</strong></p>
  <ul>
    <li><strong>Reservas:</strong> nome, telefone, email (opcional), dia, hora, número de pessoas e notas. Servem para gerir a reserva e avisá-lo de alterações. Fundamento jurídico: a gestão do que nos pede (art. 6.º, n.º 1, alínea b), do RGPD). O telefone é obrigatório porque lhe enviamos por WhatsApp a ligação para confirmar a reserva; só é utilizado para isso e para o avisar de alterações. Nunca enviamos publicidade nem fazemos chamadas comerciais.</li>
    <li><strong>Conta (opcional):</strong> nome, email, telefone (opcional) e uma impressão digital irreversível (hash) da sua palavra-passe. Serve para reservar sem escrever os seus dados e para ver e cancelar as suas reservas. Fundamento jurídico: as condições de utilização da conta que aceita ao criá-la (art. 6.º, n.º 1, alínea b), do RGPD). Na sua conta aparecem as reservas feitas com ela ou com o mesmo email; as feitas sem email não podem ser adicionadas.</li>
    <li><strong>Estatísticas e publicidade:</strong> só se aceitar esses cookies. Fundamento jurídico: o seu consentimento, que pode retirar a qualquer momento.</li>
    <li><strong>Registo de segurança:</strong> quando aceita estas condições ou altera a sua palavra-passe ou os seus dados, com o IP (cifrado) e o navegador. Fundamento jurídico: o nosso interesse legítimo em poder demonstrar o seu consentimento e proteger a sua conta.</li>
  </ul>
  <p><strong>Dados obrigatórios:</strong> para reservar são necessários o nome e o telefone; sem eles não podemos enviar-lhe a confirmação. O email é opcional.</p>
  <p><strong>Publicidade e perfis:</strong> não lhe enviamos emails promocionais nem partilhamos os seus dados para que outros o façam. Os emails que recebe são apenas sobre a sua reserva ou a sua conta. Não tomamos decisões automatizadas sobre si nem criamos perfis.</p>
  <p><strong>Comunicações a terceiros:</strong> não cedemos os seus dados a terceiros, salvo obrigação legal (por exemplo, a pedido de um juiz).</p>
  <p><strong>Prestadores que tratam dados por nossa conta (subcontratantes):</strong> Hostinger (Hostinger International Ltd.), que aloja o site e a base de dados em servidores da União Europeia; WhatsApp (WhatsApp Ireland Ltd., do grupo Meta), através do qual recebe a mensagem de confirmação do WhatsApp do restaurante<?php if ($google_mail): ?>; Google (Google Ireland Ltd., Gmail), através do qual lhe enviamos os emails da sua reserva ou conta<?php endif; ?><?php if ($analytics): ?>; Google (Analytics), só se aceitar os cookies de análise<?php endif; ?><?php if ($s['pixel_id'] !== ''): ?>; Meta Platforms Ireland Ltd. (Facebook e Instagram), só se aceitar os cookies de publicidade<?php endif; ?>. Todos assinaram connosco o contrato exigido pelo art. 28.º do RGPD (as suas condições de tratamento de dados).</p>
  <?php $tr = array_merge(['WhatsApp'], $google_mail || $analytics ? ['Google'] : [], $s['pixel_id'] !== '' ? ['Meta'] : []);
        $trTxt = count($tr) > 1 ? implode(', ', array_slice($tr, 0, -1)) . ' e ' . end($tr) . ' podem' : 'O WhatsApp pode'; ?>
  <p><strong>Transferências para fora da UE:</strong> <?= $trTxt ?> tratar dados nos Estados Unidos. Essas transferências estão abrangidas pelo Quadro de Privacidade de Dados UE-EUA (decisão de adequação da Comissão Europeia de julho de 2023) e por cláusulas contratuais-tipo.<?php if ($analytics || $s['pixel_id'] !== ''): ?> Se não aceitar os cookies de análise e publicidade, nada é enviado à Google nem à Meta a partir deste site.<?php endif; ?></p>
  <p><strong>Durante quanto tempo:</strong> o nome, o telefone, o email e as notas de cada reserva são apagados automaticamente 24 horas depois da hora reservada. O aviso de cada reserva que o restaurante recebe por email inclui apenas o nome, o dia e o número de pessoas, e o restaurante apaga periodicamente a conversa de WhatsApp da confirmação. Os dados da sua conta são guardados até a eliminar; se criar uma conta e não a ativar, é eliminada ao fim de 7 dias. O registo de segurança é guardado durante 3 anos, o prazo em que poderia ser necessário demonstrar o seu consentimento perante a Agência Espanhola de Proteção de Dados, e depois é apagado.</p>
  <p><strong>Segurança:</strong> a ligação é sempre cifrada (https). Os seus dados pessoais são guardados cifrados na base de dados e a sua palavra-passe nunca é guardada, apenas uma impressão digital irreversível. Se ocorresse uma violação de segurança que afetasse os seus dados, avisaríamos a Agência Espanhola de Proteção de Dados em menos de 72 horas e a si sem demora.</p>
  <p><strong>Os seus direitos:</strong> pode aceder aos seus dados, retificá-los, apagá-los, opor-se ao seu tratamento, limitá-lo e levá-los para outro serviço (portabilidade), bem como retirar a qualquer momento os consentimentos que tenha dado (por exemplo, o dos cookies), sem afetar o tratamento anterior. Em «A minha conta» pode descarregar todos os seus dados e eliminar a conta; para tudo o resto escreva para <?= h($contacto ?: 'o nosso contacto') ?> indicando o direito que pretende exercer. Responderemos no prazo máximo de um mês. Se considerar que não tratamos bem os seus dados, pode apresentar reclamação à Agência Espanhola de Proteção de Dados (<a href="https://www.aepd.es" target="_blank" rel="noopener">aepd.es</a>).</p>
  <p><strong>Menores:</strong> o site não se destina a menores de 14 anos. Se tiver menos de 14 anos, peça aos seus pais ou tutores que façam a reserva.</p>

  <h2 id="cookies">Cookies</h2>
  <p>Os cookies são pequenos ficheiros que o site guarda no seu navegador. Os necessários são sempre utilizados; os restantes só se os aceitar no aviso de cookies. Pode mudar a sua escolha quando quiser: <a href="#cookies" data-cookies>configurar cookies</a>.</p>
  <table class="ck-tabla">
    <thead><tr><th>Cookie</th><th>Finalidade</th><th>De quem</th><th>Duração</th></tr></thead>
    <tbody>
      <tr><td>cookie_consent</td><td>Lembrar a sua escolha sobre os cookies (necessário). Passado esse prazo voltamos a perguntar.</td><td>Este site</td><td>13 meses</td></tr>
      <tr><td>cliente_sesion</td><td>Manter a sua conta aberta se iniciar sessão (necessário)</td><td>Este site</td><td>60 dias</td></tr>
      <tr><td>idioma</td><td>Lembrar o idioma que escolhe com as bandeiras (necessário, só se mudar de idioma)</td><td>Este site</td><td>1 ano</td></tr>
      <?php if ($s['ga_id'] !== '' || $s['gtm_id'] !== ''): ?>
      <tr><td>_ga, _ga_*</td><td>Estatísticas anónimas de visitas (análise, só se aceitar)</td><td>Google Analytics</td><td>2 anos</td></tr>
      <?php endif; ?>
      <?php if ($s['gtm_id'] !== ''): ?>
      <tr><td>_gcl_*</td><td>Medir anúncios da Google (publicidade, só se aceitar)</td><td>Google</td><td>3 meses</td></tr>
      <?php endif; ?>
      <?php if ($s['pixel_id'] !== ''): ?>
      <tr><td>_fbp, _fbc</td><td>Medir anúncios no Facebook e no Instagram (publicidade, só se aceitar)</td><td>Meta</td><td>3 meses</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($mapa): ?><p>Se tocar em «Mostrar o mapa», é carregado o Google Maps, que pode usar os seus próprios cookies; até o fazer, não há ligação à Google.</p><?php endif; ?>
  <p>As fontes e as fotografias são servidas a partir do nosso próprio servidor, pelo que o seu navegador não se liga a outras empresas para as carregar.</p>
  <p>Se não aceitar os cookies opcionais, não voltaremos a perguntar até passar o prazo indicado, e o site funciona da mesma forma.</p>
  <p>Também pode apagar ou bloquear os cookies nas definições do seu navegador.</p>

