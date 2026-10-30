# Guía de tu web de reservas

Esta guía es para ti, el dueño o encargado del restaurante. No hace falta saber de informática: sigue los pasos en orden y, si algo no sale como aquí se explica, apunta el mensaje que ves en pantalla y pásaselo a quien te hizo la web.

**Lo que tienes:**

- **Una web** donde tus clientes ven la carta y los menús y piden mesa. También pueden crearse una cuenta para ver y cancelar sus reservas.
- **Una app de gestión** (en el móvil o en el ordenador) donde aceptas o rechazas las reservas, cambias la carta y ajustas la web.
- **WhatsApp automático** (opcional): el mensaje para que el cliente confirme la reserva le llega solo, y además recibe un recordatorio 24 y 12 horas antes.

---

## 1. Antes de empezar: lo que necesitas

- Un plan de **Hostinger** con tu dominio (por ejemplo `casamarisa.com`). Entras en el panel de Hostinger, que se llama **hPanel**, en hpanel.hostinger.com.
- Un **email** donde quieras recibir el aviso de cada reserva.
- Opcional: una cuenta de **Gmail** para que los emails a tus clientes salgan desde ahí.
- Opcional: el **número de WhatsApp** del restaurante, si quieres el WhatsApp automático.

---

## 2. Poner la web en marcha en Hostinger

Normalmente esta parte la hace quien te ha hecho la web. Si te toca a ti:

### 2.1. Crear la base de datos (MySQL)

La base de datos es donde se guardan las reservas, la carta y los ajustes.

1. En hPanel, entra en **Bases de datos → Administración**.
2. En «Crear una nueva base de datos MySQL», escribe:
   - Nombre de la base de datos: por ejemplo `restaurante`. Hostinger le pondrá delante algo como `u123456789_`.
   - Usuario: por ejemplo `admin`. Quedará algo como `u123456789_admin`.
   - Contraseña: invéntate una larga y **apúntala**.
3. Pulsa **Crear**. Apunta los tres datos **completos**, con el `u123456789_` delante: los necesitas en el paso 2.3.

> También se puede instalar con **SQLite**, que no necesita crear nada. MySQL es lo recomendado en Hostinger porque Hostinger hace copias de seguridad de las bases de datos.

### 2.2. Subir la web

Quien te hizo la web ya la ha preparado para que Hostinger la coja de GitHub:

1. En hPanel, abre **Archivos → Administrador de archivos** y vacía la carpeta `public_html`.
2. Ve a **Avanzado → Git** y crea un repositorio con la dirección que te hayan dado. En «Rama» pon **`hostinger`** y deja «Directorio» vacío.
3. Pulsa **Desplegar**. Activa también el **despliegue automático**: así las mejoras llegan solas.

### 2.3. Instalar

1. Abre en el navegador `https://tu-dominio.com/install.php`.
2. Rellena el formulario:
   - **Base de datos:** elige **MySQL**. En «Servidor» deja `localhost` y escribe el nombre, el usuario y la contraseña del paso 2.1.
   - **Restaurante:** el nombre y el email donde quieres recibir las reservas.
   - **Acceso a la app:** el usuario (por ejemplo `admin`) y una contraseña de al menos 8 caracteres. **Apúntalos**: son para entrar en la app de gestión.
3. Pulsa instalar. Cuando diga que está listo, entra en el Administrador de archivos y **borra `install.php`**.
4. En hPanel, en **Seguridad → SSL**, comprueba que el certificado está **activo**. Es el candado del navegador, y sin él la web no es segura.

> **Muy importante:** en el Administrador de archivos verás un archivo `config.php`. Descárgalo y guárdalo en un sitio seguro, por ejemplo en un pendrive o en tu correo. Los datos de tus clientes van cifrados y, sin ese archivo, no se pueden recuperar.

---

## 3. La app de gestión

Puedes usarla en el móvil o en el ordenador:

- **Android:** instala la app que te pasen (archivo `.apk`). La primera vez pide la dirección de tu web: escribe `tu-dominio.com`.
- **iPhone:** abre `https://tu-dominio.com/panel/` en Safari, pulsa **Compartir** y luego **Añadir a pantalla de inicio**.
- **Ordenador:** entra en `https://tu-dominio.com/panel/`.

