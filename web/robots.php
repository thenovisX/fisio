<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /panel/\nDisallow: /api/\nDisallow: /install.php\nDisallow: /cuenta.php\nDisallow: /confirmar.php\n\nSitemap: " . base_url() . "/sitemap.xml\n";
