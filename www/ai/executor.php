<?php

// Traduit les tool_calls retournes par l'IA en appels concrets a l'API REST SOPlanning.
// Chaque methode execute() retourne un tableau :
//   ['success' => bool, 'message' => string, 'data' => mixed, 'refresh' => bool]

class SoplanningExecutor {

    private $apiBase;
    private $apiKeyHeader;

    // Cache des statuts par defaut (evite plusieurs appels API dans une meme requete)
    private $defaultStatusTask    = null;
    private $defaultStatusProject = null;

    public function __construct() {
        $this->apiBase      = rtrim(CONFIG_SOPLANNING_URL, '/') . '/api/endpoint';
        $this->apiKeyHeader = CONFIG_SOPLANNING_API_KEY_NAME . ': ' . CONFIG_SOPLANNING_API_KEY_VALUE;
    }

    // Point d'entree : dispatch vers la bonne methode selon le nom du tool
    // $name      : nom du tool (ex: 'creer_tache')
    // $arguments : tableau associatif des arguments retournes par l'IA
    public function execute($name, array $arguments) {
        switch ($name) {
            case 'rechercher_utilisateur': return $this->rechercherUtilisateur($arguments);
            case 'rechercher_projet':      return $this->rechercherProjet($arguments);
            case 'creer_tache':            return $this->creerTache($arguments);
            case 'modifier_tache':         return $this->modifierTache($arguments);
            case 'supprimer_tache':        return $this->supprimerTache($arguments);
            case 'lister_taches':          return $this->listerTaches($arguments);
            case 'creer_projet':           return $this->creerProjet($arguments);
            case 'demander_clarification': return $this->demanderClarification($arguments);
            default:
                return $this->error('Tool inconnu : ' . $name);
        }
    }

    // Recupere le premier statut disponible pour les taches ou les projets.
    // Les statuts sont deja tries par priorite cote API.
    private function getDefaultStatus($forTask = true) {
        $cacheKey = $forTask ? 'defaultStatusTask' : 'defaultStatusProject';
        if ($this->$cacheKey !== null) {
            return $this->$cacheKey;
        }

        $response = $this->get('statutes');
        if (!$response['ok'] || empty($response['data'])) {
            throw new Exception('Impossible de recuperer la liste des statuts');
        }

        $filterKey = $forTask ? 'for_task' : 'for_project';
        foreach ($response['data'] as $status) {
            if (!empty($status[$filterKey])) {
                $this->$cacheKey = $status['id'];
                return $status['id'];
            }
        }

        throw new Exception('Aucun statut disponible pour ' . ($forTask ? 'les taches' : 'les projets'));
    }

    // -------------------------------------------------------------------
    // RECHERCHE
    // -------------------------------------------------------------------

    private function rechercherUtilisateur(array $args) {
        $nom = $this->req($args, 'nom');

        // Deux appels : recherche par nom d'affichage ET par identifiant (LIKE %nom%)
        $byName = $this->get('users?' . http_build_query(array('name' => $nom)));
        $byId   = $this->get('users?' . http_build_query(array('id'   => $nom)));

        if (!$byName['ok'] && !$byId['ok']) {
            return $this->error('Erreur recherche utilisateurs : ' . $byName['message']);
        }

        $seen = array();
        $results = array();
        $pool = array_merge(
            ($byName['ok'] ? (array)$byName['data'] : array()),
            ($byId['ok']   ? (array)$byId['data']   : array())
        );
        foreach ($pool as $u) {
            $id = isset($u['id']) ? $u['id'] : '';
            if ($id !== '' && !isset($seen[$id])) {
                $seen[$id] = true;
                $results[] = array('id' => $id, 'nom' => isset($u['name']) ? $u['name'] : '');
            }
        }

        if (empty($results)) {
            return $this->success('Aucun utilisateur trouve pour "' . $nom . '"', array(), false);
        }

        $lines = array();
        foreach ($results as $r) { $lines[] = $r['id'] . ':' . $r['nom']; }
        return $this->success(implode(', ', $lines), $results, false);
    }

