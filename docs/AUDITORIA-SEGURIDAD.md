# Auditoría de seguridad (29 de octubre de 2026)

Revisión de código y arquitectura de la web del restaurante (PHP 8.1 sin framework, API en `web/api/index.php`, lógica en `web/lib/`, panel/app en `app/www` → `web/panel`). Para cada comprobación se indica el estado **antes** de esta revisión, el archivo afectado, la severidad y lo que se ha corregido.

Severidad: **Crítica** (compromiso directo y fácil) · **Alta** (compromiso de cuentas o datos con condiciones realistas) · **Media** (requiere condiciones concretas o tiene impacto limitado) · **Baja** (endurecimiento / buenas prácticas).

## Resumen

| # | Comprobación | Antes | Severidad | Ahora |
|---|---|---|---|---|
| 1 | Hashing de contraseñas | bcrypt coste 10 (`PASSWORD_DEFAULT`) | Baja | ✅ Argon2id (o bcrypt 12) y rehash automático al entrar |
| 2 | Flags de cookies/tokens | ✅ Cookie `HttpOnly`, `Secure`, `SameSite=Lax`; panel con token por cabecera | — | ✅ Sin cambios |
| 3 | Tokens de recuperación | ✅ 256 bits, 1 h, un solo uso, solo se guarda la huella | — | ✅ Sin cambios |
| 4 | Cierre de sesión / rotación | Las sesiones de cliente seguían vivas tras cambiar o recuperar la contraseña; tokens del panel sin caducidad absoluta | **Alta** | ✅ Corregido |
| 5 | IDOR en reservas | ✅ Todo filtra por `customer_id` o por token secreto | — | ✅ Sin cambios |
| 6 | Separación de roles | Empleados sin permiso de ajustes veían la URL del cron con su clave | Media | ✅ Corregido |
| 7 | Protección de endpoints | ✅ Toda ruta de gestión pasa por `require_auth()` y `require_perm()`/`require_admin()` | — | ✅ Sin cambios |
| 8 | SQL injection | ✅ Todas las consultas con sentencias preparadas (PDO) | — | ✅ Sin cambios |
| 9 | XSS | ✅ Escapado con `h()`/`esc()`/`textContent`; sin CSP | Media | ✅ CSP con nonce añadida |
| 10 | CSRF | ✅ `SameSite=Lax` + comprobación de `Origin` | Baja | ✅ También `Sec-Fetch-Site` |
| 11 | Reservas concurrentes | Comprobar aforo y guardar no era atómico: sobre-reserva en MySQL, error 500 en SQLite | **Alta** | ✅ Bloqueo en base de datos |
| 12 | Precios de la carta | ✅ No hay pedidos ni pagos; precios solo desde el panel y validados en servidor | — | ✅ Sin cambios |
| 13 | APIs de terceros | ✅ Tokens de Meta, Google y SMTP cifrados en servidor y nunca enviados al panel | — | ✅ Sin cambios |
| 14 | Fuerza bruta | Límite solo por IP (ataques repartidos sin tope); altas y reenvíos sin límite (bombardeo de emails); enumeración de cuentas por tiempo de respuesta y en el registro | **Alta** | ✅ Corregido |
| 15 | Spam de reservas | Solo 5/hora por IP | Media | ✅ +10/día por IP y máx. 3 activas por email/cuenta |
| 16 | Rate limiting global | No había | Media | ✅ 240 peticiones/min por IP en la API |
| 17 | Datos mínimos (PII) | ✅ Nombre y teléfono obligatorios; email opcional; datos cifrados y borrados a las 24 h | — | ✅ Sin cambios |
| 18 | Fuga de datos en la API | ✅ Sin huellas ni tokens en las respuestas | — | ✅ Sin cambios |
| 19 | Logs | Trazas de error podían incluir argumentos (contraseñas); con `debug` se registraban enlaces con tokens | Baja | ✅ Corregido |
| 20 | Gestión de secretos | ✅ `config.php` fuera de git y bloqueado por `.htaccess`; secretos de terceros cifrados en BD | — | ✅ Secreto nuevo de 256 bits en instalaciones nuevas |
| 21 | CORS | `Access-Control-Allow-Origin: *` | Media | ✅ Lista blanca |
| 22 | Cabeceras HTTP | ✅ HSTS, nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy en `.htaccess`; **faltaba CSP** | Media | ✅ CSP en web, API y panel |
| — | Extra: reinstalación | `install.php` se podía volver a ejecutar si la BD fallaba | **Alta** | ✅ Bloqueado si existe `config.php` |
| — | Extra: Host header | Enlaces de email construidos con la cabecera `Host` | Media | ✅ `base_url` fijo en `config.php` |
| — | Extra: borrado de archivos | `remove_upload()` aceptaba `uploads/../…` | Baja | ✅ Solo nombres sueltos de `uploads/` |

