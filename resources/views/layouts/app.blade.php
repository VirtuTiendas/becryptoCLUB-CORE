<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'BeCryptoCLUB') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.4/dist/tailwind.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-950 to-black">
        <nav class="p-6 border-b border-slate-800 flex items-center justify-between">
            <div class="text-xl font-bold text-cyan-400">BeCryptoCLUB – Code X Hub</div>
            <div class="space-x-4 text-sm uppercase tracking-widest text-slate-400">
                <a href="/" class="hover:text-cyan-400">Home</a>
                <a href="/dashboard" class="hover:text-cyan-400">Admin</a>
            </div>
        </nav>
        <main class="p-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
