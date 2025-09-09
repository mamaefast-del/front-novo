<?php
session_start();
require 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT nome, saldo, telefone, email FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

// Carrega logo
$dadosJson = file_exists('imagens_menu.json') ? json_decode(file_get_contents('imagens_menu.json'), true) : [];
$logo = $dadosJson['logo'] ?? 'logo.png';

// Histórico de transações
$stmt = $pdo->prepare("SELECT 'deposito' as tipo, valor, status, criado_em as data FROM transacoes_pix WHERE usuario_id = ? 
                      UNION ALL 
                      SELECT 'saque' as tipo, valor, status, data FROM saques WHERE usuario_id = ? 
                      ORDER BY data DESC LIMIT 10");
$stmt->execute([$_SESSION['usuario_id'], $_SESSION['usuario_id']]);
$transacoes = $stmt->fetchAll();

// Histórico de jogos
$stmt = $pdo->prepare("SELECT valor_apostado, valor_premiado, data_jogo as data FROM historico_jogos WHERE usuario_id = ? ORDER BY data_jogo DESC LIMIT 10");
$stmt->execute([$_SESSION['usuario_id']]);
$jogos = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Meu Perfil</title>
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

    .header-title {
      color: #fab201;
      font-size: 18px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
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

    /* Container */
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    /* Tabs */
    .tabs {
      display: flex;
      background: #111318;
      border-bottom: 1px solid #1a1d24;
      position: sticky;
      top: 72px;
      z-index: 50;
      backdrop-filter: blur(20px);
      margin: 0 -20px;
    }

    .tab {
      flex: 1;
      text-align: center;
      padding: 16px 0;
      cursor: pointer;
      font-weight: 700;
      color: #8b949e;
      transition: all 0.3s ease;
      position: relative;
      background: transparent;
      border: none;
      font-size: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .tab.active {
      color: #fab201;
    }

    .tab.active::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(135deg, #fab201, #f4c430);
      border-radius: 2px 2px 0 0;
    }

    .tab:hover:not(.active) {
      color: #ffffff;
      background: rgba(250, 178, 1, 0.1);
    }

    /* Tab Content */
    .tab-content {
      display: none;
      padding: 24px 0;
      animation: fadeInUp 0.4s ease;
    }

    .tab-content.active {
      display: block;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Cards */
    .card {
      background: #111318;
      border: 1px solid #1a1d24;
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 24px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .card::before {
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

    .card:hover {
      border-color: #fab201;
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(250, 178, 1, 0.1);
    }

    .card h3 {
      color: #fab201;
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Profile Info */
    .profile-info {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .info-item {
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 12px;
      padding: 16px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .info-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 1px;
      background: linear-gradient(90deg, transparent, #fab201, transparent);
      animation: shimmer 4s infinite;
    }

    .info-item:hover {
      border-color: #fab201;
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(250, 178, 1, 0.1);
    }

    .info-label {
      color: #8b949e;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .info-value {
      color: #ffffff;
      font-size: 16px;
      font-weight: 600;
    }

    .info-value.highlight {
      color: #fab201;
      font-weight: 700;
      font-size: 18px;
    }

    /* Transaction List */
    .transaction-list {
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 12px;
      overflow: hidden;
    }

    .transaction-item {
      padding: 16px 20px;
      border-bottom: 1px solid #21262d;
      transition: all 0.3s ease;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .transaction-item:last-child {
      border-bottom: none;
    }

    .transaction-item:hover {
      background: rgba(250, 178, 1, 0.05);
    }

    .transaction-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .transaction-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }

    .transaction-icon.deposito {
      background: rgba(34, 197, 94, 0.2);
      color: #22c55e;
    }

    .transaction-icon.saque {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
    }

    .transaction-icon.jogo {
      background: rgba(250, 178, 1, 0.2);
      color: #fab201;
    }

    .transaction-details h4 {
      color: #ffffff;
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 2px;
    }

    .transaction-details p {
      color: #8b949e;
      font-size: 12px;
    }

    .transaction-amount {
      text-align: right;
    }

    .transaction-value {
      font-weight: 700;
      font-size: 14px;
      margin-bottom: 2px;
    }

    .transaction-value.positive {
      color: #22c55e;
    }

    .transaction-value.negative {
      color: #ef4444;
    }

    .transaction-value.neutral {
      color: #fab201;
    }

    .transaction-status {
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 4px;
      text-transform: uppercase;
      font-weight: 600;
    }

    .status-aprovado {
      background: rgba(34, 197, 94, 0.2);
      color: #22c55e;
    }

    .status-pendente {
      background: rgba(250, 178, 1, 0.2);
      color: #fab201;
    }

    .status-recusado {
      background: rgba(239, 68, 68, 0.2);
      color: #ef4444;
    }

    /* Action Buttons */
    .action-buttons {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .btn {
      padding: 14px 20px;
      border-radius: 12px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-size: 14px;
      position: relative;
      overflow: hidden;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
      transition: left 0.5s ease;
    }

    .btn:hover::before {
      left: 100%;
    }

    .btn-primary {
      background: linear-gradient(135deg, #fab201, #f4c430);
      color: #000;
      box-shadow: 0 2px 8px rgba(250, 178, 1, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(250, 178, 1, 0.4);
    }

    .btn-secondary {
      background: #1a1d24;
      color: #ffffff;
      border: 1px solid #2a2d34;
    }

    .btn-secondary:hover {
      background: #2a2d34;
      border-color: #fab201;
      transform: translateY(-2px);
    }

    .btn-danger {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #ffffff;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(239, 68, 68, 0.4);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #8b949e;
    }

    .empty-state i {
      font-size: 48px;
      color: #21262d;
      margin-bottom: 16px;
      opacity: 0.7;
    }

    .empty-state h3 {
      color: #8b949e;
      margin-bottom: 8px;
      font-size: 18px;
    }

    .empty-state p {
      font-size: 14px;
      line-height: 1.6;
    }

    /* Bottom Navigation */
    .bottom-nav {
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

    .bottom-nav a {
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

    .bottom-nav a:hover,
    .bottom-nav a.active {
      color: #fab201;
      background: rgba(250, 178, 1, 0.1);
    }

    .bottom-nav .deposit-btn {
      background: linear-gradient(135deg, #fab201, #f4c430);
      color: #000 !important;
      font-weight: 700;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(250, 178, 1, 0.3);
    }

    .bottom-nav .deposit-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(250, 178, 1, 0.4);
    }

    .bottom-nav i {
      font-size: 16px;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .container {
        padding: 0 16px;
      }

      .header-content {
        padding: 0 4px;
      }

      .profile-info {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
      }

      .action-buttons {
        grid-template-columns: 1fr;
        gap: 12px;
      }

      .transaction-item {
        padding: 12px 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }

      .transaction-amount {
        text-align: left;
        width: 100%;
      }

      .transaction-details h4 {
        font-size: 13px;
      }

      .transaction-details p {
        font-size: 11px;
      }

      .tabs {
        margin: 0 -16px;
      }

      .tab {
        font-size: 12px;
        padding: 12px 8px;
      }

      .tab i {
        font-size: 14px;
      }
    }

    /* Loading Animation */
    .card {
      animation: slideInUp 0.6s ease forwards;
      opacity: 0;
    }

    .card:nth-child(1) { animation-delay: 0.1s; }
    .card:nth-child(2) { animation-delay: 0.2s; }
    .card:nth-child(3) { animation-delay: 0.3s; }

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

    /* Profile Stats */
    .profile-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: #0d1117;
      border: 1px solid #21262d;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #fab201, transparent);
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      border-color: #fab201;
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(250, 178, 1, 0.1);
    }

    .stat-card:hover::before {
      left: 100%;
    }

    .stat-value {
      font-size: 24px;
      font-weight: 800;
      color: #fab201;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 12px;
      color: #8b949e;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Welcome Section */
    .welcome-section {
      background: linear-gradient(135deg, #111318 0%, #1a1d24 100%);
      border-radius: 16px;
      padding: 32px 24px;
      margin-bottom: 32px;
      text-align: center;
      border: 1px solid #2a2d34;
      position: relative;
      overflow: hidden;
    }

    .welcome-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, transparent, #fab201, transparent);
      animation: shimmer 3s infinite;
    }

    .welcome-section h1 {
      font-size: 28px;
      font-weight: 800;
      color: #fab201;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }

    .welcome-section p {
      color: #8b949e;
      font-size: 16px;
    }

    /* Pulse animation for important elements */
    .info-value.highlight {
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0%, 100% { 
        transform: scale(1);
        opacity: 1;
      }
      50% { 
        transform: scale(1.05);
        opacity: 0.9;
      }
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
      <div class="header-title">
        <i class="fas fa-user-circle"></i> Meu Perfil
      </div>
      <div class="saldo">
        R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="openTab('perfil')">
      <i class="fas fa-user"></i> <span>Perfil</span>
    </button>
    <button class="tab" onclick="openTab('transacoes')">
      <i class="fas fa-exchange-alt"></i> <span>Transações</span>
    </button>
    <button class="tab" onclick="openTab('jogos')">
      <i class="fas fa-gamepad"></i> <span>Histórico</span>
    </button>
  </div>

  <div class="container">
    <!-- Tab Perfil -->
    <div id="perfil" class="tab-content active">
      <!-- Welcome Section -->
      <div class="welcome-section">
        <h1>
          <i class="fas fa-crown"></i>
          Olá, <?= explode(' ', $usuario['nome'])[0] ?>!
        </h1>
        <p>Gerencie sua conta e acompanhe sua jornada conosco</p>
      </div>

      <!-- Profile Stats -->
      <div class="profile-stats">
        <div class="stat-card">
          <div class="stat-value">R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?></div>
          <div class="stat-label">Saldo Atual</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= count($transacoes) ?></div>
          <div class="stat-label">Transações</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= count($jogos) ?></div>
          <div class="stat-label">Jogos</div>
        </div>
      </div>

      <!-- Informações do Perfil -->
      <div class="card">
        <h3><i class="fas fa-user-circle"></i> Informações Pessoais</h3>
        <div class="profile-info">
          <div class="info-item">
            <div class="info-label">Nome Completo</div>
            <div class="info-value"><?= htmlspecialchars($usuario['nome']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Telefone</div>
            <div class="info-value"><?= htmlspecialchars($usuario['telefone'] ?: 'Não informado') ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">E-mail</div>
            <div class="info-value"><?= htmlspecialchars($usuario['email']) ?></div>
          </div>
          <div class="info-item">
            <div class="info-label">Saldo Disponível</div>
            <div class="info-value highlight">R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?></div>
          </div>
        </div>
      </div>

      <!-- Ações Rápidas -->
      <div class="card">
        <h3><i class="fas fa-bolt"></i> Ações Rápidas</h3>
        <div class="action-buttons">
          <a href="deposito.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Fazer Depósito
          </a>
          <a href="saque.php" class="btn btn-secondary">
            <i class="fas fa-money-bill-wave"></i> Solicitar Saque
          </a>
          <a href="menu.php" class="btn btn-secondary">
            <i class="fas fa-gift"></i> Ver Pacotes
          </a>
          <a href="logout.php" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja sair?')">
            <i class="fas fa-sign-out-alt"></i> Sair da Conta
          </a>
        </div>
      </div>
    </div>

    <!-- Tab Transações -->
    <div id="transacoes" class="tab-content">
      <div class="card">
        <h3><i class="fas fa-exchange-alt"></i> Últimas Transações</h3>
        <?php if (!empty($transacoes)): ?>
          <div class="transaction-list">
            <?php foreach ($transacoes as $transacao): ?>
              <div class="transaction-item">
                <div class="transaction-info">
                  <div class="transaction-icon <?= $transacao['tipo'] ?>">
                    <i class="fas fa-<?= $transacao['tipo'] == 'deposito' ? 'arrow-down' : 'arrow-up' ?>"></i>
                  </div>
                  <div class="transaction-details">
                    <h4><?= ucfirst($transacao['tipo']) ?></h4>
                    <p><?= date('d/m/Y H:i', strtotime($transacao['data'])) ?></p>
                  </div>
                </div>
                <div class="transaction-amount">
                  <div class="transaction-value <?= $transacao['tipo'] == 'deposito' ? 'positive' : 'negative' ?>">
                    <?= $transacao['tipo'] == 'deposito' ? '+' : '-' ?>R$ <?= number_format($transacao['valor'], 2, ',', '.') ?>
                  </div>
                  <div class="transaction-status status-<?= $transacao['status'] ?>">
                    <?= ucfirst($transacao['status']) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-exchange-alt"></i>
            <h3>Nenhuma transação encontrada</h3>
            <p>Suas transações aparecerão aqui quando você fizer depósitos ou saques.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tab Jogos -->
    <div id="jogos" class="tab-content">
      <div class="card">
        <h3><i class="fas fa-gamepad"></i> Histórico de Jogos</h3>
        <?php if (!empty($jogos)): ?>
          <div class="transaction-list">
            <?php foreach ($jogos as $jogo): ?>
              <div class="transaction-item">
                <div class="transaction-info">
                  <div class="transaction-icon jogo">
                    <i class="fas fa-dice"></i>
                  </div>
                  <div class="transaction-details">
                    <h4>Jogo da Sorte</h4>
                    <p><?= date('d/m/Y H:i', strtotime($jogo['data'])) ?></p>
                  </div>
                </div>
                <div class="transaction-amount">
                  <div class="transaction-value negative">
                    -R$ <?= number_format($jogo['valor_apostado'], 2, ',', '.') ?>
                  </div>
                  <?php if ($jogo['valor_premiado'] > 0): ?>
                    <div class="transaction-value positive" style="font-size: 12px;">
                      +R$ <?= number_format($jogo['valor_premiado'], 2, ',', '.') ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fas fa-gamepad"></i>
            <h3>Nenhum jogo encontrado</h3>
            <p>Seu histórico de jogos aparecerá aqui quando você começar a jogar.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="index">
      <i class="fas fa-home"></i>
      <span>Início</span>
    </a>
    <a href="menu">
      <i class="fas fa-box"></i>
      <span>Pacotes</span>
    </a>
    <a href="deposito" class="deposit-btn">
      <i class="fas fa-credit-card"></i>
      <span>Depositar</span>
    </a>
    <a href="afiliados">
      <i class="fas fa-users"></i>
      <span>Afiliados</span>
    </a>
    <a href="perfil" class="active">
      <i class="fas fa-user"></i>
      <span>Perfil</span>
    </a>
  </div>

  <script>
    function openTab(tabName) {
      // Remove active class from all tabs and contents
      document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
      
      // Add active class to clicked tab and corresponding content
      event.target.classList.add('active');
      document.getElementById(tabName).classList.add('active');
    }

    // Animação de entrada dos cards
    document.addEventListener('DOMContentLoaded', function() {
      const cards = document.querySelectorAll('.card');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });

      // Animação das transações
      const transactionItems = document.querySelectorAll('.transaction-item');
      transactionItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(-20px)';
        item.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
          item.style.opacity = '1';
          item.style.transform = 'translateX(0)';
        }, index * 100);
      });

      // Animação dos stats
      const statCards = document.querySelectorAll('.stat-card');
      statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.4s ease';
        
        setTimeout(() => {
          card.style.opacity = '1';
          card.style.transform = 'translateY(0)';
        }, index * 150);
      });
    });

    // Efeito de hover nos botões de ação
    document.querySelectorAll('.btn').forEach(btn => {
      btn.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px) scale(1.02)';
      });
      
      btn.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
      });
    });

    // Efeito de click nos cards de informação
    document.querySelectorAll('.info-item').forEach(item => {
      item.addEventListener('click', function() {
        this.style.transform = 'scale(0.98)';
        setTimeout(() => {
          this.style.transform = 'scale(1)';
        }, 150);
      });
    });
  </script>
</body>
</html>