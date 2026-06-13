<?php
function alche_render_header(string $title, string $pageCss, string $bodyClass = '', array $extraCss = [], array $extraJs = []): void
{
    $links = array_merge(['css/app.css', $pageCss], $extraCss);
    ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<?php foreach ($links as $href): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" />
<?php endforeach; ?>
  </head>
  <body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
    <header class="sticky top-0 z-50 border-b border-white/10 bg-slate-950/92 backdrop-blur-md">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 md:px-8">
        <a href="index.php" class="font-display text-xl font-black uppercase tracking-tight text-white">International Fish Market</a>
        <nav class="flex flex-wrap items-center gap-3 font-mono text-[10px] uppercase tracking-[0.24em] text-white/70">
          <a href="index.php" class="hover:text-white">Home</a>
          <a href="shop.php" class="hover:text-white">Shop</a>
          <a href="about.php" class="hover:text-white">About</a>
          <a href="recipes.php" class="hover:text-white">Recipes</a>
          <a href="wallet.php" class="hover:text-white">Wallet</a>
          <a href="profile.php" class="hover:text-white">Profile</a>
          <a href="login.php" class="rounded-full border border-cyan-400/30 px-3 py-1 text-cyan-300 hover:border-cyan-300 hover:text-white">Login</a>
        </nav>
      </div>
    </header>
    <main>
<?php
    foreach ($extraJs as $src) {
        $GLOBALS['alche_extra_js'][] = $src;
    }
}

function alche_render_footer(): void
{
    $scripts = $GLOBALS['alche_extra_js'] ?? [];
    ?>
    </main>
    <footer class="border-t border-slate-200 bg-white">
      <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-[11px] text-slate-500 md:flex-row md:items-center md:justify-between md:px-8">
        <p>International Fish Market traditional PHP edition.</p>
        <p class="font-mono uppercase tracking-[0.2em]">Readable pages, linked CSS, lighter JS</p>
      </div>
    </footer>
<?php foreach ($scripts as $src): ?>
    <script src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
  </body>
</html>
<?php
}

