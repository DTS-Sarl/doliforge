<?php
/* Copyright (C) 2024 DTS SARL
 * Handler AJAX du module monmodule — monmodule.ajax.php
 *
 * Appelé par JS via MonModule.ajax(action, data, callback)
 * Retourne toujours du JSON : { success: true/false, data: ..., error: ... }
 */

// ---- Sécurité AVANT main.inc.php ----
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK',    '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');

$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/monmodule/class/monobjet.class.php');

global $db, $conf, $user;

// ---- Lire l'action (POST standard ou JSON) ----
$action = '';
$input  = [];
$ct     = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

if (strpos($ct, 'application/json') !== false) {
	$raw   = file_get_contents('php://input');
	$input = json_decode($raw, true) ?: [];
	$action = isset($input['action']) ? (string) $input['action'] : '';
} else {
	$action = GETPOST('action', 'aZ09');
}

// ---- Vérifications initiales ----
top_httphead('application/json');

if (!isModEnabled('monmodule')) {
	echo json_encode(['success' => false, 'error' => 'Module disabled']);
	exit;
}
if (!$user->hasRight('monmodule', 'monobjet', 'read')) {
	echo json_encode(['success' => false, 'error' => 'Forbidden']);
	exit;
}

// ---- Dispatch des actions ----
switch ($action) {

	// Lecture : liste des objets
	case 'getlist':
		$object = new MonObjet($db);
		$list   = [];

		$sql  = "SELECT rowid, ref, label, status FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
		$sql .= " WHERE entity IN (".getEntity('monobjet').")";
		$sql .= " ORDER BY ref ASC";
		$sql .= $db->plimit(100, 0);

		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$list[] = [
					'id'     => (int) $obj->rowid,
					'ref'    => $obj->ref,
					'label'  => $obj->label,
					'status' => (int) $obj->status,
				];
			}
			echo json_encode(['success' => true, 'data' => $list]);
		} else {
			echo json_encode(['success' => false, 'error' => $db->lasterror()]);
		}
		break;

	// Lecture : un objet par id
	case 'get':
		if (!$user->hasRight('monmodule', 'monobjet', 'read')) {
			echo json_encode(['success' => false, 'error' => 'Forbidden']);
			break;
		}
		$id = (int) (isset($input['id']) ? $input['id'] : GETPOST('id', 'int'));
		$object = new MonObjet($db);
		if ($object->fetch($id) > 0) {
			echo json_encode(['success' => true, 'data' => [
				'id'     => $object->id,
				'ref'    => $object->ref,
				'label'  => $object->label,
				'status' => $object->status,
			]]);
		} else {
			echo json_encode(['success' => false, 'error' => 'Not found']);
		}
		break;

	// Écriture : mettre à jour le libellé
	case 'update':
		if (!$user->hasRight('monmodule', 'monobjet', 'write')) {
			echo json_encode(['success' => false, 'error' => 'Forbidden']);
			break;
		}
		$id    = (int) (isset($input['id'])    ? $input['id']    : GETPOST('id',    'int'));
		$label = isset($input['label']) ? $input['label'] : GETPOST('label', 'alphanohtml');

		$object = new MonObjet($db);
		if ($object->fetch($id) > 0) {
			$object->label = $label;
			$result = $object->update($user);
			echo json_encode($result > 0
				? ['success' => true]
				: ['success' => false, 'error' => $object->error]);
		} else {
			echo json_encode(['success' => false, 'error' => 'Not found']);
		}
		break;

	default:
		echo json_encode(['success' => false, 'error' => 'Unknown action: '.$action]);
		break;
}
exit;
