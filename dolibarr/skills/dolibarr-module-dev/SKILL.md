---
name: dolibarr-module-dev
description: >-
  Développement, création et audit de modules Dolibarr ERP/CRM (versions 21 à 23).
  Couvre la structure d'un module, le descripteur modXxx.class.php, les objets métier
  CommonObject et le pattern $fields, la base de données, la sécurité (GETPOST, CSRF,
  échappement SQL, permissions), l'intégration sans surcharge du cœur via hooks et
  triggers, la compatibilité multi-entité et la publication DoliStore.
  Utilise impérativement ce skill dès que l'utilisateur travaille sur un module
  Dolibarr — créer un module, ajouter un objet métier, une page, un hook, un trigger,
  une API REST, ou auditer/corriger/sécuriser du code Dolibarr existant (DoliHotel,
  DoliTaxes, DoliStation ou tout autre module). Déclenche-le même pour une demande
  brève comme « ajoute un champ », « corrige ce bug », « relis ce fichier » en
  contexte de module Dolibarr.
---

# Développement de modules Dolibarr (21-23)

Produire du code de module Dolibarr **propre, sécurisé et compatible écosystème**,
et auditer du code existant selon les mêmes critères.

## Avant de commencer

L'utilisateur est développeur PHP/Laravel senior mais débutant sur Dolibarr. Le
piège principal est d'appliquer les réflexes Laravel/PSR : Dolibarr a ses propres
conventions, antérieures aux standards modernes, et les ignorer **casse la
compatibilité**. Ne jamais « inventer une meilleure façon de faire » si Dolibarr
impose une convention — la cohérence avec le cœur prime sur l'élégance.

| Réflexe Laravel | Équivalent Dolibarr |
|---|---|
| Eloquent / migrations | Classe `extends CommonObject` + fichiers `.sql` |
| `$request->input()` | `GETPOST('nom', 'type')` — jamais `$_GET`/`$_POST` |
| Injection de dépendances | Globales : `global $db, $conf, $user, $langs;` |
| Requêtes préparées PDO | Échappement manuel : `$db->escape()`, casts `(int)` |
| Middleware d'autorisation | `$user->hasRight()` + `restrictedArea()` en tête de page |
| Events / Listeners | Triggers et Hooks |

## Workflow

Identifier le mode. Produire du neuf → **Création**. Relire/corriger/sécuriser du
code fourni → **Audit**. Les deux peuvent s'enchaîner.

**Création** — dérouler dans l'ordre, en lisant la fiche correspondante :
1. Structure du module → `references/structure-module.md`
2. Descripteur `modXxx.class.php` → `references/descripteur.md`
3. Objets métier → `references/objets-metier.md`
4. Base de données → `references/base-de-donnees.md`
5. Pages fiche/liste → `references/pages-ui.md`
6. Intégration (réagir à un événement, étendre une page) → `references/hooks-et-triggers.md`
7. Toujours : appliquer `references/securite.md` et `references/conventions-code.md`.

Pour un module neuf, recommander d'utiliser le **Module Builder** intégré (Accueil >
Configuration > Modules > Module Builder) pour générer le squelette conforme, puis
intervenir dessus.

**Audit** — dérouler trois passes, dans cet ordre, sans s'arrêter au seul bug
signalé (un bug isolé révèle souvent une classe de problèmes) :
1. Sécurité → `references/securite.md`
2. Compatibilité écosystème → `references/compatibilite-ecosysteme.md`
3. Qualité / conventions → `references/conventions-code.md`

Vérifier aussi `references/pieges.md`. Pour chaque problème : citer fichier/ligne,
expliquer le risque concret, proposer le correctif minimal — l'utilisateur préfère
les corrections ciblées aux réécritures.

## Règles d'or non négociables

Toute violation est un défaut à signaler ou corriger.

1. Toute entrée passe par `GETPOST()` avec un type de filtre adapté — jamais
   `$_GET` / `$_POST` / `$_REQUEST`.
2. Toute action qui écrit est protégée par jeton CSRF (`newToken()`).
3. Toute valeur en SQL est échappée (`$db->escape()`, cast `(int)`).
4. Toute page contrôle les permissions avant traitement (`$user->hasRight()`,
   `accessforbidden()` / `restrictedArea()`).
5. Aucun fichier du cœur n'est modifié — intégration via hooks, triggers, extrafields.
6. Le champ `entity` est respecté pour le multi-société (`getEntity()`).
7. Aucune dépendance dure non déclarée — conditionner par `isModEnabled()`.
8. Les chemins/URLs passent par `dol_buildpath()`, les inclusions par
   `dol_include_once()`.

## Principe « vérifier, ne pas supposer »

Dolibarr 21, 22 et 23 diffèrent. Le module se développe dans un dossier autonome,
sans installation Dolibarr autour : il n'y a pas de sources locales à consulter.
Ne pas se fier à la mémoire pour une signature de méthode ou un comportement propre
à une version : vérifier contre la documentation développeur Dolibarr officielle ou
le dépôt source Dolibarr sur GitHub (via recherche web). En cas de doute sur un
point de version, le dire et vérifier plutôt que d'affirmer.

## Top 3 des pièges à connaître avant de coder

1. **Un trigger s'exécute même module désactivé** — toujours commencer `runTrigger`
   par `if (!isModEnabled('monmodule')) return 0;`.
2. **`entity` oublié = fuite de données** — invisible en mono-société, critique en
   multicompany. Toujours `WHERE entity IN (getEntity(...))`.
3. **NOCSRFCHECK pour les pages AJAX** — sans cette constante *avant*
   `main.inc.php`, les appels AJAX retournent 403.

## Fiches de référence

Charger uniquement la fiche pertinente au moment voulu :

- `references/structure-module.md` — arborescence et emplacement des fichiers.
- `references/descripteur.md` — descripteur : numéro, droits, menus, `module_parts`.
- `references/objets-metier.md` — `CommonObject`, `$fields`, méthodes CRUD.
- `references/base-de-donnees.md` — fichiers SQL, préfixe, `entity`, migrations, index.
- `references/securite.md` — `GETPOST`, CSRF, échappement SQL, permissions, pages AJAX.
- `references/hooks-et-triggers.md` — intégration sans surcharge du cœur.
- `references/pages-ui.md` — structure des pages fiche/liste, formulaires, CSS.
- `references/conventions-code.md` — helpers `dol_*`, retours, transactions, logs, i18n, admin.
- `references/compatibilite-ecosysteme.md` — multi-entité, dépendances, extrafields.
- `references/pieges.md` — pièges spécifiques à Dolibarr.
- `references/dolistore-publication.md` — checklist de publication DoliStore.
- `references/debug.md` — méthodologie de debug, `dol_syslog`, erreurs courantes.
- `references/refactoring.md` — restructurer un module sans tout casser, extraction de classes, migrations SQL.
- `references/performance.md` — requêtes lentes, N+1, index SQL, pagination, cache.
- `references/tests.md` — page de test admin, fixtures SQL, checklist de recette avant livraison.
- `references/api-rest.md` — consommer et exposer des endpoints REST Dolibarr.
