<?php
$user = requireLogin();
list($role, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';

$vessels = dbGetAll("SELECT v.id, v.name, v.status, v.capacity_tonnes, vt.latitude, vt.longitude, vt.speed_kn as speed, vt.is_fishing, vt.catch_kg_estimate as catch_today FROM vessels v LEFT JOIN (SELECT a.* FROM vessel_track a INNER JOIN (SELECT vessel_id, MAX(id) AS max_id FROM vessel_track GROUP BY vessel_id) b ON a.id = b.max_id) vt ON vt.vessel_id = v.id ORDER BY v.name");
$stats = [
    'total_vessels' => count($vessels),
    'at_sea' => count(array_filter($vessels, fn($v) => $v['status'] === 'at_sea' && !$v['is_fishing'])),
    'fishing' => count(array_filter($vessels, fn($v) => $v['is_fishing'])),
    'docked' => count(array_filter($vessels, fn($v) => $v['status'] === 'docked')),
    'total_catches_today' => array_sum(array_column($vessels, 'catch_today')),
];
$zones = [];
try { $zones = dbGetAll("SELECT name, '' as status, 0 as vessel_count FROM fishery_zone WHERE is_active = true ORDER BY name"); } catch (Exception $e) { $zones = []; }
$title = 'AI Vessel Simulation';
$extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.stat-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-bottom: 16px; }
.stat-card { background: rgba(255,255,255,0.06); border: 1px solid var(--border); border-radius: 12px; padding: 14px; }
.stat-card .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); margin-bottom: 4px; font-weight: 700; }
.stat-card .value { font-size: 22px; font-weight: 800; }
#vesselMap { width: 100%; height: 520px; border-radius: 14px; overflow: hidden; border: 1px solid var(--border); }
.vessel-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; }
.v-at-sea { background: rgba(96,165,250,0.2); color: #60a5fa; }
.v-fishing { background: rgba(52,211,153,0.2); color: var(--ok); }
.v-docked { background: rgba(255,255,255,0.1); color: var(--muted); }
.zone-card { background: rgba(255,255,255,0.04); border: 1px solid var(--border); border-radius: 10px; padding: 12px; margin-bottom: 8px; }
.zone-card .z-name { font-size: 13px; font-weight: 700; }
.zone-card .z-detail { font-size: 11px; color: var(--muted); margin-top: 4px; }
.density-bar { height: 4px; border-radius: 2px; margin-top: 4px; background: rgba(255,255,255,0.1); overflow: hidden; }
.density-fill { height: 100%; border-radius: 2px; background: var(--brand); }
.log-box { max-height: 200px; overflow-y: auto; font-size: 11px; }
.log-box div { padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
.legend-item { display:flex;align-items:center;gap:6px;padding:4px 6px;margin-bottom:2px;border-radius:6px;transition:all 0.15s; }
.legend-item:hover { background:rgba(255,255,255,0.08); }
.legend-item.active { color: #e2e8f0; }
.legend-item.dim { color: rgba(148,163,184,0.4); }
.legend-count { margin-left:auto;font-size:10px;background:rgba(255,255,255,0.1);padding:1px 6px;border-radius:8px;min-width:18px;text-align:center; }
@media (max-width: 900px) { .stat-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 600px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="dash-layout">
  <nav class="dash-sidebar">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:var(--muted);padding:8px 14px 10px;">🚢 Fleet</div>
    <a class="dash-sidebar-item active" href="vessels/">🗺️ Fleet Dashboard</a>
    <a class="dash-sidebar-item" href="vessels/trips/">🎣 Trip Log & Sorting</a>
  </nav>

  <div class="dash-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <div>
        <h2 style="margin:0;font-size:18px;">🚢 AI Vessel Simulation</h2>
        <p class="subtle" style="margin:2px 0 0;">Real-time fleet tracking with AI-driven behavior</p>
      </div>
      <div style="display:flex;gap:6px;">
        <button class="btn btn-primary" onclick="location.reload()" style="padding:5px 14px;font-size:11px;font-weight:700;">Refresh</button>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat-card"><div class="label">Vessels Total</div><div class="value" style="color:var(--brand);"><?= e($stats['total_vessels']) ?></div></div>
      <div class="stat-card"><div class="label">At Sea</div><div class="value" style="color:#fbbf24;"><?= e($stats['at_sea']) ?></div></div>
      <div class="stat-card"><div class="label">Fishing Now</div><div class="value" style="color:var(--ok);"><?= e($stats['fishing']) ?></div></div>
      <div class="stat-card"><div class="label">Docked</div><div class="value" style="color:var(--muted);"><?= e($stats['docked']) ?></div></div>
      <div class="stat-card"><div class="label">Catch Count</div><div class="value" style="color:var(--ok);"><?= number_format($stats['total_catches_today'], 1) ?>kg</div></div>
      <div class="stat-card"><div class="label">AI Actions / 24h</div><div class="value" style="color:var(--brand);">—</div></div>
    </div>

    <div class="card" style="padding:0;overflow:hidden;margin-bottom:16px;position:relative;z-index:1;">
      <div id="vesselMap"></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
      <div class="card">
        <h3 style="font-size:14px;margin-bottom:12px;">🚢 Vessel Fleet</h3>
        <div style="max-height:300px;overflow-y:auto;">
          <?php if (empty($vessels)): ?><div style="text-align:center;padding:30px;color:var(--muted);">No vessels deployed yet.</div><?php endif; ?>
          <?php foreach ($vessels as $v):
            $isFish = $v['is_fishing'] ?? false;
            $statusLabel = $v['status'] === 'docked' ? 'Docked' : ($isFish ? 'Fishing' : 'At Sea');
            $tagClass = $v['status'] === 'docked' ? 'v-docked' : ($isFish ? 'v-fishing' : 'v-at-sea');
            $emoji = $isFish ? '🎣' : ($v['status'] === 'docked' ? '⚓' : '🚢');
          ?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05);">
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand-2));display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;">
              <?= $emoji ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;font-size:13px;"><?= e($v['name']) ?></div>
              <div style="font-size:10px;color:var(--muted);">
                <?= $v['latitude'] ? number_format((float)$v['latitude'], 4).', '.number_format((float)$v['longitude'], 4) : '📍 Pending GPS...' ?>
              </div>
            </div>
            <div>
              <span class="vessel-tag <?= $tagClass ?>"><?= $statusLabel ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <h3 style="font-size:15px;margin-bottom:12px;">🎯 Fishing Zones</h3>
        <div style="max-height:400px;overflow-y:auto;">
          <?php foreach ($zones as $z): ?>
          <div class="zone-card">
            <div class="z-name"><?= e($z['name']) ?></div>
            <div class="z-detail">🐟 Various species · Active</div>
            <div class="density-bar"><div class="density-fill" style="width:60%;"></div></div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($zones)): ?><div style="text-align:center;padding:30px;color:var(--muted);">No fishing zones defined.</div><?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const map = L.map('vesselMap', { center: [4.0, 103.0], zoom: 6, zoomControl: true, attributionControl: false });
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(map);

