<?php
// Sessão (2h) + cookies seguros
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params([
  'lifetime' => 7200,
  'path'     => '/',
  'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

require 'db.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = "Preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Informe um e-mail válido.";
    } else {
        // Busca no admins por e-mail (case-insensitive)
        $stmt = $pdo->prepare("SELECT id, nome, email, senha FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($senha, $admin['senha'])) {
            $erro = "E-mail ou senha incorretos.";
        } else {
            session_regenerate_id(true);

            // Dados de sessão do admin
            $_SESSION['admin_id']    = (int)$admin['id'];
            $_SESSION['admin_nome']  = $admin['nome'] ?? 'Admin';
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin']       = true; // 👈 flag que identifica o admin

            header("Location: painel_admin.php");
            exit;
        }
    }
}

// Imagens dinâmicas
$jsonFile = __DIR__ . '/imagens_menu.json';
$imagens = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];

$banner1 = $imagens['banner1'] ?? 'banner.webp';
$banner2 = $imagens['banner2'] ?? 'banner2.png';
$logo    = $imagens['logo']    ?? 'logo.png';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login - Painel Administrativo</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/cores-dinamicas.css?v=<?= time() ?>">
  <style>
    :root {
      --bg-dark: #0d1117;
      --bg-panel: #161b22;
      --primary-gold: #fbce00;
      --text-light: #f0f6fc;
      --text-muted: #8b949e;
      --radius: 12px;
      --transition: 0.3s ease;
      --border-panel: #21262d;
      --shadow-gold: 0 0 20px rgba(251, 206, 0, 0.3);
      --error-color: #ef4444;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
      color: var(--text-light);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    /* Background Animation */
    body::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(251, 206, 0, 0.03) 0%, transparent 70%);
      animation: rotate 20s linear infinite;
      z-index: -1;
    }

    @keyframes rotate {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .login-container {
      background: var(--bg-panel);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      padding: 40px;
      width: 100%;
      max-width: 450px;
      box-shadow: var(--shadow-gold);
      position: relative;
      overflow: hidden;
      backdrop-filter: blur(10px);
    }

    .login-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, var(--primary-gold), transparent);
      animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
      0% { left: -100%; }
      100% { left: 100%; }
    }

    .logo-section {
      text-align: center;
      margin-bottom: 32px;
    }

    .logo-image {
      height: 60px;
      margin-bottom: 16px;
      filter: drop-shadow(0 0 10px rgba(251, 206, 0, 0.3));
    }

    .welcome-title {
      font-size: 28px;
      font-weight: 800;
      color: var(--primary-gold);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .welcome-subtitle {
      color: var(--text-muted);
      font-size: 16px;
      font-weight: 500;
    }

    .form-group {
      margin-bottom: 24px;
      position: relative;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 16px;
      color: var(--text-muted);
      font-size: 18px;
      z-index: 2;
      transition: var(--transition);
    }

    .form-input {
      width: 100%;
      padding: 16px 16px 16px 50px;
      background: var(--bg-dark);
      border: 1px solid var(--border-panel);
      border-radius: var(--radius);
      color: var(--text-light);
      font-size: 16px;
      font-weight: 500;
      transition: var(--transition);
    }

    .form-input:focus {
      outline: none;
      border-color: var(--primary-gold);
      box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
    }

    .form-input:focus + .input-icon {
      color: var(--primary-gold);
    }

    .form-input::placeholder {
      color: var(--text-muted);
      font-weight: 400;
    }

    .error-message {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid var(--error-color);
      color: var(--error-color);
      padding: 16px 20px;
      border-radius: var(--radius);
      margin-bottom: 24px;
      font-weight: 600;
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      animation: slideInDown 0.4s ease;
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .login-button {
      width: 100%;
      padding: 16px 24px;
      background: linear-gradient(135deg, var(--primary-gold), #f4c430);
      color: #000;
      font-weight: 700;
      font-size: 16px;
      border: none;
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 4px 16px rgba(251, 206, 0, 0.3);
      margin-bottom: 24px;
    }

    .login-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(251, 206, 0, 0.4);
    }

    .login-button:active {
      transform: translateY(0);
    }

    .footer-text {
      text-align: center;
      color: var(--text-muted);
      font-size: 14px;
      font-weight: 500;
      padding-top: 20px;
      border-top: 1px solid var(--border-panel);
    }

    .admin-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(251, 206, 0, 0.15);
      color: var(--primary-gold);
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 24px;
    }

    /* Loading State */
    .login-button.loading {
      pointer-events: none;
      opacity: 0.8;
    }

    .login-button.loading::after {
      content: '';
      width: 20px;
      height: 20px;
      border: 2px solid transparent;
      border-top: 2px solid #000;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-left: 8px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .login-container {
        padding: 32px 24px;
        margin: 10px;
      }

      .welcome-title {
        font-size: 24px;
      }

      .logo-image {
        height: 50px;
      }

      .form-input {
        padding: 14px 14px 14px 46px;
        font-size: 16px; /* Prevent zoom on iOS */
      }

      .input-icon {
        left: 14px;
        font-size: 16px;
      }
    }

    /* Dark mode enhancements */
    @media (prefers-color-scheme: dark) {
      .login-container {
        box-shadow: var(--shadow-gold), 0 0 40px rgba(0, 0, 0, 0.5);
      }
    }

    /* Focus visible for accessibility */
    .login-button:focus-visible {
      outline: 2px solid var(--primary-gold);
      outline-offset: 2px;
    }

    .form-input:focus-visible {
      outline: 2px solid var(--primary-gold);
      outline-offset: 2px;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="logo-section">
      <img src="images/<?= htmlspecialchars($logo) ?>?v=<?= time() ?>" alt="Logo" class="logo-image">
      
      <div class="admin-badge">
        <i class="fas fa-shield-alt"></i>
        Área Administrativa
      </div>
      
      <h1 class="welcome-title">
        <i class="fas fa-crown"></i>
        Bem-vindo de volta!
      </h1>
      <p class="welcome-subtitle">Conecte-se ao painel administrativo</p>
    </div>

    <form method="post" novalidate>
      <?php if (!empty($erro)): ?>
        <div class="error-message">
          <i class="fas fa-exclamation-triangle"></i>
          <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <div class="form-group">
        <div class="input-wrapper">
          <input 
            type="email" 
            name="email" 
            class="form-input"
            placeholder="seu@email.com" 
            required 
            autocomplete="username"
            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
          >
          <i class="fas fa-envelope input-icon"></i>
        </div>
      </div>

      <div class="form-group">
        <div class="input-wrapper">
          <input 
            type="password" 
            name="senha" 
            class="form-input"
            placeholder="Sua senha" 
            required 
            autocomplete="current-password"
          >
          <i class="fas fa-lock input-icon"></i>
        </div>
      </div>

      <button type="submit" class="login-button" id="loginBtn">
        <i class="fas fa-sign-in-alt"></i>
        Entrar no Painel
      </button>
    </form>

    <div class="footer-text">
      <i class="fas fa-info-circle"></i>
      Precisa de acesso? Fale com o administrador.
    </div>
  </div>

  <script>
    // Loading state no botão
    document.querySelector('form').addEventListener('submit', function() {
      const btn = document.getElementById('loginBtn');
      btn.classList.add('loading');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Entrando...';
    });

    // Auto-focus no primeiro campo vazio
    document.addEventListener('DOMContentLoaded', function() {
      const emailInput = document.querySelector('input[name="email"]');
      const senhaInput = document.querySelector('input[name="senha"]');
      
      if (!emailInput.value) {
        emailInput.focus();
      } else {
        senhaInput.focus();
      }
    });

    // Animação nos ícones dos inputs
    document.querySelectorAll('.form-input').forEach(input => {
      input.addEventListener('focus', function() {
        this.parentElement.querySelector('.input-icon').style.transform = 'scale(1.1)';
      });
      
      input.addEventListener('blur', function() {
        this.parentElement.querySelector('.input-icon').style.transform = 'scale(1)';
      });
    });
  </script>
</body>
</html>