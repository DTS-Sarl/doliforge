# Extrafields

Les extrafields permettent d'ajouter des champs supplémentaires à un objet
du cœur ou d'un module **sans modifier de fichier source**. Ils sont gérés
nativement par Dolibarr via l'interface d'administration ou programmatiquement.

## Déclarer des extrafields dans le descripteur

Pour qu'un module crée automatiquement des extrafields à l'activation :

```php
// Dans modMonModule.class.php — méthode init()
public function init($options = '')
{
    $result = $this->_load_tables('/install/mysql/', 'monmodule');

    // Créer les extrafields programmatiquement
    include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $extrafields = new ExtraFields($this->db);

    // Ajouter un champ texte sur les tiers (societe)
    $extrafields->addExtraField(
        'monmodule_code',              // Nom du champ (préfixé pour éviter les collisions)
        'Code MonModule',              // Label
        'varchar',                     // Type : varchar, int, double, date, datetime, boolean, text, select, etc.
        100,                           // Position
        '64',                          // Taille (varchar) ou '' pour les autres types
        'societe',                     // Table cible (sans llx_ ni _extrafields)
        0,                             // Unique (0 = non, 1 = oui)
        0,                             // Obligatoire (0 = non, 1 = oui)
        '',                            // Valeur par défaut
        1,                             // Visibilité (0 = caché, 1 = visible)
        1,                             // Toujours éditable
        '',                            // Condition de visibilité
        '',                            // Paramètres (pour select, checkbox, etc.)
        '',                            // Help text
        '',                            // Condition d'activation
        0,                             // entity (0 = toutes)
        5                              // Activer le filtre dans les listes (0-5)
    );

    // Ajouter un select sur les factures
    $extrafields->addExtraField(
        'monmodule_type',
        'Type MonModule',
        'select',
        110,
        '',
        'facture',
        0, 0,
        '',                            // Valeur par défaut
        1, 1,
        '',
        // Paramètres du select : tableau clé=>valeur encodé en JSON
        json_encode(['options' => ['A' => 'Type A', 'B' => 'Type B', 'C' => 'Type C']]),
    );

    return $result;
}
```

## Supprimer les extrafields à la désactivation

```php
// Dans modMonModule.class.php — méthode remove()
public function remove($options = '')
{
    $result = parent::remove($options);

    include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
    $extrafields = new ExtraFields($this->db);

    $extrafields->delete('monmodule_code', 'societe');
    $extrafields->delete('monmodule_type', 'facture');

    return $result;
}
```

## Types d'extrafields disponibles

| Type | Stockage | Usage |
| --- | --- | --- |
| `varchar` | VARCHAR(n) | Texte court |
| `text` | TEXT | Texte long |
| `int` | INTEGER | Nombre entier |
| `double` | DOUBLE(24,8) | Nombre décimal |
| `date` | DATE | Date |
| `datetime` | DATETIME | Date et heure |
| `boolean` | BOOLEAN | Case à cocher |
| `select` | VARCHAR | Liste déroulante (paramètres JSON) |
| `sellist` | VARCHAR | Liste depuis une table SQL |
| `checkbox` | TEXT | Cases à cocher multiples |
| `radio` | VARCHAR | Boutons radio |
| `link` | INTEGER | Lien vers un objet Dolibarr |
| `phone` | VARCHAR | Numéro de téléphone |
| `mail` | VARCHAR | Adresse email |
| `url` | VARCHAR | URL |
| `password` | VARCHAR | Mot de passe (masqué) |
| `price` | DOUBLE(24,8) | Prix formaté |
| `html` | TEXT | Éditeur HTML WYSIWYG |

## Afficher les extrafields dans un formulaire

Dolibarr affiche automatiquement les extrafields si la page utilise le pattern
standard. Pour les afficher manuellement :

```php
// Charger les extrafields de l'objet
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

// En mode édition — afficher les champs de saisie
if ($action == 'edit' || $action == 'create') {
    $object->showOptionals($extrafields, 'edit');
}

// En mode lecture — afficher les valeurs
if ($action == '' || $action == 'view') {
    $object->showOptionals($extrafields, 'view');
}
```

## Extrafields sur les objets du module

Pour que les objets du module supportent les extrafields :

```php
// Dans la classe MonObjet — propriétés
public $table_element_line = 'monmodule_mondetail';  // Si lignes de détail

// Dans la classe MonObjet — méthode fetch()
public function fetch($id, $ref = '')
{
    // ... fetch standard ...

    // Charger les extrafields
    $this->fetch_optionals();

    return 1;
}

// Dans la classe MonObjet — méthode create()
public function create(User $user)
{
    // ... create standard ...

    if ($this->id > 0) {
        // Sauvegarder les extrafields
        $this->insertExtraFields();
    }

    return $this->id;
}

// Dans la classe MonObjet — méthode update()
public function update(User $user)
{
    // ... update standard ...

    // Mettre à jour les extrafields
    $this->insertExtraFields();

    return 1;
}
```

## Filtrer par extrafield dans une liste

Les extrafields peuvent être filtrés dans les listes si `filterable` > 0 :

```php
// Dans monobjetlist.php
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

// Ajouter les en-têtes de filtre extrafields
$extrafieldsobjectkey = $object->table_element;
if (!empty($extrafields->attributes[$extrafieldsobjectkey]['label'])) {
    foreach ($extrafields->attributes[$extrafieldsobjectkey]['label'] as $key => $label) {
        if ($extrafields->attributes[$extrafieldsobjectkey]['list'][$key] > 0) {
            $search_options_extra[$key] = GETPOST('search_options_'.$key, 'alphanohtml');
        }
    }
}

// Ajouter les clauses WHERE pour les filtres extrafields
if (!empty($search_options_extra)) {
    foreach ($search_options_extra as $key => $value) {
        if ($value !== '') {
            $sql .= natural_search('ef.'.$key, $value);
        }
    }
}

// Jointure avec la table extrafields
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields AS ef";
$sql .= " ON ef.fk_object = t.rowid";
```

## Convention de nommage

- **Préfixer** le nom de l'extrafield avec le nom du module : `monmodule_monchamp`
- Cela évite les collisions avec les extrafields d'autres modules
- Le label peut être sans préfixe (il sera affiché à l'utilisateur)

## Bonnes pratiques

- **Préférer les extrafields** quand le champ est une simple donnée sans logique métier
- **Préférer une table séparée** quand le champ implique de la logique complexe ou des relations
- **Supprimer** les extrafields dans `remove()` pour ne pas laisser de déchets
- **Ne pas stocker** de données critiques dans des extrafields — ils sont faciles à supprimer
- **Tester** l'activation et la désactivation du module pour vérifier la création/suppression