    private function rechercherProjet(array $args) {
        $nom = $this->req($args, 'nom');

        // Deux appels : recherche par nom ET par identifiant (LIKE %nom%)
        $byName = $this->get('projects?' . http_build_query(array('name' => $nom)));
        $byId   = $this->get('projects?' . http_build_query(array('id'   => $nom)));

        if (!$byName['ok'] && !$byId['ok']) {
            return $this->error('Erreur recherche projets : ' . $byName['message']);
        }

        $seen = array();
        $results = array();
        $pool = array_merge(
            ($byName['ok'] ? (array)$byName['data'] : array()),
            ($byId['ok']   ? (array)$byId['data']   : array())
        );
        foreach ($pool as $p) {
            $id = isset($p['id']) ? $p['id'] : '';
            if ($id !== '' && !isset($seen[$id])) {
                $seen[$id] = true;
                $results[] = array('id' => $id, 'nom' => isset($p['name']) ? $p['name'] : '');
            }
        }

        if (empty($results)) {
            return $this->success('Aucun projet trouve pour "' . $nom . '"', array(), false);
        }

        $lines = array();
        foreach ($results as $p) { $lines[] = $p['id'] . ':' . $p['nom']; }
        return $this->success(implode(', ', $lines), $results, false);
    }

    // -------------------------------------------------------------------
    // TACHES
    // -------------------------------------------------------------------

    private function creerTache(array $args) {
        $params = array(
            'task_id'    => '',
            'link_id'    => '',
            'status_id'  => $this->getDefaultStatus(true),
            'user_id'    => $this->req($args, 'user_id'),
            'project_id' => $this->req($args, 'project_id'),
            'start_date' => $this->req($args, 'start_date'),
            'end_date'   => $this->opt($args, 'end_date',   $this->opt($args, 'start_date', '')),
            'start_time' => $this->opt($args, 'start_time', ''),
            'end_time'   => $this->opt($args, 'end_time',   ''),
            'title'      => $this->opt($args, 'title',      ''),
            'comment'    => $this->opt($args, 'comment',    ''),
            'place_id'   => $this->opt($args, 'place_id',   ''),
            'resource_id'=> $this->opt($args, 'resource_id',''),
        );

        $response = $this->post('tasks', $params);
        if (!$response['ok']) {
            return $this->error('Erreur creation tache : ' . $response['message']);
        }

        $task = $response['data'];
        $msg  = 'Tache creee (ID ' . $task['task_id'] . ')';
        if (!empty($task['title']))      $msg .= ' : ' . $task['title'];
        $msg .= ' pour ' . $task['user_id'];
        $msg .= ' sur ' . $task['project_id'];
        $msg .= ' le ' . $this->formatDate($task['start_date']);
        if (!empty($task['start_hour'])) $msg .= ' de ' . $task['start_hour'] . ' a ' . $task['end_hour'];

        return $this->success($msg, $task, true);
    }

    private function modifierTache(array $args) {
        $taskId = $this->req($args, 'task_id');

        // On recharge la tache existante via l'API pour conserver les champs non modifies
        $existing = $this->get('tasks/' . $taskId);
        if (!$existing['ok']) {
            return $this->error('Tache ID ' . $taskId . ' introuvable');
        }
        $t = $existing['data'];

        $params = array(
            'task_id'    => $taskId,
            'link_id'    => $t['link_id'],
            'status_id'  => $this->opt($args, 'status_id',  $t['status_id']),
            'user_id'    => $this->opt($args, 'user_id',    $t['user_id']),
            'project_id' => $this->opt($args, 'project_id', $t['project_id']),
            'start_date' => $this->opt($args, 'start_date', $t['start_date']),
            'end_date'   => $this->opt($args, 'end_date',   $t['end_date']),
            'start_time' => $this->opt($args, 'start_time', $t['start_hour']),
            'end_time'   => $this->opt($args, 'end_time',   $t['end_hour']),
            'title'      => $this->opt($args, 'title',      $t['title']),
            'comment'    => $this->opt($args, 'comment',    $t['comment']),
            'place_id'   => $this->opt($args, 'place_id',   $t['resource_id']),
            'resource_id'=> $this->opt($args, 'resource_id',$t['place_id']),
        );

        $response = $this->post('tasks', $params);
        if (!$response['ok']) {
            return $this->error('Erreur modification tache : ' . $response['message']);
        }

        return $this->success('Tache ID ' . $taskId . ' modifiee.', $response['data'], true);
    }

