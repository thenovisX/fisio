<?php
declare(strict_types=1);
/* Página de los menús (opcional). Si el tema tiene tema/menus.php se pinta; si no, se va a los menús de la portada. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }
$menus = menus_public();
if (!$menus || !tema_tiene('menus')) { header('Location: ./' . ($menus ? '#menus' : ''), true, 302); exit; }

tema_render('menus', [
    'menus' => $menus,           // igual que en la portada
    'carta' => carta_publica(),  // por si el tema quiere los nombres de los alérgenos ($carta['allergens'])
    'carta_on' => carta_on(),
]);
