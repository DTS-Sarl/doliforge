<?php
/* Copyright (C) 2024 DTS SARL
 * Module : monmodule
 * Remplacer : monmodule → nom_technique, Monmodule → CamelCase, MONMODULE → MAJUSCULES
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modMonmodule extends DolibarrModules
{
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// ---- Identification ----
		$this->numero     = 500000;           // ← CHANGER par un numéro unique
		$this->rights_class = 'monmodule';
		$this->family     = 'hr';             // crm, financial, hr, projects, products, ecm, technic, other
		$this->module_position = 500;
		$this->name       = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'MonModuleDescription';
		$this->version    = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto      = 'generic';

		// ---- Dépendances ----
		$this->depends     = [];
		$this->requiredby  = [];
		$this->conflictwith = [];
		$this->langfiles   = ['monmodule@monmodule'];
		$this->phpmin      = [7, 4];

		// ---- Assets ----
		$this->module_parts = [
			'css'      => ['/monmodule/css/monmodule.css'],
			'js'       => ['/monmodule/js/monmodule.js'],
			'hooks'    => [],       // ex: ['thirdpartycard', 'invoicecard']
			'triggers' => 0,        // 1 si triggers actifs
		];

		// ---- Constantes ----
		$this->const = [];

		// ---- Droits ----
		$r = 0;
		$this->rights[$r][0] = $this->numero.'01';
		$this->rights[$r][1] = 'MonModuleReadObjects';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'monobjet';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero.'02';
		$this->rights[$r][1] = 'MonModuleWriteObjects';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'monobjet';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero.'03';
		$this->rights[$r][1] = 'MonModuleDeleteObjects';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'monobjet';
		$this->rights[$r][5] = 'delete';
		$r++;

		// ---- Menus ----
		$r = 0;

		// Menu gauche — groupe
		$this->menu[$r] = [
			'fk_menu'  => 0,
			'type'     => 'top',
			'titre'    => 'MonModuleMenu',
			'prefix'   => img_picto('', $this->picto, 'class="pictofixedwidth"'),
			'mainmenu' => 'monmodule',
			'leftmenu' => '',
			'url'      => '/monmodule/monobjetlist.php',
			'langs'    => 'monmodule@monmodule',
			'position' => 100,
			'enabled'  => 'isModEnabled("monmodule")',
			'perms'    => '$user->hasRight("monmodule", "monobjet", "read")',
			'target'   => '',
			'user'     => 0,
		];
		$r++;

		// Entrée liste
		$this->menu[$r] = [
			'fk_menu'  => 'fk_mainmenu=monmodule',
			'type'     => 'left',
			'titre'    => 'MonModuleMenuList',
			'mainmenu' => 'monmodule',
			'leftmenu' => 'monobjetlist',
			'url'      => '/monmodule/monobjetlist.php',
			'langs'    => 'monmodule@monmodule',
			'position' => 110,
			'enabled'  => 'isModEnabled("monmodule")',
			'perms'    => '$user->hasRight("monmodule", "monobjet", "read")',
			'target'   => '',
			'user'     => 0,
		];
		$r++;

		// Entrée nouveau
		$this->menu[$r] = [
			'fk_menu'  => 'fk_mainmenu=monmodule,fk_leftmenu=monobjetlist',
			'type'     => 'left',
			'titre'    => 'NewMonObjet',
			'mainmenu' => 'monmodule',
			'leftmenu' => 'monobjet_new',
			'url'      => '/monmodule/monobjetcard.php?action=create',
			'langs'    => 'monmodule@monmodule',
			'position' => 111,
			'enabled'  => 'isModEnabled("monmodule")',
			'perms'    => '$user->hasRight("monmodule", "monobjet", "write")',
			'target'   => '',
			'user'     => 0,
		];
		$r++;

		// ---- Boîtes tableau de bord (optionnel) ----
		$this->boxes = [];

		// ---- Cronjobs (optionnel) ----
		$this->cronjobs = [];

		// ---- Exports (optionnel) ----
		$this->export_code        = [];
		$this->export_label       = [];
		$this->export_permission  = [];
		$this->export_fields_array = [];
	}

	public function init($options = '')
	{
		global $conf, $langs;

		$result = $this->_load_tables('/monmodule/sql/');
		if ($result < 0) return -1;

		return $this->_init([], $options);
	}

	public function remove($options = '')
	{
		$sql = [];
		return $this->_remove($sql, $options);
	}
}
