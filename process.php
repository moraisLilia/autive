
<?php
header('Content-Type: application/json');
session_start(); // Inicia a sessão no topo do arquivo
require_once "config.php";

$action = $_POST['action'] ?? '';

// Função para enviar resposta JSON
function sendJsonResponse($success, $message) {
    echo json_encode(["success" => $success, "message" => $message]);
    exit;
}

if ($action === 'register') {
    $email = filter_input(INPUT_POST, 'register-email', FILTER_SANITIZE_EMAIL);
    $username = filter_input(INPUT_POST, 'register-username', FILTER_SANITIZE_STRING);
    $name = filter_input(INPUT_POST, 'register-name', FILTER_SANITIZE_STRING);
    $password = $_POST['register-password'] ?? ''; // Senha não deve ser sanitizada, apenas verificada

    if (empty($email) || empty($username) || empty($name) || empty($password)) {
        sendJsonResponse(false, "Preencha todos os campos.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, "Formato de e-mail inválido.");
    }

    // Verificar se já existe email
    try {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$check) {
            throw new Exception("Erro ao preparar a consulta de verificação de e-mail: " . $conn->error);
        }
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            sendJsonResponse(false, "Email já cadastrado.");
        }
        $check->close();
    } catch (Exception $e) {
        sendJsonResponse(false, "Erro interno ao verificar e-mail: " . $e->getMessage());
    }

    // Criptografar senha
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    if ($hashedPassword === false) {
        sendJsonResponse(false, "Erro ao criptografar a senha.");
    }

    // Inserir novo usuário
    try {
        $stmt = $conn->prepare("INSERT INTO users (email, username, name, password) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Erro ao preparar a consulta de registro: " . $conn->error);
        }
        $stmt->bind_param("ssss", $email, $username, $name, $hashedPassword);

        if ($stmt->execute()) {
            sendJsonResponse(true, "Cadastro realizado com sucesso!");
        } else {
            throw new Exception("Erro ao executar o cadastro: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        sendJsonResponse(false, "Erro ao cadastrar: " . $e->getMessage());
    }

} elseif ($action === 'login') {
    $email = filter_input(INPUT_POST, 'login-email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['login-password'] ?? '';

    if (empty($email) || empty($password)) {
        sendJsonResponse(false, "Preencha todos os campos.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, "Formato de e-mail inválido.");
    }

    try {
        $stmt = $conn->prepare("SELECT id, password, username FROM users WHERE email = ?");
        if (!$stmt) {
            throw new Exception("Erro ao preparar a consulta de login: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashedPassword, $username);
            $stmt->fetch();

            if (password_verify($password, $hashedPassword)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                session_regenerate_id(true); // Regenera o ID da sessão para segurança

                sendJsonResponse(true, "Login realizado com sucesso!");
            } else {
                sendJsonResponse(false, "Senha incorreta.");
            }
        } else {
            sendJsonResponse(false, "Usuário não encontrado.");
        }
        $stmt->close();
    } catch (Exception $e) {
        sendJsonResponse(false, "Erro interno ao fazer login: " . $e->getMessage());
    }

} else {
    sendJsonResponse(false, "Ação inválida.");
}

$conn->close();
?>
