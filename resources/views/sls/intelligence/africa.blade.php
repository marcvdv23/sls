@extends('sls.layouts.app')

@section('title', 'Africa Country Intelligence')
@section('eyebrow', '1G-SLS')
@section('page_title', 'Africa Country Intelligence')

@push('head')
    <style>
        .legacy-page { display: grid; gap: 14px; }
        .legacy-page > header,
        .legacy-page > main { padding: 0; }
        .legacy-page > header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-card);
            box-shadow: var(--card-shadow);
            padding: 14px;
        }
        .legacy-page > main { display: grid; gap: 14px; }
        .legacy-page .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
        .legacy-page .filters,
        .legacy-page .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .legacy-page label { color: var(--text-secondary); font-size: 12px; font-weight: 800; }
        .legacy-page table { background: var(--bg-secondary); }
        .legacy-page .empty { border: 1px dashed var(--border-subtle); border-radius: var(--radius-card); padding: 14px; }
        @media (max-width: 980px) {
            .legacy-page > header { align-items: flex-start; flex-direction: column; }
        }
    </style>
@endpush

@section('content')
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Country Intelligence</p>
                <h1>Africa Social Security and Tender Monitor</h1>
            </div>
            <a class="button" href="{{ url('/sls') }}">Back to dashboard</a>
        </header>

        <main>
            <div class="layout">
                <section class="panel map-wrap">
                    <div id="africa-map"></div>
                </section>

                <aside class="side">
                    <section class="panel">
                        <p class="eyebrow">Monitor Scope</p>
                        <h2>52 African Countries</h2>
                        <div class="stat-grid">
                            <div class="stat">
                                <strong>{{ $monitoredCount }}</strong>
                                <span class="muted">in country table</span>
                            </div>
                            <div class="stat">
                                <strong>{{ $updateCount }}</strong>
                                <span class="muted">news and tender updates</span>
                            </div>
                        </div>
                        <div class="legend">
                            <span><i class="swatch" style="background: var(--update);"></i> Has relevant news/tender</span>
                            <span><i class="swatch" style="background: var(--monitored);"></i> Monitored/no update</span>
                            <span><i class="swatch" style="background: var(--none);"></i> Not initialized</span>
                        </div>
                    </section>

                    <section class="panel">
                        <p class="eyebrow">Tracked Topics</p>
                        <h2>News and Tender Filter</h2>
                        <ul class="topic-list">
                            <li>social security administrations and schemes</li>
                            <li>pension funds and pension reform</li>
                            <li>provident funds</li>
                            <li>ministry of labor or labour announcements</li>
                            <li>tenders, procurement notices, bids, RFPs, and expressions of interest</li>
                            <li>contributions, benefits, registration, compliance, and enforcement</li>
                        </ul>
                    </section>

                    <section class="panel">
                        <p class="eyebrow">Latest Captured Items</p>
                        <h2>Review Queue</h2>
                        <p class="muted">Every summarized item must keep a link to the original announcement, article, gazette, or tender notice.</p>
                        <div class="update-list">
                            @forelse ($countries->where('latest_title') as $country)
                                <article class="update-item">
                                    <h3>{{ $country['name'] }}</h3>
                                    <p>{{ $country['latest_title'] }}</p>
                                    <p class="muted">{{ $country['latest_date'] ?: 'Date not captured' }}</p>
                                </article>
                            @empty
                                <p class="muted">No Africa intelligence items have been collected yet. The daily monitor will populate this section once enabled.</p>
                            @endforelse
                        </div>
                    </section>
                </aside>
            </div>
        </main>

        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const countries = @json($countries->values());
            const countryByIso = Object.fromEntries(countries.map((country) => [country.iso.toUpperCase(), country]));
            const countryByName = Object.fromEntries(countries.map((country) => [normalizeName(country.name), country]));
            const colors = {
                none: '#cfd6e2',
                monitored: '#7bb7cf',
                update: '#d62828',
                border: '#ffffff',
                hover: '#176b87',
            };

            const map = L.map('africa-map', {
                zoomControl: true,
                scrollWheelZoom: true,
            }).setView([2.5, 20], 3);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 8,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            fetch('https://raw.githubusercontent.com/datasets/geo-countries/master/data/countries.geojson')
                .then((response) => response.json())
                .then((geojson) => {
                    const africaLayer = L.geoJSON(geojson, {
                        filter: (feature) => Boolean(findCountry(feature)),
                        style: (feature) => styleFor(findCountry(feature)),
                        onEachFeature: (feature, layer) => {
                            const country = findCountry(feature);

                            layer.bindPopup(popupFor(country), { maxWidth: 360 });
                            layer.on({
                                mouseover: (event) => {
                                    event.target.setStyle({ weight: 2.5, color: colors.hover });
                                    event.target.openPopup();
                                },
                                mouseout: (event) => {
                                    africaLayer.resetStyle(event.target);
                                },
                                click: (event) => event.target.openPopup(),
                            });
                        },
                    }).addTo(map);

                    map.fitBounds(africaLayer.getBounds(), { padding: [12, 12] });
                })
                .catch(() => {
                    document.getElementById('africa-map').innerHTML = '<div style="padding:1rem;">The map boundary layer could not be loaded. Check internet access and refresh this page.</div>';
                });

            function findCountry(feature) {
                const props = feature.properties || {};
                const codeCandidates = [
                    props.ISO3166_1_ALPHA_2,
                    props['ISO3166-1-Alpha-2'],
                    props.ISO_A2,
                    props.iso_a2,
                    props.ADM0_A3,
                    props.ISO_A3,
                ].filter(Boolean).map((code) => String(code).toUpperCase());

                for (const code of codeCandidates) {
                    if (countryByIso[code]) {
                        return countryByIso[code];
                    }
                }

                const nameCandidates = [
                    props.ADMIN,
                    props.name,
                    props.NAME,
                    props.NAME_LONG,
                    props.BRK_NAME,
                    props.FORMAL_EN,
                ].filter(Boolean).map(normalizeName);

                for (const name of nameCandidates) {
                    if (countryByName[name]) {
                        return countryByName[name];
                    }
                }

                return null;
            }

            function styleFor(country) {
                const fill = country.status === 'has-update'
                    ? colors.update
                    : (country.status === 'monitored' ? colors.monitored : colors.none);

                return {
                    color: colors.border,
                    weight: 1,
                    fillColor: fill,
                    fillOpacity: .82,
                };
            }

            function popupFor(country) {
                const dateAndSource = [country.latest_date || 'Date not captured', country.latest_source].filter(Boolean).join(' | ');
                const sourceLink = country.latest_url
                    ? `<p><a href="${escapeHtml(country.latest_url)}" target="_blank" rel="noreferrer">Open original source</a></p>`
                    : '';
                const body = country.latest_title
                    ? `<p><strong>${escapeHtml(country.latest_title)}</strong></p><p>${escapeHtml(country.latest_summary || '').slice(0, 280)}</p><p class="muted">${escapeHtml(dateAndSource)}</p>${sourceLink}`
                    : '<p>No social security, pension, provident fund, ministry of labor, or tender update has been captured yet.</p>';

                return `<div class="map-popup"><h3>${escapeHtml(country.name)}</h3>${body}</div>`;
            }

            function normalizeName(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/^the\s+/i, '')
                    .replace(/&/g, 'and')
                    .replace(/cote d.?ivoire/i, "cote d'ivoire")
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, ' ')
                    .trim();
            }

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[char]));
            }
        </script>
    </div>
@endsection