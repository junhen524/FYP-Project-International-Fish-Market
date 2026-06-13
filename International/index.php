<?php
require __DIR__ . '/includes/bootstrap.php';
$__flash = flash();
$__welcomeMsg = $__flash['welcome'] ?? '';
$__fishmongerProducts = dbGetAll("SELECT p1.*, COALESCE(p1.image_url, '') as image_url FROM product p1 INNER JOIN (SELECT MIN(id) as id FROM product WHERE is_active = TRUE AND category IS NOT NULL AND category != '' AND id != 27 GROUP BY category) p2 ON p1.id = p2.id WHERE p1.is_active = TRUE ORDER BY p1.id ASC LIMIT 5");
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
      International Fish Market
    </title>
    <link rel="stylesheet" href="css/index.css?v=<?= urlencode($__ifmAssetVersion('css/index.css')) ?>" />
    <style>
      .flip-card:hover .flip-card-inner { transform: rotateY(180deg); }
    </style>
    <script src="https://unpkg.com/lenis@1.2.3/dist/lenis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
  </head>
  <body style="margin: 0px; overflow-x: hidden;">
    <div id="root">
      <div class="relative min-h-screen bg-bg-dark text-slate-800 selection:bg-brand-blue/30 selection:text-white" id="alche-studio-replica-root">
        <div class="fixed right-5 top-1/2 -translate-y-1/2 z-[100] hidden lg:flex flex-col items-center space-y-2">
          <button class="group relative flex items-center justify-center w-8 h-8 rounded-full focus:outline-none cursor-pointer" title="Scroll to Main">
            <span class="absolute right-8 opacity-0 group-hover:opacity-100 transition-all duration-300 font-mono text-[9px] uppercase tracking-widest text-slate-600 bg-white border border-slate-200 px-2.5 py-1 rounded shadow-sm pointer-events-none whitespace-nowrap">
              Main
            </span>
            <div class="w-1.5 h-1.5 rounded-full bg-slate-300 group-hover:bg-slate-500 transition-all duration-300">
            </div>
          </button>
          <button class="group relative flex items-center justify-center w-8 h-8 rounded-full focus:outline-none cursor-pointer" title="Scroll to Shop">
            <span class="absolute right-8 opacity-0 group-hover:opacity-100 transition-all duration-300 font-mono text-[9px] uppercase tracking-widest text-[#00d7e2] bg-white border border-slate-200 px-2.5 py-1 rounded shadow-sm pointer-events-none whitespace-nowrap">
              Shop
            </span>
            <div class="w-1.5 h-1.5 rounded-full bg-slate-300 group-hover:bg-slate-500 transition-all duration-300">
            </div>
          </button>
          <button class="group relative flex items-center justify-center w-8 h-8 rounded-full focus:outline-none cursor-pointer" title="Scroll to Recipes">
            <span class="absolute right-8 opacity-0 group-hover:opacity-100 transition-all duration-300 font-mono text-[9px] uppercase tracking-widest text-[#00d7e2] bg-white border border-slate-200 px-2.5 py-1 rounded shadow-sm pointer-events-none whitespace-nowrap">
              Recipes
            </span>
            <div class="w-1.5 h-1.5 rounded-full bg-slate-300 group-hover:bg-slate-500 transition-all duration-300">
            </div>
          </button>
        </div>
        <canvas class="fixed top-0 left-0 w-screen h-screen pointer-events-none z-0" id="neon-grid-stage" width="1440" height="2200">
        </canvas>
        <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
          <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
            <div class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
              <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">
                INTERNATIONAL FISH MARKET
              </span>
              <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse">
              </span>
            </div>
            <?php require __DIR__ . '/includes/nav_bar.php'; ?>
          </div>
        </header>
<?php if ($__welcomeMsg): ?>
<div style="position:fixed;top:80px;left:50%;transform:translateX(-50%);z-index:40;background:rgba(255,255,255,0.95);backdrop-filter:blur(12px);border:1px solid rgba(13,148,136,0.2);border-radius:16px;padding:12px 28px;box-shadow:0 8px 32px rgba(0,0,0,0.08);animation:welcomeFadeIn 0.5s ease-out" id="welcome-banner">
  <span style="font-size:14px;font-weight:700;color:#0f172a;letter-spacing:0.5px">👋 Welcome back, <span style="color:#0d9488"><?= e($__welcomeMsg) ?></span></span>
