<nav class="hidden md:flex items-center space-x-3" id="desktop-nav-menu">
  <a href="<?= $__ifmBasePath ?>shop.php" class="flex items-center space-x-1.5 font-display text-xs tracking-widest uppercase transition-all duration-200 text-slate-600 hover:text-slate-950 active:scale-95 no-underline">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-brand-blue"><path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.103 6.034h17.794"/><path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/></svg>
    <span class="font-bold">Shop</span>
  </a>
  <a href="<?= $__ifmBasePath ?>recipes.php" class="flex items-center space-x-1.5 font-display text-xs tracking-widest uppercase transition-all duration-200 text-slate-600 hover:text-slate-950 active:scale-95 no-underline">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-amber-500"><path d="M17 21a1 1 0 0 0 1-1v-5.35c0-.457.316-.844.727-1.041a4 4 0 0 0-2.134-7.589 5 5 0 0 0-9.186 0 4 4 0 0 0-2.134 7.588c.411.198.727.585.727 1.041V20a1 1 0 0 0 1 1Z"/><path d="M6 17h12"/></svg>
    <span class="font-bold">Recipes</span>
  </a>
  <a href="<?= $__ifmBasePath ?>about.php" class="flex items-center space-x-1.5 font-display text-xs tracking-widest uppercase transition-all duration-200 text-slate-600 hover:text-slate-950 active:scale-95 no-underline">
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-sky-500"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
    <span class="font-bold">About</span>
  </a>
  <a href="<?= $__ifmBasePath ?>cart.php" class="flex items-center space-x-1.5 border border-slate-200 hover:bg-slate-50/85 px-2.5 py-1 rounded-lg transition-all duration-200 active:scale-95 relative no-underline" title="Sourcing Cart">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-brand-teal"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
    <span class="font-display text-[9px] uppercase font-semibold text-slate-600 tracking-wider">Cart</span>
    <span id="nav-cart-count" style="<?= intl_cart_count() > 0 ? '' : 'display:none' ?>" class="absolute -top-1.5 -right-1.5 bg-brand-blue text-white text-[8px] font-bold rounded-full w-4 h-4 flex items-center justify-center"><?= intl_cart_count() ?></span>
  </a>
  <?php require __DIR__ . '/user_nav.php'; ?>
</nav>
<div class="flex items-center space-x-3 md:hidden">
  <button class="text-slate-700 hover:text-slate-950 focus:outline-none p-1.5 hover:bg-slate-100 rounded-lg" aria-label="Toggle Menu" id="mobile-menu-hamburger">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h16"/></svg>
  </button>
</div>
