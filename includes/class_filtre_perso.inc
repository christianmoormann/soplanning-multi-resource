<?php

class Filtre_perso extends GObject
{

	public function __construct()
	{
		$this->table = 'planning_filtre_perso';
		$this->fields['filtre_perso_id'] = new GInteger('planning_filtre_perso', 'filtre_perso_id', 11, FALSE, '');
		$this->fields['filtre_perso_nom'] = new GString('planning_filtre_perso', 'filtre_perso_nom', 100, FALSE, '');
		$this->fields['filtre_perso_contenu'] = new GString('planning_filtre_perso', 'filtre_perso_contenu', 65000, TRUE, NULL);
		$this->fields['module'] = new GString('planning_filtre_perso', 'module', 50, FALSE, 'planning');
		$this->fields['user_id'] = new GString('planning_filtre_perso', 'user_id', 20, FALSE, '');
		$this->fields['date_creation'] = new GDatetime('planning_filtre_perso', 'date_creation', 19, False, date('Y-m-d H:i:s'));
		$this->primaryKeys = array('filtre_perso_id');

		parent::__construct();
	}

	public function db_save($updateData = array(), $insertData = array(), $import=false)
	{
		if (!$this->saved && !$import) {
			$result = db_query('SELECT MAX(' . $this->primaryKeys[0] . ') AS max FROM ' . $this->table);
			$new = 1;
			while ($var = db_fetch_array($result)) {
				$new = $var['max'] + 1;
			}
			$this->data[$this->primaryKeys[0]] = $new;
		}
		return parent::db_save($updateData, $insertData);
	}


}

?>