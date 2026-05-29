<?php
/* Copyright (C) 2024 DTS SARL
 * Page de configuration du module monmodule
 */

$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/monmodule/lib/monmodule.lib.php');

global $db, $conf, $user, $langs;
$langs->loadLangs(['monmodule@monmodule', 'admin']);

if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');

// ---------- Traitement ----------
if ($action == 'setvalue' && GETPOST('token', 'alphanohtml') == newToken()) {
	dolibarr_set_const($db, 'MONMODULE_OPTION1',
		GETPOST('MONMODULE_OPTION1', 'alphanohtml'), 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

// ---------- Affichage ----------
llxHeader('', $langs->trans('MonModuleSetup'));

$head = monmodule_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans('MonModuleSetup'),
	-1, 'cog');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setvalue">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '</tr>';

// Option 1
print '<tr class="oddeven">';
print '<td>';
print $langs->trans('MonModuleOption1');
print ' <span class="opacitymedium">'.$langs->trans('MonModuleOption1Help').'</span>';
print '</td>';
print '<td>';
print '<input type="text" name="MONMODULE_OPTION1" class="minwidth200"';
print ' value="'.dol_escape_htmltag(getDolGlobalString('MONMODULE_OPTION1')).'">';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '&nbsp;<a class="button button-cancel" href="'.DOL_URL_ROOT.'/admin/modules.php">';
print $langs->trans('Cancel').'</a>';
print '</div>';

print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
