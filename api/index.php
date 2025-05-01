<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ip'])) {
    $ip = $_POST['ip'];
    $ipData = json_decode(file_get_contents("https://apimobile.meituan.com/locate/v2/ip/loc?rgeo=true&ip=" . urlencode($ip)), true);
    if (!$ipData || !isset($ipData['data']['lat'])) exit(json_encode(['success' => false]));
    
    $lat = $ipData['data']['lat']; $lng = $ipData['data']['lng']; $rgeo = $ipData['data']['rgeo'];
    $cityData = json_decode(file_get_contents("https://apimobile.meituan.com/group/v1/city/latlng/{$lat},{$lng}?tag=0"), true)['data'] ?? [];
    
    exit(json_encode(['success' => true, 'ip' => $ip, 'lat' => $lat, 'lng' => $lng, 
        'country' => $rgeo['country'] ?? '', 'province' => $rgeo['province'] ?? '', 
        'city' => $rgeo['city'] ?? '', 'district' => $rgeo['district'] ?? '', 
        'detail' => $cityData['detail'] ?? '']));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8"><title>IP Locator</title><meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fastly.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" rel="stylesheet"/>
    <script src="https://fastly.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
</head>
<body class="bg-slate-50 font-sans p-3 md:p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-xl font-medium mb-6 text-center text-gray-700">IP Geolocation</h1>
        <div class="grid md:grid-cols-2 gap-4" id="cards"></div>
        <div class="text-center text-xs text-gray-400 mt-6">Powered by Meituan API & CARTO</div>
    </div>
    <script>
    (async () => {
        const sources = [
            {id: 'f', url: 'https://ipv4.lvhai.org/', label: 'Foreign IP', color: '#3b82f6'},
            {id: 'd', url: 'https://ipv4_cu.itdog.cn/', label: 'Domestic IP', color: '#10b981'}
        ];
        
        // Create cards & fetch IPs
        sources.forEach(s => {
            document.getElementById('cards').innerHTML += `
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-sm font-medium text-gray-700">${s.label}</h2>
                        <div class="h-2 w-2 rounded-full" style="background:${s.color}"></div>
                    </div>
                    <div id="${s.id}-content" class="p-3 text-sm">
                        <div class="animate-pulse h-4 w-20 bg-gray-200 rounded"></div>
                    </div>
                    <div id="${s.id}-map" class="h-40 rounded-md mt-2 hidden"></div>
                </div>`;
            
            // Process IP
            (async () => {
                try {
                    // Get IP
                    const ip = await fetch(s.url).then(r => s.url.includes('lvhai') ? 
                        r.text().then(t => { try { return JSON.parse(t).ip || t.trim(); } catch { return t.trim(); } }) : 
                        r.json().then(j => j.ip)).catch(() => null);
                    
                    if (!ip) {
                        document.getElementById(`${s.id}-content`).innerHTML = `<div class="text-red-500">Failed to get IP</div>`;
                        return;
                    }
                    
                    document.getElementById(`${s.id}-content`).innerHTML = `<div class="font-mono text-gray-600">IP: ${ip}</div>`;
                    
                    // Get location
                    const data = await fetch(location.href, {
                        method: 'POST', 
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `ip=${encodeURIComponent(ip)}`
                    }).then(r => r.json());
                    
                    if (!data.success) {
                        document.getElementById(`${s.id}-content`).innerHTML += `<div class="text-red-500 text-xs mt-1">Location failed</div>`;
                        return;
                    }
                    
                    // Update content
                    document.getElementById(`${s.id}-content`).innerHTML = `
                        <div class="font-mono text-gray-600">IP: ${ip}</div>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs mt-2">
                            <div><span class="text-gray-500">Country:</span> ${data.country||'—'}</div>
                            <div><span class="text-gray-500">Province:</span> ${data.province||'—'}</div>
                            <div><span class="text-gray-500">City:</span> ${data.city||'—'}</div>
                            <div><span class="text-gray-500">District:</span> ${data.district||'—'}</div>
                        </div>`;
                    
                    // Show map with CARTO basemap
                    const mapEl = document.getElementById(`${s.id}-map`);
                    mapEl.classList.remove('hidden');
                    const map = L.map(mapEl, {zoomControl: false, attributionControl: false}).setView([data.lat, data.lng], 10);
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                        subdomains: 'abcd'
                    }).addTo(map);
                    L.circleMarker([data.lat, data.lng], {radius: 6, color: s.color, weight: 2, fillOpacity: 0.3}).addTo(map);
                    setTimeout(() => map.invalidateSize(), 100);
                } catch (err) {
                    document.getElementById(`${s.id}-content`).innerHTML = `<div class="text-red-500">Error: ${err.message}</div>`;
                }
            })();
        });
    })();
    </script>
</body>
</html>
