# API

Todas las rutas son `api/?r=<ruta>`. Las respuestas son JSON: `{"ok": true, "data": …}` o `{"ok": false, "error": "mensaje para el usuario"}`.
GET para leer y POST con JSON (`Content-Type: application/json`) para escribir.

## Públicas (las usa la web)

Idioma: la web pública manda `?lang=es|en|pt|gl` (o la cookie `idioma`) y los mensajes de error y los emails salen en ese idioma. La API de gestión responde siempre en español.

| Ruta | Método | Datos | Devuelve |
|---|---|---|---|
| `ping` | GET | — | nombre del restaurante |
| `info` | GET | — | nombre, frase, contacto, horario, días cerrados, políticas |
| `menu` | GET | — | carta: `categories` (con `dishes`), `allergens` y `carta_on` y `menus` (menús activos traducidos) |
| `slots&date=AAAA-MM-DD&service=ID` | GET | — | `open`, `reason`, `slots: [{time, left}]`. Con `service`, solo las horas en las que cabe toda la sesión de ese tratamiento |
| `reserve` | POST | `service_id` (obligatorio si hay tratamientos), `date`, `time`, `people`, `name`, `phone`, `email`?, `notes`?, `privacy: 1`, `website: ''` | `id`, `when`, `message`, `linked` (sale en una cuenta), `email` |
| `erase_request` | POST JSON | `contact` (email o teléfono): pide borrar todos los datos; responde siempre lo mismo. Con email llega un enlace; con teléfono, la petición sale en la app |

Límite: 5 reservas por hora desde la misma conexión. `website` es un campo trampa para bots y tiene que ir vacío.

## Clientes (cookie de sesión, mismo dominio)

| Ruta | Método | Datos |
|---|---|---|
| `c_me` | GET | — (cliente con sesión o `null`) |
| `c_register` | POST | `name`, `email`, `phone`?, `password`, `privacy: 1` → envía el email de activación |
| `c_resend` | POST | `email` |
| `c_forgot` | POST | `email` → si hay cuenta activada, envía el enlace para cambiar la contraseña (1 hora, un solo uso). Responde igual exista o no |
| `c_reset` | POST | `t` (token del enlace), `password` → cambia la contraseña y deja al cliente dentro |
| `c_login` | POST | `email`, `password` |
| `c_logout` | POST | — |
| `c_reservations` | GET | — (`upcoming`, `past`) |
| `c_confirm` | POST | `id` |
| `c_cancel` | POST | `id` |
| `c_profile` | POST | `name`, `phone`, `email`, `password`?, `new_password`? |
| `c_delete` | POST | `password` |
| `c_export` | GET | — (descarga JSON con todos sus datos) |

## Gestión (app; cabecera `X-Token`, que se obtiene con `login`)

Todos los usuarios pueden leer reservas y carta. Para cambiar hacen falta permisos: `reservas` (estado, guardar, borrar y cerrar días), `carta` (secciones y platos) y `ajustes` (ajustes, fotos y email de prueba). El administrador los tiene todos y gestiona los usuarios. Sin permiso, la API responde 403.

