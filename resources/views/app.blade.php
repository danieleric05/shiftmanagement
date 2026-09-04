<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Gestion des shifts">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    {{-- Applique la classe `dark` avant le premier rendu pour éviter un
    flash de thème clair : doit rester un script inline synchrone placé
    avant le CSS/JS de l'app. --}}
    <script @if(app()->bound('csp-nonce')) nonce="{{ app('csp-nonce') }}" @endif>
        (function () {
            try {
                var theme = localStorage.getItem('theme');
                var dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @routes(nonce: app()->bound('csp-nonce') ? app('csp-nonce') : null)
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
