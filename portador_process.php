<?php
// Configurações e conexão do banco de dados (reutiliza config.php)
header('Content-Type: application/json');
session_start();
require_once "config.php"; // Seu arquivo de conexão com o banco

// Função de utilidade
function sendJsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(["success" => $success, "message" => $message], $data));
    exit;
}

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(false, "Usuário não autenticado. Faça login para acessar.");
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // --- A. Preferências da Criança ---

    case 'add_preference':
        $preference = filter_input(INPUT_POST, 'preference', FILTER_SANITIZE_STRING);

        if (empty($preference)) {
            sendJsonResponse(false, "A preferência não pode ser vazia.");
        }

        try {
            $stmt = $conn->prepare("INSERT INTO preferencias (user_id, preferencia) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $preference);
            $stmt->execute();
            $stmt->close();
            sendJsonResponse(true, "Preferência adicionada com sucesso.");
        } catch (Exception $e) {
            sendJsonResponse(false, "Erro ao adicionar preferência: " . $e->getMessage());
        }
        break;

    case 'get_preferences':
        try {
            $stmt = $conn->prepare("SELECT id, preferencia FROM preferencias WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $preferences = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            sendJsonResponse(true, "Preferências carregadas.", ["preferences" => $preferences]);
        } catch (Exception $e) {
            sendJsonResponse(false, "Erro ao carregar preferências: " . $e->getMessage());
        }
        break;
        
    case 'delete_preference':
        $pref_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        if (!$pref_id) {
            sendJsonResponse(false, "ID da preferência inválido.");
        }
        
        try {
            $stmt = $conn->prepare("DELETE FROM preferencias WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $pref_id, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                sendJsonResponse(true, "Preferência excluída com sucesso.");
            } else {
                sendJsonResponse(false, "Preferência não encontrada ou não permitida.");
            }
            $stmt->close();
        } catch (Exception $e) {
            sendJsonResponse(false, "Erro ao excluir preferência: " . $e->getMessage());
        }
        break;


    // --- B. Eventos (Consultas/Medicamentos/Rotina) ---

    case 'save_event':
        // Filtra e valida os dados
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?? null;
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $start = $_POST['start'] ?? '';
        $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
        $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
        $medicationDetails = filter_input(INPUT_POST, 'medicationDetails', FILTER_SANITIZE_STRING);

        if (empty($title) || empty($start) || !in_array($type, ['consulta', 'medicamento', 'rotina'])) {
            sendJsonResponse(false, "Dados do evento inválidos.");
        }

        try {
            if ($id) {
                // Atualizar evento existente
                $sql = "UPDATE eventos SET titulo=?, tipo=?, data_hora=?, detalhes_medicamento=?, notas=? WHERE id=? AND user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssii", $title, $type, $start, $medicationDetails, $notes, $id, $user_id);
            } else {
                // Inserir novo evento
                $sql = "INSERT INTO eventos (user_id, titulo, tipo, data_hora, detalhes_medicamento, notas) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssss", $user_id, $title, $type, $start, $medicationDetails, $notes);
            }
            
            $stmt->execute();
            
            if (!$id) {
                $id = $conn->insert_id;
            }

            $stmt->close();
            sendJsonResponse(true, "Evento salvo com sucesso.", ["id" => $id]);
            
        } catch (Exception $e) {
            sendJsonResponse(false, "Erro ao salvar evento: " . $e->getMessage());
        }
        break;

    case 'get_events':
        try {
            $stmt = $conn->prepare("SELECT id, titulo, tipo, data_hora, detalhes_medicamento, notas FROM eventos WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $events = [];
            
            while ($row = $result->fetch_assoc()) {
                // Formatação para o FullCalendar
                $fc_title = $row['titulo'];
                $class_name = '';
                
                if ($row['tipo'] === 'medicamento') {
                    $fc_title .= " (" . $row['detalhes_medicamento'] . ")";
                    $class_name = 'bg-warning text-dark medicamento-event';
                } else if ($row['tipo'] === 'consulta') {
                    $class_name = 'bg-success';
                } else if ($row['tipo'] === 'rotina') {
                    $class_name = 'bg-primary'; // Cor diferente para rotina
                }
                
                $events[] = [
                    "id" => (string)$row['id'], // ID deve ser string no FC
                    "title" => $fc_title,
                    "start" => $row['data_hora'],
                    "extendedProps" => [
                        "title" => $row['titulo'], // Título original para edição
                        "type" => $row['tipo'],
                        "notes" => $row['notas'],
                        "medicationDetails" => $row['detalhes_medicamento']
                    ],
                    "className" => $class_name
                ];
            }
            $stmt->close();
            sendJsonResponse(true, "Eventos carregados.", ["events" => $events]);
            
        } catch (Exception $e) {
            sendJsonResponse(false, "Erro ao carregar eventos: " . $e->getMessage());
        }
        break;

    case 'delete_event':
        $event_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        if (!$event_id) {
            sendJsonResponse(false, "ID do evento inválido.");
        }
        
        try {
            $stmt = $conn->prepare("DELETE FROM eventos WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $event_id, $user_id);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                sendJsonResponse(true, "Evento excluído com sucesso.");
            } else {
                sendJsonResponse(false, "Evento não encontrado ou não permitido.");
            }
            $stmt->close();
        } catch (Exception $e) {
            sendJsonResponse(false, "Erro ao excluir evento: " . $e->getMessage());
        }
        break;

    default:
        sendJsonResponse(false, "Ação desconhecida.");
        break;
}

$conn->close();
?>