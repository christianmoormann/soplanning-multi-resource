<?php
// Test du flux complet sans session reelle
require 'base.inc';
require BASE . '/../config.inc';
require 'ollama.php';
require 'context.php';
require 'tools.php';
require 'executor.php';

$currentUserId = 'GAILLARD'; // utilisateur de test

// Charge le contexte
$ctx = (new SoplanningContext())->get();
$systemPrompt = buildSystemPromptTest($ctx, $currentUserId);

$ollama   = new OllamaClient(CONFIG_AI_OLLAMA_URL, CONFIG_AI_OLLAMA_MODEL);
$executor = new SoplanningExecutor();
$tools    = SoplanningTools::get();

$testMessages = array(
    "Cree une tache pour GAILLARD demain de 10h a 11h sur le projet CONGES",
    "Quelles taches a GAILLARD le 26 mars 2026 ?",
);

foreach ($testMessages as $userMessage) {
    echo "\n=== " . $userMessage . " ===\n";

    $messages = array(
        array('role' => 'system', 'content' => $systemPrompt),
        array('role' => 'user',   'content' => $userMessage),
    );

    $t = microtime(true);
    $response = $ollama->chat($messages, $tools);
    $duree = round(microtime(true) - $t, 1);

    if (!empty($response['tool_calls'])) {
        echo "Tool call ({$duree}s):\n";
        foreach ($response['tool_calls'] as $tc) {
            echo "  -> " . $tc['name'] . " : " . json_encode($tc['arguments'], JSON_UNESCAPED_UNICODE) . "\n";
            $args = is_array($tc['arguments']) ? $tc['arguments'] : json_decode($tc['arguments'], true);
            $result = $executor->execute($tc['name'], $args);
            echo "  Resultat : " . ($result['success'] ? 'OK' : 'ERREUR') . " : " . $result['message'] . "\n";
        }
    } else {
        echo "Reponse directe ({$duree}s) : " . $response['content'] . "\n";
    }
}

function buildSystemPromptTest($ctx, $uid) {
    $lines   = array();
    $lines[] = 'Tu es l assistant IA de SOPlanning. Reponds en francais.';
    $lines[] = 'Utilisateur connecte : ' . $uid;
    $lines[] = 'Date du jour : ' . $ctx['date_today'];
    $statusTask = array();
    foreach ($ctx['statuses'] as $s) {
        if ($s['pour_tache']) $statusTask[] = $s['id'] . '=' . $s['nom'];
    }
    if ($statusTask) $lines[] = 'Statuts taches : ' . implode(', ', $statusTask);
    $lines[] = 'Les dates dans les tools sont TOUJOURS au format YYYY-MM-DD (ex: ' . date('Y-m-d') . ' = aujourd hui, ' . date('Y-m-d', strtotime('+1 day')) . ' = demain).';
    $lines[] = 'Si l utilisateur fournit un code court en majuscules (ex: GAILLARD, CONGES), c est deja un ID valide, utilise-le directement.';
    $lines[] = 'Si l utilisateur donne un prenom ou un nom complet inconnu, utilise rechercher_utilisateur. Pour un projet inconnu, utilise rechercher_projet.';
    $lines[] = 'Si plusieurs correspondances, utilise demander_clarification. Ne cree pas sans user_id et project_id valides.';
    return implode("\n", $lines);
}
