<?php
/* Copyright (C) 2024 DTS SARL
 * Fiche MonObjet — monobjetcard.php
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

// ---- Entrées ----
$id     = GETPOST('id',     'int');
$ref    = GETPOST('ref',    'alphanohtml');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'alpha');

// ---- Droits ----
if (!isModEnabled('monmodule')) accessforbidden('Module non activé');
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

// ---- Objet ----
$object = new MonObjet($db);
if ($id > 0 || $ref) {
	$result = $object->fetch($id, $ref ?: null);
	if ($result <= 0) {
		setEventMessages($langs->trans('ErrorMonObjetNotFound'), null, 'errors');
		header('Location: monobjetlist.php');
		exit;
	}
}

// ---- Actions ----
if ($action == 'add' && !$cancel) {
	if (!$user->hasRight('monmodule', 'monobjet', 'write')) accessforbidden();

	$object->ref         = GETPOST('ref', 'alphanohtml');
	$object->label       = GETPOST('label', 'alphanohtml');
	$object->fk_soc      = GETPOST('fk_soc', 'int');
	$object->note_public = GETPOST('note_public', 'restricthtml');

	$result = $object->create($user);
	if ($result > 0) {
		setEventMessages($langs->trans('MonObjetCreated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'create';
	}
}

if ($action == 'update' && !$cancel) {
	if (!$user->hasRight('monmodule', 'monobjet', 'write')) accessforbidden();

	$object->label       = GETPOST('label', 'alphanohtml');
	$object->fk_soc      = GETPOST('fk_soc', 'int');
	$object->note_public = GETPOST('note_public', 'restricthtml');

	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans('MonObjetUpdated'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
		$action = 'edit';
	}
}

if ($action == 'confirm_validate' && GETPOST('confirm', 'alpha') == 'yes') {
	if (!$user->hasRight('monmodule', 'monobjet', 'write')) accessforbidden();
	$result = $object->validate($user);
	if ($result > 0) {
		setEventMessages($langs->trans('MonObjetValidated'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
	exit;
}

if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes') {
	if (!$user->hasRight('monmodule', 'monobjet', 'delete')) accessforbidden();
	$result = $object->delete($user);
	if ($result > 0) {
		setEventMessages($langs->trans('MonObjetDeleted'), null, 'mesgs');
		header('Location: monobjetlist.php');
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// ---- Affichage ----
llxHeader('', $langs->trans($action == 'create' ? 'NewMonObjet' : 'MonObjetCard'),
	'', '', 0, 0, $arrayofjs, $arrayofcss);

// Inject variables PHP → JS
print '<script>';
print 'var monmodule_ajax_url = "'.dol_buildpath('/monmodule/ajax/monmodule.ajax.php', 1).'";';
print 'var monmodule_token = "'.newToken().'";';
print 'var monmodule_lang = { confirmDelete: "'.$langs->trans('ConfirmDeleteMonObjet').'" };';
print '</script>';

$form = new Form($db);

// ---- Confirmation suppression ----
if ($action == 'delete') {
	$formconfirm = $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$object->id,
		$langs->trans('DeleteMonObjet'),
		$langs->trans('ConfirmDeleteMonObjet'),
		'confirm_delete', '', 0, 1
	);
	print $formconfirm;
}

// ---- Confirmation validation ----
if ($action == 'validate') {
	$formconfirm = $form->formconfirm(
		$_SERVER['PHP_SELF'].'?id='.$object->id,
		$langs->trans('ValidateMonObjet'),
		$langs->trans('ConfirmValidateMonObjet'),
		'confirm_validate', '', 0, 1
	);
	print $formconfirm;
}

// ---- Titre ----
$title = ($action == 'create') ? $langs->trans('NewMonObjet') : $object->ref;
print load_fiche_titre($title, '', 'monobjet@monmodule');

// ---- Onglets ----
if ($object->id > 0) {
	$head = monobjet_prepare_head($object);
	print dol_get_fiche_head($head, 'card', $langs->trans('MonObjet'), -1, 'monobjet@monmodule');
} else {
	print dol_get_fiche_head([], '', $langs->trans('NewMonObjet'), -1, 'monobjet@monmodule');
}

// ---- Formulaire création / édition ----
if ($action == 'create' || $action == 'edit') {
	$formaction = ($action == 'create') ? 'add' : 'update';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token"  value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.$formaction.'">';
	if ($object->id > 0) print '<input type="hidden" name="id" value="'.$object->id.'">';

	print '<table class="border centpercent">';

	// Référence (création uniquement)
	if ($action == 'create') {
		print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Ref').'</td>';
		print '<td><input type="text" name="ref" class="minwidth200"';
		print ' value="'.dol_escape_htmltag(GETPOST('ref', 'alphanohtml')).'"></td></tr>';
	}

	// Libellé
	print '<tr><td class="titlefieldcreate">'.$langs->trans('Label').'</td>';
	print '<td><input type="text" name="label" class="minwidth300"';
	print ' value="'.dol_escape_htmltag($action == 'edit' ? $object->label : GETPOST('label', 'alphanohtml')).'"></td></tr>';

	// Tiers
	print '<tr><td>'.$langs->trans('ThirdParty').'</td>';
	print '<td>'.$form->select_company(
		$action == 'edit' ? $object->fk_soc : GETPOST('fk_soc', 'int'),
		'fk_soc', '', 'SelectThirdParty', 1, 0, [], 0, 'minwidth300'
	).'</td></tr>';

	print '</table>';

	print '<div class="center">';
	print '<input type="submit" class="button" name="save"';
	print ' value="'.$langs->trans($action == 'create' ? 'Create' : 'Save').'">';
	print ' &nbsp;<input type="submit" class="button button-cancel" name="cancel"';
	print ' value="'.$langs->trans('Cancel').'">';
	print '</div>';
	print '</form>';

} else {
	// ---- Vue lecture ----
	print '<table class="border centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td>';
	print '<td>'.dol_escape_htmltag($object->ref).'</td></tr>';

	print '<tr><td>'.$langs->trans('Label').'</td>';
	print '<td>'.dol_escape_htmltag($object->label).'</td></tr>';

	if ($object->fk_soc > 0) {
		$societe = new Societe($db);
		$societe->fetch($object->fk_soc);
		print '<tr><td>'.$langs->trans('ThirdParty').'</td>';
		print '<td>'.$societe->getNomUrl(1).'</td></tr>';
	}

	print '<tr><td>'.$langs->trans('Status').'</td>';
	print '<td>'.$object->getLibStatut(5).'</td></tr>';

	print '<tr><td>'.$langs->trans('DateCreation').'</td>';
	print '<td>'.dol_print_date($object->date_creation, 'dayhour').'</td></tr>';

	print '</table>';
}

print dol_get_fiche_end();

// ---- Barre d'actions ----
if ($object->id > 0 && $action != 'edit') {
	print '<div class="tabsAction">';

	print dolGetButtonAction('', $langs->trans('BackToList'), 'default',
		dol_buildpath('/monmodule/monobjetlist.php', 1), '');

	if ($user->hasRight('monmodule', 'monobjet', 'write')) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default',
			$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken(), '');
	}

	if ($object->status == MonObjet::STATUS_DRAFT
		&& $user->hasRight('monmodule', 'monobjet', 'write')) {
		print dolGetButtonAction('', $langs->trans('Validate'), 'default',
			$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=validate&token='.newToken(),
			'', 1);
	}

	if ($user->hasRight('monmodule', 'monobjet', 'delete')) {
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete',
			$_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '');
	}

	print '</div>';
}

llxFooter();
$db->close();
