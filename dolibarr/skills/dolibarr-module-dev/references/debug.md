# Debug de modules Dolibarr

Méthodologie systématique pour diagnostiquer et corriger les problèmes dans un
module Dolibarr. Suivre dans l'ordre : symptôme → logs → isolation → correction.

## RÈGLE ABSOLUE — interdiction des diagnostics paresseux

Quand un développeur signale un bug (texte ou capture d'écran), il est **INTERDIT**
d'avancer les explications suivantes :

- **"Peut-être que le fichier déployé n'est pas la dernière version"** — un développeur
  sait déployer ses fichiers, c'est le B.A.BA du métier. Ne jamais insinuer qu'il ne
  l'a pas fait.
- **"Peut-être que votre navigateur bloque la requête pour des raisons de sécurité"** —
  si d'autres éléments de la page fonctionnent correctement, ce n'est pas le navigateur.
  Cette explication est fausse dans 99% des cas.
- **"Peut-être que le cache n'a pas été vidé"** — sauf si le développeur dit
  explicitement ne pas l'avoir fait, ne pas ressortir ça comme première piste.
- **Toute explication qui revient à dire que le développeur n'a pas fait son travail de
  base** — c'est condescendant et contre-productif.

Ces réponses sont **fausses, condescendantes et perdent du temps**. Le développeur est
devant son environnement — il voit ce que tu ne vois pas. Lui faire confiance.

**La bonne approche** : lire le code, les logs, la requête SQL ou le HTML rendu, et
chercher la vraie cause dans les fichiers. Poser des questions précises si des
informations manquent (contenu d'un fichier, sortie d'un log), mais jamais accuser
l'environnement ou les gestes du développeur.

---

## Méthodologie : instrumenter avant de toucher

Face à un bug, le premier réflexe n'est **PAS** de modifier du code. C'est de créer
un point de debug visuel pour obtenir des données réelles.

### Étape 1 — créer un point de debug visuel

Choisir selon le contexte :

- **Page de diagnostic** (`admin/diagnostic.php`) — pour les problèmes de configuration,
  de tables, de constantes ou de permissions
- **Bloc debug sur la page concernée** — ajouter temporairement une `<div>` qui affiche
  les variables clés directement dans la page :
  ```php
  if ($user->admin) {
      print '<div style="background:#ffe;border:1px solid #f90;padding:10px;margin:10px 0;">';
      print '<strong>DEBUG</strong><pre>';
      var_dump($maVariable);
      print '</pre></div>';
  }
  ```
- **`dol_syslog()` ciblés** — sur les variables et branchements suspects, à lire dans
  le log en temps réel

### Étape 2 — lire la sortie, identifier la vraie cause

Lire ce que le debug retourne. La cause réelle est dans ces données — pas dans une
théorie. Seulement après avoir lu la sortie, agir.

### Étape 3 — corriger chirurgicalement

Modifier **uniquement** le code identifié comme source du problème. Rien d'autre.

### Ce qui est INTERDIT pendant un debug

- Lancer des modifications en cascade pour "essayer des choses"
- Itérer sur des hypothèses sans données concrètes
- Toucher du code qui fonctionnait avant — c'est ainsi qu'on casse ce qui marchait
- Réécrire une section entière alors qu'une seule ligne est en cause

**Règle absolue : zéro modification de code sans preuve que c'est là que le bug
se trouve.** Un bug non localisé se debug, il ne se corrige pas.

---

## Régression : utiliser git avant d'ouvrir un fichier

Quand quelque chose qui fonctionnait ne fonctionne plus, `git` dit immédiatement ce
qui a changé. C'est souvent plus rapide que n'importe quel debug.

```bash
# Voir les derniers commits sur un fichier précis
git log --oneline -- htdocs/custom/monmodule/monfichier.php

# Voir exactement ce qui a changé dans le dernier commit
git diff HEAD~1 htdocs/custom/monmodule/monfichier.php

# Voir tout ce qui a changé depuis un commit stable
git diff abc1234 -- htdocs/custom/monmodule/
```

Si la régression est apparue après une modification connue, lire le diff avant toute
autre action. La cause est souvent là.

---

## Un seul bug à la fois

Ne jamais ouvrir deux fronts de debug simultanément. Si pendant le debug on découvre
un autre problème, le **noter** et l'ignorer jusqu'à ce que le premier soit résolu.

Travailler sur deux bugs en même temps aboutit systématiquement à :
- deux bugs non résolus
- du code modifié dans des zones non liées au problème initial
- une impossibilité de savoir ce qui a causé quoi

Terminer proprement le premier bug, commiter si possible, puis passer au suivant.

---

## Nettoyer les debug après correction

Tout code de debug ajouté pendant l'investigation doit être **retiré dès le bug
corrigé** :

- Blocs `<div>` de debug dans les pages
- `var_dump`, `print_r`, `die()` de diagnostic
- `dol_syslog()` ajoutés en excès pour tracer l'exécution
- Pages `diagnostic.php` temporaires

Laisser du code debug en production est une faille de sécurité (exposition de données
internes) et une source de confusion pour les interventions futures.

**Règle** : un bug corrigé = code debug retiré = commit propre.

---

## Principe : ne jamais deviner, toujours vérifier

Avant de modifier du code, confirmer où le problème se produit réellement. Un
affichage incorrect peut venir d'un échec d'écriture en base, pas d'un bug
d'affichage. Un « ça ne se déclenche pas » peut venir d'un module non activé, pas
du code du trigger.

## Activer les logs Dolibarr

### Configuration dans `conf.php`

Activer les logs Dolibarr dans `htdocs/conf/conf.php` :

```php
$dolibarr_main_prod = '0';          // Mode développement (affiche les erreurs)
$dolibarr_syslog_level = 7;         // LOG_DEBUG — tous les niveaux
$dolibarr_syslog_file = '/tmp/dolibarr.log';  // Fichier de log dédié
```

### Lire les logs

```bash
# Suivre les logs en temps réel (Linux/Mac)
tail -f /tmp/dolibarr.log | grep monmodule

# Suivre les logs Apache/nginx
tail -f /var/log/apache2/error.log | grep dolibarr

# Chercher les erreurs PHP
tail -f /var/log/apache2/error.log | grep -i "fatal\|error\|warning"
```

### Journaliser avec `dol_syslog()`

Toujours utiliser `dol_syslog()`, jamais `var_dump`, `print_r` ou `error_log` :

```php
// Dans une classe
dol_syslog("MonObjet::create ref=".$this->ref." socid=".$this->fk_soc, LOG_DEBUG);

// Avant une requête SQL problématique
dol_syslog("MonObjet::fetch sql=".$sql, LOG_DEBUG);

// Après un résultat inattendu
dol_syslog("MonObjet::create result=".$result." error=".$this->error, LOG_WARNING);

// Erreur critique
dol_syslog("MonObjet::create ERREUR CRITIQUE: ".$db->lasterror(), LOG_ERR);
```

Convention de format : `NomClasse::methode message_descriptif`, niveau approprié.

| Niveau | Usage |
|---|---|
| `LOG_DEBUG` | Trace d'exécution, valeurs de variables |
| `LOG_INFO` | Actions normales (création, modification) |
| `LOG_WARNING` | Situation anormale non bloquante |
| `LOG_ERR` | Erreur bloquante, opération échouée |

## Erreur 500 — diagnostic systématique

1. **Lire le log Apache/PHP** — le message d'erreur exact est là :
   ```bash
   tail -20 /var/log/apache2/error.log
   ```

2. **Causes fréquentes** :
   - `require_once` d'un fichier inexistant → vérifier le chemin
   - Classe non trouvée → `dol_include_once()` manquant ou chemin incorrect
   - Erreur de syntaxe PHP → vérifier le fichier indiqué dans le log
   - Timeout → traitement trop long (boucle infinie, requête SQL lente)

3. **Si aucun log visible** → vérifier `error_reporting` et `display_errors` dans
   `php.ini` ou ajouter temporairement en tête de fichier :
   ```php
   ini_set('display_errors', 1);
   error_reporting(E_ALL);
   ```
   **Ne jamais laisser ça en production.**

## Erreur 403 — CSRF et permissions

1. **Sur un formulaire POST** :
   - Token CSRF manquant → ajouter `<input type="hidden" name="token" value="'.newToken().'">`
   - Token expiré → la session a expiré, reconnecter

2. **Sur une page AJAX** :
   - `NOCSRFCHECK` non défini avant `main.inc.php` → ajouter les constantes AJAX
   - WAF (Tiger Protect, ModSecurity) bloque la requête → vérifier le contenu POST

3. **Accès refusé (accessforbidden)** :
   - Permission non assignée à l'utilisateur → vérifier dans Administration > Utilisateurs
   - `restrictedArea()` avec mauvais paramètre → vérifier table et id

## Page blanche — pas d'affichage

1. **`llxHeader()` jamais appelé** → vérifier que le code atteint l'affichage
2. **`die()` ou `exit` prématuré** → chercher dans le code les `die`, `exit`, `return`
3. **Erreur PHP fatale masquée** → activer `display_errors` temporairement
4. **Boucle infinie en inclusion** → vérifier les `require_once` circulaires

## Trigger qui ne se déclenche pas

Vérifier dans cet ordre :

1. **Module activé ?** → Administration > Modules, le module doit être vert
2. **`module_parts['triggers'] = 1` dans le descripteur ?** → sans cette déclaration,
   le trigger est ignoré
3. **Fichier correctement nommé ?** → `interface_NN_modMonModule_NomTrigger.class.php`
4. **Classe correctement nommée ?** → `class InterfaceNomTrigger extends DolibarrTriggers`
5. **Cache Dolibarr ?** → désactiver/réactiver le module
6. **`isModEnabled()` en début de `runTrigger` ?** → si oui, le module est-il bien activé ?
7. **Bon événement ?** → vérifier le nom exact (`BILL_VALIDATE`, pas `INVOICE_VALIDATE`)
8. **Ajouter un log de debug** dans `runTrigger` pour confirmer l'appel :
   ```php
   dol_syslog("MonTrigger::runTrigger action=".$action, LOG_DEBUG);
   ```

## Hook qui ne s'affiche pas

1. **Contextes déclarés dans `module_parts['hooks']` ?** → le descripteur doit lister
   les contextes exacts
2. **Nom de méthode correct ?** → doit correspondre exactement au point de hook
   (`addMoreActionsButtons`, pas `addButtons`)
3. **Vérification de `context` trop restrictive ?** → logger `$parameters['context']`
4. **Module réactivé après ajout du hook ?** → nécessaire pour que Dolibarr découvre
   la classe
5. **`$this->resprints` utilisé pour l'affichage ?** → ne pas faire `print` directement

## Requête SQL qui ne retourne rien

1. **Logger la requête** :
   ```php
   dol_syslog("DEBUG sql=".$sql, LOG_DEBUG);
   $resql = $db->query($sql);
   if (!$resql) {
       dol_syslog("SQL ERROR: ".$db->lasterror(), LOG_ERR);
   } else {
       dol_syslog("SQL num_rows=".$db->num_rows($resql), LOG_DEBUG);
   }
   ```

2. **Exécuter la requête manuellement** dans phpMyAdmin en remplaçant
   `MAIN_DB_PREFIX` par le vrai préfixe (`llx_` par défaut)

3. **Vérifier `entity`** → la cause n° 1 de « zéro résultat » est un filtre `entity`
   qui ne correspond pas. Logger `$conf->entity` et vérifier en base.

4. **Vérifier les types** → `fk_soc = '42'` (chaîne) vs `fk_soc = 42` (entier) peut
   donner des résultats différents selon le moteur

## Données qui « reviennent en arrière » après modification

Checklist dans l'ordre :

1. **L'action POST est-elle atteinte ?** → token CSRF valide ? droits OK ?
2. **`update()` / `updateCommon()` est-il appelé ?** → pas juste `$object->status = 1`
3. **Le retour est-il vérifié ?** → `if ($result < 0)` avec message d'erreur
4. **La transaction est-elle `commit()` ?** → `$db->begin()` sans `$db->commit()`
   laisse l'écriture en attente
5. **La page suivante recharge-t-elle depuis la base ?** → vérifier le `fetch()` après
   redirection

## Debug JavaScript

1. **Console navigateur** (F12) → onglet Console pour les erreurs JS
2. **Onglet Réseau** → vérifier les appels AJAX (statut, réponse, headers)
3. **Cache navigateur** → Ctrl+Shift+R pour forcer le rechargement
4. **Versionner les assets** → ajouter `?v=X.Y.Z` aux URLs CSS/JS

Erreurs AJAX fréquentes :

| Statut HTTP | Cause probable |
|---|---|
| 403 | CSRF / WAF / permissions |
| 404 | Chemin AJAX incorrect (`dol_buildpath` vs URL codée en dur) |
| 500 | Erreur PHP dans le handler AJAX (lire le log) |
| 200 mais vide | `exit` ou `die` manquant après `echo json_encode(...)` |

## Debug sur hébergement mutualisé (o2switch, OVH)

Sur un hébergement mutualisé, l'accès aux logs est limité :

1. **Activer les logs Dolibarr** dans `conf.php` vers un fichier dans le document root
2. **Créer une page de diagnostic** dans `admin/diagnostic.php` :
   ```php
   <?php
   if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
   $res = @include '../../main.inc.php';
   if (!$res) $res = @include '../../../main.inc.php';
   if (!$res) die('Include of main fails');
   if (!$user->admin) accessforbidden();

   print '<h2>Diagnostic MonModule</h2>';
   print '<pre>';

   // Version Dolibarr
   print 'DOL_VERSION: '.DOL_VERSION."\n";
   print 'PHP: '.PHP_VERSION."\n";
   print 'Module activé: '.(isModEnabled('monmodule') ? 'OUI' : 'NON')."\n";

   // Vérifier les tables
   $sql = "SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX)."monmodule_%'";
   $resql = $db->query($sql);
   print 'Tables trouvées: '.$db->num_rows($resql)."\n";

   // Vérifier les constantes
   print 'MONMODULE_OPTION: '.getDolGlobalString('MONMODULE_OPTION')."\n";

   // Vérifier les permissions
   print 'Droit read: '.($user->hasRight('monmodule', 'monobjet', 'read') ? 'OUI' : 'NON')."\n";

   print '</pre>';
   llxFooter();
   ```

3. **Supprimer ou protéger la page de diagnostic** avant mise en production

## Checklist de debug rapide

Quand quelque chose ne marche pas, vérifier dans cet ordre :

1. **Logs** → qu'est-ce que le serveur dit réellement ?
2. **Cache** → module réactivé ? navigateur vidé ?
3. **Permissions** → l'utilisateur a-t-il les droits ?
4. **CSRF** → token présent dans le formulaire ?
5. **SQL** → requête correcte ? entity filtré ? résultat vérifié ?
6. **Chemin** → `dol_buildpath()` utilisé ? bon type (0 vs 1) ?
7. **Version** → le code est-il bien déployé sur le serveur ?
