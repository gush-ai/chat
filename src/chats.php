<?php
// File: src/chats.php
/**
 * Simple AI chat endpoint.
 * Expects JSON payload: { "message": "text" }
 * Returns JSON: { "reply": "AI response" } or { "error": "description" }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
define('CHAT_DEBUG_MODE', false); // set true only for temporary debugging

session_start();
$_SESSION['gush_chat_history'] ??= [];

// Load token securely from environment (fallback to hard‑coded for demo)
$token = getenv('GUSH_API_TOKEN') ?: 'gush_live_551e08a0f66dfa65eb1e96ac6c3e8f661db03b4fde210f14';
$gateway_endpoint = 'https://ai.sstore.ng/api-access.php/v1';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? null;

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userMessage = trim($input['message'] ?? '');

    if ($userMessage === '') {
        echo json_encode(['error' => 'Payload error: Message is empty.']);
        exit;
    }

    // Store user message
    $_SESSION['gush_chat_history'][] = ['role' => 'user', 'content' => $userMessage];

    // Keep only the last 15 entries to limit payload size
    $history = array_slice($_SESSION['gush_chat_history'], -15);

    $payload = [
        'messages'    => $history,
        'temperature' => 0.7,
        'max_tokens'  => 4096,
    ];

    $ch = curl_init($gateway_endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_REFERER        => 'https://sstore.ng',
        CURLOPT_TIMEOUT        => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError   = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode([
            'error'        => 'Gateway Connection Error.',
            'details'      => $curlError,
            'debug_active' => CHAT_DEBUG_MODE,
        ]);
        exit;
    }

    $decoded = json_decode($rawResponse, true);
    $aiReply = $decoded['choices'][0]['message']['content'] ?? '';

    if ($httpCode === 200 && $aiReply !== '') {
        $_SESSION['gush_chat_history'][] = ['role' => 'assistant', 'content' => $aiReply];
        echo json_encode(['reply' => $aiReply]);
    } else {
        $errorMsg = $decoded['error']['message'] ?? 'Unexpected response from AI service.';
        if (CHAT_DEBUG_MODE) {
            echo json_encode([
                'error'          => 'DEBUG: AI call failed.',
                'upstream_code'  => $httpCode,
                'details'        => $errorMsg,
                'raw_response'   => $rawResponse,
            ]);
        } else {
            echo json_encode([
                'error'          => $errorMsg,
                'upstream_code'  => $httpCode,
            ]);
        }
    }
    exit;
}

// Clear conversation history
if ($action === 'clear') {
    $_SESSION['gush_chat_history'] = [];
    echo json_encode(['ok' => true]);
    exit;
}

// Fallback for unsupported routes
echo json_encode(['error' => 'Invalid request.']);
exit;
?>