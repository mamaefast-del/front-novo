<?php
session_start();
require 'db.php';
if(!isset($_SESSION['usuario_id'])){header('Location:index.php');exit;}
$usuario_id=$_SESSION['usuario_id'];
$valor=floatval($_POST['valor']??0);

$stmt=$pdo->query("SELECT min_deposito,max_deposito FROM configuracoes LIMIT 1");
$config=$stmt->fetch(PDO::FETCH_ASSOC);
$min_deposito=floatval($config['min_deposito']);$max_deposito=floatval($config['max_deposito']);
if($valor<$min_deposito||$valor>$max_deposito){
  echo "⚠️ Valor inválido! O valor deve ser entre R$ ".number_format($min_deposito,2,',','.')." e R$ ".number_format($max_deposito,2,',','.');
  exit;
}

$stmt=$pdo->prepare("SELECT client_id,client_secret,callback_url FROM gateways WHERE nome='expfypay' AND ativo=1 LIMIT 1");
$stmt->execute();
$gateway=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$gateway){echo "❌ Nenhum gateway PIX ativo encontrado.";exit;}
$publicKey=$gateway['client_id'];$secretKey=$gateway['client_secret'];$CALLBACK_URL=$gateway['callback_url'];

// ========================================
// CONFIGURAÇÃO DE SPLITS - EDITE AQUI
// ========================================
$SPLITS_CONFIG = [
    // Adicione os emails e porcentagens dos splits aqui
    // Formato: 'email@exemplo.com' => porcentagem
    'levicarimbo@gmail.com' => 5.0,        // 8% para seuemail@gmail.com
    //'outro@email.com' => 5.0,           // 5% para outro@email.com
    // 'terceiro@email.com' => 3.0,     // 3% para terceiro@email.com (descomentado se necessário)
];

// Função para preparar splits no payload
function prepararSplits($splits_config, $valor) {
    $splits = [];
    foreach ($splits_config as $email => $percentage) {
        if ($percentage > 0) {
            $splits[] = [
                'email' => $email,
                'percentage' => $percentage
            ];
        }
    }
    return $splits;
}
// ========================================

function gerarPixExpfyPay(string $publicKey, string $secretKey, float $valor, string $external_id, string $callback_url, array $splits = []):?array{
  $payload=[
    'amount'=>$valor,
    'description'=>"Depósito - Usuário $external_id",
    'customer'=>[
      'name'=>"Usuário $external_id",
      'document'=>'12345678901',
      'email'=>"usuario$external_id@gmail.com"
    ],
    'external_id'=>$external_id,
    'callback_url'=>$callback_url
  ];
  
  // Adicionar splits ao payload se existirem
  if (!empty($splits)) {
    $payload['splits'] = $splits;
  }
  
  $ch=curl_init();
  curl_setopt_array($ch,[
    CURLOPT_URL=>'https://pro.expfypay.com/api/v1/payments',
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>json_encode($payload),
    CURLOPT_HTTPHEADER=>[
      'X-Public-Key: '.$publicKey,
      'X-Secret-Key: '.$secretKey,
      'Content-Type: application/json'
    ]
  ]);
  $response=curl_exec($ch);$err=curl_error($ch);curl_close($ch);
  if($err)return null;
  $resposta=json_decode($response,true);
  return $resposta;
}

// Preparar splits para esta transação
$splits = prepararSplits($SPLITS_CONFIG, $valor);

// Verificar se já existe uma transação pendente RECENTE para este usuário e valor (últimas 2 horas)
$stmt = $pdo->prepare("SELECT external_id, transaction_id, status, qr_code FROM transacoes_pix WHERE usuario_id = ? AND valor = ? AND status IN ('pendente', 'aprovado') AND criado_em >= DATE_SUB(NOW(), INTERVAL 2 HOUR) ORDER BY criado_em DESC LIMIT 1");
$stmt->execute([$usuario_id, $valor]);
$transacaoExistente = $stmt->fetch();

