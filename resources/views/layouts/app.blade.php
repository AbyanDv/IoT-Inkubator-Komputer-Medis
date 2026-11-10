<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full bg-slate-50 font-sans text-slate-900 antialiased">
        <header class="border-b border-slate-200 bg-white">
            <nav class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-3 sm:px-6"
                 aria-label="Navigasi utama">
                <a href="{{ route('dashboard') }}" class="text-sm font-semibold tracking-tight">
                    Inkubator Bayi
                </a>

                <ul class="flex flex-1 items-center gap-1 text-sm">
                    @foreach ([['dashboard', 'Dashboard'], ['babies', 'Bayi'], ['history', 'Riwayat']] as [$name, $label])
                        <li>
                            <a href="{{ route($name) }}"
                               @if (request()->routeIs($name)) aria-current="page" @endif
                               class="rounded-md px-3 py-1.5 font-medium transition-colors duration-150
                                      {{ request()->routeIs($name)
                                          ? 'bg-slate-900 text-white'
                                          : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors duration-150
                                   hover:bg-slate-100 hover:text-slate-900
                                   focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
                        Keluar
                    </button>
                </form>
            </nav>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            {{ $slot }}
        </main>
    </body>
</html>
