<?php
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['username'] = $username;
        header('Location: ./admin/index.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Dapoer Funraise</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #B64B62;
            --secondary: #5A46A2;
            --accent: #F9CC22;
            --purple-light: #DFBEE0;
            --purple-mid: #9180BB;
            --cream: #FFF5EE;
            --dark: #2a1f3d;
            --font-main: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --shadow-md: 0 12px 30px rgba(90, 70, 162, 0.15);
            --shadow-lg: 0 24px 50px rgba(90, 70, 162, 0.2);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: var(--font-main);
            color: #333;
            background: linear-gradient(135deg, var(--cream) 0%, #fef8f4 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* === HEADER === */
        .app-header {
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            color: white;
            padding: 1.2rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .app-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            z-index: 1;
        }
        .app-header > * { position: relative; z-index: 2; }

        .app-header {
            background: linear-gradient(90deg, var(--secondary), var(--primary));
            color: white;
            padding: 1.2rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            box-shadow: 0 4px 20px rgba(90, 70, 162, 0.25);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .app-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            z-index: 1;
        }
        .app-header > * { position: relative; z-index: 2; }
        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        .logo:hover { transform: scale(1.02); }

        .logo-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            backdrop-filter: blur(4px);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            /* gap: 2px; */
        }
        .logo-main {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .logo-sub {
            font-size: 0.95rem;
            font-weight: 500;
            opacity: 0.9;
            color: rgba(255,255,255,0.95);
            margin-top: -2px;
        }

        /* === MAIN CONTENT === */
        main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        .login-container {
            width: 100%;
            max-width: 480px;
            background: white;
            padding: 2.8rem;
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            text-align: center;
        }
        .brand-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 8px 20px rgba(90, 70, 162, 0.25);
        }
        h2 {
            font-size: 2rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            font-weight: 700;
        }

        /* === FORM === */
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 1.05rem;
            color: var(--dark);
        }
        .form-group input {
            width: 100%;
            padding: 14px 18px;
            border-radius: 12px;
            border: 2px solid var(--purple-light);
            background: var(--cream);
            font-size: 1.05rem;
            transition: var(--transition);
            color: #333;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(90, 70, 162, 0.2);
        }

        /* === BUTTONS === */
        .login-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 1.2rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 32px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            border: none;
            outline: none;
            box-shadow: var(--shadow-md);
            white-space: nowrap;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #d05876);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 16px 40px rgba(182, 75, 98, 0.4);
        }
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary), #7058c4);
            color: white;
        }
        .btn-secondary:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 16px 40px rgba(90, 70, 162, 0.4);
        }

        /* === ALERTS === */
        .alert {
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 1.8rem;
            font-weight: 600;
            text-align: center;
            font-size: 1.1rem;
        }
        .alert-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* FOOTER */
        footer {
            background: linear-gradient(135deg, var(--secondary), var(--dark));
            color: rgba(255,255,255,0.85);
            text-align: center;
            padding: 24px;
            font-size: 1.05rem;
            font-weight: 500;
        }

        /* SCREEN READERS */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* === MOBILE RESPONSIVE === */
        @media (max-width: 768px) {
            .app-header {
                padding: 1rem;
                flex-wrap: wrap;
            }
            .logo-main { font-size: 1.4rem; }
            .logo-sub { font-size: 0.85rem; }
            .login-container {
                padding: 2rem 1.5rem;
            }
            .btn {
                padding: 12px 24px;
                font-size: 1rem;
            }
            .login-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="logo">
            <div class="logo-icon">
                <img src="assets/logo.png" alt="Logo Dapoer Funraise" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <div class="logo-text">
                <span class="logo-main">Dapoer Funraise</span>
                <span class="logo-sub">Cemilan rumahan yang bikin nagih!</span>
            </div>
        </div>
    </header>

    <main>
        <div class="login-container" role="main" aria-labelledby="login-title">
            <div class="brand-logo" aria-hidden="true">
                <img src="assets/logo.png" alt="Logo Dapoer Funraise" style="width: 100%; height: 100%; object-fit: contain;">
            </div>
            <h2 id="login-title">Masuk ke Dashboard</h2>

            <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" placeholder="Masukkan username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                </div>

                <div class="login-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 <strong>Dapoer Funraise</strong> — Mendukung Expo Campus MAN 2 Samarinda</p>
    </footer>
</body>
</html>