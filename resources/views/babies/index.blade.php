@extends('layouts.app')

@section('title', 'Babies')

@section('content')
    @php
        $birthValue = old('birth_date');
        if (is_string($birthValue) && $birthValue !== '' && ! str_contains($birthValue, 'T')) {
            $birthValue .= 'T00:00';
        }
    @endphp

    <div class="space-y-8">
        <div class="reveal flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-2xl">
                <p class="eyebrow">Simulation records</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Babies</h1>
                <p class="mt-3 max-w-xl text-[0.95rem] leading-relaxed text-ink-soft">
                    Profiles used to give incubator telemetry a context. Pairing happens from the device page.
                </p>
            </div>
            <a href="#register-baby" class="btn btn-primary">
                <x-icon name="plus" class="size-4" />
                Register baby
            </a>
        </div>

        <section aria-labelledby="baby-records-heading" class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 id="baby-records-heading" class="text-lg font-semibold">Records</h2>
                    <p class="mt-1 text-sm text-ink-soft">
                        {{ $babies->total() }} {{ Str::plural('profile', $babies->total()) }} found
                    </p>
                </div>
            </div>

            <search>
                <form method="GET" action="{{ route('babies.index') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-line bg-paper p-4">
                    <div class="min-w-52 flex-1">
                        <label for="baby-search" class="field-label">Search</label>
                        <input
                            id="baby-search"
                            name="search"
                            type="search"
                            value="{{ request('search') }}"
                            placeholder="Name or baby code"
                            autocomplete="off"
                            spellcheck="false"
                            class="field-input"
                        >
                    </div>
                    <div class="min-w-40">
                        <label for="baby-status" class="field-label">Status</label>
                        <select id="baby-status" name="status" class="field-input appearance-none bg-[length:0.7rem] bg-[right_1rem_center] bg-no-repeat pr-10" style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 12 8%22 fill=%22none%22 stroke=%22%235d6a64%22 stroke-width=%221.5%22%3E%3Cpath d=%22m1 1.5 5 5 5-5%22/%3E%3C/svg%3E')">
                            <option value="">All statuses</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary">
                        <x-icon name="search" class="size-4" />
                        Filter
                    </button>
                    @if (request()->filled('search') || request()->filled('status'))
                        <a href="{{ route('babies.index') }}" class="btn btn-ghost">Reset</a>
                    @endif
                </form>
            </search>

            @if ($babies->isEmpty())
                <div class="card flex flex-col items-center justify-center gap-3 border-dashed px-6 py-16 text-center">
                    <span class="grid size-11 place-items-center rounded-full bg-accent-soft text-accent">
                        <x-icon name="baby" class="size-5" />
                    </span>
                    <div>
                        <p class="font-semibold">No baby profiles match</p>
                        <p class="mt-1 text-sm text-ink-soft">Adjust the filter or register a new simulation record below.</p>
                    </div>
                </div>
            @else
                <div class="space-y-3 sm:hidden">
                    @foreach ($babies as $baby)
                        <article class="card card-link p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold">
                                        <a href="{{ route('babies.show', $baby) }}" class="block">{{ $baby->name }}</a>
                                    </h3>
                                    <p class="mt-0.5 font-mono text-xs text-ink-soft">{{ $baby->baby_code }}</p>
                                </div>
                                <x-status-badge :status="$baby->status" />
                            </div>
                            <dl class="mt-3 space-y-1.5 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-ink-soft">Incubator</dt>
                                    <dd class="truncate text-right font-medium">{{ $baby->currentIncubator?->name ?? 'Unassigned' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-ink-soft">Birth</dt>
                                    <dd class="tabular-nums">{{ $baby->birth_date?->format('d M Y') }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-ink-soft">Weight</dt>
                                    <dd class="tabular-nums">{{ number_format($baby->weight_grams) }} g</dd>
                                </div>
                            </dl>
                            <a href="{{ route('babies.show', $baby) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-accent">
                                Open profile
                                <x-icon name="chevron-right" class="size-3.5" />
                            </a>
                        </article>
                    @endforeach
                </div>

                <div class="hidden overflow-hidden rounded-2xl border border-line bg-paper sm:block">
                    <table class="table-base">
                        <caption class="sr-only">Simulation baby profiles</caption>
                        <thead>
                            <tr>
                                <th scope="col">Baby</th>
                                <th scope="col">Status</th>
                                <th scope="col">Incubator</th>
                                <th scope="col">Birth date</th>
                                <th scope="col">Gestation</th>
                                <th scope="col">Weight</th>
                                <th scope="col"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($babies as $baby)
                                <tr class="transition-colors duration-150 hover:bg-bone/60">
                                    <th scope="row" class="font-medium">
                                        <a href="{{ route('babies.show', $baby) }}" class="block">
                                            <span class="block font-semibold text-ink">{{ $baby->name }}</span>
                                            <span class="mt-0.5 block font-mono text-xs font-normal text-ink-soft">{{ $baby->baby_code }}</span>
                                        </a>
                                    </th>
                                    <td><x-status-badge :status="$baby->status" /></td>
                                    <td>
                                        @if ($baby->currentIncubator)
                                            <a href="{{ route('incubators.show', $baby->currentIncubator) }}" class="font-medium text-accent">{{ $baby->currentIncubator->name }}</a>
                                        @else
                                            <span class="text-ink-soft">Unassigned</span>
                                        @endif
                                    </td>
                                    <td class="tabular-nums text-ink-soft">{{ $baby->birth_date?->format('d M Y') }}</td>
                                    <td class="tabular-nums">{{ $baby->gestational_age_weeks }} wk</td>
                                    <td class="tabular-nums">{{ number_format($baby->weight_grams) }} g</td>
                                    <td class="text-right">
                                        <a href="{{ route('babies.show', $baby) }}" class="btn btn-ghost btn-sm px-2.5">
                                            Open
                                            <x-icon name="chevron-right" class="size-3.5" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-1">
                    {{ $babies->links() }}
                </div>
            @endif
        </section>

        <section id="register-baby" class="card reveal scroll-mt-24 overflow-hidden" aria-labelledby="register-baby-heading">
            <div class="border-b border-line px-5 py-4 sm:px-6">
                <p class="eyebrow">New record</p>
                <h2 id="register-baby-heading" class="mt-1 text-lg font-semibold">Register simulation baby</h2>
                <p class="mt-0.5 text-sm text-ink-soft">All fields describe a simulated patient profile. They are not clinical records.</p>
            </div>

            <form method="POST" action="{{ route('babies.store') }}" class="px-5 py-6 sm:px-6">
                @csrf

                @if ($errors->any())
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger" role="alert">
                        <x-icon name="alert" class="mt-0.5 size-4" />
                        <div>
                            <p class="font-semibold">Please check the record.</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field name="baby_code" label="Baby code" :value="old('baby_code')" placeholder="BAYI-2026-003" required autocomplete="off" inputmode="text" hint="Unique identifier used on the incubator LCD." />
                    <x-field name="name" label="Name" :value="old('name')" placeholder="Baby Ny. Sari" required autocomplete="off" />
                    <x-field name="birth_date" label="Birth date & time" type="datetime-local" :value="$birthValue" required />
                    <x-field name="gestational_age_weeks" label="Gestational age (weeks)" type="number" :value="old('gestational_age_weeks')" min="20" max="45" step="1" required inputmode="numeric" />
                    <x-field name="weight_grams" label="Birth weight (g)" type="number" :value="old('weight_grams')" min="100" max="10000" step="1" required inputmode="numeric" />
                    <x-field name="length_cm" label="Birth length (cm)" type="number" :value="old('length_cm')" min="10" max="100" step="0.1" required inputmode="decimal" />

                    <fieldset class="sm:col-span-2">
                        <legend class="field-label">Gender</legend>
                        <div class="mt-2 flex flex-wrap gap-3">
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line bg-bone/40 px-4 text-sm font-medium transition-colors duration-150 has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                <input type="radio" name="gender" value="male" @checked(old('gender', 'male') === 'male') class="size-4 accent-[var(--color-accent)]">
                                Male
                            </label>
                            <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line bg-bone/40 px-4 text-sm font-medium transition-colors duration-150 has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                                <input type="radio" name="gender" value="female" @checked(old('gender') === 'female') class="size-4 accent-[var(--color-accent)]">
                                Female
                            </label>
                        </div>
                        @error('gender')
                            <p class="field-error" role="alert">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <x-field name="notes" label="Notes" type="textarea" :value="old('notes')" rows="3" placeholder="Optional simulation context" hint="Up to 5,000 characters." />
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs leading-relaxed text-ink-soft">Status starts as active. Complete it from the profile page.</p>
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="plus" class="size-4" />
                        Create baby
                    </button>
                </div>
            </form>
        </section>
    </div>
@endsection
