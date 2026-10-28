<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';
intl_require_login();

// ── Fetch favorited recipes ──
$favIds = intl_recipe_favorites();
$dbRecipes = [];
if (!empty($favIds)) {
    $placeholders = implode(',', array_fill(0, count($favIds), '?'));
    $dbRecipes = dbGetAll("SELECT * FROM export_recipes WHERE is_active = 1 AND id IN ($placeholders) ORDER BY FIELD(id, $placeholders)", array_merge($favIds, $favIds));
}

// ── Fetch recipe items ──
$recipeIds = array_column($dbRecipes, 'id');
$groupedItems = [];
if (!empty($recipeIds)) {
    $placeholders = implode(',', array_fill(0, count($recipeIds), '?'));
    $allItems = dbGetAll(
        "SELECT eri.*, p.slug AS product_slug FROM export_recipe_items eri LEFT JOIN product p ON eri.product_id = p.id WHERE eri.recipe_id IN ($placeholders) ORDER BY eri.recipe_id, eri.sort_order",
        $recipeIds
    );
    foreach ($allItems as $item) {
        $groupedItems[$item['recipe_id']][] = $item;
    }
}

// ── Build recipes JSON ──
$recipesJson = [];
foreach ($dbRecipes as $r) {
    $items = $groupedItems[$r['id']] ?? [];
    $ingredients = [];
    $steps = [];
    foreach ($items as $it) {
        if ($it['type'] === 'ingredient') {
            $ingredients[] = ['text' => $it['content'], 'product_slug' => $it['product_slug']];
        } else {
            $steps[] = $it['content'];
        }
    }
    $recipesJson[] = [
        'id' => (int)$r['id'],
        'title' => $r['title'],
        'sub' => $r['subtitle'],
        'desc' => $r['description'],
        'level' => $r['level'],
        'time' => (int)$r['time_minutes'],
        'img' => $r['image_url'],
        'ingredients' => $ingredients,
        'steps' => $steps,
    ];
}

