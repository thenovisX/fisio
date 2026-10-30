<?php
declare(strict_types=1);

/** Los 14 alérgenos de declaración obligatoria (Reglamento UE 1169/2011). */
const ALLERGENS = [
    'gluten'      => ['Gluten', 'Gl', 'Trigo (también espelta y kamut), centeno, cebada, avena y todo lo hecho con ellos: pan, pasta, rebozados, salsas espesadas.'],
    'crustaceos'  => ['Crustáceos', 'Cr', 'Gambas, langostinos, cigalas, bogavante, nécoras, cangrejos y caldos o salsas que los lleven.'],
    'huevos'      => ['Huevos', 'Hu', 'Huevo de cualquier ave y lo elaborado con él: mayonesa, alioli, masas, rebozados, postres.'],
    'pescado'     => ['Pescado', 'Pe', 'Cualquier pescado y sus derivados, como fumet, caldos o salsas de pescado.'],
    'cacahuetes'  => ['Cacahuetes', 'Ca', 'Cacahuetes y productos que los contienen, como aceite o crema de cacahuete.'],
    'soja'        => ['Soja', 'So', 'Soja y derivados: salsa de soja, tofu, edamame, bebida de soja, lecitina de soja.'],
    'lacteos'     => ['Lácteos', 'La', 'Leche de cualquier animal y derivados, incluida la lactosa: nata, mantequilla, queso, yogur.'],
    'frutos'      => ['Frutos de cáscara', 'Fc', 'Almendras, avellanas, nueces, anacardos, pacanas, pistachos, macadamias y nueces de Brasil.'],
    'apio'        => ['Apio', 'Ap', 'Apio en rama, en raíz o como especia, y caldos, sopas o sofritos que lo lleven.'],
    'mostaza'     => ['Mostaza', 'Mo', 'Mostaza en grano, en polvo o en salsa, y aliños o vinagretas que la contengan.'],
    'sesamo'      => ['Sésamo', 'Se', 'Granos y aceite de sésamo, tahini, y panes o salsas que lo lleven.'],
    'sulfitos'    => ['Sulfitos', 'Su', 'Dióxido de azufre y sulfitos (más de 10 mg/kg). Suelen estar en el vino, la cerveza, la sidra y algunos encurtidos.'],
    'altramuces'  => ['Altramuces', 'Al', 'Altramuces y harinas o productos elaborados con ellos.'],
    'moluscos'    => ['Moluscos', 'Ml', 'Mejillones, almejas, ostras, vieiras, zamburiñas, pulpo, calamar, sepia y caracoles.'],
];

function allergen_list(): array
{
    $o = [];
    foreach (ALLERGENS as $k => [$name, $abbr, $desc]) $o[] = ['key' => $k, 'name' => $name, 'abbr' => $abbr, 'desc' => $desc];
    return $o;
}

function dish_out(array $d): array
{
    return [
        'id' => (int)$d['id'], 'category_id' => (int)$d['category_id'], 'name' => $d['name'],
        'description' => $d['description'], 'price' => (float)$d['price'],
        'allergens' => $d['allergens'] === '' ? [] : explode(',', $d['allergens']),
        'photo' => $d['photo'] === '' ? '' : (preg_match('#^https?://#', $d['photo']) ? $d['photo'] : base_url() . '/' . $d['photo']),
        'available' => (bool)$d['available'], 'featured' => (bool)$d['featured'], 'position' => (int)$d['position'],
        'duration' => (int)($d['duration'] ?? 0) ?: (int)settings()['table_minutes'],   // minutos que dura el tratamiento
    ];
}

