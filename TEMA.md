# Cómo hacer un tema

El tema es el diseño de la web. Vive entero en `web/tema/` y es lo único que cambia de un cliente a otro. El backend le da los datos y se encarga de reservas, cuentas, emails, cookies y textos legales.

## Archivos obligatorios

| Archivo | Para qué | Variables que recibe |
|---|---|---|
| `tema.php` | Configuración: nombre, versión, colores, huecos de foto. **Solo constantes** (lo leen también la API y el panel). | — |
| `inicio.php` | Portada (`/`) | `$carta`, `$menus`, `$carta_on`, `$menus_button` |
| `cuenta.php` | Entrar, registrarse y «Mi cuenta» (`cuenta.php`) | `$reservas`, `$activada`, `$enlace_caducado`, `$registro` |
| `legal.php` | Información legal (`legal.php`) | `$secciones` |
| `borrar.php` | Borrar mis datos (`borrar-datos.php`): pedir el enlace y confirmar el borrado. Pinta `borrar_form()` en «pedir» e «invalido» | `$estado`, `$contacto`, `$resumen`, `$hecho`, `$borrar_boton` |
| `recuperar.php` | Recuperar la contraseña (`recuperar.php`): pedir el enlace (`<form data-api="c_forgot">` + `[data-forgot-ok]`) y elegir la nueva (`<form data-api="c_reset">` con `<input type="hidden" name="t">`). Si el tema no lo tiene, sale una página sencilla. | `$estado` (`pedir`, `nueva`, `invalido`), `$token` |
| `confirmar.php` | Página del enlace del WhatsApp (`confirmar.php`) | `$ok`, `$pendiente`, `$estado`, `$reserva`, `$fecha`, `$error`, `$ics_url`, `$confirmar_form`, `$cancelar_form` |

Todas reciben también `$s` (los ajustes del restaurante) y `$cliente` (el cliente con sesión, o `null`).

Opcionales:

| Archivo | Para qué | Variables que recibe |
|---|---|---|
| `carta.php` | Página propia de la carta (`carta.php`). Si el tema no la tiene, `carta.php` lleva a `./#carta`. | `$carta`, `$menus`, `$carta_on` |
| `menus.php` | Página propia de los menús (`menus.php`). Si el tema no la tiene, lleva a `./#menus`. | `$menus`, `$carta`, `$carta_on` |
| `reservar.php` | Página «Cómo reservar» (`reservar.php`): políticas, horario… Si no existe, lleva a `./#reservar`. | — |
| `lang.php` | Traducciones de los textos del propio tema (ver «Idiomas»). | — |

El resto es libre: `partes.php` (cabecera y pie compartidos), `assets/` (CSS, JS, fuentes, imágenes). Para enlazar un archivo de `assets/` con su versión: `<?= asset('tema.css') ?>`. Si tu tema tiene `carta.php` o `reservar.php`, enlázalas desde el menú (`carta.php`, `reservar.php`) y entran solas en el sitemap.

## Lo que tiene que tener cada página (obligatorio)

```php
<!doctype html>
<html lang="<?= html_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($s['name']) ?></title>
<?php head_tags(); ?>          <!-- SEO, aviso de cookies, iconos de la app, estilos del SDK -->
<link rel="stylesheet" href="<?= asset('tema.css') ?>">
</head>
<body>
  …tu diseño…
  <footer>
    … enlaces a legal.php#aviso, legal.php#privacidad, legal.php#cookies …
    <?= cookies_link() ?>      <!-- «Configurar cookies»: obligatorio por ley -->
  </footer>
<?php body_end(); ?>           <!-- ventana de reserva + SDK -->
</body>
</html>
```

## Reglas legales para el diseño

