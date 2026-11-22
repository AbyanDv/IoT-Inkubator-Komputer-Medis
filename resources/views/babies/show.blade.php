@extends('layouts.app')

@section('title', $baby->name)

@section('content')
    @php
        $birthValue = $baby->birth_date?->format('Y-m-d\TH:i');
        $latest = $history->last();
        $ageDays = $baby->birth_date ? (int) $baby->birth_date->diffInDays(now()) : null;
        $pairings = $baby->devicePairings->sortByDesc('paired_at');
        $readings = $baby->telemetries->take(12);
        $initials = mb_strtoupper(mb_substr($baby->name, 0, 2));
    @endphp

    <div class="space-y-6">
        <div class="reveal flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0">
                <a href="{{ route('babies.index') }}" class="back-link">
                    <x-icon name="arrow-left" class="size-3.5" />
                    All babies
                </a>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-accent-soft text-base font-semibold text-accent">{{ $initials }}</span>
                    <h1 class="text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">{{ $baby->name }}</h1>
                    <x-status-badge :status="$baby->status" />
                </div>
                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-soft">
                    <span class="font-mono">{{ $baby->baby_code }}</span>
                    <span aria-hidden="true">·</span>
                    <span class="capitalize">{{ $baby->gender }}</span>
                    <span aria-hidden="true">·</span>
                    <span class="tabular-nums">{{ $baby->gestational_age_weeks }} weeks gestation</span>
                    <span aria-hidden="true">·</span>
                    <span class="tabular-nums">{{ number_format($baby->weight_grams) }} g at birth</span>
                </p>
            </div>

            @if ($baby->currentIncubator)
                <a href="{{ route('incubators.show', $baby->currentIncubator) }}" class="btn btn-secondary">
                    <x-icon name="fleet" class="size-4" />
                    Open {{ $baby->currentIncubator->name }}
                </a>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="space-y-6">
                <section class="card reveal p-5 sm:p-6" aria-labelledby="monitoring-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="eyebrow">Monitoring context</p>
                            <h2 id="monitoring-heading" class="mt-1 text-lg font-semibold">Latest recorded vitals</h2>
                        </div>
                        <p class="text-xs text-ink-soft">
                            @if ($latest)
                                <span class="tabular-nums">{{ $latest->recorded_at?->format('d M Y · H:i:s') ?? 'Unknown time' }}</span>
                            @else
                                No readings yet
                            @endif
                        </p>
                    </div>

                    @if ($baby->currentIncubator)
                        <div class="mt-5 flex items-center gap-3 rounded-xl border border-line bg-bone/60 p-4">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-paper text-ink-soft">
                                <x-icon name="fleet" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.12em] text-ink-soft">Currently paired with</p>
                                <a href="{{ route('incubators.show', $baby->currentIncubator) }}" class="block truncate font-semibold text-ink">{{ $baby->currentIncubator->name }}</a>
                            </div>
                            <x-status-badge :status="$baby->currentIncubator->status" class="ml-auto" />
                        </div>
                    @else
                        <p class="mt-5 rounded-xl border border-dashed border-line-strong px-4 py-3 text-sm leading-relaxed text-ink-soft">
                            {{ $baby->status === 'completed' ? 'This simulation profile is completed and no longer monitored.' : 'Not assigned to an incubator. Pair from the device page.' }}
                        </p>
                    @endif

                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-line p-3">
                            <p class="eyebrow">Baby</p>
                            <p class="mt-2 flex items-baseline gap-1">
                                <span class="text-2xl font-semibold tabular-nums tracking-[-0.04em]">{{ $latest ? number_format((float) $latest->temperature_object, 1) : '—' }}</span>
                                <span class="text-xs font-medium text-ink-soft">°C</span>
                            </p>
                        </div>
                        <div class="rounded-xl border border-line p-3">
                            <p class="eyebrow">Room</p>
                            <p class="mt-2 flex items-baseline gap-1">
                                <span class="text-2xl font-semibold tabular-nums tracking-[-0.04em]">{{ $latest ? number_format((float) $latest->temperature_room, 1) : '—' }}</span>
                                <span class="text-xs font-medium text-ink-soft">°C</span>
                            </p>
                        </div>
                        <div class="rounded-xl border border-line p-3">
                            <p class="eyebrow">Humidity</p>
                            <p class="mt-2 flex items-baseline gap-1">
                                <span class="text-2xl font-semibold tabular-nums tracking-[-0.04em]">{{ $latest ? number_format((float) $latest->humidity, 1) : '—' }}</span>
                                <span class="text-xs font-medium text-ink-soft">%</span>
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-xs leading-relaxed text-ink-soft">
                        Telemetry is shown for context only. The paired device records these values; the profile page does not control the heater.
                    </p>
                </section>

                <section class="card reveal p-5 sm:p-6" aria-labelledby="pairing-history-heading">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="pairing-history-heading" class="text-lg font-semibold">Pairing history</h2>
                        <span class="text-xs text-ink-soft">{{ $pairings->count() }} {{ Str::plural('record', $pairings->count()) }}</span>
                    </div>

                    @if ($pairings->isEmpty())
                        <p class="mt-4 text-sm leading-relaxed text-ink-soft">This baby has never been paired with an incubator.</p>
                    @else
                        <ol class="mt-4 space-y-4">
                            @foreach ($pairings as $pairing)
                                <li class="flex gap-3">
                                    <span class="mt-1 grid size-7 shrink-0 place-items-center rounded-full {{ $pairing->status === 'active' ? 'bg-accent-soft text-accent' : 'bg-bone text-ink-soft' }}">
                                        <x-icon :name="$pairing->status === 'active' ? 'link' : 'unlink'" class="size-3.5" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium">
                                                @if ($pairing->incubator)
                                                    <a href="{{ route('incubators.show', $pairing->incubator) }}" class="text-accent">{{ $pairing->incubator->name }}</a>
                                                @else
                                                    Removed device
                                                @endif
                                            </p>
                                            <x-status-badge :status="$pairing->status" />
                                        </div>
                                        <p class="mt-1 text-xs tabular-nums text-ink-soft">
                                            Paired {{ $pairing->paired_at?->format('d M Y · H:i') ?? 'unknown' }}
                                            @if ($pairing->unpaired_at)
                                                <span aria-hidden="true">→</span> released {{ $pairing->unpaired_at->format('d M Y · H:i') }}
                                            @endif
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                <section class="card reveal overflow-hidden" aria-labelledby="reading-history-heading">
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-4 sm:px-6">
                        <div>
                            <h2 id="reading-history-heading" class="text-lg font-semibold">Telemetry history</h2>
                            <p class="mt-0.5 text-sm text-ink-soft">Readings recorded while this profile was monitored.</p>
                        </div>
                        <span class="whitespace-nowrap text-xs text-ink-soft">{{ $history->count() }} recent</span>
                    </div>

                    @if ($readings->isEmpty())
                        <p class="px-5 py-10 text-center text-sm text-ink-soft sm:px-6">No telemetry has been linked to this profile.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="table-base min-w-[44rem]">
                                <caption class="sr-only">Recent telemetry readings for {{ $baby->name }}</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Recorded</th>
                                        <th scope="col">Baby</th>
                                        <th scope="col">Room</th>
                                        <th scope="col">Humidity</th>
                                        <th scope="col">Heater 1</th>
                                        <th scope="col">Heater 2</th>
                                        <th scope="col">Fan</th>
                                        <th scope="col">Blue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($readings as $reading)
                                        <tr>
                                            <td class="whitespace-nowrap font-mono text-xs text-ink-soft">{{ $reading->recorded_at?->format('d M · H:i:s') ?? '—' }}</td>
                                            <td class="tabular-nums">{{ number_format((float) $reading->temperature_object, 2) }} °C</td>
                                            <td class="tabular-nums text-ink-soft">{{ number_format((float) $reading->temperature_room, 2) }} °C</td>
                                            <td class="tabular-nums text-ink-soft">{{ number_format((float) $reading->humidity, 2) }} %</td>
                                            <td class="tabular-nums text-ink-soft">{{ $reading->heater_1_status ? 'On' : 'Off' }}</td>
                                            <td class="tabular-nums text-ink-soft">{{ $reading->heater_2_status ? 'On' : 'Off' }}</td>
                                            <td class="tabular-nums text-ink-soft">{{ $reading->fan_status ? 'On' : 'Off' }}</td>
                                            <td class="tabular-nums text-ink-soft">{{ $reading->blue_led_status ? 'On' : 'Off' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="space-y-6">
                <section class="card reveal p-5" aria-labelledby="profile-heading">
                    <h2 id="profile-heading" class="text-lg font-semibold">Profile</h2>

                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft">Baby code</dt>
                            <dd class="font-mono text-xs">{{ $baby->baby_code }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft">Birth date</dt>
                            <dd class="text-right tabular-nums">{{ $baby->birth_date?->format('d M Y · H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft">Age</dt>
                            <dd class="tabular-nums">{{ $ageDays !== null ? $ageDays.' '.Str::plural('day', $ageDays) : '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft">Gestation</dt>
                            <dd class="tabular-nums">{{ $baby->gestational_age_weeks }} weeks</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft">Weight</dt>
                            <dd class="tabular-nums">{{ number_format($baby->weight_grams) }} g</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft">Length</dt>
                            <dd class="tabular-nums">{{ number_format((float) $baby->length_cm, 1) }} cm</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft">Registered</dt>
                            <dd class="tabular-nums">{{ $baby->created_at?->format('d M Y') }}</dd>
                        </div>
                    </dl>

                    @if ($baby->notes)
                        <div class="mt-4 border-t border-line pt-4">
                            <p class="eyebrow">Notes</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-soft">{{ $baby->notes }}</p>
                        </div>
                    @endif
                </section>

                <section class="card reveal overflow-hidden" aria-labelledby="edit-baby-heading">
                    <div class="border-b border-line px-5 py-4">
                        <h2 id="edit-baby-heading" class="text-lg font-semibold">Edit profile</h2>
                        <p class="mt-0.5 text-sm text-ink-soft">Changes apply to the simulation record only.</p>
                    </div>

                    @if ($errors->any())
                        <div class="mx-5 mt-4 flex items-start gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                            <x-icon name="alert" class="mt-0.5 size-4" />
                            <div>
                                <p class="font-semibold">Please check the profile.</p>
                                <ul class="mt-1 list-inside list-disc">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('babies.update', $baby) }}" class="space-y-4 px-5 py-5">
                        @csrf
                        @method('PUT')

                        <x-field name="baby_code" label="Baby code" :value="$baby->baby_code" required autocomplete="off" />
                        <x-field name="name" label="Name" :value="$baby->name" required autocomplete="off" />
                        <x-field name="birth_date" label="Birth date & time" type="datetime-local" :value="$birthValue" required />
                        <div class="grid grid-cols-2 gap-3">
                            <x-field name="gestational_age_weeks" label="Gestation (wk)" type="number" :value="$baby->gestational_age_weeks" min="20" max="45" step="1" required inputmode="numeric" />
                            <x-field name="weight_grams" label="Weight (g)" type="number" :value="$baby->weight_grams" min="100" max="10000" step="1" required inputmode="numeric" />
                        </div>
                        <x-field name="length_cm" label="Length (cm)" type="number" :value="$baby->length_cm" min="10" max="100" step="0.1" required inputmode="decimal" />

                        <fieldset>
                            <legend class="field-label">Gender</legend>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line bg-bone/40 px-4 text-sm font-medium transition-colors duration-150 has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                    <input type="radio" name="gender" value="male" @checked(old('gender', $baby->gender) === 'male') class="size-4 accent-[var(--color-accent)]">
                                    Male
                                </label>
                                <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line bg-bone/40 px-4 text-sm font-medium transition-colors duration-150 has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                    <input type="radio" name="gender" value="female" @checked(old('gender', $baby->gender) === 'female') class="size-4 accent-[var(--color-accent)]">
                                    Female
                                </label>
                            </div>
                            @error('gender')
                                <p class="field-error" role="alert">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <x-field name="notes" label="Notes" type="textarea" :value="$baby->notes" rows="3" />

                        <button type="submit" class="btn btn-primary w-full">
                            <x-icon name="check" class="size-4" />
                            Save changes
                        </button>
                    </form>
                </section>

                @if ($baby->status === 'active')
                    <section class="rounded-2xl border border-danger/20 bg-danger-soft p-5">
                        <h2 class="text-sm font-semibold text-danger">Complete simulation</h2>
                        <p class="mt-1.5 text-xs leading-relaxed text-danger">
                            Closes the active pairing and clears the incubator assignment. Telemetry history is preserved.
                        </p>
                        <button type="button" class="btn btn-danger mt-4 w-full" command="show-modal" commandfor="complete-baby-dialog">
                            <x-icon name="x" class="size-4" />
                            Complete profile
                        </button>
                    </section>

                    <dialog id="complete-baby-dialog" aria-labelledby="complete-baby-title">
                        <div class="dialog-card">
                            <p class="eyebrow">Simulation record</p>
                            <h2 id="complete-baby-title" class="mt-2 text-xl font-semibold">Complete {{ $baby->name }}?</h2>
                            <p class="mt-2 text-sm leading-relaxed text-ink-soft">
                                The profile becomes completed. Any active pairing ends and the incubator is released. This cannot be undone from the interface.
                            </p>
                            <form method="POST" action="{{ route('babies.destroy', $baby) }}" class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-secondary" command="close" commandfor="complete-baby-dialog" autofocus>Keep active</button>
                                <button type="submit" class="btn btn-danger">Complete profile</button>
                            </form>
                        </div>
                    </dialog>
                @else
                    <section class="card reveal p-5">
                        <h2 class="text-sm font-semibold">Completed profile</h2>
                        <p class="mt-1.5 text-xs leading-relaxed text-ink-soft">
                            This record is closed. It stays available for audit and telemetry history.
                        </p>
                    </section>
                @endif
            </aside>
        </div>
    </div>
@endsection
