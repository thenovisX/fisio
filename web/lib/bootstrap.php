<?php
declare(strict_types=1);

require __DIR__ . '/i18n.php';
require __DIR__ . '/core.php';
require __DIR__ . '/cifrado.php';
require __DIR__ . '/reservas.php';
require __DIR__ . '/whatsapp.php';
require __DIR__ . '/carta.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/clientes.php';
require __DIR__ . '/recuperar.php';
require __DIR__ . '/borrado.php';
require __DIR__ . '/traductor.php';
// Configuración del tema (solo constantes: nombre, colores, huecos de foto) y fotos
require dirname(__DIR__) . '/tema/tema.php';
require __DIR__ . '/fotos.php';

date_default_timezone_set(cfg()['timezone'] ?? 'Europe/Madrid');
ini_set('display_errors', !empty(cfg()['debug']) ? '1' : '0');
// Las trazas de los errores van al registro del servidor sin los argumentos de las funciones
// (si no, una contraseña o un token podrían acabar escritos en el log)
ini_set('zend.exception_ignore_args', '1');