var vesselIcon = L.divIcon({ className: '', html: '<div style="font-size:24px;text-align:center;line-height:1;filter:drop-shadow(0 0 4px rgba(52,211,153,0.6));">🚢</div>', iconSize: [28, 28], iconAnchor: [14, 14] });
var dockedVesselIcon = L.divIcon({ className: '', html: '<div style="font-size:24px;text-align:center;line-height:1;filter:drop-shadow(0 0 4px rgba(239,68,68,0.6));">⚓</div>', iconSize: [28, 28], iconAnchor: [14, 14] });
var fishingVesselIcon = L.divIcon({ className: '', html: '<div style="font-size:26px;text-align:center;line-height:1;filter:drop-shadow(0 0 8px rgba(52,211,153,0.9));">🎣</div>', iconSize: [30, 30], iconAnchor: [15, 15] });
var driverIcon = L.divIcon({ className: '', html: '<div style="font-size:20px;text-align:center;line-height:1;">🚛</div>', iconSize: [24, 24], iconAnchor: [12, 12] });
var shipIcon = L.divIcon({ className: '', html: '<div style="font-size:22px;text-align:center;line-height:1;filter:drop-shadow(0 0 4px rgba(249,115,22,0.5));">🛳️</div>', iconSize: [26, 26], iconAnchor: [13, 13] });

