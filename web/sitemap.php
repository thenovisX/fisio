<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
$b = base_url();
$hoy = date('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= htmlspecialchars($b . '/') ?></loc><lastmod><?= $hoy ?></lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>
<?php foreach (['carta' => ['weekly', '0.9'], 'menus' => ['daily', '0.9'], 'reservar' => ['monthly', '0.8']] as $pagina => [$freq, $prio]): if (!is_file(__DIR__ . '/tema/' . $pagina . '.php') || ($pagina === 'menus' && !menus_public())) continue; ?>
  <url><loc><?= htmlspecialchars($b . '/' . $pagina . '.php') ?></loc><lastmod><?= $hoy ?></lastmod><changefreq><?= $freq ?></changefreq><priority><?= $prio ?></priority></url>
<?php endforeach; ?>
  <url><loc><?= htmlspecialchars($b . '/legal.php') ?></loc><changefreq>yearly</changefreq><priority>0.2</priority></url>
</urlset>
