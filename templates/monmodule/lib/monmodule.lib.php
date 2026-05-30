<?php
/* Copyright (C) 2024 DTS SARL
 * Fonctions utilitaires du module monmodule
 */

/**
 * Prépare les onglets de la fiche MonObjet
 */
function monobjet_prepare_head($object)
{
	global $langs, $conf, $user;
	$langs->load('monmodule@monmodule');

	$h = 0;
	$head = [];

	$head[$h][0] = dol_buildpath('/monmodule/monobjetcard.php', 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans('MonObjetCard');
	$head[$h][2] = 'card';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'monobjet@monmodule');

	return $head;
}

/**
 * Prépare les onglets des pages d'administration
 */
function monmodule_admin_prepare_head()
{
	global $langs;
	$langs->load('monmodule@monmodule');

	$h = 0;
	$head = [];

	$head[$h][0] = dol_buildpath('/monmodule/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath('/monmodule/admin/test.php', 1);
	$head[$h][1] = $langs->trans('Test');
	$head[$h][2] = 'test';
	$h++;

	$head[$h][0] = dol_buildpath('/monmodule/admin/about.php', 1);
	$head[$h][1] = $langs->trans('About');
	$head[$h][2] = 'about';
	$h++;

	return $head;
}
