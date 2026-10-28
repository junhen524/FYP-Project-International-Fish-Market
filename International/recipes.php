<?php
$__ifmBasePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
$__ifmBasePath = $__ifmBasePath === '/' || $__ifmBasePath === '.' ? '' : rtrim($__ifmBasePath, '/');
$__ifmBasePath = $__ifmBasePath === '' ? '/' : $__ifmBasePath . '/';
$__ifmAssetVersion = static function ($relativePath) {
    $absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : (string) time();
};
require_once __DIR__ . '/includes/bootstrap.php';

// ── Check if restaurant user ──
$__isRestaurant = isset($_SESSION['ifm_restaurant_id']);
$__restaurantUser = intl_current_user();

// ── Flash message from redirect ──
$shareMsg = $_SESSION['share_recipe_msg'] ?? '';
$shareMsgType = $_SESSION['share_recipe_type'] ?? '';
unset($_SESSION['share_recipe_msg'], $_SESSION['share_recipe_type']);

// ── Handle Share Recipe POST ──
if ($__isRestaurant && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'share_recipe') {
    // Reset flash message vars before processing
    $shareMsg = '';
    $shareMsgType = '';
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $level = trim($_POST['level'] ?? 'Easy');
    $timeMinutes = (int)($_POST['time_minutes'] ?? 0);
    $imageUrl = '';
    // Handle file upload first
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/recipes/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($ext, $allowed)) {
            $filename = 'recipe_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $filename);
            $imageUrl = 'uploads/recipes/' . $filename;
        }
    }
    // Fall back to URL if no file uploaded
    if ($imageUrl === '') {
        $imageUrl = trim($_POST['image_url'] ?? '');
    }
    $ingredientsJson = $_POST['ingredients_json'] ?? '[]';
    $stepsJson = $_POST['steps_json'] ?? '[]';

    if ($title === '') {
        $shareMsg = 'Recipe title is required.';
        $shareMsgType = 'error';
    } else {
        $rid = (int)$_SESSION['ifm_restaurant_id'];
        dbExecute("INSERT INTO export_recipes (title, subtitle, description, level, time_minutes, image_url, is_active, status, restaurant_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 0, 'pending', ?, NOW(), NOW())",
            [$title, $subtitle, $description, $level, $timeMinutes, $imageUrl, $rid]);
        $recipeId = (int)dbLastInsertId();

        // Insert ingredients
        $ingredients = json_decode($ingredientsJson, true);
        if (is_array($ingredients)) {
            $sort = 0;
            foreach ($ingredients as $ing) {
                $content = trim($ing['content'] ?? '');
                $productId = !empty($ing['product_id']) ? (int)$ing['product_id'] : null;
                if ($content !== '') {
                    dbExecute("INSERT INTO export_recipe_items (recipe_id, type, content, sort_order, product_id) VALUES (?, 'ingredient', ?, ?, ?)",
                        [$recipeId, $content, $sort, $productId]);
                    $sort++;
                }
            }
        }

        // Insert steps
        $steps = json_decode($stepsJson, true);
        if (is_array($steps)) {
            $sort = 0;
            foreach ($steps as $step) {
                $content = trim($step['content'] ?? '');
                if ($content !== '') {
                    dbExecute("INSERT INTO export_recipe_items (recipe_id, type, content, sort_order) VALUES (?, 'step', ?, ?)",
                        [$recipeId, $content, $sort]);
                    $sort++;
                }
            }
        }

        $shareMsg = 'Recipe shared successfully!';
        $shareMsgType = 'success';
        // Redirect to prevent double-submit on refresh
        $_SESSION['share_recipe_msg'] = $shareMsg;
        $_SESSION['share_recipe_type'] = $shareMsgType;
        header('Location: ' . ($_SERVER['REQUEST_URI'] ?? 'recipes.php'));
        exit;
    }
}

// ── Fetch restaurant's own recipes ──
$__restaurantRecipes = [];
$__restaurantRecipesJson = '[]';
if ($__isRestaurant) {
    $rid = (int)$_SESSION['ifm_restaurant_id'];
    $__restaurantRecipes = dbGetAll("SELECT id, title, subtitle, description, level, time_minutes, image_url, status, created_at FROM export_recipes WHERE restaurant_id = ? ORDER BY created_at DESC", [$rid]);
    // Fetch items for restaurant recipes
    $rRecipeIds = array_column($__restaurantRecipes, 'id');
    $rGroupedItems = [];
    if (!empty($rRecipeIds)) {
        $rPlaceholders = implode(',', array_fill(0, count($rRecipeIds), '?'));
        $rAllItems = dbGetAll(
            "SELECT eri.*, p.slug AS product_slug FROM export_recipe_items eri LEFT JOIN product p ON eri.product_id = p.id WHERE eri.recipe_id IN ($rPlaceholders) ORDER BY eri.recipe_id, eri.sort_order",
            $rRecipeIds
        );
        foreach ($rAllItems as $item) {
            $rGroupedItems[$item['recipe_id']][] = $item;
        }
    }
    // Build JSON for restaurant recipes
    $rJson = [];
    foreach ($__restaurantRecipes as $r) {
        $items = $rGroupedItems[$r['id']] ?? [];
        $ingredients = [];
        $steps = [];
        foreach ($items as $it) {
            if ($it['type'] === 'ingredient') {
                $ingredients[] = ['text' => $it['content'], 'product_slug' => $it['product_slug']];
            } else {
                $steps[] = $it['content'];
            }
        }
        $imgUrl = $r['image_url'];
        // Fix relative URL paths for uploaded images
        if ($imgUrl !== '' && $imgUrl[0] !== '/' && !preg_match('#^https?://#i', $imgUrl)) {
            $imgUrl = rtrim($__ifmBasePath, '/') . '/' . $imgUrl;
        }
        $rJson[] = [
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'sub' => $r['subtitle'],
            'desc' => $r['description'],
            'level' => $r['level'],
            'time' => (int)$r['time_minutes'],
            'img' => $imgUrl,
            'status' => $r['status'],
            'created' => $r['created_at'],
            'ingredients' => $ingredients,
            'steps' => $steps,
        ];
    }
    $__restaurantRecipesJson = json_encode($rJson);
}

