<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Dicek SETELAH validasi: percobaan yang gagal validasi tidak membuat
        // apa pun, jadi tidak adil kalau ikut memakan jatah. Salah ketik email
        // beberapa kali tidak boleh mengunci orang dari mendaftar.
        $this->pastikanTidakDibatasi();

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        RateLimiter::hit($this->kunciLaju(), 60 * 60);

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    /**
     * Lima akun per jam per alamat IP.
     *
     * Login dibatasi karena menebak sandi itu murah. Pendaftaran dibatasi
     * karena justru lebih mahal: tiap percobaan yang berhasil menulis baris
     * baru di database dan memicu satu email verifikasi.
     *
     * Angkanya sengaja longgar. Satu rumah atau satu kantor di balik satu IP
     * publik harus tetap bisa membuat beberapa akun di hari yang sama.
     */
    protected function pastikanTidakDibatasi(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciLaju(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $detik = RateLimiter::availableIn($this->kunciLaju());

        throw ValidationException::withMessages([
            'email' => 'Terlalu banyak pendaftaran dari jaringan ini. Coba lagi dalam '
                .$detik.' detik.',
        ]);
    }

    protected function kunciLaju(): string
    {
        return 'daftar|'.request()->ip();
    }
}; ?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-ink-muted hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-bg focus:ring-accent" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>

        {{-- Tautannya ada di sini, bukan cuma di footer halaman depan: inilah
             titik orang menyerahkan datanya, jadi di sinilah ia berhak tahu
             apa yang disimpan. --}}
        <p class="mt-6 text-xs text-ink-faint">
            Dengan mendaftar, kamu menyetujui
            <a href="{{ route('privasi') }}" class="underline underline-offset-2 hover:text-ink-muted transition-colors">kebijakan privasi</a>.
            Singkatnya: datanya cuma dipakai menampilkan jurnalmu sendiri, dan bisa kamu hapus seluruhnya kapan saja.
        </p>
    </form>
</div>