// Filter state
var filterState = { fishing: true, active: true, docked: true, drivers: true, shipments: true, zones: true };
var overlays = {
  fishing: L.layerGroup().addTo(map),
  active: L.layerGroup().addTo(map),
  docked: L.layerGroup().addTo(map),
  drivers: L.layerGroup().addTo(map),
  shipments: L.layerGroup().addTo(map),
  zones: []
};
var counts = { fishing: 0, active: 0, docked: 0, drivers: 0, shipments: 0 };

function updateFilter(key) {
  filterState[key] = !filterState[key];
  if (key === 'zones') {
    overlays.zones.forEach(function(c) { if (filterState.zones) { c.addTo(map); } else { map.removeLayer(c); } });
  } else {
    if (filterState[key]) { overlays[key].addTo(map); } else { map.removeLayer(overlays[key]); }
  }
  renderLegend();
}

function renderLegend() {
  var el = document.getElementById('legendContent');
  if (!el) return;
  el.innerHTML =
    '<div class="legend-item' + (filterState.fishing ? ' active' : ' dim') + '" onclick="updateFilter(\'fishing\')"><span style="font-size:14px;">🎣</span> Fishing <span class="legend-count">' + counts.fishing + '</span></div>' +
    '<div class="legend-item' + (filterState.active ? ' active' : ' dim') + '" onclick="updateFilter(\'active\')"><span style="font-size:14px;">🚢</span> Active <span class="legend-count">' + counts.active + '</span></div>' +
    '<div class="legend-item' + (filterState.docked ? ' active' : ' dim') + '" onclick="updateFilter(\'docked\')"><span style="font-size:14px;">⚓</span> Docked <span class="legend-count">' + counts.docked + '</span></div>' +
    '<div class="legend-item' + (filterState.drivers ? ' active' : ' dim') + '" onclick="updateFilter(\'drivers\')"><span style="font-size:14px;">🚛</span> Drivers <span class="legend-count">' + counts.drivers + '</span></div>' +
    '<div class="legend-item' + (filterState.shipments ? ' active' : ' dim') + '" onclick="updateFilter(\'shipments\')"><span style="font-size:14px;">🛳️</span> Shipments <span class="legend-count">' + counts.shipments + '</span></div>' +
    '<div class="legend-item' + (filterState.zones ? ' active' : ' dim') + '" onclick="updateFilter(\'zones\')" style="margin-top:4px;padding-top:4px;border-top:1px solid rgba(255,255,255,0.1);"><span style="width:10px;height:10px;border-radius:50%;background:#2dd4bf;display:inline-block;"></span> Zones</div>';
}

var legend = L.control({ position: 'bottomright' });
legend.onAdd = function() {
  var div = L.DomUtil.create('div', '');
  div.id = 'legendContainer';
  div.style.background = 'rgba(8,18,37,0.9)';
  div.style.padding = '8px 12px';
  div.style.borderRadius = '10px';
  div.style.border = '1px solid rgba(255,255,255,0.12)';
  div.style.fontSize = '11px';
  div.style.fontWeight = '600';
  div.style.minWidth = '140px';
  div.style.cursor = 'pointer';
  div.innerHTML = '<div id="legendContent"></div>';
  return div;
};
legend.addTo(map);

