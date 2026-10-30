<?php
declare(strict_types=1);
/* Portada. La lógica está aquí; el diseño, en tema/inicio.php. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }

tema_render('inicio', [
    'carta' => carta_publica(),   // ['categories' => [...con 'dishes'], 'allergens' => [...]] con nombres y descripciones en el idioma de la visita
    'menus' => menus_public(),   // menús activos (del día, degustación…) traducidos; [] si no hay
    'carta_on' => carta_on(),    // ¿el restaurante quiere mostrar la carta principal?
    'menus_button' => menus_button(),   // ¿quiere el botón «Ver menús» en la portada?
]);
