<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in</title>
</head>
<body>
    <main>
        <h1>Log in</h1>
        @if ($errors->any())
            <div role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        <form method="POST" action="/login">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="username">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password">
            <button type="submit">Log in</button>
        </form>
    </main>
</body>
</html>
