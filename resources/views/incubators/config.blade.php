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
            </div>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-ink-soft">
                Changes replace the current settings. The device receives them on its next sync.
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
                    <h2 id="config-form-heading" class="text-lg font-semibold">Current settings</h2>
                    <p class="mt-0.5 text-sm text-ink-soft">Saving replaces the stored configuration.</p>
                </div>

                <div class="space-y-8 px-5 py-6 sm:px-6">
                    <fieldset>
                        <legend class="text-base font-semibold">Thermal target</legend>
                        <p class="mt-1 text-sm leading-relaxed text-ink-soft">
                            The PID loop on the device drives the heater toward this temperature target.
                        </p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <x-field
                                name="temperature_setpoint"
                                label="Setpoint (°C)"
                                type="number"
                                :value="$value('temperature_setpoint', 30)"
                                min="28"
                                max="37"
                                step="0.1"
                                required
                                hint="Server range: 28–37 °C. Firmware safety clamps still apply."
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
                        <legend class="text-base font-semibold">Indicators</legend>
                        <div class="mt-2 divide-y divide-line">
                            <x-toggle
                                name="blue_led_enabled"
                                label="Blue light"
                                hint="Instructional phototherapy simulation. Not a calibrated light source."
                                :checked="$config?->blue_led_enabled ?? false"
                            />
                        </div>
                    </fieldset>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-line bg-bone/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <a href="{{ route('incubators.show', $incubator) }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="check" class="size-4" />
                        Save configuration
                    </button>
                </div>
            </form>

            <aside class="space-y-6">
                <section class="card reveal p-5" aria-labelledby="current-config-heading">
                    <p class="eyebrow">Current configuration</p>
                    <h2 id="current-config-heading" class="mt-1 text-lg font-semibold">
                        {{ $config ? 'Configured' : 'Not configured' }}
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
                                <dt class="text-ink-soft">Blue light</dt>
                                <dd class="font-medium">{{ $config->blue_led_enabled ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                        </dl>

                    @else
                        <p class="mt-3 text-sm leading-relaxed text-ink-soft">Save settings to configure this device.</p>
                    @endif
                </section>

                <section class="rounded-2xl border border-warn/25 bg-warn-soft p-5">
                    <p class="flex items-center gap-2 text-sm font-semibold text-warn">
                        <x-icon name="alert" class="size-4" />
                        Safety boundary
                    </p>
                    <p class="mt-2 text-xs leading-relaxed text-warn">
                        Heater safety runs on the ESP32. Sensor faults force both heater relays off and the fan relay on, even without server access.
                    </p>
                </section>
            </aside>
        </div>
    </div>
@endsection
