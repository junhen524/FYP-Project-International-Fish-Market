
<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>
      International Fish Market Admin Login
    </title>
    <link rel="stylesheet" href="css/adminlogin.css?v=<?= urlencode($__ifmAssetVersion('css/adminlogin.css')) ?>" />
  </head>
  <body style="margin: 0; overflow-x: hidden;">
    <div id="root">
      <div class="relative min-h-screen bg-bg-dark text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip" id="alche-studio-replica-root">
        <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
          <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
            <div class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
              <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">
                INTERNATIONAL FISH MARKET
              </span>
              <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse">
              </span>
            </div>
            <nav class="hidden md:flex items-center space-x-6" id="desktop-nav-menu">
              <a href="<?= $__ifmBasePath ?>shop.php" class="flex items-center space-x-1.5 font-display text-xs tracking-widest uppercase transition-all duration-200 text-slate-600 hover:text-slate-950 active:scale-95 no-underline">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-bag text-brand-blue" aria-hidden="true">
                  <path d="M16 10a4 4 0 0 1-8 0">
                  </path>
                  <path d="M3.103 6.034h17.794">
                  </path>
                  <path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z">
                  </path>
                </svg>
                <span class="font-bold">
                  Shop
                </span>
              </a>
              <a href="<?= $__ifmBasePath ?>recipes.php" class="flex items-center space-x-1.5 font-display text-xs tracking-widest uppercase transition-all duration-200 text-slate-600 hover:text-slate-950 active:scale-95 no-underline">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chef-hat text-amber-500" aria-hidden="true">
                  <path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z">
                  </path>
                  <path d="M6 17h12">
                  </path>
                </svg>
                <span class="font-bold">
                  Recipes
                </span>
              </a>
              <a href="<?= $__ifmBasePath ?>about.php" class="flex items-center space-x-1.5 font-display text-xs tracking-widest uppercase transition-all duration-200 text-slate-600 hover:text-slate-950 active:scale-95 no-underline">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info text-emerald-500" aria-hidden="true">
                  <circle cx="12" cy="12" r="10">
                  </circle>
                  <path d="M12 16v-4">
                  </path>
                  <path d="M12 8h.01">
                  </path>
                </svg>
                <span class="font-bold">
                  About
                </span>
              </a>
              <a href="<?= $__ifmBasePath ?>cart.php" class="flex items-center space-x-1.5 border border-slate-200 hover:bg-slate-50/85 px-2.5 py-1 rounded-lg transition-all duration-200 active:scale-95 relative no-underline" title="Sourcing Cart">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shopping-cart text-brand-teal" aria-hidden="true">
                  <circle cx="8" cy="21" r="1">
                  </circle>
                  <circle cx="19" cy="21" r="1">
                  </circle>
                  <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12">
                  </path>
                </svg>
                <span class="font-display text-[9px] uppercase font-semibold text-slate-600 tracking-wider">
                  Cart
                </span>
              </a>
              <a href="<?= $__ifmBasePath ?>login.php" class="flex items-center space-x-1 border border-slate-200 hover:bg-slate-50/85 px-2.5 py-1 rounded-lg transition-all duration-200 active:scale-95 no-underline">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user text-slate-400" aria-hidden="true">
                  <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2">
                  </path>
                  <circle cx="12" cy="7" r="4">
                  </circle>
                </svg>
                <span class="font-display text-[9px] uppercase font-semibold text-slate-600 tracking-wider">
                  Login
                </span>
              </a>
            </nav>
            <div class="flex items-center space-x-3 md:hidden">
              <button class="text-slate-700 hover:text-slate-950 focus:outline-none p-1.5 hover:bg-slate-100 rounded-lg" aria-label="Toggle Menu" id="mobile-menu-hamburger">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu" aria-hidden="true">
                  <path d="M4 5h16">
                  </path>
                  <path d="M4 12h16">
                  </path>
                  <path d="M4 19h16">
                  </path>
                </svg>
              </button>
            </div>
          </div>
        </header>
        <div id="subpage-viewport">
          <div class="min-h-screen bg-slate-100/60 pt-28 pb-16 px-4 md:px-8">
            <div class="max-w-md mx-auto">
              <button class="mb-5 flex items-center space-x-2 text-xs font-mono text-slate-500 hover:text-slate-800 transition-colors uppercase tracking-wider group">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left group-hover:-translate-x-1 transition-transform" aria-hidden="true">
                  <path d="m12 19-7-7 7-7">
                  </path>
                  <path d="M19 12H5">
                  </path>
                </svg>
                <span>
                  Return to Market
                </span>
              </button>
              <div class="bg-white border border-slate-200 rounded-[28px] shadow-xl overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-check" aria-hidden="true">
                      <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                      </path>
                      <path d="m9 12 2 2 4-4">
                      </path>
                    </svg>
                  </div>
                  <div>
                    <h1 class="font-display font-black text-lg text-slate-900 uppercase tracking-tight">
                      Admin Login
                    </h1>
                    <p class="text-[10px] font-mono uppercase tracking-widest text-slate-400">
                      Hidden operations entry
                    </p>
                  </div>
                </div>
                <form method="post" action="<?= $__ifmBasePath ?>admin.php" class="p-6 space-y-4">
                  <div class="rounded-2xl bg-sky-50 border border-sky-100 px-4 py-3 text-[11px] text-sky-700 leading-relaxed">
                    This page is intentionally not linked in the main navigation. Later you can replace this fixed login with your real admin database auth.
                  </div>
                  <div class="space-y-1">
                    <label class="font-mono text-[8px] uppercase text-slate-400 font-bold block">
                      Admin Username
                    </label>
                    <input class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-xs font-mono text-slate-900 focus:outline-none" type="text" name="username" value="admin">
                  </div>
                  <div class="space-y-1">
                    <label class="font-mono text-[8px] uppercase text-slate-400 font-bold block">
                      Admin Password
                    </label>
                    <input class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-xs font-mono text-slate-900 focus:outline-none" type="password" name="password" value="">
                  </div>
                  <button type="submit" name="action" value="login" class="w-full py-3 bg-slate-900 hover:bg-slate-950 text-white font-mono text-[10px] uppercase tracking-widest font-black rounded-2xl transition-all cursor-pointer flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-lock-keyhole" aria-hidden="true">
                      <circle cx="12" cy="16" r="1">
                      </circle>
                      <rect x="3" y="10" width="18" height="12" rx="2">
                      </rect>
                      <path d="M7 10V7a5 5 0 0 1 10 0v3">
                      </path>
                    </svg>
                    <span>
                      Enter Admin Dashboard
                    </span>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
      window.__APP_ROUTE__ = "/adminlogin.php";
      window.__APP_BASE_PATH__ = <?= json_encode($__ifmBasePath) ?>;
      window.__ADMIN_PHP_SESSION__ = false;
    </script>
  </body>
</html>

