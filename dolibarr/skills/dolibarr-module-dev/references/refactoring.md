# Refactoring de modules Dolibarr

Comment restructurer un module qui a grossi sans tout casser.
Principe directeur : **chaque étape doit laisser le module fonctionnel et commitable.**

## Quand refactoriser — et quand ne pas le faire

Refactoriser quand :
- Un fichier dépasse 500-600 lignes et mélange plusieurs responsabilités
- La même logique est copiée-collée dans 3 fichiers ou plus
- Ajouter une nouvelle fonctionnalité oblige à modifier 5 endroits

Ne pas refactoriser quand :
- Ça marche et personne ne touche à ce code
- On est en train de corriger un bug — finir le bug d'abord
- La livraison est imminente

## Règle absolue : un seul changement structurel à la fois

Ne jamais refactoriser ET corriger un bug ET ajouter une feature dans le même commit.
Chaque commit de refactoring doit être 100% neutre fonctionnellement — le comportement
ne change pas, seule la structure change.

## Extraire une classe depuis un fichier trop gros

### Étape 1 — identifier la responsabilité à extraire

Trouver un groupe de fonctions qui traitent le même sujet. Exemple : toutes les
fonctions qui calculent des montants dans un fichier lib/.

### Étape 2 — créer la nouvelle classe dans `class/`

```php
<?php
// class/services/MonCalculService.php

if (!defined('DOL_VERSION')) die('Acces interdit');

class MonModuleCalculService
{
    protected $db;
    protected $conf;

    public function __construct($db, $conf)
    {
        $this->db = $db;
        $this->conf = $conf;
    }

    // Déplacer les méthodes ici
    public function calculerMontant($base, $taux)
    {
        // ...
    }
}
```

### Étape 3 — remplacer les appels dans l'ancien fichier

Garder l'ancienne fonction comme façade temporaire pour ne pas casser l'existant :

```php
// Dans lib/monmodule.lib.php — façade de compatibilité
function monmoduleCalculerMontant($base, $taux)
{
    // Déléguer à la nouvelle classe
    $service = new MonModuleCalculService($db, $conf);
    return $service->calculerMontant($base, $taux);
}
```

### Étape 4 — commiter cette étape

```bash
git add class/services/MonCalculService.php lib/monmodule.lib.php
git commit -m "refactor: extraire MonCalculService depuis lib"
```

### Étape 5 — migrer les appels progressivement

Remplacer les appels à la façade par les appels directs à la classe, fichier par
fichier, avec un commit par fichier migré.

### Étape 6 — supprimer la façade quand tous les appels sont migrés

```bash
git commit -m "refactor: supprimer façade monmoduleCalculerMontant"
```

## Migrer des données SQL sans casser l'existant

Toujours utiliser des migrations additive-first :

1. **Ajouter** la nouvelle colonne (nullable, avec valeur par défaut)
2. **Migrer** les données existantes vers la nouvelle colonne
3. **Faire pointer** le code vers la nouvelle colonne
4. **Supprimer** l'ancienne colonne uniquement dans une version ultérieure

```sql
-- Étape 1 : ajouter (non bloquant)
ALTER TABLE llx_monmodule_objet ADD COLUMN nouveau_champ varchar(255) DEFAULT NULL;

-- Étape 2 : migrer les données
UPDATE llx_monmodule_objet SET nouveau_champ = ancien_champ WHERE nouveau_champ IS NULL;

-- Étape 3 : coder contre nouveau_champ
-- Étape 4 : supprimer ancien_champ dans la version N+1
```

Ne jamais renommer une colonne directement — ça casse toutes les installations existantes.

## Refactoring de pages PHP vers des services

Pattern recommandé pour décomposer un `generate.php` de 800 lignes :

```
Avant :
generate.php — 800 lignes (validation + orchestration + affichage + PDF)

Après :
generate.php — 60 lignes (routing + appels de services)
class/services/InputValidationService.php — validation des entrées
class/services/DocumentOrchestrationService.php — workflow de génération
class/services/DocumentDisplayService.php — affichage
class/services/DocumentFormatService.php — PDF/DOCX
```

Migrer un service à la fois, dans l'ordre : validation → orchestration → affichage.

## Checklist avant de merger un refactoring

- [ ] Le module s'active sans erreur
- [ ] Les pages principales s'affichent
- [ ] Les actions CRUD fonctionnent
- [ ] Aucune régression détectée sur les fonctionnalités touchées
- [ ] Aucun `var_dump` ou code debug laissé
- [ ] Les façades de compatibilité temporaires sont supprimées (ou notées pour la prochaine étape)