// ── Fetch shop products for ingredient matching ──
$shopProducts = dbGetAll("SELECT id, slug, name, export_price, unit, image_url FROM product WHERE is_active = TRUE AND LOWER(category) IN ('fish', 'mollusc', 'crustacean', 'cephalopod', 'seafood') ORDER BY name");
$productMap = []; // ingredient keyword => product slug
foreach ($shopProducts as $p) {
    $name = strtolower($p['name']);
    $words = explode(' ', $name);
    foreach ($words as $w) {
        $w = trim(preg_replace('/[^a-z0-9]/', '', $w));
        if (strlen($w) > 2 && !isset($productMap[$w])) {
            $productMap[$w] = $p['slug'];
        }
    }
}

// ── Fetch recipes from DB ──
$dbRecipes = dbGetAll("SELECT * FROM export_recipes WHERE is_active = 1 AND status = 'approved' ORDER BY id");
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

// ── Build recipes JSON for frontend ──
$recipesJson = [];
foreach ($dbRecipes as $r) {
    $items = $groupedItems[$r['id']] ?? [];
    $ingredients = [];
    $steps = [];
    foreach ($items as $it) {
        if ($it['type'] === 'ingredient') {
            $ingredients[] = [
                'text' => $it['content'],
                'product_slug' => $it['product_slug'],
            ];
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
        'img' => (function() use ($r, $__ifmBasePath) {
            $u = $r['image_url'];
            if ($u !== '' && $u[0] !== '/' && !preg_match('#^https?://#i', $u)) {
                return rtrim($__ifmBasePath, '/') . '/' . $u;
            }
            return $u;
        })(),
        'ingredients' => $ingredients,
        'steps' => $steps,
    ];
}

// ── Recipe Favorites ──
$__favRecipeIds = intl_recipe_favorites();
$__favJson = json_encode($__favRecipeIds);
$__isLoggedIn = intl_current_user() !== null;

// ── Handle AJAX toggle favorite ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'toggle_fav') {
    $rid = (int)($_POST['recipe_id'] ?? 0);
    if ($rid > 0) {
        intl_toggle_recipe_favorite($rid);
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false]);
    exit;
}
?><!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>
      Today's Catch Kitchen
    </title>
    <link rel="stylesheet" href="css/recipes.css?v=<?= urlencode($__ifmAssetVersion('css/recipes.css')) ?>" />
  </head>
  <body style="margin: 0; overflow-x: hidden;">
    <div id="root">
      <div class="relative min-h-screen bg-bg-dark text-slate-800 selection:bg-brand-blue/30 selection:text-white overflow-x-clip" id="alche-studio-replica-root">
        <header id="main-app-header" class="fixed top-0 left-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
          <div class="max-w-7xl mx-auto px-6 md:px-12 flex justify-between items-center">
            <a href="<?= $__ifmBasePath ?>" class="cursor-pointer flex items-center space-x-2 group" id="brand-logo-trigger">
              <span class="font-display font-bold text-base md:text-lg tracking-[0.25em] text-slate-950">
                INTERNATIONAL FISH MARKET
              </span>
              <span class="w-1.5 h-1.5 rounded-full bg-brand-blue animate-pulse">
              </span>
            </a>
            <?php require __DIR__ . '/includes/nav_bar.php'; ?>
          </div>
        </header>
        <div id="subpage-viewport">
          <div class="min-h-screen bg-stone-100/50 pt-28 pb-24 px-4 md:px-8">
            <div class="max-w-6xl mx-auto space-y-10">
              <div class="border-b border-stone-250 pb-5 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                  <div class="flex items-center space-x-3">
                    <div class="p-2 bg-stone-900 text-stone-50 rounded-xl shadow-lg">
                      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chef-hat" aria-hidden="true">
                        <path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z">
                        </path>
                        <path d="M6 17h12">
                        </path>
                      </svg>
                    </div>
                    <div>
                      <h1 class="font-display font-black text-2xl text-stone-900 tracking-tight uppercase">
                        Today's Catch Kitchen
                      </h1>
                      <p class="text-[10px] text-stone-400 font-mono uppercase tracking-widest mt-0.5">
                        Share recipes &amp; discover what to cook today with fresh seafood
                      </p>
                    </div>
                  </div>
                  <?php if ($__isRestaurant): ?>
                  <div style="display:flex;gap:8px">
                  <button onclick="document.getElementById('my-recipes-modal').style.display='block';document.body.style.overflow='hidden'" class="px-4 py-2.5 bg-brand-blue text-white rounded-xl text-[10px] font-bold uppercase tracking-widest no-underline hover:opacity-90 transition-all cursor-pointer border-none flex items-center gap-2 shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
                    My Recipes (<?= count($__restaurantRecipes) ?>)
                  </button>
                  <button onclick="document.getElementById('share-recipe-modal').style.display='block';document.body.style.overflow='hidden'" class="px-4 py-2.5 bg-stone-900 text-stone-50 rounded-xl text-[10px] font-bold uppercase tracking-widest no-underline hover:bg-stone-800 transition-all cursor-pointer border-none flex items-center gap-2 shrink-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                    Share Recipe
                  </button>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($dbRecipes as $i => $r): ?>
                <div onclick="openRecipe(<?= $i ?>)" class="bg-white border border-stone-200/80 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between cursor-pointer group relative">
                  <div class="relative h-48 overflow-hidden bg-stone-100">
                    <img alt="<?= e($r['title']) ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" referrerpolicy="no-referrer" src="<?php
                        $cardImg = $r['image_url'];
                        if ($cardImg !== '' && $cardImg[0] !== '/' && !preg_match('#^https?://#i', $cardImg)) {
                            echo e(rtrim($__ifmBasePath, '/') . '/' . $cardImg);
                        } else {
                            echo e($cardImg);
                        }
                    ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-stone-950/40 via-transparent to-transparent">
                    </div>
                    <div class="absolute top-4 left-4 flex items-center space-x-1.5">
                      <span class="font-mono text-[8px] bg-stone-900/90 backdrop-blur-md text-stone-50 px-2 py-1 rounded-full uppercase tracking-wider font-extrabold flex items-center gap-1 border border-stone-800">
                        <svg xmlns="http://www.w3.org/2000/svg" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles text-amber-400" aria-hidden="true">
                          <path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                          </path>
                          <path d="M20 2v4">
                          </path>
                          <path d="M22 4h-4">
                          </path>
                          <circle cx="4" cy="20" r="2">
                          </circle>
                        </svg>
                        <span>
                          <?= e($r['level']) ?>
                        </span>
                      </span>
                    </div>
                    <div class="absolute bottom-4 right-4 font-mono text-[8px] bg-stone-900/90 backdrop-blur-md text-stone-100 px-2 py-1 rounded-full font-bold uppercase tracking-wider">
                      🕐 <?= (int)$r['time_minutes'] ?> mins
                    </div>
                    <?php if ($__isLoggedIn): ?>
                    <button onclick="event.stopPropagation();toggleFav(<?= $i ?>,this)" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-stone-900/70 backdrop-blur-md border border-stone-700/50 transition-all duration-200 hover:scale-110 cursor-pointer" title="Bookmark this recipe">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="<?= in_array((int)$r['id'], $__favRecipeIds) ? '#fbbf24' : 'none' ?>" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="fav-icon">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                      </svg>
                    </button>
                    <?php endif; ?>
                  </div>
                  <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                    <div class="space-y-1.5">
                      <h3 class="font-display font-black text-sm text-stone-900 group-hover:text-amber-600 transition-colors tracking-tight leading-tight">
                        <?= e($r['title']) ?>
                      </h3>
                      <p class="text-[10px] text-stone-400 font-mono uppercase tracking-wider font-bold">
                        <?= e($r['subtitle']) ?>
                      </p>
                      <p class="text-[11px] text-stone-500 leading-normal font-sans line-clamp-3">
                        <?= e(mb_substr($r['description'], 0, 180)) ?><?= mb_strlen($r['description']) > 180 ? '...' : '' ?>
                      </p>
                    </div>
                    <div class="pt-2 border-t border-dashed border-stone-150 flex items-center justify-between text-[10px] font-mono text-amber-600 font-bold uppercase tracking-wider">
                      <span>
                        View Full Recipe details
                      </span>
                      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right group-hover:translate-x-1 transition-transform" aria-hidden="true">
                        <path d="m9 18 6-6-6-6">
                        </path>
                      </svg>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
      window.__APP_ROUTE__ = "/recipes.php";
      window.__APP_BASE_PATH__ = <?= json_encode($__ifmBasePath) ?>;
      window.__ADMIN_PHP_SESSION__ = false;
      window.__PRODUCT_MAP__ = <?= json_encode($productMap) ?>;
      window.__SHOP_PRODUCTS__ = <?= json_encode($shopProducts) ?>;
      window.__RECIPES__ = <?= json_encode($recipesJson) ?>;
      window.__FAVORITES__ = <?= $__favJson ?>;
      window.__IS_LOGGED_IN__ = <?= $__isLoggedIn ? 'true' : 'false' ?>;
      window.__IS_RESTAURANT__ = <?= $__isRestaurant ? 'true' : 'false' ?>;
    </script>
    <script src="js/app.js?v=<?= urlencode($__ifmAssetVersion('js/app.js')) ?>"></script>

    <!-- Recipe Detail Modal -->
    <div id="recipe-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);overflow-y:auto;padding:80px 20px" onclick="if(event.target===this)closeRecipe()">
      <div style="max-width:640px;margin:0 auto;background:#fafaf9;border-radius:24px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.08)">
        <div id="recipe-modal-img-wrap" style="width:100%;aspect-ratio:16/9;overflow:hidden;background:#e7e5e4">
          <img id="recipe-modal-img" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:block" referrerpolicy="no-referrer">
        </div>
        <div style="padding:28px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
            <div>
              <span id="recipe-modal-level" style="font-family:monospace;font-size:10px;background:#1c1917;color:#fafaf9;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;font-weight:700"></span>
              <h2 id="recipe-modal-title" style="font-family:'Georgia',serif;font-weight:900;font-size:22px;color:#1c1917;margin-top:12px;margin-bottom:4px"></h2>
              <p id="recipe-modal-sub" style="font-family:monospace;font-size:10px;color:#a8a29e;text-transform:uppercase;letter-spacing:1px;font-weight:600"></p>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <?php if ($__isLoggedIn): ?>
              <button id="modal-fav-btn" onclick="event.stopPropagation();toggleFavModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-stone-100 border border-stone-200 transition-all duration-200 hover:scale-110 cursor-pointer" title="Bookmark this recipe">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="modal-fav-icon">
                  <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                </svg>
              </button>
              <?php endif; ?>
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
    var recipes = window.__RECIPES__ || [];
    var favorites = window.__FAVORITES__ || [];
    var __modalRecipeIdx = -1;

    function openRecipe(i) {
      var r = recipes[i];
      if (!r) return;
      __modalRecipeIdx = i;
      document.getElementById('recipe-modal-img').src = r.img || '';
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
      r.steps.forEach(function(step, idx) {
        var li = document.createElement('li');
        li.style.cssText = 'margin-bottom:6px';
        li.textContent = step;
        steps.appendChild(li);
      });
      // Update modal fav button
      var mf = document.getElementById('modal-fav-icon');
      if (mf) mf.setAttribute('fill', favorites.indexOf(r.id) !== -1 ? '#fbbf24' : 'none');
      document.getElementById('recipe-modal').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeRecipe() {
      document.getElementById('recipe-modal').style.display = 'none';
      document.body.style.overflow = '';
    }

    function toggleFav(idx, btn) {
      var r = recipes[idx];
      if (!r) return;
      var wasFav = favorites.indexOf(r.id) !== -1;
      var icon = btn.querySelector('.fav-icon') || btn;
      // Optimistic update
      if (wasFav) {
        favorites.splice(favorites.indexOf(r.id), 1);
        icon.setAttribute('fill', 'none');
      } else {
        favorites.push(r.id);
        icon.setAttribute('fill', '#fbbf24');
      }
      // Sync modal icon
      var mf = document.getElementById('modal-fav-icon');
      if (mf) mf.setAttribute('fill', favorites.indexOf(r.id) !== -1 ? '#fbbf24' : 'none');
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'recipes.php?action=toggle_fav&recipe_id=' + r.id, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function() {
        if (xhr.status !== 200) {
          // Revert on error
          if (wasFav) { favorites.push(r.id); icon.setAttribute('fill', '#fbbf24'); }
          else { favorites.splice(favorites.indexOf(r.id), 1); icon.setAttribute('fill', 'none'); }
          if (mf) mf.setAttribute('fill', favorites.indexOf(r.id) !== -1 ? '#fbbf24' : 'none');
        }
      };
      xhr.send('action=toggle_fav&recipe_id=' + r.id);
    }

    function toggleFavModal() {
      if (__modalRecipeIdx < 0) return;
      var r = recipes[__modalRecipeIdx];
      if (!r) return;
      var wasFav = favorites.indexOf(r.id) !== -1;
      // Update card button icon
      document.querySelectorAll('.fav-icon').forEach(function(icon) {
        var btn = icon.closest('button');
        if (btn && btn.getAttribute('onclick') && btn.getAttribute('onclick').indexOf('toggleFav(' + __modalRecipeIdx) !== -1) {
          icon.setAttribute('fill', wasFav ? 'none' : '#fbbf24');
        }
      });
      // Update modal icon
      var mf = document.getElementById('modal-fav-icon');
      if (mf) mf.setAttribute('fill', wasFav ? 'none' : '#fbbf24');
      // Toggle on server
      if (wasFav) favorites.splice(favorites.indexOf(r.id), 1);
      else favorites.push(r.id);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'recipes.php?action=toggle_fav&recipe_id=' + r.id, true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.send('action=toggle_fav&recipe_id=' + r.id);
    }
    </script>

    <!-- Share Recipe Modal (Restaurant only) -->
    <div id="share-recipe-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);overflow-y:auto;padding:40px 20px" onclick="if(event.target===this)closeShareRecipe()">
      <div style="max-width:640px;margin:0 auto;background:#fafaf9;border-radius:20px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.08)">
        <div style="padding:28px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
            <div>
              <div style="font-family:monospace;font-size:10px;background:#1c1917;color:#fafaf9;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:inline-block">🍳 Share a Recipe</div>
              <p style="font-size:11px;color:#78716c;margin-top:8px;font-family:monospace">Fill in the details and add your seafood ingredients</p>
            </div>
            <button onclick="closeShareRecipe()" style="background:none;border:none;cursor:pointer;font-size:24px;color:#78716c;padding:4px 8px">&times;</button>
          </div>

          <form method="post" id="share-recipe-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="share_recipe">
            <input type="hidden" name="ingredients_json" id="share-ingredients-json" value="[]">
            <input type="hidden" name="steps_json" id="share-steps-json" value="[]">

            <!-- Basic Info -->
            <div style="margin-bottom:16px">
              <label style="font-family:monospace;font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:block;margin-bottom:4px">Recipe Title *</label>
              <input type="text" name="title" required class="share-input" placeholder="e.g. Grilled Red Snapper" style="width:100%;font-family:monospace;font-size:13px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;outline:none;background:white">
            </div>
            <div style="margin-bottom:16px">
              <label style="font-family:monospace;font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:block;margin-bottom:4px">Subtitle</label>
              <input type="text" name="subtitle" class="share-input" placeholder="e.g. A smoky citrus twist" style="width:100%;font-family:monospace;font-size:13px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;outline:none;background:white">
            </div>
            <div style="margin-bottom:16px">
              <label style="font-family:monospace;font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:block;margin-bottom:4px">Description</label>
              <textarea name="description" rows="3" class="share-input" placeholder="Describe your recipe..." style="width:100%;font-family:monospace;font-size:13px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;outline:none;background:white;resize:vertical"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
              <div>
                <label style="font-family:monospace;font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:block;margin-bottom:4px">Level</label>
                <select name="level" class="share-input" style="width:100%;font-family:monospace;font-size:13px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;outline:none;background:white">
                  <option value="Easy">Easy</option>
                  <option value="Medium">Medium</option>
                  <option value="Hard">Hard</option>
                </select>
              </div>
              <div>
                <label style="font-family:monospace;font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:block;margin-bottom:4px">Time (minutes)</label>
                <input type="number" name="time_minutes" value="30" min="1" class="share-input" style="width:100%;font-family:monospace;font-size:13px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;outline:none;background:white">
              </div>
            </div>

            <div style="margin-bottom:20px">
              <label style="font-family:monospace;font-size:10px;color:#78716c;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:block;margin-bottom:4px">Image URL</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input type="url" name="image_url" id="share-image-url" class="share-input" placeholder="https://example.com/recipe.jpg" style="flex:1;font-family:monospace;font-size:13px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;outline:none;background:white" oninput="previewShareImage()">
                <label for="share-image-upload" style="padding:10px 14px;background:#1c1917;color:#fafaf9;border-radius:10px;font-size:10px;cursor:pointer;white-space:nowrap;font-family:monospace;font-weight:700;display:flex;align-items:center;gap:6px">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                  Upload
                </label>
                <input type="file" id="share-image-upload" name="image_file" accept="image/*" style="display:none" onchange="uploadShareImage(this)">
              </div>
              <div id="share-image-preview" style="display:none;margin-top:10px;border-radius:12px;overflow:hidden;border:1px solid #e7e5e4;position:relative">
                <img id="share-image-preview-img" alt="Preview" style="width:100%;max-height:180px;object-fit:cover;display:block">
                <button type="button" onclick="clearShareImage()" style="position:absolute;top:8px;right:8px;background:rgba(0,0,0,0.6);color:white;border:none;border-radius:8px;width:28px;height:28px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center">&times;</button>
              </div>
            </div>

            <!-- Ingredients -->
            <div style="border:1px solid #e7e5e4;border-radius:14px;padding:18px;margin-bottom:16px;background:white">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;font-weight:700;margin:0">🥘 Ingredients</h4>
                <button type="button" onclick="addIngredient()" style="padding:4px 12px;background:#1c1917;color:#fafaf9;border:none;border-radius:8px;font-size:9px;font-weight:700;cursor:pointer;font-family:monospace">+ Add</button>
              </div>
              <div id="share-ingredients-list"></div>
            </div>

            <!-- Steps -->
            <div style="border:1px solid #e7e5e4;border-radius:14px;padding:18px;margin-bottom:20px;background:white">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;font-weight:700;margin:0">👨‍🍳 Instructions</h4>
                <button type="button" onclick="addStep()" style="padding:4px 12px;background:#1c1917;color:#fafaf9;border:none;border-radius:8px;font-size:9px;font-weight:700;cursor:pointer;font-family:monospace">+ Add</button>
              </div>
              <div id="share-steps-list"></div>
            </div>

            <button type="submit" style="width:100%;padding:12px;background:#1c1917;color:#fafaf9;border:none;border-radius:12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;cursor:pointer;font-family:monospace">Share Recipe</button>
          </form>
        </div>
      </div>
    </div>

    <!-- My Recipes Modal (Restaurant only) -->
    <div id="my-recipes-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(6px);overflow-y:auto;padding:80px 20px" onclick="if(event.target===this)closeMyRecipes()">
      <div style="max-width:560px;margin:0 auto;background:#fafaf9;border-radius:24px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.25)">
        <div style="padding:28px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
            <div>
              <div style="font-family:monospace;font-size:10px;background:#92400e;color:#fef3c7;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;font-weight:700;display:inline-block">🍳 My Recipes</div>
              <p style="font-size:11px;color:#78716c;margin-top:6px;font-family:monospace">Recipes you've shared — check their approval status</p>
            </div>
            <button onclick="closeMyRecipes()" style="background:none;border:none;cursor:pointer;font-size:24px;color:#78716c;padding:4px 8px">&times;</button>
          </div>
          <div id="my-recipes-list">
            <?php if ($__restaurantRecipes): ?>
            <div class="space-y-3">
              <?php $rrIdx = 0; foreach ($__restaurantRecipes as $mr): ?>
              <?php
                $statusColors = [
                    'pending' => 'background:#fef3c7;color:#92400e',
                    'approved' => 'background:#d1fae5;color:#065f46',
                    'rejected' => 'background:#fee2e2;color:#991b1b',
                ];
                $statusLabel = $mr['status'] ?? 'pending';
                $color = $statusColors[$statusLabel] ?? $statusColors['pending'];
              ?>
              <div onclick="openRestaurantRecipe(<?= $rrIdx ?>)" style="border:1px solid #e7e5e4;border-radius:14px;padding:14px;background:white;cursor:pointer;transition:box-shadow .2s" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.06)'" onmouseout="this.style.boxShadow=''">
                <div style="display:flex;gap:12px;align-items:flex-start">
                  <?php
                    $thumbUrl = $mr['image_url'];
                    if ($thumbUrl !== '' && $thumbUrl[0] !== '/' && !preg_match('#^https?://#i', $thumbUrl)) {
                        $thumbUrl = rtrim($__ifmBasePath, '/') . '/' . $thumbUrl;
                    }
                  ?>
                  <?php if ($thumbUrl): ?>
                  <img src="<?= e($thumbUrl) ?>" alt="" style="width:56px;height:56px;border-radius:10px;object-fit:cover;flex-shrink:0;background:#e7e5e4" referrerpolicy="no-referrer">
                  <?php endif; ?>
                  <div style="min-width:0;flex:1">
                    <h4 style="font-family:Georgia,serif;font-weight:900;font-size:15px;color:#1c1917;margin:0 0 4px"><?= e($mr['title']) ?></h4>
                    <?php if ($mr['subtitle']): ?><p style="font-family:monospace;font-size:9px;color:#78716c;text-transform:uppercase;letter-spacing:0.5px;margin:0 0 6px"><?= e($mr['subtitle']) ?></p><?php endif; ?>
                    <div style="display:flex;align-items:center;gap:8px">
                      <span style="font-family:monospace;font-size:8px;background:#f5f5f4;color:#44403c;padding:2px 8px;border-radius:10px;font-weight:700">🕐 <?= (int)$mr['time_minutes'] ?> mins</span>
                      <span style="font-family:monospace;font-size:9px;color:#78716c"><?= e($mr['created_at']) ?></span>
                    </div>
                  </div>
                  <span style="font-family:monospace;font-size:9px;font-weight:700;text-transform:uppercase;padding:4px 10px;border-radius:12px;letter-spacing:0.5px;white-space:nowrap;<?= $color ?>"><?= e($statusLabel) ?></span>
                </div>
              </div>
              <?php $rrIdx++; endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:32px 20px">
              <p style="font-family:Georgia,serif;font-weight:700;font-size:16px;color:#a8a29e;margin:0 0 6px">No recipes yet</p>
              <p style="font-family:monospace;font-size:10px;color:#d6d3d1">Click "Share Recipe" above to submit your first recipe.</p>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- My Recipes Detail Modal -->
    <div id="restaurant-recipe-detail" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);overflow-y:auto;padding:80px 20px" onclick="if(event.target===this)closeRestaurantRecipeDetail()">
      <div style="max-width:640px;margin:0 auto;background:#fafaf9;border-radius:24px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.2);border:1px solid rgba(255,255,255,0.08)">
        <div id="rr-modal-img-wrap" style="width:100%;aspect-ratio:16/9;overflow:hidden;background:#e7e5e4">
          <img id="rr-modal-img" src="" alt="" style="width:100%;height:100%;object-fit:cover;display:block" referrerpolicy="no-referrer">
        </div>
        <div style="padding:28px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
            <div>
              <span id="rr-modal-level" style="font-family:monospace;font-size:10px;background:#1c1917;color:#fafaf9;padding:4px 12px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;font-weight:700"></span>
              <span id="rr-modal-status" style="font-family:monospace;font-size:9px;margin-left:6px;padding:4px 10px;border-radius:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px"></span>
              <h2 id="rr-modal-title" style="font-family:'Georgia',serif;font-weight:900;font-size:22px;color:#1c1917;margin-top:12px;margin-bottom:4px"></h2>
              <p id="rr-modal-sub" style="font-family:monospace;font-size:10px;color:#a8a29e;text-transform:uppercase;letter-spacing:1px;font-weight:600"></p>
            </div>
            <button onclick="closeRestaurantRecipeDetail()" style="background:none;border:none;cursor:pointer;font-size:24px;color:#78716c;padding:4px 8px">&times;</button>
          </div>
          <p id="rr-modal-desc" style="font-size:13px;color:#57534e;line-height:1.7;margin-bottom:20px"></p>
          <div style="border:1px solid #e7e5e4;border-radius:16px;padding:20px;margin-bottom:20px;background:white">
            <h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;margin-bottom:12px;font-weight:700">🥘 Ingredients</h4>
            <ul id="rr-modal-ingredients" style="list-style:none;padding:0;font-size:13px;color:#44403c"></ul>
          </div>
          <div style="border:1px solid #e7e5e4;border-radius:16px;padding:20px;background:white">
            <h4 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#78716c;margin-bottom:12px;font-weight:700">👨‍🍳 Instructions</h4>
            <ol id="rr-modal-steps" style="padding-left:20px;font-size:13px;color:#44403c;line-height:1.8"></ol>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;padding-top:16px;border-top:1px solid #e7e5e4">
            <span id="rr-modal-time" style="font-family:monospace;font-size:11px;color:#a8a29e;font-weight:600">🕐 </span>
            <button onclick="closeRestaurantRecipeDetail()" style="padding:8px 24px;background:#1c1917;color:#fafaf9;border:none;border-radius:12px;font-size:11px;font-weight:700;cursor:pointer">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Seafood Product Picker Modal -->
    <div id="product-picker-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:10000;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);overflow-y:auto;padding:60px 20px" onclick="if(event.target===this)closeProductPicker()">
      <div style="max-width:480px;margin:0 auto;background:#fafaf9;border-radius:20px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,0.25)">
        <div style="padding:24px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h3 style="font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#1c1917;font-weight:700;margin:0">🐟 Select Seafood</h3>
            <button onclick="closeProductPicker()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#78716c">&times;</button>
          </div>
          <input type="text" id="product-picker-search" oninput="filterProducts()" placeholder="Search seafood..." style="width:100%;font-family:monospace;font-size:12px;border:1.5px solid #d6d2cb;border-radius:10px;padding:10px 14px;outline:none;background:white;margin-bottom:12px">
          <div id="product-picker-list" style="max-height:320px;overflow-y:auto"></div>
        </div>
      </div>
    </div>

    <style>
    .share-input:focus { border-color:#0d9488 !important; box-shadow:0 0 0 3px rgba(13,148,136,0.1) !important; }
    .share-ing-row { display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:8px;background:#f5f5f4;border-radius:8px; }
    .share-step-row { display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;padding:8px;background:#f5f5f4;border-radius:8px; }
    .share-ing-row input[type="text"] { flex:1;font-family:monospace;font-size:11px;border:1px solid #d6d2cb;border-radius:6px;padding:6px 10px;outline:none;background:white; }
    .share-step-row textarea { flex:1;font-family:monospace;font-size:11px;border:1px solid #d6d2cb;border-radius:6px;padding:6px 10px;outline:none;background:white;resize:vertical;min-height:36px; }
    .share-ing-row .pick-btn { font-size:8px;font-weight:700;padding:4px 8px;background:#0d9488;color:white;border:none;border-radius:6px;cursor:pointer;white-space:nowrap;font-family:monospace; }
    .share-ing-row .pick-btn:hover { background:#0f766e; }
    .share-ing-row .product-tag { font-size:8px;background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:4px;font-weight:600;font-family:monospace;white-space:nowrap; }
    .prod-pick-item { display:flex;align-items:center;justify-content:space-between;padding:10px;border-bottom:1px solid #e7e5e4;cursor:pointer;transition:background .15s; }
    .prod-pick-item:hover { background:#f5f5f4; }
    .prod-pick-item:last-child { border-bottom:none; }
    .toast{position:fixed;top:24px;right:24px;z-index:99999;padding:14px 20px;border-radius:12px;font-size:13px;font-weight:600;font-family:'Inter',sans-serif;box-shadow:0 8px 30px rgba(0,0,0,0.12);max-width:400px;animation:toastIn .3s ease;cursor:pointer}
    .toast-success{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .toast-error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
    @keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
    @keyframes toastOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}
    </style>

    <script>
    var shareProducts = window.__SHOP_PRODUCTS__ || [];

    // ── Share Recipe Functions ──
    function closeShareRecipe() {
      document.getElementById('share-recipe-modal').style.display = 'none';
      document.body.style.overflow = '';
    }

    // ── Image preview / upload ──
    function previewShareImage() {
      var url = document.getElementById('share-image-url').value.trim();
      var preview = document.getElementById('share-image-preview');
      var img = document.getElementById('share-image-preview-img');
      if (url) {
        preview.style.display = 'block';
        img.src = url;
      } else {
        preview.style.display = 'none';
        img.src = '';
      }
    }

    function uploadShareImage(input) {
      var file = input.files[0];
      if (!file) return;
      var reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('share-image-url').value = '';
        var preview = document.getElementById('share-image-preview');
        var img = document.getElementById('share-image-preview-img');
        preview.style.display = 'block';
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }

    function clearShareImage() {
      document.getElementById('share-image-url').value = '';
      document.getElementById('share-image-upload').value = '';
      document.getElementById('share-image-preview').style.display = 'none';
      document.getElementById('share-image-preview-img').src = '';
    }

    var _ingPickIdx = null;

    function closeMyRecipes() {
      document.getElementById('my-recipes-modal').style.display = 'none';
      document.body.style.overflow = '';
    }

    var __restaurantRecipes = <?= $__restaurantRecipesJson ?>;

    function openRestaurantRecipe(i) {
      var r = __restaurantRecipes[i];
      if (!r) return;
      document.getElementById('rr-modal-img').src = r.img || '';
      document.getElementById('rr-modal-title').textContent = r.title;
      document.getElementById('rr-modal-sub').textContent = r.sub || '';
      document.getElementById('rr-modal-desc').textContent = r.desc || '';
      document.getElementById('rr-modal-level').textContent = r.level;
      document.getElementById('rr-modal-time').textContent = '🕐 ' + r.time + ' mins';

      // Status badge
      var statusEl = document.getElementById('rr-modal-status');
      var statusColors = { pending: 'background:#fef3c7;color:#92400e', approved: 'background:#d1fae5;color:#065f46', rejected: 'background:#fee2e2;color:#991b1b' };
      var sc = statusColors[r.status] || statusColors.pending;
      statusEl.style.cssText = 'font-family:monospace;font-size:9px;margin-left:6px;padding:4px 10px;border-radius:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;' + sc;
      statusEl.textContent = r.status;

      // Ingredients
      var ing = document.getElementById('rr-modal-ingredients');
      ing.innerHTML = '';
      (r.ingredients || []).forEach(function(item) {
        var li = document.createElement('li');
        li.style.cssText = 'padding:6px 0;border-bottom:1px solid #f5f5f4;display:flex;align-items:center;gap:8px;justify-content:space-between';
        li.innerHTML = '<span><span style="color:#a8a29e">✦</span> ' + item.text + '</span>';
        if (item.product_slug) {
          var btn = document.createElement('a');
          btn.href = 'shop.php?highlight=' + item.product_slug;
          btn.textContent = 'View in Shop';
          btn.style.cssText = 'font-size:10px;font-weight:700;padding:4px 10px;background:#1c1917;color:#fafaf9;border-radius:8px;text-decoration:none;white-space:nowrap';
          btn.onmouseover = function(){this.style.background='#44403c'};
          btn.onmouseout = function(){this.style.background='#1c1917'};
          li.appendChild(btn);
        }
        ing.appendChild(li);
      });

      // Steps
      var steps = document.getElementById('rr-modal-steps');
      steps.innerHTML = '';
      (r.steps || []).forEach(function(step) {
        var li = document.createElement('li');
        li.style.cssText = 'margin-bottom:6px';
        li.textContent = step;
        steps.appendChild(li);
      });

      document.getElementById('restaurant-recipe-detail').style.display = 'block';
      document.body.style.overflow = 'hidden';
    }

    function closeRestaurantRecipeDetail() {
      document.getElementById('restaurant-recipe-detail').style.display = 'none';
      document.body.style.overflow = '';
    }

    var _ingPickIdx = null;

    function addIngredient(prefill) {
      var list = document.getElementById('share-ingredients-list');
      var idx = list.children.length;
      var row = document.createElement('div');
      row.className = 'share-ing-row';
      row.id = 'share-ing-' + idx;
      var productTag = prefill && prefill.product_name ? '<span class="product-tag">' + prefill.product_name + '</span>' : '';
      row.innerHTML =
        '<input type="text" id="share-ing-text-' + idx + '" placeholder="e.g. 500g red snapper" value="' + (prefill ? (prefill.text || '') : '') + '">' +
        '<input type="hidden" id="share-ing-pid-' + idx + '" value="' + (prefill ? (prefill.product_id || '') : '') + '">' +
        '<input type="hidden" id="share-ing-pname-' + idx + '" value="' + (prefill ? (prefill.product_name || '') : '') + '">' +
        '<div id="share-ing-tag-' + idx + '" style="display:' + (productTag ? 'block' : 'none') + '">' + productTag + '</div>' +
        '<button type="button" class="pick-btn" onclick="openProductPicker(' + idx + ')">🐟 Pick</button>' +
        '<button type="button" onclick="this.parentElement.remove();updateShareJson()" style="font-size:14px;color:#ef4444;background:none;border:none;cursor:pointer;padding:0 2px">&times;</button>';
      list.appendChild(row);
      updateShareJson();
    }

    function addStep(prefill) {
      var list = document.getElementById('share-steps-list');
      var idx = list.children.length;
      var row = document.createElement('div');
      row.className = 'share-step-row';
      row.innerHTML =
        '<span style="font-family:monospace;font-size:11px;font-weight:700;color:#78716c;min-width:20px;padding-top:6px">' + (idx + 1) + '.</span>' +
        '<textarea rows="2" placeholder="Step description..." oninput="updateShareJson()">' + (prefill || '') + '</textarea>' +
        '<button type="button" onclick="this.parentElement.remove();updateShareJson()" style="font-size:14px;color:#ef4444;background:none;border:none;cursor:pointer;padding:0 2px;margin-top:4px">&times;</button>';
      list.appendChild(row);
      updateShareJson();
    }

    function updateShareJson() {
      var ingredients = [];
      document.querySelectorAll('#share-ingredients-list .share-ing-row').forEach(function(row) {
        var textInput = row.querySelector('input[type="text"]');
        var pidInput = row.querySelector('input[id^="share-ing-pid-"]');
        var pnameInput = row.querySelector('input[id^="share-ing-pname-"]');
        if (textInput && textInput.value.trim()) {
          ingredients.push({
            content: textInput.value.trim(),
            product_id: pidInput ? pidInput.value : '',
            product_name: pnameInput ? pnameInput.value : ''
          });
        }
      });
      document.getElementById('share-ingredients-json').value = JSON.stringify(ingredients);

      var steps = [];
      document.querySelectorAll('#share-steps-list .share-step-row textarea').forEach(function(ta) {
        if (ta.value.trim()) steps.push({ content: ta.value.trim() });
      });
      document.getElementById('share-steps-json').value = JSON.stringify(steps);
    }

    // ── Product Picker ──
    function openProductPicker(ingIdx) {
      _ingPickIdx = ingIdx;
      renderProductList(shareProducts);
      document.getElementById('product-picker-modal').style.display = 'block';
      document.getElementById('product-picker-search').value = '';
      document.body.style.overflow = 'hidden';
    }

    function closeProductPicker() {
      document.getElementById('product-picker-modal').style.display = 'none';
      document.body.style.overflow = '';
      _ingPickIdx = null;
    }

    function filterProducts() {
      var q = document.getElementById('product-picker-search').value.toLowerCase();
      var filtered = shareProducts.filter(function(p) { return p.name.toLowerCase().indexOf(q) !== -1; });
      renderProductList(filtered);
    }

    function renderProductList(products) {
      var list = document.getElementById('product-picker-list');
      list.innerHTML = '';
      if (!products.length) {
        list.innerHTML = '<div style="text-align:center;padding:20px;color:#78716c;font-family:monospace;font-size:11px">No products found</div>';
        return;
      }
      products.forEach(function(p) {
        var div = document.createElement('div');
        div.className = 'prod-pick-item';
        div.innerHTML =
          '<div><div style="font-weight:700;font-size:13px;color:#1c1917">' + p.name + '</div>' +
          '<div style="font-size:10px;color:#78716c;font-family:monospace">$' + (p.export_price || '0.00') + ' / ' + (p.unit || 'kg') + '</div></div>' +
          '<button type="button" onclick="selectProduct(' + p.id + ',\'' + p.name.replace(/'/g, "\\'") + '\')" style="padding:4px 12px;background:#1c1917;color:#fafaf9;border:none;border-radius:8px;font-size:9px;font-weight:700;cursor:pointer;font-family:monospace">Select</button>';
        list.appendChild(div);
      });
    }

    function selectProduct(pid, pname) {
      if (_ingPickIdx === null) return;
      document.getElementById('share-ing-pid-' + _ingPickIdx).value = pid;
      document.getElementById('share-ing-pname-' + _ingPickIdx).value = pname;
      var tag = document.getElementById('share-ing-tag-' + _ingPickIdx);
      tag.style.display = 'block';
      tag.innerHTML = '<span class="product-tag">' + pname + '</span>';
      closeProductPicker();
      updateShareJson();
    }

    // Init first ingredient + step rows
    document.addEventListener('DOMContentLoaded', function() {
      <?php if ($__isRestaurant): ?>
      addIngredient();
      addStep();
      <?php endif; ?>
    });
    </script>
  <?php if ($shareMsg): ?>
  <div class="toast toast-<?= $shareMsgType ?>" onclick="this.style.animation='toastOut .3s ease forwards';setTimeout(function(){this.style.display='none'}.bind(this),300)"><?= e($shareMsg) ?></div>
  <script>(function(){var t=document.querySelector('.toast');if(t){setTimeout(function(){t.style.animation='toastOut .3s ease forwards';setTimeout(function(){t.style.display='none'},300)},3000)}})();</script>
  <?php endif; ?>
  </body>
</html>

