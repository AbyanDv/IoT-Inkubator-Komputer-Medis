<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Masuk')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function login()
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            // Same message for unknown email and wrong password.
            $this->addError('email', 'Email atau kata sandi salah.');

            return;
        }

        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.login');
    }
}
