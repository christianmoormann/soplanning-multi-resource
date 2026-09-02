<?php

// Client HTTP pour Ollama
// Doc API : https://github.com/ollama/ollama/blob/main/docs/api.md

class OllamaClient {

    private $baseUrl;
    private $model;
    private $timeoutSeconds;

    private $numCtx;

    public function __construct($baseUrl, $model, $timeoutSeconds = 600, $numCtx = 4096) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->model = $model;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->numCtx = $numCtx;
    }

    // Envoie une conversation a Ollama et retourne la reponse
    // $messages : tableau de ['role' => 'user'|'assistant'|'system', 'content' => '...']
    // $tools    : tableau de definitions de tools (optionnel)
    // Retourne un tableau ['content' => '...', 'tool_calls' => [...]] ou lance une Exception
    public function chat(array $messages, array $tools = array()) {
        $payload = array(
            'model'      => $this->model,
            'messages'   => $messages,
            'stream'     => false,
            'keep_alive' => -1,     // garde le modele en RAM indefiniment (-1 = jamais expirer)
            'options'    => array('num_ctx' => $this->numCtx),
        );

        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = $this->post('/api/chat', $payload);

        if (!isset($response['message'])) {
            throw new Exception('Ollama: reponse inattendue : ' . json_encode($response));
        }

        $result = array(
            'content'    => isset($response['message']['content']) ? $response['message']['content'] : '',
            'tool_calls' => array(),
        );

        if (!empty($response['message']['tool_calls'])) {
            foreach ($response['message']['tool_calls'] as $tc) {
                $result['tool_calls'][] = array(
                    'name'      => $tc['function']['name'],
                    'arguments' => $tc['function']['arguments'],
                );
            }
        }

        return $result;
    }

    // Envoie une conversation en mode streaming.
    // $onToken(string $token, bool $done, array $toolCalls, ?string $error)
    //   appelee pour chaque token genere ; $toolCalls rempli uniquement sur $done=true
    public function chatStream(array $messages, callable $onToken, array $tools = array()) {
        $payload = array(
            'model'      => $this->model,
            'messages'   => $messages,
            'stream'     => true,
            'keep_alive' => -1,
            'options'    => array('num_ctx' => $this->numCtx),
        );
        if (!empty($tools)) {
            $payload['tools'] = $tools;
        }

        $json       = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $lineBuf    = '';
        $toolCalls  = array();

        $ch = curl_init($this->baseUrl . '/api/chat');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION  => function($ch, $data) use (&$lineBuf, &$toolCalls, $onToken) {
                $lineBuf .= $data;
                while (($pos = strpos($lineBuf, "\n")) !== false) {
                    $line    = substr($lineBuf, 0, $pos);
                    $lineBuf = substr($lineBuf, $pos + 1);
                    if (trim($line) === '') continue;

                    $obj = json_decode($line, true);
                    if (!$obj) continue;

                    if (isset($obj['error'])) {
                        call_user_func($onToken, '', true, array(), $obj['error']);
                        return strlen($data);
                    }

                    if (!empty($obj['message']['tool_calls'])) {
                        foreach ($obj['message']['tool_calls'] as $tc) {
                            $toolCalls[] = array(
                                'name'      => $tc['function']['name'],
                                'arguments' => $tc['function']['arguments'],
                            );
                        }
                    }

                    $token = isset($obj['message']['content']) ? $obj['message']['content'] : '';
                    $done  = !empty($obj['done']);
                    call_user_func($onToken, $token, $done, $done ? $toolCalls : array(), null);
                }
                return strlen($data);
            },
        ));
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception('Ollama inaccessible : ' . $err);
        }
    }

    // Verifie qu'Ollama est joignable et que le modele est charge
    // Retourne true ou lance une Exception
    public function ping() {
        $response = $this->get('/');
        return true;
    }

    // Liste les modeles disponibles
    public function listModels() {
        $response = $this->get('/api/tags');
        return isset($response['models']) ? $response['models'] : array();
    }

    // --- Methodes privees HTTP ---

    private function post($path, array $data) {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
        ));
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new Exception('Ollama inaccessible : ' . $err);
        }
        if ($httpCode >= 400) {
            throw new Exception('Ollama HTTP ' . $httpCode . ' : ' . $raw);
        }

        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            throw new Exception('Ollama: reponse JSON invalide : ' . $raw);
        }
        return $decoded;
    }

    private function get($path) {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ));
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception('Ollama inaccessible : ' . $err);
        }
        return json_decode($raw, true) ?: array('raw' => $raw);
    }
}
