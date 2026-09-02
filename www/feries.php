<?php

require('./base.inc');
require(BASE .'/../config.inc');
require(BASE .'/../includes/header.inc');

if(!$user->checkDroit('parameters_all')) {
	$_SESSION['erreur'] = 'droitsInsuffisants';
	header('Location: ../index');
	exit;
}

if(isset($_GET['changeOrderDate'])){
	if(isset($_SESSION['ferie_order']) && $_SESSION['ferie_order'] == 'DESC'){
		$orderDate = "ASC";
	} else{
		$orderDate = "DESC";
	}
} elseif(isset($_SESSION['ferie_order'])){
	$orderDate = $_SESSION['ferie_order'];
} else{
	$_SESSION['ferie_order'] = "DESC";
	$orderDate = $_SESSION['ferie_order'];
}
$_SESSION['ferie_order'] = $orderDate;

$feries = new GCollection('Ferie');
$feries->db_load(array(), array('date_ferie' => $orderDate));
$smarty->assign('feries', $feries->getSmartyData());

$fichiers = glob(BASE . '/../holidays/*.*');
$smarty->assign('fichiers', $fichiers);

$smarty->assign('xajax', $xajax->getJavascript("", "assets/js/xajax.js"));

$smarty->display('www_feries.tpl');

?>
