# Internationalisation des modules Dolibarr

Gérer les traductions correctement dans un module Dolibarr.

## Structure des fichiers de langue

```
monmodule/
└── langs/
    ├── fr_FR/
    │   └── monmodule.lang
    └── en_US/
        └── monmodule.lang
```

Toujours fournir au minimum `fr_FR` et `en_US`. Dolibarr utilise `en_US` comme
fallback si la langue de l'utilisateur n'est pas disponible.

## Format du fichier `.lang`

```ini
# Fichier : langs/fr_FR/monmodule.lang

# En-tête obligatoire
ModuleMonModuleName = Mon Module
ModuleMonModuleDesc = Description courte du module

# Navigation
MonModuleMenu          = Mon Module
MonModuleMenuList      = Liste
MonModuleMenuNew       = Nouveau

# Objets
MonObjet               = Mon Objet
MonObjets              = Mes Objets
NewMonObjet            = Nouvel objet
MonObjetModified       = Objet modifié
MonObjetDeleted        = Objet supprimé

# Champs
FieldRef               = Référence
FieldLabel             = Libellé
FieldStatus            = Statut
FieldDateCreation      = Date de création

# Messages
MonObjetCreated        = Objet créé avec succès
MonObjetUpdated        = Objet mis à jour
MonObjetDeleteConfirm  = Confirmer la suppression de cet objet ?

# Statuts
StatusDraft            = Brouillon
StatusValidated        = Validé
StatusClosed           = Clôturé
StatusCancelled        = Annulé

# Erreurs
ErrorFieldRequired     = Le champ %s est obligatoire
ErrorRefAlreadyExists  = La référence %s existe déjà
```

### Règles de nommage

- Clés en **PascalCase** sans espaces ni tirets
- Préfixer avec le nom du module pour éviter les conflits : `MonModule*`
- Pas d'accents dans les clés — uniquement dans les valeurs
- Une clé par ligne, séparée par `=` sans espaces autour

## Charger et utiliser les traductions

### Charger le fichier de langue

```php
// En tête de page, après $langs est disponible
$langs->loadLangs(['monmodule@monmodule', 'other']);
// ou
$langs->load('monmodule@monmodule');
```

Le `@monmodule` indique que le fichier est dans le module, pas dans le cœur Dolibarr.

### Traduire une clé simple

```php
// Affichage
print $langs->trans('MonObjet');

// Dans un attribut HTML (échapper)
print '<input placeholder="'.dol_escape_htmltag($langs->trans('FieldLabel')).'">';

// Comparaison — NE PAS utiliser trans() pour comparer, utiliser les constantes
```

### Traduction avec paramètres

Les paramètres s'insèrent avec `%s`, `%d`, etc. :

```ini
# Dans le fichier .lang
ErrorFieldRequired = Le champ "%s" est obligatoire
RecordCreatedBy    = Créé par %s le %s
ItemsFound         = %d enregistrement(s) trouvé(s)
```

```php
// Passer les paramètres à trans()
print $langs->trans('ErrorFieldRequired', $langs->trans('FieldRef'));
print $langs->trans('RecordCreatedBy', $user->getFullName($langs), dol_print_date($date, 'day'));
print $langs->trans('ItemsFound', $count);
```

### Pluriels

Dolibarr ne gère pas nativement les pluriels — gérer manuellement :

```php
// Pattern recommandé
$key = ($count <= 1) ? 'MonObjet' : 'MonObjets';
print $count.' '.$langs->trans($key);
```

## Affichage conditionnel selon la langue

```php
// Tester la langue active
if ($langs->defaultlang === 'fr_FR') {
    // Contenu spécifique au français
}

// Récupérer la langue courte
$lang_code = substr($langs->defaultlang, 0, 2); // 'fr', 'en', etc.
```

## Traductions dans le descripteur

Les labels des droits, menus et onglets dans `modMonModule.class.php` utilisent
les clés de langue — pas de texte en dur :

```php
// Droits
$this->rights[$r][3] = 'Lire les objets';           // NE PAS faire ça
$this->rights[$r][3] = 'MonModuleReadObjects';        // CLE DE LANGUE

// Menus
$this->menu[$r]['titre'] = 'Mon Module';              // NE PAS faire ça
$this->menu[$r]['titre'] = 'MonModuleMenu';           // CLE DE LANGUE
```

## Jamais de texte en dur dans le code PHP

```php
/* INTERDIT — texte en dur */
print 'Objet créé avec succès';
setEventMessages('Erreur : champ manquant', null, 'errors');

/* CORRECT — via trans() */
print $langs->trans('MonObjetCreated');
setEventMessages($langs->trans('ErrorFieldRequired', 'Référence'), null, 'errors');
```

## Checklist i18n avant livraison

- [ ] Fichiers `fr_FR` et `en_US` présents et complets
- [ ] Toutes les clés utilisées dans le code sont définies dans les deux langues
- [ ] Aucun texte affiché en dur dans les fichiers PHP
- [ ] Les clés sont préfixées avec le nom du module
- [ ] Les paramètres `%s` correspondent aux arguments passés à `trans()`
- [ ] Les labels du descripteur (droits, menus) utilisent des clés de langue
