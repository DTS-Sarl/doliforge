# Compatibilité entre versions Dolibarr (18-23)

Dolibarr évolue entre versions. Certaines API sont dépréciées, d'autres ajoutées.
Cette fiche liste les différences critiques pour écrire du code compatible 18-23.

## Matrice de compatibilité des API

### Permissions

| Code | 18 | 19 | 20 | 21 | 22 | 23 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `$user->rights->module->object->read` | oui | oui | deprecated | deprecated | supprimé | supprimé | Ancienne syntaxe |
| `$user->hasRight('module', 'object', 'read')` | non | oui | oui | oui | oui | oui | **Utiliser celle-ci** |

Pattern compatible 18-23 :

```php
// Compatible toutes versions
if (method_exists($user, 'hasRight')) {
    $canRead = $user->hasRight('monmodule', 'monobjet', 'read');
} else {
    $canRead = !empty($user->rights->monmodule->monobjet->read);
}
```

Pour un module ciblant uniquement 19+, utiliser directement `$user->hasRight()`.

### Constantes globales

| Code | 18 | 19 | 20 | 21 | 22 | 23 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `$conf->global->CONSTANTE` | oui | oui | deprecated | deprecated | supprimé | supprimé | Accès direct |
| `getDolGlobalString('CONSTANTE')` | non | oui | oui | oui | oui | oui | **Utiliser celle-ci** |
| `getDolGlobalInt('CONSTANTE')` | non | oui | oui | oui | oui | oui | Pour les entiers |
| `getDolGlobalBool('CONSTANTE')` | non | non | oui | oui | oui | oui | Pour les booléens |

Pattern compatible 18-23 :

```php
// Compatible toutes versions
if (function_exists('getDolGlobalString')) {
    $val = getDolGlobalString('MONMODULE_OPTION', 'default');
} else {
    $val = !empty($conf->global->MONMODULE_OPTION) ? $conf->global->MONMODULE_OPTION : 'default';
}
```

### Module activé

| Code | 18 | 19 | 20 | 21 | 22 | 23 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `$conf->monmodule->enabled` | oui | oui | deprecated | deprecated | supprimé | supprimé | Accès direct |
| `isModEnabled('monmodule')` | oui | oui | oui | oui | oui | oui | **Stable depuis v14** |

### Fonctions HTML et formulaires

| Code | 18 | 19 | 20 | 21 | 22 | 23 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `print_titre()` | deprecated | deprecated | deprecated | supprimé | supprimé | supprimé | |
| `load_fiche_titre()` | oui | oui | oui | oui | oui | oui | **Utiliser celle-ci** |
| `dol_htmlentities()` | oui | oui | deprecated | deprecated | deprecated | deprecated | |
| `dol_escape_htmltag()` | oui | oui | oui | oui | oui | oui | **Utiliser celle-ci** |
| `dolGetButtonAction()` | non | non | oui | oui | oui | oui | Pour les boutons d'action |
| `dolGetButtonTitle()` | non | non | oui | oui | oui | oui | Pour les titres avec bouton |
| `dolGetStatus()` | non | non | oui | oui | oui | oui | Pour les badges de statut |

### Base de données

| Code | 18 | 19 | 20 | 21 | 22 | 23 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `$db->escape()` | oui | oui | oui | oui | oui | oui | Stable |
| `$db->plimit()` | oui | oui | oui | oui | oui | oui | Stable |
| `$db->idate()` | oui | oui | oui | oui | oui | oui | Stable |
| `$db->jdate()` | oui | oui | oui | oui | oui | oui | Stable |
| `getEntity()` | oui | oui | oui | oui | oui | oui | Stable depuis v7 |

### Objets métier

| Code | 18 | 19 | 20 | 21 | 22 | 23 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `CommonObject::$fields` | oui | oui | oui | oui | oui | oui | Pattern stable depuis v15 |
| `CommonObject::createCommon()` | oui | oui | oui | oui | oui | oui | Stable |
| `CommonObject::updateCommon()` | oui | oui | oui | oui | oui | oui | Stable |
| `CommonObject::deleteCommon()` | oui | oui | oui | oui | oui | oui | Stable |
| `CommonObject::fetchCommon()` | oui | oui | oui | oui | oui | oui | Stable |
| `CommonObject::call_trigger()` | oui | oui | oui | oui | oui | oui | Stable |

