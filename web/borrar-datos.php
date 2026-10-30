<?php
declare(strict_types=1);
/*
 * Borrar todos los datos de un cliente. Sin enlace: el formulario para pedirlo. Con enlace (?t=…): lo que se va a
 * borrar y el botón para confirmarlo. Abrir el enlace no borra nada (WhatsApp y el correo abren los enlaces solos
 * para la vista previa): solo se borra al pulsar el botón. El diseño está en tema/borrar.php.
 */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$token = (string)($_POST['t'] ?? $_GET['t'] ?? '');
$hecho = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    same_origin_only();
    $hecho = erase_confirm($token);
    if ($hecho === null) $token = 'x'; // enlace ya usado o caducado
}
$req = $hecho === null && $token !== '' ? erase_by_token($token) : null;

tema_render('borrar', [
    'estado' => $hecho !== null ? 'hecho' : ($req ? 'confirmar' : ($token !== '' ? 'invalido' : 'pedir')),   // pedir, confirmar, hecho, invalido
    'contacto' => $req['contact'] ?? '',            // email o teléfono (estado confirmar)
    'resumen' => $req ? erase_summary($req) : null, // accounts, reservations, future (estado confirmar)
    'hecho' => $hecho,                              // accounts, reservations, cancelled (estado hecho)
    'borrar_boton' => function (string $texto = '', string $class = '') use ($token): string {
        return '<form method="post" action="borrar-datos.php" class="r-erase-confirm"><input type="hidden" name="t" value="' . h($token) . '">'
            . '<button type="submit"' . ($class ? ' class="' . h($class) . '"' : '') . '>' . h($texto ?: t('Borrar mis datos')) . '</button></form>';
    },
]);
