{{--
    Page d'erreur de secours, autonome (aucun script, aucune feuille de style du site) : servie quand la
    page Inertia ne peut pas être produite (base de données ou session indisponibles) et pour les codes
    sans page dédiée. Mêmes couleurs et mêmes polices que le site, clair et sombre (réglage du système ou
    cookie `theme`). $status : code HTTP ; $detail : message précis facultatif.
--}}
@php
    $known = in_array($status, [403, 404, 405, 419, 429, 500, 503], true);
    $key = $known ? (string) $status : 'default';
    $retryable = in_array($status, [419, 429, 500, 503], true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light dark" />
    <meta name="robots" content="noindex" />
    <title>{{ __("errors.$key.title") }} - VFF</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #ffffff; --bg-subtle: #f3f5f6; --text: #000000; --muted: #555a64;
            --border: #e2e6ea; --accent: #ff6500; --ink: #b84800; --on-accent: #000000;
        }
        @media (prefers-color-scheme: dark) {
            :root:not([data-theme='light']) {
                --bg: #0f1419; --bg-subtle: #151b23; --text: #e8ecec; --muted: #94a3b8;
                --border: #2a3441; --ink: #ff7a26;
            }
        }
        :root[data-theme='dark'] {
            --bg: #0f1419; --bg-subtle: #151b23; --text: #e8ecec; --muted: #94a3b8;
            --border: #2a3441; --ink: #ff7a26;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            background: var(--bg); color: var(--text);
            font: 17px/1.65 Lato, 'Helvetica Neue', Arial, sans-serif;
        }
        .bar { border-bottom: 3px solid var(--accent); padding: 0 24px; }
        .bar a {
            display: inline-flex; align-items: center; min-height: 56px;
            font: 700 20px/1 Gabarito, 'Helvetica Neue', Arial, sans-serif; color: var(--text); text-decoration: none;
        }
        main { flex: 1; background: var(--bg-subtle); border-bottom: 1px solid var(--border); padding: 56px 24px; }
        .inner { max-width: 1180px; margin: 0 auto; }
        .code { margin: 0 0 8px; font: 600 15px/1.4 Inter, 'Helvetica Neue', Arial, sans-serif; color: var(--muted); }
        h1 { margin: 0; max-width: 20ch; font: 700 clamp(30px, 5vw, 44px)/1.1 Gabarito, 'Helvetica Neue', Arial, sans-serif; text-wrap: balance; }
        p.text { max-width: 60ch; margin: 16px 0 0; font-size: 20px; line-height: 1.5; color: var(--muted); }
        p.detail { max-width: 60ch; margin: 12px 0 0; }
        .actions { margin-top: 28px; }
        .btn {
            display: inline-flex; align-items: center; min-height: 44px; padding: 10px 20px;
            border-radius: 6px; background: var(--accent); color: var(--on-accent);
            font: 700 16px/1.2 Gabarito, 'Helvetica Neue', Arial, sans-serif; text-decoration: none;
        }
        .btn:focus-visible { outline: 2px solid var(--on-accent); outline-offset: 2px; box-shadow: 0 0 0 5px var(--ink); }
        .btn:hover { background: #e85a00; }
        footer { padding: 20px 24px; font: 14px/1.4 Inter, 'Helvetica Neue', Arial, sans-serif; color: var(--muted); }
        footer a { color: var(--ink); }
    </style>
    <script>
        (function () {
            var m = document.cookie.match(/(?:^|; )theme=(dark|light)(?:;|$)/);
            if (m) document.documentElement.setAttribute('data-theme', m[1]);
        })();
    </script>
</head>
<body>
    <div class="bar"><a href="/">{{ __('common.app_full') }}</a></div>
    <main>
        <div class="inner">
            <p class="code">{{ __('errors.eyebrow', ['code' => $status ?: '']) }}</p>
            <h1>{{ __("errors.$key.title") }}</h1>
            <p class="text">{{ __("errors.$key.text") }}</p>
            @if (! empty($detail))
                <p class="detail">{{ $detail }}</p>
            @endif
            <div class="actions">
                @if ($retryable)
                    <a class="btn" href="{{ request()->getRequestUri() }}">{{ __('errors.reload') }}</a>
                @else
                    <a class="btn" href="/">{{ __('errors.home') }}</a>
                @endif
            </div>
        </div>
    </main>
    <footer>{{ __('common.app_full') }}</footer>
</body>
</html>
