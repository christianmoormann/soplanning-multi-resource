<?php

// Point d'entree du chat IA - mode streaming (Server-Sent Events).
// Attend un POST JSON : {"message": "...", "reset": false}
// Retourne un flux SSE : data: {"type":"token","t":"..."}\n\n  ...  data: {"type":"done","refresh":bool}\n\n

// ob_start evite que les notices PHP/xdebug ne polluent le flux SSE
ob_start();
require 'base.inc';
require BASE . '/../config.inc';
require 'ollama.php';
require 'context.php';
require 'tools.php';
require 'executor.php';
ob_end_clean();

// Vide tous les niveaux de buffers (zlib, etc.)
while (ob_get_level() > 0) ob_end_clean();

set_time_limit(0);
ini_set('output_buffering',       'Off');
ini_set('zlib.output_compression', 0);
ob_implicit_flush(true);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');    // nginx
header('Content-Encoding: identity'); // desactive la compression Apache (mod_deflate)

// Envoie un evenement SSE et flush immediatement
function sseEvent($type, $data = array()) {
    echo 'data: ' . json_encode(array_merge(array('type' => $type), $data), JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) ob_flush();
    flush();
}

// --- 1. Verification session SOPlanning ---

if (empty($_SESSION['user_id']) || $_SESSION['user_id'] === 'publicspl') {
    sseEvent('error', array('message' => 'Session expiree, veuillez vous reconnecter.'));
    exit;
}

$currentUserId = $_SESSION['user_id'];

// --- 2. Lecture de la requete ---

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['message'])) {
    sseEvent('error', array('message' => 'Message vide.'));
    exit;
}

$userMessage  = trim($input['message']);
$resetHistory = !empty($input['reset']);

// --- 3. Historique de conversation (fichier JSON par utilisateur) ---

function historyFile($userId) {
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $userId);
    return __DIR__ . '/history/' . $safe . '.json';
}

function loadHistory($userId) {
    $file = historyFile($userId);
    if (!file_exists($file)) return array();
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : array();
}

function saveHistory($userId, array $history) {
    file_put_contents(historyFile($userId), json_encode($history, JSON_UNESCAPED_UNICODE));
}

if ($resetHistory) {
    saveHistory($currentUserId, array());
    $history = array();
} else {
    $history = loadHistory($currentUserId);
}

// --- 4. Contexte SOPlanning (charge une fois par session) ---

$contextKey = 'ai_context_' . $currentUserId;
if (!isset($_SESSION[$contextKey])) {
    try {
        $ctx = new SoplanningContext();
        $_SESSION[$contextKey] = $ctx->get();
    } catch (Exception $e) {
        sseEvent('error', array('message' => 'Erreur chargement contexte : ' . $e->getMessage()));
        exit;
    }
}
$context = $_SESSION[$contextKey];

// --- 5. Prompt systeme ---

$systemPrompt = buildSystemPrompt($context, $currentUserId);

// --- 6. Construction des messages ---

$messages   = array();
$messages[] = array('role' => 'system', 'content' => $systemPrompt);
foreach ($history as $h) {
    $messages[] = $h;
}
$messages[] = array('role' => 'user', 'content' => $userMessage);

// --- 7. Appel Ollama en streaming ---

$ollama   = new OllamaClient(CONFIG_AI_OLLAMA_URL, CONFIG_AI_OLLAMA_MODEL);
$tools    = SoplanningTools::get();
$executor = new SoplanningExecutor();

$reply   = '';
$refresh = false;