Entra con el usuario y la contraseña del paso 2.3.

### ¿Has olvidado la contraseña?

En la pantalla de entrada pulsa **«¿Has olvidado la contraseña?»** y escribe tu usuario o tu email. Te llegará un email con un enlace que **caduca en 1 hora y solo sirve una vez**. Ábrelo, elige la contraseña nueva y ya puedes entrar. Al cambiarla se cierra la sesión en los demás móviles.

- Para que el enlace llegue a tu email, ponlo en **Ajustes → Cuenta → Contraseña → «Tu email para recuperar la contraseña»**. Si eres administrador y no lo pones, el enlace llega al email de avisos del restaurante.
- Si un camarero olvida la suya y no tiene email puesto, cámbiasela tú desde **Ajustes → Cuenta → Usuarios**.

Tus clientes también pueden recuperar la suya: en la web, en **Entrar**, tienen el enlace **«¿Has olvidado la contraseña?»**.

---

## 4. Primeros ajustes

Todo se configura en la pestaña **Ajustes** de la app, que está ordenada en pestañas. Arriba tienes un buscador: escribe por ejemplo «horario» o «Gmail» y te lleva al sitio.

| Pestaña | Qué rellenar |
|---|---|
| **Local** | Nombre y presentación, contacto y dirección (teléfono, WhatsApp, email), redes sociales, fotos de la web y **datos legales** (titular y NIF: son obligatorios por ley). |
| **Horarios** | Los días y horas en los que abres. Si la cocina tiene otro horario, actívalo en «Horario de cocina»: las reservas solo se ofrecerán con la cocina abierta. |
| **Reservas** | **Mesas y turnos**: cuántas personas caben (aforo), cuánto tiempo ocupa una mesa, cada cuánto se puede reservar y el grupo más grande que aceptas por internet. **Cuándo se puede reservar**: con cuánta antelación se puede pedir mesa. **Normas para el cliente**: lo que quieras que lea antes de reservar. |
| **Técnico** | Los idiomas de la web y, dentro de **Avanzado**, el envío de emails, el WhatsApp automático, el traductor y Google. Se configura una vez y no hay que volver a tocarlo. |
| **Cuenta** | Usuarios (por ejemplo, un usuario para cada camarero, con lo que puede hacer), las cuentas de tus clientes, los avisos en el móvil y tu contraseña. |

La **carta** y los **menús** se cambian en la pestaña **Carta**: secciones, platos, precios, fotos y alérgenos. Si un plato se acaba, márcalo como agotado.

### 4.1. Que los emails lleguen bien (Gmail)

Sin esto, los emails salen desde el hosting y a veces terminan en spam. Con Gmail llegan mucho mejor:

1. En tu cuenta de Google, activa la **verificación en 2 pasos**.
2. Entra en **myaccount.google.com/apppasswords**, crea una contraseña llamada «Web restaurante» y copia las 16 letras que te da.
3. En la app, ve a **Ajustes → Técnico → Avanzado → Envío de emails**. Pon tu Gmail y esas 16 letras, activa el interruptor y pulsa **Guardar**.
4. Pulsa **Enviar un email de prueba** y comprueba que te llega.

---

## 5. WhatsApp automático (opcional)

**Sin activarlo**, al aceptar una reserva se abre WhatsApp en tu móvil con el mensaje ya escrito y solo tienes que pulsar enviar. Funciona bien y no cuesta nada.

**Con el WhatsApp automático**, el mensaje sale solo desde la web y además se mandan recordatorios. Usa la API oficial de WhatsApp Business (de Meta). Meta cobra unos céntimos por cada mensaje de este tipo, así que mira sus precios.

> Ojo: el número que registres deja de poder usarse en la app normal de WhatsApp, salvo que uses la «coexistencia» de WhatsApp Business. Si tienes dudas, usa otro número.

### 5.1. Preparar Meta

1. Entra en **business.facebook.com**, crea una app de WhatsApp y registra el número del restaurante.
2. Crea una **plantilla** de categoría **Utilidad**, por ejemplo con el nombre `confirmar_reserva` y este texto:
   «Hola {{1}}, tenemos mesa el {{2}} a las {{3}} ({{4}}). Confírmala aquí: {{5}}»
   Las variables son: 1 = nombre, 2 = día, 3 = hora, 4 = personas y 5 = enlace. Espera a que Meta la apruebe, que suele tardar de minutos a un día.