- **Nada de recursos de otras webs**: ni Google Fonts desde Google, ni imágenes o scripts de otros dominios. Descarga las fuentes a `tema/assets/fonts/` (las de Google Fonts tienen licencia libre). La política de privacidad dice que todo se sirve desde el propio servidor.
- **Mapas**: si incrustas Google Maps, cárgalo solo cuando el visitante pulse un botón («Mostrar el mapa») y pon `const TEMA_GOOGLE_MAPS = true;` en `tema.php`. Así se añade el aviso en la página de cookies.
- **Scripts y seguridad (CSP)**: la web manda una cabecera Content-Security-Policy. Los scripts del tema van en archivos de `tema/assets/` (`<script src="…" defer>`); si necesitas un `<script>` en línea, ponle `nonce="<?= csp_nonce() ?>"`. Nada de `onclick="…"` ni `onsubmit="…"`: usa `addEventListener` en `tema.js` (para pedir confirmación antes de enviar un formulario basta con `data-confirm="¿Seguro…?"`). Si incrustas algo de otro dominio, añádelo a `send_csp()` en `lib/core.php`.
- **No cambies los textos legales** desde el tema: salen de `lib/legal.php` y se rellenan solos.
- **Muestra la información de alérgenos** en la carta (los iconos con su nombre o la leyenda).

## Datos del restaurante: `$s`

`name`, `tagline` (frase corta), `description` (presentación), `address`, `city`, `phone`, `whatsapp`, `email`, `instagram`, `facebook`, `tiktok`, `tripadvisor`, `maps_url`, `ribbon` (frases destacadas, separadas por comas), `footer_title`, `hours`, `kitchen_on`, `kitchen_hours`, `closed_dates`, `late_minutes`, `policies`, `max_people`, `days_ahead`, `owner_name`, `owner_nif`.

Imprime siempre con `h()`: `<?= h($s['name']) ?>`.

`$s` ya viene traducido al idioma de la visita en los textos propios del restaurante (`tagline`, `description`, `footer_title`, `ribbon` y `policies`): imprimes `$s['tagline']` y sale en el idioma que toca. El nombre y los datos de contacto nunca se traducen.

## Funciones disponibles

| Función | Devuelve |
|---|---|
| `h($texto)` | Texto escapado para HTML |
| `asset('archivo.css')` | `tema/assets/archivo.css?v=…` |
| `photo_img('portada', 1200, '100vw')` | `<img>` completo con `srcset` y `alt` |
| `photo('portada', 1200)` / `photo_abs(...)` | Solo la dirección de la foto (relativa / completa) |
| `photo_credits()` | Autores de las fotos de ejemplo que siguen puestas (para el pie) |
| `horario()` | `[['dia' => 'Lunes', 'texto' => '13:00–16:00', 'cocina' => '13:30–15:30' o '', 'hoy' => bool], …]` (`cocina` solo si hay horario de cocina y es distinto) |
| `horario_hoy()` | Horario de hoy, `'Cerrado'` o `'Cerrado hoy'` |
| `politicas()` | `[['titulo' => 'Grupos', 'texto' => '…'], …]` |
| `redes()` | `['Instagram' => url, …]` (solo las configuradas) |
| `tel_href($tel)`, `wa_href()`, `maps_href()` | Enlaces para llamar, WhatsApp y «Cómo llegar» |
| `al_icon('gluten', 22, 'Gluten')` | Icono de un alérgeno |
| `precio(12.5)` | `12,50 €` |
| `cookies_link()` | Enlace «Configurar cookies» (traducido) |
| `borrar_form('clase-del-botón')` | Formulario «Borrar mis datos» (email o teléfono). Ponlo en la página legal o en las políticas y enlaza `borrar-datos.php` desde el pie |
| `selector_idioma('mi-clase')` | Banderas circulares para cambiar de idioma (vacío si solo hay uno). Ponlo en la cabecera |
| `html_lang()` | `es-ES`, `en-GB`, `pt-PT` o `gl-ES`, para `<html lang>` |
| `t('texto en español', …)` | Texto fijo en el idioma de la visita (admite `%s` y `%d`) |
| `tr('texto del restaurante')` | Texto del restaurante traducido (solo si lo imprimes sin pasar por `$s`, `$carta` o `politicas()`) |
| `tema_tiene('carta')` | ¿Existe `tema/carta.php`? |
| `carta_publica()` | La carta con nombres, descripciones y alérgenos en el idioma de la visita (ya viene en `$carta`) |
| `menu_data(false)` | La carta sin traducir |