## 1. Autenticación y gestión de sesiones

### 1.1 Hashing de contraseñas · Baja · corregido
- **Antes:** `password_hash($pass, PASSWORD_DEFAULT)` (bcrypt coste 10) en `web/lib/auth.php`, `clientes.php`, `recuperar.php` e `install.php`.
- **Riesgo:** bcrypt 10 es aceptable pero está por debajo de lo recomendado hoy (OWASP: Argon2id, o bcrypt ≥ 12).
- **Corrección:** `pass_hash()` / `pass_needs_rehash()` / `pass_verify()` en `web/lib/core.php`. Usa Argon2id si PHP lo incluye y si no bcrypt coste 12. Al entrar, las huellas antiguas se rehacen solas, así que nadie tiene que cambiar su contraseña.

### 1.2 Cookies y tokens · cumple
- Clientes: sesión PHP con cookie `HttpOnly`, `Secure` (si hay HTTPS), `SameSite=Lax`, `use_strict_mode` y `session_regenerate_id()` al entrar (`web/lib/clientes.php`, `customer_session()`).
- Panel: token aleatorio de 256 bits enviado en la cabecera `X-Token` (no es cookie, así que no hay CSRF). En BD solo se guarda su SHA-256. El token vive en `localStorage` de la app; por eso es importante la CSP (punto 3.2).

### 1.3 Recuperación de cuenta · cumple
- `web/lib/recuperar.php`: token de 256 bits, válido 60 min, de un solo uso (marcado de forma atómica con `UPDATE … WHERE used_at IS NULL`), solo se guarda la huella, pedir otro anula el anterior y la respuesta no revela si la cuenta existe.

### 1.4 Cierre de sesión y rotación · **Alta** · corregido
- **Antes:** cambiar la contraseña desde «Mi cuenta» o recuperarla por email **no cerraba las demás sesiones del cliente** (sesiones PHP en archivos que no se podían enumerar). Quien hubiera robado la sesión seguía dentro después de que la víctima cambiara la contraseña. Los tokens del panel solo caducaban por inactividad (90 días): un token robado y usado de vez en cuando no caducaba nunca.
- **Corrección:**
  - `current_customer()` compara una huella de la contraseña guardada en la sesión (`$_SESSION['pv']`, `pass_fingerprint()`). Si la contraseña cambia, las demás sesiones dejan de valer al momento. La sesión desde la que se cambia sigue, con identificador nuevo.
  - `require_auth()` (`web/lib/auth.php`): caducidad absoluta de 180 días (`TOKEN_MAX_DAYS`) además de los 90 de inactividad; el token caducado se borra.
  - Ya existía y se mantiene: logout borra el token/sesión en servidor y la cookie; recuperar la contraseña del panel borra todos sus tokens.

## 2. Control de acceso y autorización

