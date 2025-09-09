<?php
$duracao = 60 * 60 * 24 * 30;
session_set_cookie_params(['lifetime'=>$duracao,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.gc_maxlifetime',$duracao);
session_start();
require 'db.php';
$config = $pdo->query("SELECT min_deposito,max_deposito FROM configuracoes LIMIT 1")->fetch();
$min = floatval($config['min_deposito']);
$max = floatval($config['max_deposito']);
if(!isset($_SESSION['usuario_id'])){
  header('Location: index.php');
  exit;
}
$usuarioId = $_SESSION['usuario_id'];
$stmt = $pdo->prepare("SELECT nome, saldo FROM usuarios WHERE id=?");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();
$stmt = $pdo->prepare("SELECT valor,status,criado_em FROM transacoes_pix WHERE usuario_id=? ORDER BY criado_em DESC LIMIT 10");
$stmt->execute([$usuarioId]);
$transacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$dadosJson = file_exists('imagens_menu.json') ? json_decode(file_get_contents('imagens_menu.json'), true) : [];
$logo = $dadosJson['logo'] ?? 'logo.png';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Depositar - Caixas</title>
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

/* Container principal */
.main-container {
  max-width: 600px;
  margin: 0 auto;
  padding: 24px 20px;
}

/* Formulário de depósito */
.deposit-form {
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

.input-group input {
  width: 100%;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #21262d;
  background: #0d1117;
  color: #ffffff;
  font-size: 16px;
  font-weight: 600;
  text-align: center;
  transition: all 0.2s ease;
}

.input-group input:focus {
  border-color: #fab201;
  outline: none;
  box-shadow: 0 0 0 3px rgba(250, 178, 1, 0.1);
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

.quick-amounts {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 24px;
}

.quick-amount {
  padding: 12px 8px;
  border-radius: 8px;
  border: 1px solid #21262d;
  background: #0d1117;
  color: #ffffff;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
  font-size: 14px;
}

.quick-amount:hover {
  border-color: #fab201;
  background: rgba(250, 178, 1, 0.1);
  color: #fab201;
}

.generate-btn {
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

.generate-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(250, 178, 1, 0.4);
}

.generate-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
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

.transaction-item {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 12px;
  transition: all 0.2s ease;
}

.transaction-item:hover {
  border-color: #fab201;
  background: rgba(250, 178, 1, 0.05);
}

.transaction-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.transaction-value {
  color: #fab201;
  font-weight: 700;
  font-size: 16px;
}

.transaction-status {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
}

.status-approved {
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-pending {
  background: rgba(245, 158, 11, 0.2);
  color: #f59e0b;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.transaction-date {
  color: #8b949e;
  font-size: 12px;
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
  
  .deposit-form {
    padding: 24px 20px;
  }
  
  .quick-amounts {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .header-content {
    padding: 0 4px;
  }
}

/* Animações */
.deposit-form {
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

.transaction-item {
  animation: slideInLeft 0.4s ease forwards;
  opacity: 0;
}

.transaction-item:nth-child(1) { animation-delay: 0.1s; }
.transaction-item:nth-child(2) { animation-delay: 0.2s; }
.transaction-item:nth-child(3) { animation-delay: 0.3s; }
.transaction-item:nth-child(4) { animation-delay: 0.4s; }
.transaction-item:nth-child(5) { animation-delay: 0.5s; }

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
      <i class="fas fa-credit-card"></i> Depósito
    </div>
    <div class="user-info">
      <span class="saldo">R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?></span>
    </div>
  </div>
</div>

<div class="main-container">
  <!-- Formulário de Depósito -->
  <div class="deposit-form">
    <div class="form-title">
      <h1><i class="fas fa-wallet"></i> Depositar via PIX</h1>
      <p>Escolha o valor que deseja depositar em sua conta</p>
    </div>
    
    <form method="POST" action="gerar_pix.php">
      <div class="input-group">
        <label for="valor">Valor do Depósito</label>
        <input type="number" name="valor" id="valor" value="<?= $min ?>" min="<?= $min ?>" max="<?= $max ?>" step="1" required>
      </div>
      
      <div class="limits-info">
        <i class="fas fa-info-circle"></i>
        Mínimo: R$ <?= number_format($min, 2, ',', '.') ?> | Máximo: R$ <?= number_format($max, 2, ',', '.') ?>
      </div>
      
      <div class="quick-amounts">
        <?php 
        $valores = [20, 30, 50, 100, 150, 200];
        foreach($valores as $v): 
        ?>
          <button type="button" class="quick-amount" onclick="setValor(<?= $v ?>)">
            R$ <?= $v ?>
          </button>
        <?php endforeach; ?>
      </div>
      
      <button type="submit" class="generate-btn">
        <i class="fas fa-qrcode"></i>
        Gerar PIX
      </button>
    </form>
  </div>

  <!-- Histórico de Transações -->
  <div class="history-section">
    <div class="history-header">
      <h2><i class="fas fa-history"></i> Histórico de Depósitos</h2>
      <p>Acompanhe suas últimas transações</p>
    </div>
    
    <?php if(count($transacoes) === 0): ?>
      <div class="empty-state">
        <i class="fas fa-receipt"></i>
        <p>Nenhuma transação encontrada</p>
        <small>Suas transações aparecerão aqui após o primeiro depósito</small>
      </div>
    <?php else: ?>
      <?php foreach($transacoes as $t): ?>
        <div class="transaction-item">
          <div class="transaction-header">
            <div class="transaction-value">
              R$ <?= number_format($t['valor'], 2, ',', '.') ?>
            </div>
            <div class="transaction-status <?= $t['status'] === 'aprovado' ? 'status-approved' : 'status-pending' ?>">
              <?= $t['status'] === 'aprovado' ? 'Aprovado' : 'Pendente' ?>
            </div>
          </div>
          <div class="transaction-date">
            <?php 
            $d = new DateTime($t['criado_em'], new DateTimeZone('UTC'));
            $d->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            echo $d->format('d/m/Y \à\s H:i');
            ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
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
  <a href="deposito" class="deposit-btn active">
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
function setValor(valor) {
  document.getElementById('valor').value = valor;
  
  // Feedback visual
  const input = document.getElementById('valor');
  input.style.borderColor = '#fab201';
  input.style.boxShadow = '0 0 0 3px rgba(250, 178, 1, 0.1)';
  
  setTimeout(() => {
    input.style.borderColor = '#21262d';
    input.style.boxShadow = 'none';
  }, 1000);
}

// Formatação do input de valor
document.getElementById('valor').addEventListener('input', function(e) {
  let value = e.target.value;
  if (value < <?= $min ?>) {
    e.target.style.borderColor = '#ff6b6b';
  } else if (value > <?= $max ?>) {
    e.target.style.borderColor = '#ff6b6b';
  } else {
    e.target.style.borderColor = '#21262d';
  }
});

// Animação de entrada para os itens do histórico
document.addEventListener('DOMContentLoaded', function() {
  const historySection = document.querySelector('.history-section');
  if (historySection) {
    historySection.style.opacity = '1';
  }
});
</script>
</body>
</html>