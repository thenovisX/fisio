<?php /* Legal texts in English (translation; the Spanish version prevails). Variables: $s, $titular, $contacto, $google_mail, $analytics, $mapa */ ?>
  <h2 id="aviso">Legal notice</h2>
  <p>In compliance with Spanish Law 34/2002 on information society services (LSSI-CE): this website belongs to <strong><?= h($titular) ?></strong><?= $s['owner_nif'] ? ', tax ID (NIF) ' . h($s['owner_nif']) : '' ?>,
    with registered address at <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?: '—' ?>.<?= $s['owner_registry'] ? ' ' . h($s['owner_registry']) . '.' : '' ?>
    Email: <?= $s['email'] ? '<a href="mailto:' . h($s['email']) . '">' . h($s['email']) . '</a>' : '—' ?><?= $s['phone'] ? ' · Phone: ' . h($s['phone']) : '' ?>.</p>
  <p>Texts, photos and prices are for guidance only and may change without notice. The menu at the restaurant is the one that applies.</p>

  <h2 id="politicas">Booking policies</h2>
  <p>When you book on the website you send a request. The restaurant reviews it and sends a WhatsApp message with a link to the phone number you gave: open it and tap “Confirm my booking”. You can also cancel from that same link. Cancelling is always free. We hold the table for <?= (int)$s['late_minutes'] ?> minutes after the booked time.</p>
  <?php foreach (legal_policies() as $line): ?><p><?= h($line) ?></p><?php endforeach; ?>

  <h2 id="condiciones">Terms of use</h2>
  <p>These terms govern the use of this website and its booking service. By booking or creating an account you accept them. Version of <?= h(date('j/n/Y', strtotime(LEGAL_VERSION))) ?>.</p>
  <p><strong>The service:</strong> the website lets you view the menu and request table bookings at <?= h($s['name']) ?>. Booking is free and nothing is charged through the website.</p>
  <p><strong>Bookings:</strong> a booking is a request until the restaurant sends you the WhatsApp message with the link and you tap “Confirm my booking”. If you made a mistake in any detail, make a new booking.
    You can cancel it free of charge at any time. We hold the table for <?= (int)$s['late_minutes'] ?> minutes after the booked time.
    The restaurant may decline or cancel a booking if there is no availability or for reasons beyond its control, and will let you know as soon as possible.</p>
  <p><strong>Your account:</strong> it is personal. Keep your password secret. We may suspend an account used to make fake or abusive bookings. You can delete it whenever you like from “My account”.</p>
  <p><strong>Menu and prices:</strong> they are for guidance only and may change. Allergens are shown as accurately as possible, but always tell us about any allergy before ordering.</p>
  <p><strong>Liability:</strong> we do our best to keep the website working properly and without interruptions, but we are not liable for technical failures beyond our control. Nothing in these terms limits your statutory rights as a consumer.</p>
  <p><strong>Law and courts:</strong> Spanish law applies. In the event of a dispute, the courts of your place of residence as a consumer will have jurisdiction.</p>

  <h2 id="privacidad">Privacy policy</h2>
  <p><strong>Data controller:</strong> <?= h($titular) ?><?= $s['owner_nif'] ? ', tax ID (NIF) ' . h($s['owner_nif']) : '' ?>. <?= h(trim($s['address'] . ', ' . $s['city'], ', ')) ?>. <strong>Contact:</strong> <?= h($contacto ?: '—') ?>.</p>
  <p><strong>What data we process and why:</strong></p>
  <ul>
    <li><strong>Bookings:</strong> name, phone, email (optional), day, time, number of people and notes. Used to manage your booking and tell you about changes. Legal basis: handling what you ask us for (art. 6.1.b GDPR). The phone number is required because we send you the confirmation link by WhatsApp; it is only used for that and to tell you about changes. We never send advertising or make sales calls.</li>
    <li><strong>Account (optional):</strong> name, email, phone (optional) and an irreversible fingerprint (hash) of your password. Used so you can book without typing your details and see and cancel your bookings. Legal basis: the account terms you accept when you create it (art. 6.1.b GDPR). Your account shows the bookings made with it or with the same email address; bookings made without an email cannot be added.</li>
    <li><strong>Statistics and advertising:</strong> only if you accept those cookies. Legal basis: your consent, which you can withdraw at any time.</li>
    <li><strong>Security log:</strong> when you accept these terms or change your password or details, with your IP address (encrypted) and browser. Legal basis: our legitimate interest in being able to prove your consent and protect your account.</li>
  </ul>
  <p><strong>Required data:</strong> to book we need your name and phone number; without them we cannot send you the confirmation. Email is optional.</p>
  <p><strong>Advertising and profiling:</strong> we do not send you promotional emails or share your data so that others can. The emails you receive are only about your booking or your account. We do not take automated decisions about you or build profiles.</p>
  <p><strong>Disclosures:</strong> we do not share your data with third parties unless required by law (for example, at a court’s request).</p>
  <p><strong>Providers that process data on our behalf (processors):</strong> Hostinger (Hostinger International Ltd.), which hosts the website and database on servers in the European Union; WhatsApp (WhatsApp Ireland Ltd., part of the Meta group), through which you receive the confirmation message from the restaurant’s WhatsApp<?php if ($google_mail): ?>; Google (Google Ireland Ltd., Gmail), through which we send emails about your booking or account<?php endif; ?><?php if ($analytics): ?>; Google (Analytics), only if you accept analytics cookies<?php endif; ?><?php if ($s['pixel_id'] !== ''): ?>; Meta Platforms Ireland Ltd. (Facebook and Instagram), only if you accept advertising cookies<?php endif; ?>. All of them have signed with us the agreement required by art. 28 GDPR (their data processing terms).</p>
  <?php $tr = array_merge(['WhatsApp'], $google_mail || $analytics ? ['Google'] : [], $s['pixel_id'] !== '' ? ['Meta'] : []);
        $trTxt = count($tr) > 1 ? implode(', ', array_slice($tr, 0, -1)) . ' and ' . end($tr) : 'WhatsApp'; ?>
  <p><strong>Transfers outside the EU:</strong> <?= $trTxt ?> may process data in the United States. These transfers are covered by the EU–US Data Privacy Framework (European Commission adequacy decision of July 2023) and by standard contractual clauses.<?php if ($analytics || $s['pixel_id'] !== ''): ?> If you do not accept analytics and advertising cookies, nothing is sent to Google or Meta from this website.<?php endif; ?></p>
  <p><strong>How long:</strong> the name, phone number, email and notes of each booking are deleted automatically 24 hours after the booked time. The email notice the restaurant receives for each booking only includes the name, day and number of people, and the restaurant regularly deletes the WhatsApp confirmation chat. Your account data is kept until you delete it; if you create an account and do not activate it, it is deleted after 7 days. The security log is kept for 3 years, the period during which your consent might have to be proven to the Spanish Data Protection Agency, and is then deleted.</p>
  <p><strong>Security:</strong> the connection is always encrypted (https). Your personal data is stored encrypted in the database and your password is never stored, only an irreversible fingerprint. If a security breach affected your data, we would notify the Spanish Data Protection Agency within 72 hours and you without delay.</p>
  <p><strong>Your rights:</strong> you can access, correct and delete your data, object to or restrict its use, take it to another service (portability), and withdraw any consent you have given (for example, for cookies) at any time without affecting what came before. From “My account” you can download all your data and delete your account yourself; for anything else, write to <?= h($contacto ?: 'our contact address') ?> saying which right you wish to exercise. We will reply within one month at the latest. If you think we are not handling your data properly, you can complain to the Spanish Data Protection Agency (<a href="https://www.aepd.es" target="_blank" rel="noopener">aepd.es</a>).</p>
  <p><strong>Minors:</strong> the website is not aimed at children under 14. If you are under 14, ask your parents or guardians to make the booking.</p>

  <h2 id="cookies">Cookies</h2>
  <p>Cookies are small files that the website stores in your browser. Necessary ones are always used; the rest only if you accept them in the cookie notice. You can change your choice at any time: <a href="#cookies" data-cookies>cookie settings</a>.</p>
  <table class="ck-tabla">
    <thead><tr><th>Cookie</th><th>Purpose</th><th>Owner</th><th>Duration</th></tr></thead>
    <tbody>
      <tr><td>cookie_consent</td><td>Remember your cookie choice (necessary). After this period we will ask you again.</td><td>This website</td><td>13 months</td></tr>
      <tr><td>cliente_sesion</td><td>Keep you signed in to your account (necessary)</td><td>This website</td><td>60 days</td></tr>
      <tr><td>idioma</td><td>Remember the language you choose with the flags (necessary, only if you change language)</td><td>This website</td><td>1 year</td></tr>
      <?php if ($s['ga_id'] !== '' || $s['gtm_id'] !== ''): ?>
      <tr><td>_ga, _ga_*</td><td>Anonymous visit statistics (analytics, only if you accept)</td><td>Google Analytics</td><td>2 years</td></tr>
      <?php endif; ?>
      <?php if ($s['gtm_id'] !== ''): ?>
      <tr><td>_gcl_*</td><td>Measure Google ads (advertising, only if you accept)</td><td>Google</td><td>3 months</td></tr>
      <?php endif; ?>
      <?php if ($s['pixel_id'] !== ''): ?>
      <tr><td>_fbp, _fbc</td><td>Measure ads on Facebook and Instagram (advertising, only if you accept)</td><td>Meta</td><td>3 months</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?php if ($mapa): ?><p>If you press “Show the map”, Google Maps is loaded, which may use its own cookies; until you press it, nothing connects to Google.</p><?php endif; ?>
  <p>Fonts and photos are served from our own server, so your browser does not connect to other companies to load them.</p>
  <p>If you do not accept optional cookies, we will not ask you again until the period above has passed, and the website works just the same.</p>
  <p>You can also delete or block cookies in your browser settings.</p>

