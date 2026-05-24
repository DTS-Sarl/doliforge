# Le descripteur `modXxx.class.php`

Le descripteur (`core/modules/modMonModule.class.php`) `extends DolibarrModules`.
Tout se configure dans le constructeur. C'est lui qui déclare au gestionnaire de
modules les droits, menus, constantes et sous-systèmes.

## Squelette minimal

```php
<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modMonModule extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf, $langs;
        $this->db = $db;

        $this->numero = 500123;            // numéro UNIQUE
        $this->rights_class = 'monmodule'; // préfixe des permissions
        $this->family = 'crm';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Description courte';
        $this->editor_name = 'DTS SARL';
        $this->editor_url = 'https://dywants.com';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'monmodule@monmodule';

        $this->dirs = array();
        $this->depends = array();
        $this->conflictwith = array();
        $this->module_parts = array();
        $this->const = array();
        $this->rights = array();
        $this->menu = array();
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/monmodule/sql/');
        if ($result < 0) return -1;
        return $this->_init(array(), $options);
    }

    public function remove($options = '')
    {
        return $this->_remove(array(), $options);
    }
}
```

## Donner un numéro de module unique

`$this->numero` identifie le module dans l'installation. Une collision empêche
l'activation.

Incorrect (numéro arbitraire bas, risque de collision avec un module du cœur ou
DoliStore) :
```php
$this->numero = 100;
```

Correct — usage privé : un nombre élevé peu risqué.
```php
$this->numero = 500123;
```

Correct — module destiné à DoliStore : réserver un identifiant officiel auprès de
l'association Dolibarr (page de réservation du wiki). C'est une exigence de
publication.

## Déclarer les droits

Chaque droit est une ligne de `$this->rights` :
`[id, label, défaut_admin, défaut_public, catégorie, action]`.

```php
$r = 0;
$this->rights[$r][0] = $this->numero + 1;
$this->rights[$r][1] = 'Lire les objets MonModule';
$this->rights[$r][4] = 'monobjet';
$this->rights[$r][5] = 'read';
$r++;
$this->rights[$r][0] = $this->numero + 2;
$this->rights[$r][1] = 'Créer/modifier les objets MonModule';
$this->rights[$r][4] = 'monobjet';
$this->rights[$r][5] = 'write';
```

Vérification dans le code : `$user->hasRight('monmodule', 'monobjet', 'read')`.

## Déclarer les menus

```php
$this->menu[$r] = array(
    'fk_menu'  => 'fk_mainmenu=monmodule',
    'type'     => 'left',
    'titre'    => 'MonObjetList',
    'mainmenu' => 'monmodule',
    'leftmenu' => 'monmodule_monobjet',
    'url'      => '/monmodule/monobjetlist.php',
    'langs'    => 'monmodule@monmodule',
    'position' => 1000 + $r,
    'enabled'  => 'isModEnabled("monmodule")',
    'perms'    => '$user->hasRight("monmodule", "monobjet", "read")',
    'user'     => 2,
);
```

`enabled` et `perms` sont des chaînes PHP évaluées : c'est ce qui conditionne
l'affichage du menu selon le contexte et l'utilisateur.

## Activer les sous-systèmes via `module_parts`

Un mécanisme n'est découvert par Dolibarr que s'il est déclaré ici. C'est ce qui
permet d'étendre Dolibarr **sans toucher au cœur**.

```php
$this->module_parts = array(
    'triggers' => 1,                                  // charge core/triggers/
    'hooks'    => array('thirdpartycard', 'invoicecard'), // contextes de hooks
    'css'      => array('/monmodule/css/monmodule.css'),
    'js'       => array('/monmodule/js/monmodule.js'),
    'models'   => 1,                                  // modèles de documents
);
```

Incorrect (trigger présent dans `core/triggers/` mais non déclaré → jamais exécuté) :
```php
$this->module_parts = array();
```

