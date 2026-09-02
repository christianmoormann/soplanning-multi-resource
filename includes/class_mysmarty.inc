<?php

class MySmarty {

	private $smartyObj;
	/**
     * This is the constructor required to create an object
	 * @global	array	$cfgAvailableLanguages	an array containing all available languages
	 */
    public function __construct($lang = '', $module = array()) {
		global $cfgAvailableLanguages;
		if (!is_null($lang) && $lang != '') {
			$lang = $lang;
		} elseif(isset($_SESSION['language']) && $lang === ''){
			$lang = $_SESSION['language'];
		} else {
			$lang = 'fr';
		}
		$this->smartyObj = new Smarty\Smarty;

		$this->smartyObj->error_reporting = E_ALL;

		$this->smartyObj->setTemplateDir(BASE . '/../templates/');
		$this->smartyObj->setConfigDir(BASE . '/../templates/languages/');
		$this->smartyObj->setCompileDir(COMPIL_DIR);
		$this->smartyObj->setCacheDir(BASE . '/../smarty/cache/');

		$this->smartyObj->assign('BASE', BASE);
		$this->smartyObj->assign('lang', $lang);

		// no boolean in the config file
		//$this->smartyObj->config_booleanize = FALSE;

		//$smartyObj->loadFilter('output', 'trimwhitespace');
		
		// we load modules from the config file NOW
		if (count($module) > 0) {
			foreach ($module as $key => $value){
				$this->smartyObj->configLoad($cfgAvailableLanguages[$lang][1] . '.txt', $value);
			}
		} else {
			$this->smartyObj->configLoad($cfgAvailableLanguages[$lang][1] . '.txt');
		}

		$this->smartyObj->registerPlugin("modifier","xss_protect", "xss_protect");
		$this->smartyObj->registerPlugin("modifier","urlencode", "urlencode");
		$this->smartyObj->registerPlugin("modifier","sqldate2userdate", "sqldate2userdate");
		$this->smartyObj->registerPlugin("modifier","forceISODateFormat", "forceISODateFormat");
		$this->smartyObj->registerPlugin("modifier","explode", "explode");
		$this->smartyObj->registerPlugin("modifier","sqltime2usertime", "sqltime2usertime");
		$this->smartyObj->registerPlugin("modifier","str_replace", "str_replace");
		$this->smartyObj->registerPlugin("modifier","formatMessage", "formatMessage");
		$this->smartyObj->registerPlugin("modifier","delete_session_value", "delete_session_value");
		$this->smartyObj->registerPlugin("modifier","json_encode", "json_encode");
		$this->smartyObj->registerPlugin("modifier","substr", "substr");
		$this->smartyObj->registerPlugin("modifier","buttonFontColor", "buttonFontColor");
		$this->smartyObj->registerPlugin("modifier","sqldatetime2userdatetime", "sqldatetime2userdatetime");
		$this->smartyObj->registerPlugin("modifier","strpos", "strpos");
		$this->smartyObj->registerPlugin("modifier","formaterNombreDecimal", "formaterNombreDecimal");
		$this->smartyObj->registerPlugin("modifier","strtoupper", "strtoupper");
		$this->smartyObj->registerPlugin("modifier","basename", "basename");
		$this->smartyObj->registerPlugin("modifier","sprintf", "sprintf");
		$this->smartyObj->registerPlugin("modifier","usertime2sqltime", "usertime2sqltime");
		$this->smartyObj->registerPlugin("modifier","json_decode", "json_decode");
		$this->smartyObj->registerPlugin("modifier","heures2Jours", "heures2Jours");
		$this->smartyObj->registerPlugin("modifier","print_r", "print_r");
		$this->smartyObj->registerPlugin("modifier","var_dump", "var_dump");
		$this->smartyObj->registerPlugin("modifier","intval", "intval");

	}

	/**
	 * returns the content of Smarty's output buffer instead of displaying it
	 * @param	string	$resourceName	the name of the template
	 * @param	integer	$cacheId		l'id du cache
	 * @param	integer	$compileId		l'id de la compil
	 * @return	string					some html code
	 */
    public function getHtml($resourceName, $cacheId = NULL, $compileId = NULL)
    {
        return $this->smartyObj->fetch($resourceName, $cacheId, $compileId, FALSE);
    }

	public function assign($name, $val){
		$this->smartyObj->assign($name, $val);
	}

	public function display($template){
		return $this->smartyObj->display($template);
	}

	public function getConfigVars($var){
		$text = $this->smartyObj->getConfigVars($var);
		if ($text == '') {
			return '#' . $var . '#';
		} else {
			return $text;
		}
	}

	public function clearAllCache(){
		return $this->smartyObj->clearAllCache();
	}
	public function clearCompiledTemplate(){
		return $this->smartyObj->clearCompiledTemplate();
	}
	public function configLoad($fichier){
		return $this->smartyObj->configLoad($fichier);
	}
}

?>