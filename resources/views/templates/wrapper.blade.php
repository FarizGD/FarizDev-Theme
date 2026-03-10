<!DOCTYPE html>
<html>
    <head>
        <title>{{ config('app.name', 'Pterodactyl') }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="noindex">
            <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
            <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
            <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
            <link rel="manifest" href="/favicons/manifest.json">
            <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#bc6e3c">
            <link rel="shortcut icon" href="/favicons/favicon.ico">
            <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            <meta name="theme-color" content="#0e4688">
        @show

        @section('user-data')
            @if(!is_null(Auth::user()))
                <script>
                    window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
                </script>
            @endif
            @if(!empty($siteConfiguration))
                <script>
                    window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
                </script>
            @endif
        @show

        @yield('assets')

        @include('layouts.scripts')
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8909333065170510"
            crossorigin="anonymous"></script>
        <style>
            @keyframes gradientMove {
                0% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }

            html, body {
                min-height: 100%;
                overflow-y: auto;
                font-family: "IBM Plex Sans", "Roboto", system-ui, sans-serif;
                color: #f8fafc;
            }

            .ptero-shell {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                background: radial-gradient(circle at 15% 20%, rgba(96, 165, 250, 0.2), transparent 50%),
                    radial-gradient(circle at 80% 10%, rgba(30, 64, 175, 0.3), transparent 45%),
                    linear-gradient(135deg, #0b1220, #0f1f3a, #1e3a8a);
                background-size: 800% 800%;
                animation: gradientMove 18s ease infinite;
            }

            .ptero-shell::after {
                content: "";
                position: fixed;
                inset: 0;
                background-image: radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
                background-size: 120px 120px;
                opacity: 0.2;
                pointer-events: none;
            }

            .ptero-app {
                width: min(1200px, 92vw);
                margin: 72px auto 48px;
                padding: 32px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.12);
                border: 1px solid rgba(255, 255, 255, 0.2);
                box-shadow: 0 30px 70px rgba(8, 12, 24, 0.6);
                backdrop-filter: blur(22px);
            }

            #app .bg-neutral-800,
            #app .bg-neutral-900,
            #app .bg-gray-800,
            #app .bg-black,
            #app .shadow,
            #app .shadow-md,
            #app .shadow-lg,
            #app .shadow-xl {
                background: rgba(255, 255, 255, 0.14) !important;
                border: 1px solid rgba(255, 255, 255, 0.18);
                box-shadow: 0 26px 60px rgba(15, 23, 42, 0.45);
                backdrop-filter: blur(20px);
                border-radius: 24px;
            }

            #app h1, #app h2, #app h3, #app h4 {
                font-family: "IBM Plex Sans", "Roboto", system-ui, sans-serif;
                letter-spacing: 0.3px;
            }

            #app .text-neutral-100,
            #app .text-neutral-200,
            #app .text-gray-100,
            #app .text-gray-200,
            #app .text-white {
                color: #f8fafc !important;
                text-shadow: 0 1px 2px rgba(15, 23, 42, 0.45);
            }

            #app .text-neutral-300,
            #app .text-neutral-400,
            #app .text-gray-300,
            #app .text-gray-400 {
                color: rgba(226, 232, 240, 0.85) !important;
            }

            #app input,
            #app select,
            #app textarea {
                background: rgba(255, 255, 255, 0.18) !important;
                border: 1px solid rgba(255, 255, 255, 0.25) !important;
                color: #f8fafc !important;
                border-radius: 16px !important;
            }

            #app input::placeholder,
            #app textarea::placeholder {
                color: rgba(248, 250, 252, 0.7) !important;
            }

            #app button,
            #app .btn,
            #app [role="button"] {
                background: rgba(255, 255, 255, 0.22) !important;
                border: 1px solid rgba(255, 255, 255, 0.25) !important;
                color: #f8fafc !important;
                border-radius: 999px !important;
                padding: 10px 18px !important;
                font-weight: 600;
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            }

            #app button:hover,
            #app .btn:hover,
            #app [role="button"]:hover {
                background: rgba(255, 255, 255, 0.3) !important;
                box-shadow: 0 10px 30px rgba(59, 130, 246, 0.25);
                transform: translateY(-1px);
            }

            #app table {
                background: rgba(15, 23, 42, 0.2);
                border-radius: 20px;
                overflow: hidden;
            }

            #app th,
            #app td {
                border-color: rgba(255, 255, 255, 0.12) !important;
            }

            .ptero-footer {
                margin: auto 0 24px;
                text-align: center;
                font-size: 0.9rem;
                color: rgba(248, 250, 252, 0.8);
            }

            .ptero-footer a {
                color: rgba(248, 250, 252, 0.95);
                text-decoration: none;
                border-bottom: 1px solid rgba(248, 250, 252, 0.4);
            }

            .ptero-footer a:hover {
                color: #ffffff;
                border-bottom-color: #ffffff;
            }
        </style>
    </head>
    <body class="ptero-shell {{ $css['body'] ?? '' }}">
        @section('content')
            @yield('above-container')
            <div class="ptero-app">
                @yield('container')
            </div>
            @yield('below-container')
            <div class="ptero-footer">
                <a href="https://youtube.com/@fariz-gd" target="_blank" rel="noopener noreferrer">(C)2026 FarizDev-Theme</a>
            </div>
        @show
        @section('scripts')
            {!! $asset->js('main.js') !!}
        @show
    </body>
</html>
