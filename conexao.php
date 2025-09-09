<?php
// Configurar o fuso horário do PHP para Brasília
date_default_timezone_set('America/Sao_Paulo');

$host = '127.0.0.1';
$db   = 'caixasupresa';
$user = 'caixasupresa';
$pass = 'caixasupresa'; 
$charset = 'utf8mb4';
$port = 3306;

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_TIMEOUT            => 10
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Configurar o fuso horário do MySQL para Brasília (UTC-3)
    $pdo->exec("SET time_zone = '-03:00'");
    
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

try {
    $site = $pdo->query("SELECT nome_site, logo, deposito_min, saque_min, cpa_padrao, revshare_padrao FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $nomeSite = $site['nome_site'] ?? ''; 
    $logoSite = $site['logo'] ?? '';
    $depositoMin = $site['deposito_min'] ?? 10;
    $saqueMin = $site['saque_min'] ?? 50;
    $cpaPadrao = $site['cpa_padrao'] ?? 10;
    $revshare_padrao = $site['revshare_padrao'] ?? 10;
} catch (\PDOException $e) {
    // Se der erro na query, continuar sem os dados do site
    $nomeSite = 'Site'; 
    $logoSite = '';
    $depositoMin = 10;
    $saqueMin = 50;
    $cpaPadrao = 10;
    $revshare_padrao = 10;
}
?>