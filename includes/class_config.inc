<?php

class Config extends GObject
{

	public function __construct()
	{
		$this->table = 'planning_config';
		
		$this->fields['cle'] = new GString('planning_config', 'cle', 100, FALSE, '');
		$this->fields['valeur'] = new GString('planning_config', 'valeur', 1000, TRUE, NULL);
		$this->primaryKeys = array('cle');

		parent::__construct();
	}
}

?>