<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';
$__ifmAboutStoryCards = [
    ['color' => 'rgb(15, 76, 129)', 'title' => 'Fresh Catch'],
    ['color' => 'rgb(13, 148, 136)', 'title' => 'Seafood Care'],
    ['color' => 'rgb(234, 122, 47)', 'title' => 'Recipe Share'],
    ['color' => 'rgb(53, 92, 125)', 'title' => 'Table Story'],
];

$cards_data = [
    [
        'numberCode' => 'IFM.001 // FRESH_CATCH',
        'color' => 'rgb(15, 76, 129)',
        'title' => 'Fresh Catch',
        'description' => 'Our platform begins with premium seafood. We highlight freshness, sourcing, and visual quality so restaurants can present the story behind what they serve.',
        'subtitle' => 'Ocean First',
        'image' => '/assets/seafood_banner_bg_1780142350689-Xpud7jgV.png',
    ],
    [
        'numberCode' => 'IFM.002 // SEAFOOD_CARE',
        'color' => 'rgb(13, 148, 136)',
        'title' => 'Seafood Care',
        'description' => 'Cold-chain handling, texture protection, and product care all shape how seafood arrives in the kitchen and how confidently restaurants can share it with their guests.',
        'subtitle' => 'Handled Well',
        'image' => '/assets/Salmon-Sockeye-LY__RHmg.png',
    ],
    [
        'numberCode' => 'IFM.003 // RECIPE_SHARE',
        'color' => 'rgb(234, 122, 47)',
        'title' => 'Recipe Share',
        'description' => 'International Fish Market is not here to write recipes for them. We give restaurants a place to share their own seafood dishes, ideas, plating, and kitchen stories with a wider audience.',
        'subtitle' => 'Restaurant Voices',
        'image' => '/assets/recipe1-0-t-hNS_.png',
    ],
    [
        'numberCode' => 'IFM.004 // TABLE_STORY',
        'color' => 'rgb(53, 92, 125)',
        'title' => 'Table Story',
        'description' => 'From seafood selection to restaurant creativity, the final story belongs at the table. International Fish Market closes the loop by connecting product, people, recipes, and presentation in one place.',
        'subtitle' => 'Closing The Loop',
        'image' => '/assets/recipe3-u_vq5WAz.png',
    ],
];

