<?php
/* Copyright (C) 2024 DTS SARL
 * Hooks du module monmodule — actions_monmodule.class.php
 *
 * Déclaré dans le descripteur :
 *   $this->module_parts['hooks'] = ['thirdpartycard', 'invoicecard', 'thirdpartylist'];
 *
 * Chaque méthode publique correspond à un point de hook Dolibarr.
 * Toujours vérifier $parameters['context'] en début de méthode.
 */

class ActionsMonModule
{
	/** @var array Données à remonter au hookmanager */
	public $results = [];

	/** @var string HTML à injecter dans la page */
	public $resprints = '';

	/** @var array Erreurs */
	public $errors = [];

	// ============================================================
	// Hook : onglet supplémentaire sur la fiche tiers
	// Contexte : thirdpartycard
	// ============================================================

	/**
	 * Ajouter un onglet "Mes Objets" sur la fiche tiers
	 */
	public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $user;

		if (!in_array('thirdpartycard', explode(':', $parameters['context']))) return 0;
		if (!isModEnabled('monmodule')) return 0;
		if (!$user->hasRight('monmodule', 'monobjet', 'read')) return 0;

		$langs->load('monmodule@monmodule');

		// Compter les objets liés à ce tiers (optionnel)
		// $nbItems = 0;

		$this->results[] = [
			'url'   => dol_buildpath('/monmodule/monobjetlist.php', 1)
			           .'?fk_soc='.$object->id,
			'label' => $langs->trans('MonObjets'),
			// 'badge'      => $nbItems ?: null,
			// 'badgeClass' => 'badgeneutral',
			'key'   => 'monobjet',
		];
		return 1;
	}

	// ============================================================
	// Hook : champs supplémentaires sur un formulaire (ex : facture)
	// Contexte : invoicecard
	// ============================================================

	/**
	 * Afficher des champs supplémentaires sur la fiche facture
	 */
	public function formObjectOptions(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!in_array('invoicecard', explode(':', $parameters['context']))) return 0;
		if (!isModEnabled('monmodule')) return 0;

		$langs->load('monmodule@monmodule');

		if ($action == 'create' || $action == 'edit') {
			// Champ en mode saisie
			$val = dol_escape_htmltag(GETPOST('monchamp', 'alphanohtml'));
			$this->resprints .= '<tr class="oddeven">';
			$this->resprints .= '<td>'.$langs->trans('MonChamp').'</td>';
			$this->resprints .= '<td><input type="text" name="monchamp" class="minwidth200" value="'.$val.'"></td>';
			$this->resprints .= '</tr>';
		} else {
			// Champ en lecture — lire depuis la table du module
			$val = '';
			$this->resprints .= '<tr class="oddeven">';
			$this->resprints .= '<td>'.$langs->trans('MonChamp').'</td>';
			$this->resprints .= '<td>'.dol_escape_htmltag($val).'</td>';
			$this->resprints .= '</tr>';
		}
		return 0;
	}

	/**
	 * Persister les champs supplémentaires de la facture
	 */
	public function doActions(&$parameters, &$object, &$action, $hookmanager)
	{
		global $db, $user;

		if (!in_array('invoicecard', explode(':', $parameters['context']))) return 0;

		if (($action == 'add' || $action == 'update') && $object->id > 0) {
			if (!$user->hasRight('monmodule', 'monobjet', 'write')) return 0;

			$val = $db->escape(GETPOST('monchamp', 'alphanohtml'));
			$sql  = "INSERT INTO ".MAIN_DB_PREFIX."monmodule_invoice_extra (fk_invoice, monchamp)";
			$sql .= " VALUES (".((int) $object->id).", '".$val."')";
			$sql .= " ON DUPLICATE KEY UPDATE monchamp = '".$val."'";
			$db->query($sql);
		}
		return 0;
	}

	// ============================================================
	// Hook : colonne supplémentaire sur la liste des tiers
	// Contexte : thirdpartylist
	// ============================================================

	/**
	 * En-tête de colonne sur la liste des tiers
	 */
	public function printFieldListTitle(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (!in_array('thirdpartylist', explode(':', $parameters['context']))) return 0;
		$langs->load('monmodule@monmodule');

		$this->resprints = '<th class="liste_titre">'.$langs->trans('MonChamp').'</th>';
		return 0;
	}

	/**
	 * Valeur de colonne par ligne dans la liste des tiers
	 */
	public function printFieldListValue(&$parameters, &$object, &$action, $hookmanager)
	{
		global $db;

		if (!in_array('thirdpartylist', explode(':', $parameters['context']))) return 0;

		// Lire la valeur depuis la table du module
		$val = '';
		// $sql = "SELECT monchamp FROM ".MAIN_DB_PREFIX."monmodule_soc_extra WHERE fk_soc=".(int)$object->id;
		// $res = $db->query($sql);
		// if ($res && $row = $db->fetch_object($res)) $val = $row->monchamp;

		$this->resprints = '<td>'.dol_escape_htmltag($val).'</td>';
		return 0;
	}

	/**
	 * Jointure SQL additionnelle sur la liste des tiers (optionnel)
	 */
	public function printFieldListWhere(&$parameters, &$object, &$action, $hookmanager)
	{
		if (!in_array('thirdpartylist', explode(':', $parameters['context']))) return 0;

		// Exemple : jointure avec une table du module
		// $this->resprints = ' LEFT JOIN '.MAIN_DB_PREFIX.'monmodule_soc_extra AS mme'
		//     .' ON mme.fk_soc = t.rowid';
		return 0;
	}
}
