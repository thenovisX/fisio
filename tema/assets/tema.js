/*
 * JavaScript del tema Fibra. La reserva y la cuenta las hace el SDK (sdk/restaurante.js); aquí solo va el movimiento:
 *  1. Menú del móvil.
 *  2. Scroll suave con Lenis, conectado a GSAP ScrollTrigger.
 *  3. Cursor propio con retraso (lerp) y botones magnéticos.
 *  4. Títulos letra a letra (SplitType) con física elástica.
 *  5. Portada: fondo de fibras con un shader de Three.js que reacciona al ratón.
 *  6. Scroll jacking: tratamientos en una pista horizontal fijada y pasos que emergen desde la profundidad.
 * Con «reducir movimiento» activado en el sistema, o si falta alguna librería, la web se ve entera y quieta.
 */
(function () {
  'use strict';

  /* ---------- 1. Menú del móvil ---------- */
  var btn = document.querySelector('[data-menu]');
  var nav = document.getElementById('menu');
  function menu(open) {
    if (!btn || !nav) return;
    nav.classList.toggle('abierto', open);
    btn.setAttribute('aria-expanded', String(open));
  }
  if (btn && nav) {
    btn.addEventListener('click', function () { menu(!nav.classList.contains('abierto')); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) menu(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') menu(false); });
  }
  document.addEventListener('restaurante:antes-de-reservar', function () { menu(false); });

  var root = document.documentElement;
  var quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var gsap = window.gsap, ST = window.ScrollTrigger;
  if (quieto || !gsap || !ST) {
    document.body.classList.add('sin-animacion');
    root.classList.add('sin-webgl');
    return;
  }
  gsap.registerPlugin(ST);

  /* ---------- 2. Lenis + ScrollTrigger ---------- */
  var lenis = null;
  if (window.Lenis) {
    lenis = new window.Lenis({ duration: 1.15, easing: function (t) { return Math.min(1, 1.001 - Math.pow(2, -10 * t)); }, smoothWheel: true });
    lenis.on('scroll', ST.update);
    gsap.ticker.add(function (time) { lenis.raf(time * 1000); });
    gsap.ticker.lagSmoothing(0);
    // Los enlaces a #secciones también van con el scroll suave
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href^="#"], a[href^="./#"]');
      if (!a || a.hasAttribute('data-book')) return;
      var id = a.getAttribute('href').replace('./', '');
      var el = id.length > 1 && document.querySelector(id);
      if (!el) return;
      e.preventDefault();
      lenis.scrollTo(el, { offset: 0, duration: 1.4 });
    });
    // La ventana de cita y el aviso de cookies tienen su propio scroll: Lenis no los toca y se para mientras están abiertos
    var dlg = document.getElementById('bk');
    if (dlg) {
      dlg.setAttribute('data-lenis-prevent', '');
      document.addEventListener('restaurante:reserva-abierta', function () { lenis.stop(); });
      dlg.addEventListener('close', function () { lenis.start(); });
    }
  }

  /* ---------- 3. Cursor y botones magnéticos (solo con ratón) ---------- */
  var conRaton = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var cursor = document.querySelector('.cursor');
  if (conRaton && cursor) {
    document.body.classList.add('tiene-cursor');
    var mx = innerWidth / 2, my = innerHeight / 2, cx = mx, cy = my;
    addEventListener('pointermove', function (e) { mx = e.clientX; my = e.clientY; }, { passive: true });
    gsap.ticker.add(function () {
      cx += (mx - cx) * 0.18;
      cy += (my - cy) * 0.18;
      cursor.style.transform = 'translate3d(' + cx + 'px,' + cy + 'px,0)';
    });
    document.addEventListener('pointerover', function (e) {
      cursor.classList.toggle('es-grande', !!e.target.closest('a, button, label, h1, .manifiesto-t, .tarjeta'));
    });
    // Los botones magnéticos siguen un poco al ratón y vuelven con un rebote elástico
    document.querySelectorAll('[data-magnet]').forEach(function (el) {
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect();
        gsap.to(el, { x: (e.clientX - r.left - r.width / 2) * 0.35, y: (e.clientY - r.top - r.height / 2) * 0.35, duration: 0.4, ease: 'power3.out' });
      });
      el.addEventListener('pointerleave', function () { gsap.to(el, { x: 0, y: 0, duration: 1.1, ease: 'elastic.out(1, 0.35)' }); });
    });
  }

  /* ---------- 4. Títulos letra a letra ---------- */
  var Split = window.SplitType;
  function letras(el) {
    if (!Split) return [];
    return new Split(el, { types: 'words,chars', tagName: 'span' }).chars || [];
  }
  document.querySelectorAll('[data-split]').forEach(function (el) {
    var chars = letras(el);
    if (!chars.length) return;
    gsap.from(chars, {
      yPercent: 110, rotateX: -80, opacity: 0, transformOrigin: '50% 100%',
      duration: 1.4, ease: 'elastic.out(1, 0.55)', stagger: 0.025,
      scrollTrigger: { trigger: el, start: 'top 85%' },
    });
  });

  var esInicio = document.body.classList.contains('inicio');
  if (!esInicio) return;

  /* ---------- Portada ---------- */
  var titulo = document.querySelector('[data-hero-title]');
  if (titulo) {
    var chars = [];
    titulo.querySelectorAll('span, em').forEach(function (l) { chars = chars.concat(letras(l)); });
    if (chars.length) {
      gsap.set(titulo, { perspective: 800 });
      gsap.from(chars, {
        rotateX: function () { return gsap.utils.random(-110, 110); },
        rotateY: function () { return gsap.utils.random(-70, 70); },
        yPercent: 120, opacity: 0, transformOrigin: '50% 60% -40px',
        duration: 2, ease: 'elastic.out(1, 0.45)', stagger: { each: 0.045, from: 'start' }, delay: 0.15,
      });
    }
    gsap.from('.hero-kicker span, .hero-frase, .hero-scroll', { y: 24, opacity: 0, duration: 1.1, stagger: 0.08, delay: 0.7, ease: 'power3.out' });
    gsap.from('.sello', { scale: 0, rotate: -120, duration: 1.6, delay: 1, ease: 'elastic.out(1, 0.5)' });
    // Al bajar, el título se aleja hacia el fondo y la portada se oscurece
    gsap.to(titulo, { scale: 0.82, yPercent: -30, opacity: 0.15, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
    gsap.to('.hero-frase', { yPercent: -160, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
    gsap.to('.sello', { rotate: 220, yPercent: -80, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  }

  /* ---------- Cinta: se mueve sola y acelera con la velocidad del scroll ---------- */
  var cinta = document.querySelector('[data-cinta]');
  if (cinta) {
    var x = 0, vel = 0;
    var ancho = function () { return cinta.firstElementChild.offsetWidth; };
    var w = ancho();
    addEventListener('resize', function () { w = ancho(); });
    if (lenis) lenis.on('scroll', function (l) { vel = l.velocity || 0; });
    gsap.ticker.add(function (t, dt) {
      x -= (0.6 + Math.min(Math.abs(vel) * 0.35, 14)) * (dt / 16.7);
      if (-x >= w) x += w;
      cinta.style.transform = 'translate3d(' + x + 'px,0,0)';
      vel *= 0.92;
    });
  }

  /* ---------- Manifiesto: las palabras se encienden al leer ---------- */
  var mani = document.querySelector('[data-words]');
  if (mani && Split) {
    var words = new Split(mani, { types: 'words', tagName: 'span' }).words || [];
    gsap.to(words, { opacity: 1, ease: 'none', stagger: 0.12, scrollTrigger: { trigger: mani, start: 'top 75%', end: 'bottom 45%', scrub: true } });
  }
  document.querySelectorAll('[data-depth]').forEach(function (el) {
    var d = parseFloat(el.getAttribute('data-depth')) || 0.2;
    gsap.fromTo(el, { yPercent: 60 * d }, { yPercent: -60 * d, ease: 'none', scrollTrigger: { trigger: el.parentNode, start: 'top bottom', end: 'bottom top', scrub: true } });
  });

  /* ---------- 6. Scroll jacking (solo en pantallas anchas) ---------- */
  var mm = gsap.matchMedia();
  mm.add('(min-width: 761px)', function () {
    // Tratamientos: la sección se queda fija y la pista avanza en horizontal
    var pista = document.querySelector('[data-pista]');
    if (pista) {
      var dist = function () { return Math.max(0, pista.scrollWidth - innerWidth); };
      var tween = gsap.to(pista, {
        x: function () { return -dist(); }, ease: 'none',
        scrollTrigger: { trigger: '#tratamientos', start: 'top top', end: function () { return '+=' + dist(); }, pin: true, scrub: 1, invalidateOnRefresh: true, anticipatePin: 1 },
      });
      // Cada tarjeta llega desde el fondo (escala 0,5 → 1) al entrar en pantalla
      pista.querySelectorAll('.tarjeta').forEach(function (c) {
        gsap.from(c, { scale: 0.5, opacity: 0, rotate: -4, ease: 'power2.out', scrollTrigger: { trigger: c, containerAnimation: tween, start: 'left 100%', end: 'left 62%', scrub: true } });
      });
    }
    // Pasos: viaje en 3D. Cada número emerge desde lejos y se va por delante de la cámara
    var pasos = gsap.utils.toArray('[data-paso]');
    if (pasos.length) {
      var tl = gsap.timeline({ scrollTrigger: { trigger: '[data-pasos]', start: 'top top', end: '+=' + (pasos.length * 90) + '%', pin: true, scrub: 1 } });
      pasos.forEach(function (p, i) {
        tl.fromTo(p, { scale: 0.5, opacity: 0, z: -600 }, { scale: 1, opacity: 1, z: 0, duration: 1, ease: 'power2.out' }, i * 0.8);
      });
      tl.from('.pasos .pildora-grande', { y: 60, opacity: 0, duration: 0.6 }, '-=0.3');
    }
  });

  /* ---------- 5. Fondo de fibras (Three.js) ---------- */
  var canvas = document.getElementById('fibras');
  if (!canvas) return;
  var base = (document.currentScript && document.currentScript.src) || (document.querySelector('script[src*="tema.js"]') || {}).src || '';
  var tres = new URL('vendor/three.module.min.js?v=0.160.0', base).href;
  import(tres).then(function (THREE) {
    var renderer;
    try {
      renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: false, alpha: false, powerPreference: 'low-power' });
    } catch (e) { root.classList.add('sin-webgl'); return; }
    var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    renderer.setPixelRatio(dpr);
    var scene = new THREE.Scene();
    var camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);
    var U = {
      uTime: { value: 0 }, uRes: { value: new THREE.Vector2(1, 1) },
      uMouse: { value: new THREE.Vector2(0.5, 0.5) }, uScroll: { value: 0 },
      uTinta: { value: new THREE.Color('#0F1D1C') }, uCoral: { value: new THREE.Color('#FF5B3A') }, uSalvia: { value: new THREE.Color('#9DBBA6') },
    };
    var mat = new THREE.ShaderMaterial({
      uniforms: U,
      vertexShader: 'void main(){ gl_Position = vec4(position.xy, 0.0, 1.0); }',
      fragmentShader: SHADER,
    });
    scene.add(new THREE.Mesh(new THREE.PlaneGeometry(2, 2), mat));

    function size() {
      renderer.setSize(innerWidth, innerHeight, false);
      U.uRes.value.set(innerWidth * dpr, innerHeight * dpr);
    }
    size();
    addEventListener('resize', size);
    var tx = 0.5, ty = 0.5;
    addEventListener('pointermove', function (e) { tx = e.clientX / innerWidth; ty = 1 - e.clientY / innerHeight; }, { passive: true });

    var visible = true;
    ST.create({ trigger: '.hero', start: 'top top', end: 'bottom+=40% top', onToggle: function (s) { visible = s.isActive; }, onUpdate: function (s) { U.uScroll.value = s.progress; } });
    gsap.ticker.add(function (time) {
      if (!visible || document.hidden) return;
      U.uTime.value = time;
      U.uMouse.value.x += (tx - U.uMouse.value.x) * 0.05;
      U.uMouse.value.y += (ty - U.uMouse.value.y) * 0.05;
      renderer.render(scene, camera);
    });
  }).catch(function () { root.classList.add('sin-webgl'); });

  /* Fibras: ruido fractal deformado (domain warping) dibujado como líneas finas que fluyen,
   * como fibras musculares. Cerca del ratón se curvan y se calientan hacia el coral. */
  var SHADER = [
    'uniform float uTime; uniform vec2 uRes; uniform vec2 uMouse; uniform float uScroll;',
    'uniform vec3 uTinta; uniform vec3 uCoral; uniform vec3 uSalvia;',
    'vec3 mod289(vec3 x){return x-floor(x*(1.0/289.0))*289.0;}',
    'vec4 mod289(vec4 x){return x-floor(x*(1.0/289.0))*289.0;}',
    'vec4 permute(vec4 x){return mod289(((x*34.0)+10.0)*x);}',
    'vec4 taylorInvSqrt(vec4 r){return 1.79284291400159-0.85373472095314*r;}',
    'float snoise(vec3 v){',
    ' const vec2 C=vec2(1.0/6.0,1.0/3.0); const vec4 D=vec4(0.0,0.5,1.0,2.0);',
    ' vec3 i=floor(v+dot(v,C.yyy)); vec3 x0=v-i+dot(i,C.xxx);',
    ' vec3 g=step(x0.yzx,x0.xyz); vec3 l=1.0-g; vec3 i1=min(g.xyz,l.zxy); vec3 i2=max(g.xyz,l.zxy);',
    ' vec3 x1=x0-i1+C.xxx; vec3 x2=x0-i2+C.yyy; vec3 x3=x0-D.yyy; i=mod289(i);',
    ' vec4 p=permute(permute(permute(i.z+vec4(0.0,i1.z,i2.z,1.0))+i.y+vec4(0.0,i1.y,i2.y,1.0))+i.x+vec4(0.0,i1.x,i2.x,1.0));',
    ' float n_=0.142857142857; vec3 ns=n_*D.wyz-D.xzx; vec4 j=p-49.0*floor(p*ns.z*ns.z);',
    ' vec4 x_=floor(j*ns.z); vec4 y_=floor(j-7.0*x_); vec4 x=x_*ns.x+ns.yyyy; vec4 y=y_*ns.x+ns.yyyy; vec4 h=1.0-abs(x)-abs(y);',
    ' vec4 b0=vec4(x.xy,y.xy); vec4 b1=vec4(x.zw,y.zw); vec4 s0=floor(b0)*2.0+1.0; vec4 s1=floor(b1)*2.0+1.0; vec4 sh=-step(h,vec4(0.0));',
    ' vec4 a0=b0.xzyw+s0.xzyw*sh.xxyy; vec4 a1=b1.xzyw+s1.xzyw*sh.zzww;',
    ' vec3 p0=vec3(a0.xy,h.x); vec3 p1=vec3(a0.zw,h.y); vec3 p2=vec3(a1.xy,h.z); vec3 p3=vec3(a1.zw,h.w);',
    ' vec4 norm=taylorInvSqrt(vec4(dot(p0,p0),dot(p1,p1),dot(p2,p2),dot(p3,p3))); p0*=norm.x; p1*=norm.y; p2*=norm.z; p3*=norm.w;',
    ' vec4 m=max(0.5-vec4(dot(x0,x0),dot(x1,x1),dot(x2,x2),dot(x3,x3)),0.0); m=m*m;',
    ' return 105.0*dot(m*m,vec4(dot(p0,x0),dot(p1,x1),dot(p2,x2),dot(p3,x3)));',
    '}',
    'float fbm(vec3 p){ float f=0.0; float a=0.5; for(int i=0;i<4;i++){ f+=a*snoise(p); p*=2.02; a*=0.5; } return f; }',
    'void main(){',
    ' vec2 p=(gl_FragCoord.xy-0.5*uRes)/uRes.y;',
    ' vec2 m=(uMouse-0.5)*vec2(uRes.x/uRes.y,1.0);',
    ' vec2 dm=p-m; float d=length(dm);',
    ' float lens=exp(-d*d*7.0);',
    ' p+=dm*0.22*lens;',
    ' float t=uTime*0.05;',
    ' vec2 q=vec2(fbm(vec3(p*1.1,t)),fbm(vec3(p*1.1+vec2(5.2,1.3),t)));',
    ' float f=fbm(vec3(p*1.3+q*1.7,t*1.4));',
    ' float k=p.y*9.0+p.x*2.5+f*6.0+q.x*3.0+uScroll*4.0;',
    ' float fib=abs(fract(k)-0.5);',
    ' float w=fwidth(k);',
    ' float line=1.0-smoothstep(0.0,w*1.6,fib);',
    ' float band=smoothstep(-0.2,0.6,f+q.y);',
    ' vec3 col=uTinta;',
    ' vec3 fibCol=mix(uSalvia,uCoral,clamp(lens*1.4+smoothstep(0.35,0.9,q.y+f*0.5),0.0,1.0));',
    ' col=mix(col,fibCol,line*(0.18+0.55*band));',
    ' col+=uCoral*lens*0.10;',
    ' float v=smoothstep(1.25,0.2,length((gl_FragCoord.xy/uRes-0.5)*vec2(1.6,1.1)));',
    ' col*=mix(0.55,1.0,v);',
    ' col*=1.0-uScroll*0.45;',
    ' float g=fract(sin(dot(gl_FragCoord.xy,vec2(12.9898,78.233))+uTime)*43758.5453);',
    ' col+=(g-0.5)*0.035;',
    ' gl_FragColor=vec4(col,1.0);',
    '}',
  ].join('\n');
})();
