@extends('layouts.app')

@section('title', 'Configuration · '.$incubator->name)

@section('content')
    @php
        $value = fn (string $field, mixed $fallback) => old($field, $config?->{$field} ?? $fallback);
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <div class="reveal">
            <a href="{{ route('incubators.show', $incubator) }}" class="back-link">
                <x-icon name="arrow-left" class="size-3.5" />
                {{ $incubator->name }}
            </a>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <h1 class="text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">Device configuration</h1>
                @if ($config)
                    <span class="badge badge-neutral">Current v{{ $config->config_version }}</span>
                @endif
            </div>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink-soft">
                Saving publishes a new version. The ESP32 collects it on its next telemetry response, then acknowledges it automatically.
            </p>
        </div>

        @if ($errors->any())
            <div class="reveal flex items-start gap-3 rounded-2xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                <x-icon name="alert" class="mt-0.5 size-4" />
                <div>
                    <p class="font-semibold">Configuration was not saved.</p>
                    <ul class="mt-1 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <form
                method="POST"
                action="{{ route('incubators.config.update', $incubator) }}"
                class="card reveal overflow-hidden"
                aria-labelledby="config-form-heading"
            >
                @csrf
                <div class="border-b border-line px-5 py-4 sm:px-6">
                    <h2 id="config-form-heading" class="text-lg font-semibold">New version {{ $nextVersion }}</h2>
                    <p class="mt-0.5 text-sm text-ink-soft">Every save creates a new immutable version.</p>
                </div>

                <div class="space-y-8 px-5 py-6 sm:px-6">
                    <fieldset>
                        <legend class="text-base font-semibold">Thermal target</legend>
                        <p class="mt-1 text-sm leading-relaxed text-ink-soft">
                            The PID loop on the device drives the heater toward this object-temperature target.
                        </p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <x-field
                                name="temperature_setpoint"
                                label="Setpoint (°C)"
                                type="number"
                                :value="$value('temperature_setpoint', 30)"
                                min="20"
                                max="40"
                                step="0.1"
                                required
                                hint="Server range: 20–40 °C. Firmware safety clamps still apply."
                                inputmode="decimal"
                            />
                        </div>
                    </fieldset>

                    <fieldset class="section-divider">
                        <legend class="text-base font-semibold">PID tuning</legend>
                        <p class="mt-1 text-sm leading-relaxed text-ink-soft">
                            Local control gains. Larger Kp reacts harder; Ki removes steady-state error; Kd damps overshoot.
                        </p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-3">
                            <x-field name="pid_kp" label="Kp" type="number" :value="$value('pid_kp', '2.000')" min="0" max="100" step="0.001" required inputmode="decimal" />
                            <x-field name="pid_ki" label="Ki" type="number" :value="$value('pid_ki', '0.100')" min="0" max="100" step="0.001" required inputmode="decimal" />
                            <x-field name="pid_kd" label="Kd" type="number" :value="$value('pid_kd', '0.500')" min="0" max="100" step="0.001" required inputmode="decimal" />
                        </div>
                    </fieldset>

                    <fieldset class="section-divider">
                        <legend class="text-base font-semibold">Circulation</legend>
                        <p class="mt-1 text-sm leading-relaxed text-ink-soft">Choose how the device resolves fan output.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-line bg-bone/40 p-3 transition-colors duration-150 has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                <input
                                    type="radio"
                                    name="fan_mode"
                                    value="pwm"
                                    @checked(old('fan_mode', $config?->fan_mode ?? 'pwm') === 'pwm')
                                    class="mt-1 size-4 shrink-0 accent-[var(--color-accent)]"
                                >
                                <span>
                                    <span class="block text-sm font-medium">Fixed PWM</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-ink-soft">Fan holds the PWM value published with this configuration.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-line bg-bone/40 p-3 transition-colors duration-150 has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                <input
                                    type="radio"
                                    name="fan_mode"
                                    value="auto"
                                    @checked(old('fan_mode', $config?->fan_mode ?? 'pwm') === 'auto')
                                    class="mt-1 size-4 shrink-0 accent-[var(--color-accent)]"
                                >
                                <span>
                                    <span class="block text-sm font-medium">Auto</span>
                                    <span class="mt-0.5 block text-xs leading-relaxed text-ink-soft">Device follows its local temperature and humidity profile.</span>
                                </span>
                            </label>
                        </div>
                        @error('fan_mode')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <fieldset class="section-divider">
                        <legend class="text-base font-semibold">Indicators</legend>
                        <div class="mt-2 divide-y divide-line">
                            <x-toggle
                                name="blue_led_enabled"
                                label="Blue light"
                                hint="Instructional phototherapy simulation. Not a calibrated light source."
                                :checked="$config?->blue_led_enabled ?? false"
                            />
                            <x-toggle
                                name="simulation_mode"
                                label="Simulation mode"
                                hint="Keeps the device in the academic test profile."
                                :checked="$config?->simulation_mode ?? true"
                            />
                        </div>
                    </fieldset>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-line bg-bone/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <a href="{{ route('incubators.show', $incubator) }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="check" class="size-4" />
                        Save as v{{ $nextVersion }}
                    </button>
                </div>
            </form>

            <aside class="space-y-6">
                <section class="card reveal p-5" aria-labelledby="current-config-heading">
                    <p class="eyebrow">Active version</p>
                    <h2 id="current-config-heading" class="mt-1 text-lg font-semibold">
                        {{ $config ? 'v'.$config->config_version : 'None' }}
                    </h2>

                    @if ($config)
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-ink-soft">Setpoint</dt>
                                <dd class="font-semibold tabular-nums">{{ number_format((float) $config->temperature_setpoint, 1) }} °C</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-ink-soft">PID</dt>
                                <dd class="font-mono text-xs tabular-nums">{{ number_format((float) $config->pid_kp, 2) }} / {{ number_format((float) $config->pid_ki, 2) }} / {{ number_format((float) $config->pid_kd, 2) }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-ink-soft">Fan</dt>
                                <dd class="font-medium">{{ $config->fan_mode === 'pwm' ? 'Fixed PWM' : 'Auto' }}</dd>
                            </div>
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-ink-soft">Blue light</dt>
                                <dd class="font-medium">{{ $config->blue_led_enabled ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex items-center gap-2 border-t border-line pt-3 text-xs text-ink-soft">
                            <span class="status-dot {{ $config->applied_at ? 'bg-accent' : 'bg-warn' }}" aria-hidden="true"></span>
                            {{ $config->applied_at ? 'Applied '.$config->applied_at->diffForHumans() : 'Awaiting device acknowledgement' }}
                        </div>
                    @else
                        <p class="mt-3 text-sm leading-relaxed text-ink-soft">No version has been published yet. The first save becomes v1.</p>
                    @endif
                </section>

                <section class="card reveal p-5" aria-labelledby="sync-heading">
                    <p class="eyebrow">Sync path</p>
                    <h2 id="sync-heading" class="mt-1 text-lg font-semibold">No extra device request</h2>
                    <ol class="mt-4 space-y-3 text-sm leading-relaxed text-ink-soft">
                        <li class="flex gap-3">
                            <span class="grid size-5 shrink-0 place-items-center rounded-full bg-ink text-[0.65rem] font-semibold text-white">1</span>
                            <span>Save creates v{{ $nextVersion }} on the server.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid size-5 shrink-0 place-items-center rounded-full bg-ink text-[0.65rem] font-semibold text-white">2</span>
                            <span>The next telemetry response carries the new payload.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid size-5 shrink-0 place-items-center rounded-full bg-ink text-[0.65rem] font-semibold text-white">3</span>
                            <span>The device applies it locally and reports the version back.</span>
                        </li>
                    </ol>
                </section>

                <section class="rounded-2xl border border-warn/25 bg-warn-soft p-5">
                    <p class="flex items-center gap-2 text-sm font-semibold text-warn">
                        <x-icon name="alert" class="size-4" />
                        Safety boundary
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-warn">
                        Heater safety runs on the ESP32, not here. A sensor fault forces PWM to 0% locally even if the server is unreachable.
                    </p>
                </section>
            </aside>
        </div>
    </div>
@endsection
