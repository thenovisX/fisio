<?php
// install.php crea config.php automáticamente. Esto es solo de referencia.
return [
    'db_driver' => 'mysql',            // 'mysql' en Hostinger, 'sqlite' para pruebas
    'db_host'   => 'localhost',
    'db_name'   => 'u123456789_restaurante',
    'db_user'   => 'u123456789_admin',
    'db_pass'   => 'CAMBIA_ESTO',
    'db_path'   => __DIR__ . '/data/restaurante.sqlite',
    'timezone'  => 'Europe/Madrid',
    'secret'    => 'cadena-aleatoria-larga',
    'debug'     => false,
    // Seguridad (opcional)
    'base_url'  => 'https://tu-dominio.com',   // dirección fija para los enlaces de los emails (recomendado)
    'rate_limit' => 240,                       // peticiones por minuto y por IP a la API (0 = sin límite)
    'cors_origins' => [],                      // otras webs que pueden llamar a la API, p. ej. ['https://otra-web.com']
    'csp' => true,                             // Content-Security-Policy en las páginas (false la quita)
    'csp_report_only' => false,                // true: solo avisa en la consola del navegador, no bloquea
];
