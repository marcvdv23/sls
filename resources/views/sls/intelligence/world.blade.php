@extends('sls.layouts.app')

@section('title', 'World Country Intelligence')
@section('eyebrow', '1G-SLS')
@section('page_title', 'World Country Intelligence')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .legacy-page { --update: #d62828; --monitored: #7bb7cf; --none: #cfd6e2; display: grid; gap: 14px; }
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
        .layout { display: grid; gap: 12px; }
        .map-wrap {
            min-width: 0;
            padding: 0;
            overflow: hidden;
        }
        #world-map {
            width: 100%;
            height: min(72vh, 760px);
            min-height: 560px;
            background: #eef5f8;
            border-radius: var(--radius-card);
            z-index: 1;
        }
        .leaflet-container {
            font: inherit;
        }
        .scope-strip,
        .dense-section {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-subtle);
            border-bottom: 1px solid var(--border-subtle);
            padding: 10px 0;
        }
        .scope-strip {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) minmax(240px, 2fr) auto;
            gap: 12px;
            align-items: center;
        }
        .scope-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            color: var(--text-secondary);
            font-size: 12px;
        }
        .stat strong {
            display: inline-block;
            margin-right: 4px;
            font-family: "Sora", sans-serif;
            font-size: .98rem;
            line-height: 1;
        }
        .region-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 12px;
        }
        .region-actions button {
            min-height: 30px;
            padding: 4px 8px;
            font-size: 12px;
        }
        .region-link {
            border: 1px solid var(--border-visible);
            border-radius: var(--radius-input);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 800;
            padding: 5px 9px;
            text-decoration: none;
        }
        .region-link.active {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            color: #fff;
        }
        .legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; }
        .topic-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        .topic-row span {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-badge);
            font-size: 12px;
            padding: 3px 7px;
        }
        .dense-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
        }
        .dense-table th,
        .dense-table td {
            border-bottom: 1px solid var(--border-subtle);
            font-size: 12px;
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
        }
        .dense-table th {
            color: var(--text-muted);
            font-size: 11px;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .dense-table .country-col { width: 16%; }
        .dense-table .date-col { width: 12%; }
        .dense-table .source-col { width: 18%; }
        .dense-table .link-col { width: 110px; }
        .dense-table td { line-height: 1.35; }
        .dense-table a { font-weight: 800; }
        .swatch {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 3px;
            margin-right: 6px;
            vertical-align: -1px;
        }
        .map-popup {
            display: grid;
            gap: 6px;
            max-width: 340px;
        }
        .map-popup h3 {
            margin: 0;
            font-size: .95rem;
        }
        .map-popup p {
            margin: 0;
            line-height: 1.35;
        }
        @media (max-width: 1180px) {
            .scope-strip { grid-template-columns: 1fr; }
            #world-map { height: 620px; }
        }
        @media (max-width: 720px) {
            #world-map { min-height: 420px; height: 60vh; }
        }
    </style>
@endpush

@section('content')
    @php
        $selectedRegion = strtolower((string) request('region', 'all'));
        $selectedRegion = in_array($selectedRegion, ['africa', 'asia', 'latin america', 'latin_america', 'caribbean', 'north america', 'north_america', 'europe', 'all'], true)
            ? str_replace('_', ' ', $selectedRegion)
            : 'all';
        $selectedRegionLabel = $selectedRegion === 'all' ? 'All regions' : ucwords($selectedRegion);
        $regionLinks = [
            'all' => 'All regions',
            'africa' => 'Africa',
            'asia' => 'Asia',
            'latin_america' => 'Latin America',
            'caribbean' => 'Caribbean',
            'north_america' => 'North America',
            'europe' => 'Europe',
        ];
    @endphp
    <div class="legacy-page">
<header>
            <div>
                <p class="eyebrow">Country Intelligence</p>
                <h1>{{ $focusConfig['label'] ?? 'World Intelligence Monitor' }}</h1>
                <p class="muted" style="margin:.25rem 0 0;">Map view: {{ $selectedRegionLabel }}</p>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <a class="button" style="background:#fff;color:var(--accent);" href="{{ route('sls.intelligence.review', ['focus' => $focus, 'region' => request('region', 'all')]) }}">Review all items</a>
                <a class="button" style="background:#fff;color:var(--accent);" href="{{ route('sls.intelligence.sources', ['region' => request('region', 'all')]) }}">Source coverage</a>
                <a class="button" href="{{ url('/sls') }}">Back to dashboard</a>
            </div>
        </header>

        <main>
            <div class="layout">
                <section class="panel map-wrap">
                    <div id="world-map"></div>
                </section>

                <section class="scope-strip">
                    <div>
                        <p class="eyebrow" style="margin:0 0 4px;">Monitor Scope</p>
                        <div class="scope-stats">
                            <span class="stat"><strong>{{ $countries->count() }}</strong> countries/markets</span>
                            <span class="stat"><strong>{{ $monitoredCount }}</strong> monitored</span>
                            <span class="stat"><strong>{{ $updateCount }}</strong> updates</span>
                        </div>
                    </div>
                    <div class="region-actions" style="margin-top:0;">
                        @foreach ($regionLinks as $regionKey => $regionLabel)
                            @php $isActiveRegion = $regionKey === 'latin_america' ? $selectedRegion === 'latin america' : $selectedRegion === $regionKey; @endphp
                            <a class="region-link {{ $isActiveRegion ? 'active' : '' }}" href="{{ route('sls.intelligence.world', ['focus' => $focus, 'region' => $regionKey]) }}">
                                {{ $regionLabel }}{{ $regionKey !== 'all' ? ' ('.($regionCount[$regionLabel] ?? 0).')' : '' }}
                            </a>
                        @endforeach
                    </div>
                    <div class="legend">
                        <span><i class="swatch" style="background: var(--update);"></i> Has update</span>
                        <span><i class="swatch" style="background: var(--monitored);"></i> Monitored</span>
                        <span><i class="swatch" style="background: var(--none);"></i> Not initialized</span>
                    </div>
                </section>

                <section class="dense-section">
                    <p class="eyebrow" style="margin:0;">Tracked Topics</p>
                    <div class="topic-row">
                        @foreach (array_slice($focusConfig['terms'] ?? [], 0, 18) as $term)
                            <span>{{ $term }}</span>
                        @endforeach
                    </div>
                    <div class="region-actions">
                        @foreach ($focuses as $focusKey => $availableFocus)
                            <a class="region-link {{ $focus === $focusKey ? 'active' : '' }}" href="{{ route('sls.intelligence.world', ['focus' => $focusKey, 'region' => request('region', 'all')]) }}">{{ $availableFocus['label'] }}</a>
                        @endforeach
                    </div>
                </section>

                <section class="dense-section">
                    <p class="eyebrow" style="margin:0;">Latest Captured Items</p>
                    <table class="dense-table">
                        <thead>
                            <tr>
                                <th class="country-col">Country</th>
                                <th>Latest item</th>
                                <th class="date-col">Date</th>
                                <th class="source-col">Source</th>
                                <th class="link-col">Link</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($latestItems ?? $countries->where('latest_title')->sortByDesc('latest_sort_date')->values() as $country)
                                <tr>
                                    <td><strong>{{ $country['name'] }}</strong><br><span class="muted">{{ $country['region_group'] ?: 'Global' }}</span></td>
                                    <td>
                                        @if (! empty($country['latest_needs_translation']))
                                            <span class="translation-pending">Translation pending</span>
                                        @else
                                            {{ $country['latest_title'] }}
                                        @endif
                                        @if (! empty($country['latest_original_title']) && $country['latest_original_title'] !== $country['latest_title'])
                                            <br><span class="muted">Original: {{ $country['latest_original_title'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $country['latest_date'] ?: 'Date not captured' }}</td>
                                    <td>{{ $country['latest_source'] ?: 'Source not captured' }}</td>
                                    <td>
                                        @if ($country['latest_url'])
                                            <a href="{{ $country['latest_url'] }}" rel="noreferrer">Open</a>
                                        @else
                                            <span class="muted">No URL</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="muted">No intelligence items have been collected yet. The scheduled monitor will populate this section once enabled.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </section>
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

            const map = L.map('world-map', {
                zoomControl: true,
                scrollWheelZoom: true,
            }).setView([8, -15], 3);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 8,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(map);

            let worldLayer;
            const regionBounds = {};

            fetch('https://raw.githubusercontent.com/datasets/geo-countries/master/data/countries.geojson')
                .then((response) => response.json())
                .then((geojson) => {
                    worldLayer = L.geoJSON(geojson, {
                        filter: (feature) => Boolean(findCountry(feature)),
                        style: (feature) => styleFor(findCountry(feature)),
                        onEachFeature: (feature, layer) => {
                            const country = findCountry(feature);
                            const hasUpdate = country.status === 'has-update' && Boolean(country.latest_title);

                            if (hasUpdate) {
                                layer.bindPopup(popupFor(country), { maxWidth: 360 });
                            }

                            layer.on({
                                mouseover: (event) => {
                                    event.target.setStyle({ weight: 2.5, color: colors.hover });
                                    if (hasUpdate) {
                                        event.target.openPopup();
                                    }
                                },
                                mouseout: (event) => {
                                    worldLayer.resetStyle(event.target);
                                },
                                click: (event) => {
                                    if (hasUpdate) {
                                        event.target.openPopup();
                                    }
                                },
                            });

                            const region = country.region_group || 'World';
                            if (!regionBounds[region]) {
                                regionBounds[region] = L.latLngBounds([]);
                            }
                            regionBounds[region].extend(layer.getBounds());
                        },
                    }).addTo(map);

                    map.invalidateSize();
                    map.fitBounds(worldLayer.getBounds(), { padding: [12, 12] });
                    focusFromQuery();

                    window.setTimeout(() => {
                        map.invalidateSize();
                        if (worldLayer) {
                            map.fitBounds(worldLayer.getBounds(), { padding: [12, 12] });
                            focusFromQuery();
                        }
                    }, 250);
                })
                .catch(() => {
                    document.getElementById('world-map').innerHTML = '<div style="padding:1rem;">The map boundary layer could not be loaded. Check internet access and refresh this page.</div>';
                });

            document.querySelectorAll('[data-region]').forEach((button) => {
                button.addEventListener('click', () => focusRegion(button.dataset.region));
            });

            function focusFromQuery() {
                const params = new URLSearchParams(window.location.search);
                const region = params.get('region');

                if (region) {
                    const normalizedRegion = region
                        .replace(/_/g, ' ')
                        .replace(/\b\w/g, (letter) => letter.toUpperCase());
                    focusRegion(normalizedRegion);
                }
            }

            function focusRegion(region) {
                if (region === 'World' && worldLayer) {
                    map.fitBounds(worldLayer.getBounds(), { padding: [12, 12] });
                    return;
                }

                if (regionBounds[region]) {
                    map.fitBounds(regionBounds[region], { padding: [24, 24] });
                }
            }

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
                    ? `<p><a class="source-link" href="${escapeHtml(country.latest_url)}" rel="noreferrer">Open original source</a></p>`
                    : '';
                const body = country.latest_title
                    ? `<p><strong>${escapeHtml(country.latest_title)}</strong></p><p>${escapeHtml(country.latest_summary || '').slice(0, 280)}</p><p class="muted">${escapeHtml(dateAndSource)}</p>${sourceLink}`
                    : '<p>No update has been captured yet for the selected intelligence focus.</p>';

                return `<div class="map-popup"><h3>${escapeHtml(country.name)}</h3>${body}</div>`;
            }

            function normalizeName(value) {
                return String(value || '')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/^the\s+/i, '')
                    .replace(/&/g, 'and')
                    .replace(/cote d.?ivoire/i, "cote d'ivoire")
                    .replace(/curacao/i, 'curacao')
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

            document.getElementById('world-map').addEventListener('click', (event) => {
                const link = event.target.closest('a.source-link');

                if (! link) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                window.location.assign(link.href);
            });
        </script>
    </div>
@endsection