| Ruta | Método | Datos |
|---|---|---|
| `login` | POST | `username`, `password` → `token` y `me` (rol y permisos) |
| `forgot` | POST | (sin sesión) `username` (usuario o email) → envía el enlace `panel/#reset=…` al email del usuario o, si es administrador sin email, al de avisos del restaurante. Responde igual exista o no |
| `reset_check` | POST | (sin sesión) `t` → `username` si el enlace sigue valiendo (no lo gasta) |
| `reset` | POST | (sin sesión) `t`, `password` → cambia la contraseña y cierra todas las sesiones de ese usuario |
| `me_email` | POST | `email` (vacío lo quita): email propio para recuperar la contraseña |
| `logout`, `me` | POST / GET | — |
| `users` | GET | (administrador) lista de usuarios y permisos posibles |
| `customers` | GET | (administrador) cuentas de clientes (`customers`) y peticiones de borrado por teléfono pendientes (`erase`) |
| `customer_delete` | POST | (administrador) `id`: borra una cuenta |
| `erase_whatsapp` | POST | (administrador) `id` de una petición de borrado: devuelve `wa_url` con el enlace de confirmación para el cliente |
| `erase_dismiss` | POST | (administrador) `id`: descarta una petición de borrado |
| `reject_info` | GET | `id` de una reserva: horas libres ese día cerca de la pedida (`slots`) y textos para rechazarla por WhatsApp (`text_slots` con `{horas}`, `text_none`, `wa_number`) |
| `user_save` | POST | (administrador) `id`?, `username`, `name`, `email`?, `password`?, `role` (`admin` o `staff`), `perms` (`reservas`, `carta`, `ajustes`), `active` |
| `user_delete` | POST | (administrador) `id` |
| `reservations&from=&to=` | GET | reservas entre dos fechas |
| `requests` | GET | `nuevas` y `esperando` |
| `month&month=AAAA-MM` | GET | resumen por día |
| `changes&since=N` | GET | novedades desde el número N (avisos) |
| `slots_admin&date=&minutes=&service=&exclude=` | GET | horas libres sin las reglas de antelación, para una sesión de `minutes` (o la duración de `service`); `exclude` = id de la cita que se está cambiando |
| `reservation_status` | POST | `id`, `status` (`esperando`, `confirmada`, `rechazada`, `cancelada`). Con el WhatsApp automático activado, pasar a `esperando` manda el mensaje por la API de Meta y la respuesta lleva `wa_sent` y `wa_error` |
| `reservation_whatsapp` | POST | `id` de una reserva en `esperando`: reenvía el WhatsApp por la API de Meta (`wa_sent`, `wa_error`) |
| `reservation_save` | POST | `id`? y los datos de la cita, con `service_id`, `service`, `duration` y `patient_id`? (si no va, se une a la ficha con el mismo teléfono) |
| `reservation_delete` | POST | `id` (desaparece de la app; el enlace del cliente dirá «cancelada») |
| `admin_menu` | GET | carta completa, también lo agotado, `menus` (todos), `carta_on` y `menus_button` |
| `category_save`, `category_move`, `category_delete` | POST | `id`, `name` / `dir` |
| `menu_save` | POST JSON | `id`? (sin id = nuevo), `on`, `title`, `price`, `days` (1–7), `note`, `courses` [{`name`, `dishes` [{`name`, `desc`, `extra`, `allergens`, `photo`}]}] (permiso carta) |
| `menu_photo` | POST multipart | `photo` (archivo): sube la foto de un plato de un menú y devuelve `photo` (ruta para `menu_save`) y `photo_url` |
| `menus_button_toggle` | POST JSON | `on`: botón «Ver menús» en la portada (permiso carta) |
| `menu_toggle` | POST JSON | `id`, `on` (permiso carta) |
| `menu_move` | POST JSON | `id`, `dir` (-1 o 1) (permiso carta) |
| `menu_delete` | POST JSON | `id` (permiso carta) |
| `carta_toggle` | POST JSON | `on`: mostrar u ocultar la carta principal en la web (permiso carta) |
| `dish_save` | POST multipart | `id`?, `name`, `price`, `duration` (minutos, 5–480), `category_id`, `description`, `allergens` (separados por comas), `available`, `featured`, `photo` (archivo), `remove_photo` |
| `dish_toggle`, `dish_move`, `dish_delete` | POST | `id`, `available` / `dir` |
| `settings` | GET | ajustes (sin la contraseña del correo) + huecos de foto del tema |
| `settings_save` | POST | cualquier ajuste |
| `web_photo` / `web_photo_reset` | POST | `key` (hueco de `TEMA_FOTOS`), `photo` (archivo) |
| `mail_test` | POST | `to`? → envía un email de prueba |
| `whatsapp_test` | POST | `to`? (por defecto el WhatsApp del restaurante) → envía un WhatsApp de prueba por la API de Meta |
| `tr_status` | GET | (ajustes) cuántos textos hay traducidos de cada idioma y el último error del traductor |
| `tr_run` | POST | (ajustes) traduce ahora lo que falte |
| `tr_list` | GET | (ajustes) todos los textos con sus traducciones, para revisarlas |
| `tr_save` | POST | (ajustes) `h`, `lang`, `txt` → guarda una traducción corregida a mano (ya no se pisa) |
| `password` | POST | `current`, `new` |

### Pacientes (permiso `pacientes`)

| Ruta | Método | Datos |
|---|---|---|
| `patients` | GET | todas las fichas (el panel busca y filtra en el móvil), con `visits`, `last`, `next`, número de notas y la última |
| `patient&id=` | GET | una ficha con `notes` (notas de sesión) y `appointments` (sus citas) |
| `patient_save` | POST | `id`?, `name`, `phone`, `email`, `birth`, `reason`, `history`, `tags`; `reservation_id`? para crearla desde una cita. Une las citas con el mismo teléfono |
| `patient_delete` | POST | `id` |
| `note_save` | POST | `id`?, `patient_id`, `date`, `service`, `pain` (0–10, o -1 sin dato), `body`, `use_bono` (descuenta una sesión del bono) |
| `note_delete` | POST | `id` |
| `bono` | POST | `id` del paciente y `add` (vende un bono de N sesiones) o `left` (sesiones que quedan) |
