<?php

require 'base.inc';
require BASE . '/../config.inc';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Max-Age: 1000");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Origin, Cache-Control, Pragma, Authorization, Accept, Accept-Encoding");
header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS, DELETE");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

class FailedAuthException extends Exception{}
class BadInputException extends Exception{}
class RessourceNotFoundException extends Exception{}
class SaveErrorException extends Exception{}

// Token validity: 30 days
define('MOBILE_TOKEN_EXPIRY', 60 * 60 * 24 * 30);

/**
 * Generate a stateless Bearer token for a user.
 * Format (base64): user_id|timestamp|hmac_sha256(user_id|timestamp, cle)
 */
function generateMobileToken($user) {
    $payload = $user->user_id . '|' . time();
    $sig = hash_hmac('sha256', $payload, $user->cle);
    return base64_encode($payload . '|' . $sig);
}

/**
 * Verify and decode a Bearer token. Returns the User object or throws.
 */
function verifyMobileToken($token) {
    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        throw new FailedAuthException('Invalid token format');
    }
    $parts = explode('|', $decoded);
    if (count($parts) !== 3) {
        throw new FailedAuthException('Invalid token structure');
    }
    list($user_id, $timestamp, $sig) = $parts;

    if (!ctype_digit($timestamp) || time() - (int)$timestamp > MOBILE_TOKEN_EXPIRY) {
        throw new FailedAuthException('Token expired');
    }

    $user = new User();
    if (!$user->db_load(array('user_id', '=', $user_id))) {
        throw new FailedAuthException('User not found');
    }

    $expected = hash_hmac('sha256', $user_id . '|' . $timestamp, $user->cle);
    if (!hash_equals($expected, $sig)) {
        throw new FailedAuthException('Invalid token signature');
    }

    if ($user->login_actif == 'non') {
        throw new FailedAuthException('Account disabled');
    }

    return $user;
}

/**
 * Extract and verify Bearer token from Authorization header.
 */
function getMobileAuth() {
    // getallheaders() fallback for CGI environments
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
    }

    // Case-insensitive search for Authorization header
    $authHeader = '';
    foreach ($headers as $k => $v) {
        if (strtolower($k) === 'authorization') {
            $authHeader = $v;
            break;
        }
    }

    if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
        throw new FailedAuthException('Missing or invalid Authorization header');
    }

    $token = substr($authHeader, 7);
    return verifyMobileToken($token);
}

// doc : https://github.com/marcj/php-rest-service

use RestService\Server;

