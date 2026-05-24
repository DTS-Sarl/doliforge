# Objets métier : `CommonObject` et le pattern `$fields`

## Étendre `CommonObject`, pas `Model`

Tout objet métier persistant `extends CommonObject`. Il n'y a pas d'ORM façon
Eloquent. Sur Dolibarr 21-23, la bonne pratique est le **pattern `$fields`** : les
colonnes sont décrites dans un tableau `$fields`, et les méthodes génériques
(`createCommon`, `fetchCommon`…) lisent ce tableau pour générer le SQL, les
formulaires et les listes. C'est ce que produit le Module Builder.

Incorrect (CRUD réécrit entièrement à la main, incohérent avec le cœur) :
```php
class MonObjet
{
    public function save() { /* INSERT manuel */ }
    public function load($id) { /* SELECT manuel */ }
}
```

Correct (s'appuie sur `CommonObject` et `$fields`) :
```php
class MonObjet extends CommonObject
{
    public $module = 'monmodule';
    public $element = 'monobjet';
    public $table_element = 'monmodule_monobjet';  // SANS le préfixe llx_
    public $picto = 'monobjet@monmodule';
    public $ismultientitymanaged = 1;
    public $isextrafieldmanaged = 1;

    public $fields = array(
        'rowid' => array('type'=>'integer', 'label'=>'TechnicalID',
            'enabled'=>1, 'position'=>1, 'notnull'=>1, 'visible'=>0,
            'noteditable'=>1, 'index'=>1),
        'ref' => array('type'=>'varchar(128)', 'label'=>'Ref',
            'enabled'=>1, 'position'=>10, 'notnull'=>1, 'visible'=>1,
            'index'=>1, 'searchall'=>1, 'showoncombobox'=>1),
        'label' => array('type'=>'varchar(255)', 'label'=>'Label',
            'enabled'=>1, 'position'=>20, 'visible'=>1),
        'fk_soc' => array('type'=>'integer:Societe:societe/class/societe.class.php',
            'label'=>'ThirdParty', 'enabled'=>1, 'position'=>30, 'visible'=>1),
        'status' => array('type'=>'integer', 'label'=>'Status',
            'enabled'=>1, 'position'=>500, 'notnull'=>1, 'visible'=>1,
            'default'=>0, 'arrayofkeyval'=>array(0=>'Draft', 1=>'Validated')),
        'entity' => array('type'=>'integer', 'label'=>'Entity',
            'enabled'=>1, 'position'=>1000, 'notnull'=>1, 'visible'=>0, 'index'=>1),
    );

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }
}
```

## Garder les méthodes CRUD comme façade des méthodes `*Common`

Les méthodes publiques `create/fetch/update/delete` délèguent aux méthodes
génériques. C'est l'API attendue par le reste de Dolibarr (hooks, API REST, imports).

```php
public function create(User $user, $notrigger = 0)
{
    return $this->createCommon($user, $notrigger);
}
public function fetch($id, $ref = null)
{
    return $this->fetchCommon($id, $ref);
}
public function update(User $user, $notrigger = 0)
{
    return $this->updateCommon($user, $notrigger);
}
public function delete(User $user, $notrigger = 0)
{
    return $this->deleteCommon($user, $notrigger);
}
```

## Toujours inclure `entity` dans `$fields` si multi-société

Dès que `ismultientitymanaged = 1`, le champ `entity` doit figurer dans `$fields`
et dans la table. Sans lui, l'objet fuite entre les sociétés d'une install
multicompany. Voir `compatibilite-ecosysteme.md`.

## Clés utiles du tableau `$fields`

| Clé | Rôle |
|---|---|
| `type` | `integer`, `varchar(N)`, `text`, `datetime`, `price`, `double`, ou relation `'integer:Classe:chemin'` |
| `label` | clé de traduction du libellé |
| `enabled` | `1`/`0` ou expression — champ actif |
| `visible` | `1` visible, `0` caché, `-1`/`-2` liste/fiche seulement |
| `notnull` | `1` = NOT NULL |
| `position` | ordre d'affichage |
| `default` | valeur par défaut |
| `index` | `1` pour créer un index |
| `noteditable` | `1` = lecture seule |
| `arrayofkeyval` | tableau valeur→libellé (champ select) |
| `searchall` | inclus dans la recherche globale |

## Respecter la convention de retour

Toutes les méthodes d'un objet métier retournent `> 0` (succès), `0` (neutre /
non trouvé) ou `< 0` (erreur). Les messages vont dans `$this->error` (dernier) et
`$this->errors[]` (liste).

Incorrect :
```php
public function create(User $user)
{
    if (empty($this->ref)) return false;   // booléen, hors convention
}
```

