<?php
$duracao = 60 * 60 * 24 * 30;
session_set_cookie_params(['lifetime'=>$duracao,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.gc_maxlifetime',$duracao);
session_start();
require 'db.php';
$usuarioLogado=false;$usuario=null;
if(isset($_SESSION['usuario_id'])){
  $usuarioLogado=true;
  $stmt=$pdo->prepare("SELECT nome, saldo FROM usuarios WHERE id=?");
  $stmt->execute([$_SESSION['usuario_id']]);
  $usuario=$stmt->fetch();
}
$jsonFile=__DIR__.'/imagens_menu.json';
$imagens=file_exists($jsonFile)?json_decode(file_get_contents($jsonFile),true):[];
$banner1=$imagens['banner1']??'banner.webp';
$dadosJson=file_exists('imagens_menu.json')?json_decode(file_get_contents('imagens_menu.json'),true):[];
$logo=$dadosJson['logo']??'logo.png';
if($usuarioLogado){
  $stmt=$pdo->prepare("SELECT nome, saldo FROM usuarios WHERE id=?");
  $stmt->execute([$_SESSION['usuario_id']]);
  $usuario=$stmt->fetch();
}

// Ganhadores fake para credibilidade
$ganhadores = [
  ['nome' => 'Carlos M.', 'produto' => 'iPhone 15 Pro', 'imagem' => 'https://images.pexels.com/photos/607812/pexels-photo-607812.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 8.500', 'tempo' => '2 min atrás'],
  ['nome' => 'Ana P.', 'produto' => 'MacBook Air', 'imagem' => 'https://images.pexels.com/photos/18105/pexels-photo.jpg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 12.200', 'tempo' => '5 min atrás'],
  ['nome' => 'João S.', 'produto' => 'PlayStation 5', 'imagem' => 'https://images.pexels.com/photos/9072316/pexels-photo-9072316.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 4.500', 'tempo' => '8 min atrás'],
  ['nome' => 'Maria L.', 'produto' => 'Samsung Galaxy', 'imagem' => 'https://images.pexels.com/photos/1092644/pexels-photo-1092644.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 3.800', 'tempo' => '12 min atrás'],
  ['nome' => 'Pedro R.', 'produto' => 'AirPods Pro', 'imagem' => 'https://images.pexels.com/photos/3780681/pexels-photo-3780681.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 2.100', 'tempo' => '15 min atrás'],
  ['nome' => 'Lucia F.', 'produto' => 'Nintendo Switch', 'imagem' => 'https://images.pexels.com/photos/1298601/pexels-photo-1298601.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 2.800', 'tempo' => '18 min atrás']
];

// Buscar produtos reais das roletas para ganhadores
$stmt = $pdo->query("SELECT premios_json FROM raspadinhas_config WHERE ativa = 1");
$todasRoletas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$produtosReais = [];
foreach($todasRoletas as $roleta) {
  $premios = json_decode($roleta['premios_json'] ?? '[]', true);
  if(is_array($premios)) {
    foreach($premios as $premio) {
      if(isset($premio['nome']) && isset($premio['valor']) && isset($premio['imagem']) && floatval($premio['valor']) > 0) {
        $produtosReais[] = [
          'nome' => $premio['nome'],
          'valor' => floatval($premio['valor']),
          'imagem' => $premio['imagem']
        ];
      }
    }
  }
}

// Gerar ganhadores com produtos reais
$nomesGanhadores = [
  'Carlos M.', 'Ana P.', 'João S.', 'Maria L.', 'Pedro R.', 'Lucia F.', 'Bruno C.', 'Camila S.', 
  'Rafael L.', 'Juliana R.', 'Roberto F.', 'Fernanda C.', 'Diego A.', 'Patricia M.', 'Gustavo L.',
  'Mariana S.', 'Felipe R.', 'Carla B.', 'Thiago P.', 'Vanessa T.', 'Leonardo D.', 'Isabela G.',
  'Rodrigo N.', 'Amanda K.', 'Marcelo V.', 'Priscila H.', 'Eduardo J.', 'Tatiana W.', 'Vinicius Q.', 'Larissa Z.'
];

$ganhadores = [];
if(!empty($produtosReais)) {
  // Filtrar produtos entre R$ 30 e R$ 3.000
  $produtosAltos = array_filter($produtosReais, function($produto) {
    return $produto['valor'] >= 30 && $produto['valor'] <= 3000;
  });
  
  if(!empty($produtosAltos)) {
  for($i = 0; $i < 8; $i++) {
      $produto = $produtosAltos[array_rand($produtosAltos)];
    $ganhadores[] = [
      'nome' => $nomesGanhadores[array_rand($nomesGanhadores)],
      'produto' => $produto['nome'],
      'produto_imagem' => $produto['imagem'],
      'valor' => 'R$ ' . number_format($produto['valor'], 2, ',', '.'),
      'tempo' => rand(1, 30) . ' min atrás'
    ];
  }
  } else {
    // Fallback caso não tenha produtos na faixa de R$ 30 a R$ 3.000
    $ganhadores = [
      ['nome' => 'Carlos M.', 'produto' => 'iPhone 15 Pro', 'produto_imagem' => 'https://images.pexels.com/photos/607812/pexels-photo-607812.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 8.500', 'tempo' => '2 min atrás'],
      ['nome' => 'Ana P.', 'produto' => 'MacBook Air', 'produto_imagem' => 'https://images.pexels.com/photos/18105/pexels-photo.jpg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 12.200', 'tempo' => '5 min atrás']
    ];
  }
} else {
  // Fallback caso não tenha produtos
  $ganhadores = [
    ['nome' => 'Carlos M.', 'produto' => 'iPhone 15 Pro', 'produto_imagem' => 'https://images.pexels.com/photos/607812/pexels-photo-607812.jpeg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 8.500', 'tempo' => '2 min atrás'],
    ['nome' => 'Ana P.', 'produto' => 'MacBook Air', 'produto_imagem' => 'https://images.pexels.com/photos/18105/pexels-photo.jpg?auto=compress&cs=tinysrgb&w=300&h=300&fit=crop', 'valor' => 'R$ 12.200', 'tempo' => '5 min atrás']
  ];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Início - Caixas</title>
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

/* Container principal */
.main-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* Banner Principal */
.hero-banner {
  margin: 24px auto;
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.hero-banner img {
  width: 100%;
  height: 280px;
  object-fit: cover;
  display: block;
}

.hero-banner::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(45deg, rgba(250, 178, 1, 0.1), transparent);
  pointer-events: none;
}

/* Últimos Ganhadores */
.winners-section {
  margin: 40px auto;
  background: #111318;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #1a1d24;
}

.winners-header {
  text-align: center;
  margin-bottom: 24px;
}

.winners-header h2 {
  color: #fab201;
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.winners-header p {
  color: #8b949e;
  font-size: 14px;
}

.winners-grid {
  display: flex;
  overflow-x: auto;
  gap: 16px;
  padding: 8px 0;
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.winners-grid::-webkit-scrollbar {
  display: none;
}

.winner-card {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 16px;
  min-width: 200px;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
  text-align: center;
  transition: all 0.3s ease;
}

.winner-card::before {
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

.winner-card:hover {
  border-color: #fab201;
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(250, 178, 1, 0.2);
}

.winner-product-image {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: 12px;
  margin: 0 auto 12px;
  display: block;
  border: 2px solid #fab201;
}

.winner-details h4 {
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
  color: #ffffff;
}

.winner-details .product-name {
  font-size: 12px;
  color: #8b949e;
  margin-bottom: 8px;
}

.winner-details small {
  color: #8b949e;
  font-size: 10px;
}

.winner-prize {
  color: #fab201;
  font-weight: 700;
  font-size: 16px;
  margin-top: 8px;
}
.winners-section {
  margin: 40px auto;
  background: #111318;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #1a1d24;
  overflow: hidden;
}

.winners-header {
  text-align: center;
  margin-bottom: 24px;
}

.winners-header h2 {
  color: #fab201;
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.winners-header p {
  color: #8b949e;
  font-size: 14px;
}

.winners-carousel {
  position: relative;
  overflow: hidden;
}

.winners-track {
  display: flex;
  gap: 16px;
  animation: scroll-winners 30s linear infinite;
  width: fit-content;
}

.winners-track:hover {
  animation-play-state: paused;
}

@keyframes scroll-winners {
  0% { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

.winner-card {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 12px;
  min-width: 200px;
  flex-shrink: 0;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.winner-card::before {
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

.winner-card:hover {
  border-color: #fab201;
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(250, 178, 1, 0.2);
}

.winner-name {
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
}

.winner-action {
  color: #8b949e;
  font-size: 12px;
  margin-bottom: 8px;
}

.winner-product {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.product-icon {
  width: 24px;
  height: 24px;
  border-radius: 4px;
  object-fit: cover;
  border: 1px solid #fab201;
}

.product-name {
  color: #ffffff;
  font-size: 12px;
  font-weight: 500;
}

.winner-value {
  background: linear-gradient(135deg, #fab201, #f4c430);
  color: #000;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
}

.winner-time {
  color: #8b949e;
  font-size: 12px;
  text-align: center;
  margin-top: 4px;
}

/* Duplicar cards para scroll infinito */
.winners-track::after {
  content: '';
  flex-shrink: 0;
  width: 16px;
}

/* Responsivo */
@media (max-width: 768px) {
  .winners-track {
    gap: 12px;
  }
  
  .winner-card {
    min-width: 180px;
    padding: 10px;
  }
  
  .winner-name {
    font-size: 13px;
  }
  
  .winner-action {
    font-size: 11px;
  }
  
  .product-name {
    font-size: 11px;
  }
  
  .winner-value {
    font-size: 11px;
    padding: 3px 6px;
  }
  
  .winner-time {
    font-size: 11px;
  }
}

/* Animações de entrada */
.winner-card {
  animation: slideInUp 0.6s ease forwards;
  opacity: 0;
}

.winner-card:nth-child(1) { animation-delay: 0.1s; }
.winner-card:nth-child(2) { animation-delay: 0.2s; }
.winner-card:nth-child(3) { animation-delay: 0.3s; }
.winner-card:nth-child(4) { animation-delay: 0.4s; }
.winner-card:nth-child(5) { animation-delay: 0.5s; }
.winner-card:nth-child(6) { animation-delay: 0.6s; }
.winner-card:nth-child(7) { animation-delay: 0.7s; }
.winner-card:nth-child(8) { animation-delay: 0.8s; }

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

/* Seção de Pacotes */
.packages-section {
  margin: 40px auto;
}

.section-header {
  margin-bottom: 24px;
}

.section-header h2 {
  color: #ffffff;
  font-size: 28px;
  font-weight: 800;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-header p {
  color: #8b949e;
  font-size: 16px;
}

.packages-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.package-card {
  background: #111318;
  border-radius: 16px;
  overflow: hidden;
  transition: all 0.3s ease;
  position: relative;
  border: 2px solid transparent;
  background-clip: padding-box;
}

.package-card::before {
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

.package-card:hover::before {
  opacity: 1;
}

.package-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(250, 178, 1, 0.2);
}

.package-image {
  position: relative;
  height: 200px;
  overflow: hidden;

  display: flex;               /* ativa flexbox */
  justify-content: center;     /* centraliza horizontal */
  align-items: center;         /* centraliza vertical */
}

.package-image img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain; /* mantém proporção sem cortar */
}


.package-card:hover .package-image img {
  transform: scale(1.05);
}

.hot-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: linear-gradient(135deg, #ff4444, #ff6600);
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  gap: 4px;
  box-shadow: 0 2px 8px rgba(255, 68, 68, 0.4);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

.package-price {
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

.package-info {
  padding: 20px;
  text-align: center;
}

.package-title {
  font-weight: 700;
  color: #ffffff;
  font-size: 16px;
  margin-bottom: 4px;
}

.package-subtitle {
  color: #8b949e;
  font-size: 12px;
}

/* Como funciona */
.how-it-works {
  margin: 60px auto;
  background: #111318;
  border-radius: 16px;
  padding: 40px 24px;
  border: 1px solid #1a1d24;
}

.how-it-works h2 {
  color: #fab201;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.how-it-works h3 {
  color: #ffffff;
  font-size: 32px;
  font-weight: 800;
  text-align: center;
  margin-bottom: 12px;
}

.how-it-works > p {
  text-align: center;
  color: #8b949e;
  font-size: 16px;
  margin-bottom: 32px;
}

.steps-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 24px;
}

.step-card {
  background: #0d1117;
  border: 1px solid #21262d;
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
}

.step-card:hover {
  border-color: #fab201;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(250, 178, 1, 0.1);
}

.step-number {
  background: linear-gradient(135deg, #fab201, #f4c430);
  color: #000;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 18px;
  margin: 0 auto 16px;
}

.step-card h4 {
  color: #ffffff;
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}

.step-card p {
  color: #8b949e;
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 16px;
}

.step-image {
  border-radius: 8px;
  overflow: hidden;
  background: #0a0b0f;
}

.step-image img {
  width: 100%;
  height: auto;
  display: block;
}

/* Footer */
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

/* Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  justify-content: center;
  align-items: center;
  animation: modalFadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal.show {
  display: flex;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
    backdrop-filter: blur(0px);
  }
  to {
    opacity: 1;
    backdrop-filter: blur(20px);
  }
}

.modal-content {
  background: linear-gradient(145deg, rgba(15, 20, 25, 0.98), rgba(20, 25, 35, 0.95));
  backdrop-filter: blur(40px);
  -webkit-backdrop-filter: blur(40px);
  border: 1px solid rgba(250, 178, 1, 0.1);
  border-radius: 24px;
  width: 100%;
  max-width: 380px;
  padding: 24px 20px;
  box-shadow: 
    0 32px 64px rgba(0, 0, 0, 0.9),
    0 0 0 1px rgba(250, 178, 1, 0.05),
    inset 0 1px 0 rgba(255, 255, 255, 0.05);
  position: relative;
  overflow: hidden;
  margin: 16px;
  animation: modalSlideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(40px) scale(0.9);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.modal-content::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, #fab201, transparent);
  opacity: 0.6;
}


.modal .close {
  position: absolute;
  top: 20px;
  right: 24px;
  font-size: 20px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.modal .close:hover {
  color: #fab201;
  background: rgba(250, 178, 1, 0.1);
  border-color: rgba(250, 178, 1, 0.2);
  transform: rotate(90deg) scale(1.1);
  box-shadow: 0 4px 12px rgba(250, 178, 1, 0.2);
}

/* Logo no modal */
.modal-logo {
  text-align: center;
  margin-bottom: 20px;
  position: relative;
}

.modal-logo img {
  height: 40px;
  filter: brightness(1.2) drop-shadow(0 4px 12px rgba(250, 178, 1, 0.3));
  transition: transform 0.3s ease;
}

.modal-logo img:hover {
  transform: scale(1.05);
}

/* Títulos do modal */
.modal-title {
  text-align: center;
  margin-bottom: 8px;
}

.modal-title h2 {
  color: #ffffff;
  font-size: 24px;
  font-weight: 900;
  margin-bottom: 8px;
  background: linear-gradient(135deg, #ffffff, #f1f5f9);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  letter-spacing: -0.5px;
}

.modal-subtitle {
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
  margin-bottom: 24px;
  line-height: 1.6;
  font-weight: 400;
}

/* Navegação entre forms */
.form-switch {
  text-align: center;
  margin-top: 20px;
}

.form-switch p {
  color: #64748b;
  font-size: 14px;
  margin: 8px 0;
  font-weight: 500;
}

.form-switch p:first-child {
  color: #475569;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin: 16px 0 8px;
  position: relative;
}

.form-switch p:first-child::before,
.form-switch p:first-child::after {
  content: '';
  position: absolute;
  top: 50%;
  width: 40px;
  height: 1px;
  background: linear-gradient(90deg, transparent, #374151, transparent);
}

.form-switch p:first-child::before {
  left: -50px;
}

.form-switch p:first-child::after {
  right: -50px;
}

.switch-btn {
  background: transparent;
  border: 2px solid rgba(250, 178, 1, 0.3);
  color: #fab201;
  padding: 12px 24px;
  border-radius: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 14px;
  margin-top: 8px;
  position: relative;
  overflow: hidden;
}

.switch-btn::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(250, 178, 1, 0.1), transparent);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.switch-btn:hover {
  border-color: #fab201;
  background: rgba(250, 178, 1, 0.05);
  transform: translateY(-1px);
  box-shadow: 0 8px 25px rgba(250, 178, 1, 0.15);
}

.switch-btn:hover::before {
  opacity: 1;
}

.input-group {
  position: relative;
  margin-bottom: 16px;
}

.input-label {
  display: block;
  color: #e2e8f0;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 6px;
  letter-spacing: 0.2px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.input-label i {
  color: #64748b;
  font-size: 16px;
}

.input-group input {
  width: 100%;
  padding: 14px 16px;
  border-radius: 16px;
  border: 2px solid rgba(255, 255, 255, 0.08);
  background: rgba(15, 23, 42, 0.6);
  color: #ffffff;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.input-group input:focus {
  border-color: rgba(250, 178, 1, 0.6);
  outline: none;
  box-shadow: 
    0 0 0 4px rgba(250, 178, 1, 0.1),
    0 8px 25px rgba(250, 178, 1, 0.15),
    inset 0 1px 0 rgba(255, 255, 255, 0.1);
  background: rgba(15, 23, 42, 0.8);
  transform: translateY(-1px);
}

.input-group input::placeholder {
  color: #64748b;
  font-weight: 500;
}

/* Indicador de força da senha */
.password-strength {
  margin-top: 8px;
  display: flex;
  gap: 4px;
}

.strength-bar {
  height: 2px;
  flex: 1;
  background: rgba(255, 255, 255, 0.08);
  border-radius: 2px;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.strength-bar.active {
  background: linear-gradient(90deg, #fab201, #f59e0b);
  box-shadow: 0 0 8px rgba(250, 178, 1, 0.4);
}

.strength-bar.active::after {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
  animation: strengthShimmer 1.5s ease-in-out;
}

@keyframes strengthShimmer {
  0% { left: -100%; }
  100% { left: 100%; }
}

.password-hint {
  color: #64748b;
  font-size: 11px;
  margin-top: 6px;
  font-weight: 500;
}

/* Botão de mostrar/ocultar senha */
.password-toggle {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #64748b;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  padding: 6px;
  border-radius: 8px;
}

.password-toggle:hover {
  color: #fab201;
  background: rgba(250, 178, 1, 0.1);
  transform: translateY(-50%) scale(1.1);
}

.btn-full {
  width: 100%;
  padding: 16px 24px;
  border-radius: 16px;
  font-size: 15px;
  font-weight: 700;
  margin-top: 20px;
  border: none;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  background: linear-gradient(135deg, #fab201, #f59e0b);
  color: #000;
  box-shadow: 
    0 8px 25px rgba(250, 178, 1, 0.4),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
  position: relative;
  overflow: hidden;
}

.btn-full::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), transparent);
  opacity: 0;
  transition: opacity 0.3s ease;
}

.btn-primary.btn-full:hover {
  transform: translateY(-3px);
  box-shadow: 
    0 12px 35px rgba(250, 178, 1, 0.5),
    inset 0 1px 0 rgba(255, 255, 255, 0.3);
  background: linear-gradient(135deg, #fcd34d, #fab201);
}

.btn-primary.btn-full:hover::before {
  opacity: 1;
}

.btn-primary.btn-full:active {
  transform: translateY(-1px);
  box-shadow: 
    0 4px 15px rgba(250, 178, 1, 0.4),
    inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

#auth-msg {
  margin-top: 16px;
  padding: 12px 16px;
  border-radius: 12px;
  font-weight: 500;
  font-size: 13px;
  text-align: center;
  border: 1px solid rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.1);
  color: #fca5a5;
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  display: none;
}

#auth-msg.success {
  color: #86efac;
  background: rgba(34, 197, 94, 0.1);
  border-color: rgba(34, 197, 94, 0.3);
}

/* Termos de uso */
.terms-text {
  text-align: center;
  font-size: 11px;
  color: #64748b;
  margin-top: 16px;
  line-height: 1.5;
  font-weight: 400;
}

.terms-text a {
  color: #fab201;
  text-decoration: none;
  font-weight: 500;
  transition: all 0.3s ease;
}

.terms-text a:hover {
  color: #fcd34d;
  text-shadow: 0 0 8px rgba(250, 178, 1, 0.3);
}

/* Confirmação */
.confirm-modal {
  display: none;
  position: fixed;
  z-index: 9999;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  justify-content: center;
  align-items: center;
  animation: modalFadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.confirm-modal.show {
  display: flex;
}

.confirm-box {
  width: min(92vw, 480px);
  background: linear-gradient(145deg, #0f1419, #1a1f2e);
  border: 1px solid rgba(250, 178, 1, 0.2);
  border-radius: 16px;
  padding: 40px 32px;
  color: #ffffff;
  text-align: center;
  box-shadow: 0 25px 80px rgba(0, 0, 0, 0.8), 0 0 40px rgba(250, 178, 1, 0.1);
  position: relative;
  overflow: hidden;
}

.confirm-box::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 2px;
  background: linear-gradient(90deg, transparent, #fab201, transparent);
  animation: shimmer 3s infinite;
}

.confirm-box h4 {
  margin: 0 0 12px;
  color: #ffffff;
  font-size: 22px;
  font-weight: 800;
}

.confirm-box p {
  margin: 0 0 24px;
  color: #9ca3af;
  line-height: 1.6;
  font-size: 15px;
}

.confirm-actions {
  display: flex;
  gap: 12px;
  flex-direction: column;
}

@media (min-width: 520px) {
  .confirm-actions {
    flex-direction: row;
  }
}

.btn-ghost {
  background: rgba(13, 17, 23, 0.8);
  border: 2px solid rgba(107, 114, 128, 0.3);
  color: #9ca3af;
  transition: all 0.3s ease;
}

.btn-ghost:hover {
  background: rgba(26, 29, 36, 0.9);
  border-color: rgba(250, 178, 1, 0.5);
  color: #ffffff;
  transform: translateY(-1px);
}

/* Efeitos adicionais para inputs focados */
.input-group.focused .icon {
  color: #fab201;
  transform: translateY(-50%) scale(1.1);
}

.input-group.focused input {
  border-color: #fab201;
  box-shadow: 0 0 0 4px rgba(250, 178, 1, 0.15);
  background: rgba(13, 17, 23, 0.95);
}

/* Animação de loading */
.btn-primary.btn-full:disabled {
  opacity: 0.8;
  cursor: not-allowed;
  transform: none;
}

.btn-primary.btn-full:disabled:hover {
  transform: none;
  box-shadow: 0 4px 20px rgba(250, 178, 1, 0.4);
}

/* Melhorias no shimmer */
@keyframes shimmer {
  0% { 
    left: -100%; 
    opacity: 0;
  }
  50% { 
    opacity: 1;
  }
  100% { 
    left: 100%; 
    opacity: 0;
  }
}

/* Responsivo */
@media (max-width: 768px) {
  .modal-content {
    margin: 12px;
    padding: 20px 16px;
    max-width: 340px;
  }
  
  .tabs button {
    padding: 12px 14px;
    font-size: 13px;
  }
  
  .input-group input {
    padding: 12px 14px;
    font-size: 13px;
  }
  
  .btn-full {
    padding: 12px 16px;
    font-size: 13px;
  }
  
  .modal-title h2 {
    font-size: 20px;
  }
  
  .modal-subtitle {
    font-size: 12px;
  }
}

@media (max-width: 768px) {
  .main-container {
    padding: 0 16px;
  }
  
  .hero-banner {
    margin: 16px auto 32px;
  }
  
  .hero-banner img {
    height: 200px;
  }
  
  .packages-grid {
    grid-template-columns: 1fr;
  }
  
  .steps-grid {
    grid-template-columns: 1fr;
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
  
  .winner-card {
    min-width: 180px;
  }
  
  .winner-product-image {
    width: 60px;
    height: 60px;
  }
}

/* Animações de entrada */
.package-card {
  animation: slideInUp 0.6s ease forwards;
  opacity: 0;
}

.package-card:nth-child(1) { animation-delay: 0.1s; }
.package-card:nth-child(2) { animation-delay: 0.2s; }
.package-card:nth-child(3) { animation-delay: 0.3s; }
.package-card:nth-child(4) { animation-delay: 0.4s; }
.package-card:nth-child(5) { animation-delay: 0.5s; }

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

.winner-card {
  animation: slideInLeft 0.6s ease forwards;
  opacity: 0;
}

.winner-card:nth-child(1) { animation-delay: 0.1s; }
.winner-card:nth-child(2) { animation-delay: 0.2s; }
.winner-card:nth-child(3) { animation-delay: 0.3s; }
.winner-card:nth-child(4) { animation-delay: 0.4s; }
.winner-card:nth-child(5) { animation-delay: 0.5s; }
.winner-card:nth-child(6) { animation-delay: 0.6s; }

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
.winner-item {
  animation: slideInUp 0.4s ease forwards;
  opacity: 0;
}

.winner-item:nth-child(1) { animation-delay: 0.1s; }
.winner-item:nth-child(2) { animation-delay: 0.2s; }
.winner-item:nth-child(3) { animation-delay: 0.3s; }
.winner-item:nth-child(4) { animation-delay: 0.4s; }
.winner-item:nth-child(5) { animation-delay: 0.5s; }
.winner-item:nth-child(6) { animation-delay: 0.6s; }
  </style>
</head>
<body>
<script>
const urlParams=new URLSearchParams(window.location.search);
const codigoConvite=urlParams.get('codigo');
if(codigoConvite){localStorage.setItem('codigo_convite',codigoConvite);}
</script>

<!-- Header -->
<div class="header">
  <div class="header-content">
    <div class="logo">
      <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
    </div>
    <div class="user-actions">
      <?php if($usuarioLogado):?>
        <span class="saldo">R$ <?= number_format($usuario['saldo'],2,',','.') ?></span>
        <button class="btn btn-primary btn-depositar" onclick='window.location.href="deposito.php"'>
          <i class="fas fa-plus"></i> Recarregar
        </button>
      <?php else:?>
        <button class="btn btn-secondary" id="btn-open-login">
          <i class="fas fa-sign-in-alt"></i> Entrar
        </button>
        <button class="btn btn-primary" id="btn-open-register">
          <i class="fas fa-user-plus"></i> Cadastre-se
        </button>
      <?php endif;?>
    </div>
  </div>
</div>

<div class="main-container">
  <!-- Banner Principal -->
  <div class="hero-banner">
    <a href="/menu">
      <img src="images/<?= $banner1 ?>?v=<?= filemtime("images/$banner1") ?>" alt="Banner Principal">
    </a>
  </div>

  <!-- Últimos Ganhadores -->
  <div class="winners-section">
    <div class="winners-header">
      <h2><i class="fas fa-trophy"></i> Ganhadores Recentes</h2>
    </div>
    <div class="winners-carousel">
      <div class="winners-track">
        <?php 
        // Duplicar ganhadores para scroll infinito
        $ganhadoresDuplicados = array_merge($ganhadores, $ganhadores);
        foreach($ganhadoresDuplicados as $ganhador): 
        ?>
          <div class="winner-card">
            <div class="winner-name">
              <?= $ganhador['nome'] ?>
            </div>
            <div class="winner-action">Abriu caixa</div>
            <div class="winner-product">
              <img src="<?= $ganhador['produto_imagem'] ?>" alt="<?= $ganhador['produto'] ?>" class="product-icon">
              <span class="product-name"><?= $ganhador['produto'] ?></span>
            </div>
            <div class="winner-value"><?= $ganhador['valor'] ?></div>
            <div class="winner-time"><?= $ganhador['tempo'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Pacotes Premium -->
  <div class="packages-section">
    <div class="section-header">
      <h2><i class="fas fa-gift"></i> CAIXAS PREMIOS</h2>
      <p>Escolha sua caixa e tenha a chance de ganhar prêmios incríveis</p>
    </div>
    <div class="packages-grid">
      <?php
        $imagensMenu=file_exists('imagens_menu.json')?json_decode(file_get_contents('imagens_menu.json'),true):[];
        
        // Buscar caixinhas do banco de dados
        $stmt = $pdo->query("SELECT id, nome, valor, ativa FROM raspadinhas_config WHERE ativa = 1 ORDER BY id ASC");
        $raspadinhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar as caixinhas para exibição
        $caixinhas_exibicao = [];
        foreach($raspadinhas as $index => $rasp) {
          $id = $rasp['id'];
          $nome = $rasp['nome'] ?? "Caixinha $id";
          $valor = floatval($rasp['valor'] ?? 0);
          
          // Usar fallback para imagens_menu.json
          $imagem_final = '';
          $menu_key = "menu" . ($index + 1);
          if (isset($imagensMenu[$menu_key])) {
            $imagem_final = "images/" . $imagensMenu[$menu_key] . "?v=" . filemtime("images/" . $imagensMenu[$menu_key]);
          } else {
            $imagem_final = "images/menu" . ($index + 1) . ".png";
          }
          
          // Determinar se é "hot" baseado no valor (valores maiores são hot)
          $hot = $valor >= 15;
          
          $caixinhas_exibicao[] = [
            "id" => $id,
            "nome" => $nome,
            "imagem" => $imagem_final,
            "valor" => "R$ " . number_format($valor, 2, ',', '.'),
            "hot" => $hot
          ];
        }
        
        foreach($raspadinhas as $r){
          // Determinar imagem usando o sistema existente
          $menu_key = "menu" . array_search($r, $raspadinhas) + 1;
          $imagem_final = '';
          if (isset($imagensMenu[$menu_key])) {
            $imagem_final = "images/" . $imagensMenu[$menu_key] . "?v=" . filemtime("images/" . $imagensMenu[$menu_key]);
          } else {
            $imagem_final = "images/menu" . (array_search($r, $raspadinhas) + 1) . ".png";
          }
          
          // Determinar se é "hot"
          $hot = floatval($r['valor']) >= 15;
          $valor_formatado = "R$ " . number_format(floatval($r['valor']), 2, ',', '.');
          
          echo '
          <div class="package-card">
            <a href="roleta.php?id='.$r['id'].'" style="text-decoration:none;color:inherit;">
              <div class="package-image">
                '.($hot ? '<div class="hot-badge"><i class="fas fa-fire"></i> HOT</div>' : '').'
                <img src="'.$imagem_final.'" alt="Pacote">
                <span class="package-price">'.$valor_formatado.'</span>
              </div>
              <div class="package-info">
                <div class="package-title">'.strtoupper($r['nome'] ?? 'CAIXINHA '.$r['id']).'</div>
                <div class="package-subtitle">Clique para abrir</div>
              </div>
            </a>
          </div>';
        }
      ?>
    </div>
  </div>

  <!-- Como Funciona -->
  <div class="how-it-works">
    <h2>Como funciona</h2>
    <h3>É MUITO SIMPLES</h3>
    <p>Abrir um pacote é bem fácil! Veja o passo a passo para começar agora</p>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-number">1</div>
        <h4><i class="fas fa-wallet"></i> Deposite</h4>
        <p>Clique no botão amarelo no canto superior do site e escolha a quantia ideal para fazer seu depósito.</p>
        <div class="step-image"><img src="images/h1.webp" alt="Deposite"></div>
      </div>
      <div class="step-card">
        <div class="step-number">2</div>
        <h4><i class="fas fa-gift"></i> Escolha um Pacote</h4>
        <p>Encontre o pacote ou uma raspadinha perfeita para você e clique em abrir.</p>
        <div class="step-image"><img src="images/h2.webp" alt="Escolha um pacote"></div>
      </div>
      <div class="step-card">
        <div class="step-number">3</div>
        <h4><i class="fas fa-mouse-pointer"></i> Clique em abrir</h4>
        <p>Após escolher sua premiação desejada, clique em abrir.</p>
        <div class="step-image"><img src="images/h3.webp" alt="Clique em abrir"></div>
      </div>
      <div class="step-card">
        <div class="step-number">4</div>
        <h4><i class="fas fa-trophy"></i> Aproveite!</h4>
        <p>Parabéns! Agora você pode retirar o valor no PIX ou enviar o produto para sua casa.</p>
        <div class="step-image"><img src="images/h4.webp" alt="Aproveite!"></div>
      </div>
    </div>
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
  <a href="index" class="active">
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

<?php if(!$usuarioLogado):?>
<!-- Modal de Autenticação -->
<div id="modal-auth" class="modal">
  <div class="modal-content">
    <span class="close" onclick="fecharModal()">&times;</span>
    
    <div class="modal-logo">
      <img src="images/<?= $logo ?>?v=<?= time() ?>" alt="Logo">
    </div>
    
    <div id="form-login" class="tab-content">
      <div class="modal-title">
        <h2>Bem-vindo de volta!</h2>
      </div>
      <p class="modal-subtitle">Entre na sua conta e continue sua jornada</p>
      
      <form id="loginForm">
        <div class="input-group">
          <label class="input-label">Email</label>
          <input type="email" name="email" placeholder="Seu e-mail" required>
        </div>
        <div class="input-group">
          <label class="input-label">Senha</label>
          <input type="password" name="senha" placeholder="••••••••" required>
          <button type="button" class="password-toggle" onclick="togglePassword(this)">
            <i class="fas fa-eye"></i>
          </button>
        </div>
        <button type="submit" class="btn btn-primary btn-full">
          Entrar na Conta →
        </button>
      </form>
      
      <div class="form-switch">
        <p>OU</p>
        <p>Ainda não tem uma conta?</p>
        <button type="button" class="switch-btn" onclick="mostrarTab('register')">
          Criar Nova Conta →
        </button>
      </div>
    </div>
    
    <div id="form-register" class="tab-content" style="display:none;">
      <div class="modal-title">
        <h2>Crie sua conta!</h2>
      </div>
      <p class="modal-subtitle">Junte-se a nós e comece a ganhar prêmios incríveis</p>
      
      <form id="registerForm">
        <div class="input-group">
          <label class="input-label">Nome Completo</label>
          <input type="text" name="nome" placeholder="Nome completo" required>
        </div>
        <div class="input-group">
          <label class="input-label">Email</label>
          <input type="email" name="email" placeholder="Seu e-mail" required>
        </div>
        <div class="input-group">
          <label class="input-label">Crie uma senha</label>
          <input type="password" name="senha" placeholder="••••••••" required>
          <button type="button" class="password-toggle" onclick="togglePassword(this)">
            <i class="fas fa-eye"></i>
          </button>
          <div class="password-strength">
            <div class="strength-bar"></div>
            <div class="strength-bar"></div>
            <div class="strength-bar"></div>
            <div class="strength-bar"></div>
          </div>
          <p class="password-hint">Sua senha deve ter pelo menos 6 caracteres</p>
        </div>
        <button type="submit" class="btn btn-primary btn-full">
          Criar Minha Conta →
        </button>
      </form>
      
      <p class="terms-text">
        Ao criar uma conta, você concorda com nossos 
        <a href="#" onclick="alert('Termos de Uso')">Termos de Uso</a> 
        e <a href="#" onclick="alert('Política de Privacidade')">Política de Privacidade</a>
      </p>
      
      <div class="form-switch">
        <p>OU</p>
        <p>Já tem uma conta?</p>
        <button type="button" class="switch-btn" onclick="mostrarTab('login')">
          Fazer Login →
        </button>
      </div>
    </div>
    
    <div id="auth-msg"></div>
  </div>
</div>

<!-- Modal de Confirmação -->
<div id="confirm-cancel" class="confirm-modal">
  <div class="confirm-box">
    <h4>Tem certeza que deseja cancelar seu registro?</h4>
    <p>Cadastre-se agora e tenha a chance de ganhar bônus e rodadas grátis!</p>
    <div class="confirm-actions">
      <button id="btn-retomar-cadastro" class="btn btn-primary">
        <i class="fas fa-arrow-right"></i> Continuar
      </button>
      <button id="btn-cancelar-cadastro" class="btn btn-ghost">
        <i class="fas fa-times"></i> Sim, quero cancelar
      </button>
    </div>
  </div>
</div>
<?php endif;?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(function(){
  // Indicador de força da senha
  $('#registerForm input[name="senha"]').on('input', function() {
    updatePasswordStrength($(this).val());
  });
  
  // Animação de loading no botão de submit
  $('#loginForm, #registerForm').on('submit', function() {
    const btn = $(this).find('button[type="submit"]');
    const originalText = btn.html();
    btn.html('Processando... <i class="fas fa-spinner fa-spin"></i>');
    btn.prop('disabled', true);
    
    // Restaurar botão em caso de erro
    setTimeout(() => {
      btn.html(originalText);
      btn.prop('disabled', false);
    }, 5000);
  });
  
  // Efeitos visuais melhorados
  $('.input-group input').on('focus', function() {
    $(this).closest('.input-group').addClass('focused');
  }).on('blur', function() {
    $(this).closest('.input-group').removeClass('focused');
  });
});

const usuarioLogado=<?= $usuarioLogado?'true':'false' ?>;

document.querySelectorAll('a[data-requer-login]').forEach(l=>{
  l.addEventListener('click',function(e){
    if(!usuarioLogado){
      e.preventDefault();
      openAuth('login');
    }
  })
});

document.querySelectorAll('.btn-depositar').forEach(b=>{
  b.addEventListener('click',function(e){
    if(!usuarioLogado){
      e.preventDefault();
      openAuth('login');
    }
  })
});

const btnOpenLogin=document.getElementById('btn-open-login');
const btnOpenRegister=document.getElementById('btn-open-register');
if(btnOpenLogin){btnOpenLogin.addEventListener('click',()=>openAuth('login'));} 
if(btnOpenRegister){btnOpenRegister.addEventListener('click',()=>openAuth('register'));} 

let cadastroEmAndamento=false;

function openAuth(tab){
  mostrarTab(tab);
  document.getElementById('modal-auth').classList.add('show');
}

function fecharModal(){
  const regTab=document.getElementById('form-register');
  const isRegisterVisible=regTab&&regTab.style.display!=='none';
  if(isRegisterVisible&&cadastroEmAndamento){
    const c=document.getElementById('confirm-cancel');
    if(c){c.classList.add('show');}
    return;
  }
  const modal=document.getElementById('modal-auth');
  if(modal){modal.classList.remove('show');}
}

function mostrarTab(tab){
  const isLogin=(tab==='login');
  document.getElementById('form-login').style.display=isLogin?'block':'none';
  document.getElementById('form-register').style.display=isLogin?'none':'block';
  const bLogin=document.getElementById('tab-login'),bReg=document.getElementById('tab-register');
  if(bLogin&&bReg){
    bLogin.classList.toggle('active',isLogin);
    bReg.classList.toggle('active',!isLogin);
  }
  const msg=document.getElementById('auth-msg');
  if(msg)msg.innerText='';
}

document.addEventListener('DOMContentLoaded',function(){
  const regForm=document.getElementById('registerForm');
  if(regForm){
    regForm.addEventListener('input',()=>{cadastroEmAndamento=true;});
    regForm.addEventListener('submit',()=>{cadastroEmAndamento=false;});
  }
  const b1=document.getElementById('btn-retomar-cadastro');
  const b2=document.getElementById('btn-cancelar-cadastro');
  if(b1){
    b1.addEventListener('click',()=>{
      document.getElementById('confirm-cancel').classList.remove('show');
    });
  }
  if(b2){
    b2.addEventListener('click',()=>{
      cadastroEmAndamento=false;
      document.getElementById('confirm-cancel').classList.remove('show');
      const m=document.getElementById('modal-auth');
      if(m){m.classList.remove('show');}
    });
  }
});

// Simulação de novos ganhadores em tempo real
function atualizarGanhadores() {
  const nomes = [
    'Ricardo M.', 'Fernanda S.', 'Gabriel L.', 'Patricia R.', 'Bruno C.', 'Camila F.', 'Lucas A.', 'Marina P.',
    'Alexandre T.', 'Beatriz N.', 'Caio R.', 'Daniela M.', 'Everton L.', 'Fabiana K.', 'Guilherme P.', 'Helena S.'
  ];
  
  const winnerCards = document.querySelectorAll('.winner-card');
  if (winnerCards.length > 0) {
    const randomIndex = Math.floor(Math.random() * (winnerCards.length / 2));
    const card = winnerCards[randomIndex];
    
    const nome = nomes[Math.floor(Math.random() * nomes.length)];
    
    const nomeElement = card.querySelector('.winner-name');
    const tempoElement = card.querySelector('.winner-time');
    
    if (nomeElement && tempoElement) {
      nomeElement.textContent = nome;
      tempoElement.textContent = 'Agora mesmo';
      
      card.style.borderColor = '#fbce00';
      card.style.background = 'rgba(251, 206, 0, 0.1)';
      
      setTimeout(() => {
        card.style.borderColor = '#21262d';
        card.style.background = '#0d1117';
      }, 3000);
    }
  }
}
</script>
<script>
$(function(){
  $('#loginForm').submit(function(e){
    e.preventDefault();
    $.ajax({
      url:'login_ajax.php',
      type:'POST',
      data:$(this).serialize(),
      success:function(r){
        if(r.trim()==='success'){
          location.reload();
        }else{
          $('#auth-msg').text(r);
        }
      },
      error:function(){
        $('#auth-msg').text('Erro ao processar requisição. Tente novamente.');
      }
    });
  });
  
  $('#registerForm').submit(function(e){
    e.preventDefault();
    $.ajax({
      url:'register_ajax.php',
      method:'POST',
      data:$(this).serialize(),
      success:function(r){
        if(r.trim()==='success'){
          window.location.href='deposito';
        }else{
          $('#auth-msg').text(r);
        }
      },
      error:function(){
        $('#auth-msg').text('Erro ao processar requisição. Tente novamente.');
      }
    });
  });
});
</script>
</body>
</html>