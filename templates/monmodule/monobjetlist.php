<?php
/* Copyright (C) 2024 DTS SARL
 * Liste MonObjet — monobjetlist.php
 */

$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/monmodule/class/monobjet.class.php');
dol_include_once('/monmodule/lib/monmodule.lib.php');

global $db, $conf, $user, $langs, $hookmanager;
$langs->loadLangs(['monmodule@monmodule']);

// ---- Assets ----
$arrayofcss = [dol_buildpath('/monmodule/css/monmodule.css', 1).'?v=1.0.0'];
$arrayofjs  = [dol_buildpath('/monmodule/js/monmodule.js',  1).'?v=1.0.0'];

// ---- Droits ----
if (!isModEnabled('monmodule')) accessforbidden('Module non activé');
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

// ---- Paramètres liste ----
$action    = GETPOST('action',    'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = max(0, GETPOSTINT('page'));
$limit     = $conf->liste_limit;
$offset    = $page * $limit;

if (empty($sortfield)) $sortfield = 't.rowid';
if (empty($sortorder)) $sortorder = 'DESC';

// ---- Filtres ----
$search_ref    = GETPOST('search_ref',    'alphanohtml');
$search_label  = GETPOST('search_label',  'alphanohtml');
$search_status = GETPOST('search_status', 'int');

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha')) {
	$search_ref    = '';
	$search_label  = '';
	$search_status = '';
}

// ---- Requête SQL ----
$sql  = "SELECT t.rowid, t.ref, t.label, t.status, t.fk_soc, t.date_creation";
$sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet AS t";
$sql .= " WHERE t.entity IN (".getEntity('monobjet').")";

if ($search_ref !== '')
	$sql .= " AND t.ref LIKE '%".$db->escape($db->escapeforlike($search_ref))."%'";
if ($search_label !== '')
	$sql .= " AND t.label LIKE '%".$db->escape($db->escapeforlike($search_label))."%'";
if ($search_status !== '' && $search_status >= 0)
	$sql .= " AND t.status = ".((int) $search_status);

$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit, $offset);

$resql = $db->query($sql);
if (!$resql) dol_print_error($db);

// Compter le total (sans plimit)
$sqlcount  = "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."monmodule_monobjet AS t";
$sqlcount .= " WHERE t.entity IN (".getEntity('monobjet').")";
if ($search_ref !== '')
	$sqlcount .= " AND t.ref LIKE '%".$db->escape($db->escapeforlike($search_ref))."%'";
if ($search_label !== '')
	$sqlcount .= " AND t.label LIKE '%".$db->escape($db->escapeforlike($search_label))."%'";
if ($search_status !== '' && $search_status >= 0)
	$sqlcount .= " AND t.status = ".((int) $search_status);

$rescount  = $db->query($sqlcount);
$num_total = ($rescount ? (int) $db->fetch_object($rescount)->nb : 0);

// ---- Affichage ----
llxHeader('', $langs->trans('MonObjetList'), '', '', 0, 0, $arrayofjs, $arrayofcss);

$form = new Form($db);

// Titre + bouton créer
$newcardbutton = '';
if ($user->hasRight('monmodule', 'monobjet', 'write')) {
	$newcardbutton = dolGetButtonTitle(
		$langs->trans('NewMonObjet'), '',
		'fa fa-plus-circle',
		dol_buildpath('/monmodule/monobjetcard.php', 1).'?action=create'
	);
}
print load_fiche_titre($langs->trans('MonObjetList'), $newcardbutton, 'monobjet@monmodule');

// Formulaire liste + filtres
print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';

$param = '&search_ref='.urlencode($search_ref)
       .'&search_label='.urlencode($search_label)
       .'&search_status='.urlencode((string) $search_status);

print_barre_liste(
	$langs->trans('MonObjetList'), $page, $_SERVER['PHP_SELF'],
	$param, $sortfield, $sortorder, '', $num_total, $num_total,
	'monobjet@monmodule', 0, '', '', $limit
);

// En-tête tableau
print '<table class="tagtable nobottomiftotal liste">';

// Ligne filtres
print '<tr class="liste_titre_filter">';

