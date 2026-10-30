/*
 * Aviso de cookies (RGPD / guía de la AEPD).
 * - Nada de analítica ni publicidad se carga hasta que el visitante acepta.
 * - «Rechazar» está al mismo nivel que «Aceptar».
 * - La elección se guarda 13 meses y se puede cambiar desde el pie («Configurar cookies»).
 * - Usa el modo de consentimiento v2 de Google.
 */
(function () {
  'use strict';
  // Traducciones del idioma actual (las pone head_tags en window.I18N)
  function L(x) { return (window.I18N || {})[x] || x; }
  var T = window.SITE_TRACKING || {};
  var NAME = 'cookie_consent';
  var VERSION = 1;

  function read() {
    var m = document.cookie.match(/(?:^|;\s*)cookie_consent=([^;]+)/);
    if (!m) return null;
    try {
      var c = JSON.parse(decodeURIComponent(m[1]));
      return c && c.v === VERSION ? c : null;
    } catch (e) { return null; }
  }

  function save(c) {
    c.v = VERSION;
    c.t = new Date().toISOString().slice(0, 10);
    var exp = new Date(Date.now() + 395 * 864e5).toUTCString();
    document.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(c)) + '; expires=' + exp + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }

  function loadScript(src) {
    var s = document.createElement('script');
    s.async = true;
    s.src = src;
    document.head.appendChild(s);
  }

  var loaded = { ga: false, gtm: false, pixel: false };
  function apply(c) {
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', {
        analytics_storage: c.analytics ? 'granted' : 'denied',
        ad_storage: c.marketing ? 'granted' : 'denied',
        ad_user_data: c.marketing ? 'granted' : 'denied',
        ad_personalization: c.marketing ? 'granted' : 'denied'
      });
    }
    if (c.analytics && T.ga && !loaded.ga) {
      loaded.ga = true;
      loadScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(T.ga));
      window.gtag('js', new Date());
      window.gtag('config', T.ga, { anonymize_ip: true });
    }
    if ((c.analytics || c.marketing) && T.gtm && !loaded.gtm) {
      loaded.gtm = true;
      window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      loadScript('https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(T.gtm));
    }
    if (c.marketing && T.pixel && !loaded.pixel) {
      loaded.pixel = true;
      /* Código oficial del píxel de Meta */
      !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s); }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      window.fbq('init', T.pixel);
      window.fbq('track', 'PageView');
    }
  }

  // Si retira un permiso que ya había dado, se borran esas cookies y se recarga
  function wipe(prev, c) {
    var gone = (prev && prev.analytics && !c.analytics) || (prev && prev.marketing && !c.marketing);
    if (!gone) return false;
    var host = location.hostname.replace(/^www\./, '');
    document.cookie.split(';').forEach(function (kv) {
      var k = kv.split('=')[0].trim();
      if (/^(_ga|_gid|_gat|_fbp|_fbc|_gcl)/.test(k)) {
        ['', '; domain=' + host, '; domain=.' + host].forEach(function (d) {
          document.cookie = k + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + d;
        });
      }
    });
    return true;
  }

  var box = null;
  function close() { if (box) { box.remove(); box = null; } }

  function choose(c) {
    var prev = read();
    save(c);
    close();
    if (wipe(prev, c)) { location.reload(); return; }
    apply(c);
  }

  function show(settings) {
    close();
    var c = read() || { analytics: false, marketing: false };
    box = document.createElement('div');
    box.className = 'ck';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'false');
    box.setAttribute('aria-labelledby', 'ck-t');
    box.innerHTML =
      '<p class="ck-t" id="ck-t">Cookies</p>' +
      '<p class="ck-txt">' + L(T.pixel || T.gtm
        ? 'Usamos cookies necesarias para que la web funcione y, si nos das permiso, cookies de análisis para saber cómo se usa la web y mejorarla, y de publicidad para medir nuestros anuncios.'
        : 'Usamos cookies necesarias para que la web funcione y, si nos das permiso, cookies de análisis para saber cómo se usa la web y mejorarla.') +
      ' ' + L('Puedes cambiar tu elección cuando quieras.') + ' <a href="legal.php#cookies">' + L('Más información') + '</a>.</p>' +
      '<div class="ck-opts"' + (settings ? '' : ' hidden') + '>' +
        '<label class="ck-opt"><span><strong>' + L('Necesarias') + '</strong><small>' + L('Mantienen tu sesión y recuerdan esta elección. Siempre activas.') + '</small></span><input type="checkbox" checked disabled></label>' +
        '<label class="ck-opt"><span><strong>' + L('Análisis') + '</strong><small>' + L('Estadísticas de visitas (Google Analytics).') + '</small></span><input type="checkbox" name="analytics"' + (c.analytics ? ' checked' : '') + '></label>' +
        '<label class="ck-opt"><span><strong>' + L('Publicidad') + '</strong><small>' + (navigator.globalPrivacyControl === true ? L('Desactivada: tu navegador pide no compartir datos (GPC).') : L('Medir anuncios en Google, Instagram o Facebook. Implica compartir datos con esas empresas.')) + '</small></span><input type="checkbox" name="marketing"' + (c.marketing && navigator.globalPrivacyControl !== true ? ' checked' : '') + (navigator.globalPrivacyControl === true ? ' disabled' : '') + '></label>' +
      '</div>' +
      '<div class="ck-btns">' +
        '<button type="button" class="ck-btn ck-btn-main" data-ck="reject">' + L('Rechazar todas') + '</button>' +
        '<button type="button" class="ck-btn" data-ck="config"' + (settings ? ' hidden' : '') + '>' + L('Configurar') + '</button>' +
        '<button type="button" class="ck-btn" data-ck="save"' + (settings ? '' : ' hidden') + '>' + L('Guardar elección') + '</button>' +
        '<button type="button" class="ck-btn ck-btn-main" data-ck="accept">' + L('Aceptar todas') + '</button>' +
      '</div>';
    document.body.appendChild(box);
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-ck]');
      if (!b) return;
      var a = b.dataset.ck;
      if (a === 'accept') choose({ analytics: true, marketing: true });
      else if (a === 'reject') choose({ analytics: false, marketing: false });
      else if (a === 'config') {
        box.querySelector('.ck-opts').hidden = false;
        b.hidden = true;
        box.querySelector('[data-ck="save"]').hidden = false;
      } else if (a === 'save') {
        choose({ analytics: box.querySelector('[name=analytics]').checked, marketing: box.querySelector('[name=marketing]').checked });
      }
    });
    var first = box.querySelector('[data-ck="accept"]');
    if (settings && first) first.focus();
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-cookies]')) { e.preventDefault(); show(true); }
  });

  // Global Privacy Control: si el navegador pide no compartir datos, la publicidad queda desactivada
  var gpc = navigator.globalPrivacyControl === true;
  function respectGpc(c) { if (gpc && c) c.marketing = false; return c; }
  var _choose = choose;
  choose = function (c) { _choose(respectGpc(c)); };

  var c = respectGpc(read());
  if (c) apply(c);
  else if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { show(false); });
  else show(false);
})();