    private function supprimerTache(array $args) {
        $taskId = $this->req($args, 'task_id');

        $response = $this->delete('tasks/' . $taskId);
        if (!$response['ok']) {
            return $this->error('Erreur suppression tache ID ' . $taskId . ' : ' . $response['message']);
        }

        return $this->success('Tache ID ' . $taskId . ' supprimee.', null, true);
    }

    private function listerTaches(array $args) {
        $queryParams = array();
        if (!empty($args['user_id']))    $queryParams['user_id']    = $args['user_id'];
        if (!empty($args['project_id'])) $queryParams['project_id'] = $args['project_id'];
        if (!empty($args['start_date'])) $queryParams['start_date'] = $args['start_date'];

        // Si start_date definie mais pas end_date, utiliser start_date comme end_date
        // (evite de retourner toutes les taches depuis cette date)
        $endDate = !empty($args['end_date']) ? $args['end_date'] : $this->opt($args, 'start_date', '');
        if (!empty($endDate)) $queryParams['end_date'] = $endDate;

        // Protection : toujours exiger au moins un filtre pour eviter un out-of-memory
        if (empty($queryParams)) {
            return $this->error('Veuillez preciser au moins un filtre (user_id, project_id ou dates) pour lister les taches.');
        }

        $route    = 'tasks?' . http_build_query($queryParams);
        $response = $this->get($route);
        if (!$response['ok']) {
            return $this->error('Erreur liste taches : ' . $response['message']);
        }

        $taches = $response['data'];
        if (empty($taches)) {
            return $this->success('Aucune tache trouvee pour ces criteres.', array(), false);
        }

        // Limite a 50 resultats pour ne pas surcharger le contexte IA
        $total   = count($taches);
        $taches  = array_slice($taches, 0, 50);
        $tronque = ($total > 50);

        // Formate les taches de facon lisible pour l'IA
        $lines = array();
        foreach ($taches as $t) {
            $line = 'ID ' . $t['task_id']
                  . ' | ' . $t['user_id']
                  . ' | ' . $t['project_id']
                  . ' | ' . $this->formatDate($t['start_date']);
            if (!empty($t['start_hour'])) $line .= ' ' . $t['start_hour'] . '-' . $t['end_hour'];
            if (!empty($t['title']))      $line .= ' | ' . $t['title'];
            $lines[] = $line;
        }

        $msg = count($taches) . ' tache(s) affichee(s)';
        if ($tronque) $msg .= ' (sur ' . $total . ' au total, affinage du filtre recommande)';
        $msg .= ' :\n' . implode('\n', $lines);
        return $this->success($msg, $taches, false);
    }

    // -------------------------------------------------------------------
    // PROJETS
    // -------------------------------------------------------------------

    private function creerProjet(array $args) {
        $projectId = $this->req($args, 'project_id');
        $queryParams = array(
            'name'      => $this->req($args, 'name'),
            'owner_id'  => $this->req($args, 'owner_id'),
            'status_id' => $this->getDefaultStatus(false),
            'delivery'  => $this->opt($args, 'delivery', ''),
            'comment'   => $this->opt($args, 'comment',  ''),
        );

        $response = $this->put('projects/' . $projectId, $queryParams);
        if (!$response['ok']) {
            return $this->error('Erreur creation projet : ' . $response['message']);
        }

        $projet = $response['data'];
        return $this->success(
            'Projet "' . $projet['name'] . '" cree (ID : ' . $projet['id'] . ').',
            $projet,
            true
        );
    }

    // -------------------------------------------------------------------
    // CLARIFICATION (pas d'appel API)
    // -------------------------------------------------------------------