### 2.1 IDOR · cumple
- Cliente: `customer_cancel()`, `customer_confirm()`, `customer_reservations()` y `customer_export()` filtran por `customer_id` de la sesión (`WHERE id = ? AND customer_id = ?`). Cambiar el id en el payload no da acceso a reservas ajenas.
- Enlaces de WhatsApp/email (`confirmar.php`, `borrar-datos.php`): necesitan un token secreto de 128/256 bits comparado con `hash_equals()`.

### 2.2 Separación de roles · Media · corregido
- Roles: **cliente** (sesión de la web, solo sus datos), **empleado** (`staff` con permisos `reservas`, `carta`, `ajustes`) y **administrador** (usuarios, clientes y borrados RGPD).
- **Fallo:** `GET api/?r=settings` lo podía leer cualquier empleado, aunque no tuviera el permiso de ajustes, e incluía `cron_url` con la clave del cron. **Corregido** en `web/api/index.php`: sin `ajustes` no se envían `cron_url`, `smtp_error` ni `wa_error`.
- Decisión de diseño (informativa): todos los empleados pueden **ver** reservas con teléfono y notas, porque las necesitan para atender. Si se quisiera restringir, habría que añadir un permiso «ver datos de contacto».

### 2.3 Protección de endpoints · cumple
- `web/api/index.php`: las rutas públicas van antes de `require_auth()`; las de gestión, después, y cada escritura llama a `only_post()` + `require_perm()` o `require_admin()`. Las de cliente usan `require_customer()` + `same_origin_only()`.

## 3. Validación de entradas y sanitización

### 3.1 SQL / NoSQL injection · cumple
- `q()` en `web/lib/core.php` usa siempre `prepare()` + `execute()`. Las únicas interpolaciones son constantes del código (listas de `?` y nombres de tabla fijos).