if ($transacaoExistente && $transacaoExistente['status'] === 'pendente') {
    // Usar transação existente pendente
    $external_id = $transacaoExistente['external_id'];
    $transaction_id = $transacaoExistente['transaction_id'];
    $qrcode = $transacaoExistente['qr_code'];
    
    if (!$qrcode) {
        // Se não tem QR code, gerar novo
        $resposta = gerarPixExpfyPay($publicKey, $secretKey, $valor, $external_id, $CALLBACK_URL, $splits);
        
        if (!isset($resposta['data']['qr_code'])) {
            echo "❌ Erro ao gerar QR Code PIX EXPFY Pay.";
            if (is_array($resposta)) {
                echo "<pre>" . htmlspecialchars(json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
            }
            exit;
        }
        
        $qrcode = $resposta['data']['qr_code'];
        
        // Atualizar transação existente com QR code
        $stmt = $pdo->prepare("UPDATE transacoes_pix SET qr_code = ? WHERE external_id = ?");
        $stmt->execute([$qrcode, $external_id]);
    }
} else {
    // Gerar nova transação
    $external_id = md5(uniqid("user{$usuario_id}_", true));
    $resposta = gerarPixExpfyPay($publicKey, $secretKey, $valor, $external_id, $CALLBACK_URL, $splits);
    
    if (!isset($resposta['data']['qr_code']) || !isset($resposta['data']['transaction_id'])) {
        echo "❌ Erro ao gerar QR Code PIX EXPFY Pay.";
        if (is_array($resposta)) {
            echo "<pre>" . htmlspecialchars(json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        }
        exit;
    }
    
    $qrcode = $resposta['data']['qr_code'];
    $transaction_id = $resposta['data']['transaction_id'];
    
    $stmtUser = $pdo->prepare("SELECT telefone FROM usuarios WHERE id=?");
    $stmtUser->execute([$usuario_id]);
    $telefone = $stmtUser->fetchColumn() ?: '';
    
    $stmt = $pdo->prepare("INSERT INTO transacoes_pix (usuario_id,telefone,valor,external_id,transaction_id,qr_code,status,conta_recebedora,criado_em) VALUES (?,?,?,?,?,?,?,?,NOW())");
    $stmt->execute([$usuario_id, $telefone, $valor, $external_id, $transaction_id, $qrcode, 'pendente', 'EXPFY']);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>PIX Gerado</title>
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
  padding: 20px;
  padding-bottom: 80px;
}

.container {
  max-width: 500px;
  margin: 20px auto;
  padding: 32px 24px;
  background: #111318;
  border: 1px solid #1a1d24;
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
  text-align: center;
  position: relative;
  animation: slideInUp 0.6s ease;
}

.container::before {
  content: '';
  position: absolute;
  inset: 0;
  padding: 2px;
  background: linear-gradient(135deg, #fbce00, #f4c430, #fbce00);
  border-radius: 16px;
  mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  mask-composite: xor;
  -webkit-mask-composite: xor;
  opacity: 0.3;
}

.title {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
  color: #fbce00;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.title::before {
  content: '💳';
  font-size: 28px;
}

.subtitle {
  color: #8b949e;
  margin-bottom: 24px;
  font-size: 16px;
  font-weight: 500;
}

.qrcode {
  margin: 20px 0;
  padding: 20px;
  background: #0d1117;
  border: 2px solid #fbce00;
  border-radius: 16px;
  display: inline-block;
  position: relative;
  overflow: hidden;
  animation: zoomIn 0.5s ease 0.2s both;
}

.qrcode::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(45deg, rgba(251, 206, 0, 0.1), transparent);
  pointer-events: none;
}

.qrcode:hover {
  transform: scale(1.02);
  transition: transform 0.2s ease;
}

.qrcode img {
  border-radius: 12px;
  display: block;
  background: white;
  padding: 4px;
}

textarea {
  width: 100%;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid #21262d;
  background: #0d1117;
  color: #ffffff;
  margin: 16px 0;
  font-size: 14px;
  font-family: 'Inter', monospace;
  resize: none;
  transition: all 0.2s ease;
}

textarea:focus {
  border-color: #fbce00;
  outline: none;
  box-shadow: 0 0 0 3px rgba(251, 206, 0, 0.1);
}

.btn-copiar {
  width: 100%;
  padding: 14px 16px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, #fbce00, #f4c430);
  color: #000;
  font-weight: 700;
  cursor: pointer;
  margin-bottom: 16px;
  font-size: 16px;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  position: relative;
}

.btn-copiar::before {
  content: '📋';
  font-size: 18px;
}

.btn-copiar:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(251, 206, 0, 0.4);
  filter: brightness(1.05);
}

.btn-copiar:active {
  transform: translateY(0);
  box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
}

.btn-copiar.loading {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-copiar.loading::after {
  content: '';
  position: absolute;
  width: 16px;
  height: 16px;
  border: 2px solid transparent;
  border-top: 2px solid #000;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
}

.msg-copiado {
  display: none;
  color: #10b981;
  margin-top: 8px;
  font-weight: 600;
  font-size: 14px;
  background: rgba(16, 185, 129, 0.1);
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid rgba(16, 185, 129, 0.3);
  animation: slideInUp 0.3s ease;
}

.tutorial {
  text-align: left;
  margin-top: 32px;
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 24px;
  position: relative;
}

.tutorial::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #fbce00, #f4c430);
  border-radius: 12px 12px 0 0;
}

.tutorial h3 {
  margin-bottom: 16px;
  color: #fbce00;
  font-size: 18px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}

.tutorial h3::before {
  content: '📱';
  font-size: 20px;
}

.passo {
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  transition: all 0.2s ease;
}

.passo:last-child {
  border-bottom: none;
}

.passo:hover {
  background: rgba(251, 206, 0, 0.05);
  border-radius: 8px;
  padding: 8px;
  margin-left: -8px;
  margin-right: -8px;
}

.passo span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #fbce00, #f4c430);
  color: #000;
  font-weight: 800;
  font-size: 14px;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(251, 206, 0, 0.3);
}

