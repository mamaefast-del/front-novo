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
$saldo = $usuario['saldo'];

$config = $pdo->query("SELECT min_saque, max_saque FROM configuracoes LIMIT 1")->fetch();
$min_saque = floatval($config['min_saque'] ?? 1);
$max_saque = floatval($config['max_saque'] ?? 1000);

// Carrega logo
$dadosJson = file_exists('imagens_menu.json') ? json_decode(file_get_contents('imagens_menu.json'), true) : [];
$logo = $dadosJson['logo'] ?? 'logo.png';

$stmtSaques = $pdo->prepare("SELECT valor, chave_pix, tipo_chave, status, data FROM saques WHERE usuario_id = ? ORDER BY data DESC");
$stmtSaques->execute([$_SESSION['usuario_id']]);
$saques = $stmtSaques->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Saque - Caixas</title>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
/* Reset e base */
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

.user-info {
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

.btn {
  padding: 10px 16px;
  border-radius: 8px;
  border: none;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
}

.btn-secondary {
  background: #1a1d24;
  color: #ffffff;
  border: 1px solid #2a2d34;
}

.btn-secondary:hover {
  background: #2a2d34;
}

/* Container principal */
.main-container {
  max-width: 600px;
  margin: 0 auto;
  padding: 24px 20px;
}

/* Formulário de saque */
.withdraw-form {
  background: #111318;
  border: 1px solid #1a1d24;
  border-radius: 16px;
  padding: 32px 24px;
  margin-bottom: 32px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.form-title {
  text-align: center;
  margin-bottom: 24px;
}

.form-title h1 {
  color: #ffffff;
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.form-title p {
  color: #8b949e;
  font-size: 14px;
}

.balance-info {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 20px;
  text-align: center;
}

.balance-info .balance-label {
  color: #8b949e;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.balance-info .balance-value {
  color: #fab201;
  font-size: 20px;
  font-weight: 800;
}

.limits-info {
  text-align: center;
  color: #8b949e;
  font-size: 12px;
  margin-bottom: 20px;
  padding: 8px 12px;
  background: #0d1117;
  border-radius: 8px;
  border: 1px solid #21262d;
}

.input-group {
  margin-bottom: 20px;
}

.input-group label {
  display: block;
  color: #ffffff;
  font-weight: 600;
  margin-bottom: 8px;
  font-size: 14px;
}

.input-group input,
.input-group select {
  width: 100%;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #21262d;
  background: #0d1117;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.2s ease;
}

.input-group input:focus,
.input-group select:focus {
  border-color: #fab201;
  outline: none;
  box-shadow: 0 0 0 3px rgba(250, 178, 1, 0.1);
}

.input-group input::placeholder {
  color: #8b949e;
}

.input-group select {
  cursor: pointer;
}

.input-group select option {
  background: #0d1117;
  color: #ffffff;
}

.withdraw-btn {
  width: 100%;
  padding: 16px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(135deg, #fab201, #f4c430);
  color: #000;
  font-weight: 800;
  font-size: 16px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 16px rgba(250, 178, 1, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.withdraw-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(250, 178, 1, 0.4);
}

.withdraw-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Mensagem */
.message {
  padding: 16px 20px;
  border-radius: 12px;
  margin: 20px 0;
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 500;
  animation: slideInDown 0.4s ease;
  text-align: center;
  justify-content: center;
}

.message.success {
  background: rgba(34, 197, 94, 0.15);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #22c55e;
}

.message.error {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
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

/* Histórico */
.history-section {
  background: #111318;
  border: 1px solid #1a1d24;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.history-header {
  margin-bottom: 20px;
}

.history-header h2 {
  color: #ffffff;
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.history-header p {
  color: #8b949e;
  font-size: 14px;
}

.withdraw-item {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  transition: all 0.2s ease;
}

.withdraw-item:hover {
  border-color: #fab201;
  background: rgba(250, 178, 1, 0.05);
}

.withdraw-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.withdraw-value {
  color: #fab201;
  font-weight: 700;
  font-size: 16px;
}

.withdraw-status {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-aprovado {
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-pendente {
  background: rgba(245, 158, 11, 0.2);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-recusado {
  background: rgba(239, 68, 68, 0.2);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.withdraw-details {
  color: #8b949e;
  font-size: 12px;
  margin-bottom: 4px;
}

.withdraw-date {
  color: #8b949e;
  font-size: 12px;
}

.pix-key {
  user-select: text;
  pointer-events: auto;
  background: #1a1d24;
  padding: 4px 8px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 11px;
  color: #fab201;
  display: inline-block;
  margin: 4px 0;
}

.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: #8b949e;
}

.empty-state i {
  font-size: 48px;
  margin-bottom: 16px;
  opacity: 0.5;
}

/* Footer Info */
.footer-info {
  background: #111318;
  text-align: center;
  padding: 40px 20px;
  margin-top: 60px;
  border-top: 1px solid #1a1d24;
}

.footer-info .logo img {
  height: 36px;
  margin-bottom: 16px;
  filter: brightness(1.1);
}

.footer-info p {
  color: #8b949e;
  margin: 8px 0;
  font-size: 14px;
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

/* Responsivo */
@media (max-width: 768px) {
  .main-container {
    padding: 16px;
  }
  
  .withdraw-form {
    padding: 24px 20px;
  }
  
  .header-content {
    padding: 0 4px;
  }
  
  .withdraw-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }
}

/* Animações */
.withdraw-form {
  animation: slideInUp 0.6s ease forwards;
}

.history-section {
  animation: slideInUp 0.6s ease forwards;
  animation-delay: 0.2s;
  opacity: 0;
}

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

.withdraw-item {
  animation: slideInLeft 0.4s ease forwards;
  opacity: 0;
}

.withdraw-item:nth-child(1) { animation-delay: 0.1s; }
.withdraw-item:nth-child(2) { animation-delay: 0.2s; }
.withdraw-item:nth-child(3) { animation-delay: 0.3s; }
.withdraw-item:nth-child(4) { animation-delay: 0.4s; }
.withdraw-item:nth-child(5) { animation-delay: 0.5s; }

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
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
      <i class="fas fa-money-bill-wave"></i> Saque
    </div>
    <div class="user-info">
      <span class="saldo">R$ <?= number_format($saldo, 2, ',', '.') ?></span>
    </div>
  </div>
</div>

<div class="main-container">
  <!-- Formulário de Saque -->
  <div class="withdraw-form">
    <div class="form-title">
      <h1><i class="fas fa-wallet"></i> Solicitar Saque via PIX</h1>
      <p>Retire seus ganhos de forma rápida e segura</p>
    </div>
    
    <div class="balance-info">
      <div class="balance-label">Saldo Disponível</div>
      <div class="balance-value">R$ <?= number_format($saldo, 2, ',', '.') ?></div>
    </div>
    
    <div class="limits-info">
      <i class="fas fa-info-circle"></i>
      Mínimo: R$ <?= number_format($min_saque, 2, ',', '.') ?> | Máximo: R$ <?= number_format($max_saque, 2, ',', '.') ?>
    </div>
    
    <form id="formSaque">
      <div class="input-group">
        <label for="valor">Valor do Saque (R$)</label>
        <input 
          type="number" 
          name="valor" 
          id="valor" 
          placeholder="Digite o valor"
          min="<?= $min_saque ?>" 
          max="<?= $max_saque ?>" 
          step="0.01"
          required
        >
      </div>
      
      <div class="input-group">
        <label for="nome">Nome Completo</label>
        <input type="text" name="nome" id="nome" placeholder="Seu nome completo" required>
      </div>
      
      <div class="input-group">
        <label for="cpf_cnpj">CPF ou CNPJ</label>
        <input type="text" name="cpf_cnpj" id="cpf_cnpj" placeholder="000.000.000-00" required>
      </div>
      
      <div class="input-group">
        <label for="tipo_chave">Tipo de Chave PIX</label>
        <select name="tipo_chave" id="tipo_chave" required>
          <option value="">Selecione o tipo de chave</option>
          <option value="CPF">CPF</option>
          <option value="CNPJ">CNPJ</option>
          <option value="EMAIL">Email</option>
          <option value="PHONE">Telefone</option>
          <option value="RANDOM">Chave Aleatória</option>
        </select>
      </div>
      
      <div class="input-group">
        <label for="chave_pix">Chave PIX</label>
        <input type="text" name="chave_pix" id="chave_pix" placeholder="Digite sua chave PIX" required>
      </div>
      
      <button type="submit" class="withdraw-btn">
        <i class="fas fa-paper-plane"></i>
        Solicitar Saque
      </button>
    </form>
    
    <div id="mensagem"></div>
  </div>

  <!-- Histórico de Saques -->
  <div class="history-section">
    <div class="history-header">
      <h2><i class="fas fa-history"></i> Histórico de Saques</h2>
      <p>Acompanhe suas solicitações de saque</p>
    </div>
    
    <?php if (count($saques) === 0): ?>
      <div class="empty-state">
        <i class="fas fa-money-bill-wave"></i>
        <h3>Nenhum saque solicitado</h3>
        <p>Suas solicitações de saque aparecerão aqui</p>
      </div>
    <?php else: ?>
      <?php foreach ($saques as $s): ?>
        <div class="withdraw-item">
          <div class="withdraw-header">
            <div class="withdraw-value">
              R$ <?= number_format($s['valor'], 2, ',', '.') ?>
            </div>
            <div class="withdraw-status status-<?= $s['status'] ?>">
              <?= ucfirst($s['status']) ?>
            </div>
          </div>
          <div class="withdraw-details">
            <strong>Chave PIX:</strong> 
            <span class="pix-key"><?= htmlspecialchars($s['chave_pix']) ?></span>
            (<?= $s['tipo_chave'] ?>)
          </div>
          <div class="withdraw-date">
            <?php
            $dataUtc = new DateTime($s['data'], new DateTimeZone('UTC'));
            $dataUtc->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            ?>
            <?= $dataUtc->format('d/m/Y \à\s H:i') ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Footer Info -->
<div class="footer-info">
  <div class="logo">
    <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
  </div>
  <p>A maior e melhor plataforma de premiações do Brasil</p>
  <p>© 2025 Show de prêmios! Todos os direitos reservados.</p>
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
  <a href="afiliado">
    <i class="fas fa-users"></i>
    <span>Afiliados</span>
  </a>
  <a href="perfil">
    <i class="fas fa-user"></i>
    <span>Perfil</span>
  </a>
</div>

<script>
document.getElementById('formSaque').addEventListener('submit', function(e) {
  e.preventDefault();

  const btn = this.querySelector('.withdraw-btn');
  const originalText = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
  btn.disabled = true;

  const formData = new FormData(this);

  fetch('processar_saque.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    const msgDiv = document.getElementById('mensagem');
    msgDiv.className = 'message ' + (data.status === 'sucesso' ? 'success' : 'error');
    msgDiv.innerHTML = '<i class="fas fa-' + (data.status === 'sucesso' ? 'check-circle' : 'exclamation-circle') + '"></i>' + data.mensagem;

    if (data.status === 'sucesso') {
      // Limpar formulário
      this.reset();
      // Recarregar página após 2 segundos para mostrar o novo saque no histórico
      setTimeout(() => {
        window.location.reload();
      }, 2000);
    }
  })
  .catch(() => {
    const msgDiv = document.getElementById('mensagem');
    msgDiv.className = 'message error';
    msgDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i>Erro ao solicitar saque. Tente novamente.';
  })
  .finally(() => {
    btn.innerHTML = originalText;
    btn.disabled = false;
  });
});

// Formatação do CPF/CNPJ
document.getElementById('cpf_cnpj').addEventListener('input', function(e) {
  let value = e.target.value.replace(/\D/g, '');
  
  if (value.length <= 11) {
    // CPF
    value = value.replace(/(\d{3})(\d)/, '$1.$2');
    value = value.replace(/(\d{3})(\d)/, '$1.$2');
    value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  } else {
    // CNPJ
    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
    value = value.replace(/(\d{4})(\d)/, '$1-$2');
  }
  
  e.target.value = value;
});

// Validação do valor
document.getElementById('valor').addEventListener('input', function(e) {
  const value = parseFloat(e.target.value);
  const min = <?= $min_saque ?>;
  const max = <?= $max_saque ?>;
  const saldo = <?= $saldo ?>;
  
  if (value < min || value > max || value > saldo) {
    e.target.style.borderColor = '#ef4444';
  } else {
    e.target.style.borderColor = '#21262d';
  }
});

// Animação de entrada para o histórico
document.addEventListener('DOMContentLoaded', function() {
  const historySection = document.querySelector('.history-section');
  if (historySection) {
    historySection.style.opacity = '1';
  }
});
</script>
</body>
</html>