### 3.2 XSS · Media · reforzado
- **Estado:** el servidor escapa con `h()` (`htmlspecialchars`, `ENT_QUOTES`) y `json_encode(..., JSON_HEX_TAG)`; el panel usa `esc()` en todas las plantillas con datos de clientes (nombres, notas, descripciones) y `textContent` en avisos. No se ha encontrado ningún XSS explotable.
- **Faltaba** una segunda barrera: no había Content-Security-Policy, y un XSS en el panel podría robar el token de `localStorage`.
- **Corrección:**
  - Web: `send_csp()` (`web/lib/core.php`, se envía desde `web/lib/pagina.php`). Solo scripts propios, los que llevan nonce por petición y los de Google Analytics/Tag Manager y el píxel de Meta (que solo se cargan con consentimiento); `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `frame-ancestors 'self'`.
  - El único manejador en línea (`onsubmit` de `confirmar.php`) pasa a `data-confirm` gestionado por `web/sdk/restaurante.js`.
  - API: `default-src 'none'; frame-ancestors 'none'`.
  - Panel (`app/www/index.html` y `web/panel/index.html`): CSP en `<meta>` con `script-src 'self'`.
  - Opciones en `config.php`: `'csp' => false` la quita; `'csp_report_only' => true` solo avisa.

### 3.3 CSRF · Baja · reforzado
- Cookies `SameSite=Lax` + `same_origin_only()` en todas las rutas de cliente que cambian algo. Ahora, si el navegador no manda `Origin`, también se rechaza `Sec-Fetch-Site: cross-site/same-site`. El panel usa cabecera `X-Token`, inmune a CSRF. `confirmar.php` y `borrar-datos.php` funcionan con token secreto en el formulario.

## 4. Lógica de negocio y condiciones de carrera

### 4.1 Reservas concurrentes · **Alta** · corregido
- **Antes:** `create_public_reservation()` (`web/lib/reservas.php`) abría una transacción, leía el aforo con `day_slots()` y luego insertaba. En MySQL/InnoDB (REPEATABLE READ) dos peticiones simultáneas leen el mismo aforo libre y **las dos se guardan: sobre-reserva**. En SQLite la segunda fallaba con «database is locked» (error 500).
- **Corrección:** `reservation_lock()` modifica una fila fija de `settings` (`_res_lock`) al empezar la transacción. En MySQL deja la fila bloqueada hasta el commit; en SQLite coge el bloqueo de escritura. La segunda petición espera y ya ve las plazas ocupadas.
- **Prueba:** aforo 4, cuatro peticiones simultáneas de 2 personas a la misma hora → 2 aceptadas, 2 «Esa hora ya está completa», total 4 personas.

### 4.2 Carta y precios · cumple
- No hay pedidos ni pagos online: la carta solo se muestra. Los precios solo los cambia el panel con permiso `carta` y se validan en servidor (`save_dish()`, `menu_save()`: 0–9999, redondeo a 2 decimales). El cliente nunca envía precios.

### 4.3 Llamadas / APIs de terceros · cumple
- No hay WebRTC ni telefonía tipo Twilio; las llamadas son enlaces `tel:` y `wa.me`. La API de WhatsApp (Meta), Google Translate y SMTP se llaman solo desde el servidor (`web/lib/whatsapp.php`, `traductor.php`, `core.php`). Sus claves se guardan cifradas con libsodium y `settings_admin()` las vacía antes de mandar los ajustes al panel.

## 5. Rate limiting y prevención de abuso

### 5.1 Fuerza bruta · **Alta** · corregido
- **Antes:** login del panel y de clientes: 10 fallos por IP cada 15 min. Un ataque repartido entre muchas IP no tenía límite por cuenta. El registro y el reenvío de activación no contaban intentos, así que se podía usar la web para bombardear buzones. Además el registro decía «Ya hay una cuenta con ese email» y el login respondía antes cuando el usuario no existía (sin calcular la huella), dos formas de saber qué cuentas existen.
- **Corrección** (`throttle_check()` / `throttle_hit()` en `web/lib/core.php`):
  - Panel: 10 fallos/IP **y** 20 fallos/usuario cada 15 min. Clientes: igual por IP y por email.
  - Registro y reenvío: 5 por IP y hora. Recuperar contraseña: 5 por IP cada 15 min (ya existía).
  - Contraseña actual (cambiar contraseña, cambiar email, borrar cuenta): 5 fallos por cuenta cada 15 min.
  - `pass_verify()` calcula una huella falsa cuando la cuenta no existe (mismo tiempo de respuesta).
  - El registro responde siempre «revisa tu email». Si la cuenta ya existía, al dueño le llega un aviso con el enlace para recuperar la contraseña.

### 5.2 Spam de reservas · Media · corregido
- `reservation_spam_guard()` (`web/lib/reservas.php`): 5 por hora y 10 al día por IP y como mucho 3 reservas activas (pendientes, esperando o confirmadas, de hoy en adelante) por email o por cuenta. Se mantienen la trampa anti-bots y la aceptación obligatoria de las políticas.

### 5.3 Límite global de la API · Media · corregido
- `api_rate_limit()`: 240 peticiones por minuto y por IP en toda la API (responde `429` con `Retry-After`). Usa APCu si existe; si no, un archivo pequeño por IP en `data/limites/` (bloqueado por `.htaccess`, se limpia solo). Se ajusta con `'rate_limit'` en `config.php` (0 lo desactiva).

## 6. Protección de datos (RGPD / LOPDGDD)

### 6.1 Datos mínimos · cumple
- Reserva: nombre y teléfono (obligatorios), email y notas (opcionales). Cuenta: nombre, email, contraseña y teléfono opcional. Datos personales cifrados (XSalsa20-Poly1305) y borrados 24 h después de la reserva; IP solo como huella. Portabilidad (`c_export`) y borrado (`borrar-datos.php`) disponibles.
- Ajuste menor: el aviso por email de una petición de borrado ya no lleva el teléfono completo (solo los 3 últimos dígitos); el teléfono está en el panel.

### 6.2 Fuga de datos en respuestas · cumple
- `user_out()`, `customer_out()`, `res_out()` y `public_info()` devuelven listas cerradas de campos. `require_auth()` quita `pass_hash`. Ninguna respuesta lleva huellas, tokens de otros usuarios ni claves de terceros. Los errores 500 no muestran detalles salvo con `debug`.

### 6.3 Logs · Baja · corregido
- `zend.exception_ignore_args = 1` en `web/lib/bootstrap.php`: las trazas que se escriben con `error_log()` ya no llevan los argumentos de las funciones, donde podía aparecer una contraseña.
- Los enlaces con tokens (activación y recuperación) solo se escriben en el log con `debug` **y** desde `localhost` (`dev_log()`), nunca en producción.

## 7. Infraestructura, secretos y cabeceras

### 7.1 Secretos · cumple
- El equivalente a `.env` en este proyecto es `web/config.php` (credenciales de BD y `secret`). Está en `.gitignore`, `.htaccess` lo bloquea y lo genera `install.php`. No hay ningún secreto en el historial de git (revisado). Las claves de Meta, Google y SMTP van cifradas en la BD y nunca salen hacia la app.
- Instalaciones nuevas: `secret` de 256 bits (antes 128).

### 7.2 CORS · Media · corregido
- **Antes:** `Access-Control-Allow-Origin: *` en toda la API.
- **Ahora:** `cors_headers()` solo responde a la propia web, a la app (`https://localhost`, `capacitor://localhost`, `http://localhost:*`) y a los orígenes que se añadan en `config.php` (`'cors_origins' => ['https://…']`), con `Vary: Origin`.

