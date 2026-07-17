@php
    $appName = config('periscope.name', 'Periscope');
    $themeLabels = [
        'default' => 'Open Water',
        'harbor' => 'Harbor',
        'meadow' => 'Meadow',
        'submarine' => 'Into the Depths',
        'abyss' => 'Abyss',
    ];
    $themeGroups = [
        'Light themes' => ['default', 'harbor', 'meadow'],
        'Dark themes' => ['submarine', 'abyss'],
    ];
    $allowedThemes = array_keys($themeLabels);
    $configuredTheme = strtolower((string) config('periscope.theme', 'default'));
    $sessionTheme = strtolower((string) session('periscope.theme', ''));
    $resolvedTheme = in_array($sessionTheme, $allowedThemes, true) ? $sessionTheme : $configuredTheme;
    $periscopeTheme = in_array($resolvedTheme, $allowedThemes, true) ? $resolvedTheme : 'default';
    $topbarAction = $topbarAction ?? route('periscope.index');
    $filters = $filters ?? \TortoiseIT\LaravelPeriscope\Support\EntryFilters::fromRequest(request());
    $tags = $tags ?? collect();
    $primaryTypes = ['request', 'log', 'mail', 'command', 'job', 'schedule'];
    $typeCounts = isset($typeCounts) ? $typeCounts : collect();
    $typeCountByType = $typeCounts->keyBy('type');
    $primaryTypeCounts = collect($primaryTypes)
        ->map(fn ($type) => $typeCountByType->get($type) ?? (object) ['type' => $type, 'total' => 0]);
    $secondaryTypeCounts = $typeCounts
        ->reject(fn ($typeCount) => in_array($typeCount->type, $primaryTypes, true))
        ->sortBy('type')
        ->values();
    $svgIcon = function (string $name): string {
        $paths = [
            'alert' => '<path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 4.2 2.7 17.4A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.6L13.7 4.2a2 2 0 0 0-3.4 0Z"/>',
            'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
            'briefcase' => '<path d="M9 6V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1"/><path d="M3 8h18v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M3 13h18"/>',
            'braces' => '<path d="M8 3H7a2 2 0 0 0-2 2v4a2 2 0 0 1-2 2 2 2 0 0 1 2 2v4a2 2 0 0 0 2 2h1"/><path d="M16 3h1a2 2 0 0 1 2 2v4a2 2 0 0 0 2 2 2 2 0 0 0-2 2v4a2 2 0 0 1-2 2h-1"/>',
            'circle' => '<circle cx="12" cy="12" r="7"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'database' => '<ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5"/><path d="M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
            'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>',
            'gauge' => '<path d="M4 14a8 8 0 0 1 16 0"/><path d="M12 14l4-4"/><path d="M6.3 18h11.4"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
            'layers' => '<path d="m12 3 9 5-9 5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>',
            'layout' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M9 10v10"/>',
            'lock' => '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
            'rocket' => '<path d="M5 15c-1 1-2 4-2 6 2 0 5-1 6-2"/><path d="M9 15 4 10c3-6 9-8 17-7-1 8-3 14-9 17Z"/><path d="M15 9h.01"/>',
            'route' => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h3a3 3 0 0 0 0-6H9a3 3 0 0 1 0-6h7"/>',
            'server' => '<rect x="4" y="4" width="16" height="6" rx="2"/><rect x="4" y="14" width="16" height="6" rx="2"/><path d="M8 7h.01"/><path d="M8 17h.01"/>',
            'table' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18"/><path d="M9 4v16"/><path d="M15 4v16"/>',
            'terminal' => '<path d="m4 7 5 5-5 5"/><path d="M12 19h8"/>',
            'zap' => '<path d="M13 2 4 14h8l-1 8 9-12h-8Z"/>',
        ];

        return '<svg viewBox="0 0 24 24" aria-hidden="true">'.($paths[$name] ?? $paths['circle']).'</svg>';
    };
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $appName }}</title>
    <link rel="icon" type="image/png" href="{{ route('periscope.assets', ['asset' => 'periscope-favicon.png']) }}">
    <script>document.documentElement.classList.add('js');</script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        :root {
            color-scheme: light;
            --bg: #eef7fb;
            --panel: #ffffff;
            --panel-head: #f4fbfd;
            --line: #cfe3ec;
            --text: #13223f;
            --muted: #617489;
            --soft: #e2f2f7;
            --brand: #b85c38;
            --brand-dark: #102850;
            --brand-soft: #f4e3dc;
            --accent: #4a9fca;
            --accent-soft: #ddf4fb;
            --green: #11785e;
            --green-soft: #dff4ec;
            --blue: #2f79b8;
            --yellow: #7a5a12;
            --yellow-soft: #f6efd8;
            --red: #ba2f2f;
            --red-dark: #8f1d1d;
            --red-soft: #fde5e5;
            --code: #10223f;
            --code-text: #dff7ff;
            --sidebar-bg: #e1f0f6;
            --sidebar-text: #223954;
            --nav-section-title: #6c8497;
            --nav-item-text: #243c58;
            --link: var(--brand);
            --link-hover: var(--brand-dark);
            --nav-icon-bg: #cfe6f0;
            --nav-icon-text: #4b6f88;
            --nav-item-hover-bg: #d5eaf2;
            --nav-item-hover-text: var(--brand-dark);
            --nav-active-icon-text: #ffffff;
            --nav-count: #69849a;
            --topbar-bg: var(--brand-dark);
            --topbar-line: #081936;
            --topbar-text: #f5fbfe;
            --topbar-title: #ffffff;
            --topbar-muted: #cfeaf4;
            --topbar-shadow: 0 1px 14px rgb(16 40 80 / 22%);
            --topbar-control-border: rgb(255 255 255 / 16%);
            --topbar-control-bg: rgb(255 255 255 / 10%);
            --topbar-control-text: #ffffff;
            --topbar-label: #dceff7;
            --calendar-indicator-filter: invert(1);
            --calendar-indicator-opacity: .75;
            --field-bg: #ffffff;
            --field-border: #bfd9e6;
            --field-text: var(--text);
            --control-bg: #f8fcfe;
            --control-text: #294863;
            --button-secondary-bg: #dceff6;
            --button-secondary-text: #243c58;
            --sidebar-button-secondary-bg: #cfe6f0;
            --sidebar-button-secondary-text: #243c58;
            --table-head-text: #172947;
            --table-row-hover: #f7fcfe;
            --table-clickable-hover: #f3f9fc;
            --badge-text: #2d536b;
            --badge-info-text: #25759f;
            --title-text: #13223f;
            --title-link: #246d92;
            --title-link-hover: #174f6d;
            --meta-text: #586174;
            --popover-bg: #ffffff;
            --popover-shadow: 0 22px 70px rgb(16 40 80 / 24%);
            --local-filter-shadow: 0 18px 45px rgb(20 46 64 / 18%);
            --check-count-bg: #e7f4f8;
            --check-count-text: #456174;
            --mail-header-bg: #f7fcfe;
            --trace-card-bg: #f8fcfe;
            --trace-compact-bg: #ffffff;
            --trace-details-bg: #fbfdfe;
            --modal-bg: #ffffff;
            --health-card-bg: #f7fcfe;
            --health-error-line: #f2b9b9;
            --health-warn-line: #d8c586;
            --insight-card-bg: #ffffff;
            --mini-pill-text: #31556b;
            --timeline-line: #d3e4ec;
            --timeline-marker-bg: #ffffff;
            --timeline-marker-text: #426074;
            --timeline-card-bg: #ffffff;
            --timeline-card-line: #9bb8c8;
            --flow-phase-bg: linear-gradient(180deg, #fff 0, #f8fcfe 100%);
            --flow-node-bg: #ffffff;
            --flow-node-line: #9bb8c8;
            --flow-query-title: #314b5f;
            --phase-count-text: #426074;
            --node-kind-text: #244763;
            --node-meta-text: #2f6178;
            --error-line: #f2b9b9;
            --error-flow-line: #f0b5b5;
            --pre-error-bg: #fbfdfe;
            --pre-error-link-bg: #ffffff;
            --pre-error-link-hover: #f3f9fc;
            --modal-shadow: 0 24px 80px rgb(11 24 42 / 32%);
            --preview-frame-bg: #ffffff;
        }

        body.theme-submarine {
            color-scheme: dark;
            --bg: #02070d;
            --panel: #061522;
            --panel-head: #0a1d2e;
            --line: #13344a;
            --text: #b7ffd5;
            --muted: #5ed79b;
            --soft: #0d2533;
            --brand: #3df58f;
            --brand-dark: #abffd4;
            --brand-soft: #0d2f24;
            --accent: #4eb3ff;
            --accent-soft: #11283b;
            --green: #61ffa8;
            --green-soft: #0f3f2b;
            --blue: #6dbdff;
            --yellow: #f1d57a;
            --yellow-soft: #3b3414;
            --red: #ff6d6d;
            --red-dark: #e04c4c;
            --red-soft: #411919;
            --code: #010407;
            --code-text: #8dffbf;
            --sidebar-bg: #040d16;
            --sidebar-text: #96f7c2;
            --nav-section-title: #4bc78e;
            --nav-item-text: #a8ffd2;
            --link: #55ffab;
            --link-hover: #9cffcf;
            --nav-icon-bg: #0f2a3c;
            --nav-icon-text: #87ffd0;
            --nav-item-hover-bg: #0f2534;
            --nav-item-hover-text: #d3ffe8;
            --nav-active-icon-text: #03130b;
            --nav-count: #7de8b8;
            --topbar-bg: #04111c;
            --topbar-line: #17455f;
            --topbar-text: #bfffdc;
            --topbar-title: #d5ffe9;
            --topbar-muted: #92e6bc;
            --topbar-shadow: 0 1px 14px rgb(0 0 0 / 48%);
        }
        body.theme-harbor {
            color-scheme: light;
            --bg: #f2f7f5;
            --panel: #ffffff;
            --panel-head: #f3faf6;
            --line: #cadfd5;
            --text: #172b25;
            --muted: #61776e;
            --soft: #e2f0e9;
            --brand: #207f71;
            --brand-dark: #173f48;
            --brand-soft: #dff1ec;
            --accent: #c97842;
            --accent-soft: #f8e9dd;
            --green: #1d7458;
            --green-soft: #e1f3eb;
            --blue: #2f7eb6;
            --yellow: #7b5d10;
            --yellow-soft: #f7efd5;
            --red: #b3333e;
            --red-dark: #8d2330;
            --red-soft: #fbe4e7;
            --code: #132b31;
            --code-text: #e7fff6;
            --sidebar-bg: #e4f1eb;
            --sidebar-text: #21463f;
            --nav-section-title: #6b8278;
            --nav-item-text: #284a43;
            --nav-icon-bg: #cce4dc;
            --nav-icon-text: #347266;
            --nav-item-hover-bg: #d8ece5;
            --sidebar-button-secondary-bg: #d2e8df;
            --sidebar-button-secondary-text: #21463f;
            --nav-count: #6f8a80;
        }
        body.theme-meadow {
            color-scheme: light;
            --bg: #f7f7ef;
            --panel: #ffffff;
            --panel-head: #fbfbf1;
            --line: #dddcbf;
            --text: #2a2a1d;
            --muted: #74745d;
            --soft: #ecebd2;
            --brand: #6f7f24;
            --brand-dark: #2f4522;
            --brand-soft: #e7ebc7;
            --accent: #b2634a;
            --accent-soft: #f4e4dc;
            --green: #4f7427;
            --green-soft: #e8f0d8;
            --blue: #4b79b8;
            --yellow: #8b6114;
            --yellow-soft: #f7ecd0;
            --red: #b44038;
            --red-dark: #8d2c26;
            --red-soft: #f8e1df;
            --code: #252d1d;
            --code-text: #f1ffd9;
            --sidebar-bg: #ededcf;
            --sidebar-text: #3c4d2e;
            --nav-section-title: #7e8058;
            --nav-item-text: #3f502f;
            --nav-icon-bg: #dedfb9;
            --nav-icon-text: #63712a;
            --nav-item-hover-bg: #e5e7c6;
            --sidebar-button-secondary-bg: #e1e2bd;
            --sidebar-button-secondary-text: #3f502f;
            --nav-count: #7d8159;
        }
        body.theme-abyss {
            color-scheme: dark;
            --bg: #090a10;
            --panel: #12131c;
            --panel-head: #181a25;
            --line: #2b2d3d;
            --text: #edf0ff;
            --muted: #a4a9bd;
            --soft: #202231;
            --brand: #ff8f70;
            --brand-dark: #f5d4c9;
            --brand-soft: #3a231f;
            --accent: #8fa8ff;
            --accent-soft: #202946;
            --green: #7dd7a6;
            --green-soft: #193224;
            --blue: #8fa8ff;
            --yellow: #e7c66c;
            --yellow-soft: #382f18;
            --red: #ff7777;
            --red-dark: #e15858;
            --red-soft: #3b1d22;
            --code: #05060b;
            --code-text: #e9ecff;
            --sidebar-bg: #0d0f18;
            --sidebar-text: #dce1ff;
            --nav-section-title: #9ca3bf;
            --nav-item-text: #d3d9f7;
            --link: #ffab93;
            --link-hover: #ffc1b0;
            --nav-icon-bg: #202337;
            --nav-icon-text: #c4ccff;
            --nav-item-hover-bg: #1a1d2c;
            --nav-item-hover-text: #fff1eb;
            --nav-active-icon-text: #1a0b08;
            --nav-count: #adb5d9;
            --topbar-bg: #151723;
            --topbar-line: #303345;
            --topbar-text: #fff3ee;
            --topbar-title: #fff6f2;
            --topbar-muted: #c8cde8;
            --topbar-shadow: 0 1px 14px rgb(0 0 0 / 44%);
        }
        body:is(.theme-submarine, .theme-abyss) {
            --topbar-control-border: var(--line);
            --topbar-control-bg: var(--panel);
            --topbar-control-text: var(--text);
            --topbar-label: var(--brand-dark);
            --calendar-indicator-filter: none;
            --calendar-indicator-opacity: 1;
            --field-bg: var(--panel);
            --field-border: var(--line);
            --field-text: var(--text);
            --control-bg: var(--soft);
            --control-text: var(--text);
            --button-secondary-bg: var(--soft);
            --button-secondary-text: var(--text);
            --sidebar-button-secondary-bg: var(--soft);
            --sidebar-button-secondary-text: var(--text);
            --table-head-text: var(--text);
            --table-row-hover: var(--soft);
            --table-clickable-hover: var(--soft);
            --badge-text: var(--text);
            --badge-info-text: var(--text);
            --title-text: var(--text);
            --title-link: var(--text);
            --title-link-hover: var(--brand);
            --meta-text: var(--text);
            --popover-bg: var(--panel);
            --popover-shadow: 0 22px 70px rgb(0 0 0 / 32%);
            --local-filter-shadow: 0 18px 45px rgb(0 0 0 / 28%);
            --check-count-bg: var(--soft);
            --check-count-text: var(--text);
            --mail-header-bg: var(--panel);
            --trace-card-bg: var(--soft);
            --trace-compact-bg: var(--soft);
            --trace-details-bg: var(--panel);
            --modal-bg: var(--panel);
            --health-card-bg: var(--panel);
            --health-error-line: var(--line);
            --health-warn-line: var(--line);
            --insight-card-bg: var(--panel);
            --mini-pill-text: var(--text);
            --timeline-line: var(--line);
            --timeline-marker-bg: var(--panel);
            --timeline-marker-text: var(--text);
            --timeline-card-bg: var(--panel);
            --timeline-card-line: var(--line);
            --flow-phase-bg: linear-gradient(180deg, var(--panel) 0, var(--panel-head) 100%);
            --flow-node-bg: var(--panel);
            --flow-node-line: var(--line);
            --flow-query-title: var(--text);
            --phase-count-text: var(--text);
            --node-kind-text: var(--text);
            --node-meta-text: var(--text);
            --error-line: var(--line);
            --error-flow-line: var(--line);
            --pre-error-bg: var(--panel);
            --pre-error-link-bg: var(--panel);
            --pre-error-link-hover: var(--soft);
            --modal-shadow: 0 24px 80px rgb(0 0 0 / 36%);
            --preview-frame-bg: var(--panel);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: "Inter", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            font-weight: 400;
        }
        a { color: var(--link); text-decoration: none; }
        a:hover { color: var(--link-hover); text-decoration: underline; }
        .app-shell {
            display: grid;
            grid-template-columns: 264px minmax(0, 1fr);
            min-height: 100vh;
            width: 100%;
        }
        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid var(--line);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 18px 16px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 54px;
            margin-bottom: 18px;
        }
        .brand-logo {
            display: block;
            width: 48px;
            height: 48px;
            object-fit: contain;
        }
        .brand-caption {
            display: grid;
            gap: 2px;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0;
        }
        .brand-caption span:last-child {
            color: var(--muted);
            font-weight: 500;
        }
        .nav-section-title {
            margin: 18px 10px 8px;
            color: var(--nav-section-title);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .nav-list { display: grid; gap: 4px; }
        .nav-list + .nav-list { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
        .nav-item {
            display: grid;
            grid-template-columns: 32px 1fr auto;
            align-items: center;
            gap: 10px;
            min-height: 42px;
            border-radius: 8px;
            padding: 8px 10px;
            color: var(--nav-item-text);
            font-weight: 600;
        }
        .nav-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--nav-icon-bg);
            color: var(--nav-icon-text);
        }
        .nav-icon svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .nav-item:hover { background: var(--nav-item-hover-bg); color: var(--nav-item-hover-text); text-decoration: none; }
        .nav-item.active { background: var(--brand-soft); color: var(--brand-dark); }
        .nav-item.active .nav-icon { background: var(--brand); color: var(--nav-active-icon-text); }
        .nav-count {
            color: var(--nav-count);
            font-size: 11px;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
        }
        .sidebar-actions { display: grid; gap: 8px; margin-top: 18px; padding: 0 4px; }
        .theme-switcher {
            display: grid;
            gap: 6px;
            margin-top: 14px;
            padding: 0 4px;
        }
        .theme-switcher label {
            color: var(--nav-section-title);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .theme-switcher-controls {
            display: grid;
            grid-template-columns: 1fr;
        }
        .theme-switcher select {
            min-height: 34px;
            border-radius: 8px;
            background: var(--panel);
            color: var(--text);
        }
        .main-credit {
            margin-top: 26px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }
        .main-credit a {
            color: inherit;
            text-decoration: none;
        }
        .main-credit a:hover {
            color: var(--brand);
            text-decoration: underline;
        }
        .main { min-width: 0; }
        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: grid;
            grid-template-columns: minmax(220px, 1fr) auto;
            align-items: center;
            gap: 18px;
            min-height: 76px;
            border-bottom: 1px solid var(--topbar-line);
            background: var(--topbar-bg);
            color: var(--topbar-text);
            padding: 12px 24px;
            box-shadow: var(--topbar-shadow);
        }
        .topbar-title h2 {
            margin: 0;
            color: var(--topbar-title);
            font-size: 22px;
            line-height: 1.2;
            font-weight: 650;
        }
        .topbar-title p { margin: 4px 0 0; color: var(--topbar-muted); font-size: 13px; font-weight: 400; }
        .topbar-tools {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            min-width: 0;
        }
        .date-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .date-controls label {
            display: flex;
            grid-template-columns: none;
            align-items: center;
            gap: 6px;
            color: var(--topbar-label);
            font-size: 11px;
            font-weight: 700;
        }
        .date-controls input {
            min-height: 34px;
            width: 180px;
            border-color: var(--topbar-control-border);
            background: var(--topbar-control-bg);
            color: var(--topbar-control-text);
        }
        .date-controls input::-webkit-calendar-picker-indicator {
            filter: var(--calendar-indicator-filter);
            opacity: var(--calendar-indicator-opacity);
        }
        .filter-menu { position: relative; }
        .filter-menu summary {
            list-style: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 34px;
            border: 1px solid var(--topbar-control-border);
            border-radius: 8px;
            background: var(--topbar-control-bg);
            color: var(--topbar-control-text);
            cursor: pointer;
        }
        .filter-menu summary::-webkit-details-marker { display: none; }
        .filter-menu summary svg {
            width: 17px;
            height: 17px;
            stroke: currentColor;
            stroke-width: 2;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .filter-menu[open] summary { background: var(--brand); color: #fff; border-color: var(--brand); }
        .filter-tray {
            position: absolute;
            right: 0;
            top: calc(100% + 14px);
            width: min(760px, calc(100vw - 320px));
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--popover-bg);
            color: var(--text);
            box-shadow: var(--popover-shadow);
            padding: 16px;
        }
        .filter-tray::before {
            content: "";
            position: absolute;
            top: -7px;
            right: 14px;
            width: 14px;
            height: 14px;
            transform: rotate(45deg);
            border-left: 1px solid var(--line);
            border-top: 1px solid var(--line);
            background: var(--popover-bg);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: minmax(240px, 1.5fr) repeat(3, minmax(120px, 1fr));
            gap: 12px;
        }
        .filter-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
        }
        .live-note { margin-right: auto; }
        .content {
            min-width: 0;
            padding: 22px 24px 30px;
        }
        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgb(29 37 34 / 5%);
        }
        .panel-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: relative;
            min-height: 54px;
            border-bottom: 1px solid var(--line);
            padding: 0 16px;
            font-weight: 750;
        }
        .panel-title-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .overview-panel {
            overflow: hidden;
        }
        .overview-grid {
            display: grid;
            grid-template-columns: minmax(220px, .7fr) minmax(0, 1.3fr);
            gap: 0;
        }
        .overview-stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            border-right: 1px solid var(--line);
            background: var(--panel-head);
            padding: 16px;
        }
        .overview-stat {
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--panel);
            padding: 12px;
        }
        .overview-stat.linked {
            display: block;
            color: var(--text);
            text-decoration: none;
        }
        .overview-stat.linked:hover {
            border-color: var(--brand);
            color: var(--text);
            text-decoration: none;
        }
        .overview-stat strong {
            display: block;
            color: var(--brand-dark);
            font-size: 24px;
            line-height: 1;
            font-weight: 750;
        }
        .overview-stat span {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .overview-body {
            display: grid;
            gap: 14px;
            min-width: 0;
            padding: 16px;
        }
        .overview-error-list {
            display: grid;
            gap: 8px;
        }
        .overview-error-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            min-width: 0;
            border: 1px solid var(--error-line);
            border-left: 4px solid var(--red);
            border-radius: 8px;
            background: var(--flow-node-bg);
            padding: 10px 12px;
        }
        .overview-error-main {
            min-width: 0;
        }
        .overview-error-main a {
            color: var(--brand-dark);
            font-size: 13px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }
        .overview-error-main a:hover {
            color: var(--brand);
            text-decoration: none;
        }
        .overview-error-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 5px;
        }
        .overview-error-action {
            white-space: nowrap;
        }
        .overview-board {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: start;
            gap: 16px;
        }
        .overview-column {
            display: grid;
            align-content: start;
            gap: 16px;
            min-width: 0;
        }
        .snapshot-card {
            overflow: hidden;
        }
        .snapshot-list {
            display: grid;
            gap: 8px;
            max-height: 360px;
            overflow: auto;
            padding: 14px;
        }
        .snapshot-list.issue-list {
            max-height: 520px;
        }
        .snapshot-list.compact {
            max-height: 300px;
        }
        .snapshot-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            min-width: max-content;
            padding: 0.25em 0.5em;
        }
        .snapshot-actions .check-count,
        .snapshot-disclosure .check-count,
        .snapshot-row .check-count {
            white-space: nowrap;
        }
        .snapshot-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 12px;
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--flow-node-bg);
            padding: 10px 12px;
            color: var(--text);
        }
        .snapshot-row.linked:hover {
            border-color: var(--brand);
            color: var(--text);
            text-decoration: none;
        }
        .snapshot-row strong {
            display: block;
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .snapshot-disclosure {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--flow-node-bg);
            overflow: hidden;
        }
        .snapshot-disclosure summary {
            display: grid;
            grid-template-columns: 16px minmax(0, 1fr) auto;
            align-items: start;
            gap: 10px;
            padding: 10px 12px;
            cursor: pointer;
            list-style: none;
        }
        .snapshot-disclosure summary::-webkit-details-marker { display: none; }
        .snapshot-disclosure-caret {
            display: inline-grid;
            place-items: center;
            width: 16px;
            color: var(--brand);
            font-weight: 850;
            line-height: 1.2;
        }
        .snapshot-disclosure-caret::before {
            content: "+";
        }
        .snapshot-disclosure[open] .snapshot-disclosure-caret::before { content: "-"; }
        .snapshot-disclosure-title {
            min-width: 0;
            text-align: left;
        }
        .snapshot-disclosure-title strong,
        .snapshot-disclosure-title .subtitle {
            display: block;
        }
        .snapshot-disclosure-body {
            display: grid;
            gap: 6px;
            border-top: 1px solid var(--line);
            padding: 10px 12px 12px;
        }
        .snapshot-mini-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: start;
            gap: 10px;
            border-radius: 6px;
            padding: 6px 7px;
            color: var(--text);
            font-size: 12px;
        }
        .snapshot-mini-row:hover {
            background: var(--soft);
            color: var(--brand-dark);
            text-decoration: none;
        }
        .snapshot-mini-row span:first-child {
            min-width: 0;
            overflow-wrap: anywhere;
        }
        .snapshot-mini-row span:last-child {
            color: var(--meta-text);
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        label { display: grid; gap: 5px; color: var(--muted); font-size: 12px; font-weight: 700; }
        input, select {
            width: 100%;
            min-height: 36px;
            border: 1px solid var(--field-border);
            border-radius: 8px;
            padding: 7px 11px;
            color: var(--field-text);
            background: var(--field-bg);
            font: inherit;
        }
        .buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        button, .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            border: 1px solid var(--brand);
            border-radius: 8px;
            padding: 6px 12px;
            background: var(--brand);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .button:hover, button:hover { text-decoration: none; filter: brightness(.98); }
        .text-button {
            min-height: auto;
            border: 0;
            border-radius: 0;
            padding: 0;
            background: transparent;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 700;
        }
        .text-button:hover { filter: none; text-decoration: underline; }
        .button.secondary, button.secondary {
            border-color: var(--line);
            background: var(--button-secondary-bg);
            color: var(--button-secondary-text);
        }
        .topbar .button.secondary {
            border-color: var(--topbar-control-border);
            background: var(--topbar-control-bg);
            color: var(--topbar-control-text);
        }
        .topbar .button.secondary.active {
            border-color: var(--brand);
            background: var(--brand);
        }
        .sidebar .button.secondary { background: var(--sidebar-button-secondary-bg); color: var(--sidebar-button-secondary-text); }
        .icon-button { gap: 7px; }
        .icon-button svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.9;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .hint { color: var(--muted); font-size: 12px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: middle;
        }
        th {
            color: var(--table-head-text);
            font-size: 12px;
            font-weight: 750;
            background: var(--panel-head);
            white-space: nowrap;
        }
        tr:hover td { background: var(--table-row-hover); }
        tr.clickable-row { cursor: pointer; }
        tr.clickable-row:hover td { background: var(--table-clickable-hover); }
        tr.filtered-out { display: none; }
        .type, .badge {
            display: inline-flex;
            align-items: center;
            min-height: 22px;
            border-radius: 6px;
            padding: 2px 7px;
            background: var(--soft);
            color: var(--badge-text);
            font-size: 12px;
            font-weight: 750;
            white-space: nowrap;
        }
        .badge.error { background: var(--red-dark); color: #fff; }
        .badge.ok { background: var(--green-soft); color: var(--green); }
        .badge.warn { background: var(--yellow-soft); color: var(--yellow); }
        .badge.info { background: var(--accent-soft); color: var(--badge-info-text); }
        .title { max-width: 980px; font-weight: 500; overflow-wrap: anywhere; color: var(--title-text); }
        .title a { color: var(--title-link); font-weight: 500; }
        .title a:hover { color: var(--title-link-hover); text-decoration: none; }
        .subtitle { margin-top: 4px; color: var(--muted); font-size: 12px; overflow-wrap: anywhere; }
        .meta { color: var(--meta-text); white-space: nowrap; font-variant-numeric: tabular-nums; }
        .empty { padding: 38px 18px; text-align: center; color: var(--muted); }
        .pager { display: flex; justify-content: flex-end; padding: 12px 16px; }
        .detail-stack { display: grid; gap: 24px; }
        .card-filter-menu { position: relative; }
        .card-filter-menu summary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--control-bg);
            color: var(--control-text);
            cursor: pointer;
            list-style: none;
        }
        .card-filter-menu summary::-webkit-details-marker { display: none; }
        .card-filter-menu summary svg {
            width: 17px;
            height: 17px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
        }
        .card-filter-menu[open] summary {
            border-color: var(--brand);
            background: var(--brand);
            color: #fff;
        }
        .card-filter-menu .local-filter-body {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 25;
            width: min(720px, calc(100vw - 310px));
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--popover-bg);
            box-shadow: var(--local-filter-shadow);
        }
        .local-filter-body {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) minmax(320px, 1fr);
            gap: 16px;
            padding: 16px;
        }
        .type-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px;
        }
        .check-row {
            display: flex;
            grid-template-columns: none;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--control-bg);
            padding: 6px 9px;
            color: var(--control-text);
            font-size: 13px;
            font-weight: 650;
        }
        .check-row input {
            width: auto;
            min-height: 0;
            margin: 0;
        }
        .check-count {
            margin-left: auto;
            border-radius: 999px;
            background: var(--check-count-bg);
            color: var(--check-count-text);
            padding: 2px 7px;
            font-size: 11px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .pattern-filter {
            align-self: stretch;
        }
        textarea {
            width: 100%;
            min-height: 82px;
            border: 1px solid var(--field-border);
            border-radius: 8px;
            padding: 8px 10px;
            color: var(--text);
            background: var(--field-bg);
            font: inherit;
            resize: vertical;
        }
        .filter-actions.compact {
            grid-column: 1 / -1;
            margin-top: 0;
            padding-top: 12px;
        }
        .detail-summary {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(260px, .6fr);
            gap: 16px;
        }
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 650;
        }
        .breadcrumb a { color: var(--brand-dark); }
        .summary-card {
            padding: 16px;
        }
        .summary-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .summary-card h3 {
            margin: 0 0 12px;
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }
        .summary-item {
            min-width: 0;
        }
        .summary-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
        }
        .summary-value {
            margin-top: 4px;
            color: var(--text);
            font-weight: 600;
            overflow-wrap: anywhere;
        }
        .mail-header-block {
            grid-column: 1 / -1;
            display: grid;
            gap: 10px;
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--mail-header-bg);
            padding: 12px;
        }
        .mail-participants {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            min-width: 0;
        }
        .mail-participants > div,
        .mail-subject {
            min-width: 0;
        }
        .mail-address,
        .mail-subject > div:last-child {
            display: block;
            margin-top: 4px;
            color: var(--text);
            font-weight: 600;
            overflow-wrap: anywhere;
        }
        .summary-card-head h3 { margin-bottom: 0; }
        .muted {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
        }
        .tag-link {
            width: max-content;
            margin-top: 6px;
        }
        .user-tags-block {
            display: grid;
            gap: 12px;
        }
        .user-tags-block .tags {
            margin-top: 0;
        }
        .code-card { overflow: hidden; }
        .tabbar {
            display: flex;
            gap: 18px;
            align-items: center;
            min-height: 42px;
            border-bottom: 1px solid var(--line);
            background: var(--panel);
            padding: 0 16px;
        }
        .tabbar span {
            align-self: stretch;
            display: inline-flex;
            align-items: center;
            border-bottom: 2px solid transparent;
            color: var(--muted);
            font-size: 12px;
            font-weight: 750;
        }
        .tabbar span.active { border-bottom-color: var(--accent); color: var(--brand-dark); }
        .tabbar button {
            align-self: stretch;
            min-height: 42px;
            border: 0;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 0;
            background: transparent;
            color: var(--muted);
            font-size: 12px;
            font-weight: 750;
        }
        .tabbar button.active { border-bottom-color: var(--accent); color: var(--brand-dark); }
        .tab-panel[hidden] { display: none; }
        .lifecycle-tabs { overflow: hidden; }
        .lifecycle-tabs .panel-title { border-top: 0; }
        pre {
            margin: 0;
            padding: 16px;
            overflow: auto;
            max-height: 300px;
            background: var(--code);
            color: var(--code-text);
            line-height: 1.45;
            white-space: pre-wrap;
            font-size: 12px;
        }
        .code-block { background: var(--code); }
        .raw-scroll pre, .context-card pre { max-height: 300px; }
        .stack-card { overflow: hidden; }
        .trace-list {
            display: grid;
            gap: 8px;
            padding: 14px;
        }
        .trace-list.compact {
            gap: 6px;
            padding: 0;
        }
        .trace-frame {
            display: grid;
            gap: 4px;
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--trace-card-bg);
            padding: 10px 12px;
        }
        .trace-list.compact .trace-frame {
            background: var(--trace-compact-bg);
            padding: 8px 10px;
        }
        .trace-frame-main {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
        }
        .trace-file {
            min-width: 0;
            color: var(--brand-dark);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            font-weight: 750;
            overflow-wrap: anywhere;
        }
        .trace-call {
            color: var(--muted);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            overflow-wrap: anywhere;
        }
        .source-preview {
            overflow: hidden;
            border: 1px solid #c9dce5;
            border-radius: 7px;
            background: #0f2138;
            color: #dff7ff;
        }
        .source-preview-wrap {
            position: relative;
            display: block;
        }
        .source-toggle {
            position: absolute;
            top: 7px;
            right: 7px;
            z-index: 3;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 1px solid rgb(255 255 255 / 28%);
            border-radius: 6px;
            background: rgb(15 33 56 / 86%);
            color: #fff;
            cursor: pointer;
            padding: 0;
        }
        .source-toggle span {
            position: absolute;
            width: 1px;
            height: 1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
        }
        .source-toggle::before {
            content: "+";
            font-size: 18px;
            font-weight: 850;
            line-height: 1;
        }
        .source-preview-wrap.expanded .source-toggle::before { content: "-"; }
        .source-toggle:hover, .source-toggle:focus-visible { background: var(--brand); }
        .source-scroll {
            max-height: 420px;
            display: none;
            overflow: auto;
            overscroll-behavior: contain;
        }
        .source-preview-wrap.expanded .source-compact { display: none; }
        .source-preview-wrap.expanded .source-scroll { display: block; }
        .source-line {
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr);
            min-width: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 1.55;
        }
        .source-line.is-target {
            background: rgb(74 159 202 / 16%);
            box-shadow: inset 3px 0 0 var(--accent);
        }
        .source-number {
            padding: 0 10px;
            color: #7fa7ba;
            text-align: right;
            user-select: none;
        }
        .source-line code {
            display: block;
            min-width: 0;
            padding-right: 12px;
            color: inherit;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }
        .syntax-keyword { color: #f4c06a; }
        .syntax-variable { color: #8be9fd; }
        .syntax-string { color: #a9e68f; }
        .syntax-comment { color: #7f92a8; }
        .trace-details {
            border-top: 1px solid var(--line);
            background: var(--trace-details-bg);
        }
        .trace-details.compact {
            border-top: 0;
            background: transparent;
        }
        .trace-details summary {
            cursor: pointer;
            padding: 10px 14px;
            color: var(--brand);
            font-size: 12px;
            font-weight: 750;
        }
        .trace-details.compact summary { padding: 0; }
        .mail-preview {
            width: 100%;
            min-height: 420px;
            border: 0;
            background: var(--modal-bg);
        }
        .lifecycle-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, .65fr);
            gap: 16px;
        }
        .lifecycle-overview .summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .periscope-modal {
            width: min(920px, calc(100vw - 32px));
            border: 0;
            padding: 0;
            background: transparent;
        }
        .periscope-modal::backdrop {
            background: rgb(11 24 42 / 58%);
        }
        .modal-card {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: var(--modal-bg);
            box-shadow: var(--modal-shadow);
        }
        .modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid var(--line);
        }
        .modal-head h3 {
            margin: 0;
            color: var(--brand-dark);
            font-size: 13px;
            font-weight: 750;
        }
        .periscope-modal pre {
            border-radius: 0;
            max-height: min(70vh, 620px);
        }
        .health-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .health-grid > div {
            min-height: 62px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--health-card-bg);
            padding: 10px;
        }
        .health-grid strong {
            display: block;
            color: var(--brand-dark);
            font-size: 22px;
            line-height: 1;
            font-weight: 700;
        }
        .health-grid span {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .health-grid .has-error { background: var(--red-soft); border-color: var(--health-error-line); }
        .health-grid .has-error strong { color: var(--red); }
        .health-grid .has-warn { background: var(--yellow-soft); border-color: var(--health-warn-line); }
        .health-grid .has-warn strong { color: var(--yellow); }
        .insight-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
        }
        .insight-card {
            display: grid;
            align-content: start;
            gap: 10px;
            min-width: 0;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--insight-card-bg);
            padding: 12px;
        }
        .insight-card h3, .pre-error-card h3 {
            margin: 0;
            color: var(--brand-dark);
            font-size: 13px;
            font-weight: 750;
        }
        .insight-list {
            display: grid;
            gap: 7px;
            margin: 0;
        }
        .insight-list div {
            display: grid;
            grid-template-columns: 88px minmax(0, 1fr);
            gap: 8px;
        }
        .insight-list dt {
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .insight-list dd {
            margin: 0;
            color: var(--text);
            font-size: 12px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }
        .insight-note {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }
        .insight-mini-list, .pre-error-items {
            display: grid;
            gap: 6px;
        }
        .insight-mini-list span {
            border-radius: 6px;
            background: var(--soft);
            color: var(--mini-pill-text);
            padding: 5px 7px;
            font-size: 11px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .timeline-list {
            display: grid;
            padding: 16px;
        }
        .timeline-item {
            position: relative;
            display: grid;
            grid-template-columns: 78px minmax(0, 1fr);
            gap: 12px;
            min-width: 0;
        }
        .timeline-item::before {
            content: "";
            position: absolute;
            left: 38px;
            top: 36px;
            bottom: -6px;
            width: 2px;
            background: var(--timeline-line);
        }
        .timeline-item:last-child::before { display: none; }
        .timeline-marker {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: center;
        }
        .timeline-marker span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 76px;
            height: 30px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--timeline-marker-bg);
            color: var(--timeline-marker-text);
            font-size: 11px;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
        }
        .timeline-card {
            display: grid;
            gap: 8px;
            min-width: 0;
            margin-bottom: 12px;
            border: 1px solid var(--line);
            border-left: 4px solid var(--timeline-card-line);
            border-radius: 8px;
            background: var(--timeline-card-bg);
            padding: 12px;
            box-shadow: 0 1px 2px rgb(16 40 80 / 7%);
        }
        .timeline-item.selected .timeline-card {
            border-color: var(--brand);
            border-left-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-soft);
        }
        .timeline-item.severity-ok .timeline-card { border-left-color: var(--green); }
        .timeline-item.severity-info .timeline-card { border-left-color: var(--blue); }
        .timeline-item.severity-warn .timeline-card { border-left-color: var(--yellow); }
        .timeline-item.severity-error .timeline-card { border-left-color: var(--red); }
        .timeline-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .timeline-head a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 750;
        }
        .timeline-title {
            color: var(--text);
            font-size: 13px;
            font-weight: 650;
            overflow-wrap: anywhere;
        }
        .timeline-duration {
            overflow: hidden;
            height: 7px;
            border-radius: 999px;
            background: var(--soft);
        }
        .timeline-duration span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--accent);
        }
        .timeline-item.severity-warn .timeline-duration span { background: var(--yellow); }
        .timeline-item.severity-error .timeline-duration span { background: var(--red); }
        .flow-board {
            display: grid;
            grid-template-columns: repeat(var(--flow-columns, 1), minmax(220px, 1fr));
            gap: 0;
            overflow-x: auto;
        }
        .flow-phase {
            min-width: 0;
            border-right: 1px solid var(--line);
            background: var(--flow-phase-bg);
        }
        .flow-phase:last-child { border-right: 0; }
        .phase-title {
            position: relative;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 48px;
            border-bottom: 1px solid var(--line);
            background: var(--panel-head);
            padding: 0 14px;
            color: var(--brand-dark);
            font-weight: 750;
        }
        .phase-title span:last-child {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 26px;
            min-height: 22px;
            border-radius: 999px;
            background: var(--soft);
            color: var(--phase-count-text);
            font-size: 11px;
            font-weight: 800;
        }
        .flow-lane {
            display: grid;
            gap: 12px;
            padding: 16px;
        }
        .flow-node {
            display: grid;
            gap: 8px;
            min-width: 0;
            border: 1px solid var(--line);
            border-left: 4px solid var(--flow-node-line);
            border-radius: 8px;
            background: var(--flow-node-bg);
            padding: 12px 12px 12px 16px;
            box-shadow: 0 1px 2px rgb(16 40 80 / 7%);
        }
        .flow-node.selected {
            border-color: var(--brand);
            border-left-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-soft);
        }
        .flow-node.request-node { border-left-color: var(--accent); }
        .flow-node.severity-ok { border-left-color: var(--green); }
        .flow-node.severity-info { border-left-color: var(--blue); }
        .flow-node.severity-warn { border-left-color: var(--yellow); }
        .flow-node.severity-error { border-left-color: var(--red); }
        .flow-node.query-node {
            gap: 5px;
            padding-top: 9px;
            padding-bottom: 9px;
        }
        .flow-node.query-node .node-title {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            color: var(--flow-query-title);
            font-size: 12px;
            line-height: 1.35;
            font-weight: 500;
        }
        .flow-node.query-node .node-meta span {
            min-height: 18px;
            padding: 1px 7px;
            font-size: 10px;
        }
        .error-trail-list {
            display: grid;
            gap: 12px;
            padding: 16px;
        }
        .error-trail-item {
            display: grid;
            gap: 8px;
            border: 1px solid var(--error-line);
            border-left: 4px solid var(--red);
            border-radius: 8px;
            background: var(--flow-node-bg);
            padding: 12px;
        }
        .error-trail-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .error-trail-head a {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 750;
        }
        .error-trail-title {
            color: var(--text);
            font-size: 13px;
            font-weight: 650;
            overflow-wrap: anywhere;
        }
        .pre-error-list {
            display: grid;
            gap: 12px;
            border-top: 1px solid var(--line);
            padding: 16px;
        }
        .pre-error-card {
            display: grid;
            gap: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--pre-error-bg);
            padding: 12px;
        }
        .pre-error-items a {
            display: grid;
            grid-template-columns: 90px minmax(0, 1fr) auto;
            gap: 8px;
            align-items: center;
            border-radius: 6px;
            background: var(--pre-error-link-bg);
            padding: 7px 8px;
            color: var(--text);
        }
        .pre-error-items a:hover { text-decoration: none; background: var(--pre-error-link-hover); }
        .pre-error-items span {
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .pre-error-items strong {
            min-width: 0;
            font-size: 12px;
            font-weight: 650;
            overflow-wrap: anywhere;
        }
        .pre-error-items em {
            color: var(--muted);
            font-size: 11px;
            font-style: normal;
            font-weight: 750;
            white-space: nowrap;
        }
        .error-flow {
            display: grid;
            gap: 0;
            padding: 16px;
        }
        .error-flow-item {
            position: relative;
            display: grid;
            grid-template-columns: 42px minmax(0, 1fr);
            gap: 12px;
            min-width: 0;
        }
        .error-flow-item::before {
            content: "";
            position: absolute;
            left: 20px;
            top: 40px;
            bottom: -8px;
            width: 2px;
            background: var(--error-flow-line);
        }
        .error-flow-item:last-child::before { display: none; }
        .error-flow-marker {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border: 2px solid var(--error-flow-line);
            border-radius: 999px;
            background: var(--red-dark);
            color: #fff;
            font-size: 12px;
            font-weight: 850;
            font-variant-numeric: tabular-nums;
        }
        .error-flow-card {
            display: grid;
            gap: 8px;
            min-width: 0;
            margin-bottom: 14px;
            border: 1px solid var(--error-line);
            border-radius: 8px;
            background: var(--flow-node-bg);
            padding: 12px;
        }
        .error-flow-item.current .error-flow-card {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-soft);
        }
        .node-link {
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 750;
        }
        .node-link:hover { text-decoration: none; color: var(--brand); }
        .node-sequence {
            color: var(--muted);
            font-variant-numeric: tabular-nums;
        }
        .node-kind {
            color: var(--node-kind-text);
        }
        .node-title {
            color: var(--text);
            font-size: 13px;
            font-weight: 550;
            overflow-wrap: anywhere;
        }
        .node-subtitle {
            color: var(--muted);
            font-size: 12px;
            overflow-wrap: anywhere;
        }
        .node-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            min-width: 0;
        }
        .node-meta span {
            display: inline-block;
            max-width: 100%;
            min-width: 0;
            min-height: 21px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--node-meta-text);
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1.35;
            overflow-wrap: anywhere;
            white-space: normal;
        }
        .tags { display: flex; flex-wrap: wrap; gap: 6px; }
        .preview-frame { width: 100%; min-height: 320px; border: 0; background: var(--preview-frame-bg); }
        @media (max-width: 1180px) {
            .app-shell { grid-template-columns: 220px minmax(0, 1fr); }
            .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .date-controls input { width: 158px; }
            .filter-tray { width: min(680px, calc(100vw - 272px)); }
        }
        @media (max-width: 860px) {
            .app-shell { grid-template-columns: 1fr; }
            .sidebar { position: static; height: auto; }
            .topbar { position: static; grid-template-columns: 1fr; }
            .topbar-tools { justify-content: flex-start; flex-wrap: wrap; }
            .date-controls { flex-wrap: wrap; }
            .filter-tray { position: fixed; left: 14px; right: 14px; top: 84px; width: auto; }
            .card-filter-menu .local-filter-body { position: fixed; left: 14px; right: 14px; top: 84px; width: auto; }
            .filter-grid, .local-filter-body, .overview-grid, .overview-board { grid-template-columns: 1fr; }
            .overview-stats { border-right: 0; border-bottom: 1px solid var(--line); }
            .overview-error-item { grid-template-columns: 1fr; }
            .overview-error-action { white-space: normal; }
            .detail-summary, .summary-grid, .lifecycle-hero, .lifecycle-overview .summary-grid, .insight-grid { grid-template-columns: 1fr; }
            .mail-participants { grid-template-columns: 1fr; }
            .phase-title { position: static; }
            th, td { padding: 10px; }
        }
    </style>
