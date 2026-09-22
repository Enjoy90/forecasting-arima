<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CV Pande Sejahtera') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600;700&family=Manrope:wght@400;500;600;700&display=swap" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body { font-family: 'Manrope', sans-serif; }
            .auth-page {
                min-height: 100vh;
                background: radial-gradient(1200px 700px at 85% -10%, #24406E 0%, transparent 60%), linear-gradient(180deg, #0E1B33 0%, #101F3D 100%);
            }
            .auth-brand-name { font-family: 'Space Grotesk', sans-serif; }

            .auth-card {
                background: rgba(255, 255, 255, 0.045);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 20px;
                box-shadow: 0 30px 80px -20px rgba(0, 0, 0, 0.5);
            }

            /* Scoped ke kartu auth saja, tidak menyentuh komponen di halaman lain */
            .auth-card label {
                font-weight: 600 !important;
                font-size: 13px !important;
                color: #C9D2E8 !important;
                letter-spacing: 0.01em;
            }
            .auth-card input[type="email"],
            .auth-card input[type="password"],
            .auth-card input[type="text"] {
                background: rgba(255, 255, 255, 0.06) !important;
                border: 1px solid rgba(255, 255, 255, 0.14) !important;
                border-radius: 10px !important;
                padding: 11px 14px !important;
                color: #E7ECFB !important;
                font-family: 'Manrope', sans-serif;
                box-shadow: none !important;
            }
            .auth-card input[type="email"]::placeholder,
            .auth-card input[type="password"]::placeholder,
            .auth-card input[type="text"]::placeholder {
                color: #5C6B8A;
            }
            .auth-card input[type="email"]:focus,
            .auth-card input[type="password"]:focus,
            .auth-card input[type="text"]:focus {
                border-color: #7BD1F0 !important;
                box-shadow: 0 0 0 3px rgba(123, 209, 240, 0.18) !important;
            }
            .auth-card input[type="checkbox"] { accent-color: #7BD1F0; }
            .auth-card .text-gray-600 { color: #9FB0D6 !important; }
            .auth-card .text-green-600 { color: #5EDEAA !important; }
            .auth-card .text-red-600 { color: #FF9B9B !important; }
            .auth-card a { color: #7BD1F0; }
            .auth-card a:hover { color: #A6E4FA; }
            .auth-card button[type="submit"] {
                background: linear-gradient(135deg, #5B8DEF, #7BD1F0) !important;
                color: #0E1B33 !important;
                border: none !important;
                border-radius: 10px !important;
                font-family: 'Manrope', sans-serif !important;
                font-weight: 700 !important;
                font-size: 14px !important;
                text-transform: none !important;
                letter-spacing: normal !important;
                padding: 11px 24px !important;
            }
            .auth-card button[type="submit"]:hover { filter: brightness(1.08); }
        </style>
    </head>
    <body class="antialiased">
        <div class="auth-page flex flex-col items-center justify-center min-h-screen px-6 py-12">
            <a href="/" class="flex items-center gap-3 mb-8">
                <span class="w-10 h-10 rounded-[10px] flex items-center justify-center font-bold text-[15px]" style="background: linear-gradient(135deg, #5B8DEF, #7BD1F0); color: #0E1B33;">PS</span>
                <span class="auth-brand-name text-white font-semibold text-lg">Pande Sejahtera</span>
            </a>

            <div class="auth-card w-full sm:max-w-md px-8 py-9">
                @php
                    $judul = match (true) {
                        request()->routeIs('login') => 'Masuk ke Sistem',
                        request()->routeIs('register') => 'Buat Akun',
                        request()->routeIs('password.request') => 'Lupa Kata Sandi',
                        request()->routeIs('password.reset') => 'Atur Ulang Kata Sandi',
                        request()->routeIs('password.confirm') => 'Konfirmasi Kata Sandi',
                        request()->routeIs('verification.notice') => 'Verifikasi Email',
                        default => null,
                    };
                    $subjudul = match (true) {
                        request()->routeIs('login') => 'Masuk pakai akun yang terdaftar di perusahaan.',
                        request()->routeIs('register') => 'Lengkapi data untuk membuat akun baru.',
                        request()->routeIs('password.request') => 'Masukkan email untuk menerima tautan reset kata sandi.',
                        request()->routeIs('password.reset') => 'Buat kata sandi baru untuk akun kamu.',
                        request()->routeIs('password.confirm') => 'Konfirmasi ulang kata sandi sebelum lanjut.',
                        request()->routeIs('verification.notice') => 'Cek email kamu untuk tautan verifikasi.',
                        default => null,
                    };
                @endphp

                @if ($judul)
                    <h1 style="font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700; color: #FFFFFF; margin: 0 0 4px;">{{ $judul }}</h1>
                @endif
                @if ($subjudul)
                    <p style="font-size: 13px; color: #7488AD; margin: 0 0 26px;">{{ $subjudul }}</p>
                @endif

                {{ $slot }}
            </div>
        </div>
    </body>
</html>