/** Carta completa agrupada por categorías. $all = incluir platos agotados (para el panel). */
function menu_data(bool $all): array
{
    $cats = q('SELECT * FROM categories ORDER BY position, id')->fetchAll();
    $dishes = q('SELECT * FROM dishes ' . ($all ? '' : 'WHERE available = 1 ') . 'ORDER BY position, id')->fetchAll();
    $by = [];
    foreach ($dishes as $d) $by[(int)$d['category_id']][] = dish_out($d);
    $out = [];
    foreach ($cats as $c) {
        $items = $by[(int)$c['id']] ?? [];
        if (!$all && !$items) continue;
        $out[] = ['id' => (int)$c['id'], 'name' => $c['name'], 'position' => (int)$c['position'], 'dishes' => $items];
    }
    return ['categories' => $out, 'allergens' => allergen_list()];
}

function save_category(array $in): array
{
    $name = str_in($in, 'name', 80, true, 'nombre de la sección');
    $id = (int)($in['id'] ?? 0);
    if ($id) {
        q('UPDATE categories SET name = ? WHERE id = ?', [$name, $id]);
    } else {
        $pos = (int)q('SELECT COALESCE(MAX(position), 0) + 1 FROM categories')->fetchColumn();
        q('INSERT INTO categories (name, position) VALUES (?, ?)', [$name, $pos]);
        $id = (int)db()->lastInsertId();
    }
    return ['id' => $id, 'name' => $name];
}

function delete_category(int $id): void
{
    $n = (int)q('SELECT COUNT(*) FROM dishes WHERE category_id = ?', [$id])->fetchColumn();
    if ($n) throw new ValidationError("Esta sección tiene $n tratamientos. Muévelos o bórralos antes.");
    q('DELETE FROM categories WHERE id = ?', [$id]);
}

function get_dish(int $id): array
{
    $d = q('SELECT * FROM dishes WHERE id = ?', [$id])->fetch();
    if (!$d) throw new ValidationError('El tratamiento no existe');
    return $d;
}

