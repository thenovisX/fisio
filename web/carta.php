<?php
declare(strict_types=1);
/* Página de la carta (opcional). Si el tema tiene tema/carta.php se pinta; si no, se va a la carta de la portada. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }
if (!tema_tiene('carta')) { header('Location: ./#carta', true, 302); exit; }

tema_render('carta', [
    'carta' => carta_publica(),   // igual que en la portada
    'menus' => menus_public(),   // menús activos (del día, degustación…) traducidos; [] si no hay
    'carta_on' => carta_on(),    // ¿el restaurante quiere mostrar la carta principal?
    'menus_button' => menus_button(),   // ¿quiere el botón «Ver menús» en la portada?
]);
