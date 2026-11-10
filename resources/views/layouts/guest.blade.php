<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full items-center justify-center bg-slate-50 px-4 py-10 font-sans text-slate-900 antialiased">
        {{ $slot }}
    </body>
</html>