3. Para los recordatorios, crea otra plantilla de Utilidad, por ejemplo `recordatorio_reserva`:
   «Hola {{1}}, te recordamos tu reserva el {{2}} a las {{3}} ({{4}}). ¡Te esperamos!»
4. Copia el **identificador del número de teléfono** y un **token de acceso permanente** (se crea con un «usuario del sistema»).

### 5.2. Ponerlo en la app

1. Ve a **Ajustes → Técnico → Avanzado → WhatsApp automático**.
2. Pega el identificador y el token, y escribe el nombre de la plantilla y su idioma (`es`).
3. Activa **«Enviar el WhatsApp automáticamente al aceptar»** y, si quieres recordatorios, escribe el nombre de la otra plantilla y activa **«Enviar recordatorios 24 h y 12 h antes»**.
4. Pulsa **Guardar** y luego **Enviar un WhatsApp de prueba**. Debe llegarte al WhatsApp del restaurante.

Si algo falla, el error sale en esa misma sección y la reserva no se pierde: en su ficha tienes **Enviar a mano**.

### 5.3. Que los recordatorios salgan a su hora (tarea programada)

Los recordatorios salen cuando alguien visita la web o usa la app. Para que salgan puntuales aunque no entre nadie, crea una tarea programada:

1. En la app, en **WhatsApp automático → Tarea programada**, copia la **dirección para el cron** (empieza por `https://tu-dominio.com/cron.php?key=`).
2. En hPanel, ve a **Avanzado → Cron Jobs**.
3. Crea una tarea **cada 10 minutos** de tipo «Personalizado», con este comando (cambia la dirección por la que copiaste):
   `curl -s "https://tu-dominio.com/cron.php?key=…"`
   Si Hostinger te deja elegir «PHP», también vale `php public_html/cron.php`.
4. Guarda. Ya no tienes que hacer nada más.

---

## 6. El día a día

1. **Llega una reserva:** el móvil avisa (activa los avisos en **Ajustes → Cuenta → Avisos de reservas**) y además te llega un email. La verás en **Peticiones**.
2. **La aceptas:** pulsa **Confirmar por WhatsApp**, o «Aceptar y enviar WhatsApp automático» si lo tienes activado. El cliente recibe un enlace y, cuando lo abre y pulsa confirmar, la app te avisa.
3. **No hay sitio:** pulsa **Rechazar**. La app te propone un WhatsApp con las horas libres de ese día.
4. **Hoy y Calendario:** ves las reservas del día y de cualquier fecha. Las que te hagan por teléfono las apuntas con el botón **Nueva reserva**. En el Calendario también cierras días sueltos (festivos o vacaciones).
5. **Si el cliente cancela**, desde su enlace o desde su cuenta, la app te avisa.

---

## 7. Preguntas frecuentes

**No me llegan los emails de reservas.**
Mira en la carpeta de spam. Configura Gmail (apartado 4.1) y pulsa «Enviar un email de prueba». Si da error, el mensaje sale en esa misma pantalla.

**Un cliente dice que no le llega el enlace para recuperar la contraseña.**
Que mire en spam y espere un par de minutos: si lo pide varias veces seguidas, solo se manda uno cada 2 minutos. El enlace caduca en 1 hora.

**Quiero cerrar por vacaciones.**
En la pestaña **Calendario**, toca cada día y pulsa **«Cerrar este día (festivo, vacaciones…)»**. Ese día ya no se podrá reservar por internet. Para volver a abrirlo, pulsa **«Abrir este día»**.

**Un cliente quiere que borremos sus datos.**
Él mismo puede hacerlo desde **Borrar mis datos**, en el pie de la web. Si te lo pide a ti, lo tienes en **Ajustes → Cuenta → Clientes con cuenta**.

**Se me ha estropeado el móvil.**
No pasa nada: todo está en la web. Instala la app en el nuevo y entra con tu usuario.

**¿Hay copia de seguridad?**
Hostinger hace copias de la base de datos y de los archivos (en hPanel: **Archivos → Copias de seguridad**). Además, guarda tú el archivo `config.php` (paso 2.3).
