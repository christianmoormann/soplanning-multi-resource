<?php

// Retourne l'historique de conversation de l'utilisateur connecte (GET).
// Repond en JSON : {"messages": [{"role":"user","content":"..."},{"role":"assistant","content":"..."},...]}

ob_start();
require 'base.inc';
require BASE . '/../config.inc';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

if (empty($_SESSION['user_id']) || $_SESSION['user_id'] === 'publicspl') {
    echo json_encode(array('messages' => array()));
    exit;
}

$userId = $_SESSION['user_id'];
$safe   = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userId);
$file   = __DIR__ . '/history/' . $safe . '.json';

$history = array();
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
    if (is_array($data)) $history = $data;
}

// Ne retourner que les messages user/assistant (pas les tool results ni system)
$messages = array();
foreach ($history as $h) {
    if (isset($h['role']) && ($h['role'] === 'user' || $h['role'] === 'assistant')) {
        $messages[] = array('role' => $h['role'], 'content' => $h['content']);
    }
}

echo json_encode(array('messages' => $messages), JSON_UNESCAPED_UNICODE);
