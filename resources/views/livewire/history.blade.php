<div class="space-y-6">
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h1 class="text-base font-semibold tracking-tight">Riwayat pembacaan</h1>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <div>
                <label for="baby" class="block text-sm font-medium">Bayi</label>
                <select id="baby" wire:model.live="babyId"
                        class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                               focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                    @foreach ($babies as $option)
                        <option value="{{ $option->id }}">{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="from" class="block text-sm font-medium">Dari</label>
                <input id="from" type="datetime-local" wire:model.live.debounce.500ms="from"
                       @error('from') aria-invalid="true" aria-describedby="from-error" @enderror
                       class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums
                              focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                @error('from')
                    <p id="from-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="to" class="block text-sm font-medium">Sampai</label>
                <input id="to" type="datetime-local" wire:model.live.debounce.500ms="to"
                       @error('to') aria-invalid="true" aria-describedby="to-error" @enderror
                       class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums
                              focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                @error('to')
                    <p id="to-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button type="button" wire:click="resetFilters"
                    class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition
                           duration-150 hover:bg-slate-100">
                Reset ke 24 jam terakhir
            </button>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white" wire:loading.class="opacity-60">
        @if (! $baby)
            <p class="px-5 py-12 text-center text-sm text-slate-500">
                Belum ada bayi. Tambahkan bayi terlebih dahulu di halaman Bayi.
            </p>
        @elseif ($readings->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">
                Tidak ada data pada rentang waktu ini. Coba lebarkan filter waktunya.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <caption class="sr-only">Riwayat pembacaan sensor {{ $baby->name }}</caption>
                    <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-semibold">Waktu</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Suhu kulit</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Suhu inkubator</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Kelembapan</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Setpoint</th>
                            <th scope="col" class="px-4 py-3 text-right font-semibold">Pemanas</th>
                            <th scope="col" class="px-4 py-3 font-semibold">Buzzer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 tabular-nums">
                        @foreach ($readings as $reading)
                            <tr class="transition-colors duration-150 hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-2.5 text-slate-600">
                                    {{ $reading->recorded_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-4 py-2.5 text-right">{{ number_format($reading->skin_temperature, 1, ',', '.') }} °C</td>
                                <td class="px-4 py-2.5 text-right">{{ number_format($reading->incubator_temperature, 1, ',', '.') }} °C</td>
                                <td class="px-4 py-2.5 text-right">{{ $reading->humidity }} %</td>
                                <td class="px-4 py-2.5 text-right">{{ $reading->setpoint === null ? '—' : number_format($reading->setpoint, 1, ',', '.').' °C' }}</td>
                                <td class="px-4 py-2.5 text-right">{{ $reading->heaters }} / 4</td>
                                <td class="px-4 py-2.5">
                                    @if ($reading->buzzer)
                                        <span class="font-semibold text-red-700">Aktif</span>
                                    @else
                                        <span class="text-slate-500">Mati</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">
                {{ $readings->links() }}
            </div>
        @endif
    </section>
</div>