</head>
<body class="theme-{{ $periscopeTheme }}">
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <img class="brand-logo" src="{{ route('periscope.assets', ['asset' => 'periscope-logo.png']) }}" alt="{{ $appName }}">
                <span class="brand-caption">
                    <span>{{ $appName }}</span>
                    <span>Telescope companion</span>
                </span>
            </div>

            @if ($typeCounts->isNotEmpty())
                <nav aria-label="Entry types">
                    <div class="nav-list">
                        <a class="nav-item {{ request()->routeIs('periscope.index') ? 'active' : '' }}" href="{{ route('periscope.index', request()->except('type', 'before', 'uuid')) }}">
                            <span class="nav-icon">{!! $svgIcon('gauge') !!}</span>
                            <span>Overview</span>
                            <span class="nav-count">{{ number_format($typeCounts->sum('total')) }}</span>
                        </a>
                        <a class="nav-item {{ request()->routeIs('periscope.entries.index') && ! request('type') ? 'active' : '' }}" href="{{ route('periscope.entries.index', request()->except('type', 'before', 'uuid')) }}">
                            <span class="nav-icon">{!! $svgIcon('circle') !!}</span>
                            <span>All entries</span>
                            <span class="nav-count">{{ number_format($typeCounts->sum('total')) }}</span>
                        </a>
                    </div>

                    <div class="nav-section-title">Primary</div>
                    <div class="nav-list">
                        @foreach ($primaryTypeCounts as $typeCount)
                            <a class="nav-item {{ request()->routeIs('periscope.entries.index') && request('type') === $typeCount->type ? 'active' : '' }}" href="{{ route('periscope.entries.index', array_merge(request()->except('before', 'uuid'), ['type' => $typeCount->type])) }}">
                                <span class="nav-icon">{!! $svgIcon(\TortoiseIT\LaravelPeriscope\Support\EntryType::iconFor($typeCount->type)) !!}</span>
                                <span>{{ \TortoiseIT\LaravelPeriscope\Support\EntryType::labelFor($typeCount->type) }}</span>
                                <span class="nav-count">{{ number_format($typeCount->total) }}</span>
                            </a>
                        @endforeach
                    </div>

                    <div class="nav-section-title">Other</div>
                    <div class="nav-list">
                        @foreach ($secondaryTypeCounts as $typeCount)
                            <a class="nav-item {{ request()->routeIs('periscope.entries.index') && request('type') === $typeCount->type ? 'active' : '' }}" href="{{ route('periscope.entries.index', array_merge(request()->except('before', 'uuid'), ['type' => $typeCount->type])) }}">
                                <span class="nav-icon">{!! $svgIcon(\TortoiseIT\LaravelPeriscope\Support\EntryType::iconFor($typeCount->type)) !!}</span>
                                <span>{{ \TortoiseIT\LaravelPeriscope\Support\EntryType::labelFor($typeCount->type) }}</span>
                                <span class="nav-count">{{ number_format($typeCount->total) }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif

            <div class="sidebar-actions">
                <a class="button secondary" href="{{ url(config('telescope.path', 'telescope')) }}">Open Telescope</a>
                <a class="button secondary" href="{{ $topbarAction }}">Reset</a>
            </div>

            <form class="theme-switcher" method="post" action="{{ route('periscope.theme') }}">
                @csrf
                <label for="periscope-theme">Theme</label>
                <div class="theme-switcher-controls">
                    <select id="periscope-theme" name="theme" onchange="this.form.submit()">
                        @foreach ($themeGroups as $groupLabel => $themes)
                            <optgroup label="{{ $groupLabel }}">
                                @foreach ($themes as $theme)
                                    <option value="{{ $theme }}" @selected($periscopeTheme === $theme)>{{ $themeLabels[$theme] }}{{ $theme === 'default' ? ' (default)' : '' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
            </form>
        </aside>

        <main class="main">
            <form class="topbar autosubmit" method="get" action="{{ $topbarAction }}">
                @if ($filters->type)
                    <input type="hidden" name="type" value="{{ $filters->type }}">
                @elseif ($filters->types)
                    @foreach ($filters->types as $selectedType)
                        <input type="hidden" name="types[]" value="{{ $selectedType }}">
                    @endforeach
                @endif
                <div class="topbar-title">
                    <h2>@yield('page-title', 'Entries')</h2>
                    <p>@yield('page-subtitle', 'Filter, inspect, and deep-link Telescope data.')</p>
                </div>
                <div class="topbar-tools">
                    <div class="date-controls">
                        <label>
                            From
                            <input type="datetime-local" name="from" value="{{ $filters->from?->format('Y-m-d\TH:i') }}">
                        </label>
                        <label>
                            To
                            <input type="datetime-local" name="to" value="{{ $filters->to?->format('Y-m-d\TH:i') }}">
                        </label>
                    </div>
                    <details class="filter-menu">
                        <summary aria-label="Open filters" title="Filters">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M4 5h16"/>
                                <path d="M7 12h10"/>
                                <path d="M10 19h4"/>
                            </svg>
                        </summary>
                        <div class="filter-tray">
                            <div class="filter-grid">
                                <label>
                                    Search
                                    <input type="search" name="q" value="{{ $filters->query }}" placeholder="Search title, body, SQL, exception, headers">
                                </label>
                                <label>
                                    Method
                                    <input name="method" value="{{ $filters->method }}" placeholder="POST">
                                </label>
                                <label>
                                    Status
                                    <input name="status" value="{{ $filters->status }}" placeholder="500">
                                </label>
                                <label>
                                    Per page
                                    <select name="per_page">
                                        @foreach ([25, 50, 100, 150, 200] as $pageSize)
                                            <option value="{{ $pageSize }}" @selected($filters->perPage === $pageSize)>{{ $pageSize }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>
                                    Path
                                    <input name="path" value="{{ $filters->path }}" placeholder="/checkout">
                                </label>
                                <label>
                                    Tag
                                    <input name="tag" value="{{ $filters->tag }}" list="known-tags" placeholder="Auth:1">
                                    <datalist id="known-tags">
                                        @foreach ($tags as $tag)
                                            <option value="{{ $tag }}"></option>
                                        @endforeach
                                    </datalist>
                                </label>
                                <label class="check-row filter-toggle">
                                    <input type="checkbox" name="errors" value="1" @checked($filters->errorsOnly)>
                                    <span>Errors only</span>
                                </label>
                            </div>
                            <div class="filter-actions">
                                <span class="hint live-note">{{ $filters->activeCount() }} active {{ \Illuminate\Support\Str::plural('filter', $filters->activeCount()) }}. Changes update automatically.</span>
                                <div class="buttons">
                                    <a class="button secondary" href="{{ $topbarAction }}">Reset</a>
                                </div>
                            </div>
                        </div>
                    </details>
                    <div class="buttons">
                        @yield('topbar-actions')
                    </div>
                </div>
            </form>

            <div class="content">
                @yield('content')
                <div class="main-credit">
                    Built by <a href="https://sean-barton.co.uk" target="_blank" rel="noopener noreferrer">Tortoise IT</a>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.querySelectorAll('form.autosubmit').forEach((form) => {
            let timer;
            const submit = () => form.requestSubmit ? form.requestSubmit() : form.submit();

            form.querySelectorAll('input, select').forEach((field) => {
                const eventName = field.matches('input[type="search"], input[name="path"], input[name="tag"]') ? 'input' : 'change';
                field.addEventListener(eventName, () => {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(submit, eventName === 'input' ? 550 : 0);
                });
            });
        });

        document.querySelectorAll('tr[data-href]').forEach((row) => {
            row.addEventListener('click', (event) => {
                if (event.target.closest('a, button, input, select, summary')) {
                    return;
                }

                window.location.href = row.dataset.href;
            });
        });

        document.querySelectorAll('[data-tabs]').forEach((tabs) => {
            tabs.querySelectorAll('[data-tab-target]').forEach((button) => {
                button.addEventListener('click', () => {
                    tabs.querySelectorAll('[data-tab-target]').forEach((tab) => tab.classList.remove('active'));
                    tabs.querySelectorAll('[data-tab-panel]').forEach((panel) => panel.hidden = true);
                    button.classList.add('active');
                    tabs.querySelector('[data-tab-panel="'+button.dataset.tabTarget+'"]').hidden = false;
                });
            });
        });

        document.querySelectorAll('[data-source-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const preview = button.closest('[data-source-preview]');
                const isExpanded = preview?.classList.toggle('expanded') ?? false;

                button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
                button.setAttribute('title', isExpanded ? 'Collapse source preview' : 'Expand source preview');
                button.setAttribute('aria-label', isExpanded ? 'Collapse source preview' : 'Expand source preview');
            });
        });

        document.querySelectorAll('[data-copy-text]').forEach((button) => {
            button.addEventListener('click', async () => {
                const original = button.textContent;
                const value = button.dataset.copyEncoded === 'base64'
                    ? atob(button.dataset.copyText || '')
                    : (button.dataset.copyText || '');

                try {
                    await navigator.clipboard.writeText(value);
                    button.textContent = 'Copied';
                    window.setTimeout(() => button.textContent = original, 1200);
                } catch (error) {
                    button.textContent = 'Copy failed';
                    window.setTimeout(() => button.textContent = original, 1600);
                }
            });
        });

        document.querySelectorAll('[data-modal-open]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.querySelector('[data-modal="'+button.dataset.modalOpen+'"]');

                if (modal && typeof modal.showModal === 'function') {
                    modal.showModal();
                }
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => {
                button.closest('dialog')?.close();
            });
        });

        document.querySelectorAll('dialog.periscope-modal').forEach((dialog) => {
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        });

        (() => {
            const button = document.querySelector('[data-watch-toggle]');

            if (! button) {
                return;
            }

            const storageKey = 'periscope.watch.enabled';
            const available = button.dataset.watchAvailable === '1';
            const label = button.querySelector('span');
            let timer;
            let enabled = available && localStorage.getItem(storageKey) === '1';

            const render = () => {
                button.classList.toggle('active', enabled);
                button.disabled = ! available;
                button.title = available ? 'Watch open-ended list' : 'Watch is only available when the list has no To date or historical page cursor';
                if (label) {
                    label.textContent = enabled ? 'Watching' : 'Watch';
                }
            };

            const schedule = () => {
                window.clearInterval(timer);

                if (! enabled || ! available) {
                    return;
                }

                timer = window.setInterval(() => {
                    window.location.reload();
                }, 4500);
            };

            button.addEventListener('click', () => {
                if (! available) {
                    return;
                }

                enabled = ! enabled;
                localStorage.setItem(storageKey, enabled ? '1' : '0');
                render();
                schedule();
            });

            render();
            schedule();
        })();

        (() => {
            const panel = document.querySelector('[data-local-filter-panel]');

            if (! panel) {
                return;
            }

            const rows = [...document.querySelectorAll('[data-entry-row]')];
            const checks = [...panel.querySelectorAll('[data-type-filter]')];
            const patternInput = panel.querySelector('[data-exclude-patterns]');
            const reset = panel.querySelector('[data-local-filter-reset]');
            const summary = panel.querySelector('[data-local-filter-summary]');
            const visibleCount = document.querySelector('[data-visible-entry-count]');
            const typesKey = 'periscope.localFilters.types';
            const patternsKey = 'periscope.localFilters.patterns';
            const knownTypes = checks.map((check) => check.value);
            const url = new URL(window.location.href);
            const selectedFromUrl = [
                ...url.searchParams.getAll('types[]'),
                ...url.searchParams.getAll('types'),
            ].filter((type) => knownTypes.includes(type));

            const storedTypes = localStorage.getItem(typesKey);
            let selectedTypes = selectedFromUrl.length
                ? selectedFromUrl
                : storedTypes === null
                ? knownTypes
                : JSON.parse(storedTypes || '[]').filter((type) => knownTypes.includes(type));

            if (storedTypes !== null && selectedTypes.length === 0 && storedTypes !== '[]') {
                selectedTypes = knownTypes;
            }

            const patterns = () => (patternInput?.value || '')
                .split(/\r?\n/)
                .map((line) => line.trim().toLowerCase())
                .filter(Boolean);

            const apply = () => {
                const excludedPatterns = patterns();
                let visible = 0;

                rows.forEach((row) => {
                    const text = row.dataset.entrySearch || '';
                    const patternDenied = excludedPatterns.some((pattern) => text.includes(pattern));
                    const hidden = patternDenied;

                    row.classList.toggle('filtered-out', hidden);

                    if (! hidden) {
                        visible++;
                    }
                });

                localStorage.setItem(typesKey, JSON.stringify(selectedTypes));
                localStorage.setItem(patternsKey, patternInput?.value || '');

                if (visibleCount) {
                    visibleCount.textContent = visible;
                }

                if (summary) {
                    const disabledCount = knownTypes.length - selectedTypes.length;
                    const patternCount = excludedPatterns.length;
                    summary.textContent = disabledCount || patternCount
                        ? `${disabledCount} types excluded from the server query, ${patternCount} patterns hidden locally.`
                        : 'No local filters applied.';
                }
            };

            const navigateWithTypes = () => {
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.delete('before');
                nextUrl.searchParams.delete('types');
                nextUrl.searchParams.delete('types[]');

                if (selectedTypes.length === 0) {
                    nextUrl.searchParams.append('types[]', '__none');
                } else if (selectedTypes.length !== knownTypes.length) {
                    selectedTypes.forEach((type) => nextUrl.searchParams.append('types[]', type));
                }

                localStorage.setItem(typesKey, JSON.stringify(selectedTypes));
                window.location.href = nextUrl.toString();
            };

            checks.forEach((check) => {
                check.checked = selectedTypes.includes(check.value);
                check.addEventListener('change', () => {
                    selectedTypes = checks.filter((item) => item.checked).map((item) => item.value);
                    navigateWithTypes();
                });
            });

            if (patternInput) {
                patternInput.value = localStorage.getItem(patternsKey) || '';
                patternInput.addEventListener('input', apply);
            }

            reset?.addEventListener('click', () => {
                selectedTypes = knownTypes;
                checks.forEach((check) => check.checked = true);
                if (patternInput) {
                    patternInput.value = '';
                }
                localStorage.removeItem(patternsKey);
                navigateWithTypes();
            });

            apply();
        })();
    </script>
</body>
</html>
