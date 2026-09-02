<?php

// This sanitizes all input for security
// GET/COOKIE: short values (IDs, tokens), FILTER_SANITIZE_FULL_SPECIAL_CHARS is fine
$_GET    = filter_input_array(INPUT_GET,    FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? [];
$_COOKIE = filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? [];

// POST: use FILTER_CALLBACK with explicit ISO-8859-1 encoding.
// FILTER_SANITIZE_FULL_SPECIAL_CHARS calls htmlspecialchars() with default_charset (UTF-8),
// which drops bytes valid in Latin-1 but invalid in UTF-8 (accented chars like e-acute).
// Using ISO-8859-1 explicitly preserves accented chars while still encoding < > & " '
function _spl_sanitize_post($val) {
    if (is_array($val))   return array_map('_spl_sanitize_post', $val);
    if (!is_string($val)) return $val;
    return htmlspecialchars($val, ENT_QUOTES, 'ISO-8859-1');
}
$_POST = array_map('_spl_sanitize_post', filter_input_array(INPUT_POST, FILTER_DEFAULT) ?? []);


// Check Public access
if (isset($_GET['public']) && (CONFIG_SOPLANNING_OPTION_ACCES == 1 || CONFIG_SOPLANNING_OPTION_ACCES == 2)) {
    $_SESSION['public']=1;
    $_SESSION['user_id']='publicspl';
    switch (CONFIG_SOPLANNING_OPTION_ACCES) {
    // Public access
    case 1:
        break;
    // Public access with key
    case 2:
        if ($_GET['cle'] != CONFIG_SECURE_KEY) {
            unset($_SESSION['public']);
            unset($_SESSION['user_id']);
            break;
        }
    // other
    // no break
    default:
        unset($_SESSION['public']);
        unset($_SESSION['user_id']);
    }
}

// Connexion if not connect (with session value)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == '') {
    // direct authentification check
    if (isset($_COOKIE['direct_auth']) && $_COOKIE['direct_auth'] != '') {
        $data = explode(';', $_COOKIE['direct_auth']);
        // Check cookies fields
        if (count($data) != 3) {
            setcookie('direct_auth', '', time() - 3600, '/');
            header(HOME_REDIRECTION);
            exit;
        }
        // Check if user exist
        $user_id = trim($data[0]);
        $user = new User();
        if (!$user->db_load(array('user_id', '=', $user_id, 'login_actif', '=', 'oui'))) {
            setcookie('direct_auth', '', time() - 3600, '/');
            header(HOME_REDIRECTION);
            exit;
        }
        // Check password cookie
        $check = hash_hmac('sha256', $user->user_id . $data[1], $user->cle);
        if ($check != $data[2]) {
            setcookie('direct_auth', '', time() - 3600, '/');
            header(HOME_REDIRECTION);
            exit;
        }
        // Check date modif
        if (!is_null($user->date_modif) && $user->date_modif > $data[1]) {
            setcookie('direct_auth', '', time() - 3600, '/');
            header(HOME_REDIRECTION);
            exit;
        }
        // Write cookie
        $now = new Datetime();
        $dateCheck = DateTime::createFromFormat('Y-m-d H:i:s', $data[1]);
        $dateCheck->modify('+45 days');
        if ($dateCheck < $now) {
            setcookie('direct_auth', '', time() - 3600, '/');
            header(HOME_REDIRECTION);
            exit;
        }

        // in order to avoid 2fa check if activated
        $_SESSION['2fa_ok'] = 'ok';

        // User init session
        $user->initPostLogin();
    } else {
        $_SESSION['message'] = 'erreur_fin_connexion';
        header(HOME_REDIRECTION);
        exit;
    }
}

// Check Google 2FA
if (CONFIG_GOOGLE_2FA_ACTIVE == '1' && !isset($_SESSION['2fa_ok']) && basename($_SERVER['REQUEST_URI']) != '2fa' && basename($_SERVER['REQUEST_URI']) != 'qrcode_2fa') {
    header('Location: ' . BASE . '/2fa');
    exit;
}

// User verification (current connexion)
$user = new User();
if (!$user->db_load(array('user_id', '=', $_SESSION['user_id']))) {
    $_SESSION['message'] = 'erreur_fin_connexion';
    header(HOME_REDIRECTION);
    exit;
}

// Smarty starting
 if (!isset($smarty)) {
     $smarty = new MySmarty();
 }
// Smarty Assign User Data
$userdata=$user->getSmartyData();
$smarty->assign('user', $userdata);

// Smarty assign Version Info
$version = new Version();
$infoVersion = $version->getVersion();
$smarty->assign('infoVersion', $infoVersion);

// Smarty Help URL
$tabAide = array('planning' => 'planning', 'ressources' => 'ressources', 'lieux' => 'lieux', 'user_list' => 'utilisateurs', 'user_groupes' => 'equipes', 'projets' => 'projets', 'groupe_list' => 'groupes');
$urlCourante = $_SERVER['REQUEST_URI'];
if (strpos($urlCourante, '/aide/') !== false) {
   $lienAide = 'index';
} else {
  foreach ($tabAide as $pageCourante => $urlAide) {
  if (strpos($urlCourante, $pageCourante) !== false) {
     $lienAide = $urlAide;
    }
  }
  if (!isset($lienAide)) {
    $lienAide = 'index';
  }
}
$smarty->assign('lienAide', BASE . '/aide/' . $lienAide);


if(is_file(BASE . '/../abo.txt')) {
	$abo = file_get_contents(BASE . '/../abo.txt');
	$smarty->assign('dateAbo', $abo);
}
