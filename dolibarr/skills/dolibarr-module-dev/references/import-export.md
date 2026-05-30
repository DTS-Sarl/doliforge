# Import / Export de données

Dolibarr dispose d'un système d'import et d'export intégré qui permet aux
utilisateurs de charger des données depuis un CSV ou d'exporter des listes.
Un module déclare ses profils d'import/export dans le descripteur.

## Déclarer un export dans le descripteur

```php
// Dans modMonModule.class.php — __construct()

// ---- Export ----
$r = 0;
$this->export_code[$r]  = $this->rights_class.'_'.$r;
$this->export_label[$r] = 'ExportMonObjets';    // Clé de traduction
$this->export_icon[$r]  = 'monobjet@monmodule';
$this->export_permission[$r] = array(array('monmodule', 'monobjet', 'read'));

$this->export_fields_array[$r] = array(
    't.rowid'         => 'Id',
    't.ref'           => 'Ref',
    't.label'         => 'Label',
    't.description'   => 'Description',
    't.status'        => 'Status',
    't.date_creation' => 'DateCreation',
    's.nom'           => 'ThirdPartyName',
);
$this->export_TypeFields_array[$r] = array(
    't.rowid'         => 'Numeric',
    't.ref'           => 'Text',
    't.label'         => 'Text',
    't.description'   => 'Text',
    't.status'        => 'Status',
    't.date_creation' => 'Date',
    's.nom'           => 'Text',
);
$this->export_entities_array[$r] = array(
    's.nom' => 'company',   // Entité liée (pour les jointures)
);

$this->export_sql_start[$r]  = 'SELECT DISTINCT ';
$this->export_sql_end[$r]    = ' FROM '.MAIN_DB_PREFIX.'monmodule_monobjet AS t';
$this->export_sql_end[$r]   .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe AS s ON s.rowid = t.fk_soc';
$this->export_sql_end[$r]   .= ' WHERE t.entity IN ('.getEntity('monobjet').')';

$r++;
```

## Déclarer un import dans le descripteur

```php
// ---- Import ----
$r = 0;
$this->import_code[$r]  = $this->rights_class.'_'.$r;
$this->import_label[$r] = 'ImportMonObjets';
$this->import_icon[$r]  = 'monobjet@monmodule';
$this->import_permission[$r] = array(array('monmodule', 'monobjet', 'write'));

$this->import_tables_array[$r] = array(
    't' => MAIN_DB_PREFIX.'monmodule_monobjet',
);
$this->import_fields_array[$r] = array(
    't.ref'           => 'Ref*',         // * = obligatoire
    't.label'         => 'Label*',
    't.description'   => 'Description',
    't.status'        => 'Status',
    't.fk_soc'        => 'ThirdPartyId',
    't.date_creation' => 'DateCreation',
);
$this->import_regex_array[$r] = array(
    't.ref'    => '^[A-Z0-9\-]+$',       // Validation regex
    't.status' => '^[0-9]$',
);
$this->import_examplevalues_array[$r] = array(
    't.ref'           => 'OBJ-001',
    't.label'         => 'Mon objet test',
    't.description'   => 'Description optionnelle',
    't.status'        => '0',
    't.fk_soc'        => '1',
    't.date_creation' => '2024-01-15',
);
// Colonnes automatiques (non importées mais remplies automatiquement)
$this->import_fieldshidden_array[$r] = array(
    't.entity'        => 'currententity',
    't.fk_user_creat' => 'user->id',
    't.tms'           => 'updatedate',
);

$r++;
```

## Types de champs pour l'import/export

| Type | Comportement |
| --- | --- |
| `Text` | Texte libre |
| `Numeric` | Nombre entier ou décimal |
| `Date` | Format `YYYY-MM-DD` |
| `Boolean` | 0 ou 1 |
| `Status` | Statut numérique avec libellé |
| `List:table:column:rowid` | Clé étrangère vers une table |
| `FormSelect:method` | Sélection via méthode de formulaire |

## Export personnalisé (hors système natif)

Pour un export plus contrôlé (bouton sur la page liste, format spécifique) :

```php
// Action d'export sur monobjetlist.php
if ($action == 'export_csv' && $user->hasRight('monmodule', 'monobjet', 'read')) {
    $sql  = "SELECT t.ref, t.label, t.status, t.date_creation, s.nom AS soc_nom";
    $sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet AS t";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = t.fk_soc";
    $sql .= " WHERE t.entity IN (".getEntity('monobjet').")";
    $sql .= " ORDER BY t.ref ASC";

    $resql = $db->query($sql);
    if ($resql) {
        // En-têtes HTTP pour téléchargement CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="export_monobjet_'.date('Ymd').'.csv"');
        header('Pragma: no-cache');

        $output = fopen('php://output', 'w');

        // BOM UTF-8 pour Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // En-tête CSV
        fputcsv($output, ['Ref', 'Label', 'Status', 'Date creation', 'Tiers'], ';');

        // Lignes
        while ($obj = $db->fetch_object($resql)) {
            fputcsv($output, [
                $obj->ref,
                $obj->label,
                $obj->status,
                $obj->date_creation,
                $obj->soc_nom,
            ], ';');
        }

        fclose($output);
        exit;
    }
}
```

Bouton d'export sur la page liste :

```php
// Dans la barre de titre
$exportbutton = '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?action=export_csv&token='.newToken().'">';
$exportbutton .= $langs->trans('ExportCSV');
$exportbutton .= '</a>';
```

