# Plantilla web para centros de fisioterapia

Base para hacer la web de un centro de fisioterapia: portada con diseño propio, citas online con confirmación por WhatsApp y un panel pensado para el día a día del fisio. Nace del backend de reservas para restaurantes (repositorio `backend`, que no se toca) adaptado a citas por tratamiento.

> **¿Eres el dueño del centro?** La [guía sencilla](docs/GUIA-CLIENTE.md) explica la instalación en Hostinger, los ajustes y WhatsApp (está escrita para restaurantes; el funcionamiento es el mismo).

Lo que ya funciona:

- **Citas por tratamiento.** Cada tratamiento tiene su duración (30, 45, 60 min…) y su precio. El paciente elige tratamiento, día y hora libre, y confirma por WhatsApp. Las horas libres se calculan con la duración real: una sesión de 60 min a las 10:00 ocupa hueco hasta las 11:00, y la última hora que se ofrece es la que termina antes de cerrar.
- **Varios fisios o cabinas a la vez**: el ajuste «Fisios o cabinas a la vez» (`capacity`) dice cuántas citas caben al mismo tiempo.
- **Panel nuevo** (móvil, ordenador y app Android) inspirado en Wallapop y Spotify:
  - **Hoy**: quién está en sesión ahora (con barra de progreso), la siguiente cita, peticiones nuevas y la agenda del día con los **huecos libres**: tocar un hueco apunta una cita ahí.
  - **Agenda**: el mes con puntos según lo lleno que está cada día, el día elegido y cerrar o abrir un día.
  - **Bandeja**: peticiones de la web como mensajes. Aceptar abre WhatsApp con el texto escrito; rechazar propone otras horas libres.
  - **Pacientes**: fichas con motivo de consulta, antecedentes, **notas de sesión**, gráfica de **dolor (EVA 0–10)**, historial de citas y **bonos** de sesiones que se descuentan al apuntar la nota.
  - Botón **+** en el centro para apuntar una cita en una sola hoja (paciente, tratamiento, día y hora).
  - **Más**: tratamientos como tarjetas, horario, agenda, fotos de la web y contraseña. Lo menos usado (usuarios, correo, idiomas, textos legales, WhatsApp automático) sigue en «Ajustes avanzados» (`panel/avanzado/`).
- **Tema «Fibra»**: portada artística con fondo de fibras en Three.js, desplazamiento suave (Lenis), animaciones con GSAP y ScrollTrigger y títulos con SplitType. Todas las librerías y fuentes van dentro del tema (sin CDN). Respeta «reducir movimiento» y funciona sin WebGL.
- **Cuentas de pacientes**, borrado de datos, cuatro idiomas (español, inglés, portugués y gallego), textos legales, SEO, emails por Gmail, WhatsApp automático opcional y seguridad (datos cifrados, bloqueo por intentos): todo lo del backend original, ver más abajo y [SEGURIDAD.md](SEGURIDAD.md).

**Datos clínicos.** Las peticiones de cita de la web se borran 24 h después, como en el original, pero las **fichas de paciente y sus notas no se borran solas**: la ley de autonomía del paciente obliga a guardar la historia clínica al menos 5 años desde el alta. Las notas, el teléfono y el email de la ficha van cifrados. Revisa con el centro el texto de privacidad (Ajustes avanzados → Textos legales) antes de publicar.

```
web/                → lo que se sube a Hostinger
  tema/             → EL DISEÑO («Fibra»): lo único que cambias en cada proyecto (ver TEMA.md)
  index.php, cuenta.php, legal.php, confirmar.php…  → «puertas»: hacen la lógica y llaman al tema. No se tocan.
  api/              → API que usan la web y el panel (ver API.md)
  lib/              → el backend (citas, pacientes, tratamientos, emails, textos legales…)
  sdk/              → JavaScript y CSS comunes: ventana de cita, aviso de cookies, cuenta
  panel/            → el panel en versión web (copia de app/www)
  install.php       → instalador
panel/              → código fuente del panel (React + TypeScript + Tailwind + shadcn/ui)
app/                → la app Android (Capacitor); app/www es el panel compilado
scripts/            → utilidades (copiar el panel, generar iconos)
```

---

## Empezar la web de un cliente nuevo

1. En GitHub, abre este repositorio y pulsa **Use this template → Create a new repository**. Ponle el nombre del cliente (por ejemplo `web-casa-marisa`) y márcalo como **Private**.
   (Si no ves el botón: Settings → General → marca **Template repository**.)
