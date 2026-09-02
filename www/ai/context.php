<?php

// Recupere le contexte SOPlanning via l'API REST pour l'injecter dans le prompt IA.
// Utilise CONFIG_SOPLANNING_URL, CONFIG_SOPLANNING_API_KEY_NAME, CONFIG_SOPLANNING_API_KEY_VALUE

class SoplanningContext {

    private $apiBase;
    private $apiKeyHeader;

    public function __construct() {
        // CONFIG_SOPLANNING_URL se termine par un slash, ex: http://host/soplanning/www/
        $this->apiBase     = rtrim(CONFIG_SOPLANNING_URL, '/') . '/api/endpoint';
        $this->apiKeyHeader = CONFIG_SOPLANNING_API_KEY_NAME . ': ' . CONFIG_SOPLANNING_API_KEY_VALUE;
    }

    // Retourne un tableau pret a etre encode en JSON pour le prompt systeme de l'IA.
    // Format : ['users' => [...], 'projects' => [...], 'places' => [...], 'resources' => [...]]
    public function get() {
        return array(
            'date_today' => date('Y-m-d'),
            'users'      => $this->simplify($this->fetch('users')),
            'projects'   => $this->simplify($this->fetch('projects')),
            'places'     => $this->simplify($this->fetch('places')),
            'resources'  => $this->simplify($this->fetch('resources')),
            'statuses'   => $this->fetchStatuses(),
        );
    }

    // Retourne les statuts avec leur usage (tache/projet) pour que l'IA puisse les choisir.
    private function fetchStatuses() {
        $items  = $this->fetch('statutes');
        $result = array();
        foreach ($items as $item) {
            if (!isset($item['id']) || !isset($item['name'])) continue;
            $result[] = array(
                'id'          => $item['id'],
                'nom'         => $item['name'],
                'pour_tache'  => !empty($item['for_task']),
                'pour_projet' => !empty($item['for_project']),
            );
        }
        return $result;
    }

    // Retourne uniquement id+nom pour alleger le contexte envoye a l'IA.
    // L'API SOPlanning utilise toujours "id" et "name" dans ses reponses.
    private function simplify(array $items) {
        $result = array();
        foreach ($items as $item) {
            if (isset($item['id']) && isset($item['name'])) {
                $result[] = array('id' => $item['id'], 'nom' => $item['name']);
            }
        }
        return $result;
    }

    // Appelle GET /endpoint/{route}, extrait le tableau "data" de la reponse
    // (php-rest-service enveloppe toujours : {"status":200,"data":[...]})
    private function fetch($route) {
        $url = $this->apiBase . '/' . $route;
        $ch  = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array($this->apiKeyHeader),
            CURLOPT_TIMEOUT        => 10,
        ));
        $raw     = curl_exec($ch);
        $err     = curl_error($ch);
        $status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new Exception('API SOPlanning inaccessible (' . $route . ') : ' . $err);
        }
        if ($status !== 200) {
            throw new Exception('API SOPlanning erreur HTTP ' . $status . ' sur /' . $route);
        }

        // Les notices PHP/xdebug peuvent preceder le JSON : on cherche le premier { valide
        $response = json_decode($raw, true);
        if ($response === null) {
            $pos = strpos($raw, '{');
            while ($pos !== false) {
                $response = json_decode(substr($raw, $pos), true);
                if ($response !== null) break;
                $pos = strpos($raw, '{', $pos + 1);
            }
        }
        if (!isset($response['data']) || !is_array($response['data'])) {
            throw new Exception('API SOPlanning reponse invalide sur /' . $route);
        }
        return $response['data'];
    }
}
