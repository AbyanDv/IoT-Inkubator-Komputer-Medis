<div wire:poll.2s class="space-y-6">
    @if (! $baby)
        <div class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center">
            <h1 class="text-lg font-semibold">Belum ada bayi di inkubator</h1>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                Pilih bayi dari daftar lalu aktifkan di inkubator untuk mulai memantau suhu.
            </p>
            <a href="{{ route('babies') }}"
               class="mt-6 inline-block rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white
                      transition duration-150 hover:bg-slate-800 active:scale-[0.99]">
                Buka halaman Bayi
            </a>
        </div>
    @else
        {{-- Baby identity --}}
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight">{{ $baby->name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-600">
                    <span class="tabular-nums">{{ number_format($baby->weight_grams, 0, ',', '.') }} gram</span>
                    <span aria-hidden="true" class="text-slate-300">•</span>
                    <span class="tabular-nums">{{ $ageHours < 1 ? 'Baru masuk' : ($ageHours < 24 ? number_format($ageHours, 0).' jam' : number_format($ageHours / 24, 0).' hari') }} di inkubator</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if ($baby->premature)
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
                        Prematur
                    </span>
                @else
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                        Cukup bulan
                    </span>
                @endif
            </div>
        </section>

        {{-- Device silence warning --}}
        @if ($stale)
            <div role="status" class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <svg class="size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5.75a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5.75Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                </svg>
                <p>
                    <span class="font-semibold">Perangkat tidak mengirim data.</span>
                    {{ $secondsAgo === null ? 'Belum ada data masuk untuk bayi ini.' : 'Data terakhir '.$secondsAgo.' detik lalu.' }}
                </p>
            </div>
        @endif

        {{-- Digital readings --}}
        @php
            $numbers = [
                ['label' => 'Suhu inkubator', 'value' => $reading?->incubator_temperature, 'unit' => '°C'],
                ['label' => 'Suhu kulit', 'value' => $reading?->skin_temperature, 'unit' => '°C'],
                ['label' => 'Kelembapan', 'value' => $reading?->humidity, 'unit' => '%', 'digits' => 0],
                ['label' => 'Setpoint', 'value' => $setpoint, 'unit' => '°C'],
            ];
        @endphp

        <section class="grid grid-cols-2 gap-4 lg:grid-cols-4" aria-label="Data sensor">
            @foreach ($numbers as $number)
                @php
                    $value = $number['value'];
                    $digits = $number['digits'] ?? 1;
                    $isHumidity = $number['label'] === 'Kelembapan';
                    $alarm = $isHumidity && $reading && ! $humidityOk;
                @endphp

                <div class="rounded-2xl border bg-white px-5 py-5
                            {{ $alarm ? 'border-amber-400 ring-1 ring-amber-400/40' : 'border-slate-200' }}">
                    <p class="text-sm font-medium text-slate-500">{{ $number['label'] }}</p>

                    <p class="reading-value mt-3 flex items-baseline gap-1 tabular-nums
                              {{ $stale ? 'text-slate-300' : 'text-slate-900' }}">
                        <span class="text-5xl font-semibold tracking-tight sm:text-6xl">
                            {{ $value === null ? '—' : number_format($value, $digits, ',', '.') }}
                        </span>
                        <span class="text-lg font-medium {{ $stale ? 'text-slate-300' : 'text-slate-500' }}">{{ $number['unit'] }}</span>
                    </p>

                    @if ($isHumidity)
                        @if (! $reading)
                            <p class="mt-2 text-xs text-slate-400">Menunggu data</p>
                        @elseif ($humidityOk)
                            <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                </svg>
                                Dalam target 50–60%
                            </p>
                        @else
                            <p class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-amber-800">
                                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5.75a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5.75Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                </svg>
                                Di luar target 50–60%
                            </p>
                        @endif
                    @endif
                </div>
            @endforeach
        </section>

        {{-- Heaters and buzzer --}}
        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white px-5 py-5">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="text-sm font-medium text-slate-500">Pemanas</h2>
                    <p class="text-sm font-semibold tabular-nums {{ $stale ? 'text-slate-400' : 'text-slate-900' }}">
                        {{ $reading?->heaters ?? 0 }} dari 4 menyala
                    </p>
                </div>

                <ol class="mt-4 grid grid-cols-4 gap-2">
                    @for ($i = 1; $i <= 4; $i++)
                        @php $on = $reading !== null && $i <= $reading->heaters; @endphp
                        <li class="rounded-lg border px-2 py-3 text-center
                                   {{ $on ? 'border-orange-300 bg-orange-50' : 'border-slate-200 bg-slate-50' }}">
                            <span class="block text-xs font-medium text-slate-500">Pemanas {{ $i }}</span>
                            <span class="mt-1 block text-sm font-semibold {{ $on ? 'text-orange-800' : 'text-slate-400' }}">
                                {{ $on ? 'Nyala' : 'Mati' }}
                            </span>
                        </li>
                    @endfor
                </ol>
            </div>

            <div aria-live="polite" class="rounded-2xl border px-5 py-5
                        {{ $reading?->buzzer ? 'border-red-300 bg-red-50' : 'border-slate-200 bg-white' }}">
                <h2 class="text-sm font-medium text-slate-500">Alarm buzzer</h2>

                @if ($reading?->buzzer)
                    <p class="mt-3 flex items-center gap-2 text-2xl font-semibold text-red-800">
                        <svg class="size-6 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 2a6 6 0 0 0-6 6c0 1.887-.454 3.665-1.257 5.234a.75.75 0 0 0 .515 1.076 32.91 32.91 0 0 0 3.256.508 3.5 3.5 0 0 0 6.972 0 32.903 32.903 0 0 0 3.256-.508.75.75 0 0 0 .515-1.076A11.448 11.448 0 0 1 16 8a6 6 0 0 0-6-6Z" />
                        </svg>
                        Buzzer aktif
                    </p>
                    <p class="mt-1 text-sm text-red-700">Suhu inkubator jauh dari setpoint. Periksa bayi dan perangkat.</p>
                @else
                    <p class="mt-3 flex items-center gap-2 text-2xl font-semibold text-slate-400">
                        <svg class="size-6 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M13.5 4.938a7 7 0 1 1-9.006 1.737c.202-.257.59-.227.764.05a4.5 4.5 0 1 0 5.98-1.146.75.75 0 0 1-.272-1.024c.24-.4.702-.55 1.134-.348a7.001 7.001 0 0 1 1.4.731ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Z" clip-rule="evenodd" />
                        </svg>
                        Tidak aktif
                    </p>
                    <p class="mt-1 text-sm text-slate-500">Suhu inkubator dalam batas aman.</p>
                @endif
            </div>
        </section>

        {{-- Last update --}}
        <p class="text-sm text-slate-500">
            @if ($secondsAgo === null)
                Belum ada data masuk.
            @else
                Data terakhir <span class="font-medium tabular-nums text-slate-700">{{ $secondsAgo }} detik lalu</span>.
            @endif
        </p>
    @endif
</div>
