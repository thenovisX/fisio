<?php
declare(strict_types=1);

function schema_sql(bool $sqlite): array
{
    $ai = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT PRIMARY KEY AUTO_INCREMENT';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) PRIMARY KEY, v TEXT NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS users (id $ai, username VARCHAR(60) NOT NULL UNIQUE, pass_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL,
            name VARCHAR(80) NOT NULL DEFAULT '', role VARCHAR(10) NOT NULL DEFAULT 'admin', perms VARCHAR(255) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1,
            email VARCHAR(512) NOT NULL DEFAULT '', email_idx VARCHAR(64) NOT NULL DEFAULT '')$tail",
        "CREATE TABLE IF NOT EXISTS tokens (token CHAR(64) PRIMARY KEY, user_id INT NOT NULL, created_at DATETIME NOT NULL, last_used DATETIME NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS login_attempts (id $ai, ip VARCHAR(64) NOT NULL, at DATETIME NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS categories (id $ai, name VARCHAR(80) NOT NULL, position INT NOT NULL DEFAULT 0)$tail",
        "CREATE TABLE IF NOT EXISTS dishes (id $ai, category_id INT NOT NULL, name VARCHAR(120) NOT NULL, description TEXT NOT NULL,
            price DECIMAL(8,2) NOT NULL DEFAULT 0, allergens VARCHAR(255) NOT NULL DEFAULT '', photo VARCHAR(255) NOT NULL DEFAULT '',
            available TINYINT NOT NULL DEFAULT 1, featured TINYINT NOT NULL DEFAULT 0, position INT NOT NULL DEFAULT 0,
            duration INT NOT NULL DEFAULT 45)$tail",
        "CREATE TABLE IF NOT EXISTS reservations (id $ai, name VARCHAR(512) NOT NULL, phone VARCHAR(255) NOT NULL, email VARCHAR(512) NOT NULL DEFAULT '', email_idx VARCHAR(64) NOT NULL DEFAULT '',
            rdate DATE NOT NULL, rtime CHAR(5) NOT NULL, people INT NOT NULL, notes TEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            source VARCHAR(10) NOT NULL DEFAULT 'web', ip VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            customer_id INT NOT NULL DEFAULT 0, verify_token VARCHAR(255) NOT NULL DEFAULT '', seq INT NOT NULL DEFAULT 0,
            cancel_by VARCHAR(12) NOT NULL DEFAULT '', deleted TINYINT NOT NULL DEFAULT 0, lang CHAR(2) NOT NULL DEFAULT 'es',
            reminders TINYINT NOT NULL DEFAULT 0, service_id INT NOT NULL DEFAULT 0, service VARCHAR(160) NOT NULL DEFAULT '',
            duration INT NOT NULL DEFAULT 0, patient_id INT NOT NULL DEFAULT 0)$tail",
        // Fichas de pacientes del centro (todo lo personal va cifrado). No se borran a las 24 h como las reservas:
        // la historia clínica se guarda mientras haga falta para la asistencia (Ley 41/2002, mínimo 5 años).
        "CREATE TABLE IF NOT EXISTS patients (id $ai, name VARCHAR(512) NOT NULL, phone VARCHAR(255) NOT NULL DEFAULT '', email VARCHAR(512) NOT NULL DEFAULT '',
            phone_idx VARCHAR(64) NOT NULL DEFAULT '', birth VARCHAR(255) NOT NULL DEFAULT '', reason TEXT NOT NULL, history TEXT NOT NULL,
            bono_total INT NOT NULL DEFAULT 0, bono_left INT NOT NULL DEFAULT 0, tags VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL)$tail",
        "CREATE TABLE IF NOT EXISTS session_notes (id $ai, patient_id INT NOT NULL, rdate DATE NOT NULL, service VARCHAR(160) NOT NULL DEFAULT '',
            pain INT NOT NULL DEFAULT -1, body TEXT NOT NULL, author VARCHAR(80) NOT NULL DEFAULT '', created_at DATETIME NOT NULL)$tail",
        'CREATE INDEX idx_pat_phone ON patients (phone_idx)',
        'CREATE INDEX idx_note_patient ON session_notes (patient_id)',
        "CREATE TABLE IF NOT EXISTS translations (h CHAR(40) NOT NULL, lang CHAR(2) NOT NULL, src TEXT NOT NULL, txt TEXT NOT NULL, manual TINYINT NOT NULL DEFAULT 0, at DATETIME NOT NULL, PRIMARY KEY (h, lang))$tail",
        "CREATE TABLE IF NOT EXISTS customers (id $ai, name VARCHAR(512) NOT NULL, email VARCHAR(512) NOT NULL, email_idx VARCHAR(64) NOT NULL DEFAULT '', phone VARCHAR(255) NOT NULL DEFAULT '',
            pass_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_login DATETIME NULL,
            verified_at DATETIME NULL, verify_hash VARCHAR(64) NOT NULL DEFAULT '', verify_expires DATETIME NULL)$tail",
        "CREATE TABLE IF NOT EXISTS audit_log (id $ai, at DATETIME NOT NULL, customer_id INT NOT NULL DEFAULT 0, event VARCHAR(40) NOT NULL,
            detail VARCHAR(255) NOT NULL DEFAULT '', ip VARCHAR(512) NOT NULL DEFAULT '', ua VARCHAR(255) NOT NULL DEFAULT '')$tail",
        "CREATE TABLE IF NOT EXISTS erase_requests (id $ai, kind VARCHAR(10) NOT NULL, contact VARCHAR(512) NOT NULL, token_hash CHAR(64) NOT NULL DEFAULT '',
            status VARCHAR(10) NOT NULL DEFAULT 'nueva', ip VARCHAR(64) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, expires_at DATETIME NULL)$tail",
        "CREATE TABLE IF NOT EXISTS password_resets (id $ai, kind VARCHAR(10) NOT NULL, subject_id INT NOT NULL, token_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL)$tail",
        'CREATE INDEX idx_reset_hash ON password_resets (token_hash)',
        'CREATE INDEX idx_res_date ON reservations (rdate)',
        'CREATE INDEX idx_dish_cat ON dishes (category_id)',
        'CREATE INDEX idx_res_customer ON reservations (customer_id)',
        'CREATE INDEX idx_res_email ON reservations (email_idx)',
        'CREATE INDEX idx_cus_email ON customers (email_idx)',
    ];
}