function save_dish(array $in, ?array $file): array
{
    $id = (int)($in['id'] ?? 0);
    $cat = (int)($in['category_id'] ?? 0);
    if (!q('SELECT id FROM categories WHERE id = ?', [$cat])->fetch()) throw new ValidationError('Elige una sección');
    $name = str_in($in, 'name', 120, true, 'nombre del tratamiento');
    $desc = mb_substr(trim((string)($in['description'] ?? '')), 0, 400);
    $price = round((float)str_replace(',', '.', (string)($in['price'] ?? '0')), 2);
    if ($price < 0 || $price > 9999) throw new ValidationError('Precio no válido');
    $al = $in['allergens'] ?? [];
    if (is_string($al)) $al = $al === '' ? [] : explode(',', $al);
    $al = implode(',', array_values(array_intersect(array_keys(ALLERGENS), (array)$al)));
    $available = filter_var($in['available'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $featured = filter_var($in['featured'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $dur = (int)($in['duration'] ?? 0) ?: (int)settings()['table_minutes'];
    if ($dur < 5 || $dur > 480) throw new ValidationError('La duración va de 5 a 480 minutos');

    if ($id) {
        $old = get_dish($id);
        q('UPDATE dishes SET category_id=?, name=?, description=?, price=?, allergens=?, available=?, featured=?, duration=? WHERE id=?',
            [$cat, $name, $desc, $price, $al, $available, $featured, $dur, $id]);
    } else {
        $pos = (int)q('SELECT COALESCE(MAX(position), 0) + 1 FROM dishes WHERE category_id = ?', [$cat])->fetchColumn();
        q('INSERT INTO dishes (category_id, name, description, price, allergens, photo, available, featured, position, duration) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$cat, $name, $desc, $price, $al, '', $available, $featured, $pos, $dur]);
        $id = (int)db()->lastInsertId();
        $old = get_dish($id);
    }

    if (filter_var($in['remove_photo'] ?? false, FILTER_VALIDATE_BOOLEAN) && $old['photo']) {
        remove_upload($old['photo']);
        q("UPDATE dishes SET photo = '' WHERE id = ?", [$id]);
    }
    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $path = store_photo($file, $id);
        if ($old['photo']) remove_upload($old['photo']);
        q('UPDATE dishes SET photo = ? WHERE id = ?', [$path, $id]);
    }
    return dish_out(get_dish($id));
}

function toggle_dish(int $id, bool $available): array
{
    get_dish($id);
    q('UPDATE dishes SET available = ? WHERE id = ?', [$available ? 1 : 0, $id]);
    return dish_out(get_dish($id));
}

function delete_dish(int $id): void
{
    $d = get_dish($id);
    if ($d['photo']) remove_upload($d['photo']);
    q('DELETE FROM dishes WHERE id = ?', [$id]);
}

/** Sube o baja un plato (dir = -1 / 1) dentro de su sección. */
function move_dish(int $id, int $dir): void
{
    $d = get_dish($id);
    $list = q('SELECT id FROM dishes WHERE category_id = ? ORDER BY position, id', [$d['category_id']])->fetchAll(PDO::FETCH_COLUMN);
    $i = array_search($id, array_map('intval', $list), true);
    $j = $i + ($dir < 0 ? -1 : 1);
    if ($i === false || $j < 0 || $j >= count($list)) return;
    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
    foreach ($list as $pos => $did) q('UPDATE dishes SET position = ? WHERE id = ?', [$pos + 1, (int)$did]);
}

function move_category(int $id, int $dir): void
{
    $list = array_map('intval', q('SELECT id FROM categories ORDER BY position, id')->fetchAll(PDO::FETCH_COLUMN));
    $i = array_search($id, $list, true);
    $j = $i + ($dir < 0 ? -1 : 1);
    if ($i === false || $j < 0 || $j >= count($list)) return;
    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
    foreach ($list as $pos => $cid) q('UPDATE categories SET position = ? WHERE id = ?', [$pos + 1, $cid]);
}

/* ---------- Fotos ---------- */

function uploads_dir(): string
{
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function remove_upload(string $rel): void
{
    // Solo archivos sueltos de uploads/ (nada de «uploads/../config.php»)
    if (!preg_match('#^uploads/[A-Za-z0-9_-][A-Za-z0-9._-]*$#', $rel) || str_contains($rel, '..')) return;
    $f = dirname(__DIR__) . '/' . $rel;
    if (is_file($f)) @unlink($f);
}

function store_photo(array $file, int $dishId): string
{
    return store_image($file, 'plato-' . $dishId, 1200);
}

function store_image(array $file, string $prefix, int $max): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new ValidationError($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'La foto pesa demasiado' : 'No se pudo subir la foto');
    }
    if ($file['size'] > 12 * 1024 * 1024) throw new ValidationError('La foto pesa demasiado (máx. 12 MB)');
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new ValidationError('La foto tiene que ser JPG, PNG o WEBP');
    }
    $name = 'uploads/' . $prefix . '-' . bin2hex(random_bytes(4)) . '.jpg';
    $dest = dirname(__DIR__) . '/' . $name;
    uploads_dir();

    if (!function_exists('imagecreatetruecolor')) {
        if ($info[2] !== IMAGETYPE_JPEG) throw new ValidationError('Sube la foto en JPG');
        move_uploaded_file($file['tmp_name'], $dest);
        return $name;
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
    };
    if (!$src) throw new ValidationError('No se pudo leer la foto');

    // Corrige el giro de las fotos hechas con el móvil
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) $src = imagerotate($src, $rot, 0);
    }
    $w = imagesx($src);
    $hgt = imagesy($src);
    $scale = min(1, $max / max($w, $hgt));
    $nw = (int)round($w * $scale);
    $nh = (int)round($hgt * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    imagejpeg($dst, $dest, 82);
    return $name;
}

/* ---------- Fotos de la web (portada, nosotros, galería) ---------- */


function web_photo_set(string $key, ?array $file): array
{
    if (!isset(tema_fotos()[$key])) throw new ValidationError('Foto no válida');
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new ValidationError('Elige una foto');
    $photos = settings()['photos'];
    $path = store_image($file, 'web-' . $key, array_key_first(tema_fotos()) === $key ? 2200 : 1600);
    if (!empty($photos[$key])) remove_upload($photos[$key]);
    $photos[$key] = $path;
    web_photos_save($photos);
    return web_photos_info();
}

function web_photo_reset(string $key): array
{
    $photos = settings()['photos'];
    if (!empty($photos[$key])) remove_upload($photos[$key]);
    unset($photos[$key]);
    web_photos_save($photos);
    return web_photos_info();
}

function web_photos_save(array $photos): void
{
    q("DELETE FROM settings WHERE k = 'photos'");
    q("INSERT INTO settings (k, v) VALUES ('photos', ?)", [json_encode($photos, JSON_UNESCAPED_SLASHES)]);
    settings(true);
}

/** Para el panel: cada hueco con su nombre, la foto actual y si es propia. */
function web_photos_info(): array
{
    $own = settings()['photos'];
    $o = [];
    foreach (tema_fotos() as $k => $info) {
        $label = $info['label'] ?? $k;
        $url = photo($k, 600);
        if (!preg_match('#^https?://#', $url)) $url = base_url() . '/' . $url;
        $o[] = ['key' => $k, 'label' => $label, 'url' => $url, 'custom' => !empty($own[$k])];
    }
    return $o;
}

/* ---------- Menús (menú del día, degustación, infantil…) y carta principal ---------- */

/*
 * Cada menú tiene un precio cerrado y varios apartados (primero, segundo, postre, café…) con los platos
 * a elegir. Un plato puede llevar suplemento («+5 €»). Cada menú se activa y desactiva por separado desde la app,
 * y la carta principal también (setting carta_on).
 */
function menu_norm(array $m): array
{
    return [
        'id'      => preg_replace('/[^a-z0-9]/', '', (string)($m['id'] ?? '')) ?: substr(sha1(json_encode($m)), 0, 8),
        'on'      => (bool)($m['on'] ?? false),
        'title'   => (string)($m['title'] ?? '') ?: 'Menú del día',
        'price'   => (float)($m['price'] ?? 0),
        'days'    => array_values(array_map('intval', (array)($m['days'] ?? [1, 2, 3, 4, 5]))),
        'note'    => (string)($m['note'] ?? ''),
        'courses' => array_values(array_map(fn($c) => [
            'name'   => (string)($c['name'] ?? ''),
            'dishes' => array_values(array_map(fn($d) => [
                'name'      => (string)($d['name'] ?? ''),
                'desc'      => (string)($d['desc'] ?? ''),
                'extra'     => (float)($d['extra'] ?? 0),
                'allergens' => array_values(array_intersect(array_keys(ALLERGENS), (array)($d['allergens'] ?? []))),
                'photo'     => (string)($d['photo'] ?? ''),
                'photo_url' => ($d['photo'] ?? '') === '' ? '' : base_url() . '/' . $d['photo'],
            ], (array)($c['dishes'] ?? []))),
        ], (array)($m['courses'] ?? []))),
    ];
}

/** Todos los menús, en orden (para la app). */
function menus_all(): array
{
    $s = settings();
    $list = (array)($s['menus'] ?? []);
    // Antes solo había un menú del día (setting daily_menu)
    if (!$list && !empty($s['daily_menu'])) $list = [$s['daily_menu'] + ['id' => 'dia']];
    return array_values(array_map('menu_norm', $list));
}

function menus_store(array $list): void
{
    q("DELETE FROM settings WHERE k = 'menus'");
    q("INSERT INTO settings (k, v) VALUES ('menus', ?)", [json_encode(array_values($list), JSON_UNESCAPED_UNICODE)]);
    settings(true);
}

function menu_find(array $list, string $id): int
{
    foreach ($list as $i => $m) if ($m['id'] === $id) return $i;
    throw new ValidationError('Ese menú ya no existe');
}

/** Crea (sin id) o guarda un menú. Devuelve el menú guardado. */
function menu_save(array $in): array
{
    $money = function ($v, string $what): float {
        $v = round((float)str_replace(',', '.', (string)$v), 2);
        if ($v < 0 || $v > 9999) throw new ValidationError('Precio no válido: ' . $what);
        return $v;
    };
    $txt = fn($v, int $max) => mb_substr(trim((string)$v), 0, $max);
    $list = menus_all();
    $id = preg_replace('/[^a-z0-9]/', '', (string)($in['id'] ?? ''));
    $m = [
        'id'    => $id ?: bin2hex(random_bytes(4)),
        'on'    => filter_var($in['on'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'title' => $txt($in['title'] ?? '', 80) ?: 'Menú del día',
        'price' => $money($in['price'] ?? 0, 'menú'),
        'days'  => array_values(array_unique(array_filter(array_map('intval', (array)($in['days'] ?? [])), fn($d) => $d >= 1 && $d <= 7))),
        'note'  => $txt($in['note'] ?? '', 300),
        'courses' => [],
    ];
    sort($m['days']);
    foreach (array_slice((array)($in['courses'] ?? []), 0, 12) as $c) {
        $name = $txt($c['name'] ?? '', 60);
        $dishes = [];
        foreach (array_slice((array)($c['dishes'] ?? []), 0, 30) as $d) {
            $dn = $txt($d['name'] ?? '', 120);
            if ($dn === '') continue;
            $photo = (string)($d['photo'] ?? '');
            if (!preg_match('#^uploads/menu-[a-z0-9-]+\.jpg$#', $photo) || !is_file(dirname(__DIR__) . '/' . $photo)) $photo = '';
            $al = $d['allergens'] ?? [];
            if (is_string($al)) $al = $al === '' ? [] : explode(',', $al);
            $dishes[] = [
                'name' => $dn, 'desc' => $txt($d['desc'] ?? '', 200), 'extra' => $money($d['extra'] ?? 0, $dn),
                'allergens' => array_values(array_intersect(array_keys(ALLERGENS), (array)$al)), 'photo' => $photo,
            ];
        }
        if ($name === '' && !$dishes) continue;
        if ($name === '') throw new ValidationError('Ponle nombre a cada apartado del menú (Primero, Segundo…)');
        $m['courses'][] = ['name' => $name, 'dishes' => $dishes];
    }
    if ($m['on']) {
        if ($m['price'] <= 0) throw new ValidationError('Pon el precio del menú para activarlo');
        if (!array_filter($m['courses'], fn($c) => $c['dishes'])) throw new ValidationError('Añade al menos un plato para activar el menú');
        if (!$m['days']) throw new ValidationError('Elige qué días se sirve el menú');
    }
    if ($id) {
        $i = menu_find($list, $id);
        $old = $list[$i];
        $list[$i] = $m;
    } else {
        if (count($list) >= 20) throw new ValidationError('Como mucho 20 menús');
        $list[] = $m;
    }
    menus_store($list);
    if (!empty($old)) menu_photos_cleanup(menu_photos($old), menu_photos($m));
    return menu_norm($m);
}

/** Fotos que usa un menú. */
function menu_photos(array $m): array
{
    $o = [];
    foreach ($m['courses'] as $c) foreach ($c['dishes'] as $d) if (!empty($d['photo'])) $o[] = $d['photo'];
    return $o;
}

/** Borra las fotos que ya no usa ningún menú. */
function menu_photos_cleanup(array $before, array $after = []): void
{
    $used = [];
    foreach (menus_all() as $mm) foreach (menu_photos($mm) as $f) $used[$f] = true;
    foreach (array_diff($before, $after) as $f) if (empty($used[$f])) remove_upload($f);
}

/** Sube la foto de un plato de un menú; se guarda en el menú al pulsar «Guardar». Devuelve la ruta y la URL. */
function menu_photo_upload(?array $file): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new ValidationError('No ha llegado ninguna foto');
    $path = store_image($file, 'menu', 1200);
    return ['photo' => $path, 'photo_url' => base_url() . '/' . $path];
}

/** ¿Sale el botón «Ver menús» en la portada? */
function menus_button(): bool
{
    return (bool)(settings()['menus_button'] ?? true);
}

function menus_button_toggle(bool $on): bool
{
    q("DELETE FROM settings WHERE k = 'menus_button'");
    q("INSERT INTO settings (k, v) VALUES ('menus_button', ?)", [json_encode($on)]);
    settings(true);
    return $on;
}

/** Solo activar o desactivar (el interruptor de la app). */
function menu_toggle(string $id, bool $on): array
{
    $list = menus_all();
    return menu_save(['on' => $on] + $list[menu_find($list, $id)]);
}

function menu_delete(string $id): void
{
    $list = menus_all();
    $old = array_splice($list, menu_find($list, $id), 1)[0];
    menus_store($list);
    menu_photos_cleanup(menu_photos($old));
}

function menu_move(string $id, int $dir): void
{
    $list = menus_all();
    $i = menu_find($list, $id);
    $j = $i + ($dir < 0 ? -1 : 1);
    if (!isset($list[$j])) return;
    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
    menus_store($list);
}

/** ¿Sale la carta principal en la web? */
function carta_on(): bool
{
    return (bool)(settings()['carta_on'] ?? true);
}

function carta_toggle(bool $on): bool
{
    q("DELETE FROM settings WHERE k = 'carta_on'");
    q("INSERT INTO settings (k, v) VALUES ('carta_on', ?)", [json_encode($on)]);
    settings(true);
    return $on;
}

/**
 * Los menús activos para la web, traducidos. Cada uno lleva además 'today' (¿se sirve hoy?),
 * 'every_day' (todos los días) y 'days_text' («de lunes a viernes»).
 */
function menus_public(): array
{
    $out = [];
    $closedToday = in_array(today(), settings()['closed_dates'], true);
    foreach (menus_all() as $m) {
        if (!$m['on']) continue;
        $m['courses'] = array_values(array_filter($m['courses'], fn($c) => $c['dishes']));
        if (!$m['courses']) continue;
        $m['title'] = tr($m['title']);
        $m['note'] = tr($m['note']);
        foreach ($m['courses'] as &$c) {
            $c['name'] = tr($c['name']);
            foreach ($c['dishes'] as &$d) { $d['name'] = tr($d['name']); $d['desc'] = tr($d['desc']); unset($d['photo']); $d['photo'] = $d['photo_url']; unset($d['photo_url']); }
            unset($d);
        }
        unset($c);
        $m['today'] = in_array((int)date('N'), $m['days'], true) && !$closedToday;
        $m['every_day'] = count($m['days']) === 7;
        $m['days_text'] = days_text($m['days']);
        $out[] = $m;
    }
    return $out;
}

/** «de lunes a viernes», «todos los días», «martes, jueves y sábado». */
function days_text(array $days): string
{
    sort($days);
    if (count($days) === 7) return t('todos los días');
    if (!$days) return '';
    $consec = count($days) >= 3 && $days === range($days[0], end($days));
    if ($consec) return t('de %s a %s', day_name($days[0]), day_name(end($days)));
    $names = array_map('day_name', $days);
    $last = array_pop($names);
    return $names ? implode(', ', $names) . ' ' . t('y') . ' ' . $last : $last;
}

/** 14,50 € → «14,50»; 15 → «15». */
function euros(float $p): string
{
    return fmod($p, 1.0) == 0.0 ? number_format($p, 0, ',', '.') : number_format($p, 2, ',', '.');
}
