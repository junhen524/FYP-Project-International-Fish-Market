<?php
/**
 * Inline JS script (replaces app-loader.js + app-brand-patch.js + entry file)
 * Combine all small JS files here and output directly to HTML
 */
function __ifmAssetVersion(string $path): string {

    $f = __DIR__ . '/' . $path;
    return file_exists($f) ? (string)filemtime($f) : '1';
}
function renderInlineScripts() { ?>
<script>
// ===== app-brand-patch.js (inlined) =====
(function() {
const BRAND_RULES = [
  ["Kaiyo Premium Seafood Market", "International Fish Market"],
  ["KAIYO PREMIUM SEAFOOD MARKET", "INTERNATIONAL FISH MARKET"],
  ["KAIYO_NATURAL_SEA_CARE", "INTERNATIONAL_FISH_MARKET"],
  ["Kaiyo Sourcing Shop", "International Fish Market Sourcing Shop"],
  ["Kaiyo Admin", "International Fish Market Admin"],
  ["Kaiyo", "International Fish Market"],
  ["KAIYO", "INTERNATIONAL FISH MARKET"],
];

function replaceBrandText(value) {
  let nextValue = String(value || "");
  BRAND_RULES.forEach(function (entry) {
    nextValue = nextValue.split(entry[0]).join(entry[1]);
  });
  return nextValue;
}

function patchTextNode(node) {
  if (!node || !node.nodeValue || !node.nodeValue.trim()) return;
  const patched = replaceBrandText(node.nodeValue);
  if (patched !== node.nodeValue) node.nodeValue = patched;
}

function patchAttributes(root) {
  const nodes = root.querySelectorAll("[title],[aria-label],[placeholder],[alt]");
  nodes.forEach(function (node) {
    ["title", "aria-label", "placeholder", "alt"].forEach(function (attr) {
      if (!node.hasAttribute(attr)) return;
      const cur = node.getAttribute(attr);
      const patched = replaceBrandText(cur);
      if (patched !== cur) node.setAttribute(attr, patched);
    });
  });
}

function patchDocumentText(root) {
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode: function (node) {
      const parent = node.parentNode;
      if (!parent || !parent.nodeName) return NodeFilter.FILTER_REJECT;
      const tag = parent.nodeName.toLowerCase();
      if (tag === "script" || tag === "style" || tag === "noscript") return NodeFilter.FILTER_REJECT;
      return NodeFilter.FILTER_ACCEPT;
    },
  });
  let n = walker.nextNode();
  while (n) { patchTextNode(n); n = walker.nextNode(); }
  patchAttributes(root);
}

function patchDocumentTitle() { document.title = replaceBrandText(document.title); }

function patchStoredProfile(key) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return;
    const profile = JSON.parse(raw);
    if (!profile || typeof profile !== "object") return;
    ["name","email","tier","username","company","workspace"].forEach(function (f) {
      if (typeof profile[f] === "string") profile[f] = replaceBrandText(profile[f]);
    });
    localStorage.setItem(key, JSON.stringify(profile));
  } catch(e) {}
}

function patchStorage() {
  patchStoredProfile("kaiyo_user_profile");
  patchStoredProfile("ifm_user_profile");
}

function patchWindowAlert() {
  if (window.__IFM_ALERT_PATCHED__) return;
  const orig = window.alert.bind(window);
  window.alert = function (m) { orig(replaceBrandText(m)); };
  window.__IFM_ALERT_PATCHED__ = true;
}

function runBrandPatch() {
  patchStorage();
  patchWindowAlert();
  patchDocumentTitle();
  if (document.body) patchDocumentText(document.body);
}

function scheduleBrandPatch() {
  [0, 300, 900, 2200, 4500, 7000].forEach(function (d) { setTimeout(runBrandPatch, d); });
}

function patchHistoryMethod(method) {
  const orig = window.history[method];
  if (typeof orig !== "function") return;
  window.history[method] = function () {
    const r = orig.apply(window.history, arguments);
    scheduleBrandPatch();
    return r;
  };
}

function patchRouteChanges() {
  if (window.__IFM_ROUTE_PATCHED__) return;
  patchHistoryMethod("pushState");
  patchHistoryMethod("replaceState");
  window.addEventListener("popstate", scheduleBrandPatch);
  window.addEventListener("load", scheduleBrandPatch);
  window.__IFM_ROUTE_PATCHED__ = true;
}

patchRouteChanges();
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", scheduleBrandPatch, { once: true });
} else {
  scheduleBrandPatch();
}
})();

// ===== app-loader.js (inlined, modified) =====
(function() {
var APP_CHUNKS = [
  "app.js",
];

var appLoadPromise = null;
var __jsBase = (window.__APP_BASE_PATH__ || "/").replace(/\/+$/, "/") + "js/";

function loadClassicScript(src) {
  return new Promise(function(resolve, reject) {
    var script = document.createElement("script");
    script.src = __jsBase + src;
    script.async = false;
    script.onload = resolve;
    script.onerror = function() { reject(new Error("Unable to load " + src)); };
    document.head.appendChild(script);
  });
}

function startApp() {
  if (appLoadPromise) return appLoadPromise;
  appLoadPromise = APP_CHUNKS.reduce(function(chain, chunk) {
    return chain.then(function() { return loadClassicScript(chunk); });
  }, Promise.resolve());
  return appLoadPromise;
}

startApp();
})();
</script>
<?php } ?>
