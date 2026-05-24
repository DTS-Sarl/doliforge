# Compatibilité écosystème

Un module qui « marche chez moi » mais casse une autre installation ou un autre
module est un échec. Ces règles garantissent qu'il cohabite proprement.

## Ne jamais modifier un fichier du cœur

Patcher `htdocs/core/`, `htdocs/compta/` ou un autre module est interdit : la
modification est perdue à la prochaine mise à jour de Dolibarr et casse les autres
modules. Toute interaction avec le cœur passe par hooks, triggers ou extrafields.

Incorrect (édition d'un fichier du cœur) :
```php
// htdocs/compta/facture/card.php — AJOUT d'un bloc personnalisé
```

Correct (hook sur le contexte `invoicecard`) :
```php
// class/actions_monmodule.class.php
public function formObjectOptions($parameters, &$object, &$action, $hookmanager) { }
```

## Ajouter une donnée à un objet du cœur via extrafield, pas via colonne

Pour stocker une information supplémentaire sur un tiers, une facture, un produit,
utiliser un **extrafield** — paramétrable, sans code, sans toucher la table du cœur.

Incorrect :
```sql
ALTER TABLE llx_societe ADD COLUMN mon_champ varchar(64);
```

Correct : déclarer un extrafield (via l'interface Dolibarr ou le descripteur), ou
créer une table propre au module reliée par `fk_soc`.

## Conditionner toute dépendance par `isModEnabled()`

Un module ne suppose pas qu'un autre module est présent. Avant d'utiliser une
classe, une table ou un événement d'un autre module, tester son activation.

Incorrect (plante si le module Facture est désactivé) :
```php
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
$facture = new Facture($db);
```

Correct :
```php
if (isModEnabled('facture')) {
    require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
    $facture = new Facture($db);
}
```

`isModEnabled('nom')` remplace l'ancien `!empty($conf->nom->enabled)`. Noms
courants : `societe`, `facture`, `product`, `projet`, `banque`.

## Déclarer une dépendance dure dans `$this->depends`

Si le module ne peut **pas** fonctionner sans un autre, le déclarer dans le
descripteur : Dolibarr empêchera alors l'activation sans la dépendance. À réserver
aux vraies dépendances obligatoires ; sinon préférer le test conditionnel.

```php
$this->depends = array('modSociete');
```

## Respecter le champ `entity` (multi-société)

Sur une installation multicompany, chaque société a son `entity`. Une requête qui
ignore `entity` fait fuiter les données entre sociétés.

Incorrect :
```php
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_monobjet WHERE status = 1";
```

Correct :
```php
$sql  = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
$sql .= " WHERE entity IN (".getEntity('monobjet').")";
$sql .= " AND status = 1";
```

Côté objet métier, déclarer `public $ismultientitymanaged = 1;` et inclure le
champ `entity` dans `$fields` et dans la table.

## Donner un numéro de module unique

`$this->numero` ne doit entrer en collision avec aucun autre module. Usage privé :
un nombre élevé (au-delà de 500000). Publication DoliStore : un identifiant
officiel réservé auprès de l'association Dolibarr.

## Toujours `MAIN_DB_PREFIX` et `dol_buildpath()`

Aucun préfixe `llx_` ni chemin codé en dur dans le PHP. Le module doit fonctionner
quel que soit le préfixe de base configuré et quel que soit son emplacement
(`custom/` ou intégré). Voir `base-de-donnees.md` et `pages-ui.md`.

## Ne jamais dupliquer les données Dolibarr natives

Ne pas recréer de table pour stocker des informations que Dolibarr gère déjà
(utilisateurs, tiers, produits, RIB). Toujours s'intégrer aux tables existantes via
clés étrangères (`fk_user`, `fk_soc`, `fk_product`).

Incorrect (dupliquer les données employé) :

```php
// Table llx_monmodule_employee avec nom, prénom, email...
// Données déjà dans llx_user !
```

Correct (référencer l'utilisateur natif) :

```php
// Table llx_monmodule_monobjet avec fk_user → llx_user.rowid
$sql = "SELECT t.rowid, u.firstname, u.lastname";
$sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet as t";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON t.fk_user = u.rowid";
```

## Compatibilité multi-version Dolibarr

Si le module doit supporter plusieurs versions majeures (18-23), conditionner les
appels qui diffèrent :

```php
if ((float) DOL_VERSION >= 17.0) {
    // API moderne (Dolibarr 17+)
    $result = $object->fetchCommon($id);
} else {
    // Fallback ancienne API
    $result = $object->fetch($id);
}
```

Vérifier les signatures de méthode dans la documentation ou le code source Dolibarr
avant de supposer un comportement.

## Tester le cycle complet

Avant livraison, toujours tester :

1. **Activation** → tables créées, menus visibles, droits fonctionnels
2. **Utilisation** → CRUD complet, hooks/triggers fonctionnels
3. **Désactivation** → menus retirés, pas d'erreur
4. **Réactivation** → tout revient comme avant, pas de doublon SQL
5. **Multi-entité** → données isolées entre sociétés (si applicable)
