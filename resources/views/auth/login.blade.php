<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log in · Incubator Control</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-bone text-ink antialiased">
    <a href="#login-form" class="skip-link">Skip to form</a>

    <main class="grid min-h-dvh lg:grid-cols-2">
        <section class="relative flex flex-col justify-between overflow-hidden bg-ink px-6 py-8 text-white sm:px-10 lg:px-14 lg:py-12" aria-labelledby="brand-heading">
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-xl bg-white/10">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
                        <rect x="4" y="3.5" width="16" height="17" rx="3" />
                        <path d="M8 8h8M8 12h8M8 16h4" />
                    </svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-[0.95rem] font-semibold tracking-[-0.015em]">Incubator</span>
                    <span class="block text-[0.6rem] font-semibold uppercase tracking-[0.18em] text-white/50">Smart infant monitoring</span>
                </span>
            </div>

            <div class="relative mt-12 max-w-md lg:mt-0">
                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.18em] text-white/45">Komputer Medis · Academic prototype</p>
                <h1 id="brand-heading" class="mt-3 text-4xl font-semibold leading-[1.05] tracking-[-0.045em] sm:text-5xl">
                    The incubator,<br>in clear view.
                </h1>
                <p class="mt-5 max-w-sm text-[0.95rem] leading-relaxed text-white/60">
                    Monitor baby temperature, humidity, and actuator state from one quiet dashboard.
                </p>

                <svg viewBox="0 0 400 120" class="mt-10 w-full max-w-sm text-white/25" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                    <path d="M0 92h52c10 0 12-6 16-22s8-40 20-40 13 26 18 42 8 18 18 18h48c10 0 12-6 16-22s8-40 20-40 13 26 18 42 8 18 18 18h56c10 0 12-6 16-22s8-40 20-40 13 26 18 42 8 18 18 18h38" />
                    <path d="M0 92h400" stroke-dasharray="2 6" stroke-opacity=".6" />
                </svg>
            </div>

            <p class="mt-12 flex items-start gap-2 text-xs leading-relaxed text-white/40">
                <x-icon name="alert" class="mt-px size-3.5 shrink-0" />
                <span>Simulation prototype only. Not for clinical use or patient care.</span>
            </p>
        </section>

        <section class="flex items-center justify-center px-6 py-12 sm:px-10" aria-labelledby="login-heading">
            <div id="login-form" class="w-full max-w-sm">
                <p class="eyebrow">Sign in</p>
                <h2 id="login-heading" class="mt-2 text-2xl font-semibold tracking-[-0.03em]">Log in</h2>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">Use your assigned monitoring account.</p>

                @if ($errors->any())
                    <div class="mt-6 flex items-start gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                        <x-icon name="alert" class="mt-0.5 size-4" />
                        <p>{{ $errors->first('email') }}</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="field-label">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            inputmode="email"
                            spellcheck="false"
                            class="field-input"
                        >
                        @error('email')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="field-label">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="field-input"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        Log in
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </form>

                <p class="mt-6 text-xs leading-relaxed text-ink-soft">
                    Accounts are provisioned by the simulation administrator. There is no public registration.
                </p>
            </div>
        </section>
    </main>
</body>
</html>
