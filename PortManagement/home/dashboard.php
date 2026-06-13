<?php
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../services/WeatherService.php';
$user = requireLogin();
list($roleDisplay, $rawRole, $userPort) = getRoleDisplay($user);
$displayName = $user['username'] ?? $user['email'] ?? 'User';
$isSuperOrPortAdmin = ($rawRole === 'super_admin' || $rawRole === 'port_admin');

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$selected_port = $_GET['port'] ?? '';
$log_port = $_GET['log_port'] ?? '';

$logs = []; $total_logs = 0;
try {
    $port_choices = getPortsForDisplay();
    $weather_data = WeatherService::getAllWeather();

    $log_where = ''; $log_params = [];
    $total_logs = (int)dbGetValue("SELECT COUNT(*) FROM user_activity WHERE user_type = 'port'");
    $offset = ($page - 1) * $per_page;
    $logs = dbGetAll("SELECT metadatauser_id as user_id, event_type as action, metadataport as port, created_at as timestamp FROM user_activity WHERE user_type = 'port' ORDER BY created_at DESC LIMIT ? OFFSET ?", [$per_page, $offset]);
} catch (Exception $e) {}

$title = $roleDisplay . ' Dashboard';
$extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />' . "\n" . '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
require __DIR__ . '/../helpers/header.php';
?>
<style>
.port-weather-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
.mini-tag { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; text-decoration:none; background:rgba(255,255,255,0.06); color:var(--muted); border:1px solid var(--border); transition:.12s; }
.mini-tag:hover { background:rgba(255,255,255,0.12); color:var(--text); }
.mini-tag.active { background:rgba(45,212,191,0.15); color:var(--brand); border-color:var(--brand); }
.mini-tag.disabled { opacity:0.4; cursor:default; }
.port-badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700; background:rgba(255,255,255,0.06); color:var(--muted); }
.port-badge.highlight { background:rgba(45,212,191,0.15); color:var(--brand); }
.txt-mono { font-family:monospace; font-size:11px; color:var(--muted); }
.txt-bold { font-weight:700; font-size:13px; }
.txt-subtle { color:var(--muted); font-size:12px; }
.pagination a { padding:4px 10px; font-size:12px; }
@media (max-width:860px) { .port-weather-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:480px) { .port-weather-grid { grid-template-columns:1fr; } }
</style>
<div class="dash-content" style="max-width:100%;">
  <div style="display:grid;grid-template-columns:1fr;gap:16px;align-items:start;">

    <div class="card">
      <h2 style="margin:0;">Port Management Console</h2>
      <p class="subtle" style="margin:4px 0 0;">
        Accessing as <strong><?= e($roleDisplay) ?></strong>
        for <strong><?= e($userPort ?: 'Global') ?></strong>
      </p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div class="card" style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;align-items:center;gap:14px;">
          <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,rgba(45,212,191,0.25),rgba(45,212,191,0.08));display:flex;align-items:center;justify-content:center;font-size:22px;">🐟</div>
          <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted);">Online Marketplace</div>
            <div style="font-size:18px;font-weight:800;">Fish Market</div>
          </div>
        </div>
        <p class="subtle" style="margin:0;">Fresh seafood & vegetables with e-wallet payments.</p>
        <div style="margin-top:auto;">
          <a class="btn btn-primary" href="http://localhost:8001" target="_blank" style="display:flex;justify-content:center;padding:10px;border-radius:10px;background:var(--brand);color:#081225;font-weight:800;font-size:13px;text-decoration:none;">Open Fish Market</a>
        </div>
      </div>
      <div class="card" style="display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;align-items:center;gap:14px;">
          <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,rgba(96,165,250,0.25),rgba(96,165,250,0.08));display:flex;align-items:center;justify-content:center;font-size:22px;">🌍</div>
          <div>
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--muted);">Global Trade</div>
            <div style="font-size:18px;font-weight:800;">International Market</div>
          </div>
        </div>
        <p class="subtle" style="margin:0;">Premium seafood sourcing platform worldwide.</p>
        <div style="margin-top:auto;">
          <a class="btn btn-primary" href="http://localhost:8002" target="_blank" style="display:flex;justify-content:center;padding:10px;border-radius:10px;background:linear-gradient(135deg,rgba(96,165,250,0.95),rgba(99,102,241,0.95));color:#fff;font-weight:800;font-size:13px;text-decoration:none;">Open International Market</a>
        </div>
      </div>
    </div>

    <div class="port-weather-grid">
      <?php foreach ($weather_data as $pw): ?>
      <div class="card" style="padding:20px 22px;display:flex;align-items:center;gap:16px;">
        <div style="width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,rgba(45,212,191,0.2),rgba(96,165,250,0.15));display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;"><?= e($pw['emoji']) ?></div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;font-size:16px;color:var(--text);"><?= e($pw['name']) ?></div>
          <div style="font-size:20px;font-weight:800;color:var(--brand-light);margin-top:4px;"><?= e(is_numeric($pw['temperature']) ? round((float)$pw['temperature'], 1) : $pw['temperature']) ?>°C</div>
          <div style="font-size:13px;color:var(--muted);margin-top:2px;">
            <?= e($pw['description']) ?>
            <?php if ($pw['humidity']): ?> · 💧<?= e($pw['humidity']) ?>%<?php endif; ?>
            <?php if ($pw['wind_speed']): ?> · 💨<?= e(round((float)$pw['wind_speed'], 1)) ?>km/h<?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px 10px;">
          <h3 style="margin:0;">🚢 Live Fleet Tracking</h3>
          <a href="/vessels/" class="btn btn-ghost" style="padding:4px 12px;font-size:11px;">Full Dashboard →</a>
        </div>
        <div id="homeVesselMap" style="width:100%;height:300px;"></div>
      </div>

    </div>

  </div>
