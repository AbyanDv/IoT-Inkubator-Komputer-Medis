<div class="grid gap-6 lg:grid-cols-[22rem_1fr] lg:items-start">
    @if ($status)
        <div role="status" class="lg:col-span-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ $status }}
        </div>
    @endif

    {{-- Form --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h1 class="text-base font-semibold tracking-tight">
            {{ $editingId ? 'Ubah data bayi' : 'Tambah bayi' }}
        </h1>

        <form wire:submit="save" class="mt-5 space-y-4">
            <div>
                <label for="name" class="block text-sm font-medium">Nama bayi</label>
                <input id="name" type="text" wire:model="name" maxlength="100"
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                       class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                              focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                @error('name')
                    <p id="name-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="weight_grams" class="block text-sm font-medium">Berat (gram)</label>
                <input id="weight_grams" type="number" inputmode="numeric" wire:model="weight_grams"
                       min="300" max="10000" step="1"
                       @error('weight_grams') aria-invalid="true" aria-describedby="weight-error" @enderror
                       class="mt-1.5 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums
                              focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
                @error('weight_grams')
                    <p id="weight-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="premature" class="block text-sm font-medium">Prematur</label>
                <button id="premature" type="button" role="switch" aria-checked="{{ $premature ? 'true' : 'false' }}"
                        wire:click="$toggle('premature')"
                        class="mt-1.5 flex w-full items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm
                               focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">
                    <span>{{ $premature ? 'Ya' : 'Tidak' }}</span>
                    <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-150
                                 {{ $premature ? 'bg-slate-900' : 'bg-slate-300' }}">
                        <span class="inline-block size-4 rounded-full bg-white transition-transform duration-150
                                     {{ $premature ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                    </span>
                </button>
                @error('premature')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit"
                        class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition
                               duration-150 hover:bg-slate-800 active:scale-[0.99] disabled:opacity-60"
                        wire:loading.attr="disabled">
                    {{ $editingId ? 'Simpan perubahan' : 'Tambah bayi' }}
                </button>

                @if ($editingId)
                    <button type="button" wire:click="cancel"
                            class="rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition
                                   duration-150 hover:bg-slate-100 hover:text-slate-900">
                        Batal
                    </button>
                @endif
            </div>
        </form>
    </section>

    {{-- List --}}
    <section class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-5 py-4 text-base font-semibold tracking-tight">Daftar bayi</h2>

        @if ($babies->isEmpty())
            <p class="px-5 py-12 text-center text-sm text-slate-500">
                Belum ada bayi. Tambahkan lewat form di samping.
            </p>
        @else
            <ul role="list" class="divide-y divide-slate-200">
                @foreach ($babies as $baby)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-4
                               {{ $baby->is_active ? 'bg-emerald-50/60' : '' }}">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                                {{ $baby->name }}

                                @if ($baby->is_active)
                                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white">
                                        Di inkubator
                                    </span>
                                @endif

                                @if ($baby->premature)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">
                                        Prematur
                                    </span>
                                @endif
                            </p>
                            <p class="mt-0.5 text-sm tabular-nums text-slate-500">
                                {{ number_format($baby->weight_grams, 0, ',', '.') }} gram
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            @if ($baby->is_active)
                                <button type="button" wire:click="deactivate({{ $baby->id }})"
                                        wire:confirm="Akhiri sesi inkubator untuk {{ $baby->name }}?"
                                        class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 transition
                                               duration-150 hover:bg-slate-200">
                                    Akhiri sesi
                                </button>
                            @else
                                <button type="button" wire:click="activate({{ $baby->id }})"
                                        wire:confirm="Aktifkan {{ $baby->name }} di inkubator? Bayi lain akan dinonaktifkan."
                                        class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white
                                               transition duration-150 hover:bg-slate-800">
                                    Aktifkan
                                </button>
                            @endif

                            <button type="button" wire:click="edit({{ $baby->id }})"
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 transition
                                           duration-150 hover:bg-slate-200">
                                Ubah
                            </button>

                            <button type="button" wire:click="delete({{ $baby->id }})"
                                    wire:confirm="Hapus {{ $baby->name }} beserta seluruh riwayat datanya? Tindakan ini tidak bisa dibatalkan."
                                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-red-700 transition
                                           duration-150 hover:bg-red-50">
                                Hapus
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