### CSRF et sécurité

| Code | 18 | 19 | 20 | 21 | 22 | 23 | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `newToken()` | oui | oui | oui | oui | oui | oui | Stable |
| `GETPOST()` | oui | oui | oui | oui | oui | oui | Stable |
| `GETPOSTINT()` | non | non | oui | oui | oui | oui | Raccourci pour GETPOST('x', 'int') |
| `GETPOSTISSET()` | oui | oui | oui | oui | oui | oui | Stable |
| `restrictedArea()` | oui | oui | oui | oui | oui | oui | Stable |

## Compatibilité PHP

| Version Dolibarr | PHP min | PHP max recommandé | Notes |
| --- | --- | --- | --- |
| 18.x | 7.4 | 8.1 | Dernière version PHP 7.4 compatible |
| 19.x | 8.0 | 8.2 | PHP 7.4 non supporté |
| 20.x | 8.0 | 8.2 | |
| 21.x | 8.1 | 8.3 | PHP 8.0 deprecated |
| 22.x | 8.1 | 8.3 | |
| 23.x | 8.1 | 8.4 | |

### Points de vigilance PHP 7.4 → 8.x

```php
// PHP 7.4 : ternaire sans parenthèses (changement de comportement en 8.0)
// AVANT (7.4) — résultat imprévisible en 8.0
$a = 1 ? 'un' : 2 ? 'deux' : 'trois';
// APRÈS (8.0+) — parenthèses obligatoires
$a = (1 ? 'un' : 2) ? 'deux' : 'trois';

// PHP 8.0 : match (non disponible en 7.4)
// Compatible 7.4+
switch ($status) {
    case 0: $label = 'Draft'; break;
    case 1: $label = 'Valid'; break;
}

// PHP 8.1 : enum, fibers, readonly, intersection types
// NE PAS utiliser si ciblant Dolibarr 18

// PHP 8.1 : passage null aux fonctions internes
// AVANT — warning en 8.1
strlen(null);
// APRÈS — sécurisé
strlen($val ?? '');
```

## Stratégie de compatibilité recommandée

### Cibler Dolibarr 19+ (recommandé pour les nouveaux modules)

- Utiliser `$user->hasRight()`, `getDolGlobalString()`, `isModEnabled()`
- Utiliser `dolGetButtonAction()`, `dolGetStatus()`
- PHP 8.0+ minimum

### Cibler Dolibarr 18+ (compatibilité maximale)

- Ajouter des wrappers de compatibilité :

```php
// lib/monmodule_compat.lib.php
if (!function_exists('getDolGlobalString')) {
    function getDolGlobalString($key, $default = '')
    {
        global $conf;
        return !empty($conf->global->$key) ? $conf->global->$key : $default;
    }
}
```

- Garder PHP 7.4 comme minimum
- Tester sur Dolibarr 18 ET 23

## Vérifier la version à l'exécution

```php
// Vérifier la version Dolibarr
if (version_compare(DOL_VERSION, '20.0.0', '>=')) {
    // Code spécifique Dolibarr 20+
} else {
    // Fallback pour versions antérieures
}

// Vérifier la version PHP
if (version_compare(PHP_VERSION, '8.1', '>=')) {
    // Syntaxe PHP 8.1+ disponible
}
```

## Checklist de compatibilité multi-versions

- [ ] Aucun accès direct à `$user->rights->...` — utiliser `hasRight()`
- [ ] Aucun accès direct à `$conf->global->...` — utiliser `getDolGlobalString()`
- [ ] Aucun accès direct à `$conf->module->enabled` — utiliser `isModEnabled()`
- [ ] Aucune fonction dépréciée (`print_titre`, `dol_htmlentities`)
- [ ] `strlen(null)` protégé — `strlen($val ?? '')`
- [ ] Ternaires parenthésés pour PHP 8.0+
- [ ] Pas de syntaxe PHP 8.1+ si ciblant Dolibarr 18
- [ ] Version minimale Dolibarr déclarée dans le descripteur (`$this->version_min`)
- [ ] Testé sur la version Dolibarr min ET max déclarées