2. Haz el diseño **solo dentro de `web/tema/`**. Puedes partir del tema Base o borrarlo y empezar de cero. Lo que tiene que tener está en [TEMA.md](TEMA.md).
3. Cada vez que subas cambios a `main`, GitHub prepara sola la rama **`hostinger`** con la web lista para publicar.
4. En Hostinger: conecta esa rama y abre `install.php` (pasos abajo).
5. En la app de gestión, rellena los datos del cliente: nombre, contacto, horario, carta, titular y NIF, y (si quieres) el Gmail para los emails.

### Pasar una mejora del backend a webs que ya están hechas

Como el diseño está separado, basta con copiar del repositorio `backend-restaurante` a la web del cliente estas carpetas y archivos, **sin tocar `web/tema/`**:

`web/api/`, `web/lib/`, `web/sdk/`, `web/panel/`, `app/www/`, `web/index.php`, `web/cuenta.php`, `web/recuperar.php`, `web/borrar-datos.php`, `web/legal.php`, `web/confirmar.php`, `web/carta.php`, `web/menus.php`, `web/reservar.php`, `web/cron.php`, `web/foto.php`, `web/icon.php`, `web/manifest.php`, `web/sitemap.php`, `web/robots.php`, `web/sw.js`, `web/install.php` y `web/.htaccess`.

La base de datos se actualiza sola al abrir la web: las tablas y columnas nuevas se añaden sin perder datos.

---

## 1. Subir la web a Hostinger

### Opción A: con Git (se actualiza sola)

La rama **`hostinger`** contiene solo la web, con `index.php` en la raíz. **No conectes `main`**: ahí la web está dentro de `web/` y daría error 404.

1. En hPanel → **Archivos → Administrador de archivos**, vacía `public_html` (Hostinger exige que esté vacía).
2. En **Avanzado → Git → Crear un nuevo repositorio**:
   - Repositorio: la dirección del repositorio del cliente.
   - Rama: **`hostinger`**
   - Directorio: déjalo **vacío**.
   - Si el repositorio es privado, Hostinger te da una clave SSH: añádela en GitHub → Settings → Deploy keys.
3. Pulsa **Crear** y luego **Desplegar**. Activa el **despliegue automático** y pega la URL del webhook en GitHub → Settings → Webhooks.

### Instalar

1. Abre `https://dominio-del-cliente.com/install.php` y rellena:
   - la base de datos: **SQLite** (la más sencilla, no hay que crear nada) o MySQL (créala antes en hPanel → Bases de datos; el servidor es `localhost`);
   - el nombre del restaurante y el email donde llegan las reservas;
   - el usuario y la contraseña de la app de gestión.
2. Cuando termine, **borra `install.php`**.
3. En hPanel → **Seguridad → SSL**, comprueba que el certificado está activo.

Guarda una copia de `config.php`: sin él no se pueden leer los datos cifrados.

**Webs instaladas antes de noviembre de 2026:** añade a `config.php` la dirección fija de la web, `'base_url' => 'https://dominio-del-cliente.com',`, para que los enlaces de los emails (contraseña, activación) no dependan de la cabecera Host. Opciones de seguridad en `config.sample.php` (`rate_limit`, `cors_origins`, `csp`). Detalles en [docs/AUDITORIA-SEGURIDAD.md](docs/AUDITORIA-SEGURIDAD.md).

## 2. App de gestión

**Android:** el APK se genera a mano desde GitHub → **Actions** → «Compilar app Android y web» → **Run workflow**, y aparece en **Releases**. **Un mismo APK vale para todos los clientes**: al abrirla, pide la dirección de la web.

**iPhone:** desde Safari, abre `https://dominio-del-cliente.com/panel/` → Compartir → **Añadir a pantalla de inicio**.

**Desde el ordenador:** `https://dominio-del-cliente.com/panel/` (el menú pasa al lateral).

Si el teléfono de una cita coincide con el de una ficha, la cita se une sola a ese paciente. Desde una cita sin ficha, «Crear ficha» la crea con sus datos.

## 3. Enviar los emails desde Gmail

1. En la cuenta de Google, activa la **verificación en 2 pasos**.
2. En **myaccount.google.com/apppasswords**, crea una contraseña («Web restaurante») y copia las 16 letras.
3. En la app: **Ajustes → Técnico → Avanzado → Envío de emails**. Pon el Gmail y esa contraseña, activa el interruptor, guarda y pulsa **Enviar un email de prueba**.

