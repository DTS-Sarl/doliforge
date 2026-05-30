<?php
/* Copyright (C) 2024 DTS SARL
 * Page de test du module — admin/test.php
 *
 * IMPORTANT : supprimer ou protéger cette page avant mise en production.
 * Elle permet de vérifier que le module fonctionne correctement.
 */

if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');

$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/monmodule/lib/monmodule.lib.php');
dol_include_once('/monmodule/class/monobjet.class.php');

global $db, $conf, $user, $langs;
$langs->loadLangs(['monmodule@monmodule', 'admin']);

if (!$user->admin) accessforbidden();

// ---- Affichage ----
llxHeader('', $langs->trans('MonModuleTest'));

$head = monmodule_admin_prepare_head();
print dol_get_fiche_head($head, 'test', $langs->trans('MonModule'), -1, 'monmodule@monmodule');

print load_fiche_titre($langs->trans('MonModuleTest'));

$ok  = '<span style="color:green; font-weight:bold;">OK</span>';
$nok = '<span style="color:red; font-weight:bold;">ERREUR</span>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>Test</th><th>Résultat</th><th>Détail</th>';
print '</tr>';

// ---- Test 1 : Module activé ----
$test1 = isModEnabled('monmodule');
print '<tr class="oddeven">';
print '<td>Module activé</td>';
print '<td>'.($test1 ? $ok : $nok).'</td>';
print '<td>isModEnabled(\'monmodule\')</td>';
print '</tr>';

// ---- Test 2 : Table SQL accessible ----
$test2  = false;
$detail = '';
$sql = "SELECT COUNT(rowid) AS nb FROM ".MAIN_DB_PREFIX."monmodule_monobjet WHERE entity = ".((int) $conf->entity);
$resql = $db->query($sql);
if ($resql) {
	$row    = $db->fetch_object($resql);
	$test2  = true;
	$detail = $row->nb.' enregistrements';
} else {
	$detail = $db->lasterror();
}
print '<tr class="oddeven">';
print '<td>Table monmodule_monobjet</td>';
print '<td>'.($test2 ? $ok : $nok).'</td>';
print '<td>'.dol_escape_htmltag($detail).'</td>';
print '</tr>';

// ---- Test 3 : Instanciation de la classe ----
$test3  = false;
$detail = '';
try {
	$obj   = new MonObjet($db);
	$test3 = is_object($obj);
	$detail = get_class($obj);
} catch (Throwable $e) {
	$detail = $e->getMessage();
}
print '<tr class="oddeven">';
print '<td>Instanciation MonObjet</td>';
print '<td>'.($test3 ? $ok : $nok).'</td>';
print '<td>'.dol_escape_htmltag($detail).'</td>';
print '</tr>';

// ---- Test 4 : CRUD complet ----
$test4  = false;
$detail = '';
$testRef = 'TEST-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');

$obj = new MonObjet($db);
$obj->ref    = $testRef;
$obj->label  = 'Objet de test automatique';
$obj->entity = $conf->entity;
$obj->status = 0;

$db->begin();

$createResult = $obj->create($user);
if ($createResult > 0) {
	// Fetch
	$obj2 = new MonObjet($db);
	$fetchResult = $obj2->fetch($createResult);
	if ($fetchResult > 0 && $obj2->ref == $testRef) {
		// Update
		$obj2->label = 'Objet modifié';
		$updateResult = $obj2->update($user);
		if ($updateResult > 0) {
			// Delete
			$deleteResult = $obj2->delete($user);
			if ($deleteResult > 0) {
				$test4  = true;
				$detail = 'Create→Fetch→Update→Delete OK (id='.$createResult.')';
			} else {
				$detail = 'Delete échoué: '.$obj2->error;
			}
		} else {
			$detail = 'Update échoué: '.$obj2->error;
		}
	} else {
		$detail = 'Fetch échoué: ref attendue='.$testRef;
	}
} else {
	$detail = 'Create échoué: '.$obj->error;
}

$db->rollback(); // Toujours rollback — ne pas laisser de données de test

print '<tr class="oddeven">';
print '<td>CRUD complet (Create→Fetch→Update→Delete)</td>';
print '<td>'.($test4 ? $ok : $nok).'</td>';
print '<td>'.dol_escape_htmltag($detail).'</td>';
print '</tr>';

// ---- Test 5 : Permissions ----
$test5a = $user->hasRight('monmodule', 'monobjet', 'read');
$test5b = $user->hasRight('monmodule', 'monobjet', 'write');
$test5c = $user->hasRight('monmodule', 'monobjet', 'delete');

print '<tr class="oddeven">';
print '<td>Permission read</td>';
print '<td>'.($test5a ? $ok : '<span style="color:orange">NON</span>').'</td>';
print '<td>hasRight(\'monmodule\', \'monobjet\', \'read\')</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>Permission write</td>';
print '<td>'.($test5b ? $ok : '<span style="color:orange">NON</span>').'</td>';
print '<td>hasRight(\'monmodule\', \'monobjet\', \'write\')</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>Permission delete</td>';
print '<td>'.($test5c ? $ok : '<span style="color:orange">NON</span>').'</td>';
print '<td>hasRight(\'monmodule\', \'monobjet\', \'delete\')</td>';
print '</tr>';

// ---- Test 6 : Répertoire de sortie ----
$test6  = false;
$detail = '';
$dir = $conf->monmodule->dir_output ?? '';
if (!empty($dir)) {
	$test6  = is_dir($dir) || @dol_mkdir($dir);
	$detail = $dir;
} else {
	$detail = '$conf->monmodule->dir_output non défini';
}
print '<tr class="oddeven">';
print '<td>Répertoire dir_output</td>';
print '<td>'.($test6 ? $ok : $nok).'</td>';
print '<td>'.dol_escape_htmltag($detail).'</td>';
print '</tr>';

// ---- Test 7 : Constantes du module ----
$constants = ['MONMODULE_PROVIDER', 'MONMODULE_DEBUG'];
foreach ($constants as $cst) {
	$val = getDolGlobalString($cst, '(non défini)');
	print '<tr class="oddeven">';
	print '<td>Constante '.$cst.'</td>';
	print '<td>'.$ok.'</td>';
	print '<td>'.dol_escape_htmltag($val).'</td>';
	print '</tr>';
}

print '</table>';

// Résumé
$allOk = $test1 && $test2 && $test3 && $test4 && $test6;
print '<br>';
if ($allOk) {
	print '<div class="ok">'.$langs->trans('AllTestsPassed').'</div>';
} else {
	print '<div class="error">'.$langs->trans('SomeTestsFailed').'</div>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
