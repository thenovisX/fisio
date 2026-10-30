<?php
declare(strict_types=1);
/* Información legal. Los textos salen de lib/legal.php; el diseño, de tema/legal.php. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
require __DIR__ . '/lib/legal.php';
if (!installed()) { header('Location: install.php'); exit; }

tema_render('legal', ['secciones' => legal_secciones()]);