Correct :
```php
$this->module_parts = array('triggers' => 1);
```

## Déclarer les constantes de configuration

```php
$this->const[0] = array('MONMODULE_MA_CLE', 'chaine', 'valeurpardefaut',
    'Description', 1, 'current'); // 'current' = par entité
```

Lecture/écriture dans le code : `getDolGlobalString('MONMODULE_MA_CLE')` et
`dolibarr_set_const($db, 'MONMODULE_MA_CLE', $val, 'chaine', 0, '', $conf->entity)`.

## `init()` et `remove()` : activation et désactivation propres

`init()` est appelé à l'activation : il charge les tables via `_load_tables()` et
les données initiales. `remove()` est appelé à la désactivation. Toujours tester le
cycle complet activation → désactivation : un module qui s'active mais laisse des
résidus à la désactivation est un défaut.

## Ajouter des onglets sur les pages du cœur

Pour afficher un onglet du module sur une page existante (fiche tiers, facture,
utilisateur), déclarer dans `$this->tabs` :

```php
$this->tabs = array(
    // Onglet sur fiche tiers
    'thirdparty:+montab:MonOnglet:monmodule@monmodule:/monmodule/tab_thirdparty.php?id=__ID__',
    // Onglet sur fiche utilisateur
    'user:+montab:MonOnglet:monmodule@monmodule:/monmodule/tab_user.php?id=__ID__',
);
```

Format : `'objet:+identifiant:LabelTraduction:fichlang@module:chemin?id=__ID__'`.
Le `__ID__` est remplacé automatiquement par l'id de l'objet affiché.

Pour conditionner l'affichage d'un onglet selon la version Dolibarr :
```php
if ((int) DOL_VERSION > 17) {
    $this->tabs[] = 'intervention:+mytime:MonTab:monmodule@monmodule:/monmodule/tab.php?id=__ID__';
}
```

## Propriétés du descripteur — référence rapide

| Propriété | Obligatoire | Rôle |
|---|---|---|
| `$this->numero` | Oui | Identifiant unique du module |
| `$this->rights_class` | Oui | Préfixe des permissions — doit correspondre au nom du dossier |
| `$this->family` | Oui | Catégorie : `crm`, `hr`, `financial`, `projects`, `other` |
| `$this->module_position` | Non | Ordre d'affichage dans la liste des modules |
| `$this->name` | Oui | Nom technique (souvent dérivé du nom de classe) |
| `$this->description` | Oui | Description courte (une ligne) |
| `$this->descriptionlong` | Non | Description longue (DoliStore) |
| `$this->editor_name` | Non | Nom de l'éditeur (DoliStore) |
| `$this->editor_url` | Non | URL de l'éditeur |
| `$this->version` | Oui | Version sémantique (1.0.0) |
| `$this->picto` | Non | Pictogramme : `'monpicto@monmodule'` |
| `$this->depends` | Non | Modules requis : `array('modSociete')` |
| `$this->conflictwith` | Non | Modules incompatibles |

Valeurs `family` courantes : `crm`, `hr`, `financial`, `projects`, `products`,
`technic`, `portal`, `interface`, `other`.

## Déclarer des cronjobs

Un module peut déclarer des tâches planifiées exécutées par le cron Dolibarr :

```php
$this->cronjobs = array(
    0 => array(
        'label'         => 'MonJobPlanifie',
        'jobtype'       => 'method',
        'class'         => '/monmodule/class/monobjet.class.php',
        'objectname'    => 'MonObjet',
        'method'        => 'doScheduledJob',
        'parameters'    => '',
        'comment'       => 'Exécution quotidienne de la tâche',
        'frequency'     => 1,
        'unitfrequency' => 86400,  // En secondes (86400 = 1 jour)
        'status'        => 0,      // 0 = désactivé par défaut
        'test'          => 'isModEnabled("monmodule")',
    ),
);
```