Correct :
```php
public function create(User $user, $notrigger = 0)
{
    if (empty($this->ref)) {
        $this->errors[] = 'Ref obligatoire';
        return -1;
    }
    return $this->createCommon($user, $notrigger);
}
```

## Encadrer les écritures multi-tables par une transaction

```php
$this->db->begin();
$result = $this->createCommon($user);
if ($result > 0) {
    // … autres écritures liées …
    $this->db->commit();
    return $result;
}
$this->db->rollback();
return -1;
```

## Les méthodes CRUD déclenchent des triggers

`createCommon` / `updateCommon` / `deleteCommon` émettent les événements
`<ELEMENT>_CREATE`, `<ELEMENT>_MODIFY`, `<ELEMENT>_DELETE` (sauf si `$notrigger`).
Ces événements sont un point d'extension : un autre module peut y réagir. Passer
`$notrigger = 1` uniquement quand on veut explicitement court-circuiter ce mécanisme
(par exemple pour éviter une récursion).

## Relations entre objets (type FK dans `$fields`)

Pour lier un objet à un tiers, un utilisateur ou un autre objet, déclarer la
relation dans `$fields` avec le type `integer:Classe:chemin` :

```php
'fk_soc' => array(
    'type' => 'integer:Societe:societe/class/societe.class.php',
    'label' => 'ThirdParty',
    'enabled' => 'isModEnabled("societe")',
    'position' => 50,
    'notnull' => -1,
    'visible' => 1,
),
'fk_user' => array(
    'type' => 'integer:User:user/class/user.class.php',
    'label' => 'User',
    'position' => 60,
    'visible' => 1,
),
'fk_project' => array(
    'type' => 'integer:Project:projet/class/project.class.php',
    'label' => 'Project',
    'enabled' => 'isModEnabled("projet")',
    'position' => 70,
    'visible' => -1,
),
```

Dolibarr génère automatiquement un select avec auto-complétion pour ces champs dans
les formulaires. Le format est `integer:NomClasse:chemin/vers/classe.php` (chemin
relatif à `DOL_DOCUMENT_ROOT`).

## Propriétés clés de la classe

| Propriété | Obligatoire | Rôle |
|---|---|---|
| `$module` | Oui | Nom du module (doit correspondre au dossier) |
| `$element` | Oui | Identifiant technique de l'objet |
| `$table_element` | Oui | Nom de table **SANS** préfixe `llx_` |
| `$picto` | Non | Pictogramme : `'object_monobjet@monmodule'` |
| `$ismultientitymanaged` | Recommandé | `1` = filtré par entity, `0` = partagé |
| `$isextrafieldmanaged` | Non | `1` = supporte les extrafields |

`$element` sert à construire les noms de triggers (`MONELEMENT_CREATE`,
`MONELEMENT_MODIFY`, `MONELEMENT_DELETE`) et à identifier l'objet dans le système.

## Validation métier dans `create()` / `update()`

`createCommon()` ne fait **aucune validation métier**. Elle insère les données telles
quelles. Placer la validation avant l'appel :

```php
public function create(User $user, $notrigger = 0)
{
    global $langs;

    // Validation métier
    if (empty($this->ref)) {
        $this->errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Ref'));
        return -1;
    }
    if ($this->amount < 0) {
        $this->errors[] = 'Le montant ne peut pas être négatif';
        return -2;
    }

    $this->db->begin();
    $result = $this->createCommon($user, $notrigger);
    if ($result > 0) {
        $this->db->commit();
        return $result;
    }
    $this->db->rollback();
    return -1;
}
```

## Pattern « hériter d'un objet du cœur »

Pour étendre un objet natif Dolibarr (Task, Facture), hériter de sa classe plutôt
que de CommonObject directement. Observé dans le module Management (Patas-Monkey) :

```php
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

class ManagementTask extends Task
{
    public $average_thm;
    public $billingmode;

    public function fetchExtended($id)
    {
        // D'abord le fetch parent
        $result = parent::fetch($id);
        if ($result <= 0) return $result;

        // Puis les données supplémentaires
        $sql = "SELECT average_thm, billingmode";
        $sql .= " FROM ".MAIN_DB_PREFIX."management_task";
        $sql .= " WHERE fk_task = ".((int) $this->id);
        // ...
        return 1;
    }
}
```

**Attention** : cette approche couple fortement au cœur. Préférer une table séparée
liée par FK quand possible.

## Constantes de statut

Définir les statuts comme constantes de classe pour la lisibilité :

```php
class MonObjet extends CommonObject
{
    const STATUS_DRAFT = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_CLOSED = 2;
    const STATUS_CANCELED = 9;

    // Dans $fields :
    'status' => array('type' => 'integer', 'label' => 'Status',
        'arrayofkeyval' => array(
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_VALIDATED => 'Validated',
            self::STATUS_CLOSED => 'Closed',
        )),
}
```