print '<td class="liste_titre">';
print '<input class="flat maxwidth75" type="text" name="search_ref"';
print ' value="'.dol_escape_htmltag($search_ref).'">';
print '</td>';

print '<td class="liste_titre">';
print '<input class="flat maxwidth150" type="text" name="search_label"';
print ' value="'.dol_escape_htmltag($search_label).'">';
print '</td>';

print '<td class="liste_titre">';
$selStatut = '<select name="search_status" class="flat">';
$selStatut .= '<option value="-1">'.$langs->trans('All').'</option>';
$selStatut .= '<option value="0"'.($search_status === 0 ? ' selected' : '').'>'.$langs->trans('StatusDraft').'</option>';
$selStatut .= '<option value="1"'.($search_status === 1 ? ' selected' : '').'>'.$langs->trans('StatusValidated').'</option>';
$selStatut .= '<option value="9"'.($search_status === 9 ? ' selected' : '').'>'.$langs->trans('StatusClosed').'</option>';
$selStatut .= '</select>';
print $selStatut;
print '</td>';

print '<td class="liste_titre maxwidthsearch">';
print '<input type="image" class="liste_titre height12" name="button_search"';
print ' src="'.img_picto('', 'search.png', '', 0, 1).'"';
print ' value="'.dol_escape_htmltag($langs->trans('Search')).'"';
print ' title="'.dol_escape_htmltag($langs->trans('Search')).'">';
print ' <input type="image" class="liste_titre height12" name="button_removefilter"';
print ' src="'.img_picto('', 'searchclear.png', '', 0, 1).'"';
print ' value="'.dol_escape_htmltag($langs->trans('RemoveFilter')).'"';
print ' title="'.dol_escape_htmltag($langs->trans('RemoveFilter')).'">';
print '</td>';

print '</tr>';

// Ligne en-têtes colonnes
print '<tr class="liste_titre">';
print getTitleFieldOfList('Ref',    0, $_SERVER['PHP_SELF'], 't.ref',    '', $param, '', $sortfield, $sortorder, '');
print getTitleFieldOfList('Label',  0, $_SERVER['PHP_SELF'], 't.label',  '', $param, '', $sortfield, $sortorder, '');
print getTitleFieldOfList('Status', 0, $_SERVER['PHP_SELF'], 't.status', '', $param, '', $sortfield, $sortorder, '');
print getTitleFieldOfList('',       0);
print '</tr>';

// Lignes
$num = $db->num_rows($resql);
$i   = 0;
if ($num > 0) {
	$object = new MonObjet($db);
	while ($i < min($num, $limit)) {
		$obj = $db->fetch_object($resql);

		$object->id            = $obj->rowid;
		$object->ref           = $obj->ref;
		$object->label         = $obj->label;
		$object->status        = (int) $obj->status;
		$object->fk_soc        = (int) $obj->fk_soc;
		$object->date_creation = $db->jdate($obj->date_creation);

		print '<tr class="oddeven">';

		// Réf — lien vers la fiche
		print '<td><a href="monobjetcard.php?id='.$object->id.'">';
		print dol_escape_htmltag($object->ref);
		print '</a></td>';

		// Libellé
		print '<td>'.dol_escape_htmltag($object->label).'</td>';

		// Statut
		print '<td>'.$object->getLibStatut(5).'</td>';

		// Actions rapides
		print '<td class="right nowraponall">';
		if ($user->hasRight('monmodule', 'monobjet', 'write')) {
			print '<a class="editfielda" href="monobjetcard.php?id='.$object->id.'&action=edit&token='.newToken().'">';
			print img_edit();
			print '</a>';
		}
		if ($user->hasRight('monmodule', 'monobjet', 'delete')) {
			print ' <a class="deletefielda marginleftonly" href="monobjetcard.php?id='.$object->id.'&action=delete&token='.newToken().'">';
			print img_delete();
			print '</a>';
		}
		print '</td>';

		print '</tr>';
		$i++;
	}
} else {
	print '<tr class="oddeven">';
	print '<td colspan="4"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td>';
	print '</tr>';
}

print '</table>';
print '</form>';

llxFooter();
$db->close();