</div>

<script>
(function(){function t(){const n=new Date(),d=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],m=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],o=n.getDate(),e=m[n.getMonth()],a=d[n.getDay()];let i=n.getHours();const l=i>=12?'PM':'AM';i=i%12||12;const h=String(n.getMinutes()).padStart(2,'0'),s=String(n.getSeconds()).padStart(2,'0');document.getElementById('clockDate').textContent=a+', '+o+' '+e;document.getElementById('clockTime').textContent=i+':'+h+':'+s+' '+l}t();setInterval(t,1000);})();

var map = L.map('homeVesselMap').setView([4.2105, 103.0], 6);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);

var vesselMarkers = L.layerGroup().addTo(map);
var driverMarkers = L.layerGroup().addTo(map);
var shipMarkers = L.layerGroup().addTo(map);
var zoneCircles = [];

var vesselIcon = L.divIcon({ className: '', html: '<div style="font-size:24px;text-align:center;line-height:1;filter:drop-shadow(0 0 4px rgba(52,211,153,0.6));">🚢</div>', iconSize: [28, 28], iconAnchor: [14, 14] });
var dockedVesselIcon = L.divIcon({ className: '', html: '<div style="font-size:24px;text-align:center;line-height:1;filter:drop-shadow(0 0 4px rgba(239,68,68,0.6));">⚓</div>', iconSize: [28, 28], iconAnchor: [14, 14] });
var fishingVesselIcon = L.divIcon({ className: '', html: '<div style="font-size:26px;text-align:center;line-height:1;filter:drop-shadow(0 0 8px rgba(52,211,153,0.9));">🎣</div>', iconSize: [30, 30], iconAnchor: [15, 15] });
var driverIcon = L.divIcon({ className: '', html: '<div style="font-size:20px;text-align:center;line-height:1;">🚛</div>', iconSize: [24, 24], iconAnchor: [12, 12] });
var shipIcon = L.divIcon({ className: '', html: '<div style="font-size:22px;text-align:center;line-height:1;filter:drop-shadow(0 0 4px rgba(249,115,22,0.5));">🛳️</div>', iconSize: [26, 26], iconAnchor: [13, 13] });

