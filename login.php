<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

initSession();

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email et mot de passe requis';
    } elseif (login($email, $password)) {
        updateLastLogin();
        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $error = 'Email ou mot de passe incorrect';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --brand: #1a56db;
    --brand-dark: #1342b3;
    --brand-light: #e8f0ff;
    --danger: #dc2626;
    --bg: #f8fafc;
    --card: #ffffff;
    --border: #e2e8f0;
    --text: #1e293b;
    --text-muted: #64748b;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, var(--brand) 0%, var(--brand-dark) 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text);
}

.login-container {
    width: 100%;
    max-width: 420px;
    padding: 20px;
}

.login-box {
    background: var(--card);
    border-radius: 12px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    padding: 40px 32px;
}

.login-header {
    text-align: center;
    margin-bottom: 32px;
}

.logo {
    width: 60px;
    height: 60px;
    background: var(--brand-light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    font-size: 28px;
    font-weight: 700;
    color: var(--brand);
}

.login-header h1 {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 8px;
}

.login-header p {
    font-size: 14px;
    color: var(--text-muted);
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text);
}

input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
}

input[type="email"]:focus,
input[type="password"]:focus {
    outline: none;
    border-color: var(--brand);
    box-shadow: 0 0 0 3px rgba(26, 86, 219, 0.1);
}

.error {
    padding: 12px;
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    color: var(--danger);
    font-size: 13px;
    margin-bottom: 20px;
}

.btn-login {
    width: 100%;
    padding: 10px;
    background: var(--brand);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-login:hover {
    background: var(--brand-dark);
}

.btn-login:active {
    transform: scale(0.98);
}

.demo-creds {
    margin-top: 24px;
    padding: 16px;
    background: #f0fdf4;
    border: 1px solid #dcfce7;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.6;
}

.demo-creds strong {
    color: #059669;
    display: block;
    margin-bottom: 6px;
}

.demo-creds code {
    background: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: 'JetBrains Mono', monospace;
    color: var(--brand);
    font-weight: 500;
}
</style>
</head>
<body>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <div class="logo">📍</div>
            <h1><?= APP_NAME ?></h1>
            <p>Gestion de location de matériel</p>
        </div>

        <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= htmlspecialchars($email) ?>"
                    placeholder="votre@email.com"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-login">Se connecter</button>
        </form>

        <div class="demo-creds">
            <strong>👤 Identifiants de démonstration :</strong>
            Email: <code>admin@technolocation.tn</code><br>
            Mot de passe: <code>admin123</code>
        </div>
    </div>
</div>

</body>
</html>