### 7.3 Cabeceras HTTP · Media · corregido
- `.htaccess` ya ponía `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` y `Permissions-Policy`. **Faltaba `Content-Security-Policy`**: añadida (ver 3.2).

## 8. Hallazgos adicionales

### 8.1 Reinstalación con `install.php` · **Alta** · corregido
- **Antes:** el instalador solo se bloqueaba si `config.php` existía **y** la base de datos respondía. Si `install.php` no se había borrado y la BD fallaba un momento (MySQL caído), cualquiera podía reinstalar con SQLite, crear su propio administrador y sobrescribir `config.php`, con lo que el restaurante perdía también la clave de los datos cifrados.
- **Ahora:** si existe `config.php`, `install.php` no hace nada.

### 8.2 Enlaces de email con la cabecera Host · Media · corregido
- **Antes:** `base_url()` usaba `$_SERVER['HTTP_HOST']`. Si el servidor acepta cualquier `Host` (VPS con un único sitio por defecto), un atacante podía pedir la recuperación de contraseña de otro usuario con `Host: atacante.com`. La víctima recibía un enlace legítimo que apuntaba al dominio del atacante y, al abrirlo, le entregaba el token.
- **Ahora:** `install.php` guarda `base_url` en `config.php` y `base_url()` la usa. Sin ella, se valida el formato del Host. **Webs ya instaladas: añadir `'base_url' => 'https://dominio',` a `config.php`.**

### 8.3 `remove_upload()` · Baja · corregido
- Solo comprobaba que la ruta empezara por `uploads/`, así que `uploads/../config.php` también valía. Hoy no se le pasa nada que venga del usuario, pero se endurece: solo nombres de archivo sueltos dentro de `uploads/`.

## Pendiente / recomendaciones

- Añadir `base_url` a `config.php` en las webs ya instaladas (incluida la de «Novia») y copiar allí estos cambios.
- Las reservas apuntadas desde el panel siguen sin comprobar el aforo (decisión de diseño; ya estaba en el backlog).
- Tests automáticos y CI: siguen sin existir; las pruebas de esta revisión se hicieron a mano con el servidor de PHP y Chromium.
- Si algún tema incrusta otros servicios (por ejemplo, vídeos de YouTube), hay que añadir su dominio en `send_csp()`.