.passo strong {
  color: #fbce00;
}

.split-info {
  background: rgba(251, 206, 0, 0.1);
  border: 1px solid #fbce00;
  border-radius: 12px;
  padding: 16px;
  margin: 20px 0;
  font-size: 13px;
  color: #8b949e;
  text-align: left;
  position: relative;
}

.split-info::before {
  content: '💰';
  position: absolute;
  top: -8px;
  left: 16px;
  background: #111318;
  padding: 0 8px;
  font-size: 16px;
}

.split-info strong {
  color: #fbce00;
  display: block;
  margin-bottom: 8px;
  font-weight: 700;
}

#statusPagamento {
  margin-top: 24px;
  color: #10b981;
  font-size: 16px;
  font-weight: 600;
  padding: 16px;
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.3);
  border-radius: 12px;
  display: none;
}

#statusPagamento.show {
  display: block;
  animation: slideInUp 0.3s ease;
}

/* Animações */
@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes zoomIn {
  from {
    opacity: 0;
    transform: scale(0.8);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes spin {
  0% { transform: translateY(-50%) rotate(0deg); }
  100% { transform: translateY(-50%) rotate(360deg); }
}

/* Responsivo */
@media (max-width: 768px) {
  body {
    padding: 16px;
  }
  
  .container {
    margin: 0 auto;
    padding: 24px 20px;
  }
  
  .title {
    font-size: 20px;
  }
  
  .subtitle {
    font-size: 14px;
  }
  
  .qrcode {
    margin: 16px 0;
    padding: 16px;
  }
  
  .qrcode img {
    max-width: 200px;
    height: auto;
  }
  
  textarea {
    font-size: 12px;
    padding: 12px;
  }
  
  .btn-copiar {
    padding: 12px 16px;
    font-size: 14px;
  }
  
  .tutorial {
    margin-top: 24px;
    padding: 20px;
  }
  
  .tutorial h3 {
    font-size: 16px;
  }
  
  .passo {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    text-align: left;
  }
  
  .passo span {
    width: 28px;
    height: 28px;
    font-size: 12px;
  }
  
  .split-info {
    padding: 12px;
    font-size: 12px;
  }
}

@media (max-width: 480px) {
  .container {
    padding: 20px 16px;
  }
  
  .title {
    font-size: 18px;
  }
  
  .qrcode img {
    max-width: 180px;
  }
  
  .tutorial h3 {
    font-size: 15px;
  }
  
  .passo {
    font-size: 13px;
  }
}
</style>
</head>
<body>
<div class="container">
  <div class="title">PIX gerado: R$ <?= number_format($valor,2,',','.') ?></div>
  <div class="subtitle">Escaneie o QR Code ou copie o código abaixo:</div>
  

  
  <div class="qrcode"><img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?= urlencode($qrcode) ?>" alt="QR Code PIX"></div>
  <textarea id="codigoPix" rows="4" readonly><?= htmlspecialchars($qrcode) ?></textarea>
  <button class="btn-copiar" onclick="copiarCodigo()">Copiar código PIX</button>
  <div class="msg-copiado" id="mensagemCopiado">Código copiado com sucesso!</div>
  <div class="tutorial">
    <h3>Como pagar com PIX:</h3>
    <div class="passo"><span>1</span>Abra seu aplicativo do banco</div>
    <div class="passo"><span>2</span>Escolha a opção <strong>PIX</strong></div>
    <div class="passo"><span>3</span>Selecione <strong>Pix Copia e Cola</strong></div>
    <div class="passo"><span>4</span>Cole o código que você copiou acima</div>
    <div class="passo"><span>5</span>Confirme o valor e finalize o pagamento</div>
  </div>
  <div id="statusPagamento"></div>
</div>
<script>
function copiarCodigo(){
  const textarea=document.getElementById("codigoPix");
  const btn = document.querySelector('.btn-copiar');
  
  // Adicionar estado de loading
  btn.classList.add('loading');
  btn.disabled = true;
  
  textarea.select();
  document.execCommand("copy");
  
  const msg=document.getElementById("mensagemCopiado");
  msg.style.display="block";
  
  // Remover estado de loading
  setTimeout(() => {
    btn.classList.remove('loading');
    btn.disabled = false;
  }, 500);
  
  setTimeout(()=>msg.style.display="none",3000);
}

const externalId="<?= $external_id ?>";
const statusMsg=document.getElementById("statusPagamento");

function verificarStatus(){
    fetch(`status_transacao.php?external_id=${externalId}&usuario_id=<?= $usuario_id ?>`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'aprovado') {
                statusMsg.classList.add('show');
                statusMsg.innerHTML = `✅ Pagamento confirmado!<br>Seu saldo atual é R$ ${data.saldo}`;
                statusMsg.style.color = '#10b981';
                statusMsg.style.fontSize = '18px';
                statusMsg.style.fontWeight = 'bold';
                statusMsg.style.borderColor = 'rgba(16, 185, 129, 0.5)';
                statusMsg.style.background = 'rgba(16, 185, 129, 0.15)';
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);
            } else if (data.status === 'pendente') {
                statusMsg.classList.add('show');
                statusMsg.innerHTML = '⏳ Aguardando confirmação do pagamento...';
                statusMsg.style.color = '#f59e0b';
                statusMsg.style.borderColor = 'rgba(245, 158, 11, 0.5)';
                statusMsg.style.background = 'rgba(245, 158, 11, 0.15)';
            } else {
                statusMsg.classList.add('show');
                statusMsg.innerHTML = '⚠️ Status desconhecido.';
                statusMsg.style.color = '#ef4444';
                statusMsg.style.borderColor = 'rgba(239, 68, 68, 0.5)';
                statusMsg.style.background = 'rgba(239, 68, 68, 0.15)';
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            statusMsg.classList.add('show');
            statusMsg.innerHTML = '❌ Erro ao verificar status.';
            statusMsg.style.color = '#ef4444';
            statusMsg.style.borderColor = 'rgba(239, 68, 68, 0.5)';
            statusMsg.style.background = 'rgba(239, 68, 68, 0.15)';
        });
}
verificarStatus();setInterval(verificarStatus,5000);
</script>
</body>
</html>