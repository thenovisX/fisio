<?php
declare(strict_types=1);

/*
 * Fichas de pacientes del centro: datos de contacto, motivo de consulta, antecedentes, bono de sesiones
 * y notas de cada sesión (con la escala de dolor EVA de 0 a 10). Todo lo personal va cifrado.
 * Las citas se enlazan solas con la ficha por teléfono o email; desde el panel se puede crear la ficha
 * a partir de una cita con un toque.
 */

/** Huella del teléfono (últimas 9 cifras) para encontrar la ficha sin guardarlo legible. */
function phone_idx(string $phone): string
{
    $n = substr(preg_replace('/\D/', '', $phone), -9);
    return strlen($n) < 6 ? '' : hash_hmac('sha256', $n, 'phone-idx|' . (cfg()['secret'] ?? ''));
}

/** Ficha que coincide con un teléfono (o, si no, con un email). 0 si no hay. */
function patient_match(string $phone, string $email = ''): int
{
    $p = phone_idx($phone);
    if ($p !== '') {
        $id = (int)(q('SELECT id FROM patients WHERE phone_idx = ? ORDER BY id LIMIT 1', [$p])->fetchColumn() ?: 0);
        if ($id) return $id;
    }
    if (trim($email) === '') return 0;
    $want = mb_strtolower(trim($email));
    foreach (q("SELECT id, email FROM patients WHERE email <> ''")->fetchAll() as $r) {
        if (mb_strtolower(dec($r['email'])) === $want) return (int)$r['id'];
    }
    return 0;
}

function patient_out(array $r, array $stats = []): array
{
    return [
        'id' => (int)$r['id'], 'name' => dec($r['name']), 'phone' => dec($r['phone']), 'email' => dec($r['email']),
        'birth' => dec($r['birth']), 'reason' => dec($r['reason']), 'history' => dec($r['history']),
        'bono_total' => (int)$r['bono_total'], 'bono_left' => (int)$r['bono_left'],
        'tags' => $r['tags'] === '' ? [] : explode(',', $r['tags']),
        'created_at' => $r['created_at'], 'updated_at' => $r['updated_at'],
    ] + $stats;
}

/** Todas las fichas con la última y la próxima cita y cuántas sesiones lleva. */
function patients_list(): array
{
    $stats = [];
    foreach (q("SELECT patient_id, rdate, rtime, status FROM reservations WHERE patient_id > 0 AND deleted = 0 ORDER BY rdate, rtime")->fetchAll() as $r) {
        $st = &$stats[(int)$r['patient_id']];
        $st ??= ['visits' => 0, 'last' => '', 'next' => ''];
        $when = $r['rdate'] . ' ' . $r['rtime'];
        if (!in_array($r['status'], RES_ACTIVE, true)) { unset($st); continue; }
        if ($when < date('Y-m-d H:i')) { $st['visits']++; $st['last'] = $when; }
        elseif ($st['next'] === '') $st['next'] = $when;
        unset($st);
    }
    $notes = [];
    foreach (q('SELECT patient_id, COUNT(*) AS n, MAX(rdate) AS last FROM session_notes GROUP BY patient_id')->fetchAll() as $n) {
        $notes[(int)$n['patient_id']] = ['notes' => (int)$n['n'], 'last_note' => $n['last']];
    }
    $out = [];
    foreach (q('SELECT * FROM patients ORDER BY updated_at DESC')->fetchAll() as $r) {
        $id = (int)$r['id'];
        $out[] = patient_out($r, ($stats[$id] ?? ['visits' => 0, 'last' => '', 'next' => '']) + ($notes[$id] ?? ['notes' => 0, 'last_note' => '']));
    }
    return $out;
}

function patient_get(int $id): array
{
    $r = q('SELECT * FROM patients WHERE id = ?', [$id])->fetch();
    if (!$r) throw new ValidationError('La ficha no existe');
    $notes = array_map(fn($n) => [
        'id' => (int)$n['id'], 'date' => $n['rdate'], 'service' => $n['service'], 'pain' => (int)$n['pain'],
        'body' => dec($n['body']), 'author' => $n['author'], 'created_at' => $n['created_at'],
    ], q('SELECT * FROM session_notes WHERE patient_id = ? ORDER BY rdate DESC, id DESC', [$id])->fetchAll());
    $citas = array_map('res_out', q('SELECT * FROM reservations WHERE patient_id = ? AND deleted = 0 ORDER BY rdate DESC, rtime DESC LIMIT 60', [$id])->fetchAll());
    return patient_out($r) + ['notes' => $notes, 'appointments' => $citas];
}

