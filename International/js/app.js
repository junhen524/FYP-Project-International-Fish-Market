/**
 * International Fish Market - Vanilla JS (Lightweight)
 * CSS animations + minimal JS interactions.
 */
(function () {
  'use strict';

  // ============================================================
  // 1. SCROLL REVEAL - Add visible class when elements scroll in
  // ============================================================
  function initScrollReveal() {
    // Elements with data-reveal attribute or opacity:0 inline style
    var targets = document.querySelectorAll('[style*="opacity: 0"]');
    if (!targets.length) return;

    // Add CSS transition via class
    var style = document.createElement('style');
    style.textContent = '.reveal-visible { opacity: 1 !important; transform: none !important; transition: opacity 0.8s ease, transform 0.8s ease !important; }';
    document.head.appendChild(style);

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('reveal-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    targets.forEach(function (el) { observer.observe(el); });
  }

  // ============================================================
  // 2. SCROLL NAVIGATION DOTS (index.php)
  // ============================================================
  function initScrollNav() {
    var dots = document.querySelectorAll('.fixed.right-5 button, .fixed.right-5 a');
    var sections = document.querySelectorAll('section');
    if (!dots.length || !sections.length) return;

    function updateDots() {
      var scrollY = window.scrollY + window.innerHeight / 2;
      dots.forEach(function (dot, i) {
        var section = sections[i];
        if (!section) return;
        var top = section.offsetTop;
        var bottom = top + section.offsetHeight;
        var inner = dot.querySelector('[class*="rounded-full"]');
        if (inner) {
          if (scrollY >= top && scrollY < bottom) {
            inner.style.background = '#00d7e2';
            inner.style.transform = 'scale(2.2)';
            inner.style.boxShadow = '0 0 8px rgba(0,215,226,0.5)';
          } else {
            inner.style.background = '';
            inner.style.transform = 'scale(1)';
            inner.style.boxShadow = 'none';
          }
        }
      });
    }

    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function (e) {
        e.preventDefault();
        if (sections[i]) sections[i].scrollIntoView({ behavior: 'smooth' });
      });
    });

    window.addEventListener('scroll', updateDots);
    updateDots();
  }

  // ============================================================
  // 3. SCROLL DOWN INDICATOR (index.php)
  // ============================================================
  function initScrollIndicator() {
    var el = document.getElementById('scroll-visual-indicator');
    if (!el) return;
    el.addEventListener('click', function () {
      var sections = document.querySelectorAll('section');
      if (sections.length > 1) sections[1].scrollIntoView({ behavior: 'smooth' });
    });
  }

  // ============================================================
  // 4. BRAND PATCH - Replace "Kaiyo" text
  // ============================================================
  function initBrandPatch() {
    var rules = [
      ['Kaiyo Premium Seafood Market', 'International Fish Market'],
      ['KAIYO PREMIUM SEAFOOD MARKET', 'INTERNATIONAL FISH MARKET'],
      ['KAIYO_NATURAL_SEA_CARE', 'INTERNATIONAL_FISH_MARKET'],
      ['Kaiyo Sourcing Shop', 'International Fish Market Sourcing Shop'],
      ['Kaiyo Admin', 'International Fish Market Admin'],
      ['Kaiyo', 'International Fish Market'],
      ['KAIYO', 'INTERNATIONAL FISH MARKET'],
    ];

    function replace(v) {
      var s = String(v || '');
      rules.forEach(function (r) { s = s.split(r[0]).join(r[1]); });
      return s;
    }

    // Title
    document.title = replace(document.title);
    // Meta
    document.querySelectorAll('meta').forEach(function (m) {
      if (m.content) m.content = replace(m.content);
    });
    // Body text
    if (document.body) {
      var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode: function (n) {
          var p = n.parentNode;
          return (p && p.nodeName && !/script|style|noscript/i.test(p.nodeName))
            ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
      });
      var n;
      while ((n = walker.nextNode())) {
        if (n.nodeValue && n.nodeValue.indexOf('Kaiyo') !== -1) {
          n.nodeValue = replace(n.nodeValue);
        }
      }
    }
    // Attributes
    document.querySelectorAll('[title],[aria-label],[placeholder],[alt]').forEach(function (el) {
      ['title', 'aria-label', 'placeholder', 'alt'].forEach(function (a) {
        if (el.hasAttribute(a)) {
          var v = el.getAttribute(a);
          if (v.indexOf('Kaiyo') !== -1) el.setAttribute(a, replace(v));
        }
      });
    });
  }

  // ============================================================
  // 5. HEADER SCROLL EFFECT
  // ============================================================
  function initHeaderScroll() {
    var header = document.getElementById('main-app-header') ||
                 document.querySelector('header.sticky, header.fixed');
    if (!header) return;
    window.addEventListener('scroll', function () {
      if (window.scrollY > 60) {
        header.style.background = 'rgba(255,255,255,0.95)';
        header.style.backdropFilter = 'blur(10px)';
        header.style.borderBottom = '1px solid rgba(0,0,0,0.06)';
        header.style.boxShadow = '0 4px 20px rgba(0,0,0,0.06)';
      } else {
        header.style.background = '';
        header.style.backdropFilter = '';
        header.style.borderBottom = '';
        header.style.boxShadow = '';
      }
    });
  }

  // ============================================================
  // 6. NAV LINKS FIX - Ensure all links go to correct .php files
  // ============================================================
  function initNavFix() {
    // Fix any links pointing to index.php?page=xxx
    document.querySelectorAll('a[href*="index.php?page="]').forEach(function (a) {
      var m = a.href.match(/[?&]page=([a-z_-]+)/i);
      if (m) a.href = a.href.replace(/index\.php\?page=[a-z_-]+/i, m[1] + '.php');
    });
  }

  // ============================================================
  // INIT
  // ============================================================
  function init() {
    initBrandPatch();
    initScrollReveal();
    initScrollNav();
    initScrollIndicator();
    initHeaderScroll();
    initNavFix();

    // Re-patch brand for late-rendered content
    setTimeout(initBrandPatch, 500);
    setTimeout(initBrandPatch, 2000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