/**
 * Tratamientos de ejemplo para que la web no salga vacía. Se editan desde la app.
 * Cada uno: [nombre, descripción, precio, (sin uso: alérgenos), destacado, minutos que dura].
 */
function sample_menu(): array
{
    return [
        'Fisioterapia' => [
            ['Primera valoración', 'Entrevista, exploración y plan de tratamiento por escrito. Incluye la primera sesión.', 50.0, '', 1, 60],
            ['Fisioterapia traumatológica', 'Esguinces, fracturas, prótesis y postoperatorios. Terapia manual y ejercicio.', 40.0, '', 0, 45],
            ['Dolor de espalda y cuello', 'Lumbalgia, cervicalgia y ciática. Terapia manual, punción seca si hace falta y pautas para casa.', 40.0, '', 1, 45],
            ['Suelo pélvico', 'Valoración y tratamiento en embarazo, posparto e incontinencia. En sala privada.', 50.0, '', 0, 50],
        ],
        'Deporte' => [
            ['Fisioterapia deportiva', 'Lesiones musculares y tendinopatías. Vuelta al deporte paso a paso.', 45.0, '', 1, 45],
            ['Readaptación con ejercicio', 'Sesión guiada en sala con plan de fuerza adaptado a tu lesión.', 35.0, '', 0, 60],
            ['Masaje de descarga', 'Antes o después de una competición.', 35.0, '', 0, 30],
        ],
        'Técnicas' => [
            ['Punción seca', 'Para puntos gatillo y contracturas que no ceden.', 30.0, '', 0, 30],
            ['Ondas de choque', 'Fascitis plantar, epicondilitis y calcificaciones.', 40.0, '', 0, 30],
            ['Pilates terapéutico', 'Grupos reducidos de 4 personas como máximo. Precio por sesión.', 15.0, '', 0, 55],
        ],
    ];
}
