<?php
$duracao = 60 * 60 * 24 * 30;
session_set_cookie_params(['lifetime'=>$duracao,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.gc_maxlifetime',$duracao);
session_start();
require 'db.php';

$usuarioLogado = false;
$usuario = null;

if (isset($_SESSION['usuario_id'])) {
    $usuarioLogado = true;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
}

// Se não estiver logado, redirecionar para index
if (!$usuarioLogado) {
    header('Location: index.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Verificar se é afiliado
$is_affiliate = !empty($usuario['codigo_afiliado']) && $usuario['afiliado_ativo'];

// Processar solicitação para se tornar afiliado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['become_affiliate'])) {
    try {
        if (!empty($usuario['codigo_afiliado'])) {
            $stmt = $pdo->prepare("UPDATE usuarios SET afiliado_ativo = 1 WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $_SESSION['success'] = 'Sua conta de afiliado foi reativada!';
        } else {
            // Gerar código único
            do {
                $codigo = strtoupper(substr(md5(uniqid() . $usuario_id . time()), 0, 8));
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE codigo_afiliado = ?");
                $stmt->execute([$codigo]);
            } while ($stmt->fetch());
            
            $stmt = $pdo->prepare("UPDATE usuarios SET codigo_afiliado = ?, afiliado_ativo = 1, porcentagem_afiliado = 10.00 WHERE id = ?");
            $stmt->execute([$codigo, $usuario_id]);
            
            // Registrar no histórico
            $stmt = $pdo->prepare("INSERT INTO historico_afiliados (afiliado_id, acao, detalhes) VALUES (?, 'auto_registration', 'Usuário se tornou afiliado automaticamente')");
            $stmt->execute([$usuario_id]);
            
            $_SESSION['success'] = 'Parabéns! Você agora é um afiliado!';
        }
        
        header('Location: afiliados.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Erro ao processar solicitação. Tente novamente.';
    }
}

// Processar solicitação de saque
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $valor = floatval($_POST['valor'] ?? 0);
    $chave_pix = trim($_POST['chave_pix'] ?? '');
    $tipo_chave = trim($_POST['tipo_chave'] ?? '');
    
    // Buscar configurações
    $config = $pdo->query("SELECT min_saque_comissao, max_saque_comissao FROM configuracoes LIMIT 1")->fetch();
    $min_saque = floatval($config['min_saque_comissao'] ?? 10);
    $max_saque = floatval($config['max_saque_comissao'] ?? 1000);
    
    if ($valor < $min_saque || $valor > $max_saque) {
        $_SESSION['error'] = "Valor deve estar entre R$ " . number_format($min_saque, 2, ',', '.') . " e R$ " . number_format($max_saque, 2, ',', '.');
    } elseif ($valor > $usuario['comissao']) {
        $_SESSION['error'] = 'Comissão insuficiente.';
    } elseif (empty($chave_pix)) {
        $_SESSION['error'] = 'Chave PIX é obrigatória.';
    } else {
        try {
            // Verificar se não há saque pendente
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM saques WHERE usuario_id = ? AND tipo = 'comissao' AND status = 'pendente'");
            $stmt->execute([$usuario_id]);
            $saque_pendente = $stmt->fetchColumn();
            
            if ($saque_pendente > 0) {
                $_SESSION['error'] = 'Você já possui um saque de comissão pendente.';
            } else {
                // Descontar da comissão
                $stmt = $pdo->prepare("UPDATE usuarios SET comissao = comissao - ? WHERE id = ?");
                $stmt->execute([$valor, $usuario_id]);
                
                // Registrar o saque
                $stmt = $pdo->prepare("
                    INSERT INTO saques (usuario_id, valor, chave_pix, tipo_chave, status, data, tipo, nome) 
                    VALUES (?, ?, ?, ?, 'pendente', NOW(), 'comissao', ?)
                ");
                $stmt->execute([$usuario_id, $valor, $chave_pix, $tipo_chave, $usuario['nome']]);
                
                $_SESSION['success'] = 'Saque solicitado com sucesso! Aguardando aprovação.';
                header('Location: afiliados.php');
                exit;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Erro ao processar saque. Tente novamente.';
        }
    }
}

// Buscar estatísticas do afiliado
if ($is_affiliate) {
    try {
        // Indicados diretos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE codigo_afiliado_usado = ?");
        $stmt->execute([$usuario['codigo_afiliado']]);
        $total_indicados = $stmt->fetchColumn();
        
        // Volume gerado pelos indicados
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(t.valor), 0) 
            FROM transacoes_pix t 
            JOIN usuarios u ON t.usuario_id = u.id 
            WHERE u.codigo_afiliado_usado = ? AND t.status = 'aprovado'
        ");
        $stmt->execute([$usuario['codigo_afiliado']]);
        $volume_gerado = $stmt->fetchColumn();
        
        // Comissões totais geradas
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor_comissao), 0) FROM comissoes WHERE afiliado_id = ?");
        $stmt->execute([$usuario_id]);
        $comissoes_totais = $stmt->fetchColumn();
        
        // Cliques no link
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_clicks WHERE afiliado_id = ?");
        $stmt->execute([$usuario_id]);
        $total_clicks = $stmt->fetchColumn();
        
        // Cliques que converteram (LÓGICA CORRIGIDA)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM affiliate_clicks WHERE afiliado_id = ? AND converteu = 1");
        $stmt->execute([$usuario_id]);
        $clicks_convertidos = $stmt->fetchColumn();
        
        // Taxa de conversão correta: (cliques convertidos / total cliques) * 100
        $conversao = $total_clicks > 0 ? ($clicks_convertidos / $total_clicks) * 100 : 0;
        
        // Últimos indicados
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   COALESCE(SUM(CASE WHEN t.status = 'aprovado' THEN t.valor ELSE 0 END), 0) as volume_individual
            FROM usuarios u
            LEFT JOIN transacoes_pix t ON t.usuario_id = u.id
            WHERE u.codigo_afiliado_usado = ?
            GROUP BY u.id
            ORDER BY u.data_cadastro DESC
            LIMIT 5
        ");
        $stmt->execute([$usuario['codigo_afiliado']]);
        $ultimos_indicados = $stmt->fetchAll();
        
        // Histórico de comissões
        $stmt = $pdo->prepare("
            SELECT c.*, u.nome as indicado_nome, u.email as indicado_email
            FROM comissoes c
            LEFT JOIN usuarios u ON c.usuario_indicado_id = u.id
            WHERE c.afiliado_id = ?
            ORDER BY c.data_criacao DESC
            LIMIT 10
        ");
        $stmt->execute([$usuario_id]);
        $historico_comissoes = $stmt->fetchAll();
        
        // Saques de comissão
        $stmt = $pdo->prepare("
            SELECT * FROM saques 
            WHERE usuario_id = ? AND tipo = 'comissao'
            ORDER BY data DESC
            LIMIT 5
        ");
        $stmt->execute([$usuario_id]);
        $historico_saques = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $total_indicados = $volume_gerado = $comissoes_totais = $total_clicks = $clicks_convertidos = $conversao = 0;
        $ultimos_indicados = $historico_comissoes = $historico_saques = [];
    }
}

// Buscar materiais de marketing
try {
    $materiais = $pdo->query("SELECT * FROM marketing_materials WHERE ativo = 1 ORDER BY data_criacao DESC LIMIT 4")->fetchAll();
} catch (PDOException $e) {
    $materiais = [];
}

// URL base do site
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$affiliate_link = $is_affiliate ? $base_url . "/?codigo=" . $usuario['codigo_afiliado'] : '';

// Buscar dados para o header (mesmo do index.php)
$jsonFile = __DIR__.'/imagens_menu.json';
$imagens = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
$dadosJson = file_exists('imagens_menu.json') ? json_decode(file_get_contents('imagens_menu.json'), true) : [];
$logo = $dadosJson['logo'] ?? 'logo.png';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Programa de Afiliados - Ganhe Dinheiro Indicando</title>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
/* Reset e base - Mesmo do index.php */
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

/* Header - Mesmo do index.php */
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

.user-actions {
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

.btn-primary {
  background: linear-gradient(135deg, #fab201, #f4c430);
  color: #000;
  box-shadow: 0 2px 8px rgba(250, 178, 1, 0.3);
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(250, 178, 1, 0.4);
}

.btn-secondary {
  background: #1a1d24;
  color: #ffffff;
  border: 1px solid #2a2d34;
}

.btn-secondary:hover {
  background: #2a2d34;
}

.btn-success {
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: white;
}

.btn-success:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(34, 197, 94, 0.4);
}

/* Container principal */
.main-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* Hero Section para Afiliados */
.affiliate-hero {
  margin: 24px auto;
  background: linear-gradient(135deg, #111318 0%, #1a1d24 100%);
  border-radius: 16px;
  padding: 40px 24px;
  text-align: center;
  border: 1px solid #2a2d34;
  position: relative;
  overflow: hidden;
}

.affiliate-hero::before {
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

.affiliate-hero h1 {
  font-size: 32px;
  font-weight: 800;
  color: #fab201;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
}

.affiliate-hero p {
  font-size: 18px;
  color: #8b949e;
  margin-bottom: 24px;
}

.affiliate-hero .highlight {
  color: #fab201;
  font-weight: 700;
}

/* Stats Cards - Estilo do index.php */
.stats-section {
  margin: 40px auto;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: #111318;
  border: 1px solid #1a1d24;
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

/* Seções de conteúdo */
.content-section {
  margin: 40px auto;
  background: #111318;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #1a1d24;
}

.section-header {
  margin-bottom: 24px;
}

.section-header h2 {
  color: #fab201;
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-header p {
  color: #8b949e;
  font-size: 14px;
}

/* Link de Afiliação */
.affiliate-link-section {
  background: #0d1117;
  border: 2px solid #fab201;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}

.link-container {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-bottom: 12px;
}

.link-input {
  flex: 1;
  padding: 12px 16px;
  background: #1a1d24;
  border: 1px solid #2a2d34;
  border-radius: 8px;
  color: #ffffff;
  font-family: monospace;
  font-size: 14px;
}

.copy-btn {
  padding: 12px 20px;
  background: linear-gradient(135deg, #fab201, #f4c430);
  color: #000;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.copy-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(250, 178, 1, 0.4);
}

.link-info {
  font-size: 12px;
  color: #8b949e;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Formulário de Saque */
.withdrawal-form {
  background: #0d1117;
  border: 1px solid #2a2d34;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-weight: 600;
  color: #ffffff;
  font-size: 14px;
}

.form-input {
  padding: 12px 16px;
  background: #1a1d24;
  border: 1px solid #2a2d34;
  border-radius: 8px;
  color: #ffffff;
  transition: all 0.2s ease;
}

.form-input:focus {
  outline: none;
  border-color: #fab201;
  box-shadow: 0 0 0 3px rgba(250, 178, 1, 0.1);
}

/* Tabelas */
.table-container {
  overflow-x: auto;
  margin-top: 20px;
}

.table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0 8px;
  background: transparent;
}

.table th {
  padding: 12px;
  color: #fab201;
  font-weight: 700;
  text-align: left;
  background: #0d1117;
  border: 1px solid #2a2d34;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table th:first-child {
  border-radius: 8px 0 0 8px;
}

.table th:last-child {
  border-radius: 0 8px 8px 0;
}

.table td {
  padding: 12px;
  color: #8b949e;
  vertical-align: middle;
  border: 1px solid #2a2d34;
  border-top: none;
  font-size: 13px;
  background: #0d1117;
}

.table td:first-child {
  border-radius: 8px 0 0 8px;
  border-left: 1px solid #2a2d34;
}

.table td:last-child {
  border-radius: 0 8px 8px 0;
  border-right: 1px solid #2a2d34;
}

.table tr:hover {
  background: rgba(250, 178, 1, 0.05);
  transform: translateY(-1px);
}

/* Status badges */
.status-badge {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.status-pendente { 
  background: rgba(251, 206, 0, 0.15); 
  color: #fab201; 
}
.status-aprovado { 
  background: rgba(34, 197, 94, 0.15); 
  color: #22c55e; 
}
.status-pago { 
  background: rgba(34, 197, 94, 0.15); 
  color: #22c55e; 
}
.status-rejeitado { 
  background: rgba(239, 68, 68, 0.15); 
  color: #ef4444; 
}

/* Benefits Grid - Para não afiliados */
.benefits-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin: 40px 0;
}

.benefit-card {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.benefit-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 2px;
  background: linear-gradient(90deg, transparent, #fab201, transparent);
  animation: shimmer 3s infinite;
}

.benefit-card:hover {
  border-color: #fab201;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(250, 178, 1, 0.1);
}

.benefit-icon {
  font-size: 32px;
  color: #fab201;
  margin-bottom: 16px;
}

.benefit-title {
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  margin-bottom: 8px;
}

.benefit-desc {
  color: #8b949e;
  font-size: 14px;
  line-height: 1.6;
}

/* Messages */
.alert {
  padding: 16px 20px;
  border-radius: 12px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 500;
  animation: slideInDown 0.4s ease;
}

.alert-success {
  background: rgba(34, 197, 94, 0.15);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #22c55e;
}

.alert-error {
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

/* Bottom Navigation - Mesmo do index.php */
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

/* Comissão Display */
.comissao-display {
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: white;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 14px;
  box-shadow: 0 2px 8px rgba(34, 197, 94, 0.3);
  display: flex;
  align-items: center;
  gap: 6px;
}

/* Performance Bar */
.performance-bar {
  background: #1a1d24;
  border-radius: 8px;
  height: 8px;
  overflow: hidden;
  margin-top: 8px;
}

.performance-fill {
  height: 100%;
  background: linear-gradient(90deg, #fab201, #f4c430);
  border-radius: 8px;
  transition: width 0.3s ease;
}

/* Materiais Grid */
.materials-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.material-card {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 20px;
  transition: all 0.3s ease;
}

.material-card:hover {
  border-color: #fab201;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(250, 178, 1, 0.1);
}

.material-preview {
  width: 100%;
  height: 120px;
  background: #1a1d24;
  border-radius: 8px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.material-preview img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.material-title {
  font-weight: 600;
  color: #ffffff;
  margin-bottom: 4px;
  font-size: 14px;
}

.material-desc {
  font-size: 12px;
  color: #8b949e;
  margin-bottom: 12px;
}

/* Responsivo */
@media (max-width: 768px) {
  .main-container {
    padding: 0 16px;
  }
  
  .affiliate-hero {
    margin: 16px auto 32px;
    padding: 24px 16px;
  }
  
  .affiliate-hero h1 {
    font-size: 24px;
  }
  
  .affiliate-hero p {
    font-size: 16px;
  }
  
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .link-container {
    flex-direction: column;
  }
  
  .header-content {
    padding: 0 4px;
  }
  
  .user-actions {
    gap: 8px;
  }
  
  .btn {
    padding: 8px 12px;
    font-size: 13px;
  }
}

/* Animações de entrada */
.stat-card {
  animation: slideInUp 0.6s ease forwards;
  opacity: 0;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
.stat-card:nth-child(5) { animation-delay: 0.5s; }
.stat-card:nth-child(6) { animation-delay: 0.6s; }

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

.benefit-card {
  animation: slideInUp 0.6s ease forwards;
  opacity: 0;
}

.benefit-card:nth-child(1) { animation-delay: 0.1s; }
.benefit-card:nth-child(2) { animation-delay: 0.2s; }
.benefit-card:nth-child(3) { animation-delay: 0.3s; }
.benefit-card:nth-child(4) { animation-delay: 0.4s; }

/* Empty State */
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

.empty-state h3 {
  color: #ffffff;
  margin-bottom: 8px;
}

.empty-state p {
  font-size: 14px;
  line-height: 1.6;
}
  </style>
</head>
<body>

<!-- Header - Mesmo do index.php -->
<div class="header">
  <div class="header-content">
    <div class="logo">
      <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
    </div>
    <div class="user-actions">
      <span class="saldo">R$ <?= number_format($usuario['saldo'], 2, ',', '.') ?></span>
      <?php if ($is_affiliate && $usuario['comissao'] > 0): ?>
        <span class="comissao-display">
          <i class="fas fa-percentage"></i>
          Comissão: R$ <?= number_format($usuario['comissao'], 2, ',', '.') ?>
        </span>
      <?php endif; ?>
      <button class="btn btn-primary" onclick='window.location.href="deposito.php"'>
        <i class="fas fa-plus"></i> Recarregar
      </button>
    </div>
  </div>
</div>

<div class="main-container">
  
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
      <i class="fas fa-check-circle"></i>
      <?= $_SESSION['success'] ?>
    </div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">
      <i class="fas fa-exclamation-circle"></i>
      <?= $_SESSION['error'] ?>
    </div>
    <?php unset($_SESSION['error']); ?>
  <?php endif; ?>

  <?php if (!$is_affiliate): ?>
    <!-- Seção para se tornar afiliado -->
    <div class="affiliate-hero">
      <h1>
        <i class="fas fa-handshake"></i>
        Torne-se um Influencer
      </h1>
      <p>Ganhe <span class="highlight">até 25% de comissão</span> indicando novos usuários para nossa plataforma!</p>
      <p>É simples, rápido e <span class="highlight">muito lucrativo</span>!</p>
      
      <form method="POST" style="margin-top: 24px;">
        <input type="hidden" name="become_affiliate" value="1">
        <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 16px 32px;">
          <i class="fas fa-rocket"></i>
          Quero ser Influencer Agora!
        </button>
      </form>
    </div>

    <!-- Benefícios -->
    <div class="content-section">
      <div class="section-header">
        <h2><i class="fas fa-star"></i> Por que ser nosso Influencer?</h2>
        <p>Veja todos os benefícios exclusivos que preparamos para você</p>
      </div>
      
      <div class="benefits-grid">
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-percentage"></i>
          </div>
          <div class="benefit-title">Comissões Altas</div>
          <div class="benefit-desc">Ganhe até 25% de comissão sobre o volume gerado pelos seus indicados</div>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="benefit-title">Relatórios em Tempo Real</div>
          <div class="benefit-desc">Acompanhe suas métricas e ganhos em tempo real com dashboards completos</div>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-money-bill-wave"></i>
          </div>
          <div class="benefit-title">Saques Rápidos via PIX</div>
          <div class="benefit-desc">Solicite seus saques via PIX de forma simples e receba rapidamente</div>
        </div>
        
        <div class="benefit-card">
          <div class="benefit-icon">
            <i class="fas fa-tools"></i>
          </div>
          <div class="benefit-title">Materiais Profissionais</div>
          <div class="benefit-desc">Acesso a banners e materiais promocionais profissionais para suas campanhas</div>
        </div>
      </div>
    </div>

  <?php else: ?>
    <!-- Dashboard do Afiliado -->
    <div class="affiliate-hero">
      <h1>
        <i class="fas fa-crown"></i>
        Painel do Influencer
      </h1>
      <p>Código: <span class="highlight"><?= $usuario['codigo_afiliado'] ?></span> • Comissão: <span class="highlight"><?= number_format($usuario['porcentagem_afiliado'], 1) ?>%</span></p>
      <p>Acompanhe sua performance e maximize seus ganhos!</p>
    </div>

    <!-- Estatísticas -->
    <div class="stats-section">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?= number_format($total_indicados) ?></div>
          <div class="stat-label">Indicados</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= number_format($total_clicks) ?></div>
          <div class="stat-label">Cliques</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= number_format($conversao, 1) ?>%</div>
          <div class="stat-label">Taxa Conversão</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">R$ <?= number_format($volume_gerado, 2, ',', '.') ?></div>
          <div class="stat-label">Volume Gerado</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" style="color: #f59e0b;">R$ <?= number_format($usuario['comissao'], 2, ',', '.') ?></div>
          <div class="stat-label">Comissão Pendente</div>
        </div>
        <div class="stat-card">
          <div class="stat-value" style="color: #22c55e;">R$ <?= number_format($usuario['saldo_comissao'], 2, ',', '.') ?></div>
          <div class="stat-label">Total Recebido</div>
        </div>
      </div>
    </div>

    <!-- Link de Afiliação -->
    <div class="content-section">
      <div class="section-header">
        <h2><i class="fas fa-link"></i> Seu Link de Influencer</h2>
        <p>Compartilhe este link para ganhar comissões</p>
      </div>
      
      <div class="affiliate-link-section">
        <div class="link-container">
          <input type="text" 
                 class="link-input" 
                 value="<?= $affiliate_link ?>" 
                 readonly 
                 id="affiliateLink">
          <button class="copy-btn" onclick="copyAffiliateLink()">
            <i class="fas fa-copy"></i>
            Copiar Link
          </button>
        </div>
        <div class="link-info">
          <i class="fas fa-info-circle"></i>
          Compartilhe este link nas suas redes sociais e ganhe comissões sobre os depósitos
        </div>
      </div>
    </div>

    <!-- Saque de Comissão -->
    <?php if ($usuario['comissao'] > 0): ?>
      <div class="content-section">
        <div class="section-header">
          <h2><i class="fas fa-money-bill-wave"></i> Sacar Comissões</h2>
          <p>Disponível para saque: <span style="color: #fab201; font-weight: 700;">R$ <?= number_format($usuario['comissao'], 2, ',', '.') ?></span></p>
        </div>
        
        <div class="withdrawal-form">
          <form method="POST">
            <input type="hidden" name="request_withdrawal" value="1">
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Valor do Saque (R$)</label>
                <input type="number" 
                       name="valor" 
                       class="form-input" 
                       step="0.01" 
                       min="10" 
                       max="<?= $usuario['comissao'] ?>"
                       placeholder="<?= number_format($usuario['comissao'], 2, ',', '.') ?>"
                       required>
              </div>
              <div class="form-group">
                <label class="form-label">Chave PIX</label>
                <input type="text" 
                       name="chave_pix" 
                       class="form-input" 
                       placeholder="Sua chave PIX"
                       required>
              </div>
              <div class="form-group">
                <label class="form-label">Tipo de Chave</label>
                <select name="tipo_chave" class="form-input" required>
                  <option value="email">Email</option>
                  <option value="cpf">CPF</option>
                  <option value="cnpj">CNPJ</option>
                  <option value="telefone">Telefone</option>
                  <option value="aleatoria">Chave Aleatória</option>
                </select>
              </div>
            </div>
            <button type="submit" class="btn btn-success" style="width: 100%; padding: 16px; font-size: 16px;">
              <i class="fas fa-paper-plane"></i>
              Solicitar Saque via PIX
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- Últimos Indicados -->
    <div class="content-section">
      <div class="section-header">
        <h2><i class="fas fa-users"></i> Seus Indicados</h2>
        <p>Usuários que se cadastraram através do seu link</p>
      </div>
      
      <?php if (empty($ultimos_indicados)): ?>
        <div class="empty-state">
          <i class="fas fa-user-plus"></i>
          <h3>Nenhum indicado ainda</h3>
          <p>Compartilhe seu link de afiliação para começar a ganhar!</p>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Usuário</th>
                <th>Email</th>
                <th>Data Cadastro</th>
                <th>Volume Gerado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ultimos_indicados as $indicado): ?>
                <tr>
                  <td style="color: #ffffff; font-weight: 600;">
                    <?= htmlspecialchars($indicado['nome'] ?: 'Usuário #' . $indicado['id']) ?>
                  </td>
                  <td><?= htmlspecialchars($indicado['email']) ?></td>
                  <td><?= date('d/m/Y H:i', strtotime($indicado['data_cadastro'])) ?></td>
                  <td style="color: #fab201; font-weight: 700;">
                    R$ <?= number_format($indicado['volume_individual'], 2, ',', '.') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Histórico de Comissões -->
    <div class="content-section">
      <div class="section-header">
        <h2><i class="fas fa-history"></i> Histórico de Comissões</h2>
        <p>Todas as comissões geradas pelos seus indicados</p>
      </div>
      
      <?php if (empty($historico_comissoes)): ?>
        <div class="empty-state">
          <i class="fas fa-chart-line"></i>
          <h3>Nenhuma comissão gerada ainda</h3>
          <p>Quando seus indicados fizerem depósitos, as comissões aparecerão aqui.</p>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Usuário</th>
                <th>Transação</th>
                <th>Comissão</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historico_comissoes as $comissao): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($comissao['data_criacao'])) ?></td>
                  <td style="color: #ffffff; font-weight: 600;">
                    <?= htmlspecialchars($comissao['indicado_nome'] ?: 'Usuário') ?>
                  </td>
                  <td style="color: #fab201; font-weight: 700;">
                    R$ <?= number_format($comissao['valor_transacao'], 2, ',', '.') ?>
                  </td>
                  <td style="color: #22c55e; font-weight: 700;">
                    R$ <?= number_format($comissao['valor_comissao'], 2, ',', '.') ?>
                    <div style="font-size: 11px; color: #8b949e;">
                      <?= number_format($comissao['porcentagem_aplicada'], 1) ?>%
                    </div>
                  </td>
                  <td>
                    <span class="status-badge status-<?= $comissao['status'] ?>">
                      <i class="fas fa-<?= $comissao['status'] === 'pago' ? 'check-circle' : 'clock' ?>"></i>
                      <?= ucfirst($comissao['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Histórico de Saques -->
    <?php if (!empty($historico_saques)): ?>
      <div class="content-section">
        <div class="section-header">
          <h2><i class="fas fa-money-bill-wave"></i> Histórico de Saques</h2>
          <p>Seus saques de comissão solicitados</p>
        </div>
        
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Data</th>
                <th>Valor</th>
                <th>Chave PIX</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historico_saques as $saque): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($saque['data'])) ?></td>
                  <td style="color: #22c55e; font-weight: 700;">
                    R$ <?= number_format($saque['valor'], 2, ',', '.') ?>
                  </td>
                  <td><?= htmlspecialchars($saque['chave_pix']) ?></td>
                  <td>
                    <span class="status-badge status-<?= $saque['status'] ?>">
                      <i class="fas fa-<?= $saque['status'] === 'aprovado' ? 'check-circle' : ($saque['status'] === 'pendente' ? 'clock' : 'times-circle') ?>"></i>
                      <?= ucfirst($saque['status']) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Materiais de Marketing -->
    <?php if (!empty($materiais)): ?>
      <div class="content-section">
        <div class="section-header">
          <h2><i class="fas fa-images"></i> Materiais para Divulgação</h2>
          <p>Use estes materiais para promover e aumentar suas conversões</p>
        </div>
        
        <div class="materials-grid">
          <?php foreach ($materiais as $material): ?>
            <div class="material-card">
              <div class="material-preview">
                <?php if ($material['arquivo'] && file_exists($material['arquivo'])): ?>
                  <img src="<?= htmlspecialchars($material['arquivo']) ?>" alt="<?= htmlspecialchars($material['titulo']) ?>">
                <?php else: ?>
                  <i class="fas fa-image" style="font-size: 32px; color: #8b949e;"></i>
                <?php endif; ?>
              </div>
              <div class="material-title"><?= htmlspecialchars($material['titulo']) ?></div>
              <div class="material-desc"><?= htmlspecialchars($material['descricao']) ?></div>
              <?php if ($material['dimensoes']): ?>
                <div style="font-size: 11px; color: #fab201; margin-bottom: 12px;">
                  <?= htmlspecialchars($material['dimensoes']) ?>
                </div>
              <?php endif; ?>
              <?php if ($material['arquivo']): ?>
                <a href="<?= htmlspecialchars($material['arquivo']) ?>" 
                   target="_blank" 
                   class="btn btn-primary" 
                   style="width: 100%; justify-content: center;">
                  <i class="fas fa-download"></i>
                  Download
                </a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<!-- Bottom Navigation - Mesmo do index.php -->
<div class="bottom-nav">
  <a href="index.php">
    <i class="fas fa-home"></i>
    <span>Início</span>
  </a>
  <a href="menu.php">
    <i class="fas fa-box"></i>
    <span>Pacotes</span>
  </a>
  <a href="deposito.php" class="deposit-btn">
    <i class="fas fa-credit-card"></i>
    <span>Depositar</span>
  </a>
  <a href="afiliados.php" class="active">
    <i class="fas fa-handshake"></i>
    <span>Afiliados</span>
  </a>
  <a href="perfil.php">
    <i class="fas fa-user"></i>
    <span>Perfil</span>
  </a>
</div>

<script>
function copyAffiliateLink() {
  const linkInput = document.getElementById('affiliateLink');
  linkInput.select();
  linkInput.setSelectionRange(0, 99999);
  
  navigator.clipboard.writeText(linkInput.value).then(() => {
    const btn = event.target.closest('.copy-btn');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
    btn.style.background = '#22c55e';
    
    setTimeout(() => {
      btn.innerHTML = originalHTML;
      btn.style.background = '';
    }, 2000);
  }).catch(() => {
    // Fallback para navegadores mais antigos
    linkInput.select();
    document.execCommand('copy');
    
    const btn = event.target.closest('.copy-btn');
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
    
    setTimeout(() => {
      btn.innerHTML = originalHTML;
    }, 2000);
  });
}

// Animações de entrada
document.addEventListener('DOMContentLoaded', function() {
  // Animar cards de estatísticas
  const statCards = document.querySelectorAll('.stat-card');
  statCards.forEach((card, index) => {
    setTimeout(() => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, index * 100);
  });
  
  // Animar cards de benefícios
  const benefitCards = document.querySelectorAll('.benefit-card');
  benefitCards.forEach((card, index) => {
    setTimeout(() => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, index * 150);
  });
});
</script>

</body>
</html>