try {

    // --- 7a. Premier appel : detection tool call ou reponse directe ---

    $toolCallsDetected = array();
    $directTokens      = array();

    $ollama->chatStream($messages, function($token, $done, $toolCalls, $error) use (&$toolCallsDetected, &$directTokens) {
        if ($error) {
            sseEvent('error', array('message' => 'Ollama : ' . $error));
            exit;
        }
        if (!empty($toolCalls)) {
            $toolCallsDetected = $toolCalls;
        }
        // On n'emet les tokens que s'il n'y a pas (encore) de tool_calls detectes
        // (pour une reponse directe on streame immediatement)
        if ($token !== '' && empty($toolCallsDetected)) {
            sseEvent('token', array('t' => $token));
            $directTokens[] = $token;
        }
    }, $tools);

    if (!empty($toolCallsDetected)) {

        // --- 7b. Execution des tools ---

        // Informe l'utilisateur qu'une action est en cours
        $toolLabels = array(
            'rechercher_utilisateur' => 'Recherche utilisateur...',
            'rechercher_projet'      => 'Recherche projet...',
            'creer_tache'            => 'Creation de la tache...',
            'modifier_tache'         => 'Modification de la tache...',
            'supprimer_tache'        => 'Suppression de la tache...',
            'lister_taches'          => 'Recherche des taches...',
            'creer_projet'           => 'Creation du projet...',
        );
        $firstName = isset($toolCallsDetected[0]['name']) ? $toolCallsDetected[0]['name'] : '';
        $statusMsg = isset($toolLabels[$firstName]) ? $toolLabels[$firstName] : 'Traitement...';
        sseEvent('status', array('message' => $statusMsg));

        $toolResults = array();

        foreach ($toolCallsDetected as $tc) {
            $toolName = $tc['name'];
            $toolArgs = is_array($tc['arguments']) ? $tc['arguments'] : json_decode($tc['arguments'], true);

            // Cas special : demander_clarification
            if ($toolName === 'demander_clarification') {
                $result = $executor->execute($toolName, $toolArgs);
                $msg    = $result['message'];

                // On streame le texte de clarification token par token (simulation)
                foreach (preg_split('/(\\s+)/u', $msg, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
                    if ($part !== '') sseEvent('token', array('t' => $part));
                }
                $history[] = array('role' => 'user',      'content' => $userMessage);
                $history[] = array('role' => 'assistant', 'content' => $msg);
                if (count($history) > 20) $history = array_slice($history, -20);
                saveHistory($currentUserId, $history);
                sseEvent('done', array('refresh' => false));
                exit;
            }

            $result = $executor->execute($toolName, $toolArgs);
            if ($result['refresh']) $refresh = true;

            $toolResults[] = array(
                'role'    => 'tool',
                'content' => $result['success']
                    ? json_encode(array('result' => $result['message'], 'data' => $result['data']), JSON_UNESCAPED_UNICODE)
                    : json_encode(array('error'  => $result['message']), JSON_UNESCAPED_UNICODE),
            );
        }

        // Ajout de la reponse assistant (avec tool_calls) dans les messages
        $messages[] = array('role' => 'assistant', 'content' => '', 'tool_calls' => $toolCallsDetected);
        foreach ($toolResults as $tr) {
            $messages[] = $tr;
        }

        // --- 7c. Second appel : reponse finale en langage naturel (streaming) ---

        $ollama->chatStream($messages, function($token, $done, $toolCalls, $error) use (&$reply) {
            if ($error) {
                sseEvent('error', array('message' => 'Ollama : ' . $error));
                exit;
            }
            if ($token !== '') {
                sseEvent('token', array('t' => $token));
                $reply .= $token;
            }
        });

    } else {
        // Reponse directe (pas de tool call) : tokens deja envoyes ci-dessus
        $reply = implode('', $directTokens);
    }

} catch (Exception $e) {
    sseEvent('error', array('message' => 'Erreur IA : ' . $e->getMessage()));
    exit;
}

// --- 8. Mise a jour de l'historique ---

$history[] = array('role' => 'user',      'content' => $userMessage);
$history[] = array('role' => 'assistant', 'content' => $reply);

if (count($history) > 20) {
    $history = array_slice($history, -20);
}

saveHistory($currentUserId, $history);

// --- 9. Evenement de fin ---

sseEvent('done', array('refresh' => $refresh));


// ============================================================
// Construction du prompt systeme
// ============================================================

function buildSystemPrompt(array $ctx, $currentUserId) {

    $lines = array();
    $lines[] = 'Tu es l assistant IA de SOPlanning, un outil de gestion de planning.';
    $lines[] = 'Reponds TOUJOURS en francais, de facon concise et directe.';
    $lines[] = 'Utilisateur connecte : ' . $currentUserId;
    $lines[] = 'Date du jour : ' . $ctx['date_today'];
    $lines[] = '';

    // Statuts
    $statusTask = array();
    $statusProj = array();
    foreach ($ctx['statuses'] as $s) {
        if ($s['pour_tache'])  $statusTask[] = $s['id'] . '=' . $s['nom'];
        if ($s['pour_projet']) $statusProj[] = $s['id'] . '=' . $s['nom'];
    }
    if ($statusTask) $lines[] = 'Statuts taches disponibles : ' . implode(', ', $statusTask);
    if ($statusProj) $lines[] = 'Statuts projets disponibles : ' . implode(', ', $statusProj);
    $lines[] = '';

    $lines[] = 'Regles importantes :';
    $lines[] = '- Les dates dans les tools sont TOUJOURS au format YYYY-MM-DD (ex: ' . date('Y-m-d') . ' = aujourd hui, ' . date('Y-m-d', strtotime('+1 day')) . ' = demain).';
    $lines[] = '- Si l utilisateur fournit un code court en majuscules (ex: GAILLARD, CONGES), c est deja un ID valide, utilise-le directement.';
    $lines[] = '- Si l utilisateur donne un prenom ou un nom inconnu, utilise rechercher_utilisateur pour trouver l ID.';
    $lines[] = '- Pour trouver l ID d un projet inconnu, utilise rechercher_projet.';
    $lines[] = '- Si plusieurs resultats correspondent a une recherche, utilise demander_clarification.';
    $lines[] = '- Pour supprimer ou modifier une tache dont tu ne connais pas l ID, utilise d abord lister_taches.';
    $lines[] = '- Ne cree jamais une tache sans avoir user_id et project_id valides.';
    $lines[] = '- Quand tu effectues une action, confirme-la brievement a l utilisateur.';

    return implode("\n", $lines);
}