$__favRecipeIds = $favIds;
$__isLoggedIn = true;
$__favJson = json_encode($favIds);
$__recipesJson = json_encode($recipesJson);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta content="width=device-width,initial-scale=1" name="viewport">
<title>My Favorites — International Fish Market</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{'brand-blue':'#0369a1','stone-150':'#e8e5e0','stone-250':'#d6d2cb','stone-350':'#b8b2a8','amber-350':'#d9995b'},fontFamily:{display:['Inter','system-ui','sans-serif'],mono:['JetBrains Mono','monospace']}}}}
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/favorites.css?v=<?= urlencode($__ifmAssetVersion('css/favorites.css')) ?>"/>
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;overflow-x:hidden}
body{overflow-x:hidden;font-family:'Inter',system-ui,-apple-system,sans-serif}
.font-display{font-family:'Inter',system-ui,-apple-system,sans-serif;letter-spacing:-0.03em}
.line-clamp-3{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
</style>
</head>
<body>
<div id="root">
  <div class="relative min-h-screen bg-transparent text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip">
    <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
      <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
        <a href="<?= url_for('index') ?>" class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
          <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">INTERNATIONAL FISH MARKET</span>
          <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse"></span>
        </a>
        <div class="flex items-center space-x-4">
          <a href="<?= url_for('recipes') ?>" class="flex items-center space-x-1 font-display text-[10px] tracking-widest uppercase text-stone-500 hover:text-stone-950 transition-all no-underline font-bold">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
            <span>Back to Recipes</span>
          </a>
          <?php require __DIR__ . '/includes/nav_bar.php'; ?>
        </div>
      </div>
    </header>
    <div id="subpage-viewport">
  <div class="min-h-screen bg-stone-100/50 pt-28 pb-24 px-4 md:px-8">
    <div class="max-w-6xl mx-auto space-y-10">
      <div class="border-b border-stone-250 pb-5 space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center space-x-3">
            <div class="p-2 bg-stone-900 text-stone-50 rounded-xl shadow-lg">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div>
              <h1 class="font-display font-black text-2xl text-stone-900 tracking-tight uppercase">My Favorites</h1>
              <p class="text-[10px] text-stone-600 font-mono uppercase tracking-widest mt-0.5">
                <?= count($dbRecipes) ?> bookmarked recipe<?= count($dbRecipes) !== 1 ? 's' : '' ?>
              </p>
            </div>
          </div>
        </div>
      </div>

      <?php if (empty($dbRecipes)): ?>
      <div class="text-center py-20">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#d6d3d1" stroke-width="1.5" class="mx-auto mb-4"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        <p class="text-stone-600 font-mono text-xs uppercase tracking-widest font-bold">No favorites yet</p>
        <p class="text-stone-400 text-[10px] font-mono mt-1">Browse recipes and bookmark your favourites</p>
        <a href="<?= url_for('recipes') ?>" class="inline-block mt-6 px-5 py-2.5 bg-brand-blue text-white rounded-xl text-[10px] font-bold uppercase tracking-widest no-underline hover:opacity-90 transition-all">Explore Recipes</a>
      </div>
      <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($dbRecipes as $i => $r): ?>
        <div onclick="openRecipe(<?= $i ?>)" class="bg-white border border-stone-200/80 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between cursor-pointer group relative">
          <div class="relative h-48 overflow-hidden bg-stone-100">
            <img alt="<?= e($r['title']) ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" referrerpolicy="no-referrer" src="<?= e($r['image_url']) ?>">
            <div class="absolute inset-0 bg-gradient-to-t from-stone-950/40 via-transparent to-transparent"></div>
            <div class="absolute top-4 left-4 flex items-center space-x-1.5">
              <span class="font-mono text-[8px] bg-stone-900/90 backdrop-blur-md text-stone-50 px-2 py-1 rounded-full uppercase tracking-wider font-extrabold flex items-center gap-1 border border-stone-800">
                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#fbbf24"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"/><path d="M20 2v4"/><path d="M22 4h-4"/><circle cx="4" cy="20" r="2"/></svg>
                <span><?= e($r['level']) ?></span>
              </span>
            </div>
            <div class="absolute bottom-4 right-4 font-mono text-[8px] bg-stone-900/90 backdrop-blur-md text-stone-100 px-2 py-1 rounded-full font-bold uppercase tracking-wider">🕐 <?= (int)$r['time_minutes'] ?> mins</div>
            <button onclick="event.stopPropagation();toggleFavIndex(<?= $i ?>,this)" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-stone-900/70 backdrop-blur-md border border-stone-700/50 transition-all duration-200 hover:scale-110 cursor-pointer" title="Remove from favorites">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fav-icon">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
            </button>
          </div>
          <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
            <div class="space-y-1.5">
              <h3 class="font-display font-black text-sm text-stone-900 group-hover:text-amber-600 transition-colors tracking-tight leading-tight"><?= e($r['title']) ?></h3>
              <p class="text-[10px] text-stone-500 font-mono uppercase tracking-wider font-bold"><?= e($r['subtitle']) ?></p>
              <p class="text-[11px] text-stone-600 leading-normal font-sans line-clamp-3"><?= e(mb_substr($r['description'], 0, 180)) ?><?= mb_strlen($r['description']) > 180 ? '...' : '' ?></p>
            </div>
            <div class="pt-2 border-t border-dashed border-stone-150 flex items-center justify-between text-[10px] font-mono text-amber-600 font-bold uppercase tracking-wider">
              <span>View Full Recipe details</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="group-hover:translate-x-1 transition-transform"><path d="m9 18 6-6-6-6"/></svg>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div> <!-- subpage-viewport -->
  </div> <!-- bg-stone-100 wrapper -->
</div> <!-- root -->

<?php if (!empty($dbRecipes)): ?>
<!-- Recipe Modal -->
<div id="recipe-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);overflow-y:auto;padding:80px 20px" onclick="if(event.target===this)closeRecipe()">
  <div style="max-width:640px;margin:0 auto;background:#fafaf9;border-radius:24px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.08)">
    <div id="recipe-modal-img" style="width:100%;aspect-ratio:16/9;object-fit:cover;background:#e7e5e4"></div>
    <div style="padding:28px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
        <div>
          <span id="recipe-modal-level" style="font-family:monospace;font-size:10px;background:#1c1917;color:#fafaf9;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;font-weight:700"></span>
          <h2 id="recipe-modal-title" style="font-family:'Georgia',serif;font-weight:900;font-size:22px;color:#1c1917;margin-top:12px;margin-bottom:4px"></h2>
          <p id="recipe-modal-sub" style="font-family:monospace;font-size:10px;color:#a8a29e;text-transform:uppercase;letter-spacing:1px;font-weight:600"></p>
        </div>
        <div style="display:flex;align-items:center;gap:8px">
          <button id="modal-fav-btn" onclick="event.stopPropagation();toggleFavModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-stone-100 border border-stone-200 transition-all duration-200 hover:scale-110 cursor-pointer" title="Bookmark this recipe">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="modal-fav-icon"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
          </button>
          <button onclick="closeRecipe()" style="background:none;border:none;cursor:pointer;font-size:24px;color:#78716c;padding:4px 8px">&times;</button>
        </div>
      </div>
      <p id="recipe-modal-desc" style="font-size:13px;color:#57534e;line-height:1.7;margin-bottom:20px"></p>
      <div style="border:1px solid #e7e5e4;border-radius:16px;padding:20px;margin-bottom:20px;background:white">
        <h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;margin-bottom:12px;font-weight:700">🥘 Ingredients</h4>
        <ul id="recipe-modal-ingredients" style="list-style:none;padding:0;font-size:13px;color:#44403c"></ul>
      </div>
      <div style="border:1px solid #e7e5e4;border-radius:16px;padding:20px;background:white">
        <h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;margin-bottom:12px;font-weight:700">👨‍🍳 Instructions</h4>
        <ol id="recipe-modal-steps" style="padding-left:20px;font-size:13px;color:#44403c;line-height:1.8"></ol>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:16px;border-top:1px solid #e7e5e4">
        <span id="recipe-modal-time" style="font-family:monospace;font-size:11px;color:#a8a29e;font-weight:600">🕐 </span>
        <button onclick="closeRecipe()" style="padding:8px 24px;background:#1c1917;color:#fafaf9;border:none;border-radius:12px;font-size:11px;font-weight:700;cursor:pointer">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
var recipes = <?= $__recipesJson ?>;
var favorites = <?= $__favJson ?>;
var __modalRecipeIdx = -1;

function openRecipe(i) {
  var r = recipes[i];
  if (!r) return;
  __modalRecipeIdx = i;
  document.getElementById('recipe-modal-img').style.backgroundImage = 'url(' + r.img + ')';
  document.getElementById('recipe-modal-img').style.backgroundSize = 'cover';
  document.getElementById('recipe-modal-img').style.backgroundPosition = 'center';
  document.getElementById('recipe-modal-title').textContent = r.title;
  document.getElementById('recipe-modal-sub').textContent = r.sub;
  document.getElementById('recipe-modal-desc').textContent = r.desc;
  document.getElementById('recipe-modal-level').textContent = r.level;
  document.getElementById('recipe-modal-time').textContent = '🕐 ' + r.time + ' mins';
  var ing = document.getElementById('recipe-modal-ingredients');
  ing.innerHTML = '';
  r.ingredients.forEach(function(item) {
    var li = document.createElement('li');
    li.style.cssText = 'padding:6px 0;border-bottom:1px solid #f5f5f4;display:flex;align-items:center;gap:8px;justify-content:space-between';
    li.innerHTML = '<span><span style="color:#a8a29e">✦</span> ' + item.text + '</span>';
    if (item.product_slug) {
      var btn = document.createElement('a');
      btn.href = 'shop.php?highlight=' + item.product_slug;
      btn.textContent = 'View in Shop';
      btn.style.cssText = 'font-size:10px;font-weight:700;padding:4px 10px;background:#1c1917;color:#fafaf9;border-radius:8px;text-decoration:none;white-space:nowrap;transition:background .15s';
      btn.onmouseover = function(){this.style.background='#44403c'};
      btn.onmouseout = function(){this.style.background='#1c1917'};
      li.appendChild(btn);
    }
    ing.appendChild(li);
  });
  var steps = document.getElementById('recipe-modal-steps');
  steps.innerHTML = '';
  r.steps.forEach(function(step) {
    var li = document.createElement('li');
    li.style.cssText = 'margin-bottom:6px';
    li.textContent = step;
    steps.appendChild(li);
  });
  var mf = document.getElementById('modal-fav-icon');
  if (mf) mf.setAttribute('fill', favorites.indexOf(r.id) !== -1 ? '#fbbf24' : 'none');
  document.getElementById('recipe-modal').style.display = 'block';
  document.body.style.overflow = 'hidden';
}

function closeRecipe() {
  document.getElementById('recipe-modal').style.display = 'none';
  document.body.style.overflow = '';
}

function toggleFavIndex(idx, btn) {
  var r = recipes[idx];
  if (!r) return;
  var wasFav = favorites.indexOf(r.id) !== -1;
  var icon = btn.querySelector('.fav-icon') || btn;
  if (wasFav) {
    favorites.splice(favorites.indexOf(r.id), 1);
    // Animate card removal
    var card = btn.closest('[onclick]');
    if (card) { card.style.transition = 'opacity 0.3s,transform 0.3s'; card.style.opacity = '0'; card.style.transform = 'scale(0.95)'; setTimeout(function(){ card.remove(); }, 350); }
  }
  var mf = document.getElementById('modal-fav-icon');
  if (mf) mf.setAttribute('fill', favorites.indexOf(r.id) !== -1 ? '#fbbf24' : 'none');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', '<?= url_for('recipes') ?>?action=toggle_fav&recipe_id=' + r.id, true);
  xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  xhr.onload = function() {
    if (xhr.status !== 200 && wasFav) { favorites.push(r.id); }
  };
  xhr.send('action=toggle_fav&recipe_id=' + r.id);
}

function toggleFavModal() {
  if (__modalRecipeIdx < 0) return;
  var btn = document.querySelector('[onclick*="toggleFavIndex(' + __modalRecipeIdx + ',this)"]');
  if (btn) toggleFavIndex(__modalRecipeIdx, btn);
  else {
    var r = recipes[__modalRecipeIdx];
    if (!r) return;
    var wasFav = favorites.indexOf(r.id) !== -1;
    var mf = document.getElementById('modal-fav-icon');
    if (mf) mf.setAttribute('fill', wasFav ? 'none' : '#fbbf24');
    if (wasFav) favorites.splice(favorites.indexOf(r.id), 1);
    else favorites.push(r.id);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?= url_for('recipes') ?>?action=toggle_fav&recipe_id=' + r.id, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.send('action=toggle_fav&recipe_id=' + r.id);
  }
}
</script>
<?php endif; ?>
<script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>
</body>
</html>