    private function demanderClarification(array $args) {
        $question = $this->opt($args, 'question', 'Pouvez-vous preciser votre demande ?');
        $options  = $this->opt($args, 'options', array());

        $msg = $question;
        if (!empty($options)) {
            $msg .= '\n' . implode('\n', array_map(function($o) { return '- ' . $o; }, $options));
        }

        return array(
            'success'   => true,
            'message'   => $msg,
            'data'      => null,
            'refresh'   => false,
            'needs_reply' => true,   // indique au chat.php qu'on attend une reponse
        );
    }

    // -------------------------------------------------------------------
    // Methodes HTTP privees
    // -------------------------------------------------------------------

    private function post($route, array $params) {
        $ch = curl_init($this->apiBase . '/' . $route);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => array(
                $this->apiKeyHeader,
                'Content-Type: application/x-www-form-urlencoded',
            ),
            CURLOPT_TIMEOUT => 10,
        ));
        return $this->parseResponse(curl_exec($ch), curl_error($ch), curl_getinfo($ch, CURLINFO_HTTP_CODE), $ch);
    }

    private function put($route, array $params) {
        $ch = curl_init($this->apiBase . '/' . $route . '?' . http_build_query($params));
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_HTTPHEADER     => array($this->apiKeyHeader),
            CURLOPT_TIMEOUT        => 10,
        ));
        return $this->parseResponse(curl_exec($ch), curl_error($ch), curl_getinfo($ch, CURLINFO_HTTP_CODE), $ch);
    }

    private function get($route) {
        $ch = curl_init($this->apiBase . '/' . $route);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array($this->apiKeyHeader),
            CURLOPT_TIMEOUT        => 10,
        ));
        return $this->parseResponse(curl_exec($ch), curl_error($ch), curl_getinfo($ch, CURLINFO_HTTP_CODE), $ch);
    }

    private function delete($route) {
        $ch = curl_init($this->apiBase . '/' . $route);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_HTTPHEADER     => array($this->apiKeyHeader),
            CURLOPT_TIMEOUT        => 10,
        ));
        return $this->parseResponse(curl_exec($ch), curl_error($ch), curl_getinfo($ch, CURLINFO_HTTP_CODE), $ch);
    }

    private function parseResponse($raw, $curlError, $httpCode, $ch) {
        curl_close($ch);

        if ($curlError) {
            return array('ok' => false, 'message' => 'cURL : ' . $curlError, 'data' => null);
        }

        // Les notices PHP/xdebug peuvent preceder le JSON.
        // On cherche le premier '{' qui produit un JSON valide.
        $decoded = json_decode($raw, true);
        if ($decoded === null) {
            $pos = strpos($raw, '{');
            while ($pos !== false) {
                $decoded = json_decode(substr($raw, $pos), true);
                if ($decoded !== null) break;
                $pos = strpos($raw, '{', $pos + 1);
            }
        }
        if ($decoded === null) {
            return array('ok' => false, 'message' => 'Reponse non JSON : ' . substr(strip_tags($raw), 0, 200), 'data' => null);
        }

        if ($httpCode >= 400 || (isset($decoded['status']) && $decoded['status'] >= 400)) {
            $msg = isset($decoded['message']) ? $decoded['message'] : 'Erreur HTTP ' . $httpCode;
            return array('ok' => false, 'message' => $msg, 'data' => null);
        }

        $data = isset($decoded['data']) ? $decoded['data'] : $decoded;
        return array('ok' => true, 'message' => '', 'data' => $data);
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function req(array $args, $key) {
        if (!isset($args[$key]) || $args[$key] === '' || $args[$key] === null) {
            throw new Exception('Parametre requis manquant : ' . $key);
        }
        return $args[$key];
    }

    private function opt(array $args, $key, $default = '') {
        return (isset($args[$key]) && $args[$key] !== null) ? $args[$key] : $default;
    }

    private function formatDate($date) {
        if (!$date) return '';
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d ? $d->format('d/m/Y') : $date;
    }

    private function success($message, $data, $refresh) {
        return array('success' => true,  'message' => $message, 'data' => $data, 'refresh' => $refresh);
    }

    private function error($message) {
        return array('success' => false, 'message' => $message, 'data' => null,  'refresh' => false);
    }
}
