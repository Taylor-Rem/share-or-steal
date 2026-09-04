<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <title>{{ config('game.name') }}</title>
    @vite(['resources/css/app.css', "resources/js/{$app}/main.js"])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <div id="app" data-app="{{ $app }}"></div>
</body>
</html>