$server = Server::create('/')

    // ------------------------------------------------------------------
    // TEST
    // ------------------------------------------------------------------

    ->addGetRoute('test', function() {
        return array('status' => 'ok', 'message' => 'Mobile API working');
    })

    // ------------------------------------------------------------------
    // AUTH
    // ------------------------------------------------------------------

    /**
     * POST /auth/login
     * Body: login, password
     * Returns: { token, user }
     */
    ->addPostRoute('auth/login', function($login, $password) {
        if (empty(trim($login)) || empty(trim($password))) {
            throw new BadInputException('Login and password are required');
        }

        $user = new User();
        if (!$user->db_load(array('login', '=', trim($login)))) {
            throw new FailedAuthException('Invalid credentials');
        }

        if (!$user->verifyPassword($password, $user->password)) {
            throw new FailedAuthException('Invalid credentials');
        }

        if ($user->login_actif == 'non') {
            throw new FailedAuthException('Account disabled');
        }

        // Transparent migration: rehash to bcrypt if still SHA1
        if ($user->needsRehash($user->password)) {
            $user->password = $user->hashPassword($password);
            $user->db_save();
        }

        // Update last login date
        $user->date_dernier_login = date('Y-m-d H:i:s');
        $user->db_save();

        $token = generateMobileToken($user);

        return array(
            'token' => $token,
            'expires_in' => MOBILE_TOKEN_EXPIRY,
            'user' => $user->getAPIData(),
        );
    })

    // ------------------------------------------------------------------
    // CURRENT USER
    // ------------------------------------------------------------------

    /**
     * GET /me
     * Returns: current user profile
     */
    ->addGetRoute('me', function() {
        $user = getMobileAuth();
        return $user->getAPIData();
    })

    // ------------------------------------------------------------------
    // TASKS
    // ------------------------------------------------------------------

    /**
     * GET /tasks?user_id=&project_id=&start_date=&end_date=
     * Defaults to current user's tasks.
     */
    ->addGetRoute('tasks', function($user_id = '', $project_id = '', $start_date = '', $end_date = '') {
        $currentUser = getMobileAuth();

        $criterias = array();

        // Default: show only current user's tasks
        $filterUserId = ($user_id != '' ? $user_id : $currentUser->user_id);

        $userTmp = new User();
        if (!$userTmp->db_load(array('user_id', '=', $filterUserId))) {
            throw new BadInputException('user_id unknown');
        }
        $criterias[] = 'user_id';
        $criterias[] = '=';
        $criterias[] = $filterUserId;

        if ($project_id != '') {
            $projet = new Projet();
            if (!$projet->db_load(array('projet_id', '=', $project_id))) {
                throw new BadInputException('project_id unknown');
            }
            $criterias[] = 'projet_id';
            $criterias[] = '=';
            $criterias[] = $project_id;
        }

        if ($start_date != '') {
            if (!controlDateSql($start_date)) {
                throw new BadInputException('start_date not valid (YYYY-MM-DD)');
            }
            $criterias[] = 'date_debut';
            $criterias[] = '>=';
            $criterias[] = $start_date;
        }

        if ($end_date != '') {
            if (!controlDateSql($end_date)) {
                throw new BadInputException('end_date not valid (YYYY-MM-DD)');
            }
            $criterias[] = 'date_debut';
            $criterias[] = '<=';
            $criterias[] = $end_date;
        }

        $taches = new GCollection('Periode');
        $taches->db_load($criterias, array('date_debut' => 'ASC', 'periode_id' => 'ASC'));
        $data = array();
        while ($tache = $taches->fetch()) {
            $data[] = $tache->getAPIData();
        }
        return $data;
    })

    /**
     * GET /tasks/{id}
     */
    ->addGetRoute('tasks/([0-9]+)', function() {
        getMobileAuth();
        $args = func_get_args();
        $periode_id = $args[0];
        $periode = new Periode();
        if (!$periode->db_load(array('periode_id', '=', trim($periode_id)))) {
            throw new RessourceNotFoundException('Task not found');
        }
        return $periode->getAPIData();
    })

    /**
     * POST /tasks  — create or update a task
     * Body: task_id, user_id, project_id, link_id, start_date [, end_date, start_time, end_time,
     *        duration, status_id, title, comment, link, resource_id, place_id, milestone,
     *        custom_field, creator_id]
     */
    ->addPostRoute('tasks', function($task_id, $user_id, $project_id, $link_id, $start_date, $end_date = '', $start_time = '', $end_time = '', $duration = '', $status_id = '', $title = '', $comment = '', $link = '', $resource_id = '', $place_id = '', $milestone = '', $custom_field = '', $creator_id = '') {
        getMobileAuth();
        $periode = new Periode();
        try {
            call_user_func_array(array($periode, 'putAPI'), func_get_args());
        } catch (Exception $e) {
            $errorCommand = get_class($e);
            throw new $errorCommand($e->getMessage());
        }
        return $periode->getAPIData();
    })

    /**
     * DELETE /tasks/{id}
     */
    ->addDeleteRoute('tasks/([0-9]+)', function() {
        getMobileAuth();
        $args = func_get_args();
        $periode_id = $args[0];
        $periode = new Periode();
        if (!$periode->db_load(array('periode_id', '=', trim($periode_id)))) {
            throw new RessourceNotFoundException('Task not found');
        }
        $periode->db_delete();
        return $periode->getAPIData();
    })

    // ------------------------------------------------------------------
    // PROJECTS
    // ------------------------------------------------------------------

    /**
     * GET /projects?name=
     */
    ->addGetRoute('projects', function($name = '') {
        getMobileAuth();
        $sql = "SELECT * FROM planning_projet WHERE 0=0 ";
        if ($name != '') {
            $sql .= "AND nom LIKE " . val2sql('%' . $name . '%');
        }
        $sql .= "ORDER BY nom ASC";
        $projets = new GCollection('Projet');
        $projets->db_loadSQL($sql);
        $data = array();
        while ($projet = $projets->fetch()) {
            $data[] = $projet->getAPIData();
        }
        return $data;
    })

    /**
     * GET /projects/{id}
     */
    ->addGetRoute('projects/([a-zA-Z0-9]+)', function($project_id) {
        getMobileAuth();
        $projet = new Projet();
        if (!$projet->db_load(array('projet_id', '=', $project_id))) {
            throw new RessourceNotFoundException('Project not found');
        }
        return $projet->getAPIData();
    })

    // ------------------------------------------------------------------
    // USERS (visible users list, for assignment in mobile app)
    // ------------------------------------------------------------------

    /**
     * GET /users?name=
     */
    ->addGetRoute('users', function($name = '') {
        getMobileAuth();
        $sql = "SELECT * FROM planning_user
                WHERE user_id <> 'publicspl'
                AND visible_planning = 'oui'
                AND login_actif = 'oui' ";
        if ($name != '') {
            $sql .= "AND nom LIKE " . val2sql('%' . $name . '%');
        }
        $sql .= "ORDER BY nom ASC";
        $users = new GCollection('User');
        $users->db_loadSQL($sql);
        $data = array();
        while ($userTmp = $users->fetch()) {
            $data[] = $userTmp->getAPIData();
        }
        return $data;
    })

    // ------------------------------------------------------------------
    // STATUSES
    // ------------------------------------------------------------------

    /**
     * GET /statuses
     */
    ->addGetRoute('statuses', function() {
        getMobileAuth();
        $statuses = new GCollection('Status');
        $statuses->db_load(array(), array('priorite' => 'ASC'));
        $data = array();
        while ($status = $statuses->fetch()) {
            $data[] = $status->getAPIData();
        }
        return $data;
    })

    ;

// ERROR MANAGEMENT
$server->setExceptionHandler(function(\Exception $e) use ($server) {
    if ($e instanceof BadInputException) {
        $server->getClient()->sendResponse('400', array(
            'error' => get_class($e),
            'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1')
        ));
    }
    if ($e instanceof FailedAuthException) {
        $server->getClient()->sendResponse('401', array(
            'error' => get_class($e),
            'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1')
        ));
    }
    if ($e instanceof RessourceNotFoundException) {
        $server->getClient()->sendResponse('404', array(
            'error' => get_class($e),
            'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1')
        ));
    }
    if ($e instanceof SaveErrorException) {
        $server->getClient()->sendResponse('500', array(
            'error' => get_class($e),
            'message' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'ISO-8859-1')
        ));
    }
});

$server->run();
