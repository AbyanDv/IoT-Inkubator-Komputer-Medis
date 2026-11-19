@extends('layouts.app')

@section('title', 'Incubators')

@section('content')
    @php
        $total = $incubators->count();
        $online = $incubators->filter(fn ($incubator) => $incubator->status === 'online')->count();
        $paired = $incubators->filter(fn ($incubator) => $incubator->current_baby_id !== null)->count();
        $attention = $incubators->filter(function ($incubator) {
            $sensors = $incubator->latestTelemetry?->raw_payload['sensor_status'] ?? [];
            return $incubator->status === 'error'
                || collect($sensors)->contains(fn ($value) => in_array($value, [false, 'ERROR', 'DISCONNECTED'], true));
        })->count();
    @endphp

    <div class="space-y-8">
        <div class="reveal flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-2xl">
                <p class="eyebrow">Fleet overview</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Incubators</h1>
                <p class="mt-3 max-w-xl text-[0.95rem] leading-relaxed text-ink-soft">
                    Thermal telemetry, actuator state, and baby assignment for every device on the bench.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="#devices" class="btn btn-secondary">View devices</a>
                <a href="{{ route('babies.index') }}" class="btn btn-primary">
                    <x-icon name="baby" class="size-4" />
                    Babies
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 overflow-hidden rounded-2xl border border-line bg-paper sm:grid-cols-4">
            <div class="border-b border-r border-line p-4 sm:border-b-0 sm:p-5">
                <p class="eyebrow">Devices</p>
                <p class="mt-2 text-3xl font-semibold tracking-[-0.04em] tabular-nums">{{ $total }}</p>
            </div>
            <div class="border-b border-line p-4 sm:border-b-0 sm:border-r sm:p-5">
                <p class="eyebrow">Online</p>
                <p class="mt-2 text-3xl font-semibold tracking-[-0.04em] tabular-nums {{ $online > 0 ? 'text-accent' : '' }}">{{ $online }}</p>
            </div>
            <div class="border-r border-line p-4 sm:p-5">
                <p class="eyebrow">Paired</p>
                <p class="mt-2 text-3xl font-semibold tracking-[-0.04em] tabular-nums">{{ $paired }}</p>
            </div>
            <div class="p-4 sm:p-5">
                <p class="eyebrow">Needs attention</p>
                <p class="mt-2 text-3xl font-semibold tracking-[-0.04em] tabular-nums {{ $attention > 0 ? 'text-warn' : '' }}">{{ $attention }}</p>
            </div>
        </div>

        <section id="devices" aria-labelledby="devices-heading" class="space-y-4">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <h2 id="devices-heading" class="text-lg font-semibold">All devices</h2>
                    <p class="mt-1 text-sm text-ink-soft">Latest reading from each incubator.</p>
                </div>
                <p class="hidden text-xs text-ink-soft sm:block">Polling is per device · 2.5 s</p>
            </div>

            @if ($incubators->isEmpty())
                <div class="card flex flex-col items-center justify-center gap-3 border-dashed px-6 py-16 text-center">
                    <span class="grid size-11 place-items-center rounded-full bg-accent-soft text-accent">
                        <x-icon name="fleet" class="size-5" />
                    </span>
                    <div>
                        <p class="font-semibold">No incubators registered</p>
                        <p class="mt-1 text-sm text-ink-soft">Register a device through the provisioning API to see it here.</p>
                    </div>
                </div>
            @else
                <div class="space-y-3 sm:hidden">
                    @foreach ($incubators as $incubator)
                        @php
                            $telemetry = $incubator->latestTelemetry;
                            $setpoint = $incubator->activeConfig?->temperature_setpoint;
                        @endphp
                        <article class="card card-link p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold">{{ $incubator->name }}</h3>
                                    <p class="mt-0.5 font-mono text-xs text-ink-soft">{{ $incubator->device_code }}</p>
                                </div>
                                <x-status-badge :status="$incubator->status" />
                            </div>

                            <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-bone/70 p-3">
                                <div>
                                    <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">Object</dt>
                                    <dd class="mt-1 text-lg font-semibold tabular-nums">
                                        {{ $telemetry ? number_format((float) $telemetry->temperature_object, 1) : '—' }}
                                        <span class="text-xs font-medium text-ink-soft">°C</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">Humidity</dt>
                                    <dd class="mt-1 text-lg font-semibold tabular-nums">
                                        {{ $telemetry ? number_format((float) $telemetry->humidity, 1) : '—' }}
                                        <span class="text-xs font-medium text-ink-soft">%</span>
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-3 space-y-1.5 text-sm">
                                <p class="flex justify-between gap-3">
                                    <span class="text-ink-soft">Baby</span>
                                    <span class="truncate font-medium">{{ $incubator->currentBaby?->name ?? 'Standby' }}</span>
                                </p>
                                <p class="flex justify-between gap-3">
                                    <span class="text-ink-soft">Target</span>
                                    <span class="tabular-nums">{{ $setpoint ? number_format((float) $setpoint, 1).' °C' : '—' }}</span>
                                </p>
                                <p class="flex justify-between gap-3">
                                    <span class="text-ink-soft">Last heartbeat</span>
                                    <span class="tabular-nums">{{ $incubator->last_heartbeat_at?->diffForHumans() ?? 'Never' }}</span>
                                </p>
                            </div>

                            <a href="{{ route('incubators.show', $incubator) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-accent">
                                Open device
                                <x-icon name="chevron-right" class="size-3.5" />
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="hidden overflow-hidden rounded-2xl border border-line bg-paper sm:block">
                    <table class="table-base">
                        <caption class="sr-only">Registered incubators and their latest telemetry</caption>
                        <thead>
                            <tr>
                                <th scope="col">Device</th>
                                <th scope="col">Status</th>
                                <th scope="col">Object temp.</th>
                                <th scope="col">Humidity</th>
                                <th scope="col">Baby</th>
                                <th scope="col">Last heartbeat</th>
                                <th scope="col"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($incubators as $incubator)
                                @php($telemetry = $incubator->latestTelemetry)
                                <tr class="transition-colors duration-150 hover:bg-bone/60">
                                    <th scope="row" class="font-medium">
                                        <a href="{{ route('incubators.show', $incubator) }}" class="block">
                                            <span class="block font-semibold text-ink">{{ $incubator->name }}</span>
                                            <span class="mt-0.5 block font-mono text-xs font-normal text-ink-soft">{{ $incubator->device_code }}</span>
                                        </a>
                                    </th>
                                    <td><x-status-badge :status="$incubator->status" /></td>
                                    <td class="tabular-nums">{{ $telemetry ? number_format((float) $telemetry->temperature_object, 1).' °C' : '—' }}</td>
                                    <td class="tabular-nums">{{ $telemetry ? number_format((float) $telemetry->humidity, 1).' %' : '—' }}</td>
                                    <td>
                                        @if ($incubator->currentBaby)
                                            <a href="{{ route('babies.show', $incubator->currentBaby) }}" class="font-medium text-accent">{{ $incubator->currentBaby->name }}</a>
                                        @else
                                            <span class="text-ink-soft">Standby</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums text-ink-soft">{{ $incubator->last_heartbeat_at?->diffForHumans() ?? 'Never' }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('incubators.show', $incubator) }}" class="btn btn-ghost btn-sm px-2.5">
                                            Open
                                            <x-icon name="chevron-right" class="size-3.5" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