### La carta: `$carta`

```php
$carta['categories'] = [
  ['id' => 3, 'name' => 'Entrantes', 'dishes' => [
     ['id' => 7, 'name' => 'Pulpo', 'description' => '…', 'price' => 24.0, 'allergens' => ['moluscos'],
      'photo' => 'https://…/uploads/…jpg' o '', 'featured' => true],
  ]],
];
$carta['allergens'] = [['key' => 'gluten', 'name' => 'Gluten', 'abbr' => 'GL', 'desc' => '…'], …];  // los 14
```

Solo vienen los platos disponibles y las secciones con platos.

## Huecos de foto: `TEMA_FOTOS`

```php
const TEMA_FOTOS = [
    'portada' => ['label' => 'Portada', 'src' => 'https://images.unsplash.com/photo-…', 'alt' => '…', 'by' => 'Autor', 'url' => 'https://unsplash.com/photos/…'],
    'sala'    => ['label' => 'Nosotros', 'src' => 'tema/assets/img/sala.jpg', 'alt' => '…'],
];
```

Cada hueco aparece en la app (Ajustes → Fotos de la web) con su `label` para que el cliente ponga la suya. La primera es la principal (Google y redes sociales). Las fotos de Unsplash se descargan una vez y se sirven desde el propio servidor.

## Confirmar la reserva: el botón es obligatorio

Abrir el enlace del WhatsApp **no confirma** la reserva: WhatsApp, Telegram y los antivirus del correo abren los enlaces solos para hacer la vista previa, y confirmarían reservas sin que nadie las viera. Cuando `$pendiente` es `true`, la página tiene que mostrar los datos y el botón que pinta `<?= $confirmar_form('Confirmar mi reserva', 'mi-clase-de-boton') ?>`. La reserva se confirma cuando el cliente lo pulsa.

Pon también `<?= $cancelar_form('Cancelar la reserva', 'mi-clase') ?>`: deja al cliente cancelar desde el mismo enlace, mientras la reserva está pendiente o confirmada. Después la página muestra «Reserva cancelada» (en `$error`), y lo mismo si el restaurante la cancela o la borra desde el panel.

## Reservar: `data-book`

Cualquier elemento con `data-book` abre la ventana de reserva, por ejemplo `<a href="#reservar" data-book>Reservar</a>`. No hace falta nada más.

Si el restaurante tiene teléfono, la ventana empieza preguntando **«¿Cómo quieres reservar?»**: reservar online o llamar. «Llamar» se desactiva y avisa («Ahora estamos cerrados. Podrás llamarnos hoy a las 20:00») cuando la hora del restaurante queda fuera de su horario de apertura o es un día cerrado. Sin teléfono, la ventana va directa a la reserva online.

Para que la ventana y el aviso de cookies combinen con tu diseño, define estas variables en tu CSS:

```css
:root {
  --r-bg: #fff;            /* fondo */
  --r-text: #222;          /* texto */
  --r-muted: #666;         /* texto secundario */
  --r-line: rgba(0,0,0,.14);
  --r-field: transparent;  /* fondo de los campos */
  --r-accent: #b4532a;     /* botón principal y elementos elegidos */
  --r-accent-text: #fff;
  --r-radius: 14px;
  --r-btn-radius: 999px;
  --r-font: "Mi fuente", sans-serif;
  --r-font-title: "Mi fuente de títulos", serif;
}
```

