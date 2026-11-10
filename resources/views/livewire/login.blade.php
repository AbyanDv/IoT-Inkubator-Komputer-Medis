<div class="w-full max-w-sm">
    <h1 class="text-lg font-semibold tracking-tight">Inkubator Bayi</h1>
    <p class="mt-1 text-sm text-slate-500">Masuk untuk memantau inkubator.</p>

    <form wire:submit="login" class="mt-8 space-y-5">
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" type="email" wire:model="email" autocomplete="username" autofocus required
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                   class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                          focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
            @error('email')
                <p id="email-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium">Kata sandi</label>
            <input id="password" type="password" wire:model="password" autocomplete="current-password" required
                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                   class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm
                          focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
            @error('password')
                <p id="password-error" class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition
                       duration-150 hover:bg-slate-800 active:scale-[0.99]
                       focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900
                       disabled:opacity-60" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Masuk</span>
            <span wire:loading wire:target="login">Memeriksa…</span>
        </button>
    </form>
</div>
