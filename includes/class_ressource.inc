<?php

class Ressource extends GObject
{

	public function __construct()
	{
		$this->table = 'planning_ressource';
		$this->fields['ressource_id'] = new GString('planning_ressource', 'ressource_id', 20, FALSE, '');
		$this->fields['nom'] = new GString('planning_ressource', 'nom', 50, FALSE, '');
		$this->fields['commentaire'] = new GString('planning_ressource', 'commentaire', 255, TRUE, NULL);
		$this->fields['exclusif'] = new GFloat('planning_ressource', 'exclusif', 4, TRUE, 0);
		$this->fields['couleur'] = new GString('planning_ressource', 'couleur', 6, TRUE, NULL);
		$this->primaryKeys = array('ressource_id');

		parent::__construct();
	}

	public function getAPIData(){
		$data = array();
		$groupe = new Groupe();
		$data['id'] = valueAPI($this->ressource_id);
		$data['name'] = valueAPI($this->nom);
		$data['comment'] = valueAPI($this->commentaire);
		$data['exclusive'] = valueAPI($this->exclusif);
		$data['color'] = valueAPI($this->couleur);
		return $data;
	}

	public function putAPI($resource_id, $name, $comment = '', $exclusive = ''){
		if(strlen($resource_id) > 20 || strlen($resource_id) < 1){
			throw new BadInputException('ResourceID length not valid (max 20)');
		}
		if(strlen(trim($name)) > 50 || strlen(trim($name)) <= 0){
			throw new BadInputException('Name length not valid (max 50)');
		}
		if(strlen(trim($comment)) > 255){
			throw new BadInputException('Comment length not valid (max 255)');
		}

		$this->db_load(array('ressource_id', '=', trim($resource_id)));

		$this->ressource_id = trim($resource_id);
		$this->nom = trim($name);
		$this->commentaire = (trim($comment) != '' ? trim($comment) : NULL);
		$this->exclusif = (trim($exclusive) == 'yes' ? '1' : '0');
		if(!$this->db_save()){
			throw new SaveErrorException('Unexpected error, please report maximum details to support@soplanning.org');
		}
		return true;

	}

	public function db_delete() {
		$periodes = new GCollection('Periode');
		$periodes->db_load(array('ressource_id', '=', $this->ressource_id));
		while($periode = $periodes->fetch()){
			// keep the primary pointer valid: fall back to another resource still on the task
			$autres = array();
			$res = db_query("SELECT ressource_id FROM planning_periode_ressource WHERE periode_id = " . val2sql($periode->periode_id) . " AND ressource_id <> " . val2sql($this->ressource_id) . " ORDER BY ressource_id");
			while ($row = db_fetch_array($res)) { $autres[] = $row['ressource_id']; }
			$periode->ressource_id = (count($autres) > 0) ? $autres[0] : NULL;
			$periode->db_save();
		}
		// drop junction rows for this resource (redundant with the FK cascade, safe without it)
		db_query("DELETE FROM planning_periode_ressource WHERE ressource_id = " . val2sql($this->ressource_id));
		$return = parent::db_delete();
		return $return;
	}

}

?>