function updateFleet() {
  fetch((window.PORT_BASE_PATH || '') + '/vessels/api/fleet/')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      overlays.fishing.clearLayers();
      overlays.active.clearLayers();
      overlays.docked.clearLayers();
      overlays.drivers.clearLayers();
      overlays.shipments.clearLayers();
      overlays.zones.forEach(function(c) { map.removeLayer(c); });
      overlays.zones = [];
      counts = { fishing: 0, active: 0, docked: 0, drivers: 0, shipments: 0 };

      (data.zones || []).forEach(function(z) {
        if (!z.lat || !z.lng) return;
        var opacity = Math.min(z.density * 0.25 + 0.03, 0.15);
        var circle = L.circle([z.lat, z.lng], {
          radius: z.radius * 1000,
          color: '#2dd4bf',
          fillColor: '#2dd4bf',
          fillOpacity: opacity,
          weight: 1,
          opacity: 0.4
        });
        circle.bindPopup('<strong>🎣 ' + z.name + '</strong><br>Species: ' + z.species + '<br>Density: ' + (z.density * 100).toFixed(0) + '%');
        if (filterState.zones) circle.addTo(map);
        overlays.zones.push(circle);
      });

      (data.vessels || []).forEach(function(v) {
        if (!v.lat || !v.lng) return;
        var isFishing = v.is_fishing || v.status === 'fishing';
        var isDocked = v.status === 'docked';
        var icon, layerKey;
        if (isFishing) {
          icon = fishingVesselIcon; layerKey = 'fishing'; counts.fishing++;
        } else if (isDocked) {
          icon = dockedVesselIcon; layerKey = 'docked'; counts.docked++;
        } else {
          icon = vesselIcon; layerKey = 'active'; counts.active++;
        }
        var statusLabel = isFishing ? '🎣 Fishing' : (isDocked ? '⚓ Docked' : '🚢 Active');
        var popupHtml = '<strong>' + (isFishing ? '🎣 ' : '🚢 ') + v.name + '</strong><br>' +
          'Status: ' + statusLabel + '<br>' +
          'Port: ' + (v.port_name || '—') + '<br>' +
          (v.speed ? 'Speed: ' + v.speed + ' kn<br>' : '') +
          (v.catch_kg ? 'Est. Catch: ' + v.catch_kg + ' kg 🐟' : '');
        var marker = L.marker([v.lat, v.lng], { icon: icon });
        marker.bindPopup(popupHtml);
        if (filterState[layerKey]) overlays[layerKey].addLayer(marker);
        else overlays[layerKey].addLayer(marker); // keep in overlay for later
      });

      (data.drivers || []).forEach(function(d) {
        if (!d.lat || !d.lng) return;
        counts.drivers++;
        var marker = L.marker([d.lat, d.lng], { icon: driverIcon });
        marker.bindPopup('<strong>🚛 ' + d.name + '</strong><br>Vehicle: ' + d.vehicle + '<br>Port: ' + d.port_name);
        if (filterState.drivers) overlays.drivers.addLayer(marker);
        else overlays.drivers.addLayer(marker);
      });

      (data.shipments || []).forEach(function(s) {
        if (!s.lat || !s.lng) return;
        counts.shipments++;
        var marker = L.marker([s.lat, s.lng], { icon: shipIcon });
        marker.bindPopup('<strong>🛳️ Export: ' + s.order + '</strong><br>To: ' + s.country + ' / ' + s.port + '<br>Departed from Malaysia');
        if (filterState.shipments) overlays.shipments.addLayer(marker);
        else overlays.shipments.addLayer(marker);
      });

      var logEl = document.getElementById('activityLog');
      if (logEl && data.catches) {
        logEl.innerHTML = data.catches.slice(0, 10).map(function(c) {
          return '<div style="padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;"><span style="color:#34d399;">🐟</span> Caught ' + c.weight_kg + ' kg ' + c.species + ' <span style="color:var(--muted);font-size:10px;">[' + c.quality_grade + ']</span></div>';
        }).join('');
      }
      renderLegend();
    })
    .catch(function(e) { console.log('Fleet fetch error:', e); });
}

updateFleet();
setInterval(updateFleet, 15000);
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
