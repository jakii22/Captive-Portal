<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Akses Tidak Diizinkan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #fff;
            padding: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 48px 40px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(16px);
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
        }

        .icon {
            width: 72px;
            height: 72px;
            background: rgba(239, 68, 68, 0.15);
            border: 2px solid rgba(239, 68, 68, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon svg {
            width: 36px;
            height: 36px;
            stroke: #f87171;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #fff;
        }

        p {
            font-size: 0.95rem;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.65);
            margin-bottom: 8px;
        }

        .divider {
            border: none;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin: 28px 0;
        }

        .steps-title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 16px;
        }

        .steps {
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .step-num {
            width: 26px;
            height: 26px;
            min-width: 26px;
            background: rgba(124, 58, 237, 0.3);
            border: 1px solid rgba(124, 58, 237, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #a78bfa;
            margin-top: 1px;
        }

        .step-text {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.5;
        }

        .badge {
            display: inline-block;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
        </div>

        <div class="badge">403 Akses Ditolak</div>

        <h1>Halaman Tidak Dapat Diakses</h1>
        <p>Halaman ini hanya tersedia saat perangkatmu sedang terhubung ke jaringan Wi-Fi hotspot dan perlu login.</p>

        <hr class="divider">

        <p class="steps-title">Cara Mengakses</p>
        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text">Sambungkan perangkatmu ke jaringan <strong style="color:#fff">Wi-Fi Hotspot</strong></div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">Buka browser dan akses sembarang website — halaman login akan <strong style="color:#fff">muncul otomatis</strong></div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">Pilih metode login dan nikmati akses internet</div>
            </div>
        </div>
    </div>
</body>
</html>
