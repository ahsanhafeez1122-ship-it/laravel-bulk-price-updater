<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Price imports') · {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f5f6f4; --surface: #fff; --ink: #18201c; --muted: #5d6862; --line: #dde2dd;
            --accent: #1d55b4; --ok: #1d7a4c; --ok-soft: #e4f3ea; --warn: #9a5b00; --warn-soft: #fdf0d9;
            --bad: #b42318; --bad-soft: #fde8e6; --mono: ui-monospace, SFMono-Regular, Consolas, monospace;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        a { color: var(--accent); }
        header { background: var(--ink); color: #fff; }
        header .wrap { display: flex; gap: 24px; align-items: center; padding-block: 14px; flex-wrap: wrap; }
        header a { color: #fff; text-decoration: none; opacity: .8; }
        header a:hover, header a.active { opacity: 1; }
        header strong { margin-right: auto; }
        .wrap { max-width: 1100px; margin: 0 auto; padding-inline: 16px; }
        main { padding-block: 28px 60px; display: grid; gap: 20px; }
        h1 { margin: 0; font-size: 26px; }
        h2 { margin: 0 0 12px; font-size: 18px; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 20px; }
        .muted { color: var(--muted); }
        .mono { font-family: var(--mono); font-variant-numeric: tabular-nums; }
        .flash { padding: 12px 16px; border-radius: 8px; background: var(--ok-soft); color: var(--ok); }
        .errors { padding: 12px 16px; border-radius: 8px; background: var(--bad-soft); color: var(--bad); }
        .errors p { margin: 0; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 7px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn.danger { color: var(--bad); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 10px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 600; }
        td.num, th.num { text-align: right; }
        .table-scroll { overflow-x: auto; }
        .pill { display: inline-block; padding: 2px 9px; border-radius: 99px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .pill.changed, .pill.applied { background: var(--ok-soft); color: var(--ok); }
        .pill.flagged, .pill.previewed { background: var(--warn-soft); color: var(--warn); }
        .pill.error, .pill.rolled_back { background: var(--bad-soft); color: var(--bad); }
        .pill.unchanged, .pill.discarded { background: #eef0ee; color: var(--muted); }
        .up { color: var(--bad); } .down { color: var(--ok); }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
        .stat { display: block; padding: 12px 14px; border: 1px solid var(--line); border-radius: 8px; text-decoration: none; color: inherit; }
        .stat b { display: block; font-size: 22px; font-variant-numeric: tabular-nums; }
        .stat.active { border-color: var(--accent); box-shadow: inset 0 0 0 1px var(--accent); }
        input[type=file], input[type=number], input[type=search] { font: inherit; padding: 8px; border: 1px solid var(--line); border-radius: 6px; background: #fff; }
        label { font-weight: 600; }
        .pagination nav { margin-top: 12px; }
        .pagination svg { width: 16px; height: 16px; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <strong>{{ config('app.name') }}</strong>
        <a href="{{ route('imports.index') }}" class="{{ request()->routeIs('imports.*') ? 'active' : '' }}">Imports</a>
        <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}">Products</a>
    </div>
</header>
<main class="wrap">
    @if (session('status'))
        <div class="flash" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="errors" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    @yield('content')
</main>
</body>
</html>