Todas sus clases empiezan por `bk-` (reserva), `ck-` (cookies) y `r-` (idiomas, avisos y fotos ampliadas): no uses esos prefijos en tu tema.

## Idiomas

La web sale en español, inglés, portugués y gallego (el restaurante activa los que quiera en la app: Ajustes → Idiomas de la web). El backend ya traduce a mano la ventana de reserva, el aviso de cookies, los textos legales, los errores, la cuenta y los emails y WhatsApp al cliente. El tema solo tiene que hacer tres cosas:

1. `<html lang="<?= html_lang() ?>">` y `<?= selector_idioma('mi-clase') ?>` en la cabecera. Sus clases son `.r-langs`, `.r-lang` (`.is-on` la actual) y `.r-flag`; el CSS base está en `sdk/restaurante.css` y lo adaptas desde tu CSS.
2. Escribir los textos fijos del tema con `t('texto en español')` y ponerles la traducción en `tema/lang.php`:

```php
<?php
return [
    'dict' => [
        'Nuestra historia' => ['en' => 'Our story', 'pt' => 'A nossa história', 'gl' => 'A nosa historia'],
        'Hola, %s' => ['en' => 'Hello, %s', 'pt' => 'Olá, %s', 'gl' => 'Ola, %s'],
    ],
    'js' => ['Texto usado desde JavaScript'],   // opcional: claves que el JS del tema pide con Restaurante.t('…')
];
```

   Si falta una traducción, el texto sale en español. Los textos comunes («Carta», «Reservar mesa», «Cómo llegar», alérgenos, días…) ya están en `lib/lang/textos.php`; no hace falta repetirlos.
3. No traducir a mano la carta ni los datos del restaurante: salen traducidos solos en `$s`, `$carta` y `politicas()`. Se traducen al guardar en la app (traductor automático, corregible a mano).

Los textos legales cambian de idioma solos; en la página legal puedes mostrar `legal_aviso_traduccion()` (vacío en español).

## Menús y carta principal

El restaurante crea en la app (pestaña Carta) los menús de precio cerrado que quiera (menú del día, degustación, infantil…), cada uno con su interruptor, y también puede quitar la carta principal de la web.

- `$carta_on`: `false` si el restaurante ha desactivado la carta. Entonces no pintes la carta (los platos siguen en `$carta`, pero no deben verse).
- `$menus`: lista de menús activos, en el orden de la app, ya traducidos. Vacía si no hay ninguno:

```php
[
  [
    'id' => 'a1b2c3d4', 'title' => 'Menú del día', 'price' => 14.5, 'note' => 'Incluye pan y bebida',
    'days' => [1, 2, 3, 4, 5], 'days_text' => 'de lunes a viernes',
    'every_day' => false, 'today' => true,   // ¿todos los días? ¿se sirve hoy?
    'courses' => [
      ['name' => 'Primero', 'dishes' => [['name' => 'Caldo', 'desc' => '', 'extra' => 0.0, 'allergens' => ['apio'], 'photo' => 'https://…/uploads/menu-….jpg'], …]],   // photo puede ser ''
      ['name' => 'Segundo', 'dishes' => [['name' => 'Chuletón', 'desc' => '', 'extra' => 5.0], …]],   // extra = suplemento
    ],
  ],
  …
]
```

- `$menus_button`: si es `true` (y hay menús), pon en la portada un botón «Ver menús» que lleve a los menús. El restaurante lo activa o lo quita desde la app.

Pon los menús en su propia sección (`id="menus"`), separada de la carta, para que «Ver la carta» lleve solo a la carta. Usa `id="menu-<?= h($m['id']) ?>"` en cada menú para poder enlazarlo. Si un plato tiene foto, ponle `data-zoom`; sus alérgenos se pintan con `al_icon()` como los de la carta. Pinta el suplemento como `+<?= euros($d['extra']) ?> €` solo si `extra > 0`, «Hoy sí / Hoy no» solo si no es `every_day`, y, si algún plato no tiene alérgenos puestos, añade «Pregúntanos por los alérgenos de los platos del menú.». Textos fijos ya traducidos: «Menús», «Hoy sí», «Hoy no», «a elegir», «Suplemento». Fuera de los temas, `menus_public()` y `carta_on()` dan lo mismo.

