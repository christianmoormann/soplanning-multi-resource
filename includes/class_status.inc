<?php

class Status extends GObject
{

	public function __construct()
	{
		$this->table = 'planning_status';

		$this->fields['status_id'] = new GString('planning_status', 'status_id', 10, FALSE, '');
		$this->fields['nom'] = new GString('planning_status', 'nom', 50, FALSE, '');
		$this->fields['commentaire'] = new GString('planning_status', 'commentaire', 255, TRUE, NULL);
		$this->fields['affichage'] = new GString('planning_status', 'affichage', 2, TRUE, NULL);
		$this->fields['barre'] = new GString('planning_status', 'barre', 2, TRUE, NULL);
		$this->fields['gras'] = new GString('planning_status', 'gras', 2, TRUE, NULL);
		$this->fields['italique'] = new GString('planning_status', 'italique', 2, TRUE, NULL);
		$this->fields['souligne'] = new GString('planning_status', 'souligne', 2, TRUE, NULL);
		$this->fields['defaut'] = new GString('planning_status', 'defaut', 2, TRUE, 1);
		$this->fields['affichage_liste'] = new GString('planning_status', 'affichage_liste', 2, TRUE, 1);
		$this->fields['pourcentage'] = new GFloat('planning_status', 'pourcentage', 4, TRUE, 0);
		$this->fields['couleur'] = new GString('planning_status', 'couleur', 6, TRUE, '');
		$this->fields['priorite'] = new GInteger('planning_status', 'priorite', 4, TRUE, 5);
		$this->fields['inclure_dans_calcul'] = new GEnum('planning_status', 'inclure_dans_calcul', array('oui','non'), FALSE, 'oui');

		$this->primaryKeys = array('status_id');

		parent::__construct();
	}

	
	public function updateBudgetsProjet(){
		$projets = new GCollection('Projet');
		$sql = "SELECT planning_projet.*
				FROM planning_projet
				INNER JOIN planning_periode ON planning_periode.projet_id = planning_projet.projet_id
				AND planning_periode.statut_tache = " . val2sql($this->status_id);
		$projets->db_loadSQL($sql);
		while($projet = $projets->fetch()){
			$projet->updateBudgets();
		}
		return true;		
	}


	public function getAPIData(){
		$data = array();
		$data['id'] = valueAPI($this->status_id);
		$data['name'] = valueAPI($this->nom);
		$data['comment'] = valueAPI($this->commentaire);
		$data['for_project'] = (strpos($this->affichage, 'p') !== FALSE ? true : false);
		$data['for_task'] = (strpos($this->affichage, 't') !== FALSE ? true : false);
		$data['default'] = ($this->default == 1 ? true : false);
		$data['priority'] = valueAPI($this->priorite);
		$data['name'] = valueAPI($this->nom);
		$data['name'] = valueAPI($this->nom);
		$data['name'] = valueAPI($this->nom);
		return $data;
	}


}

?>