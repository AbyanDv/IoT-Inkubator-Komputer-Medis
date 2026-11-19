@extends('layouts.app')

@section('title', $incubator->name)

@section('content')
    @php
        $telemetry = $initialTelemetry['telemetry'] ?? null;
        $sensorStatus = $telemetry['sensor_status'] ?? [];
        $sensorLabel = function (string $key) use ($sensorStatus): string {
            $value = $sensorStatus[$key] ?? null;

            if ($value === true || in_array($value, ['OK', 'ok'], true)) {
                return 'OK';
            }

            if ($value === false || in_array($value, ['ERROR', 'DISCONNECTED'], true)) {
                return 'Fault';
            }

            return 'Unavailable';
        };
        $sensorTone = fn (string $key): string => $sensorLabel($key) === 'OK' ? 'badge-ok' : ($sensorLabel($key) === 'Fault' ? 'badge-danger' : 'badge-neutral');
        $setpoint = $config ? (float) $config->temperature_setpoint : null;
        $recentReadings = $history->reverse()->take(8);
        $telemetryEndpoint = route('api.incubators.live-telemetry', $incubator);
        $initialState = [
            'telemetry' => $telemetry,
            'history' => $history,
            'sensorStatus' => $sensorStatus,
            'setpoint' => $setpoint,
            'endpoint' => $telemetryEndpoint,
            'relayOverrides' => (object) $relayOverrides,
            'relayEndpoint' => route('incubators.relays.update', $incubator),
            'relayAutoEndpoint' => route('incubators.relays.auto', $incubator),
        ];
    @endphp

    <div class="space-y-6" data-dashboard>
        <div class="reveal flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0">
                <a href="{{ route('incubators.index') }}" class="back-link">
                    <x-icon name="arrow-left" class="size-3.5" />
                    All incubators
                </a>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <h1 class="text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">{{ $incubator->name }}</h1>
                    <x-status-badge data-device-status :status="$incubator->status" />
                </div>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-soft">
                    <span class="font-mono">{{ $incubator->device_code }}</span>
                    <span aria-hidden="true">·</span>
                    <span>Firmware {{ $incubator->firmware_version ?? 'unknown' }}</span>
                    <span aria-hidden="true">·</span>
                    <span>Last heartbeat <span data-last-heartbeat class="tabular-nums">{{ $incubator->last_heartbeat_at?->diffForHumans() ?? 'Never' }}</span></span>
                </p>
            </div>

            @can('manage-config')
                <a href="{{ route('incubators.config.edit', $incubator) }}" class="btn btn-secondary">
                    <x-icon name="sliders" class="size-4" />
                    Device configuration
                </a>
            @endcan
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="space-y-6">
                <section class="card reveal p-5 sm:p-6" aria-labelledby="live-telemetry-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="eyebrow">Telemetry</p>
                            <h2 id="live-telemetry-heading" class="mt-1 text-lg font-semibold">Live telemetry</h2>
                        </div>
                        <p class="flex items-center gap-2 text-xs font-semibold text-ink-soft">
                            <span data-connection-dot class="status-dot bg-accent" aria-hidden="true"></span>
                            <span data-connection role="status">Live</span>
                            <span aria-hidden="true">·</span>
                            <time data-updated-at class="tabular-nums">{{ ($telemetry['recorded_at'] ?? null) ? \Illuminate\Support\Carbon::parse($telemetry['recorded_at'])->format('H:i:s') : 'Waiting' }}</time>
                        </p>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-line bg-bone/60 p-4">
                            <p class="flex items-center gap-1.5 eyebrow">
                                <x-icon name="thermometer" class="size-3.5" />
                                Object temp.
                            </p>
                            <p class="mt-3 flex items-baseline gap-1">
                                <span class="reading text-4xl font-semibold tracking-[-0.05em]" data-reading="temperature_object">{{ $telemetry ? number_format($telemetry['temperature_object'], 1) : '—' }}</span>
                                <span class="text-sm font-medium text-ink-soft">°C</span>
                            </p>
                            <p class="mt-1 text-xs text-ink-soft">Contact simulation · DS18B20</p>
                        </div>

                        <div class="rounded-xl border border-line bg-bone/60 p-4">
                            <p class="flex items-center gap-1.5 eyebrow">
                                <x-icon name="thermometer" class="size-3.5" />
                                Room temp.
                            </p>
                            <p class="mt-3 flex items-baseline gap-1">
                                <span class="reading text-4xl font-semibold tracking-[-0.05em]" data-reading="temperature_room">{{ $telemetry ? number_format($telemetry['temperature_room'], 1) : '—' }}</span>
                                <span class="text-sm font-medium text-ink-soft">°C</span>
                            </p>
                            <p class="mt-1 text-xs text-ink-soft">Incubator air · DHT22</p>
                        </div>

                        <div class="rounded-xl border border-line bg-bone/60 p-4">
                            <p class="flex items-center gap-1.5 eyebrow">
                                <x-icon name="droplets" class="size-3.5" />
                                Humidity
                            </p>
                            <p class="mt-3 flex items-baseline gap-1">
                                <span class="reading text-4xl font-semibold tracking-[-0.05em]" data-reading="humidity">{{ $telemetry ? number_format($telemetry['humidity'], 1) : '—' }}</span>
                                <span class="text-sm font-medium text-ink-soft">%</span>
                            </p>
                            <p class="mt-1 text-xs text-ink-soft">Relative humidity · DHT22</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-ink-soft">
                        @if ($config)
                            <span class="flex items-center gap-1.5">
                                <span class="size-1.5 rounded-full bg-warn" aria-hidden="true"></span>
                                Setpoint <strong class="font-semibold text-ink">{{ number_format($setpoint, 1) }} °C</strong>
                            </span>
                        @else
                            <span>No configuration saved.</span>
                        @endif
                        <span>Updates every 2.5 s</span>
                    </div>
                </section>

                <section class="card reveal p-5 sm:p-6" aria-labelledby="temperature-trend-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="eyebrow">Thermal trend</p>
                            <h2 id="temperature-trend-heading" class="mt-1 text-lg font-semibold">Object temperature</h2>
                        </div>
                        <p class="text-xs text-ink-soft">Last {{ $history->count() }} readings · °C</p>
                    </div>

                    <div class="relative mt-4">
                        <svg
                            data-chart
                            viewBox="0 0 600 190"
                            class="h-52 w-full"
                            role="img"
                            aria-label="Object temperature history for {{ $incubator->name }}"
                        ></svg>
                        <noscript>
                            <p class="py-10 text-center text-sm text-ink-soft">Enable JavaScript to see the live trend. Recent values are listed below.</p>
                        </noscript>
                    </div>

                    <details class="mt-3">
                        <summary class="summary-marker">Latest readings</summary>
                        <div class="mt-3 overflow-x-auto">
                            <table class="table-base min-w-[30rem]">
                                <caption class="sr-only">Recent telemetry readings</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Time</th>
                                        <th scope="col">Object</th>
                                        <th scope="col">Room</th>
                                        <th scope="col">Humidity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentReadings as $point)
                                        <tr>
                                            <td class="font-mono text-xs text-ink-soft">{{ $point['time'] ? \Illuminate\Support\Carbon::parse($point['time'])->format('H:i:s') : '—' }}</td>
                                            <td class="tabular-nums">{{ number_format($point['temperature'], 2) }} °C</td>
                                            <td class="tabular-nums text-ink-soft">{{ number_format($point['temperature_room'], 2) }} °C</td>
                                            <td class="tabular-nums text-ink-soft">{{ number_format($point['humidity'], 2) }} %</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="py-8 text-center text-ink-soft">No telemetry readings yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </details>
                </section>

                <div class="grid gap-6 sm:grid-cols-2">
                    <section class="card reveal p-5 sm:p-6" aria-labelledby="actuators-heading">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="eyebrow">Actuators</p>
                                <h2 id="actuators-heading" class="mt-1 text-lg font-semibold">Relay test</h2>
                            </div>
                        </div>

                        <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                            Check each relay channel. A test switch takes control until you return it to automatic mode.
                        </p>

                        <div class="mt-5 overflow-hidden rounded-xl border border-line">
                            @foreach ([
                                'fan' => ['label' => 'Fan / circulation', 'hint' => 'Chamber air circulation'],
                                'heater_1' => ['label' => 'Heater 1', 'hint' => 'Main heating lamp'],
                                'heater_2' => ['label' => 'Heater 2', 'hint' => 'Secondary heating lamp'],
                                'blue_light' => ['label' => 'Blue light', 'hint' => 'Phototherapy simulation'],
                            ] as $key => $relay)
                                @php
                                    $on = (bool) ($telemetry['relays'][$key] ?? false);
                                    $override = array_key_exists($key, $relayOverrides);
                                @endphp
                                <div class="grid min-h-[4.5rem] grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-x-3 gap-y-1 border-b border-line px-3 py-3 last:border-b-0 sm:px-4">
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 text-sm font-medium">
                                            {{ $relay['label'] }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-ink-soft">{{ $relay['hint'] }}</p>
                                    </div>

                                    <div class="flex min-w-[6.5rem] flex-col items-start gap-1">
                                        <span data-relay-state="{{ $key }}" class="badge {{ $on ? 'badge-ok' : 'badge-neutral' }}">
                                            <span class="status-dot" aria-hidden="true"></span>
                                            <span data-relay-text>{{ $telemetry ? ($on ? 'On' : 'Off') : 'Unknown' }}</span>
                                        </span>
                                        <p data-relay-mode="{{ $key }}" class="text-[0.68rem] font-semibold {{ $override ? 'text-warn' : 'text-ink-soft' }}">
                                            {{ $override ? 'Test override' : ($telemetry ? 'Auto' : 'Awaiting data') }}
                                        </p>
                                    </div>

                                    <label class="relative inline-flex min-h-11 min-w-11 shrink-0 cursor-pointer items-center justify-center has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-45">
                                        <input
                                            type="checkbox"
                                            data-relay-toggle="{{ $key }}"
                                            data-can-manage="{{ $canManageActuators ? 'true' : 'false' }}"
                                            aria-describedby="relay-safety-note"
                                            @checked($on)
                                            @disabled(! $canManageActuators)
                                            class="peer sr-only"
                                        >
                                        <span aria-hidden="true" class="relative h-6 w-11 rounded-full bg-line-strong transition-colors duration-150 before:absolute before:left-1 before:top-1 before:size-4 before:rounded-full before:bg-white before:shadow-sm before:transition-transform before:duration-150 before:ease-[var(--ease-out-quart)] peer-checked:bg-accent peer-checked:before:translate-x-5 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent"></span>
                                        <span class="sr-only">{{ $relay['label'] }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 space-y-3 rounded-xl bg-warn-soft px-3 py-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p id="relay-safety-note" class="max-w-sm text-xs leading-relaxed text-warn">
                                    Test only. The device returns to automatic control after 60 seconds without a server response.
                                </p>
                                @if ($canManageActuators)
                                    <button type="button" data-relay-auto class="btn btn-secondary min-h-10 px-3 text-xs {{ empty($relayOverrides) ? 'hidden' : '' }}" @disabled(! $canManageActuators)>
                                        Return to auto
                                    </button>
                                @endif
                            </div>
                            <p data-relay-message role="status" aria-live="polite" class="text-xs font-medium text-danger"></p>
                        </div>
                    </section>

                    <section class="card reveal p-5 sm:p-6" aria-labelledby="sensor-health-heading">
                        <p class="eyebrow">Sensor health</p>
                        <h2 id="sensor-health-heading" class="mt-1 text-lg font-semibold">Probe status</h2>

                        <ul class="mt-5 divide-y divide-line">
                            <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div>
                                    <p class="text-sm font-medium">DS18B20</p>
                                    <p class="mt-0.5 text-xs text-ink-soft">Object contact probe</p>
                                </div>
                                <span data-sensor="ds18b20" class="badge {{ $sensorTone('ds18b20') }}">
                                    <span class="status-dot" aria-hidden="true"></span>
                                    <span data-sensor-text>{{ $sensorLabel('ds18b20') }}</span>
                                </span>
                            </li>
                            <li class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                <div>
                                    <p class="text-sm font-medium">DHT22</p>
                                    <p class="mt-0.5 text-xs text-ink-soft">Air temperature and humidity</p>
                                </div>
                                <span data-sensor="dht22" class="badge {{ $sensorTone('dht22') }}">
                                    <span class="status-dot" aria-hidden="true"></span>
                                    <span data-sensor-text>{{ $sensorLabel('dht22') }}</span>
                                </span>
                            </li>
                        </ul>

                        <p class="mt-5 flex items-start gap-2 rounded-xl bg-warn-soft px-3 py-2.5 text-xs leading-relaxed text-warn">
                            <x-icon name="alert" class="mt-px size-3.5" />
                            <span>The ESP32 switches both heater relays off locally when a probe faults. The web view only reports that state.</span>
                        </p>
                    </section>
                </div>
            </div>

            <aside class="space-y-6">
                <section class="card reveal p-5" aria-labelledby="baby-assignment-heading">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="baby-assignment-heading" class="text-lg font-semibold">Baby assignment</h2>
                        <x-status-badge :status="$incubator->currentBaby ? 'paired' : 'unpaired'" />
                    </div>

                    @if ($incubator->currentBaby)
                        <div class="mt-4 flex items-center gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-accent-soft text-sm font-semibold text-accent">
                                {{ mb_strtoupper(mb_substr($incubator->currentBaby->name, 0, 2)) }}
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('babies.show', $incubator->currentBaby) }}" class="block truncate font-semibold text-ink">{{ $incubator->currentBaby->name }}</a>
                                <p class="mt-0.5 font-mono text-xs text-ink-soft">{{ $incubator->currentBaby->baby_code }}</p>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-2 rounded-xl bg-bone/60 p-3 text-sm">
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">Gestation</dt>
                                <dd class="mt-1 tabular-nums">{{ $incubator->currentBaby->gestational_age_weeks }} weeks</dd>
                            </div>
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">Weight</dt>
                                <dd class="mt-1 tabular-nums">{{ number_format($incubator->currentBaby->weight_grams) }} g</dd>
                            </div>
                        </dl>

                        @can('manage-pairing')
                            <button
                                type="button"
                                class="btn btn-danger mt-4 w-full"
                                command="show-modal"
                                commandfor="unpair-dialog-{{ $incubator->id }}"
                            >
                                <x-icon name="unlink" class="size-4" />
                                Unpair baby
                            </button>

                            <dialog id="unpair-dialog-{{ $incubator->id }}" aria-labelledby="unpair-title-{{ $incubator->id }}">
                                <div class="dialog-card">
                                    <p class="eyebrow">Assignment</p>
                                    <h2 id="unpair-title-{{ $incubator->id }}" class="mt-2 text-xl font-semibold">Unpair this baby?</h2>
                                    <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                                        {{ $incubator->currentBaby->name }} will be released from {{ $incubator->name }}. The device returns to standby on its next telemetry response.
                                    </p>
                                    <form method="POST" action="{{ route('incubators.unpair', $incubator) }}" class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                        @csrf
                                        <button type="button" class="btn btn-secondary" command="close" commandfor="unpair-dialog-{{ $incubator->id }}" autofocus>Keep paired</button>
                                        <button type="submit" class="btn btn-danger">Unpair baby</button>
                                    </form>
                                </div>
                            </dialog>
                        @endcan
                    @else
                        @if ($canManagePairing)
                            @if ($unassignedBabies->isNotEmpty())
                                <form method="POST" action="{{ route('incubators.pair', $incubator) }}" class="mt-4 space-y-3">
                                    @csrf
                                    <x-field name="baby_id" label="Unassigned baby" type="select" required>
                                        <option value="">Choose a baby</option>
                                        @foreach ($unassignedBabies as $baby)
                                            <option value="{{ $baby->id }}" @selected((string) old('baby_id') === (string) $baby->id)>
                                                {{ $baby->name }} · {{ $baby->baby_code }}
                                            </option>
                                        @endforeach
                                    </x-field>
                                    <button type="submit" class="btn btn-primary w-full">
                                        <x-icon name="link" class="size-4" />
                                        Pair baby
                                    </button>
                                </form>
                            @else
                                <div class="mt-4 rounded-xl border border-dashed border-line-strong p-4 text-sm leading-relaxed text-ink-soft">
                                    No active baby is waiting for an incubator.
                                    <a href="{{ route('babies.index') }}#register-baby" class="font-semibold text-accent">Register one</a>.
                                </div>
                            @endif
                        @else
                            <p class="mt-4 text-sm leading-relaxed text-ink-soft">No baby assigned. Only admins and super admins can pair a device.</p>
                        @endif
                    @endif
                </section>

                <section class="card reveal p-5" aria-labelledby="device-configuration-heading">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="device-configuration-heading" class="text-lg font-semibold">Device configuration</h2>
                    </div>

                    @if ($config)
                        <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 text-sm">
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">Setpoint</dt>
                                <dd class="mt-1 font-semibold tabular-nums">{{ number_format($setpoint, 1) }} °C</dd>
                            </div>
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">PID gains</dt>
                                <dd class="mt-1 font-mono text-xs tabular-nums">{{ number_format((float) $config->pid_kp, 2) }} / {{ number_format((float) $config->pid_ki, 2) }} / {{ number_format((float) $config->pid_kd, 2) }}</dd>
                            </div>
                            <div>
                                <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">Blue light</dt>
                                <dd class="mt-1 font-semibold">{{ $config->blue_led_enabled ? 'Enabled' : 'Disabled' }}</dd>
                            </div>
                        </dl>

                        <p class="mt-4 border-t border-line pt-3 text-xs text-ink-soft">
                            Updated {{ $config->updated_at?->diffForHumans() }}
                        </p>

                        @can('manage-config')
                            <a href="{{ route('incubators.config.edit', $incubator) }}" class="btn btn-secondary mt-4 w-full">
                                Edit configuration
                            </a>
                        @endcan
                    @else
                        <p class="mt-4 text-sm leading-relaxed text-ink-soft">No configuration exists for this device yet.</p>
                        @can('manage-config')
                            <a href="{{ route('incubators.config.edit', $incubator) }}" class="btn btn-secondary mt-4 w-full">Create configuration</a>
                        @endcan
                    @endif
                </section>

                <section class="card reveal p-5" aria-labelledby="device-events-heading">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="device-events-heading" class="text-lg font-semibold">Device events</h2>
                        <span class="text-xs text-ink-soft">{{ $events->count() }}</span>
                    </div>

                    @if ($events->isEmpty())
                        <p class="mt-4 text-sm leading-relaxed text-ink-soft">No hardware events recorded.</p>
                    @else
                        <ul class="mt-3 divide-y divide-line">
                            @foreach ($events as $event)
                                <li class="py-3 first:pt-1 last:pb-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-sm font-medium leading-snug">{{ $event->message }}</p>
                                        <x-status-badge :status="$event->priority" :label="ucfirst($event->priority)" />
                                    </div>
                                    <p class="mt-1 text-xs text-ink-soft">
                                        <span class="font-mono">{{ $event->event_type }}</span>
                                        <span aria-hidden="true">·</span>
                                        <span class="tabular-nums">{{ $event->created_at?->diffForHumans() ?? '—' }}</span>
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </aside>
        </div>
    </div>

    <script>
        (() => {
            const root = document.querySelector('[data-dashboard]');
            if (!root) return;

            const initialState = @json($initialState);

            const dashboard = {
                telemetry: initialState.telemetry,
                history: initialState.history ?? [],
                sensorStatus: initialState.sensorStatus,
                relayOverrides: initialState.relayOverrides ?? {},
                relayPending: {},
                relayBusy: false,
                relayMessage: '',
                telemetryUnavailable: false,
                deviceStatus: @json($incubator->status),
                updatedAt: '',

                start() {
                    this.render();
                    this.poll();
                    setInterval(() => this.poll(), 2500);

                    root.querySelectorAll('[data-relay-toggle]').forEach((input) => {
                        input.addEventListener('change', () => this.setRelay(input.dataset.relayToggle, input.checked));
                    });
                    root.querySelector('[data-relay-auto]')?.addEventListener('click', () => this.resetRelays());
                },

                async poll() {
                    try {
                        const response = await fetch(initialState.endpoint, {
                            headers: { Accept: 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Telemetry request failed');
                        const data = await response.json();
                        this.telemetryUnavailable = false;
                        this.telemetry = data.telemetry;
                        this.relayOverrides = data.relay_overrides ?? {};
                        this.sensorStatus = data.telemetry?.sensor_status ?? null;
                        if (data.incubator?.status) this.deviceStatus = data.incubator.status;

                        for (const [key, value] of Object.entries(this.relayPending)) {
                            if (this.telemetry?.relays?.[key] === value) delete this.relayPending[key];
                        }

                        const recordedAt = data.telemetry?.recorded_at;
                        if (recordedAt && !this.history.some((point) => point.time === recordedAt)) {
                            this.history.push({ temperature: Number(data.telemetry.temperature_object), time: recordedAt });
                            this.history = this.history.slice(-20);
                        }

                        this.updatedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    } catch { this.telemetryUnavailable = true; this.updatedAt = 'Telemetry unavailable'; }
                    this.render();
                },

                render() {
                    root.classList.toggle('is-stale', this.telemetryUnavailable);
                    this.renderConnection();
                    this.renderStatus();
                    this.renderReadings();
                    this.renderRelays();
                    this.renderSensors();
                    this.renderChart();
                },

                renderConnection() {
                    const connection = root.querySelector('[data-connection]');
                    const dot = root.querySelector('[data-connection-dot]');
                    const updated = root.querySelector('[data-updated-at]');

                    if (connection) connection.textContent = this.telemetryUnavailable ? 'Stale' : 'Live';
                    if (dot) dot.classList.toggle('bg-accent', !this.telemetryUnavailable), dot.classList.toggle('bg-warn', this.telemetryUnavailable);
                    if (updated) updated.textContent = this.updatedAt || updated.textContent;
                },

                renderStatus() {
                    const badge = root.querySelector('[data-device-status]');
                    if (!badge) return;

                    const online = this.deviceStatus === 'online';
                    badge.classList.toggle('badge-ok', online);
                    badge.classList.toggle('badge-neutral', !online);
                    badge.querySelector('[data-status-text]').textContent = online ? 'Online' : this.title(this.deviceStatus);
                },

                renderReadings() {
                    const values = {
                        temperature_object: [this.telemetry?.temperature_object, 1],
                        temperature_room: [this.telemetry?.temperature_room, 1],
                        humidity: [this.telemetry?.humidity, 1],
                    };

                    for (const [key, [value, digits]] of Object.entries(values)) {
                        const element = root.querySelector(`[data-reading="${key}"]`);
                        if (!element) continue;
                        const next = this.number(value, digits);
                        if (element.textContent !== next) {
                            element.textContent = next;
                            element.classList.remove('is-fresh');
                            void element.offsetWidth;
                            element.classList.add('is-fresh');
                            window.setTimeout(() => element.classList.remove('is-fresh'), 900);
                        }
                    }

                },

                renderRelays() {
                    const relays = this.telemetry?.relays ?? null;
                    const overrides = this.relayOverrides ?? {};

                    for (const key of ['fan', 'heater_1', 'heater_2', 'blue_light']) {
                        const on = Object.prototype.hasOwnProperty.call(this.relayPending, key)
                            ? this.relayPending[key]
                            : Object.prototype.hasOwnProperty.call(overrides, key)
                                ? overrides[key]
                                : Boolean(relays?.[key]);

                        const input = root.querySelector(`[data-relay-toggle="${key}"]`);
                        if (input) {
                            input.checked = on;
                            input.disabled = !input.dataset.canManage || this.relayBusy;
                        }

                        const badge = root.querySelector(`[data-relay-state="${key}"]`);
                        const text = badge?.querySelector('[data-relay-text]');
                        const reportedOn = Boolean(relays?.[key]);
                        if (badge && text) {
                            badge.classList.toggle('badge-ok', reportedOn && relays !== null);
                            badge.classList.toggle('badge-neutral', !reportedOn || relays === null);
                            text.textContent = relays === null ? 'Unknown' : (reportedOn ? 'On' : 'Off');
                        }

                        const mode = root.querySelector(`[data-relay-mode="${key}"]`);
                        if (mode) {
                            const override = Object.prototype.hasOwnProperty.call(overrides, key);
                            mode.textContent = override ? 'Test override' : (relays ? 'Auto' : 'Awaiting data');
                            mode.classList.toggle('text-warn', override);
                            mode.classList.toggle('text-ink-soft', !override);
                        }
                    }

                    const auto = root.querySelector('[data-relay-auto]');
                    if (auto) {
                        auto.classList.toggle('hidden', Object.keys(overrides).length === 0);
                        auto.disabled = this.relayBusy;
                    }

                    const message = root.querySelector('[data-relay-message]');
                    if (message) message.textContent = this.relayMessage;
                },

                async setRelay(key, next) {
                    const input = root.querySelector(`[data-relay-toggle="${key}"]`);
                    if (!input || input.disabled || this.relayBusy) return;

                    const previous = Object.prototype.hasOwnProperty.call(this.relayOverrides, key)
                        ? this.relayOverrides[key]
                        : Boolean(this.telemetry?.relays?.[key]);
                    this.relayBusy = true;
                    this.relayPending[key] = next;
                    this.renderRelays();

                    try {
                        const response = await fetch(initialState.relayEndpoint, {
                            method: 'POST',
                            headers: this.requestHeaders(),
                            credentials: 'same-origin',
                            body: JSON.stringify({ relay: key, on: next }),
                        });
                        if (!response.ok) throw new Error('Relay command failed');
                        const data = await response.json();
                        this.relayOverrides = data.overrides ?? {};
                        delete this.relayPending[key];
                        this.relayMessage = '';
                        this.updatedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    } catch {
                        delete this.relayPending[key];
                        this.relayOverrides = { ...this.relayOverrides, [key]: previous };
                        this.relayMessage = 'Relay command failed. State unchanged.';
                    } finally {
                        this.relayBusy = false;
                        this.renderRelays();
                    }
                },

                async resetRelays() {
                    const previousOverrides = this.relayOverrides;
                    this.relayBusy = true;
                    this.renderRelays();
                    try {
                        const response = await fetch(initialState.relayAutoEndpoint, {
                            method: 'POST',
                            headers: this.requestHeaders(),
                            credentials: 'same-origin',
                            body: JSON.stringify({}),
                        });
                        if (!response.ok) throw new Error('Relay reset failed');
                        const data = await response.json();
                        this.relayOverrides = data.overrides ?? {};
                        this.relayPending = {};
                        this.relayMessage = '';
                        this.updatedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    } catch {
                        this.relayOverrides = previousOverrides;
                        this.relayMessage = 'Could not restore automatic control. Try again.';
                    } finally {
                        this.relayBusy = false;
                        this.renderRelays();
                    }
                },

                requestHeaders() {
                    return {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    };
                },

                renderSensors() {
                    for (const key of ['ds18b20', 'dht22']) {
                        const badge = root.querySelector(`[data-sensor="${key}"]`);
                        if (!badge) continue;

                        const text = badge.querySelector('[data-sensor-text]');
                        const state = this.telemetryUnavailable ? 'Stale' : this.sensorState(key);
                        badge.classList.toggle('badge-ok', state === 'OK');
                        badge.classList.toggle('badge-danger', state === 'Fault');
                        badge.classList.toggle('badge-warn', state === 'Stale');
                        badge.classList.toggle('badge-neutral', state === 'Unavailable');
                        text.textContent = state;
                    }
                },

                renderChart() {
                    const svg = root.querySelector('[data-chart]');
                    if (!svg) return;

                    const points = this.history
                        .filter((point) => Number.isFinite(Number(point.temperature)))
                        .map((point) => ({ value: Number(point.temperature), time: point.time }));

                    const width = 600;
                    const height = 190;
                    const pad = { top: 18, right: 14, bottom: 24, left: 40 };
                    const innerWidth = width - pad.left - pad.right;
                    const innerHeight = height - pad.top - pad.bottom;

                    if (!points.length) {
                        svg.innerHTML = `<text x="${width / 2}" y="${height / 2}" text-anchor="middle" class="chart-label">Waiting for telemetry</text>`;
                        return;
                    }

                    const setpoint = Number(initialState.setpoint);
                    const values = points.map((point) => point.value).concat(Number.isFinite(setpoint) ? [setpoint] : []);
                    let min = Math.min(...values);
                    let max = Math.max(...values);
                    if (max - min < 1) {
                        min -= 0.5;
                        max += 0.5;
                    }
                    const padding = Math.max((max - min) * 0.12, 0.4);
                    min -= padding;
                    max += padding;

                    const x = (index) => points.length === 1
                        ? pad.left + innerWidth / 2
                        : pad.left + (index / (points.length - 1)) * innerWidth;
                    const y = (value) => pad.top + ((max - value) / (max - min)) * innerHeight;
                    const line = points.map((point, index) => `${x(index).toFixed(1)},${y(point.value).toFixed(1)}`).join(' ');
                    const area = `${pad.left},${pad.top + innerHeight} ${line} ${pad.left + innerWidth},${pad.top + innerHeight}`;
                    const last = points[points.length - 1];

                    let grid = '';
                    for (let step = 0; step <= 3; step++) {
                        const value = min + ((max - min) / 3) * step;
                        const yLine = y(value).toFixed(1);
                        grid += `<line class="chart-grid" x1="${pad.left}" y1="${yLine}" x2="${width - pad.right}" y2="${yLine}" />`;
                        grid += `<text class="chart-label" x="${pad.left - 8}" y="${Number(yLine) + 3}" text-anchor="end">${value.toFixed(1)}</text>`;
                    }

                    const setpointLine = Number.isFinite(setpoint)
                        ? `<line class="chart-setpoint" x1="${pad.left}" y1="${y(setpoint).toFixed(1)}" x2="${width - pad.right}" y2="${y(setpoint).toFixed(1)}" />
                           <text class="chart-setpoint-label" x="${width - pad.right}" y="${(y(setpoint) - 5).toFixed(1)}" text-anchor="end">setpoint</text>`
                        : '';

                    const timeLabel = (value, anchor) => {
                        const date = new Date(value);
                        if (Number.isNaN(date.getTime())) return '';
                        return `<text class="chart-label" x="${anchor}" y="${height - 6}" text-anchor="${anchor < width / 2 ? 'start' : 'end'}">${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</text>`;
                    };

                    svg.innerHTML = `
                        ${grid}
                        ${setpointLine}
                        <polygon class="chart-area" points="${area}" />
                        <polyline class="chart-line" points="${line}" />
                        <circle class="chart-dot" cx="${x(points.length - 1).toFixed(1)}" cy="${y(last.value).toFixed(1)}" r="3.5" />
                        ${timeLabel(points[0].time, pad.left)}
                        ${timeLabel(last.time, width - pad.right)}
                    `;
                },

                sensorState(key) {
                    const value = this.sensorStatus?.[key];
                    if (value === true || value === 'OK' || value === 'ok') return 'OK';
                    if (value === false || value === 'ERROR' || value === 'DISCONNECTED') return 'Fault';
                    return 'Unavailable';
                },

                number(value, digits) {
                    const numeric = Number(value);
                    return value === null || value === undefined || Number.isNaN(numeric) ? '—' : numeric.toFixed(digits);
                },

                title(value) {
                    return value ? value.charAt(0).toUpperCase() + value.slice(1) : 'Unknown';
                },
            };

            dashboard.start();
        })();
    </script>
@endsection