</div>
<script>
setTimeout(function(){
  var b = document.getElementById('welcome-banner');
  if (b) { b.style.transition = 'opacity 0.6s,transform 0.6s'; b.style.opacity = '0'; b.style.transform = 'translateX(-50%) translateY(-12px)'; setTimeout(function(){ b.remove(); }, 700); }
}, 4000);
</script>
<?php endif; ?>
        <section id="hero" class="relative min-h-screen flex flex-col justify-center items-center px-6 md:px-12 pt-20 overflow-hidden" style="scroll-margin-top: 200px;">
          <div class="absolute inset-0 w-full h-full overflow-hidden pointer-events-none select-none z-0">
            <video src="assets/backgroundindex-CAdX2Cdq.mp4" autoplay="" loop="" playsinline="" class="w-full h-full object-cover opacity-100 scale-[1.02] transition-opacity duration-700">
            </video>
            <div class="absolute inset-0 bg-slate-950/35 mix-blend-multiply">
            </div>
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/20 via-transparent to-slate-950/40">
            </div>
          </div>
          <div class="absolute top-[35%] left-[20%] -translate-x-1/2 -translate-y-1/2 w-[420px] h-[420px] rounded-full bg-brand-blue/20 blur-[130px] pointer-events-none animate-pulse duration-[6000ms] z-[1]">
          </div>
          <div class="absolute top-[45%] right-[20%] translate-x-1/2 -translate-y-1/2 w-[450px] h-[450px] rounded-full bg-brand-teal/15 blur-[140px] pointer-events-none animate-pulse duration-[8000ms] z-[1]">
          </div>
          <div class="max-w-5xl mx-auto text-center space-y-10 relative z-10 p-4">
            <div class="inline-flex items-center space-x-2 bg-slate-900/60 backdrop-blur-md border border-white/10 py-1.5 px-4 rounded-full shadow-lg" id="hero-tagline-wrap" style="opacity: 1; transform: none;">
              <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles text-brand-teal animate-pulse" aria-hidden="true">
                <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                </path>
                <path d="M20 2v4">
                </path>
                <path d="M22 4h-4">
                </path>
                <circle cx="4" cy="20" r="2">
                </circle>
              </svg>
              <span class="font-mono text-[9px] uppercase tracking-[0.25em] text-white font-semibold">
                International Fish Market
              </span>
            </div>
            <div class="space-y-6">
              <h1 class="font-display text-4xl sm:text-6xl md:text-7xl font-bold tracking-tight text-white leading-[1.08] max-w-4xl mx-auto drop-shadow-[0_4px_16px_rgba(0,0,0,0.5)]" style="opacity: 1; transform: none;">
                Pristine
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-brand-teal to-sky-300 animate-pulse">
                  Deep Ocean
                </span>
                Sourcing
              </h1>
              <p class="text-slate-100 text-sm md:text-lg font-light tracking-wide max-w-2xl mx-auto leading-relaxed drop-shadow-[0_2px_8px_rgba(0,0,0,0.6)]" style="opacity: 1;">
                International Fish Market connects wild-captured sub-zero sashimi-grade catch and live giant crabs with continuous -60Â°C flight cold-chains to prestigious private tables and master culinary chefs.
              </p>
            </div>
            <div class="flex flex-col sm:flex-row justify-center items-center gap-4 pt-4" id="hero-action-buttons" style="opacity: 1; transform: none;">
              <button id="btn-browse-fish" class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-brand-blue to-brand-teal rounded-2xl text-xs font-display uppercase tracking-widest font-semibold text-white hover:shadow-xl hover:shadow-brand-blue/35 transition-all duration-300 hover:scale-[1.03] shadow-md">
                Browse Fish
              </button>
              <button id="btn-browse-recipes" class="w-full sm:w-auto px-8 py-4 bg-white/10 hover:bg-white/20 border border-white/20 backdrop-blur-sm rounded-2xl text-xs font-display uppercase tracking-widest font-semibold text-white transition-all duration-200 shadow-sm">
                Browse Recipess
              </button>
            </div>
          </div>
          <div class="absolute bottom-8 cursor-pointer flex flex-col items-center space-y-1 text-slate-200 hover:text-white transition-colors duration-200 select-none group bg-slate-950/20 px-4 py-2 rounded-full backdrop-blur-[2px] border border-white/5 shadow-md" id="scroll-visual-indicator">
            <span class="font-mono text-[9px] tracking-[0.3em] uppercase drop-shadow-sm">
              SCROLL COORD
            </span>
            <div style="transform: translateY(0.949062px);">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right rotate-90 text-brand-teal group-hover:text-cyan-300" aria-hidden="true">
                <path d="m9 18 6-6-6-6">
                </path>
              </svg>
            </div>
          </div>
        </section>
        <section id="works" class="relative pt-24 md:pt-32 bg-bg-dark border-t border-slate-200/60" style="scroll-margin-top: 80px;">
          <div class="max-w-7xl mx-auto px-6 md:px-12 mb-16">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-8 pb-10 border-b border-slate-200/60">
              <div class="space-y-4">
                <div class="flex items-center space-x-2 text-brand-blue font-mono text-[10px] tracking-[0.3em] uppercase">
                  <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-compass animate-pulse text-brand-blue" aria-hidden="true">
                    <path d="m16.24 7.76-1.804 5.411a2 2 0 0 1-1.265 1.265L7.76 16.24l1.804-5.411a2 2 0 0 1 1.265-1.265z">
                    </path>
                    <circle cx="12" cy="12" r="10">
                    </circle>
                  </svg>
                  <span>
                    Daily Seafood Shipments
                  </span>
                </div>
                <h2 class="font-display text-4xl md:text-5xl font-bold text-slate-950 tracking-tight leading-none">
                  Browse the Fishmonger Counter
                </h2>
              </div>
          </div>
          <div id="works-orbit-container" class="w-full">
            <div class="w-full bg-gradient-to-br from-[#cee0ea] via-[#dbedf5] to-[#c2dae7] py-16 md:py-24">
              <div class="w-full flex flex-col justify-center items-center bg-transparent">
                <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 flex justify-center items-center pointer-events-none z-0">
                  <div class="w-[85vh] h-[85vh] rounded-full border border-slate-200/80 bg-gradient-to-b from-brand-blue/[0.035] to-transparent animate-spin duration-[40s] relative flex justify-center items-center">
                    <div class="absolute inset-6 rounded-full border border-dashed border-slate-200/80 flex justify-center items-center">
                      <div class="absolute inset-12 rounded-full border border-slate-200/50">
                      </div>
                    </div>
                    <div class="w-40 h-40 rounded-full bg-brand-blue/[0.04] blur-xl">
                    </div>
                  </div>
                </div>
                <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 flex flex-col justify-center items-center pointer-events-none z-0 overflow-hidden w-full h-[65vh]">
                  <div class="absolute w-[140%] h-[320px] rounded-[50%] border-t border-b border-brand-blue/25 bg-gradient-to-b from-brand-blue/[0.03] to-transparent shadow-[0_0_60px_rgba(15,108,245,0.06)] opacity-70 blur-[0.5px]" style="transform: rotate(-2deg) translateZ(-150px);">
                  </div>
                  <div class="absolute w-[155%] h-[400px] rounded-[50%] border-t border-slate-200 opacity-50" style="transform: rotate(-2deg) translateZ(-250px);">
                  </div>
                  <div class="absolute inset-0 opacity-[0.25] bg-[linear-gradient(rgba(0,0,0,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(0,0,0,0.03)_1px,transparent_1px)] bg-[size:40px_40px] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)]" style="transform: perspective(1200px) rotateX(60deg) translateZ(-100px);">
                  </div>
                </div>
                <div class="relative w-full overflow-hidden" style="height:32vh;">
                  <div class="mx-auto px-4 flex items-center justify-center gap-3 h-full" style="max-width:1400px;">
