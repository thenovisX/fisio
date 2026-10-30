<?php
declare(strict_types=1);
/* Página «Cómo reservar» (opcional). Si el tema tiene tema/reservar.php se pinta; si no, se va a la sección de reservas de la portada. */
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pagina.php';
if (!installed()) { header('Location: install.php'); exit; }
if (!tema_tiene('reservar')) { header('Location: ./#reservar', true, 302); exit; }

tema_render('reservar');   // usa politicas(), horario(), horario_hoy() y los datos de $s
