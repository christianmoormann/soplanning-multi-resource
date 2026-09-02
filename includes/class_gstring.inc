<?php

class GString extends GField
 {
 	/**
 	* This is the constructor required to create an object.
 	* It has to be called in every child class.
 	*/
	public function __construct($table, $name, $size, $nullable, $default, $dependantKey = NULL, $dependantTable = NULL, $dependantTableAlias = NULL)
	{
 		parent::__construct($table, $name, $size, $nullable, $default, $dependantKey, $dependantTable, $dependantTableAlias);
 	}

	/**
	 * Return the value cast to string.
	 * @param	mixed	$val
	 * @return	string|null
	 */
	public function typed($val)
	{
		if (is_null($val)) {
			return $this->nullable ? NULL : '';
		}
		return (string) $val;
	}

	/**
	 * Test if the value is valid with the type of this field.
	 * @param	string	$val
	 * @return	boolean	TRUE if $val is a valid value
	 */
	public function isValid($val)
	{
		return ((is_null($val) && $this->nullable) ||
				(!is_null($val) && (strlen($val) > 0) && (strlen($val) <= $this->size)));
	}
 }

?>