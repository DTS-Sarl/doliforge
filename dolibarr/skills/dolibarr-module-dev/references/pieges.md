# Pièges spécifiques à Dolibarr

Comportements que Dolibarr impose et qu'on rate sans le savoir — surtout en venant
de Laravel. À parcourir avant de coder, et à vérifier en audit.

## Un trigger s'exécute même quand le module est désactivé

Tant que les fichiers du trigger sont présents dans `core/triggers/`, `runTrigger`
est appelé pour chaque événement Dolibarr — y compris si le module est inactif.
Toujours commencer `runTrigger` par `if (!isModEnabled('monmodule')) return 0;`.

## La classe de hooks est appelée pour tous les contextes déclarés

Si le descripteur déclare `hooks => array('thirdpartycard', 'invoicecard')`, chaque
méthode de hook est invoquée sur **les deux** pages. Toujours vérifier
`$parameters['context']` au début de la méthode avant d'agir.

## Un champ `hidden` n'est pas une donnée de confiance

`<input type="hidden" name="fk_soc" value="42">` est modifiable par le client. Ne
jamais considérer une valeur cachée comme sûre : la passer par `GETPOST` avec un
filtre, et revérifier les droits sur l'objet ciblé.

## `GETPOST` sans filtre adapté laisse passer du contenu dangereux

`GETPOST('x')` sans 2e argument applique un filtre minimal. Pour un entier attendu,
`GETPOST('id')` peut renvoyer autre chose qu'un entier. Toujours préciser le type :
`GETPOST('id', 'int')`. À l'inverse, `restricthtml` sur un champ texte simple
laisse passer du HTML inutilement — réserver `restricthtml` aux éditeurs riches.

## La couche `$db` n'a pas de requêtes préparées

Aucun mécanisme ne sécurise les requêtes par défaut. Chaque valeur issue d'une
entrée doit être explicitement échappée (`$db->escape()`, cast `(int)`). Oublier un
seul échappement = injection SQL.

## `entity` est silencieusement oublié et fuite des données

Une requête sans clause `entity IN (getEntity(...))` fonctionne parfaitement sur
une installation mono-société — et fait fuiter les données dès qu'un client active
le multicompany. Le bug est invisible en développement.

## Modifier un `.sql` déjà livré ne met pas à jour les installations existantes

Les fichiers de `sql/` ne sont rejoués que sur une nouvelle installation ou un
changement de version du module. Éditer un fichier existant n'a aucun effet sur les
bases déjà en place : il faut un nouveau fichier de migration.

## `llx_` est une convention de fichier, pas le vrai préfixe

Dans les fichiers `.sql`, écrire `llx_` ; Dolibarr le remplace par `MAIN_DB_PREFIX`
à l'installation. Mais dans le PHP, `llx_` en dur ne sera pas remplacé et cassera
toute installation au préfixe personnalisé.

## Persistance d'état : une propriété modifiée n'est pas sauvegardée tant qu'`update()` n'est pas appelé

Changer `$object->status = 1` en mémoire ne touche pas la base. Si un statut
« revient en arrière » après navigation, la cause est presque toujours : soit
l'`update()` / `updateCommon()` n'a jamais été appelé, soit son retour `< 0` n'a
pas été vérifié (échec silencieux), soit la page suivante recharge l'objet depuis
une base où l'écriture n'a pas eu lieu. Vérifier dans l'ordre : l'action POST
est-elle bien atteinte (jeton CSRF, droits) ; `update()` est-il appelé ; son retour
est-il contrôlé ; la transaction est-elle bien `commit()`. Ne jamais conclure à un
bug d'affichage avant d'avoir confirmé que l'écriture a réellement eu lieu en base.

## Le retour d'une méthode CRUD doit être testé

`$object->create($user)` peut retourner `< 0` sans rien interrompre. Sans
`if ($result < 0) { ... }`, l'échec passe inaperçu et la page affiche un faux
succès. Toujours tester le retour et remonter `$object->errors` via
`setEventMessages()`.

## `$notrigger` court-circuite les événements

Appeler `createCommon($user, 1)` ou `update($user, 1)` empêche l'émission des
triggers `<ELEMENT>_CREATE` / `_MODIFY`. Utile pour éviter une récursion, mais si
un autre module (ou une synchro) dépend de ces événements, il ne se déclenchera
pas. N'utiliser `$notrigger = 1` que volontairement.

## Le cache des modules : un nouveau fichier n'est pas toujours vu immédiatement

Après ajout d'un trigger, d'un hook ou d'une page, il peut être nécessaire de
désactiver/réactiver le module (ou vider le cache) pour que Dolibarr le découvre.
Un « ça ne se déclenche pas » vient souvent de là, pas du code.

## `dol_buildpath()` : bien choisir le type

`dol_buildpath('/monmodule/x.php', 0)` renvoie un chemin physique ;
`dol_buildpath('/monmodule/x.php', 1)` une URL relative. Se tromper de type produit
un lien ou une inclusion cassés selon que le module est en `custom/` ou intégré.

## Les assets JS/CSS sont cachés agressivement

Après modification d'un fichier JS ou CSS, le navigateur peut servir l'ancienne
version. Solutions :

- Ajouter un paramètre de version à l'URL : `monmodule.css?v=1.2.0`
- Forcer un rafraîchissement dur (Ctrl+Shift+R)
- Demander à l'utilisateur de vider son cache navigateur

Ce piège cause 90% des « ma modif JS ne marche pas ».

## Tiger Protect / WAF bloque les requêtes AJAX

Sur les hébergeurs avec WAF (o2switch Tiger Protect, OVH ModSecurity), certaines
requêtes AJAX sont bloquées (403). Causes fréquentes :

- Corps POST contenant du HTML ou SQL (déclenche les règles WAF)
- `Content-Type` manquant ou incorrect sur les requêtes AJAX
- Requêtes POST sans `NOCSRFCHECK` défini avant `main.inc.php`

Solution : s'assurer que les constantes AJAX sont définies et que les données
envoyées ne ressemblent pas à une attaque.

## `restrictedArea()` non appelé = pas de contrôle objet

`hasRight()` vérifie le droit global (« peut lire les objets »).
`restrictedArea()` vérifie le droit **sur un objet précis** (« peut lire CET
objet, compte tenu de son entité/société »). L'un ne remplace pas l'autre.

Pattern complet :

```php
// Droit global
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

// Droit sur l'objet précis (si id connu)
if ($id > 0) {
    restrictedArea($user, 'monmodule', $id, 'monmodule_monobjet');
}
```

## Les constantes dans `$this->const` ne sont pas recréées à chaque activation

Les constantes déclarées dans le descripteur sont créées **seulement si elles
n'existent pas encore**. Si la valeur par défaut change entre deux versions du
module, les installations existantes gardent l'ancienne valeur. Pour forcer une mise
à jour, utiliser un fichier de migration ou `dolibarr_set_const()` dans `init()`.

## `getDolGlobalString()` vs `getDolGlobalInt()`

- `getDolGlobalString('CLE')` retourne une chaîne (vide si non définie)
- `getDolGlobalInt('CLE')` retourne un entier (0 si non défini)
- `isModEnabled('nom')` remplace `!empty($conf->nom->enabled)` (Dolibarr 15+)

Ne pas utiliser `$conf->global->CLE` directement — déprécié et risque de notice PHP.
