<?php
declare(strict_types=1);

/*
 * Cifrado de los datos personales guardados (nombre, teléfono, email y notas).
 * Usa libsodium (XSalsa20-Poly1305), que viene con PHP. La clave sale del «secret» de config.php,
 * así que una copia de la base de datos sin ese archivo no sirve para leer los datos.
 * Para buscar por email sin descifrar se guarda además una huella (HMAC) del email.
 */

const ENC_PREFIX = 'enc1:';

function crypto_ready(): bool
{
    return function_exists('sodium_crypto_secretbox') && (string)(cfg()['secret'] ?? '') !== '';
}

function crypto_key(): string
{
    static $k = null;
    if ($k === null) {
        $k = sodium_crypto_generichash('datos-personales|' . cfg()['secret'], '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
    return $k;
}

/** Cifra un texto. Si el servidor no tuviera libsodium, lo guarda tal cual. */
function enc(string $v): string
{
    if ($v === '' || str_starts_with($v, ENC_PREFIX) || !crypto_ready()) return $v;
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return ENC_PREFIX . base64_encode($nonce . sodium_crypto_secretbox($v, $nonce, crypto_key()));
}

/** Descifra un texto. Los datos antiguos sin cifrar se devuelven igual. */
function dec($v): string
{
    $v = (string)$v;
    if (!str_starts_with($v, ENC_PREFIX)) return $v;
    if (!crypto_ready()) return '';
    $raw = base64_decode(substr($v, strlen(ENC_PREFIX)), true);
    $nb = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
    if ($raw === false || strlen($raw) <= $nb + SODIUM_CRYPTO_SECRETBOX_MACBYTES) return '';
    $plain = sodium_crypto_secretbox_open(substr($raw, $nb), substr($raw, 0, $nb), crypto_key());
    return $plain === false ? '' : $plain;
}

/** Huella de un email para poder buscarlo sin guardarlo legible. */
function email_idx(string $email): string
{
    $email = mb_strtolower(trim($email));
    return $email === '' ? '' : hash_hmac('sha256', $email, 'email-idx|' . (cfg()['secret'] ?? ''));
}
