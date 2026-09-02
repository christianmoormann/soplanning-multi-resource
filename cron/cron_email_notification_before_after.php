<?php

set_time_limit(5000);

define('TRACE', (PHP_SAPI === 'cli' ? (in_array('trace', $argv) || in_array('--trace', $argv)) : isset($_GET['trace'])));
//define('TRACE', true);

if (php_sapi_name() === 'cli') {
	// ligne de commande
	define('CRLF_CRON', "\n");
} else {
    // navigateur
	define('CRLF_CRON', "<br>");
}

require_once './base.inc';
require_once BASE . '/../config.inc';

$tabTranchesHoraires = explode(',', CONFIG_HOURS_DISPLAYED);
foreach ($tabTranchesHoraires as $pos => $tranche) {
	if ($pos == 0) {
		define('HORAIRE_DEBUT_MATIN', $tranche . ':00:00');
	}
	if ($tranche > 12 && !defined('HORAIRE_DEBUT_APREM')) {
		define('HORAIRE_DEBUT_APREM', $tranche . ':00:00');
	}
	if (($pos+1) == count($tabTranchesHoraires)) {
		define('HORAIRE_FIN_APREM', $tranche . ':00:00');
	}
	if ($tranche >= 12 && !defined('HORAIRE_FIN_MATIN')) {
		define('HORAIRE_FIN_MATIN', $tranche . ':00:00');
	}
}

// notif avant
$taches = new GCollection('Periode');
$sql = "
		SELECT * FROM (
		  SELECT *,
			CASE
			  WHEN duree_details IS NOT NULL THEN CONCAT(date_debut, ' ', SUBSTRING_INDEX(duree_details, ';', 1))
				WHEN duree LIKE '%PM%'         THEN CONCAT(date_debut, ' ', '" . HORAIRE_DEBUT_APREM . "')
				ELSE                                CONCAT(date_debut, ' ', '" . HORAIRE_DEBUT_MATIN . "')
			END AS datetime_debut,
			ROW_NUMBER() OVER (PARTITION BY link_id ORDER BY periode_id) AS rn
		  FROM planning_periode
		  WHERE notif_avant_actif = 'oui'
			AND notif_avant_date_envoyee IS NULL
		) t
		WHERE rn = 1
		  AND (
			   (notif_avant_delai_type = 'jour'    AND NOW() >= DATE_SUB(datetime_debut, INTERVAL notif_avant_delai_nb DAY))
			OR (notif_avant_delai_type = 'heure'   AND NOW() >= DATE_SUB(datetime_debut, INTERVAL notif_avant_delai_nb HOUR))
			OR (notif_avant_delai_type = 'minute'  AND NOW() >= DATE_SUB(datetime_debut, INTERVAL notif_avant_delai_nb MINUTE))
		  )
		ORDER BY date_debut
		";
$taches->db_loadSQL($sql);
if (TRACE) echo 'NB tasks to notify before : ' . $taches->getCount() . '<br>';
//echo $sql;die;

while($tache = $taches->fetch()) {
	if (TRACE) echo '<br><br>Current task : ' . $tache->periode_id . CRLF_CRON;
	$tache->notif_avant();	
}

//notif apres
$taches = new GCollection('Periode');
$sql = "
	SELECT * FROM (
	  SELECT *,
		CASE
		  WHEN date_fin IS NOT NULL
			THEN CONCAT(date_fin, ' ', '" . HORAIRE_FIN_APREM . "')
		  WHEN duree_details IS NOT NULL
			THEN CONCAT(date_debut, ' ', SUBSTRING_INDEX(duree_details, ';', 2))
		  WHEN duree LIKE '%AM%'
			THEN CONCAT(date_debut, ' ', '" . HORAIRE_FIN_MATIN . "')
		  ELSE
			CONCAT(date_debut, ' ', '" . HORAIRE_FIN_APREM . "')
		END AS datetime_fin,
		ROW_NUMBER() OVER (PARTITION BY link_id ORDER BY periode_id) AS rn
	  FROM planning_periode
	  WHERE notif_apres_actif = 'oui'
		AND notif_apres_date_envoyee IS NULL
		AND FIND_IN_SET(statut_tache, notif_apres_statuts_requis) = 0
	) t
	WHERE rn = 1
	  AND (
		   (notif_apres_delai_type = 'jour'    AND NOW() >= DATE_ADD(datetime_fin, INTERVAL notif_apres_delai_nb DAY))
		OR (notif_apres_delai_type = 'heure'   AND NOW() >= DATE_ADD(datetime_fin, INTERVAL notif_apres_delai_nb HOUR))
		OR (notif_apres_delai_type = 'minute'  AND NOW() >= DATE_ADD(datetime_fin, INTERVAL notif_apres_delai_nb MINUTE))
	  )
	ORDER BY date_debut
	";
$taches->db_loadSQL($sql);
if (TRACE) echo 'NB tasks to notify after : ' . $taches->getCount() . '<br>';
//echo $sql;die;

while($tache = $taches->fetch()) {
	if (TRACE) echo '<br><br>Current task : ' . $tache->periode_id . CRLF_CRON;
	$tache->notif_apres();
}


if (TRACE) echo '<br /><br /><b>End';
