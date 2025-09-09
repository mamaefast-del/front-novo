<?php
session_start();
require 'db.php';

if (!isset($_SESSION['usuario_id'])) {
  header('Location: index.php');
  exit;
}

$stmt = $pdo->prepare("SELECT nome, saldo FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

$dadosJson = file_exists('imagens_menu.json') ? json_decode(file_get_contents('imagens_menu.json'), true) : [];
$logo = isset($dadosJson['logo']) ? $dadosJson['logo'] : 'logo.png';

?>

<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Pacotes - Raspadinhas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* Reset e Base */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #0a0b0f;
      color: #ffffff;
      min-height: 100vh;
      line-height: 1.5;
      padding-bottom: 80px;
    }

    /* Header */
    .header {
      background: #111318;
      border-bottom: 1px solid #1a1d24;
      padding: 16px 20px;
      position: sticky;
      top: 0;
      z-index: 100;
      backdrop-filter: blur(20px);
    }

    .header-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo img {
      height: 40px;
      filter: brightness(1.1);
    }

    .saldo-depositar {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .saldo {
      background: linear-gradient(135deg, #fab201, #f4c430);
      color: #000;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 700;
      font-size: 14px;
      box-shadow: 0 2px 8px rgba(250, 178, 1, 0.3);
    }

    .btn-depositar {
      padding: 10px 16px;
      background: linear-gradient(135deg, #fab201, #f4c430);
      color: #000;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 14px;
      box-shadow: 0 2px 8px rgba(250, 178, 1, 0.3);
    }

    .btn-depositar:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(250, 178, 1, 0.4);
    }

    /* Banner */
    .banners {
      max-width: 1200px;
      margin: 24px auto;
      padding: 0 20px;
    }

    .banner {
      width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      transition: transform 0.3s ease;
    }

    .banner:hover {
      transform: scale(1.02);
    }

    /* Container */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .container h2 {
      color: #ffffff;
      font-size: 28px;
      font-weight: 800;
      margin: 40px 0 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .container h2 i {
      color: #fab201;
      font-size: 24px;
    }

    /* Lista de Raspadinhas */
    .raspadinha-lista {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 24px;
      margin-bottom: 60px;
    }

    .raspadinha-card {
      background: #111318;
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.3s ease;
      position: relative;
      border: 2px solid transparent;
      background-clip: padding-box;
    }

    .raspadinha-card::before {
      content: '';
      position: absolute;
      inset: 0;
      padding: 2px;
      background: linear-gradient(135deg, #fab201, #f4c430, #fab201);
      border-radius: 16px;
      mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      mask-composite: xor;
      -webkit-mask-composite: xor;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .raspadinha-card:hover::before {
      opacity: 1;
    }

    .raspadinha-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 32px rgba(250, 178, 1, 0.2);
    }

    .raspadinha-card a {
      text-decoration: none;
      color: inherit;
      display: block;
    }

    .raspadinha-card > a > div:first-child {
      position: relative;
      height: 200px;
      overflow: hidden;
    }

    .raspadinha-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .raspadinha-card:hover img {
      transform: scale(1.05);
    }

    .valor-label {
      position: absolute;
      top: 12px;
      right: 12px;
      background: rgba(0, 0, 0, 0.8);
      color: #fab201;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 800;
      border: 1px solid #fab201;
      backdrop-filter: blur(10px);
    }

    .raspadinha-info {
      padding: 20px;
      text-align: center;
    }

    .raspadinha-titulo {
      font-weight: 700;
      color: #ffffff;
      font-size: 16px;
      margin-bottom: 4px;
    }

    .raspadinha-nome {
      color: #8b949e;
      font-size: 12px;
    }

    /* Footer Info */
    .rodape-info {
      background: #111318;
      text-align: center;
      padding: 40px 20px;
      margin-top: 60px;
      border-top: 1px solid #1a1d24;
    }

    .rodape-info .logo img {
      height: 36px;
      margin-bottom: 16px;
      filter: brightness(1.1);
    }

    .rodape-info p {
      color: #8b949e;
      margin: 8px 0;
      font-size: 14px;
    }

    /* Bottom Navigation */
    .footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #111318;
      border-top: 1px solid #1a1d24;
      display: flex;
      justify-content: space-around;
      padding: 12px 0;
      z-index: 1000;
      backdrop-filter: blur(20px);
    }

    .footer a {
      color: #8b949e;
      text-decoration: none;
      text-align: center;
      padding: 8px 12px;
      border-radius: 8px;
      transition: all 0.2s ease;
      font-size: 12px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }

    .footer a:hover,
    .footer a.active {
      color: #fab201;
      background: rgba(250, 178, 1, 0.1);
    }

    .footer .deposito-btn {
      background: linear-gradient(135deg, #fab201, #f4c430);
      color: #000 !important;
      font-weight: 700;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(250, 178, 1, 0.3);
    }

    .footer .deposito-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(250, 178, 1, 0.4);
    }

    .footer i {
      font-size: 16px;
    }

    /* Animações de entrada */
    .raspadinha-card {
      animation: slideInUp 0.6s ease forwards;
      opacity: 0;
    }

    .raspadinha-card:nth-child(1) { animation-delay: 0.1s; }
    .raspadinha-card:nth-child(2) { animation-delay: 0.2s; }
    .raspadinha-card:nth-child(3) { animation-delay: 0.3s; }
    .raspadinha-card:nth-child(4) { animation-delay: 0.4s; }
    .raspadinha-card:nth-child(5) { animation-delay: 0.5s; }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Shimmer effect */
    .raspadinha-card::after {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #fab201, transparent);
      animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
      0% { left: -100%; }
      100% { left: 100%; }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .header-content {
        padding: 0 4px;
      }

      .saldo-depositar {
        gap: 8px;
      }

      .btn-depositar {
        padding: 8px 12px;
        font-size: 13px;
      }

      .container {
        padding: 0 16px;
      }

      .banners {
        padding: 0 16px;
      }

      .banner {
        height: 160px;
      }

      .raspadinha-lista {
        grid-template-columns: 1fr;
        gap: 16px;
      }

      .container h2 {
        font-size: 24px;
        margin: 32px 0 20px;
      }

      .raspadinha-card > a > div:first-child {
        height: 180px;
      }
    }

    /* Loading states */
    .raspadinha-card:hover .valor-label {
      background: rgba(250, 178, 1, 0.9);
      color: #000;
      transform: scale(1.05);
    }

    /* Pulse animation for hot items */
    .raspadinha-card:nth-child(odd)::after {
      animation-delay: 1s;
    }

    .raspadinha-card:nth-child(even)::after {
      animation-delay: 2s;
    }
  </style>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <div class="header-content">
      <div class="logo">
        <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
      </div>
      <div class="saldo-depositar">
        <span class="saldo">R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?></span>
        <button class="btn-depositar" onclick='window.location.href="deposito.php"'>
          <i class="fas fa-plus"></i> Recarregar
        </button>
      </div>
    </div>
  </div>

  <!-- Banner -->
  <div class="banners">
    <img src="images/bannermenu.webp" class="banner" alt="Banner Menu">
  </div>

  <!-- Container Principal -->
  <div class="container">
    <h2>
      <i class="fas fa-box"></i> Pacotes Premium
    </h2>

    <!-- Lista de Raspadinhas -->
    <div class="raspadinha-lista">
      <?php
      $imagensMenu = file_exists('imagens_menu.json') ? json_decode(file_get_contents('imagens_menu.json'), true) : [];
      $raspadinhas = [
        [
          "id" => 1,
          "imagem" => isset($imagensMenu['menu1']) ? "images/" . $imagensMenu['menu1'] . "?v=" . filemtime("images/" . $imagensMenu['menu1']) : "images/menu1.png",
          "valor" => "R$1,00",
          "premio" => "R$ 2.000,00"
        ],
        [
          "id" => 2,
          "imagem" => isset($imagensMenu['menu2']) ? "images/" . $imagensMenu['menu2'] . "?v=" . filemtime("images/" . $imagensMenu['menu2']) : "images/menu2.png",
          "valor" => "R$5,00",
          "premio" => "R$ 1.200,00"
        ],
        [
          "id" => 3,
          "imagem" => isset($imagensMenu['menu3']) ? "images/" . $imagensMenu['menu3'] . "?v=" . filemtime("images/" . $imagensMenu['menu3']) : "images/menu3.png",
          "valor" => "R$15,00",
          "premio" => "R$ 5.000,00"
        ],
        [
          "id" => 4,
          "imagem" => isset($imagensMenu['menu4']) ? "images/" . $imagensMenu['menu4'] . "?v=" . filemtime("images/" . $imagensMenu['menu4']) : "images/menu4.png",
          "valor" => "R$50,00",
          "premio" => "R$ 80.000,00"
        ],
        [
          "id" => 5,
          "imagem" => isset($imagensMenu['menu5']) ? "images/" . $imagensMenu['menu5'] . "?v=" . filemtime("images/" . $imagensMenu['menu3']) : "images/menu5.png",
          "valor" => "R$1,00",
          "premio" => "R$ 3.000,00"
        ],
      ];

      foreach ($raspadinhas as $r) {
        echo '
        <div class="raspadinha-card">
          <a href="roleta.php?id=' . $r['id'] . '">
            <div>
              <img src="' . $r['imagem'] . '" alt="Pacote Premium">
              <span class="valor-label">' . $r['valor'] . '</span>
            </div>
            <div class="raspadinha-info">
              <div class="raspadinha-titulo">PRÊMIOS ATÉ ' . $r['premio'] . '</div>
              <div class="raspadinha-nome">' . ($r['nome'] ?? 'Pacote Premium') . '</div>
            </div>
          </a>
        </div>';
      }
      ?>
    </div>
  </div>

  <!-- Footer Info -->
  <div class="rodape-info">
    <div class="logo">
      <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
    </div>
    <p>A maior e melhor plataforma de premiações do Brasil</p>
    <p>© 2025 Show de prêmios! Todos os direitos reservados.</p>
  </div>

  <!-- Bottom Navigation -->
  <div class="footer">
    <a href="index">
      <div><i class="fas fa-home"></i></div>
    </a>
    <a href="menu" class="active">
      <div><i class="fas fa-box"></i></div>
    </a>
    <a href="deposito" class="deposito-btn">
      <div><i class="fas fa-credit-card"></i></div>
    </a>
    <a href="afiliado">
      <div><i class="fas fa-user-plus"></i></div>
    </a>
    <a href="perfil">
      <div><i class="fas fa-user-group"></i></div>
    </a>
  </div>

  <script>
    // Animação de hover nos cards
    document.querySelectorAll('.raspadinha-card').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-8px) scale(1.02)';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
      });
    });

    // Efeito de loading ao clicar
    document.querySelectorAll('.raspadinha-card a').forEach(link => {
      link.addEventListener('click', function(e) {
        const card = this.closest('.raspadinha-card');
        card.style.opacity = '0.7';
        card.style.transform = 'scale(0.95)';
        
        // Pequeno delay para mostrar o efeito
        setTimeout(() => {
          window.location.href = this.href;
        }, 150);
        
        e.preventDefault();
      });
    });

    // Animação de entrada sequencial
    const cards = document.querySelectorAll('.raspadinha-card');
    cards.forEach((card, index) => {
      card.style.animationDelay = `${index * 0.1}s`;
    });
  </script>
</body>
</html>