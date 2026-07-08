<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title>Akses Tidak Diizinkan</title>
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f6f8fd, #f1f5f9, #e2e8f0);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --text-subtle: #94a3b8;
            
            --card-bg: rgba(255, 255, 255, 0.95);
            --card-border: rgba(0, 0, 0, 0.05);
            --card-shadow: 0 24px 64px rgba(0, 0, 0, 0.06);
            
            --icon-bg: rgba(239, 68, 68, 0.1);
            --icon-border: rgba(239, 68, 68, 0.2);
            --icon-stroke: #ef4444;
            
            --divider: rgba(0, 0, 0, 0.08);
            
            --step-bg: rgba(124, 58, 237, 0.1);
            --step-border: rgba(124, 58, 237, 0.2);
            --step-color: #7c3aed;
            
            --badge-bg: rgba(239, 68, 68, 0.1);
            --badge-border: rgba(239, 68, 68, 0.2);
            --badge-color: #ef4444;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-gradient: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
                --text-main: #ffffff;
                --text-muted: rgba(255, 255, 255, 0.7);
                --text-subtle: rgba(255, 255, 255, 0.4);
                
                --card-bg: rgba(255, 255, 255, 0.07);
                --card-border: rgba(255, 255, 255, 0.12);
                --card-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
                
                --icon-bg: rgba(239, 68, 68, 0.15);
                --icon-border: rgba(239, 68, 68, 0.4);
                --icon-stroke: #f87171;
                
                --divider: rgba(255, 255, 255, 0.1);
                
                --step-bg: rgba(124, 58, 237, 0.3);
                --step-border: rgba(124, 58, 237, 0.5);
                --step-color: #a78bfa;
                
                --badge-bg: rgba(239, 68, 68, 0.15);
                --badge-border: rgba(239, 68, 68, 0.3);
                --badge-color: #f87171;
            }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-gradient);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-main);
            padding: 20px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 48px 40px;
            max-width: 440px;
            width: 100%;
            text-align: center;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: var(--card-shadow);
        }

        .icon {
            width: 72px;
            height: 72px;
            background: var(--icon-bg);
            border: 2px solid var(--icon-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon svg {
            width: 36px;
            height: 36px;
            stroke: var(--icon-stroke);
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-main);
        }

        p {
            font-size: 0.95rem;
            line-height: 1.7;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .divider {
            border: none;
            border-top: 1px solid var(--divider);
            margin: 28px 0;
        }

        .steps-title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-subtle);
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
            background: var(--step-bg);
            border: 1px solid var(--step-border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--step-color);
            margin-top: 1px;
        }

        .step-text {
            font-size: 0.875rem;
            color: var(--text-muted);
            line-height: 1.5;
        }
        
        .step-text strong {
            color: var(--text-main);
        }

        .badge {
            display: inline-block;
            background: var(--badge-bg);
            border: 1px solid var(--badge-border);
            color: var(--badge-color);
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
                <div class="step-text">Sambungkan perangkatmu ke jaringan <strong>Wi-Fi Hotspot</strong></div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text">Buka browser dan akses sembarang website — halaman login akan <strong>muncul otomatis</strong></div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text">Pilih metode login dan nikmati akses internet</div>
            </div>
        </div>
    </div>
</body>
</html>
