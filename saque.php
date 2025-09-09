<?php
session_start();
require 'db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();
$saldo = $usuario['saldo'];

$config = $pdo->query("SELECT min_saque, max_saque FROM configuracoes LIMIT 1")->fetch();
$min_saque = floatval($config['min_saque'] ?? 1);
$max_saque = floatval($config['max_saque'] ?? 1000);
$logo = isset($dadosJson['logo']) ? $dadosJson['logo'] : 'logo.png';

$stmtSaques = $pdo->prepare("SELECT valor, chave_pix, tipo_chave, status, data FROM saques WHERE usuario_id = ? ORDER BY data DESC");
$stmtSaques->execute([$_SESSION['usuario_id']]);
$saques = $stmtSaques->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Saque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <link rel=stylesheet href=/css/y345bv63.css>
</head>
<meta name="format-detection" content="telephone=no,email=no,address=no">
<body>
  <div class="container">
    <div class="back" onclick="history.back()">←</div>
    <div class="title">SACAR</div>
    <div class="tab">PIX</div>
<form id="formSaque">
<input 
  type="number" 
  name="valor" 
  id="valor" 
  class="input-box" 
  placeholder="Digite o valor"
  min="<?= $min_saque ?>" 
  max="<?= $max_saque ?>" 
  required
>
  <div class="subtitle">Saldo disponível: R$ <?= number_format($saldo, 2, ',', '.') ?></div>
  <div class="subtitle">Valor mínimo de saque: R$ <?= number_format($min_saque, 2, ',', '.') ?></div>
  <input type="text" name="nome" placeholder="Nome completo" class="input-box" required>
  <input type="text" name="cpf_cnpj" placeholder="CPF ou CNPJ" class="input-box" required>
  <select name="tipo_chave" class="input-box" required>
  <option value="">Selecione o tipo de chave</option>
  <option value="CPF">CPF</option>
  <option value="CNPJ">CNPJ</option>
  <option value="EMAIL">Email</option>
  <option value="PHONE">Telefone</option>
  <option value="RANDOM">Chave Aleatória</option>
  </select>
  <input type="text" name="chave_pix" placeholder="Digite a chave Pix" class="input-box" required>
  <button type="submit" class="generate-btn">Solicitar Saque</button>
  </form>
  <div id="mensagem" style="text-align:center; margin-top: 15px;"></div>

<script>
function addValor(v) {
  let campo = document.getElementById('valor');
  campo.value = parseInt(campo.value) + v;
}

document.getElementById('formSaque').addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(this);

  fetch('processar_saque.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    const msgDiv = document.getElementById('mensagem');
    msgDiv.textContent = data.mensagem;
    msgDiv.style.color = data.status === 'sucesso' ? 'lightgreen' : 'red';

    if (data.status === 'sucesso') {
      document.getElementById('valor').value = 10;
      this.chave_pix.value = '';
    }
  })
  .catch(() => {
    const msgDiv = document.getElementById('mensagem');
    msgDiv.textContent = 'Erro ao solicitar saque.';
    msgDiv.style.color = 'red';
  });
});
</script>
  <script>
    function addValor(v) {
      let campo = document.getElementById('valor');
      campo.value = parseInt(campo.value) + v;
    }
  </script>
  <div class="title" style="margin-top: 40px;">Histórico de Saques</div>
<?php if (count($saques) === 0): ?>
  <div class="subtitle">Nenhum saque solicitado ainda.</div>
<?php else: ?>
  <div style="margin-top: 10px;">
    <?php foreach ($saques as $s): ?>
      <div style="background: #151515; border: 1px solid #333; padding: 10px; border-radius: 8px; margin-bottom: 10px;">
        <div><strong>Valor:</strong> R$ <?= number_format($s['valor'], 2, ',', '.') ?></div>
        <div><strong>Chave Pix:</strong> <span style="user-select: text; pointer-events: none;"><?= htmlspecialchars($s['chave_pix']) ?></span> (<?= $s['tipo_chave'] ?>)</div>
        <div>
          <strong>Status:</strong>
          <?php
            $corStatus = match($s['status']) {
              'aprovado' => '#00c20f',
              'pendente' => '#ff4d4d',
              'recusado' => '#ff9900',
              default => '#aaa'
            };
            $textoStatus = ucfirst($s['status']);
          ?>
          <span style="color: <?= $corStatus ?>; font-weight: bold;"><?= $textoStatus ?></span>
        </div>
        <?php
  $dataUtc = new DateTime($s['data'], new DateTimeZone('UTC'));
  $dataUtc->setTimezone(new DateTimeZone('America/Sao_Paulo'));
?>
<div><strong>Data:</strong> <?= $dataUtc->format('d/m/Y H:i') ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
</div>
<div class=rodape-info>
<div class=logo>
<img src="images/<?= $logo ?>?v=<?= time() ?>" alt=Logo style=height:40px>
</div>
<p>Nome! é a maior e melhor plataforma de premiações do Brasil</p>
<p>© 2025 Show de prêmios!. Todos os direitos reservados.</p>
</div>

</body>
</html>
