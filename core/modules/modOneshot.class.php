<?php
/* Copyright (C) 2026 Anatole Conseil
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modOneshot.class.php
 * \ingroup oneshot
 * \brief   Module descriptor for Oneshot
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Class modOneshot
 *
 * Runs small one-off PHP scripts (data fixes, batch cancellations, ...)
 * from the admin UI, with the Dolibarr context ($db, $user, $conf) loaded.
 * Scripts live in DOL_DATA_ROOT/oneshot/, outside the web root.
 */
class modOneshot extends DolibarrModules
{
	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		// Anatole Conseil range 193940-193949 (wiki List of modules id)
		$this->numero = 193947;
		$this->rights_class = 'oneshot';
		$this->family = 'technic';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Oneshot - Run one-off PHP scripts from the admin UI";
		$this->descriptionlong = "Paste a small PHP script, run it with arguments against the live Dolibarr context (\$db, \$user, \$conf) and read its output. For data fixes and batch operations that do not deserve a module.";
		$this->editor_name = 'Anatole Conseil';
		$this->editor_url = '';
		$this->version = '0.1.1';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'technic';

		$this->module_parts = array();

		$this->dirs = array('/oneshot');
		$this->config_page_url = array('index.php@oneshot');

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('oneshot@oneshot');
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);

		$this->const = array();

		if (!isset($conf->oneshot)) {
			$conf->oneshot = new stdClass();
			$conf->oneshot->enabled = 0;
		}
		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();
		$this->rights = array();
		$this->menu = array();
	}

	/**
	 * @param string $options Options when enabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$r = $this->_init(array(), $options);
		// Seed documents/oneshot with the shipped examples (never overwrite an existing script)
		$dst = DOL_DATA_ROOT.'/oneshot';
		dol_mkdir($dst);
		foreach (glob(__DIR__.'/../../examples/*.php') ?: array() as $f) {
			if (!file_exists($dst.'/'.basename($f))) {
				copy($f, $dst.'/'.basename($f));
			}
		}
		return $r;
	}

	/**
	 * @param string $options Options when disabling module ('', 'noboxes')
	 * @return int 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
