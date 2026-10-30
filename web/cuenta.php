<?php
declare(strict_types=1);
/* Cuenta del cliente: entrar, registrarse y ver sus reservas. El diseño está en tema/cuenta.php. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }

// Enlace de activación del email (doble opt-in)
if (isset($_GET['activar'], $_GET['c'])) {
    $ok = customer_activate((int)$_GET['c'], (string)$_GET['activar']);
    header('Location: cuenta.php?' . ($ok ? 'activada=1' : 'enlace=caducado'));
    exit;
}
header('Cache-Control: no-store');
$c = current_customer();
tema_render('cuenta', [
    // null si no ha entrado; si no, ['upcoming' => [...], 'past' => [...]] con when, people, status, notes, can_cancel, id
    'reservas' => $c ? customer_reservations($c) : null,
    'activada' => isset($_GET['activada']),
    'enlace_caducado' => isset($_GET['enlace']),
    'registro' => isset($_GET['registro']),
]);
