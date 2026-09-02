<?php

require 'base.inc';
require BASE . '/../config.inc';

// http://ltb-project.org/wiki/documentation/self-service-password
function retrieve_ldap_password($login, $password){

    global $ldapUrl, $ldapBase, $ldapFilter, $ldapBindUser, $ldapBindPassword, $ldap_use_tls;

    # Connect to LDAP
    $ldap = @ldap_connect($ldapUrl);
    @ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    @ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

	if ($ldap_use_tls) {
		if(!@ldap_start_tls($ldap)){
			$result = "ldaperror";
			$err = "LDAP - failed to start tls";
			die("LDAP - failed to start tls");
		}    
	}

    # Bind
    $bind = @ldap_bind($ldap, $ldapBindUser, $ldapBindPassword);
    
	$errno = @ldap_errno($ldap);
    if ( $errno ) {
        $result = "ldaperror";
        $err = "LDAP - Bind error $errno  (".@ldap_error($ldap).")";
        die("LDAP - Bind error $errno  (".@ldap_error($ldap).")");
    }
    else {
		# Search for user
		$login = ldap_escape($login, '', LDAP_ESCAPE_FILTER);
		$ldapFilter = str_replace("{login}", $login, $ldapFilter);
		$search = @ldap_search($ldap, $ldapBase, $ldapFilter);

		$errno = @ldap_errno($ldap);
		if ( $errno ) {
			$result = "ldaperror";
			$err = "LDAP - Search error $errno  (".@ldap_error($ldap).")";
		}
		else {
			# Get user DN
			$entry = @ldap_first_entry($ldap, $search);
			$userdn = @ldap_get_dn($ldap, $entry);

			if( !$userdn ) {
				$result = "badcredentials";
				$err = "LDAP - User $login not found";
			}
			else {    
				# Bind with password
				//$bind = @ldap_bind($ldap, $userdn, $password);
				$bind = @ldap_bind($ldap, $userdn, mb_convert_encoding($password,'UTF-8' , 'ISO-8859-1'));    
				$errno = @ldap_errno($ldap);
				if ( $errno ) {
					$result = "badcredentials";
					$err = "LDAP - Bind user error $errno  (".@ldap_error($ldap).")";
				} else {
					// Everything is OK ;)
					$result = "OK";
					$err = "";
				}
			}
		}
	}
	@ldap_close($ldap);
	if ($result == "OK") {
		return True;
	}
	else {
		return False;
	}		
}

// deconnexion
if(isset($_GET['action']) && $_GET['action'] == 'logout') {
	// Audit
	if (CONFIG_SOPLANNING_OPTION_AUDIT == 1 && CONFIG_SOPLANNING_OPTION_AUDIT_CONNEXIONS == 1)
	{
		logAction('D');
	}
	
	unset($_SESSION['user_id']);
	session_regenerate_id();
	@session_destroy();
	setcookie('direct_auth', '', time() - 3600, '/');

	if(CONFIG_LOGOUT_REDIRECT != '') {
		header('Location: ' . CONFIG_LOGOUT_REDIRECT);
		exit;
	} else {
		header('Location: ../index' . (isset($_GET['language']) ? '?language=' . $_GET['language'] : ''));
		exit;
	}
}

if(isset($_GET['direct_periode_id']) && $_GET['direct_periode_id'] > 0) {
	// direct access from email to a specific task
	if(!isset($_GET['date'])) {
		$_SESSION['message'] = 'Invalid URL';
		header('Location: index');
		exit;
	}
	if(!isset($_GET['hash']) || $_GET['hash'] != md5($_GET['direct_periode_id'] . '--' . $_GET['date'] . '--' . CONFIG_SECURE_KEY)) {
		$_SESSION['message'] = 'Invalid URL';
		header('Location: index');
		exit;
	}
	
	$_SESSION['direct_periode_id'] = round($_GET['direct_periode_id']);
	header('Location: ../index');
	exit;
}

//login 
if(!isset($_GET['google_code']) && (!isset($_POST['login']) || !isset($_POST['password']) || $_POST['login'] == '' || $_POST['password'] == '' || !isset($_POST['crsf']))) {
	$_SESSION['message'] = 'erreur_bad_login';
	header('Location: ../index');
	exit;
}

// ldap password
if($ldapLogin) {
    if(!isset($_POST['password']) || !retrieve_ldap_password($_POST['login'], $_POST['password'])) {
        $_SESSION['message'] = 'erreur_bad_login';
        header('Location: ../index');
        exit;
    }
}

$user = New User();
 // AD account
if($ADLogin && ($_POST['login'] != 'admin')) {
	if(!active_directory_login($_POST['login'], $_POST['password'])){
		$_SESSION['message'] = 'erreur_bad_login';
		header('Location: ../index');
		exit;
	}
    if(!$user->db_load(array('login', '=', $_POST['login']))) {
        $_SESSION['message'] = 'erreur_bad_login';
        header('Location: ../index');
        exit;
    }
} elseif($ldapLogin && ($_POST['login'] != 'admin')) {
    if(!$user->db_load(array('login', '=', $_POST['login']))) {
        $_SESSION['message'] = 'erreur_bad_login';
        header('Location: ../index');
        exit;
    }
} elseif (CONFIG_GOOGLE_OAUTH_ACTIVE == 1 && isset($_GET['google_code'])) {
	$google_client = new Google_oauth();
	
	try {
		$email = $google_client->getAccess($_GET['google_code']);
	}
	catch(Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        header('Location: ../index');
		exit;
	}
	$users = new GCollection('User');
	$users->db_load(array('email', '=', $email));
	if($users->getCount() == 0){
        $_SESSION['message'] = 'google_sso_error_no_account_for_email';
        header('Location: ../index');
		exit;
	}
	if($users->getCount() > 1){
        $_SESSION['message'] = 'google_sso_error_several_accounts_for_email';
        header('Location: ../index');
		exit;
	}
	$user = $users->fetch();

} else {
	// classic login
	if(!$user->db_load(array('login', '=', $_POST['login']))) {
		$_SESSION['message'] = 'erreur_bad_login';
		header('Location: ../index');
		exit;
	}
	if(!$user->verifyPassword($_POST['password'], $user->password)) {
		$_SESSION['message'] = 'erreur_bad_login';
		header('Location: ../index');
		exit;
	}
	// Migration transparente : re-hasher en bcrypt si encore en SHA1
	if($user->needsRehash($user->password)) {
		$user->password = $user->hashPassword($_POST['password']);
		$user->db_save();
	}
}

if($user->login_actif == 'non' || $_POST['crsf'] != $_SESSION['CRSF']){
	$_SESSION['message'] = 'erreur_bad_login';
	header('Location: ../index');
	exit;	
}

if(isset($_POST['remember']) && $user->user_id != 'publicspl'){
	$timestamp = date('Y-m-d H:i:s');
	$sig = hash_hmac('sha256', $user->user_id . $timestamp, $user->cle);
	$cle = $user->user_id . ';' . $timestamp . ';' . $sig;
	setcookie('direct_auth', $cle, [
		'expires'  => time() + 60*60*24*30,
		'path'     => '/',
		'secure'   => false,
		'httponly' => true,
		'samesite' => 'Strict',
	]);
}

$user->initPostLogin();

// Pr�f�rence de vue planning
header('Location: ../' . $user->vueDefaut());
exit;
?>