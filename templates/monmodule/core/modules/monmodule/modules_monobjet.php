<?php
/* Copyright (C) 2024 DTS SARL
 * Classe parente abstraite pour les modèles de documents MonObjet
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

abstract class ModelePDFMonObjet extends CommonDocGenerator
{
	public $error = '';

	/**
	 * Retourne la liste des modèles disponibles
	 *
	 * @param  DoliDB $db                 Database handler
	 * @param  int    $maxfilenamelength   Max length of filenames
	 * @return array                       Tableau des modèles
	 */
	public static function liste_modeles($db, $maxfilenamelength = 0)
	{
		$type = 'monobjet';
		$list = array();

		include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
		$list = getListOfModels($db, $type, $maxfilenamelength);

		return $list;
	}
}