Sirve igual para el correo de Hostinger (smtp.hostinger.com, puerto 465).

## 4. WhatsApp automático (opcional, API de Meta)

Sin esto, al aceptar una reserva se abre WhatsApp en el móvil con el mensaje escrito y hay que pulsar enviar. Con la API oficial de WhatsApp Business el mensaje sale solo desde el servidor.

1. En **business.facebook.com** crea una app de WhatsApp y registra el número del restaurante (ese número deja de poder usarse en la app normal de WhatsApp, salvo con la «coexistencia» de WhatsApp Business).
2. Crea una plantilla de categoría **Utilidad** con 5 variables en el cuerpo, en este orden: `{{1}}` nombre, `{{2}}` día, `{{3}}` hora, `{{4}}` personas, `{{5}}` enlace para confirmar. Meta solo deja escribir primero a un cliente con una plantilla aprobada. Si la traduces a inglés (`en`) o portugués (`pt_PT`), al cliente le llega en su idioma; si no, en el idioma por defecto.
3. Copia el **identificador del número de teléfono** y un **token de acceso permanente** (usuario del sistema con permiso `whatsapp_business_messaging`).
4. En la app: **Ajustes → Técnico → Avanzado → WhatsApp automático**. Pega los datos, el nombre y el idioma de la plantilla, activa el interruptor, guarda y pulsa **Enviar un WhatsApp de prueba**.

**Recordatorios** (opcional): con otra plantilla de Utilidad de 4 variables (`{{1}}` nombre, `{{2}}` día, `{{3}}` hora, `{{4}}` personas) y el interruptor «Enviar recordatorios 24 h y 12 h antes», a cada reserva confirmada le llega un WhatsApp 24 h y 12 h antes. Cada aviso se marca en la reserva antes de enviarlo, así que nunca sale dos veces; si cambias el día o la hora, vuelven a empezar. Salen con las visitas a la web y el uso de la app (como mucho cada 5 minutos) y, para que lleguen a su hora aunque no entre nadie, conviene un Cron Job en Hostinger cada 10 minutos con la dirección que sale en la app (`cron.php?key=…`) o `php public_html/cron.php`.

El token se guarda cifrado y nunca vuelve a la app. Si Meta da error, la reserva queda igualmente «esperando», el error sale en Ajustes y en la ficha de la reserva está **Enviar a mano** con el enlace de siempre. Hace falta cURL en el servidor (Hostinger lo trae).

## Cómo funcionan las citas

1. El paciente elige tratamiento, día y hora en la web (teléfono obligatorio, email opcional). Ve «Petición realizada».
2. En el panel aparece en **Bandeja**. Al pulsar **Aceptar** se abre WhatsApp con el mensaje y el enlace. Con el WhatsApp automático activado, el botón es **Aceptar y enviar WhatsApp automático** y el mensaje sale solo.
3. El cliente abre el enlace, ve sus datos y pulsa **Confirmar mi cita** (abrir el enlace no confirma: WhatsApp lo abre solo para la vista previa). La app avisa.
4. Desde el mismo enlace puede **cancelarla**. Si el restaurante la cancela o la borra en la app, el enlace dice «Reserva cancelada».
5. Si el cliente puso email o tiene cuenta, recibe también los avisos por email.

Los enlaces no ocupan espacio aparte: cada reserva guarda una clave de 32 caracteres (unos 100 bytes cifrada).

---

## Desarrollo

**Probar en tu ordenador** (PHP 8.1 o superior con GD, sodium y pdo_sqlite):

```bash
cd web && php -S localhost:8080
# Abre http://localhost:8080/install.php y elige SQLite
```

**Cambiar el panel**: el código está en `panel/` (React). Para compilarlo:

```bash
cd panel && pnpm i && npx vite build   # deja el resultado en app/www
cd .. && bash scripts/build-panel.sh   # lo copia a web/panel
```

El panel clásico está en `panel/public/avanzado/` y se copia tal cual.

**Compilar el APK en tu PC** (Android SDK y JDK 21): `cd app && npm install && npm run apk`.

**Google Play**: crea tu propia clave de firma y sustituye `app/android/keystore/app.keystore`. La incluida es pública y solo sirve para instalar el APK a mano.