function updateFleet() {
  fetch((window.PORT_BASE_PATH || '') + '/vessels/api/fleet/')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      vesselMarkers.clearLayers();
      driverMarkers.clearLayers();
      shipMarkers.clearLayers();
      zoneCircles.forEach(function(c) { map.removeLayer(c); });
      zoneCircles = [];

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
        circle.bindPopup('<strong>🎣 ' + z.name + '</strong><br>' +
          'Species: ' + z.species + '<br>' +
          'Density: ' + (z.density * 100).toFixed(0) + '%<br>' +
          'Radius: ' + z.radius + ' km');
        circle.addTo(map);
        zoneCircles.push(circle);
      });

      (data.vessels || []).forEach(function(v) {
        if (!v.lat || !v.lng) return;
        var icon = v.is_fishing ? fishingVesselIcon : (v.status === 'at_sea' ? vesselIcon : dockedVesselIcon);
        var statusLabel = v.is_fishing ? '🎣 Fishing' : v.status;
        var popupHtml = '<strong>' + (v.is_fishing ? '🎣 ' : '🚢 ') + v.name + '</strong><br>' +
          'Status: ' + statusLabel + '<br>' +
          'Port: ' + (v.port_name || '—') + '<br>' +
          (v.speed ? 'Speed: ' + v.speed + ' kn<br>' : '') +
          (v.catch_kg ? 'Est. Catch: ' + v.catch_kg + ' kg 🐟<br>' : '') +
          (v.fish_density ? 'Fish Density: ' + (v.fish_density * 100).toFixed(0) + '%' : '');
        var marker = L.marker([v.lat, v.lng], { icon: icon });
        marker.bindPopup(popupHtml);
        vesselMarkers.addLayer(marker);
      });

      (data.drivers || []).forEach(function(d) {
        if (!d.lat || !d.lng) return;
        var marker = L.marker([d.lat, d.lng], { icon: driverIcon });
        marker.bindPopup('<strong>🚛 ' + d.name + '</strong><br>' +
          'Vehicle: ' + d.vehicle + '<br>' +
          'Port: ' + d.port_name);
        driverMarkers.addLayer(marker);
      });

      (data.shipments || []).forEach(function(s) {
        if (!s.lat || !s.lng) return;
        var marker = L.marker([s.lat, s.lng], { icon: shipIcon });
        marker.bindPopup('<strong>🛳️ Export: ' + s.order + '</strong><br>' +
          'To: ' + s.country + ' / ' + s.port + '<br>' +
          'Departed from Malaysia');
        shipMarkers.addLayer(marker);
      });

      var catchList = document.getElementById('catchList');
      if (catchList && data.catches && data.catches.length) {
        catchList.innerHTML = data.catches.slice(0, 10).map(function(c) {
          return '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:12px;">' +
            '<span style="color:var(--text);">🐟 ' + c.species + '</span>' +
            '<span style="color:var(--muted);">' + c.weight_kg + ' kg <span class="status-badge" style="font-size:10px;padding:1px 6px;">' + c.quality_grade + '</span></span>' +
            '</div>';
        }).join('');
      }
    })
    .catch(function(e) { console.log('Fleet fetch error:', e); });
}

updateFleet();
setInterval(updateFleet, 15000);

var legend = L.control({ position: 'bottomright' });
legend.onAdd = function() {
  var div = L.DomUtil.create('div', '');
  div.style.background = 'rgba(8,18,37,0.85)';
  div.style.padding = '8px 12px';
  div.style.borderRadius = '8px';
  div.style.border = '1px solid rgba(255,255,255,0.1)';
  div.style.fontSize = '11px';
  div.style.fontWeight = '600';
  div.innerHTML =
    '<div style="display:flex;align-items:center;gap:6px;color:#34d399;margin-bottom:4px;"><span style="font-size:14px;">🎣</span> Fishing</div>' +
    '<div style="display:flex;align-items:center;gap:6px;color:#34d399;margin-bottom:4px;"><span style="font-size:14px;">🚢</span> Active Vessels</div>' +
    '<div style="display:flex;align-items:center;gap:6px;color:#ef4444;margin-bottom:4px;"><span style="font-size:14px;">⚓</span> Docked Vessels</div>' +
    '<div style="display:flex;align-items:center;gap:6px;color:#a78bfa;margin-bottom:4px;"><span style="font-size:14px;">🚛</span> Delivery Drivers</div>' +
    '<div style="display:flex;align-items:center;gap:6px;color:#f97316;margin-bottom:4px;"><span style="font-size:14px;">🛳️</span> Export Shipments</div>' +
    '<div style="display:flex;align-items:center;gap:6px;color:#2dd4bf;margin-top:4px;padding-top:4px;border-top:1px solid rgba(255,255,255,0.1);"><span style="width:10px;height:10px;border-radius:50%;background:#2dd4bf;display:inline-block;"></span> Fishing Zones</div>';
  return div;
};
legend.addTo(map);
</script>
<?php require __DIR__ . '/../helpers/footer.php'; ?>
