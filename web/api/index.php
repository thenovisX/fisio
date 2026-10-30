<?php
declare(strict_types=1);

/*
 * API del centro. La usan la web pública y la app de gestión.
 * Rutas: api/?r=<ruta>. Las de gestión necesitan la cabecera X-Token (se obtiene con r=login).
 */

require dirname(__DIR__) . '/lib/bootstrap.php';

cors_headers(); // solo la propia web, la app y los orígenes de config.php (nunca «*»)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!installed()) fail('La web aún no está instalada. Abre install.php en el navegador.', 503);
api_rate_limit();

$r = (string)($_GET['r'] ?? '');
$in = input();
$id = (int)($in['id'] ?? $_GET['id'] ?? 0);

detect_lang(); // los mensajes para el cliente salen en su idioma
try {
    /* ---------- Públicas ---------- */
    switch ($r) {
        case 'ping':
            out(['restaurant' => settings()['name'], 'version' => 1]);
        case 'info':
            out(public_info());
        case 'menu':
            out(menu_data(false) + ['carta_on' => carta_on(), 'menus' => menus_public()]);
        case 'slots':
            // Con &service=ID, solo las horas en las que cabe ese tratamiento entero
            out(day_slots((string)($_GET['date'] ?? ''), false, 0, service_minutes(service_find((int)($_GET['service'] ?? 0)))));
        case 'reserve':
            only_post(); same_origin_only();
            out(create_public_reservation($in), 201);
        case 'erase_request':
            only_post(); same_origin_only();
            out(erase_request($in));
        case 'login':
            only_post();
            out(login($in));
        case 'forgot':
            only_post();
            user_forgot($in);
            out(true);
        case 'reset_check':
            only_post();
            out(user_reset_check($in));
        case 'reset':
            only_post();
            out(user_reset($in));

        /* Cuentas de clientes (cookie de sesión, solo desde la propia web) */
        case 'c_me':
            out(['customer' => customer_out(current_customer())]);
        case 'c_register':
            only_post(); same_origin_only();
            out(customer_register($in), 201);
        case 'c_login':
            only_post(); same_origin_only();
            out(customer_login($in));
        case 'c_logout':
            only_post(); same_origin_only();
            customer_logout();
            out(true);
        case 'c_resend':
            only_post(); same_origin_only();
            customer_resend($in);
            out(true);
        case 'c_forgot':
            only_post(); same_origin_only();
            customer_forgot($in);
            out(true);
        case 'c_reset':
            only_post(); same_origin_only();
            out(customer_reset($in));
        case 'c_export':
            $data = customer_export(require_customer());
            header('Content-Disposition: attachment; filename="mis-datos.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        case 'c_reservations':
            out(customer_reservations(require_customer()));
        case 'c_confirm':
            only_post(); same_origin_only();
            out(customer_confirm(require_customer(), $id));
        case 'c_cancel':
            only_post(); same_origin_only();
            out(customer_cancel(require_customer(), $id));
        case 'c_profile':
            only_post(); same_origin_only();
            out(customer_update(require_customer(), $in));
        case 'c_delete':
            only_post(); same_origin_only();
            customer_delete(require_customer(), $in);
            out(true);
    }

    /* ---------- Gestión (requieren sesión) ---------- */
    $user = require_auth();
    lang('es'); // el panel va en español
    switch ($r) {
        case 'logout':
            logout();
            out(true);
        case 'me':
            out(['user' => $user['username'], 'restaurant' => settings()['name'], 'today' => today(), 'me' => user_out($user)]);
        case 'me_email':
            only_post();
            out(user_set_email($user, $in));
        case 'customers':
            require_admin($user);
            out(['customers' => customers_list(), 'erase' => erase_pending()]);
        case 'customer_delete':
            only_post();
            require_admin($user);
            customer_delete_admin($id);
            out(true);
        case 'erase_whatsapp':
            only_post();
            require_admin($user);
            out(erase_whatsapp($id));
        case 'erase_dismiss':
            only_post();
            require_admin($user);
            erase_dismiss($id);
            out(true);
        case 'reject_info':
            require_perm($user, 'reservas');
            out(reject_info((int)($_GET['id'] ?? 0)));
        case 'users':
            require_admin($user);
            out(['users' => users_list(), 'perms' => PERMS]);
        case 'user_save':
            only_post();
            require_admin($user);
            out(user_save($user, $in));
        case 'user_delete':
            only_post();
            require_admin($user);
            user_delete($user, $id);
            out(true);

        case 'reservations':
            $from = (string)($_GET['from'] ?? today());
            out(list_reservations($from, (string)($_GET['to'] ?? $from)));
        case 'requests':
            out(list_requests());
        case 'month':
            out(month_summary((string)($_GET['month'] ?? date('Y-m'))));
        case 'changes':
            // Mientras el panel está abierto, se va traduciendo poco a poco lo que falte de la carta
            try { translate_pending(3); } catch (Throwable $e) { /* se reintenta luego */ }
            out(changes((int)($_GET['since'] ?? 0)));
        case 'tr_status':
            out(translation_status());
        case 'tr_run':
            only_post();
            require_perm($user, 'carta');
            $n = translate_pending(20, true);
            out(['done' => $n] + translation_status());
        case 'tr_list':
            out(['items' => translations_list(), 'langs' => array_values(array_diff(langs_on(), ['es']))]);
        case 'tr_save':
            only_post();
            require_perm($user, 'carta');
            translation_save($in);
            out(true);
        case 'slots_admin':
            $min = (int)($_GET['minutes'] ?? 0) ?: service_minutes(service_find((int)($_GET['service'] ?? 0)));
            out(day_slots((string)($_GET['date'] ?? ''), true, (int)($_GET['exclude'] ?? 0), $min));

        /* Fichas de pacientes (permiso «pacientes») */
        case 'patients':
            require_perm($user, 'pacientes');
            out(patients_list());
        case 'patient':
            require_perm($user, 'pacientes');
            out(patient_get((int)($_GET['id'] ?? 0)));
        case 'patient_save':
            only_post();
            require_perm($user, 'pacientes');
            out(patient_save($in));
        case 'patient_delete':
            only_post();
            require_perm($user, 'pacientes');
            patient_delete($id);
            out(true);
        case 'note_save':
            only_post();
            require_perm($user, 'pacientes');
            out(note_save($in, $user));
        case 'note_delete':
            only_post();
            require_perm($user, 'pacientes');
            out(note_delete($id));
        case 'bono':
            only_post();
            require_perm($user, 'pacientes');
            out(bono_set($id, $in));
        case 'reservation_status':
            only_post();
            require_perm($user, 'reservas');
            out(set_reservation_status($id, (string)($in['status'] ?? '')));
        case 'reservation_whatsapp':
            only_post();
            require_perm($user, 'reservas');
            out(resend_reservation_whatsapp($id));
        case 'reservation_save':
            only_post();
            require_perm($user, 'reservas');
            out(save_reservation($in));
        case 'reservation_delete':
            only_post();
            require_perm($user, 'reservas');
            delete_reservation($id);
            out(true);

        case 'admin_menu':
            out(menu_data(true) + ['menus' => menus_all(), 'carta_on' => carta_on(), 'menus_button' => menus_button()]);
        case 'menu_photo':
            only_post();
            require_perm($user, 'carta');
            out(menu_photo_upload($_FILES['photo'] ?? null));
        case 'menus_button_toggle':
            only_post();
            require_perm($user, 'carta');
            out(['menus_button' => menus_button_toggle(filter_var($in['on'] ?? false, FILTER_VALIDATE_BOOLEAN))]);
        case 'menu_save':
            only_post();
            require_perm($user, 'carta');
            $res = menu_save($in);
            tr_after_save();
            out($res);
        case 'menu_toggle':
            only_post();
            require_perm($user, 'carta');
            $res = menu_toggle((string)($in['id'] ?? ''), filter_var($in['on'] ?? false, FILTER_VALIDATE_BOOLEAN));
            tr_after_save();
            out($res);
        case 'menu_delete':
            only_post();
            require_perm($user, 'carta');
            menu_delete((string)($in['id'] ?? ''));
            out(true);
        case 'menu_move':
            only_post();
            require_perm($user, 'carta');
            menu_move((string)($in['id'] ?? ''), (int)($in['dir'] ?? 1));
            out(true);
        case 'carta_toggle':
            only_post();
            require_perm($user, 'carta');
            out(['carta_on' => carta_toggle(filter_var($in['on'] ?? false, FILTER_VALIDATE_BOOLEAN))]);
        case 'category_save':
            only_post();
            require_perm($user, 'carta');
            $res = save_category($in);
            tr_after_save();
            out($res);
        case 'category_delete':
            only_post();
            require_perm($user, 'carta');
            delete_category($id);
            out(true);
        case 'category_move':
            only_post();
            require_perm($user, 'carta');
            move_category($id, (int)($in['dir'] ?? 1));
            out(true);
        case 'dish_save':
            only_post();
            require_perm($user, 'carta');
            $res = save_dish($in, $_FILES['photo'] ?? null);
            tr_after_save();
            out($res);
        case 'dish_toggle':
            only_post();
            require_perm($user, 'carta');
            out(toggle_dish($id, filter_var($in['available'] ?? false, FILTER_VALIDATE_BOOLEAN)));
        case 'dish_move':
            only_post();
            require_perm($user, 'carta');
            move_dish($id, (int)($in['dir'] ?? 1));
            out(true);
        case 'dish_delete':
            only_post();
            require_perm($user, 'carta');
            delete_dish($id);
            out(true);

        case 'settings':
            $st = settings_admin();
            // La dirección del cron lleva su clave: solo para quien puede cambiar los ajustes
            if (!user_can($user, 'ajustes')) unset($st['cron_url'], $st['smtp_error'], $st['wa_error']);
            out($st + ['allergens' => allergen_list(), 'web_photos' => web_photos_info()]);
        case 'web_photo':
            only_post();
            require_perm($user, 'ajustes');
            out(web_photo_set((string)($in['key'] ?? ''), $_FILES['photo'] ?? null));
        case 'web_photo_reset':
            only_post();
            require_perm($user, 'ajustes');
            out(web_photo_reset((string)($in['key'] ?? '')));
        case 'settings_save':
            only_post();
            // Cerrar o abrir días sueltos lo puede hacer quien gestiona reservas; lo demás, quien tiene «ajustes»
            if (array_keys($in) === ['closed_dates']) require_perm($user, user_can($user, 'ajustes') ? 'ajustes' : 'reservas');
            else require_perm($user, 'ajustes');
            settings_save($in);
            tr_after_save();
            out(settings_admin());
        case 'mail_test':
            only_post();
            require_perm($user, 'ajustes');
            $to = trim((string)($in['to'] ?? '')) ?: (settings()['notify_email'] ?: settings()['email']);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new ValidationError('Pon antes el email para avisos de reservas (en Contacto)');
            $s = settings();
            if (!$s['smtp_on']) throw new ValidationError('Activa primero el envío con tu cuenta y guarda');
            send_mail($to, "Prueba de email de {$s['name']}", "¡Funciona! Los emails de la web ya salen desde {$s['smtp_user']}.\n\nA partir de ahora los clientes recibirán desde esta cuenta el enlace para activar su cuenta y los avisos de sus reservas.", true);
            out(['to' => $to]);
        case 'whatsapp_test':
            only_post();
            require_perm($user, 'ajustes');
            $s = settings();
            out(['to' => wa_test(trim((string)($in['to'] ?? '')) ?: ($s['whatsapp'] ?: $s['phone']))]);
        case 'password':
            only_post();
            change_password($user, $in);
            out(true);
    }
    fail('Ruta desconocida', 404);
} catch (ValidationError $e) {
    fail(t_msg($e->getMessage()), 422);
} catch (Throwable $e) {
    error_log('[restaurante] ' . $e);
    fail(!empty(cfg()['debug']) ? $e->getMessage() : 'Error en el servidor. Inténtalo de nuevo.', 500);
}

function public_info(): array
{
    $s = settings();
    $keys = ['name', 'tagline', 'description', 'address', 'city', 'phone', 'whatsapp', 'email', 'instagram', 'hours', 'closed_dates', 'max_people', 'days_ahead', 'late_minutes', 'policies'];
    return array_intersect_key($s, array_flip($keys)) + ['today' => today()];
}
