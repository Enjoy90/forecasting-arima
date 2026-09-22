<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'CV Pande Sejahtera') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Manrope', sans-serif; background: #0E1B33; color: #E7ECFB; }
        a { text-decoration: none; }
        .page { min-height: 100vh; background: radial-gradient(1200px 700px at 85% -10%, #24406E 0%, transparent 60%), linear-gradient(180deg, #0E1B33 0%, #101F3D 100%); }
        nav { display: flex; align-items: center; justify-content: space-between; padding: 32px 72px; }
        .brand { display: flex; align-items: center; gap: 12px; color: inherit; }
        .mark { width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #5B8DEF, #7BD1F0); display: flex; align-items: center; justify-content: center; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 14px; color: #0E1B33; }
        .brand-name { font-family: 'Space Grotesk', sans-serif; font-weight: 600; font-size: 16px; }
        .nav-links { display: flex; align-items: center; gap: 36px; }
        .nav-link { color: #9FB0D6; font-size: 14px; }
        .nav-link:hover { color: #7BD1F0; }
        .btn-solid { padding: 12px 22px; border-radius: 8px; background: linear-gradient(135deg, #5B8DEF, #7BD1F0); color: #0E1B33; font-weight: 700; font-size: 14px; }
        .btn-solid-lg { padding: 16px 30px; border-radius: 10px; background: linear-gradient(135deg, #5B8DEF, #7BD1F0); color: #0E1B33; font-weight: 700; font-size: 15px; }
        .btn-ghost { padding: 16px 26px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.16); color: #E7ECFB; font-weight: 600; font-size: 15px; }
        .btn-ghost:hover { border-color: rgba(255,255,255,0.4); }
        .hero-wrap { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 48px; align-items: center; max-width: 1300px; margin: 0 auto; padding: 76px 72px 110px; }
        h1 { margin: 0 0 24px; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 48px; line-height: 1.15; letter-spacing: -0.01em; }
        .lead { margin: 0 0 36px; font-size: 17px; line-height: 1.65; color: #9FB0D6; max-width: 480px; }
        .cta-row { display: flex; gap: 14px; margin-bottom: 48px; }
        .mini-stats { display: flex; gap: 32px; }
        .mini-stat-num { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 22px; color: #fff; }
        .mini-stat-label { font-size: 12px; color: #7488AD; margin-top: 2px; }
        .panel { background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; padding: 28px; }
        .panel-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; }
        .panel-title { font-family: 'Space Grotesk', sans-serif; font-weight: 600; font-size: 14px; }
        .panel-sub { font-size: 12px; color: #7488AD; margin-top: 2px; }
        .badge-good { font-size: 11px; padding: 5px 10px; border-radius: 999px; background: rgba(94,222,170,0.15); color: #5EDEAA; font-weight: 700; }
        .chart-box { height: 190px; margin-bottom: 18px; }
        .panel-foot { display: flex; justify-content: space-between; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.08); }
        .pf-num { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 17px; }
        .pf-label { font-size: 11px; color: #7488AD; margin-top: 3px; }
        .features { max-width: 1300px; margin: 0 auto; padding: 0 72px 100px; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        .card { padding: 30px; border-radius: 16px; background: rgba(255,255,255,0.035); border: 1px solid rgba(255,255,255,0.09); }
        .card-icon { width: 40px; height: 40px; border-radius: 10px; background: rgba(123,209,240,0.12); display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .card h3 { margin: 0 0 10px; font-family: 'Space Grotesk', sans-serif; font-size: 17px; font-weight: 600; }
        .card p { margin: 0; font-size: 13.5px; line-height: 1.65; color: #9FB0D6; }
        footer { max-width: 1300px; margin: 0 auto; padding: 30px 72px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #7488AD; }
        @media (max-width: 980px) {
            .hero-wrap { grid-template-columns: 1fr; padding: 60px 24px; }
            nav, .features, footer { padding-left: 24px; padding-right: 24px; }
            h1 { font-size: 34px; }
            .features { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page">
    <nav>
        <a href="/" class="brand">
            <div class="mark">PS</div>
            <div class="brand-name">Pande Sejahtera</div>
        </a>
        <div class="nav-links">
            <a href="#alur" class="nav-link">Alur Sistem</a>
            <a href="#fitur" class="nav-link">Fitur</a>
            @auth
                <a href="{{ url('/dashboard') }}" class="btn-solid">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="btn-solid">Masuk ke Sistem</a>
            @endauth
        </div>
    </nav>

    <div class="hero-wrap">
        <div>
            <h1>Sistem Peramalan Penjualan dan Perencanaan Stok</h1>
            <p class="lead">Dipakai CV Pande Sejahtera untuk meramalkan penjualan sekop tiap bulan, lalu menghitung rencana produksi dan kebutuhan bahan baku dari hasil ramalan itu.</p>
            <div class="cta-row">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn-solid-lg">Buka Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn-solid-lg">Masuk ke Sistem</a>
                @endauth
                <a href="#alur" class="btn-ghost">Lihat Alur Kerja</a>
            </div>
            <div class="mini-stats">
                <div><div class="mini-stat-num">36 bulan</div><div class="mini-stat-label">data penjualan historis</div></div>
                <div><div class="mini-stat-num">5 tahap</div><div class="mini-stat-label">produksi yang terlacak</div></div>
                <div><div class="mini-stat-num">4 peran</div><div class="mini-stat-label">pengguna berbeda</div></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <div>
                    <div class="panel-title">Contoh hasil ramalan</div>
                    <div class="panel-sub">Model ARIMA, 6 bulan ke depan</div>
                </div>
                <div class="badge-good">MAPE 6,4%</div>
            </div>
            <svg class="chart-box" viewBox="0 0 460 190" width="100%" height="190" preserveAspectRatio="none">
                <line x1="0" y1="40" x2="460" y2="40" stroke="rgba(255,255,255,0.06)" stroke-width="1"></line>
                <line x1="0" y1="90" x2="460" y2="90" stroke="rgba(255,255,255,0.06)" stroke-width="1"></line>
                <line x1="0" y1="140" x2="460" y2="140" stroke="rgba(255,255,255,0.06)" stroke-width="1"></line>
                <polyline points="0,120 60,110 120,95 180,100 240,80 300,70" fill="none" stroke="#5B8DEF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
                <polyline points="300,70 340,60 380,55 420,42 460,35" fill="none" stroke="#7BD1F0" stroke-width="3" stroke-dasharray="2 8" stroke-linecap="round" stroke-linejoin="round"></polyline>
                <path d="M300,70 340,50 380,42 420,25 460,15 460,55 420,62 380,72 340,80 300,90 Z" fill="rgba(123,209,240,0.12)" stroke="none"></path>
                <circle cx="300" cy="70" r="4.5" fill="#0E1B33" stroke="#5B8DEF" stroke-width="2"></circle>
                <circle cx="460" cy="35" r="4.5" fill="#0E1B33" stroke="#7BD1F0" stroke-width="2"></circle>
            </svg>
            <div class="panel-foot">
                <div><div class="pf-num">1.480</div><div class="pf-label">unit per bulan</div></div>
                <div><div class="pf-num">&#177; 210</div><div class="pf-label">interval kepercayaan</div></div>
                <div><div class="pf-num">Aman</div><div class="pf-label">status stok</div></div>
            </div>
        </div>
    </div>

    <div id="alur"></div>
    <div id="fitur" class="features">
        <div class="card">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7BD1F0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M7 15l4-6 4 3 5-8"></path></svg>
            </div>
            <h3>Peramalan penjualan</h3>
            <p>Data penjualan bulanan diolah dengan metode Box-Jenkins (ARIMA) sampai jadi ramalan beberapa bulan ke depan, lengkap dengan bukti perhitungannya.</p>
        </div>
        <div class="card">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7BD1F0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect></svg>
            </div>
            <h3>Rencana produksi</h3>
            <p>Dari hasil ramalan, sistem menghitung target produksi, waktu tunggu, dan kebutuhan bahan baku sampai rekomendasi order ke supplier.</p>
        </div>
        <div class="card">
            <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7BD1F0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
            </div>
            <h3>Simulasi sebelum diterapkan</h3>
            <p>Rencana stok yang dihasilkan sistem diuji dulu terhadap 12 bulan data lama, dibandingkan dengan kebijakan yang selama ini dipakai perusahaan.</p>
        </div>
    </div>

    <footer>
        <div>&copy; {{ date('Y') }} CV Pande Sejahtera</div>
        <div>Sistem Peramalan Penjualan dan Perencanaan Stok</div>
    </footer>
</div>
</body>
</html>
