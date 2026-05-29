<?php
/* Copyright (C) 2024 DTS SARL
 * Page À propos du module monmodule
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

// ---------- Affichage ----------
llxHeader('', $langs->trans('MonModuleAbout'));

$head = monmodule_admin_prepare_head();
print dol_get_fiche_head($head, 'about', $langs->trans('MonModuleAbout'), -1, 'cog');

print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td class="titlefield">'.$langs->trans('Version').'</td>';
print '<td>1.0.0</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('Author').'</td>';
print '<td>DTS SARL — <a href="https://dywants.com" target="_blank">dywants.com</a></td>';
print '</tr>';
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