## Import personnalisé (page dédiée)

Pour un import CSV avec contrôle métier, créer une page `monobjetimport.php` :

```php
<?php
$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/monmodule/class/monobjet.class.php');

global $db, $conf, $user, $langs;
$langs->load('monmodule@monmodule');

if (!$user->hasRight('monmodule', 'monobjet', 'write')) accessforbidden();

$action = GETPOST('action', 'aZ09');

// Traitement de l'import
if ($action == 'import' && !empty($_FILES['csvfile']['tmp_name'])) {
    $handle = fopen($_FILES['csvfile']['tmp_name'], 'r');
    if (!$handle) {
        setEventMessages($langs->trans('ErrorOpenFile'), null, 'errors');
    } else {
        $header = fgetcsv($handle, 0, ';');
        $lineNum = 1;
        $imported = 0;
        $errors = [];

        $db->begin();

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $lineNum++;

            // Valider les données
            $ref   = trim($row[0] ?? '');
            $label = trim($row[1] ?? '');

            if (empty($ref)) {
                $errors[] = $langs->trans('ErrorLine').' '.$lineNum.': '.$langs->trans('ErrorFieldRequired', 'Ref');
                continue;
            }

            // Vérifier les doublons
            $existing = new MonObjet($db);
            $checkSql = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
            $checkSql .= " WHERE ref = '".$db->escape($ref)."'";
            $checkSql .= " AND entity IN (".getEntity('monobjet').")";
            $checkRes = $db->query($checkSql);
            if ($checkRes && $db->num_rows($checkRes) > 0) {
                $errors[] = $langs->trans('ErrorLine').' '.$lineNum.': Ref '.$ref.' existe déjà';
                continue;
            }

            // Créer l'objet
            $object = new MonObjet($db);
            $object->ref    = $ref;
            $object->label  = $label;
            $object->entity = $conf->entity;

            $result = $object->create($user);
            if ($result > 0) {
                $imported++;
            } else {
                $errors[] = $langs->trans('ErrorLine').' '.$lineNum.': '.$object->error;
            }
        }

        fclose($handle);

        if (empty($errors)) {
            $db->commit();
            setEventMessages($langs->trans('ImportSuccess', $imported), null, 'mesgs');
        } else {
            $db->rollback();
            setEventMessages(null, $errors, 'errors');
        }
    }
}

// Affichage
llxHeader('', $langs->trans('ImportMonObjets'));

print load_fiche_titre($langs->trans('ImportMonObjets'));

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="import">';

print '<table class="noborder centpercent">';
print '<tr class="oddeven">';
print '<td>'.$langs->trans('SelectCSVFile').'</td>';
print '<td><input type="file" name="csvfile" accept=".csv"></td>';
print '</tr>';
print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Import').'">';
print '</div>';
print '</form>';

// Format attendu
print '<br>';
print '<div class="opacitymedium">';
print $langs->trans('ExpectedCSVFormat').' :<br>';
print '<code>Ref;Label;Description;Status</code>';
print '</div>';

llxFooter();
$db->close();
```

## Opérations en masse (bulk actions)

Pattern pour appliquer une action sur plusieurs objets sélectionnés :

```php
// Dans monobjetlist.php — checkboxes sur chaque ligne
print '<td><input type="checkbox" name="toselect[]" value="'.$object->id.'"></td>';

// Barre d'actions en masse
if ($user->hasRight('monmodule', 'monobjet', 'write')) {
    print '<div class="liste_titre_barre_actions">';
    print '<select name="massaction" class="flat">';
    print '<option value="">'.$langs->trans('MassAction').'</option>';
    print '<option value="validate">'.$langs->trans('Validate').'</option>';
    print '<option value="close">'.$langs->trans('Close').'</option>';
    print '<option value="delete">'.$langs->trans('Delete').'</option>';
    print '</select>';
    print ' <input type="submit" class="button" value="'.$langs->trans('Apply').'">';
    print '</div>';
}

// Traitement
$massaction = GETPOST('massaction', 'aZ09');
$toselect   = GETPOST('toselect', 'array');

if (!empty($massaction) && !empty($toselect)) {
    $db->begin();
    $nbok = 0;
    foreach ($toselect as $selectedId) {
        $obj = new MonObjet($db);
        if ($obj->fetch((int) $selectedId) > 0) {
            switch ($massaction) {
                case 'validate':
                    if ($obj->validate($user) > 0) $nbok++;
                    break;
                case 'close':
                    if ($obj->close($user) > 0) $nbok++;
                    break;
                case 'delete':
                    if ($user->hasRight('monmodule', 'monobjet', 'delete')) {
                        if ($obj->delete($user) > 0) $nbok++;
                    }
                    break;
            }
        }
    }
    $db->commit();
    setEventMessages($langs->trans('RecordsModified', $nbok), null, 'mesgs');
}
```

## Bonnes pratiques

- **Transaction** : envelopper les imports en masse dans `$db->begin()` / `commit()` / `rollback()`
- **Validation** : vérifier chaque ligne avant insertion — ne pas insérer des données invalides
- **Doublons** : toujours vérifier les doublons de référence avant import
- **Encodage** : ajouter le BOM UTF-8 en début de CSV pour la compatibilité Excel
- **Limites** : pour les gros volumes, traiter par lots (ex : 500 lignes) avec `set_time_limit(0)`
- **Sécurité** : vérifier les permissions avant import/export
- **Multi-entité** : toujours filtrer par `entity` dans les exports et forcer `entity` dans les imports
