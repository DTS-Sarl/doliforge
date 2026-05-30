# Patterns communs

Cette fiche centralise les patterns réutilisés dans plusieurs fiches de référence.
Les autres fiches y redirigent au lieu de dupliquer le code.

## Inclusion de `main.inc.php`

Chaque fichier PHP doit inclure `main.inc.php` avec un fallback multi-niveaux
adapté à la profondeur du fichier dans l'arborescence :

```php
// Racine du module (monobjetcard.php, monobjetlist.php)
$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

// Sous-dossier admin/ ou ajax/
$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
```

## Déclaration des globales

```php
global $db, $conf, $user, $langs;
```

Toujours déclarer explicitement les globales utilisées. Dolibarr ne fait pas
d'injection de dépendances.

## Lecture d'entrées avec `GETPOST`

| Usage | Code |
| --- | --- |
| Entier (ID, statut) | `GETPOST('id', 'int')` |
| Action (aZ09) | `GETPOST('action', 'aZ09')` |
| Texte sans HTML | `GETPOST('label', 'alphanohtml')` |
| Champ WYSIWYG | `GETPOST('note', 'restricthtml')` |
| Tri multi-colonnes | `GETPOST('sortfield', 'aZ09comma')` |
| Tableau (checkboxes) | `GETPOST('ids', 'array')` |

Règle : utiliser le filtre **le plus restrictif** compatible avec l'usage.
En cas de doute, `alphanohtml` est le choix sûr.

## Vérification des droits

```php
// En tête de page — bloquer l'accès
if (!isModEnabled('monmodule')) accessforbidden('Module non activé');
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

// Avant une action d'écriture
if ($action == 'add') {
    if (!$user->hasRight('monmodule', 'monobjet', 'write')) accessforbidden();
    // ...
}
```

## CSRF — formulaire POST

```php
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';
// ... champs ...
print '</form>';
```

## Échappement SQL

```php
// Chaîne
$sql .= " AND label = '".$db->escape($label)."'";

// Entier
$sql .= " AND fk_soc = ".((int) $socid);

// LIKE
$sql .= " AND ref LIKE '%".$db->escape($db->escapeforlike($search))."%'";
```

## Échappement HTML en sortie

```php
// Texte
print '<td>'.dol_escape_htmltag($object->label).'</td>';

// Dans du JavaScript
print '<script>var ref = "'.dol_escape_js($object->ref).'";</script>';
```

## Filtrage par entité (multi-société)

```php
$sql .= " WHERE t.entity IN (".getEntity('monobjet').")";
```

Toujours présent dans les SELECT, jamais oublié.

## Transaction

```php
$this->db->begin();

$result = $this->updateCommon($user);
if ($result > 0) {
    $this->db->commit();
    return $result;
}

$this->db->rollback();
return -1;
```

## Messages à l'utilisateur

```php
// Succès
setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');

// Erreur
setEventMessages($langs->trans('Error'), null, 'errors');

// Erreurs multiples depuis un objet
setEventMessages($object->error, $object->errors, 'errors');
```

## Retour de méthode métier

Convention Dolibarr : `> 0` succès, `0` neutre, `< 0` erreur.

```php
public function myMethod(User $user)
{
    if (empty($this->ref)) {
        $this->error = 'RefMissing';
        return -1;
    }
    // ... traitement ...
    return 1;
}
```

## Constantes AJAX

```php
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK',    '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');
```

Toujours **avant** `include main.inc.php`.

## Journalisation

```php
dol_syslog("MonObjet::create ref=".$this->ref, LOG_DEBUG);
```

Préfixer avec le nom de classe et la méthode. Niveaux : `LOG_DEBUG`, `LOG_INFO`,
`LOG_WARNING`, `LOG_ERR`.

## Inclusion de classes du module

```php
// Méthode recommandée
dol_include_once('/monmodule/class/monobjet.class.php');

// Alternative avec fallback
$classFile = dol_buildpath('/monmodule/class/monobjet.class.php', 0);
if (!file_exists($classFile)) {
    $classFile = DOL_DOCUMENT_ROOT.'/custom/monmodule/class/monobjet.class.php';
}
require_once $classFile;
```

## Assets CSS/JS versionnés

```php
$arrayofcss = [dol_buildpath('/monmodule/css/monmodule.css', 1).'?v=1.0.0'];
$arrayofjs  = [dol_buildpath('/monmodule/js/monmodule.js',  1).'?v=1.0.0'];

llxHeader('', $title, '', '', 0, 0, $arrayofjs, $arrayofcss);
```
