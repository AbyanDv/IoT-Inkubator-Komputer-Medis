@php
    $user = auth()->user();
    $isFleet = request()->routeIs('incubators.*');
    $isBabies = request()->routeIs('babies.*');
    $isGuide = request()->routeIs('firmware.guide');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Incubators') · Incubator Control</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-bone text-ink antialiased">
    <a href="#main" class="skip-link">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-line bg-bone">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-5 py-3 sm:px-8">
            <a href="{{ route('incubators.index') }}" class="flex items-center gap-3 rounded-lg">
                <span class="grid size-9 place-items-center rounded-xl bg-ink text-bone">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                        <rect x="4" y="3.5" width="16" height="17" rx="3" />
                        <path d="M8 8h8M8 12h8M8 16h4" />
                    </svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-[0.95rem] font-semibold tracking-[-0.015em]">Incubator</span>
                    <span class="block text-[0.6rem] font-semibold uppercase tracking-[0.18em] text-ink-soft">Smart infant monitoring</span>
                </span>
            </a>

            <nav aria-label="Primary" class="order-3 -mx-1 flex w-full flex-wrap items-center gap-1 sm:order-none sm:mx-0 sm:w-auto">
                <a
                    href="{{ route('incubators.index') }}"
                    @if ($isFleet) aria-current="page" @endif
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors duration-150 {{ $isFleet ? 'bg-ink text-white' : 'text-ink-soft hover:text-ink' }}"
                >Fleet</a>
                <a
                    href="{{ route('babies.index') }}"
                    @if ($isBabies) aria-current="page" @endif
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors duration-150 {{ $isBabies ? 'bg-ink text-white' : 'text-ink-soft hover:text-ink' }}"
                >Babies</a>
                <a
                    href="{{ route('firmware.guide') }}"
                    @if ($isGuide) aria-current="page" @endif
                    class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors duration-150 {{ $isGuide ? 'bg-ink text-white' : 'text-ink-soft hover:text-ink' }}"
                >Codegraph</a>
            </nav>

            @if ($user)
                <div class="ml-auto flex items-center gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-xs font-semibold leading-tight">{{ $user->name }}</p>
                        <p class="text-[0.68rem] capitalize leading-tight text-ink-soft">{{ str_replace('_', ' ', $user->role) }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary size-11 px-0" title="Log out">
                            <x-icon name="logout" class="size-4" />
                            <span class="sr-only">Log out</span>
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </header>

    <main id="main" class="mx-auto w-full max-w-6xl px-5 py-8 sm:px-8 sm:py-12">
        @if (session('status'))
            <div class="reveal mb-6 flex items-start gap-3 rounded-2xl border border-accent/20 bg-accent-soft px-4 py-3 text-sm font-medium text-accent" role="status">
                <x-icon name="check" class="mt-0.5 size-4" />
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="reveal mb-6 flex items-start gap-3 rounded-2xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm font-medium text-danger" role="alert">
                <x-icon name="alert" class="mt-0.5 size-4" />
                <p>{{ session('error') }}</p>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto w-full max-w-6xl px-5 pb-10 sm:px-8">
        <div class="flex flex-col gap-2 border-t border-line pt-5 text-xs text-ink-soft sm:flex-row sm:items-center sm:justify-between">
            <p class="flex items-start gap-2">
                <x-icon name="alert" class="mt-px size-3.5 text-warn" />
                <span>Simulation prototype only. Not for clinical use or patient care.</span>
            </p>
            <p>Komputer Medis · Academic prototype</p>
        </div>
    </footer>

    @yield('scripts')
</body>
</html>