// ── Products with images for rotating carousel ──
$__ifmAboutProducts = [];
$__spiralImages = [];
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=fishery_db;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $rows = $pdo->query(
        "SELECT name, image_url FROM product WHERE image_url IS NOT NULL AND image_url != '' AND is_active = 1 ORDER BY RAND() LIMIT 16"
    )->fetchAll();
    foreach ($rows as $r) {
        $filename = basename(str_replace('\\', '/', $r['image_url']));
        $__ifmAboutProducts[] = [
            'name' => $r['name'],
            'image_url' => '/assets/products/' . $filename,
        ];
    }

    // ── Fetch mixed images for spiral gallery ──
    $pi = $pdo->query("SELECT name, image_url FROM product WHERE image_url IS NOT NULL AND image_url != '' AND is_active = 1 ORDER BY RAND() LIMIT 20")->fetchAll();
    $ri = $pdo->query("SELECT title, image_url FROM export_recipes WHERE image_url IS NOT NULL AND image_url != '' ORDER BY RAND()")->fetchAll();
    $all = [];
    foreach ($pi as $p) {
        $fn = basename(str_replace('\\', '/', $p['image_url']));
        $all[] = ['title' => $p['name'], 'url' => '/assets/products/' . $fn];
    }
    foreach ($ri as $r) {
        $all[] = ['title' => $r['title'], 'url' => $r['image_url']];
    }
    shuffle($all);
    $__spiralImages = $all;
} catch (Exception $e) {}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>
      International Fish Market About
    </title>
    <link rel="stylesheet" href="css/about.css?v=<?= urlencode($__ifmAssetVersion('css/about.css')) ?>" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://unpkg.com/lenis@1.1.20/dist/lenis.min.js"></script>
  </head>
  <body style="margin: 0; overflow-x: hidden;">
    <div id="root">
      <div class="relative min-h-screen bg-bg-dark text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip" id="alche-studio-replica-root">
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
        <div id="subpage-viewport">
          <div class="relative bg-[linear-gradient(180deg,#f8fbfd_0%,#eff6fa_100%)]">
            <div class="relative min-h-[170svh] bg-[radial-gradient(circle_at_top,#f2f9fc_0%,#e6f1f6_34%,#d3e3eb_100%)]">
              <div class="fixed inset-0 z-0 flex h-screen w-full items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top,#f2f9fc_0%,#e6f1f6_34%,#d3e3eb_100%)]">
                <div class="absolute inset-0 bg-[linear-gradient(180deg,rgba(255,255,255,0.58)_0%,rgba(255,255,255,0)_20%,rgba(226,238,244,0.38)_100%)]">
                </div>
                <div class="absolute inset-0 opacity-30 bg-[linear-gradient(rgba(15,23,42,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(15,23,42,0.03)_1px,transparent_1px)] bg-[size:44px_44px]">
                </div>
                <div class="absolute left-1/2 top-1/2 h-[38vw] w-[38vw] min-h-[320px] min-w-[320px] max-h-[580px] max-w-[580px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/50 blur-3xl">
                </div>
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                  <div class="absolute left-[5%] top-[6%] flex items-center gap-3 text-slate-700/45">
                    <span class="font-display text-[clamp(24px,3vw,42px)] font-black uppercase tracking-[-0.06em]">
                      About
                    </span>
                    <span class="h-px w-20 bg-slate-500/30">
                    </span>
                    <span class="font-mono text-[10px] uppercase tracking-[0.34em]">
                      Editorial Feature
                    </span>
                  </div>
                  <div class="absolute right-[6%] top-[7%] text-right font-mono text-[10px] uppercase tracking-[0.28em] text-slate-600/48">
                    <div>
                      Volume 08
                    </div>
                    <div>
                      Issue 24
                    </div>
                  </div>
                  <div class="absolute left-1/2 top-[14%] -translate-x-1/2 whitespace-nowrap font-display text-[18vw] font-black uppercase leading-none tracking-[-0.08em] text-slate-900/[0.05]">
                    ABOUT INTERNATIONAL FISH MARKET
                  </div>
                  <div class="absolute left-[7%] top-[28%] max-w-[220px] space-y-2 text-slate-600/40">
                    <div class="font-display text-[clamp(18px,1.8vw,28px)] font-bold uppercase tracking-[-0.04em]">
                      A closer look at
                      <br>
                      the house story
                    </div>
                    <div class="font-mono text-[10px] uppercase leading-relaxed tracking-[0.22em]">
                      An editorial background on sourcing, quality, freshness, and the visual language behind the International Fish Market identity.
                    </div>
                  </div>
                  <div class="absolute left-[8%] top-[18%] rounded-full border border-white/55 bg-white/28 px-4 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-600/65 backdrop-blur-sm">
                    Our Story
                  </div>
                  <div class="absolute right-[9%] top-[24%] rounded-full border border-white/55 bg-white/28 px-4 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-600/65 backdrop-blur-sm">
                    Freshness
                  </div>
                  <div class="absolute left-[10%] bottom-[24%] rounded-full border border-white/55 bg-white/28 px-4 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-600/65 backdrop-blur-sm">
                    Quality
                  </div>
                  <div class="absolute right-[12%] bottom-[18%] rounded-full border border-white/55 bg-white/28 px-4 py-2 font-mono text-[10px] font-semibold uppercase tracking-[0.28em] text-slate-600/65 backdrop-blur-sm">
                    Craftsmanship
                  </div>
                  <div class="absolute left-[16%] top-[32%] max-w-[180px] font-mono text-[10px] uppercase tracking-[0.24em] text-slate-500/42">
                    Premium seafood selection
                  </div>
                  <div class="absolute right-[14%] top-[46%] max-w-[160px] text-right font-mono text-[10px] uppercase tracking-[0.24em] text-slate-500/42">
                    From ocean to table
                  </div>
                  <div class="absolute right-[7%] bottom-[30%] max-w-[240px] border-t border-slate-400/18 pt-3 font-mono text-[10px] uppercase leading-relaxed tracking-[0.2em] text-slate-500/40">
                    Premium selection, handled with care and presented through a cleaner, more refined about narrative.
                  </div>
                  <div class="absolute left-[6%] bottom-[10%] flex items-center gap-4 text-slate-500/38">
                    <span class="font-mono text-[10px] uppercase tracking-[0.36em]">
                      Feature
                    </span>
                    <span class="h-px w-28 bg-slate-400/20">
                    </span>
                    <span class="font-display text-[clamp(16px,1.6vw,26px)] font-bold uppercase tracking-[-0.04em]">
                      House Notes
                    </span>
                  </div>
                </div>
                <div id="gl-viewport">
                  <canvas id="spiral-webgl-canvas"></canvas>
                </div>
              </div>
              <div class="relative z-0 w-full pointer-events-none" style="height: 148vh;">
              </div>
            </div>
            <section class="relative z-20 -mt-[6vh] w-full border-t border-zinc-900/60 bg-[linear-gradient(180deg,#f8fbfd_0%,#eff6fa_100%)] pb-20 pt-16 shadow-[0_-30px_100px_rgba(0,0,0,0.9)]">
              <div class="mx-auto max-w-6xl px-4 md:px-8">
                <div class="overflow-hidden rounded-[38px] border border-slate-200/70 bg-[linear-gradient(180deg,rgba(255,255,255,0.95)_0%,rgba(247,250,252,0.98)_100%)] shadow-[0_35px_90px_rgba(15,23,42,0.08)] backdrop-blur-md">
                  <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-5 md:px-8">
                    <div class="flex items-center space-x-3">
                      <div class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-question-mark" aria-hidden="true">
                          <circle cx="12" cy="12" r="10">
                          </circle>
                          <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3">
                          </path>
                          <path d="M12 17h.01">
                          </path>
                        </svg>
                      </div>
                      <div>
                        <h3 class="font-display text-sm font-black uppercase leading-tight tracking-tight text-slate-800">
                          International Fish Market Philosophy
                        </h3>
                        <p class="mt-0.5 font-mono text-[9px] uppercase tracking-widest text-slate-400">
                          Sub-Zero cold chain technology
                        </p>
                      </div>
                    </div>
                  </div>
                  <div class="space-y-10 p-6 md:space-y-12 md:p-8">
                    <div class="grid gap-8">
                      <div class="space-y-6 rounded-[28px] border border-slate-200/90 bg-slate-50/85 p-6 md:p-8">
                        <div class="flex items-center space-x-2 text-brand-blue">
                          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles" aria-hidden="true">
                            <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                            </path>
                            <path d="M20 2v4">
                            </path>
                            <path d="M22 4h-4">
                            </path>
                            <circle cx="4" cy="20" r="2">
                            </circle>
                          </svg>
                          <h4 class="font-display text-xs font-bold uppercase tracking-wider">
                            The Legendary International Fish Market -60Â°C Promise
                          </h4>
                        </div>
                        <div class="space-y-4 text-xs font-light leading-relaxed text-slate-600 md:text-sm">
                          <p>
                            Pristine sashimi is a living work of art. To secure the perfect cell moisture and delicate raw marbling without ice crystallization damage, International Fish Market relies on hyper-baric Ikejime processes directly at raw harbor coordinates, followed by deep liquid-nitrogen static flash freezes recorded on continuous satellite IoT holds.
                          </p>
                          <p>
                            Every route in our chain is engineered around freshness retention, visual integrity, and premium restaurant-grade handling from sea to service.
                          </p>
                        </div>
                      </div>
                    </div>
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                      <div class="space-y-3 rounded-2xl border border-slate-200 bg-white/75 p-5 shadow-sm transition-shadow hover:shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-shield-check text-brand-blue" aria-hidden="true">
                          <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z">
                          </path>
                          <path d="m9 12 2 2 4-4">
                          </path>
                        </svg>
                        <h5 class="font-display text-xs font-semibold uppercase tracking-wider text-slate-900">
                          100% Traceability
                        </h5>
                        <p class="text-[10.5px] font-light leading-relaxed text-slate-500">
                          Scan any QR code on your parcel to view catch coordinates, fishing boat credentials, and custom inspection logs.
                        </p>
                      </div>
                      <div class="space-y-3 rounded-2xl border border-slate-200 bg-white/75 p-5 shadow-sm transition-shadow hover:shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-coins text-brand-teal" aria-hidden="true">
                          <circle cx="8" cy="8" r="6">
                          </circle>
                          <path d="M18.09 10.37A6 6 0 1 1 10.34 18">
                          </path>
                          <path d="M7 6h1v4">
                          </path>
                          <path d="m16.71 13.88.7.71-2.82 2.82">
                          </path>
                        </svg>
                        <h5 class="font-display text-xs font-semibold uppercase tracking-wider text-slate-900">
                          Sustainable Sourcing
                        </h5>
                        <p class="text-[10.5px] font-light leading-relaxed text-slate-500">
                          Strict quotas, traditional single-hook rod fishing, and active coral reef restoration investments are absolute values.
                        </p>
                      </div>
                      <div class="space-y-3 rounded-2xl border border-slate-200 bg-white/75 p-5 shadow-sm transition-shadow hover:shadow-md">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock text-brand-orange" aria-hidden="true">
                          <path d="M12 6v6l4 2">
                          </path>
                          <circle cx="12" cy="12" r="10">
                          </circle>
                        </svg>
                        <h5 class="font-display text-xs font-semibold uppercase tracking-wider text-slate-900">
                          Supersonic Delivery
                        </h5>
                        <p class="text-[10.5px] font-light leading-relaxed text-slate-500">
                          By leveraging dry-ice insulated flight crates, catch is delivered from live ocean water to critical ports inside 18 hours.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </section>
    <!-- ── Deck Sequence ── -->
    <div class="app-container" id="app-root-viewport">

      <div class="deck-stage-wrapper" id="parallax-3d-stage">
        <div class="deck-radial-dots"></div>
        <div class="deck-watermark">Frame</div>

        <nav class="deck-header-nav">
          <div style="display:flex; flex-direction:column;">
            <span style="font-size:10px; font-family:'DM Mono',monospace; letter-spacing:0.3em; text-transform:uppercase; opacity:0.6;">Project Reference</span>
            <span style="font-size:24px; font-weight:900; text-transform:uppercase; font-style:italic; margin-top:4px;">Brand / Appart</span>
          </div>
          <div style="display:flex; gap:48px;">
            <div style="display:flex; flex-direction:column; align-items:flex-end;">
              <span style="font-size:10px; font-family:'DM Mono',monospace; letter-spacing:0.3em; text-transform:uppercase; opacity:0.6;">Sequence</span>
              <span id="deck-sequence-counter" style="font-size:20px; font-weight:bold; font-family:'DM Mono',monospace;">01 — 04</span>
            </div>
            <div class="deck-burger" id="deck-burger-gate">
              <div></div>
              <div></div>
            </div>
          </div>
        </nav>

        <div id="cards-container" style="width:100%; height:100%; position:relative; transform-style:preserve-3d;">
          <div id="card-node-1" class="stack-card" style="background-color: rgb(15, 76, 129);">
            <div class="card-info">
              <div>
                <span style="font-family:'DM Mono',monospace; font-size:10px; opacity:0.4; display:block; letter-spacing:1px; text-transform:uppercase;">
                  IFM.001 // FRESH_CATCH
                </span>
                <h2>
          <span>Fresh</span>
          <span>Catch</span>
                </h2>
              </div>
              <div class="card-meta">
                <p>Our platform begins with premium seafood. We highlight freshness, sourcing, and visual quality so restaurants can present the story behind what they serve.</p>
                <p style="margin-top:16px; font-weight:bold; opacity:0.6; text-transform:uppercase;">
                  Ocean First // ACTIVE
                </p>
              </div>
            </div>
            <div class="card-media">
              <img src="/assets/seafood_banner_bg_1780142350689-Xpud7jgV.png" alt="Fresh Catch">
            </div>
          </div>
          <div id="card-node-2" class="stack-card" style="background-color: rgb(13, 148, 136);">
            <div class="card-info">
              <div>
                <span style="font-family:'DM Mono',monospace; font-size:10px; opacity:0.4; display:block; letter-spacing:1px; text-transform:uppercase;">
                  IFM.002 // SEAFOOD_CARE
                </span>
                <h2>
          <span>Seafood</span>
          <span>Care</span>
                </h2>
              </div>
              <div class="card-meta">
                <p>Cold-chain handling, texture protection, and product care all shape how seafood arrives in the kitchen and how confidently restaurants can share it with their guests.</p>
                <p style="margin-top:16px; font-weight:bold; opacity:0.6; text-transform:uppercase;">
                  Handled Well // ACTIVE
                </p>
              </div>
            </div>
            <div class="card-media">
              <img src="/assets/Salmon-Sockeye-LY__RHmg.png" alt="Seafood Care">
            </div>
          </div>
          <div id="card-node-3" class="stack-card" style="background-color: rgb(234, 122, 47);">
            <div class="card-info">
              <div>
                <span style="font-family:'DM Mono',monospace; font-size:10px; opacity:0.4; display:block; letter-spacing:1px; text-transform:uppercase;">
                  IFM.003 // RECIPE_SHARE
                </span>
                <h2>
          <span>Recipe</span>
          <span>Share</span>
                </h2>
              </div>
              <div class="card-meta">
                <p>International Fish Market is not here to write recipes for them. We give restaurants a place to share their own seafood dishes, ideas, plating, and kitchen stories with a wider audience.</p>
                <p style="margin-top:16px; font-weight:bold; opacity:0.6; text-transform:uppercase;">
                  Restaurant Voices // ACTIVE
                </p>
              </div>
            </div>
            <div class="card-media">
              <img src="/assets/recipe1-0-t-hNS_.png" alt="Recipe Share">
            </div>
          </div>
          <div id="card-node-4" class="stack-card" style="background-color: rgb(53, 92, 125);">
            <div class="card-info">
              <div>
                <span style="font-family:'DM Mono',monospace; font-size:10px; opacity:0.4; display:block; letter-spacing:1px; text-transform:uppercase;">
                  IFM.004 // TABLE_STORY
                </span>
                <h2>
          <span>Table</span>
          <span>Story</span>
                </h2>
              </div>
              <div class="card-meta">
                <p>From seafood selection to restaurant creativity, the final story belongs at the table. International Fish Market closes the loop by connecting product, people, recipes, and presentation in one place.</p>
                <p style="margin-top:16px; font-weight:bold; opacity:0.6; text-transform:uppercase;">
                  Closing The Loop // ACTIVE
                </p>
              </div>
            </div>
            <div class="card-media">
              <img src="/assets/recipe3-u_vq5WAz.png" alt="Table Story">
            </div>
          </div>
        </div>

        <footer class="deck-footer">
          <div style="max-width:320px; text-align:left;">
            <p id="dynamic-footer-log" style="font-size:10px; font-family:'DM Mono',monospace; line-height:1.6; opacity:0.6; font-style:italic;">
              * AUTOMATED NEURAL ASSEMBLY PROCESS INITIATED.
            </p>
          </div>
          <div style="display:flex; flex-direction:column; align-items:flex-end; gap:12px;">
            <div style="display:flex; gap:12px;">
              <button class="deck-footer-btn" id="deck-btn-prev">&larr;</button>
              <button class="deck-footer-btn" id="deck-btn-next" style="background-color:#000; color:#fff;">&rarr;</button>
            </div>
            <span id="deck-next-preview-label" style="font-size:10px; font-family:'DM Mono',monospace; letter-spacing:0.2em; text-transform:uppercase; font-weight:bold;">
              NEXT: SEAFOOD CARE
            </span>
          </div>
        </footer>
      </div>

      <div class="floating-capsule-nav" id="capsule-nav-bar">        <button class="capsule-item" data-target-stage="1">
          <span class="capsule-dot" style="background-color: rgb(15, 76, 129);"></span>
          <span>01</span>
        </button>
        <button class="capsule-item" data-target-stage="2">
          <span class="capsule-dot" style="background-color: rgb(13, 148, 136);"></span>
          <span>02</span>
        </button>
        <button class="capsule-item" data-target-stage="3">
          <span class="capsule-dot" style="background-color: rgb(234, 122, 47);"></span>
          <span>03</span>
        </button>
        <button class="capsule-item" data-target-stage="4">
          <span class="capsule-dot" style="background-color: rgb(53, 92, 125);"></span>
          <span>04</span>
        </button>
      </div>

      <section id="stage-panel-outro">
        <div class="outro-lines-bg"></div>
        <div class="outro-glow"></div>

        <div style="z-index:10; display:flex; justify-content:space-between; font-family:'DM Mono',monospace; font-size:12px; color:rgba(255,255,255,0.4);">
          <span>04 // 04 COMPLETED</span>
          <span>EDITORIAL ARC: 100%</span>
        </div>

        <div style="z-index:10; text-align:center; margin:auto; display:flex; flex-direction:column; align-items:center;">
          <p style="font-family:'DM Mono',monospace; font-size:12px; color:#f97316; letter-spacing:0.4em; font-weight:600; margin-bottom:16px;">Story Completed</p>
          <h1 style="font-size:clamp(3rem, 7vw, 7rem); font-weight:900; font-style:italic; text-transform:uppercase; color:#fff; line-height:0.85; letter-spacing:-0.03em; display:flex; flex-direction:column; align-items:center;">
            <span>International Fish Market</span>
            <span style="background:linear-gradient(to right, #f97316, #f43f5e, #fbbf24); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">Closed Loop</span>
          </h1>
          <p style="margin-top:24px; max-width:480px; font-family:'DM Mono',monospace; font-size:11px; text-transform:uppercase; letter-spacing:0.24em; color:rgba(255,255,255,0.45);">
            From fresh catch to finished plate, every part of the journey returns to one promise: quality you can see, cook, and remember.
          </p>
          <button class="replay-action-trigger" id="btn-trigger-replay">
            <span>Replay Transition</span>
          </button>
        </div>

        <div style="z-index:10; display:flex; justify-content:space-between; align-items:center; font-family:'DM Mono',monospace; font-size:10px; color:rgba(255,255,255,0.3); border-top:1px solid rgba(255,255,255,0.05); padding-top:16px;">
          <span>Fresh catch // careful handling</span>
          <span>Recipes // story // table</span>
          <span>International Fish Market</span>
        </div>
      </section>

    </div>
    <script>
      window.__APP_ROUTE__ = "/about.php";
      window.__APP_BASE_PATH__ = <?= json_encode($__ifmBasePath) ?>;
      window.__ADMIN_PHP_SESSION__ = false;
      window.__IFM_ABOUT_STORY__ = <?= json_encode($__ifmAboutStoryCards, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.__IFM_ABOUT_PRODUCTS__ = <?= json_encode($__ifmAboutProducts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      window.__SPIRAL_IMAGES__ = <?= json_encode($__spiralImages, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>

<script>
         document.addEventListener("DOMContentLoaded", () => { 
             // Base structure definition 
             const cardsData = <?php echo json_encode($cards_data); ?>; 
             const totalCards = cardsData.length; 
             
             // ⚡ Start at 1 by default (show first card immediately, no Stage 0) 
             let currentStage = 1; 
             let isTransitioning = false; 
 
             // DOM node references 
             const outroPanel = document.getElementById("stage-panel-outro"); 
             const capsuleNavBar = document.getElementById("capsule-nav-bar"); 
             
             const seqCounter = document.getElementById("deck-sequence-counter"); 
             const footerLog = document.getElementById("dynamic-footer-log"); 
             const nextPreviewLabel = document.getElementById("deck-next-preview-label"); 
             
             const btnPrev = document.getElementById("deck-btn-prev"); 
             const btnNext = document.getElementById("deck-btn-next"); 
 
             // Cache all dynamic stacked card nodes 
             const cardNodes = []; 
             for (let i = 1; i <= totalCards; i++) { 
                 cardNodes.push(document.getElementById(`card-node-${i}`)); 
             } 
 
             // --------------------------------------------------------- 
             // Core layout sync solver 
             // --------------------------------------------------------- 
             function syncApplicationState(stage) { 
                 const activeDeckIndex = Math.max(0, Math.min(stage - 1, totalCards - 1)); 
 
                 // 1. Handle STAGE 5 (Outro) — black panel slides up from bottom to cover cards
                 if (stage === 5) { 
                     outroPanel.style.transform = "translateY(0%)"; 
                     capsuleNavBar.style.opacity = "0"; 
                     capsuleNavBar.style.pointerEvents = "none"; 
                     // Keep cards in place and let outro cover them
                     return;
                 } else { 
                     outroPanel.style.transform = "translateY(100%)"; 
                     capsuleNavBar.style.opacity = "1"; 
                     capsuleNavBar.style.pointerEvents = "auto"; 
                 } 
 
                 // 4. Drive 3D physical card stacking layout 
                 cardNodes.forEach((node, idx) => { 
                     if (!node) return; 
                     const cardStageNum = idx + 1; 
                     
                     const isPast = cardStageNum < stage; 
                     const isActive = cardStageNum === stage; 
                     const level = cardStageNum - stage; 
 
                     let yVal, scaleVal, rotateXVal, opacityVal; 
 
                     if (isPast) { 
                         yVal = "-220%"; // Move fully off-screen upward along Y axis 
                         scaleVal = 1.05; 
                         rotateXVal = 35; 
                         opacityVal = 0; 
                     } else if (isActive) { 
                         yVal = "-50%";  // Golden center lock 
                         scaleVal = 1; 
                         rotateXVal = 0; 
                         opacityVal = 1; 
                     } else { 
                         // Perfect layer stack offset formula 
                         yVal = `calc(-50% + ${level * 16}px)`; 
                         scaleVal = 1 - (level * 0.06); 
                         rotateXVal = 0; 
                         opacityVal = 1; 
                     } 
 
                     node.style.transform = `translate(-50%, ${yVal}) scale(${scaleVal}) rotateX(${rotateXVal}deg)`; 
                     node.style.opacity = opacityVal; 
                     node.style.zIndex = 100 - level; 
                 }); 
 
                 // 5. Sync card text, sequence number, and preview label 
                 if (stage >= 1 && stage < 5) { 
                     const currentCard = cardsData[activeDeckIndex]; 
                     seqCounter.innerText = `0${activeDeckIndex + 1} — 04`; 
                     footerLog.innerText = `* AUTOMATED NEURAL ASSEMBLY PROCESS INITIATED. ENSURING DRIFT STABILITY IN ${currentCard.title.toUpperCase()} BEFORE LOOP COMPLETION.`; 
                     
                     if (stage >= 4) { 
                         nextPreviewLabel.innerText = "NEXT: OUTRO LOOP"; 
                     } else { 
                         const nextCard = cardsData[activeDeckIndex + 1]; 
                         nextPreviewLabel.innerText = `NEXT: ${nextCard.title.toUpperCase()}`; 
                     } 
                 } 
 
                 // 6. Drive capsule status light highlight switching 
                 document.querySelectorAll(".capsule-item").forEach((btn, idx) => { 
                     if (stage < 5 && idx === activeDeckIndex) { 
                         btn.classList.add("active"); 
                     } else { 
                         btn.classList.remove("active"); 
                     } 
                 }); 
 
                 // 7. Handle physical footer button boundary disable lock 
                 btnPrev.disabled = (stage <= 1); 
                 btnNext.disabled = (stage >= 5); 
             } 
 
             // Safety boundary action trigger (built-in 850ms lock) 
             function dispatchStageTransition(direction) { 
                 if (isTransitioning) return; 
 
                 if (direction === "next" && currentStage < 5) { 
                     isTransitioning = true; 
                     currentStage++; 
                     syncApplicationState(currentStage); 
                     setTimeout(() => { isTransitioning = false; }, 850); 
                 } else if (direction === "prev" && currentStage > 1) { 
                     isTransitioning = true; 
                     currentStage--; 
                     syncApplicationState(currentStage); 
                     setTimeout(() => { isTransitioning = false; }, 850); 
                 } 
             } 
 
             // --------------------------------------------------------- 
             // Environmental physical sensing capture suite (capsule click, gyro micro-off) 
             // --------------------------------------------------------- 

             // Capsule nav light direct click jump support 
             document.querySelectorAll(".capsule-item").forEach(button => { 
                 button.addEventListener("click", () => { 
                     const target = parseInt(button.getAttribute("data-target-stage"), 10); 
                     if (isTransitioning || currentStage === target) return; 
                     isTransitioning = true; 
                     currentStage = target; 
                     syncApplicationState(currentStage); 
                     setTimeout(() => { isTransitioning = false; }, 850); 
                 }); 
             }); 
 
             // Physical micro-offset parallax (Mouse Parallax) — only affects card container
             const cardsContainer = document.getElementById("cards-container");
             const mouseCoord = { x: 0, y: 0, targetX: 0, targetY: 0 }; 
             window.addEventListener("mousemove", (e) => { 
                 mouseCoord.targetX = (e.clientX / window.innerWidth - 0.5) * 2; 
                 mouseCoord.targetY = (e.clientY / window.innerHeight - 0.5) * 2; 
             }); 
 
             function loop() { 
                 mouseCoord.x += (mouseCoord.targetX - mouseCoord.x) * 0.07; 
                 mouseCoord.y += (mouseCoord.targetY - mouseCoord.y) * 0.07; 
                 if (cardsContainer) {
                     cardsContainer.style.transform = `perspective(1000px) rotateY(${mouseCoord.x * 1.5}deg) rotateX(${-mouseCoord.y * 1.5}deg)`; 
                 }
                 requestAnimationFrame(loop); 
             } 
             requestAnimationFrame(loop); 
 
             // Base action button event binding 
             document.getElementById("deck-burger-gate").addEventListener("click", () => { 
                 dispatchStageTransition("next"); 
             }); 
             btnPrev.addEventListener("click", () => dispatchStageTransition("prev")); 
             btnNext.addEventListener("click", () => dispatchStageTransition("next")); 
             
             document.getElementById("btn-trigger-replay").addEventListener("click", () => { 
                 isTransitioning = true; 
                 currentStage = 1; 
                 syncApplicationState(currentStage); 
                 setTimeout(() => { isTransitioning = false; }, 850); 
             }); 
 
             // Run initial frame sync (render starting from first card Stage 1) 
             syncApplicationState(1); 
         }); 
     </script>

<script>
// ── Spiral 3D WebGL Gallery (IIFE sandbox) ──
(() => {
    document.addEventListener("DOMContentLoaded", () => {

        const IMAGES = window.__SPIRAL_IMAGES__ || [];

        const container = document.getElementById("gl-viewport");
        const canvas = document.getElementById("spiral-webgl-canvas");
        if (!container || !canvas) return;

        const vertexShader = `
          varying vec2 vUv;
          varying vec3 vWorldNormal;
          varying vec3 vWorldPosition;
          void main() {
            vUv = uv;
            vWorldNormal = normalize((modelMatrix * vec4(normal, 0.0)).xyz);
            vWorldPosition = (modelMatrix * vec4(position, 1.0)).xyz;
            gl_Position = projectionMatrix * viewMatrix * modelMatrix * vec4(position, 1.0);
          }
        `;
        const fragmentShader = `
          uniform sampler2D uMap;
          uniform vec3 uCameraPosition;
          varying vec2 vUv;
          varying vec3 vWorldNormal;
          varying vec3 vWorldPosition;
          void main() {
            vec4 tex = texture2D(uMap, vUv);
            vec3 viewDir = normalize(uCameraPosition - vWorldPosition);
            float facing = max(dot(-normalize(vWorldNormal), viewDir), 0.0);
            float falloff = smoothstep(-0.2, 0.5, facing) * 0.45 + 0.42;
            vec3 color = mix(vec3(1.0), tex.rgb * falloff, 0.975) * 1.25;
            gl_FragColor = vec4(color, tex.a);
          }
        `;

        const spiralGap = 0.35;
        const cameraZ = 16.0;
        const baseRotationSpeed = 0.0004;
        const parallaxStrength = 0.12;
        const revolutions = 3.0;
        const startRadius = 5.6;
        const endRadius = 3.6;
        const scrollMultiplier = 3.6;
        const cameraSmoothing = 0.10;

        const totalTiles = IMAGES.length;
        const tilesPerRevolution = totalTiles / revolutions;
        const angleStep = (Math.PI * 2) / tilesPerRevolution;

        const scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2("#0d0d11", 0.03);

        const camera = new THREE.PerspectiveCamera(70, container.clientWidth / window.innerHeight, 0.1, 1000);
        camera.position.z = cameraZ;

        const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: "high-performance" });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
        renderer.setSize(container.clientWidth, window.innerHeight);

        const textureLoader = new THREE.TextureLoader();
        textureLoader.setCrossOrigin("anonymous");
        const textures = IMAGES.map(img => {
            return textureLoader.load(img.url, (t) => {
                t.minFilter = THREE.LinearMipmapLinearFilter;
                t.anisotropy = Math.min(renderer.capabilities.getMaxAnisotropy(), 4);
            });
        });

        const tileEdgesY = [0];
        for (let i = 0; i < totalTiles; i++) {
            const progress = i / totalTiles;
            const radius = startRadius + (endRadius - startRadius) * progress;
            const arcWidth = (2 * Math.PI * radius) / tilesPerRevolution;
            const tileHeight = arcWidth * 0.72;
            tileEdgesY.push(tileEdgesY[i] - (tileHeight + spiralGap) / tilesPerRevolution);
        }

        const spiral = new THREE.Group();
        scene.add(spiral);

        const cameraPositionUniform = { value: new THREE.Vector3() };
        const raycaster = new THREE.Raycaster();
        const meshesList = [];

        for (let i = 0; i < totalTiles; i++) {
            const progress = i / totalTiles;
            const radius = startRadius + (endRadius - startRadius) * progress;
            const arcWidth = (2 * Math.PI * radius) / tilesPerRevolution;
            const tileHeight = arcWidth * 0.72;
            const tileAngle = arcWidth / radius + 0.005;
            const centerY = (tileEdgesY[i] + tileEdgesY[i + 1]) / 2;
            const slope = tileEdgesY[i + 1] - tileEdgesY[i];

            const positions = [];
            const uvCoords = [];
            const indices = [];
            const segments = 12;

            for (let row = 0; row <= 1; row++) {
                for (let col = 0; col <= segments; col++) {
                    const angle = (col / segments - 0.5) * tileAngle;
                    positions.push(Math.sin(angle) * radius, (row - 0.5) * tileHeight + (col / segments - 0.5) * slope, Math.cos(angle) * radius);
                    uvCoords.push(col / segments, row);
                }
            }
            for (let col = 0; col < segments; col++) {
                const current = col;
                const below = current + segments + 1;
                indices.push(current, below, current + 1, below, below + 1, current + 1);
            }

            const geometry = new THREE.BufferGeometry();
            geometry.setAttribute("position", new THREE.Float32BufferAttribute(positions, 3));
            geometry.setAttribute("uv", new THREE.Float32BufferAttribute(uvCoords, 2));
            geometry.setIndex(indices);
            geometry.computeVertexNormals();

            const material = new THREE.ShaderMaterial({
                vertexShader,
                fragmentShader,
                uniforms: { uMap: { value: textures[i] }, uCameraPosition: cameraPositionUniform },
                side: THREE.DoubleSide
            });

            const mesh = new THREE.Mesh(geometry, material);
            mesh.position.y = centerY;
            mesh.userData = { index: i, tileIndex: i, image: IMAGES[i] };
            meshesList.push(mesh);

            const tile = new THREE.Group();
            tile.rotation.y = i * angleStep;
            tile.add(mesh);
            spiral.add(tile);
        }

        let scrollY = 0;
        let mouseX = 0, mouseY = 0;
        let smoothX = 0, smoothY = 0;
        let isMobile = window.innerWidth < 1000;
        let lastScrollTime = 0;
        let autoTimeRotation = 0;

        const lenis = new Lenis({ lerp: 0.08, infinite: false });
        lenis.on("scroll", () => { scrollY = window.scrollY; lastScrollTime = Date.now(); });

        window.addEventListener("mousemove", (e) => {
            mouseX = (e.clientX / window.innerWidth - 0.5) * 2;
            mouseY = (e.clientY / window.innerHeight - 0.5) * 2;
        });

        function animate(time) {
            requestAnimationFrame(animate);
            lenis.raf(time);

            const progress = Math.max(0, Math.min(scrollY / (window.innerHeight * scrollMultiplier), 1));
            const linearStep = progress * (totalTiles - 1);
            const currentStepFloor = Math.floor(linearStep);
            const stepFraction = linearStep - currentStepFloor;

            const transitionWidth = 0.40;
            const dwellWidth = 1.0 - transitionWidth;
            const smoothFrac = Math.max(0, Math.min(1, (stepFraction - dwellWidth) / transitionWidth));
            const curve = smoothFrac * smoothFrac * (3 - 2 * smoothFrac);
            const steppedActiveStep = currentStepFloor + curve;

            const stepFloorIndex = Math.min(totalTiles - 1, Math.max(0, Math.floor(steppedActiveStep)));
            const stepFracPart = steppedActiveStep - stepFloorIndex;
            const targetCamY = tileEdgesY[stepFloorIndex] + (tileEdgesY[Math.min(totalTiles, stepFloorIndex + 1)] - tileEdgesY[stepFloorIndex]) * stepFracPart;

            camera.position.y += (targetCamY - camera.position.y) * cameraSmoothing;

            if (!isMobile) {
                smoothX += (mouseX - smoothX) * 0.04;
                smoothY += (mouseY - smoothY) * 0.04;
                spiral.rotation.x = smoothY * parallaxStrength;
                spiral.rotation.z = -smoothX * parallaxStrength * 0.35;
            }

            cameraPositionUniform.value.copy(camera.position);

            const isIdle = Date.now() - lastScrollTime > 1500;
            if (baseRotationSpeed > 0) {
                if (isIdle) {
                    autoTimeRotation -= baseRotationSpeed;
                } else {
                    const nearestMultiple = Math.round(autoTimeRotation / (2 * Math.PI)) * (2 * Math.PI);
                    autoTimeRotation += (nearestMultiple - autoTimeRotation) * 0.08;
                }
            }

            const targetScrollRotation = -steppedActiveStep * angleStep;
            const targetSpiralY = targetScrollRotation + autoTimeRotation;

            let diff = targetSpiralY - spiral.rotation.y;
            diff = Math.atan2(Math.sin(diff), Math.cos(diff));
            spiral.rotation.y += diff * 0.12;

            let hoveredTileIndex = null;
            raycaster.setFromCamera(new THREE.Vector2(mouseX, -mouseY), camera);
            const intersects = raycaster.intersectObjects(spiral.children, true);
            if (intersects.length > 0) {
                let hoveredObj = intersects[0].object;
                while (hoveredObj && hoveredObj.userData.tileIndex === undefined) { hoveredObj = hoveredObj.parent; }
                if (hoveredObj && typeof hoveredObj.userData.tileIndex === "number") { hoveredTileIndex = hoveredObj.userData.tileIndex; }
            }

            for (let i = 0; i < totalTiles; i++) {
                const m = meshesList[i];
                if (m) {
                    const isSelected = (i === hoveredTileIndex);
                    const targetScale = isSelected ? 1.08 : 1.0;
                    const targetZ = isSelected ? 0.35 : 0.0;
                    m.scale.x += (targetScale - m.scale.x) * 0.15;
                    m.scale.y += (targetScale - m.scale.y) * 0.15;
                    m.scale.z += (targetScale - m.scale.z) * 0.15;
                    m.position.z += (targetZ - m.position.z) * 0.15;
                }
            }
            canvas.style.cursor = hoveredTileIndex !== null ? "pointer" : "default";
            renderer.render(scene, camera);
        }

        requestAnimationFrame(animate);

        window.addEventListener("resize", () => {
            isMobile = window.innerWidth < 1000;
            camera.aspect = container.clientWidth / window.innerHeight;
            camera.position.z = isMobile ? 14 : cameraZ;
            camera.updateProjectionMatrix();
            renderer.setSize(container.clientWidth, window.innerHeight);
        });

        // ── Lightbox ──
        const lightbox = document.getElementById("lightbox");
        const lightboxImg = document.getElementById("lightbox-active-img");
        const dotsContainer = document.getElementById("lightbox-dots");
        let activeIndex = null;

        IMAGES.forEach((_, idx) => {
            const dot = document.createElement("div");
            dot.className = "counter-dot";
            dotsContainer.appendChild(dot);
        });
        const dots = document.querySelectorAll(".counter-dot");

        renderer.domElement.addEventListener("click", (e) => {
            const rect = renderer.domElement.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            const y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
            raycaster.setFromCamera(new THREE.Vector2(x, y), camera);
            const intersects = raycaster.intersectObjects(spiral.children, true);
            if (intersects.length > 0) {
                let clickedObj = intersects[0].object;
                while (clickedObj && !clickedObj.userData.image) { clickedObj = clickedObj.parent; }
                if (clickedObj && clickedObj.userData.image) {
                    openLightbox(clickedObj.userData.index);
                }
            }
        });

        function openLightbox(index) {
            activeIndex = index;
            lightboxImg.setAttribute("src", IMAGES[index].url);
            lightbox.classList.add("active");
            updateDots();
            lenis.stop();
        }

        function closeLightbox() {
            lightbox.classList.remove("active");
            lenis.start();
        }

        function updateDots() {
            dots.forEach((dot, idx) => {
                if (idx === activeIndex) dot.classList.add("active");
                else dot.classList.remove("active");
            });
        }

        document.getElementById("lightbox-close-btn").addEventListener("click", closeLightbox);
        document.getElementById("lightbox-left-nav").addEventListener("click", () => {
            activeIndex = (activeIndex - 1 + totalTiles) % totalTiles;
            lightboxImg.setAttribute("src", IMAGES[activeIndex].url);
            updateDots();
        });
        document.getElementById("lightbox-right-nav").addEventListener("click", () => {
            activeIndex = (activeIndex + 1) % totalTiles;
            lightboxImg.setAttribute("src", IMAGES[activeIndex].url);
            updateDots();
        });

        window.addEventListener("keydown", (e) => {
            if (!lightbox.classList.contains("active")) return;
            if (e.key === "Escape") closeLightbox();
            if (e.key === "ArrowRight") document.getElementById("lightbox-right-nav").click();
            if (e.key === "ArrowLeft") document.getElementById("lightbox-left-nav").click();
        });

    });
})();
</script>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox">
  <div class="lightbox-container">
    <button class="lightbox-close" id="lightbox-close-btn">&times;</button>
    <button class="lightbox-nav prev" id="lightbox-left-nav">&larr;</button>
    <img id="lightbox-active-img" src="" alt="Gallery image" />
    <button class="lightbox-nav next" id="lightbox-right-nav">&rarr;</button>
    <div class="lightbox-dots" id="lightbox-dots"></div>
  </div>
</div>

<script>
// Neon grid canvas
(function(){
  const c = document.getElementById('neon-grid-stage');
  if (!c) return;
  const ctx = c.getContext('2d');
  if (!ctx) return;
  let W = c.width = window.innerWidth;
  let H = c.height = window.innerHeight;
  const dots = [];
  const count = Math.min(80, Math.floor(W * H / 15000));
  for (let i = 0; i < count; i++) {
    dots.push({ x: Math.random()*W, y: Math.random()*H, vx: (Math.random()-0.5)*0.4, vy: (Math.random()-0.5)*0.4, r: 1.5+Math.random()*2 });
  }
  function drawGrid() {
    ctx.clearRect(0,0,W,H);
    // lines
    ctx.strokeStyle = 'rgba(30,58,95,0.08)';
    ctx.lineWidth = 1;
    for (let x = 0; x < W; x += 60) {
      ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,H); ctx.stroke();
    }
    for (let y = 0; y < H; y += 60) {
      ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(W,y); ctx.stroke();
    }
    // dots
    for (const d of dots) {
      d.x += d.vx; d.y += d.vy;
      if (d.x < 0 || d.x > W) d.vx *= -1;
      if (d.y < 0 || d.y > H) d.vy *= -1;
      ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI*2);
      ctx.fillStyle = 'rgba(30,58,95,0.12)'; ctx.fill();
    }
    // connections
    for (let i = 0; i < dots.length; i++) {
      for (let j = i+1; j < dots.length; j++) {
        const dx = dots[i].x - dots[j].x, dy = dots[i].y - dots[j].y;
        const dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < 120) {
          ctx.beginPath(); ctx.moveTo(dots[i].x, dots[i].y); ctx.lineTo(dots[j].x, dots[j].y);
          ctx.strokeStyle = `rgba(30,58,95,${0.06 * (1 - dist/120)})`;
          ctx.lineWidth = 0.5; ctx.stroke();
        }
      }
    }
    requestAnimationFrame(drawGrid);
  }
  drawGrid();
  window.addEventListener('resize', () => { W = c.width = window.innerWidth; H = c.height = window.innerHeight; });
})();
</script>
  </body>
</html>

