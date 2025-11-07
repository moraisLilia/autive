<?php
// Desabilitar exibição de erros em produção por segurança
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

$host = "localhost";  // Geralmente localhost
$user = "root";       // Seu usuário do MySQL
$pass = "";           // Senha do MySQL
$db   = "autive";  // Nome do banco de dados

// Tenta estabelecer a conexão
$conn = new mysqli($host, $user, $pass, $db);

// Verifica se houve erro na conexão
if ($conn->connect_error) {
    // Em ambiente de produção, você logaria o erro e mostraria uma mensagem genérica
    // error_log("Falha na conexão com o banco de dados: " . $conn->connect_error);
    die(json_encode(["success" => false, "message" => "Erro interno do servidor. Tente novamente mais tarde."]));
}

// Define o charset para evitar problemas de codificação
$conn->set_charset("utf8mb4");
?>