## Fotos ampliables

Cualquier `<img data-zoom>` se amplía a pantalla grande al tocarla, con flechas, deslizar el dedo y teclado (Escape cierra). Opcionales: `data-zoom-src="…"` (foto grande), `data-zoom-caption="…"` (pie) y `data-zoom-group="platos"` (las flechas pasan solo entre fotos del mismo grupo). Con `photo_img()` basta pasar `'loading="lazy" data-zoom'` como último parámetro. Si añades fotos con JavaScript, llama a `Restaurante.ampliarFotos()`.

## Cuenta del cliente: atributos `data-*`

El SDK da el comportamiento; el tema solo pone el HTML.

| Atributo | Qué hace |
|---|---|
| `<form data-api="c_login">` | Entrar. Campos `email`, `password`. Al terminar va a `cuenta.php`. |
| `<form data-api="c_register">` | Crear cuenta. Campos `name`, `email`, `phone`, `password`, `privacy` (casilla) y `website` (campo trampa oculto). |
| `<form data-api="c_profile">` | Guardar datos. `name`, `phone`, `email`, `password`, `new_password`. |
| `<form data-api="c_delete" data-confirm="¿Seguro?">` | Borrar la cuenta. Campo `password`. |
| `<form data-api="c_forgot">` | Pedir el enlace para cambiar la contraseña. Campo `email`. Al terminar oculta el formulario y muestra `[data-forgot-ok]`. Enlázalo desde «Entrar» con `<a href="recuperar.php">`. |
| `<form data-api="c_reset">` | Guardar la contraseña nueva. Campos `t` (oculto) y `password`. Al terminar va a `cuenta.php`. |
| `<p data-error>` dentro del form | Ahí se escribe el error. |
| `<div data-tabs>` + botones `role="tab" aria-controls="id"` | Pestañas (Entrar / Crear cuenta). |
| `<div data-registro-ok hidden>` | Se muestra al registrarse («Revisa tu email»). Dentro: `<b data-registro-email>`, `<button data-resend>`. |
| `<button data-cancel="ID" data-when="texto">` | Cancelar una reserva. |
| `<button data-confirm-res="ID">` | Confirmar una reserva que espera al cliente. |
| `<button data-logout>` | Cerrar sesión. |
| `<a href="api/?r=c_export" download>` | Descargar sus datos. |

## Instalar como app

`<button data-install hidden>` aparece cuando el navegador permite instalar. Opcionales: `data-install-help` (instrucciones genéricas), `data-install-ios hidden` (instrucciones para iPhone) y `data-install-done hidden` (ya instalada).

## JavaScript propio

En `window.Restaurante` tienes:

- `Restaurante.api('slots&date=2026-11-02')` y `Restaurante.api('reserve', {...})`: llamadas a la API (ver API.md);
- `Restaurante.abrirReserva()`;
- `Restaurante.t('Texto')`: traduce un texto con el idioma actual (las claves van en `tema/lang.php`, sección `js`);
- `Restaurante.toast('Texto')`;
- `Restaurante.datos`: horario, días cerrados y cliente.

Eventos en `document`: `restaurante:antes-de-reservar`, `restaurante:reserva-abierta`, `restaurante:reserva` (con la respuesta) y `restaurante:c_login`, `restaurante:c_register`…

Si quieres hacer tu propia ventana de reserva, llama a `body_end(['reserva' => false])` y usa la API.

## Icono de la app

Por defecto se genera con la inicial del restaurante y los colores de `TEMA_COLORES`. Para usar un logo, pon un PNG cuadrado (512×512 o más) en `tema/assets/icono.png`.
