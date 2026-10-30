/*
 * SDK de la web del restaurante. Lo carga body_end() en todas las páginas. Hace tres cosas:
 *
 * 1. Ventana de reserva: cualquier elemento con data-book la abre (no hace falta nada más).
 * 2. Cuenta del cliente: formularios con data-api="c_login|c_register|c_profile|c_delete|c_forgot|c_reset", botones
 *    data-cancel="ID", data-confirm-res="ID", data-logout y data-resend. Ver TEMA.md.
 * 3. Instalar como app (data-install, data-install-ios, data-install-help, data-install-done).
 *
 * Además deja window.Restaurante con la API para temas que quieran hacer su propia interfaz:
 *   Restaurante.api('slots&date=2026-11-02').then(d => …)
 *   Restaurante.abrirReserva()
 *   document.addEventListener('restaurante:reserva', e => console.log(e.detail))
 */
(function () {
  'use strict';
  var R = window.RESTAURANT || {};
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  /* Idioma: window.I18N trae las traducciones del idioma actual (vacío en español) */
  var I = window.I18N || {};
  var LANG = document.documentElement.lang.slice(0, 2);
  function T(str) {
    var x = I[str] || str, args = Array.prototype.slice.call(arguments, 1), k = 0;
    return args.length ? x.replace(/%[sd]/g, function () { return String(args[k++]); }) : x;
  }
  var DIAS_L = I._days ? [I._days[6]].concat(I._days.slice(0, 6)) : ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  var DIAS = I._days ? DIAS_L.map(function (d) { return d.slice(0, 3); }) : ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
  var MESES = I._months || ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2], 12); }
  function addDays(s, n) { var d = parse(s); d.setDate(d.getDate() + n); return iso(d); }
  function longDay(s) {
    var d = parse(s), dn = DIAS_L[d.getDay()], n = d.getDate(), m = MESES[d.getMonth()];
    return LANG === 'en' ? dn + ' ' + n + ' ' + m : LANG === 'pt' ? dn + ', ' + n + ' de ' + m : dn + ' ' + n + ' de ' + m;
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /** Llama a la API: api('ruta') hace GET; api('ruta', datos) hace POST con JSON. Devuelve una promesa con data. */
  function api(route, body) {
    var opts = { credentials: 'same-origin', headers: {} };
    if (body !== undefined) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch('api/?r=' + route, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: T('El servidor no responde bien. Inténtalo de nuevo.') }; });
    }, function () {
      return { ok: false, error: T('Sin conexión. Revisa internet e inténtalo de nuevo.') };
    }).then(function (j) {
      if (!j.ok) throw new Error(j.error || T('Algo ha fallado'));
      return j.data;
    });
  }

  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'r-toast';
    t.setAttribute('role', 'status');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3200);
  }

  function emit(name, detail) {
    try { document.dispatchEvent(new CustomEvent('restaurante:' + name, { detail: detail })); } catch (e) { /* navegador antiguo */ }
  }

  /* ---------- Reserva en 3 pasos ---------- */
  var dlg = $('#bk');
  var B = { people: 2, date: R.today, time: '', slots: [] };

  function isClosed(day) {
    if ((R.closed || []).indexOf(day) !== -1) return true;
    var wd = String(((parse(day).getDay() + 6) % 7) + 1);
    return !((R.hours || {})[wd] || []).length;
  }

  function summary() {
    if (B.pane === 0) { $('#bk-sum').textContent = ''; return; }
    var parts = [B.people + ' ' + (B.people === 1 ? T('persona') : T('personas'))];
    if (B.date) parts.push(longDay(B.date));
    if (B.time) parts.push(B.time);
    $('#bk-sum').textContent = parts.join(' · ');
  }

  function showPane(n) {
    B.pane = n;
    $$('.bk-pane', dlg).forEach(function (p) { p.hidden = p.dataset.pane !== String(n); });
    $('#bk-step').textContent = n === 0 ? '' : n <= 3 ? T('Paso %d de 3', n) : T('Hecho');
    $('#bk-title').textContent = T(['¿Cómo quieres reservar?', 'Reservar mesa', 'Elige la hora', 'Tus datos', 'Petición enviada'][n]);
    summary();
    if (n === 0) updateCall();
    var focusable = $('.bk-pane:not([hidden]) button, .bk-pane:not([hidden]) input', dlg);
    if (n > 1 && focusable) focusable.focus({ preventScroll: true });
    if (n === 2) loadSlots();
  }

  /* ---------- ¿Está abierto ahora? (hora del restaurante, no la del móvil) ---------- */
  var NOW0 = R.now && R.now.match(/(\d+)-(\d+)-(\d+) (\d+):(\d+)/);
  var OFFSET = NOW0 ? Date.UTC(+NOW0[1], +NOW0[2] - 1, +NOW0[3], +NOW0[4], +NOW0[5]) - Date.now() : null;
  function restNow() {
    if (OFFSET === null) { var n = new Date(); return { day: iso(n), time: pad(n.getHours()) + ':' + pad(n.getMinutes()) }; }
    var d = new Date(Date.now() + OFFSET);
    return { day: d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()), time: pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) };
  }
  function rangesOf(day) {
    if (isClosed(day)) return [];
    return (R.hours || {})[String(((parse(day).getDay() + 6) % 7) + 1)] || [];
  }
  function whenText(k, time, day) {
    return k === 0 ? T('hoy a las %s', time) : k === 1 ? T('mañana a las %s', time) : T('el %s a las %s', DIAS_L[parse(day).getDay()], time);
  }
  function callStatus() {
    var n = restNow(), r = rangesOf(n.day), i;
    for (i = 0; i < r.length; i++) if (n.time >= r[i][0] && n.time < r[i][1]) return { open: true };
    for (i = 0; i < r.length; i++) if (r[i][0] > n.time) return { open: false, next: whenText(0, r[i][0], n.day) };
    for (var k = 1; k <= 14; k++) {
      var day = addDays(n.day, k), rr = rangesOf(day);
      if (rr.length) return { open: false, next: whenText(k, rr[0][0], day) };
    }
    return { open: false, next: '' };
  }
  function updateCall() {
    var a = $('#bk-call'), txt = $('#bk-call-txt');
    if (!a) return;
    var st = callStatus();
    a.classList.toggle('is-off', !st.open);
    if (st.open) {
      a.setAttribute('href', a.dataset.href);
      a.removeAttribute('aria-disabled');
      txt.textContent = T('Estamos abiertos ahora: %s', R.phone);
    } else {
      a.removeAttribute('href');
      a.setAttribute('aria-disabled', 'true');
      txt.textContent = st.next ? T('Ahora estamos cerrados. Podrás llamarnos %s.', st.next) : T('Ahora estamos cerrados.');
    }
  }

  function renderDays() {
    var box = $('#bk-days');
    box.innerHTML = '';
    for (var i = 0; i < 14; i++) {
      var day = addDays(R.today, i);
      var d = parse(day);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'bk-day';
      b.setAttribute('role', 'radio');
      b.dataset.day = day;
      b.disabled = isClosed(day);
      b.setAttribute('aria-checked', String(day === B.date));
      b.setAttribute('aria-label', longDay(day) + (b.disabled ? ', ' + T('cerrado') : ''));
      b.innerHTML = '<small>' + (i === 0 ? T('hoy') : i === 1 ? T('mañana') : DIAS[d.getDay()]) + '</small><b>' + d.getDate() + '</b><small>' + MESES[d.getMonth()].slice(0, 3) + '</small>';
      box.appendChild(b);
    }
  }

  function pickDate(day) {
    B.date = day;
    B.time = '';
    $$('.bk-day', dlg).forEach(function (b) { b.setAttribute('aria-checked', String(b.dataset.day === day)); });
    $('#bk-date').value = day;
    summary();
  }

  function slotButtons(list) {
    return '<div class="bk-slot-grid">' + list.map(function (s) {
      var off = s.left < B.people;
      return '<button type="button" class="bk-slot" data-time="' + s.time + '" aria-pressed="' + (s.time === B.time) + '"' + (off ? ' disabled aria-label="' + s.time + ', ' + T('completo') + '"' : '') + '>' + s.time + '</button>';
    }).join('') + '</div>';
  }

  function renderSlots() {
    var box = $('#bk-slots');
    var free = B.slots.filter(function (s) { return s.left >= B.people; });
    if (!free.length) {
      box.innerHTML = '<p class="bk-msg">' + esc(B.reason || T('No queda sitio para %d personas ese día.', B.people)) + ' ' + (R.phone ? T('Prueba otro día o llámanos.') : T('Prueba otro día.')) + '</p>';
    } else {
      var lunch = B.slots.filter(function (s) { return s.time < '17:00'; });
      var dinner = B.slots.filter(function (s) { return s.time >= '17:00'; });
      box.innerHTML = (lunch.length ? '<h3>' + T('Comida') + '</h3>' + slotButtons(lunch) : '') + (dinner.length ? '<h3>' + T('Cena') + '</h3>' + slotButtons(dinner) : '');
    }
    $('#bk-to3').disabled = !B.time;
  }

  function loadSlots() {
    var box = $('#bk-slots');
    box.innerHTML = '<p class="bk-msg">' + T('Buscando horas libres…') + '</p>';
    $('#bk-to3').disabled = true;
    api('slots&date=' + encodeURIComponent(B.date)).then(function (d) {
      B.slots = d.slots;
      B.reason = d.open ? '' : d.reason + '.';
      if (B.time && !B.slots.some(function (s) { return s.time === B.time && s.left >= B.people; })) B.time = '';
      renderSlots();
    }).catch(function (e) {
      box.innerHTML = '<p class="bk-msg">' + esc(e.message) + '</p>';
    });
  }

  function resetBooking() {
    B.time = '';
    var f = $('#bk-form');
    f.notes.value = '';
    f.privacy.checked = false;
    $('#bk-err').textContent = '';
    $('#bk-send').disabled = false;
    $('#bk-send').textContent = T('Enviar petición');
  }

  function openBooking() {
    if (!dlg) return;
    if (!$('.bk-pane[data-pane="4"]', dlg).hidden) resetBooking();
    renderDays();
    if (isClosed(B.date)) {
      for (var i = 0; i < 14; i++) { var d = addDays(R.today, i); if (!isClosed(d)) { pickDate(d); break; } }
    }
    $('#bk-date').min = R.today;
    $('#bk-date').max = addDays(R.today, R.daysAhead || 60);
    $('#bk-date').value = B.date;
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
    showPane($('.bk-pane[data-pane="0"]', dlg) ? 0 : 1);
    emit('reserva-abierta', {});
  }

  if (dlg) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-book]');
      if (!b) return;
      e.preventDefault();
      emit('antes-de-reservar', {});
      openBooking();
    });
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg || e.target.closest('[data-close]')) { dlg.close(); return; }
      if (e.target.closest('.bk-opt.is-off')) { e.preventDefault(); return; }
      var day = e.target.closest('.bk-day');
      if (day && !day.disabled) return pickDate(day.dataset.day);
      var slot = e.target.closest('.bk-slot');
      if (slot && !slot.disabled) {
        B.time = slot.dataset.time;
        $$('.bk-slot', dlg).forEach(function (s) { s.setAttribute('aria-pressed', String(s === slot)); });
        $('#bk-to3').disabled = false;
        summary();
        return;
      }
      var nx = e.target.closest('[data-next]');
      if (nx && !nx.disabled) showPane(+nx.dataset.next);
    });
    dlg.addEventListener('change', function (e) {
      if (e.target.name === 'people') {
        B.people = +e.target.value;
        var more = $('[name=people_more]', dlg);
        if (more) { more.value = ''; more.classList.remove('is-on'); }
        summary();
      } else if (e.target.name === 'people_more' && e.target.value) {
        B.people = +e.target.value;
        $$('[name=people]', dlg).forEach(function (r) { r.checked = false; });
        e.target.classList.add('is-on');
        summary();
      } else if (e.target.id === 'bk-date' && e.target.value) {
        if (isClosed(e.target.value)) { toast(T('Ese día el restaurante está cerrado')); e.target.value = B.date; return; }
        pickDate(e.target.value);
      }
    });
    var editWho = $('#bk-edit-who');
    if (editWho) editWho.addEventListener('click', function () { $('#bk-fields').hidden = false; editWho.parentNode.hidden = true; });

    $('#bk-send').addEventListener('click', function () {
      var f = $('#bk-form');
      var err = $('#bk-err');
      var data = {
        people: B.people, date: B.date, time: B.time,
        name: f.name.value.trim(), phone: f.phone.value.trim(), email: f.email.value.trim(),
        notes: f.notes.value.trim(), website: f.website.value, privacy: f.privacy.checked ? 1 : 0
      };
      err.textContent = '';
      if (!data.phone && R.customer) { $('#bk-fields').hidden = false; }
      if (!R.customer || !$('#bk-fields').hidden) {
        if (!data.name && !data.phone) { err.textContent = T('Faltan tu nombre y un teléfono móvil.'); $('#bk-fields').hidden = false; return; }
        if (!data.name) { err.textContent = T('Falta tu nombre.'); $('#bk-fields').hidden = false; return; }
        if (!data.phone) { err.textContent = T('Falta un teléfono móvil.'); $('#bk-fields').hidden = false; return; }
      }
      if (!data.privacy) { err.textContent = T('Marca la casilla para aceptar las políticas.'); return; }
      var btn = this;
      btn.disabled = true;
      btn.textContent = T('Enviando…');
      api('reserve', data).then(function (r) {
        $('#bk-done-t').textContent = T('Petición realizada');
        $('#bk-done-msg').textContent = r.message;
        var more = $('#bk-done-more');
        var wait = T('Esperando confirmación. Mientras no abras el enlace del WhatsApp y pulses «Confirmar mi reserva», la reserva queda pendiente.');
        if (r.linked) more.innerHTML = T('Esperando confirmación. La verás en <a href="cuenta.php">tu cuenta</a>, donde también podrás cancelarla.');
        else if (r.email) more.textContent = wait + ' ' + T('Si creas una cuenta con el mismo email, también la verás allí.');
        else more.textContent = wait + ' ' + T('Como no has puesto email, esta reserva no aparecerá en ninguna cuenta.');
        showPane(4);
        emit('reserva', r);
      }).catch(function (e) {
        err.textContent = e.message;
        btn.disabled = false;
        btn.textContent = T('Enviar petición');
      });
    });

    if (location.hash === '#reservar' && /[?&]app=1/.test(location.search)) setTimeout(openBooking, 300);
  }

  /* ---------- Ampliar fotos ---------- */
  // Cualquier <img data-zoom> se amplía al tocarla. Opcionales: data-zoom-src (foto grande), data-zoom-caption (pie)
  // y data-zoom-group="nombre" (las flechas pasan solo entre fotos del mismo grupo; sin grupo, todas las de la página).
  var ZOOM = 'img[data-zoom]';
  var lb, lbImg, lbCap, lbList = [], lbI = 0, lbX0 = null;

  function zoomCaption(img) {
    if (img.dataset.zoomCaption) return img.dataset.zoomCaption;
    var fig = img.closest('figure'), fc = fig && $('figcaption', fig);
    return fc ? fc.textContent.trim() : (img.alt || '');
  }
  function zoomSrc(img) {
    if (img.dataset.zoomSrc) return img.dataset.zoomSrc;
    var ss = img.getAttribute('srcset');
    if (!ss) return img.currentSrc || img.src;
    var best = '', w = 0;
    ss.split(/,\s+/).forEach(function (c) {
      var p = c.trim().split(/\s+/), n = parseInt(p[1], 10) || 0;
      if (p[0] && n >= w) { w = n; best = p[0]; }
    });
    return best || img.src;
  }
  function lbShow(i) {
    lbI = (i + lbList.length) % lbList.length;
    var img = lbList[lbI];
    lbImg.src = zoomSrc(img);
    lbImg.alt = img.alt || zoomCaption(img);
    lbCap.textContent = zoomCaption(img);
  }
  function lbBuild() {
    lb = document.createElement('dialog');
    lb.className = 'r-lb';
    lb.setAttribute('aria-label', T('Ampliar foto'));
    lb.innerHTML = '<div class="r-lb-in"><button type="button" class="r-lb-x" aria-label="' + T('Cerrar') + '">×</button>' +
      '<button type="button" class="r-lb-nav r-lb-prev" aria-label="' + T('Anterior') + '">‹</button>' +
      '<figure><img alt=""><figcaption></figcaption></figure>' +
      '<button type="button" class="r-lb-nav r-lb-next" aria-label="' + T('Siguiente') + '">›</button></div>';
    document.body.appendChild(lb);
    lbImg = $('img', lb);
    lbCap = $('figcaption', lb);
    lb.addEventListener('click', function (e) {
      if (e.target.closest('.r-lb-prev')) return lbShow(lbI - 1);
      if (e.target.closest('.r-lb-next')) return lbShow(lbI + 1);
      lb.close();
    });
    lb.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') lbShow(lbI - 1);
      else if (e.key === 'ArrowRight') lbShow(lbI + 1);
    });
    lb.addEventListener('pointerdown', function (e) { lbX0 = e.clientX; });
    lb.addEventListener('pointerup', function (e) {
      if (lbX0 !== null && lbList.length > 1 && Math.abs(e.clientX - lbX0) > 50) lbShow(lbI + (e.clientX < lbX0 ? 1 : -1));
      lbX0 = null;
    });
    lb.addEventListener('close', function () { lbImg.removeAttribute('src'); });
  }
  function zoomOpen(img) {
    if (!lb) lbBuild();
    var g = img.dataset.zoomGroup || '';
    lbList = $$(ZOOM).filter(function (x) { return (x.dataset.zoomGroup || '') === g && x.offsetParent !== null; });
    var i = lbList.indexOf(img);
    if (i < 0) { lbList = [img]; i = 0; }
    lb.classList.toggle('r-lb-one', lbList.length < 2);
    lbShow(i);
    if (typeof lb.showModal === 'function') lb.showModal(); else lb.setAttribute('open', '');
  }
  function zoomPrepare() {
    $$(ZOOM).forEach(function (img) {
      if (img.getAttribute('role') === 'button') return;
      img.setAttribute('tabindex', '0');
      img.setAttribute('role', 'button');
      var c = zoomCaption(img);
      img.setAttribute('aria-label', T('Ampliar foto') + (c ? ': ' + c : ''));
    });
  }
  zoomPrepare();
  document.addEventListener('click', function (e) {
    var img = e.target.closest && e.target.closest(ZOOM);
    if (img) zoomOpen(img);
  });
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches(ZOOM)) { e.preventDefault(); zoomOpen(e.target); }
  });

  /* ---------- Cuenta del cliente ---------- */
  // Pestañas: un contenedor con data-tabs y botones role="tab" con aria-controls
  $$('[data-tabs] [role=tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      $$('[role=tab]', tab.closest('[data-tabs]')).forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', String(on));
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
    });
  });

  // Formularios normales que piden confirmación antes de enviarse: <form method="post" data-confirm="¿Seguro…?">
  // (sustituye a onsubmit="…", que la Content-Security-Policy no deja ejecutar)
  $$('form[data-confirm]:not([data-api])').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.dataset.confirm)) e.preventDefault();
    });
  });

  // Formularios: <form data-api="c_login"> … <p data-error></p> <button type="submit">
  $$('form[data-api]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (f.dataset.confirm && !confirm(f.dataset.confirm)) return;
      var data = {};
      new FormData(f).forEach(function (v, k) { data[k] = v; });
      var btn = $('button[type=submit]', f), err = $('[data-error], .form-err', f), label = btn.textContent;
      if (err) err.textContent = '';
      btn.disabled = true;
      btn.textContent = T('Un momento…');
      api(f.dataset.api, data).then(function (r) {
        emit(f.dataset.api, r);
        if (f.dataset.api === 'c_register' && r && r.pending) {
          // Registro hecho: falta abrir el enlace del email. Se muestra el bloque data-registro-ok.
          f.hidden = true;
          $$('[data-tabs]').forEach(function (t) { t.hidden = true; });
          var ok = $('[data-registro-ok]');
          if (ok) ok.hidden = false;
          $$('[data-registro-email]').forEach(function (el) { el.textContent = r.email; });
          $$('[data-resend]').forEach(function (b) { b.dataset.email = r.email; });
          return;
        }
        if (f.dataset.api === 'c_forgot') {
          // Misma respuesta exista o no la cuenta: se enseña el aviso de «revisa tu email»
          f.hidden = true;
          var sent = $('[data-forgot-ok]');
          if (sent) sent.hidden = false; else toast(T('Revisa tu email'));
          return;
        }
        if (f.dataset.api === 'c_profile') {
          toast(T('Datos guardados'));
          btn.disabled = false;
          btn.textContent = label;
          $$('input[type=password]', f).forEach(function (i) { i.value = ''; });
        } else {
          location.href = f.dataset.redirect || (f.dataset.api === 'c_delete' ? './' : 'cuenta.php');
        }
      }).catch(function (ex) {
        if (err) err.textContent = ex.message; else toast(ex.message);
        btn.disabled = false;
        btn.textContent = label;
      });
    });
  });

  $$('[data-resend]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      api('c_resend', { email: b.dataset.email }).then(function () { toast(T('Email enviado otra vez')); }, function (e) { toast(e.message); })
        .then(function () { setTimeout(function () { b.disabled = false; }, 30000); });
    });
  });

  $$('[data-logout], #logout').forEach(function (b) {
    b.addEventListener('click', function () {
      api('c_logout', {}).then(function () { location.href = './'; }, function (e) { toast(e.message); });
    });
  });

  $$('[data-confirm-res]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      api('c_confirm', { id: +b.dataset.confirmRes }).then(function () {
        toast(T('Reserva confirmada'));
        setTimeout(function () { location.reload(); }, 700);
      }).catch(function (e) { toast(e.message); b.disabled = false; });
    });
  });

  $$('[data-cancel]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm(b.dataset.when ? T('¿Cancelar la reserva del %s?', b.dataset.when) : T('¿Cancelar la reserva?'))) return;
      b.disabled = true;
      api('c_cancel', { id: +b.dataset.cancel }).then(function () {
        toast(T('Reserva cancelada'));
        setTimeout(function () { location.reload(); }, 700);
      }).catch(function (e) { toast(e.message); b.disabled = false; });
    });
  });

  /* ---------- Instalar como app ---------- */
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  var installBtn = $('[data-install]'), iosTip = $('[data-install-ios]'), help = $('[data-install-help]'), done = $('[data-install-done]');
  var deferred = null;
  if (done && standalone) { done.hidden = false; if (help) help.hidden = true; }
  else if (iosTip && /iphone|ipad|ipod/i.test(navigator.userAgent)) { iosTip.hidden = false; if (help) help.hidden = true; }
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (installBtn) { installBtn.hidden = false; if (help) help.hidden = true; }
  });
  if (installBtn) installBtn.addEventListener('click', function () {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.then(function (c) {
      if (c.outcome === 'accepted') { installBtn.hidden = true; if (done) done.hidden = false; }
      deferred = null;
    });
  });
  window.addEventListener('appinstalled', function () { if (installBtn) installBtn.hidden = true; if (done) done.hidden = false; });

  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('sw.js').catch(function () { /* sin modo app */ });
  }

  // Borrar mis datos: formularios de borrar_form()
  document.addEventListener('submit', function (e) {
    var f = e.target.closest && e.target.closest('form[data-erase]');
    if (!f) return;
    e.preventDefault();
    var btn = f.querySelector('button[type=submit]'), msg = f.querySelector('.r-erase-msg');
    btn.disabled = true;
    msg.classList.remove('is-err');
    api('erase_request', { contact: f.contact.value, website: f.website.value }).then(function (d) {
      msg.textContent = d.message || '';
      f.contact.value = '';
    }, function (err) {
      msg.textContent = err.message;
      msg.classList.add('is-err');
    }).then(function () { btn.disabled = false; });
  });

  window.Restaurante = { api: api, toast: toast, t: T, abrirReserva: openBooking, ampliarFotos: zoomPrepare, datos: R };
})();
