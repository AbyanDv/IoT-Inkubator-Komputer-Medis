<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Incubator</title>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center p-6">
        <p class="text-sm font-semibold uppercase tracking-widest text-teal-700">Neonatal monitoring</p>
        <h1 class="mt-2 text-3xl font-bold">Log in</h1>
        <p class="mt-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900">Simulation prototype only. Not for clinical use or patient care.</p>

        @if ($errors->any())
            <div role="alert" class="mt-5 rounded-lg bg-rose-100 p-3 text-rose-800">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="/login" class="mt-6 space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @csrf
            <label class="block text-sm font-semibold" for="email">Email</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                   class="mt-1 block w-full rounded-lg border-slate-300" value="{{ is_scalar($email = old('email')) ? $email : '' }}">
            <label class="block text-sm font-semibold" for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-lg border-slate-300">
            <button type="submit" class="w-full rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white">Log in</button>
        </form>
    </main>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</body>
</html>
