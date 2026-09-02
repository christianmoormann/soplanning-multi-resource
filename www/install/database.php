<?php

require 'base.inc';
require BASE . '/../config.inc';

// on fait ce check avant la declaration de smarty pour faire le check d'écriture du repertoire templates_c
$version = new Version();
$checkInstall = $version->checkInstall(true, false);

if($checkInstall === TRUE) {
	header('Location: ' . BASE . '/');
	exit;
}

// permet de checker que quelqu'un ne tente pas d'acc�der � la page directement
if(!isset($_SESSION['installEnCours'])) {
	header('Location: ' . BASE . '/');
	exit;
}

// on ecrase les params
if(!isset($_POST['cfgHostname']) || !isset($_POST['cfgUsername']) || !isset($_POST['cfgPassword']) || !isset($_POST['cfgDatabase'])) {
	echo 'ok';die;
	header('Location: ' . BASE . '/');
	exit;
}
$cfgHostname = addslashes($_POST['cfgHostname']);
$cfgUsername = addslashes($_POST['cfgUsername']);
$cfgPassword = addslashes($_POST['cfgPassword']);
$cfgDatabase = addslashes($_POST['cfgDatabase']);

// installation de la base
$version = new Version();
$smarty = new MySmarty();
$res = $version->importDatabase();

if($res !== TRUE) {
	$_SESSION['erreur'] = $res;
	header('Location: ' . BASE . '/install/');
	exit;
} else {
	header('Location: ' . BASE . '/install/install_ok');
	exit;
}

?>