<?php foreach ($__fishmongerProducts as $__i => $__p):
$__cat = strtolower($__p['category'] ?? 'seafood');
$__isBlue = $__i < 2;
$__accent = $__isBlue ? 'blue' : 'teal';
$__img = intl_product_image($__p['image_url']);
?>
                  <a href="shop.php?highlight=<?= e($__p['slug']) ?>" class="flip-card h-full rounded-2xl overflow-hidden border border-slate-700 bg-[#0b1121] shadow-lg cursor-pointer group" style="flex:1 1 0%; perspective:1000px; text-decoration:none; display:block;">
                    <div class="flip-card-inner relative w-full h-full transition-transform duration-700" style="transform-style:preserve-3d;">
                      <!-- Front -->
                      <div class="absolute inset-0 rounded-2xl overflow-hidden flex flex-col" style="backface-visibility:hidden;">
                        <div class="flex-1 relative overflow-hidden" style="min-height:0;">
                          <div class="absolute inset-0" style="background-image:url('<?= e($__img) ?>'); background-size: cover; background-position: center center; background-repeat: no-repeat;"></div>
                        </div>
                        <div class="px-3 py-2.5 space-y-0.5" style="background:#0b1121;">
                          <span class="text-[7px] font-mono tracking-widest uppercase text-<?= $__accent ?>-400"><?= e($__cat) ?></span>
                          <h3 class="font-sans text-xs font-bold text-white leading-tight"><?= e($__p['name']) ?></h3>
                          <p class="text-[#94a3b8] text-[10px] leading-tight line-clamp-1"><?= e($__p['description'] ?: $__p['origin'] ?: 'Premium quality seafood') ?></p>
                        </div>
                      </div>
                      <!-- Back (price) -->
                      <div class="absolute inset-0 rounded-2xl overflow-hidden border border-slate-700 bg-[#050b14] flex flex-col items-center justify-center p-4 shadow-lg" style="backface-visibility:hidden;transform:rotateY(180deg);">
                        <span class="text-[8px] font-mono tracking-widest uppercase text-<?= $__accent ?>-400 mb-1"><?= e($__cat) ?></span>
                        <h3 class="font-sans text-xs font-bold text-white text-center leading-tight mb-2"><?= e($__p['name']) ?></h3>
                        <div class="text-center">
                          <?php if ((float)$__p['export_price'] > 0): ?>
                          <div class="text-xl font-bold text-<?= $__accent ?>-300">$<?= e(number_format((float)$__p['export_price'], 2)) ?></div>
                          <div class="text-[9px] text-slate-400 mt-1">Per <?= e($__p['unit'] ?? 'kg') ?> USD</div>
                          <?php else: ?>
                          <div class="text-base font-bold text-slate-400">Contact for price</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </a>
