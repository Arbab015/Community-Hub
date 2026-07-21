document.addEventListener('DOMContentLoaded', function() {
  const mapEl = document.getElementById('propertyLocationMap');
  const msgEl = document.getElementById('mapUnavailableMsg');
  const leaflet = window.L || window.leaFlet;
  if (!mapEl) return;

  if (!leaflet) {
    msgEl?.classList.remove('d-none');
    return;
  }

  const address = mapEl.dataset.address;
  const title = mapEl.dataset.title;
  const addressQueries = JSON.parse(mapEl.dataset.geocodeQueries || '[]');

  if (!addressQueries.length && !address) {
    mapEl.classList.add('d-none');
    msgEl?.classList.remove('d-none');
    return;
  }

  function showUnavailable() {
    mapEl.classList.add('d-none');
    msgEl?.classList.remove('d-none');
  }

  function initPropertyMap(lat, lng, zoom = 15) {
    const map = leaflet.map('propertyLocationMap').setView([lat, lng], zoom);

    leaflet.tileLayer('https://{s}.tile.osm.org/{z}/{x}/{y}.png', {
      attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a>',
      maxZoom: 18
    }).addTo(map);

    leaflet.marker([lat, lng]).addTo(map);

    setTimeout(() => map.invalidateSize(), 100);
  }

  function validCoords(data) {
    const lat = Number(data?.lat);
    const lng = Number(data?.lng ?? data?.lon);
    return Number.isFinite(lat) && Number.isFinite(lng) ? { lat, lng } : null;
  }

  function geocodeFromBrowser(query) {
    return fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query), {
      headers: { 'Accept': 'application/json' }
    })
      .then(res => res.ok ? res.json() : [])
      .then(results => validCoords(results?.[0]));
  }

  function geocodeFromBackend(query) {
    return fetch(mapEl.dataset.geocodeUrl + '?address=' + encodeURIComponent(query), {
      headers: { 'Accept': 'application/json' }
    })
      .then(res => res.ok ? res.json() : null)
      .then(data => validCoords(data));
  }

  function tryQueries(queries, resolver) {
    return queries.reduce((promise, query) => {
      return promise.then(coords => coords || resolver(query).catch(() => null));
    }, Promise.resolve(null));
  }

  tryQueries(addressQueries, geocodeFromBrowser)
    .then(coords => coords || tryQueries(addressQueries, geocodeFromBackend))
    .then(coords => coords || geocodeFromBrowser(mapEl.dataset.country || 'Pakistan').catch(() => null))
    .then(coords => {
      if (!coords) return initPropertyMap(30.3753, 69.3451, 5);
      initPropertyMap(coords.lat, coords.lng);
    })
    .catch(() => initPropertyMap(30.3753, 69.3451, 5));
});
