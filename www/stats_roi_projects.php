<?php

require('./base.inc');
require(BASE .'/../config.inc');
require(BASE .'/../includes/header.inc');

if(!$user->checkDroit('stats_roi_projects')) {
	$_SESSION['erreur'] = 'droitsInsuffisants';
	header('Location: index');
	exit;
}

// PARAMÈTRES
$dateDebut = new DateTime();
$pattern = '/^([1-9]|0[1-9]|1[0-9]|2[0-9]|3[01])\/([1-9]|0[1-9]|1[012])\/(19[0-9][0-9]|20[0-9][0-9])$/';

if(isset($_POST['go'])) {
	$_SESSION['stats_roi_projects'] = $_POST;
} elseif(isset($_SESSION['stats_roi_projects'])) {

} else {
	$_SESSION['stats_roi_projects'] = array();
}

if (isset($_REQUEST['statut_projet']) && is_array($_REQUEST['statut_projet'])) {
	$statutsProjetsChoisis = $_REQUEST['statut_projet'];
} elseif (isset($_SESSION['statut_projet']) && is_array($_SESSION['statut_projet'])) {
	$statutsProjetsChoisis = $_SESSION['statut_projet'];
} else {
	$statutsProjetsChoisis = $_SESSION['status_projets_par_defaut'];
}

if(isset($_REQUEST['rechercheProjet'])){
	if($_REQUEST['rechercheProjet'] != ''){
		$search = $_REQUEST['rechercheProjet'];
		$_SESSION['projets_roi_search'] = $search;
	} else{
		unset($_SESSION['projets_roi_search']);
		$search = '';
	}
} elseif (isset($_SESSION['projets_roi_search'])) {
	$search = $_SESSION['projets_roi_search'];
} else {
	$search = '';
}

$_SESSION['statut_projet'] = $statutsProjetsChoisis;
setcookie('statut_projet', json_encode($statutsProjetsChoisis), time()+60*60*24*500, '/');
$smarty->assign('statutsProjetsChoisis', $statutsProjetsChoisis);

$status = new GCollection('Status');
$status->db_load(array('affichage', 'IN', array('p','tp')), array('priorite' => 'ASC'));
$smarty->assign('listeStatusProjets', $status->getSmartyData());

//$chaineFiltre = implode("','", $statutsChoisis));
$projets = new GCollection('Projet');
$sql = "SELECT planning_projet.*, planning_groupe.nom AS nom_groupe
			FROM planning_projet
			LEFT JOIN planning_groupe ON planning_groupe.groupe_id = planning_projet.groupe_id
			WHERE 0 = 0
			AND planning_projet.statut in ('" . implode("','", array_map('addslashes', $statutsProjetsChoisis)) . "')";
if($search != ''){
	$searchParts = explode( ' ', $search);

	$isLike = array('0');

	foreach($searchParts as $word){
		$isLike[] = 'planning_projet.nom LIKE '.val2sql('%' . $word . '%');
		$isLike[] = 'planning_projet.iteration LIKE '.val2sql('%' . $word . '%');
		$isLike[] = 'planning_projet.projet_id LIKE '.val2sql('%' . $word . '%');
		$isLike[] = 'planning_groupe.nom LIKE '.val2sql('%' . $word . '%');
	}
	$isLike = implode(" OR ", $isLike);
	$sql .= "AND (" . $isLike . ") ";
}
$sql .=" ORDER BY nom_groupe ASC, planning_projet.nom ASC";
$projets->db_loadSQL($sql);

$smarty->assign('projets', $projets->getSmartyData());

$smarty->assign('rechercheProjet', $search);


$smarty->assign('xajax', $xajax->getJavascript("", "assets/js/xajax.js"));

$smarty->display('www_stats_roi_projects.tpl');

?>