function patient_save(array $in): array
{
    $name = str_in($in, 'name', 80, true, 'nombre');
    $phone = str_in($in, 'phone', 30);
    $email = str_in($in, 'email', 120);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ValidationError('El email no parece correcto');
    $birth = str_in($in, 'birth', 10);
    if ($birth !== '' && !is_date($birth)) throw new ValidationError('La fecha de nacimiento no es válida');
    $reason = mb_substr(trim((string)($in['reason'] ?? '')), 0, 2000);
    $history = mb_substr(trim((string)($in['history'] ?? '')), 0, 4000);
    $tags = array_slice(array_values(array_unique(array_filter(array_map(
        fn($t) => mb_substr(trim(str_replace(',', ' ', (string)$t)), 0, 24), (array)($in['tags'] ?? [])
    )))), 0, 8);
    $vals = [enc($name), enc($phone), enc($email), phone_idx($phone), enc($birth), enc($reason), enc($history), implode(',', $tags), now()];
    $id = (int)($in['id'] ?? 0);
    if ($id) {
        patient_get($id);
        q('UPDATE patients SET name=?, phone=?, email=?, phone_idx=?, birth=?, reason=?, history=?, tags=?, updated_at=? WHERE id=?', [...$vals, $id]);
    } else {
        q('INSERT INTO patients (name, phone, email, phone_idx, birth, reason, history, tags, updated_at, created_at, bono_total, bono_left)
           VALUES (?,?,?,?,?,?,?,?,?,?,0,0)', [...$vals, now()]);
        $id = (int)db()->lastInsertId();
        audit('ficha_creada', 0, "Ficha de paciente #$id");
    }
    // Citas con el mismo teléfono que aún no tienen ficha: se enlazan
    $pi = phone_idx($phone);
    if ($pi !== '') {
        foreach (q("SELECT id, phone FROM reservations WHERE patient_id = 0 AND deleted = 0 AND phone <> ''")->fetchAll() as $r) {
            if (phone_idx(dec($r['phone'])) === $pi) q('UPDATE reservations SET patient_id = ? WHERE id = ?', [$id, $r['id']]);
        }
    }
    if (isset($in['reservation_id'])) q('UPDATE reservations SET patient_id = ? WHERE id = ?', [$id, (int)$in['reservation_id']]);
    return patient_get($id);
}

/** Borra la ficha con sus notas. Las citas se quedan, sin enlace. */
function patient_delete(int $id): void
{
    patient_get($id);
    q('DELETE FROM session_notes WHERE patient_id = ?', [$id]);
    q('UPDATE reservations SET patient_id = 0 WHERE patient_id = ?', [$id]);
    q('DELETE FROM patients WHERE id = ?', [$id]);
    audit('ficha_borrada', 0, "Ficha de paciente #$id");
}

/**
 * Nota de una sesión. Con 'use_bono' descuenta una sesión del bono del paciente.
 * 'pain' es la escala EVA (0 = sin dolor, 10 = el peor dolor); -1 = sin anotar.
 */
function note_save(array $in, array $user): array
{
    $pid = (int)($in['patient_id'] ?? 0);
    patient_get($pid);
    $date = str_in($in, 'date', 10) ?: today();
    if (!is_date($date)) throw new ValidationError('Fecha no válida');
    $body = mb_substr(trim((string)($in['body'] ?? '')), 0, 4000);
    $pain = isset($in['pain']) && $in['pain'] !== '' && $in['pain'] !== null ? max(-1, min(10, (int)$in['pain'])) : -1;
    $service = mb_substr(trim((string)($in['service'] ?? '')), 0, 160);
    if ($body === '' && $pain < 0) throw new ValidationError('Escribe algo en la nota');
    $id = (int)($in['id'] ?? 0);
    $author = mb_substr((string)($user['name'] ?: $user['username']), 0, 80);
    if ($id) {
        q('UPDATE session_notes SET rdate=?, service=?, pain=?, body=? WHERE id=? AND patient_id=?', [$date, $service, $pain, enc($body), $id, $pid]);
    } else {
        q('INSERT INTO session_notes (patient_id, rdate, service, pain, body, author, created_at) VALUES (?,?,?,?,?,?,?)',
            [$pid, $date, $service, $pain, enc($body), $author, now()]);
        if (filter_var($in['use_bono'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            q('UPDATE patients SET bono_left = bono_left - 1 WHERE id = ? AND bono_left > 0', [$pid]);
        }
    }
    q('UPDATE patients SET updated_at = ? WHERE id = ?', [now(), $pid]);
    return patient_get($pid);
}

function note_delete(int $id): array
{
    $pid = (int)(q('SELECT patient_id FROM session_notes WHERE id = ?', [$id])->fetchColumn() ?: 0);
    if (!$pid) throw new ValidationError('La nota no existe');
    q('DELETE FROM session_notes WHERE id = ?', [$id]);
    return patient_get($pid);
}

/** Bono: 'add' sesiones nuevas (vende un bono) o fija 'left' a mano. */
function bono_set(int $id, array $in): array
{
    patient_get($id);
    if (isset($in['add'])) {
        $n = max(1, min(100, (int)$in['add']));
        q('UPDATE patients SET bono_total = ?, bono_left = bono_left + ?, updated_at = ? WHERE id = ?', [$n, $n, now(), $id]);
    } else {
        $left = max(0, min(100, (int)($in['left'] ?? 0)));
        q('UPDATE patients SET bono_left = ?, updated_at = ? WHERE id = ?', [$left, now(), $id]);
    }
    return patient_get($id);
}