<?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <div class="absolute top-[20%] left-[-10%] w-[350px] h-[350px] rounded-full bg-brand-teal/5 blur-[100px] pointer-events-none">
          </div>
          <div class="absolute bottom-[20%] right-[-10%] w-[350px] h-[350px] rounded-full bg-brand-blue/5 blur-[100px] pointer-events-none">
          </div>
          <div id="about" class="max-w-7xl mx-auto px-6 md:px-12 relative z-10 pt-24 md:pt-36" style="scroll-margin-top: 180px;">
            <div class="max-w-3xl space-y-4 mb-16 md:mb-24" style="opacity: 0; transform: translateY(40px);">
              <div class="flex items-center space-x-2 text-brand-blue font-mono text-[10px] tracking-[0.3em] uppercase">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-milestone" aria-hidden="true">
                  <path d="M12 13v8">
                  </path>
                  <path d="M12 3v3">
                  </path>
                  <path d="M4 6a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h13a2 2 0 0 0 1.152-.365l3.424-2.317a1 1 0 0 0 0-1.635l-3.424-2.318A2 2 0 0 0 17 6z">
                  </path>
                </svg>
                <span>
                  Philosophy
                </span>
              </div>
              <h2 class="font-display text-4xl md:text-5xl font-bold text-slate-950 tracking-tight leading-none">
                Pioneering Deep Ocean Sourcing
              </h2>
              <div class="h-1 w-20 bg-gradient-to-r from-brand-blue to-brand-teal rounded">
              </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start pb-20 border-b border-slate-200/60">
              <div class="lg:col-span-7 space-y-6 text-slate-700 md:text-lg font-light leading-relaxed font-sans" style="opacity: 0; transform: translateY(30px);">
                <p>
                  International Fish Market is an elite collective of veteran deep-sea fishermen, sub-zero cold-chain scientists, and Michelin-starred culinary curators who believe that the oceanâ€™s greatest masterpieces deserve a lossless passage to gourmet appreciation.
                </p>
                <p>
                  By integrating advanced physical Ikejime nervous neutralization, quick liquid-nitrogen shock freezing, and continuous remote-monitored IoT cold telemetry chambers, we guarantee every single wild seafood slice maintains pristine cell structures. We transmute cold ocean currents into lifetime culinary memories.
                </p>
              </div>
              <div class="lg:col-span-5 space-y-4">
                <div class="p-6 rounded-2xl bg-slate-50/50 border border-slate-200 hover:border-brand-blue/20 hover:bg-slate-50 transition-all duration-300 space-y-4 shadow-sm" style="opacity: 0; transform: translateX(30px);">
                  <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-xl bg-slate-100">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-check text-brand-teal" aria-hidden="true">
                        <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                        </path>
                        <path d="m9 12 2 2 4-4">
                        </path>
                      </svg>
                    </div>
                    <h4 class="font-display text-base font-semibold text-slate-900">
                      Ultimate Cryo-Hold
                    </h4>
                  </div>
                  <p class="text-slate-600 text-xs md:text-sm font-light leading-relaxed">
                    Utilizing patented liquid nitrogen flash-freezers on-board to halt cellular oxidation within 30 seconds of harvesting.
                  </p>
                </div>
                <div class="p-6 rounded-2xl bg-slate-50/50 border border-slate-200 hover:border-brand-blue/20 hover:bg-slate-50 transition-all duration-300 space-y-4 shadow-sm" style="opacity: 0; transform: translateX(30px);">
                  <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-xl bg-slate-100">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-flame text-brand-blue" aria-hidden="true">
                        <path d="M12 3q1 4 4 6.5t3 5.5a1 1 0 0 1-14 0 5 5 0 0 1 1-3 1 1 0 0 0 5 0c0-2-1.5-3-1.5-5q0-2 2.5-4">
                        </path>
                      </svg>
                    </div>
                    <h4 class="font-display text-base font-semibold text-slate-900">
                      Direct Wharf Registry
                    </h4>
                  </div>
                  <p class="text-slate-600 text-xs md:text-sm font-light leading-relaxed">
                    Establishing exclusive buying channels with certified Toyosu, Hokkaido, and Oma master-fishers for flawless sourcing.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>
        <div id="careers" class="relative bg-gradient-to-br from-[#cee0ea] via-[#dbedf5] to-[#c2dae7] text-slate-800 w-full h-[400vh] select-none" style="scroll-margin-top: 80px;">
          <div class="absolute inset-0 noise-bg opacity-5 pointer-events-none">
          </div>
          <div class="sticky top-0 w-full h-screen flex flex-col justify-center items-center px-4 md:px-8">
            <div class="sticky-header text-center mb-1 z-20 pointer-events-none pt-4 md:pt-0 md:absolute md:top-[9%] md:left-1/2 md:-translate-x-1/2 md:mb-0"><!-- recipe-scroll-anchor -->
              <div class="space-y-1" style="opacity: 0; transform: translateY(50px);">
                <h2 class="font-display text-3xl md:text-[42px] font-bold tracking-tight text-slate-900 leading-tight">
                  Recipes
                </h2>
              </div>
            </div>
            <div class="relative max-w-7xl mx-auto aspect-[15/7] perspective-[1400px] select-none cursor-pointer rounded-[24px] mt-6 md:mt-10 -translate-y-8 md:-translate-y-12 flex gap-0" id="card-animation-container" style="width: 78%; box-shadow: rgba(11, 41, 64, 0.14) 0px 15px 45px;">
              <div class="will-change-transform cursor-pointer flex-1 h-full relative" id="card-1" onclick="window.location='recipes.php'" style="transform-origin: 50% 100%; border-radius: 20px 0px 0px 20px; transform-style: preserve-3d; z-index: 10; box-shadow: none; transform: none;">
                <div class="absolute inset-0 w-full h-full overflow-hidden bg-[#f8fafc]" style="backface-visibility: hidden; border-radius: inherit; transform: rotateY(0deg) translateZ(1px);">
                  <img alt="Gourmet Shellfish Craftsmanship" class="w-full h-full object-cover pointer-events-none select-none" referrerpolicy="no-referrer" src="assets/splitimage.im-1-CzssRoKU.png">
                </div>
                <div class="absolute inset-0 w-full h-full flex flex-col justify-center items-center text-center overflow-hidden bg-[#f8fafc]" style="backface-visibility: hidden; transform: rotateY(180deg) translateZ(1px); border-radius: inherit;">
                  <img alt="Imperial King Crab Gratin" class="absolute inset-0 w-full h-full object-cover select-none pointer-events-none" referrerpolicy="no-referrer" src="assets/recipe1-0-t-hNS_.png">
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent">
                  </div>
                  <div class="relative z-10 w-full h-full flex flex-col justify-end items-center p-5 pb-6 text-white text-center">
                    <p class="font-sans font-medium text-sm sm:text-base md:text-[17px] leading-tight tracking-tight max-w-[220px] drop-shadow-md text-slate-100">
                      Imperial King Crab Gratin
                    </p>
                    <span class="mt-1.5 font-mono text-[8px] uppercase tracking-[0.25em] text-slate-300 font-light opacity-80">
                      COLOSSAL RED KING CRAB
                    </span>
                  </div>
                </div>
              </div>
              <div class="will-change-transform cursor-pointer flex-1 h-full relative" id="card-2" onclick="window.location='recipes.php'" style="transform-origin: 50% 100%; transform-style: preserve-3d; z-index: 15; border-radius: 0px; box-shadow: none; transform: none;">
                <div class="absolute inset-0 w-full h-full overflow-hidden bg-[#f8fafc]" style="backface-visibility: hidden; border-radius: inherit; transform: rotateY(0deg) translateZ(1px);">
                  <img alt="Pristine Deep-Sea Sourcing" class="w-full h-full object-cover pointer-events-none select-none" referrerpolicy="no-referrer" src="assets/splitimage.im-2-CUcMOYTo.png">
                </div>
                <div class="absolute inset-0 w-full h-full flex flex-col justify-center items-center text-center overflow-hidden bg-[#f8fafc]" style="backface-visibility: hidden; transform: rotateY(180deg) translateZ(1px); border-radius: inherit;">
                  <img alt="Miyako Seared Black Grouper Tataki" class="absolute inset-0 w-full h-full object-cover select-none pointer-events-none" referrerpolicy="no-referrer" src="assets/recipe2-D57BQ9mb.png">
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent">
                  </div>
                  <div class="relative z-10 w-full h-full flex flex-col justify-end items-center p-5 pb-6 text-white text-center">
                    <p class="font-sans font-medium text-sm sm:text-base md:text-[17px] leading-tight tracking-tight max-w-[220px] drop-shadow-md text-slate-100">
                      Miyako Seared Black Grouper Tataki
                    </p>
                    <span class="mt-1.5 font-mono text-[8px] uppercase tracking-[0.25em] text-slate-300 font-light opacity-80">
                      PAN-SEARED DEEP SEA FISH
                    </span>
                  </div>
                </div>
              </div>
              <div class="will-change-transform cursor-pointer flex-1 h-full relative" id="card-3" onclick="window.location='recipes.php'" style="transform-origin: 50% 100%; border-radius: 0px 20px 20px 0px; transform-style: preserve-3d; z-index: 20; box-shadow: none; transform: none;">
                <div class="absolute inset-0 w-full h-full overflow-hidden bg-[#f8fafc]" style="backface-visibility: hidden; border-radius: inherit; transform: rotateY(0deg) translateZ(1px);">
                  <img alt="Master Hot-Pot &amp; Platter Artistry" class="w-full h-full object-cover pointer-events-none select-none" referrerpolicy="no-referrer" src="assets/splitimage.im-3-QWcmCWeO.png">
                </div>
                <div class="absolute inset-0 w-full h-full flex flex-col justify-center items-center text-center overflow-hidden bg-[#f8fafc]" style="backface-visibility: hidden; transform: rotateY(180deg) translateZ(1px); border-radius: inherit;">
                  <img alt="Hiroshima Emperor Pearl Oyster Platter" class="absolute inset-0 w-full h-full object-cover select-none pointer-events-none" referrerpolicy="no-referrer" src="assets/recipe3-u_vq5WAz.png">
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent">
                  </div>
                  <div class="relative z-10 w-full h-full flex flex-col justify-end items-center p-5 pb-6 text-white text-center">
                    <p class="font-sans font-medium text-sm sm:text-base md:text-[17px] leading-tight tracking-tight max-w-[220px] drop-shadow-md text-slate-100">
                      Hiroshima Emperor Pearl Oyster Platter
                    </p>
                    <span class="mt-1.5 font-mono text-[8px] uppercase tracking-[0.25em] text-slate-300 font-light opacity-80">
                      CHILLED PEARL OYSTERS WITH CAVIAR
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <section id="custom-seafood-banner" class="relative w-full overflow-hidden bg-slate-950 py-24 sm:py-32 md:py-36 text-white border-t border-slate-900">
          <div class="absolute inset-0 w-full h-full">
            <img alt="Premium raw sashimi background" class="w-full h-full object-cover opacity-35 select-none pointer-events-none scale-105 filter contrast-125 brightness-[0.7] transform duration-1000" referrerpolicy="no-referrer" src="assets/seafood_banner_bg_1780142350689-Xpud7jgV.png">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/80 to-slate-950/90">
            </div>
          </div>
          <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-12 flex flex-col items-center text-center">
            <h2 class="font-sans font-black text-4xl sm:text-5xl md:text-6xl tracking-[0.25em] text-white select-none drop-shadow-[0_4px_20px_rgba(0,0,0,0.6)] uppercase">
              International Fish Market
            </h2>
            <div class="w-full max-w-3xl h-[1px] bg-white/10 mt-8 mb-6">
            </div>
            <div class="w-full max-w-3xl flex justify-center items-center gap-10 sm:gap-16 md:gap-20 text-xs sm:text-sm font-sans font-semibold tracking-[0.2em] text-white/80">
              <a href="shop.php" class="hover:text-amber-400 hover:scale-105 transition-all duration-300 uppercase cursor-pointer" style="text-decoration:none;color:rgba(255,255,255,0.8)">
                Shop
              </a>
              <a href="recipes.php" class="hover:text-amber-400 hover:scale-105 transition-all duration-300 uppercase cursor-pointer" style="text-decoration:none;color:rgba(255,255,255,0.8)">
                Recipes
              </a>
              <a href="about.php" class="hover:text-amber-400 hover:scale-105 transition-all duration-300 uppercase cursor-pointer" style="text-decoration:none;color:rgba(255,255,255,0.8)">
                About
              </a>
            </div>
            <div class="w-full max-w-3xl h-[1px] bg-white/10 mt-6">
            </div>
          </div>
        </section>
      </div>
    </div>
    <script>
      window.__APP_ROUTE__ = "/";
      window.__APP_BASE_PATH__ = <?= json_encode($__ifmBasePath) ?>;
      window.__ADMIN_PHP_SESSION__ = false;
    </script>
    <script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
    // Register GSAP plugins
    gsap.registerPlugin(ScrollTrigger);

    // Initialize Lenis smooth scroll
    const lenis = new Lenis();
    lenis.on("scroll", ScrollTrigger.update);

    // ── Hero button click scroll ──
    document.getElementById("btn-browse-fish")?.addEventListener("click", function() {
        var el = document.getElementById("works");
        if (el) {
            var t = el.getBoundingClientRect().top + window.pageYOffset - 80;
            lenis.scrollTo(t);
        }
    });
    document.getElementById("btn-browse-recipes")?.addEventListener("click", function() {
        lenis.scrollTo("#careers");
    });

    // ── Right nav dot click scroll ──
    document.querySelectorAll(".fixed.right-5 .group").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var title = btn.getAttribute("title") || "";
            var top = 0;
            if (title.indexOf("Main") !== -1) {
                top = 0;
            } else if (title.indexOf("Shop") !== -1) {
                var el = document.getElementById("works");
                if (el) top = el.getBoundingClientRect().top + window.pageYOffset - 80;
            } else if (title.indexOf("Recipes") !== -1) {
                lenis.scrollTo("#careers");
                return;
            }
            if (top > 0) lenis.scrollTo(top);
        });
    });

    const tickerCallback = (time) => {
        lenis.raf(time * 1000);
    };
    gsap.ticker.add(tickerCallback);
    gsap.ticker.lagSmoothing(0);

    const cardContainer = document.getElementById("card-animation-container");
    const stickyHeader = document.querySelector(".sticky-header h2");

    let isGapAnimationCompleted = false;
    let isFlipAnimationCompleted = false;

    function initAnimations() {
        ScrollTrigger.getAll().forEach((trigger) => trigger.kill());

        const mm = gsap.matchMedia();

        mm.add("(max-width: 999px)", () => {
            const elements = document.querySelectorAll(".card, #card-1, #card-2, #card-3, #card-animation-container, .sticky-header h2");
            elements.forEach((el) => {
                el.style.cssText = "";
            });
            return {};
        });

        mm.add("(min-width: 1000px)", () => {
            ScrollTrigger.create({
                trigger: "#careers",
                start: "top top",
                end: `+=${window.innerHeight * 4}px`,
                scrub: 1,
                pin: true,
                pinSpacing: true,
                onUpdate: (self) => {
                    const progress = self.progress;

                    // Sticky title transition
                    if (progress >= 0.1 && progress <= 0.25) {
                        const headerProgress = gsap.utils.mapRange(0.1, 0.25, 0, 1, progress);
                        const yValue = gsap.utils.mapRange(0, 1, 40, 0, headerProgress);
                        const opacityValue = gsap.utils.mapRange(0, 1, 0, 1, headerProgress);
                        gsap.set(stickyHeader, { y: yValue, opacity: opacityValue });
                    } else if (progress < 0.1) {
                        gsap.set(stickyHeader, { y: 40, opacity: 0 });
                    } else if (progress > 0.25) {
                        gsap.set(stickyHeader, { y: 0, opacity: 1 });
                    }

                    // Card container compression (scaling from 78% down to 60%)
                    if (progress <= 0.25) {
                        const widthPercentage = gsap.utils.mapRange(0, 0.25, 78, 60, progress);
                        gsap.set(cardContainer, { width: `${widthPercentage}%` });
                    } else {
                        gsap.set(cardContainer, { width: "60%" });
                    }

                    // Gap split & individual rounding (triggered at ~0.35)
                    if (progress >= 0.35 && !isGapAnimationCompleted) {
                        gsap.to(cardContainer, { gap: "20px", duration: 0.5, ease: "power3.out" });
                        gsap.to(["#card-1", "#card-2", "#card-3"], { borderRadius: "20px", duration: 0.5, ease: "power3.out" });
                        isGapAnimationCompleted = true;
                    } else if (progress < 0.35 && isGapAnimationCompleted) {
                        gsap.to(cardContainer, { gap: "0px", duration: 0.5, ease: "power3.out" });
                        gsap.to("#card-1", { borderRadius: "20px 0 0 20px", duration: 0.5, ease: "power3.out" });
                        gsap.to("#card-2", { borderRadius: "0px", duration: 0.5, ease: "power3.out" });
                        gsap.to("#card-3", { borderRadius: "0 20px 20px 0", duration: 0.5, ease: "power3.out" });
                        isGapAnimationCompleted = false;
                    }

                    // Flip to backplates (triggered at ~0.70)
                    if (progress >= 0.7 && !isFlipAnimationCompleted) {
                        gsap.to("#card-1, #card-2, #card-3", {
                            rotationY: 180, duration: 0.75, ease: "power3.inOut", stagger: 0.1,
                        });
                        gsap.to(["#card-1", "#card-3"], {
                            y: 30, rotationZ: (i) => [-15, 15][i], duration: 0.75, ease: "power3.inOut",
                        });
                        isFlipAnimationCompleted = true;
                    } else if (progress < 0.7 && isFlipAnimationCompleted) {
                        gsap.to("#card-1, #card-2, #card-3", {
                            rotationY: 0, duration: 0.75, ease: "power3.inOut", stagger: 0.1,
                        });
                        gsap.to(["#card-1", "#card-3"], {
                            y: 0, rotationZ: 0, duration: 0.75, ease: "power3.inOut",
                        });
                        isFlipAnimationCompleted = false;
                    }
                },
            });
        });
    }

    initAnimations();

    let resizeTimer;
    const handleResize = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { initAnimations(); }, 250);
    };
    window.addEventListener("resize", handleResize);
});
</script>
  </body>
</html>