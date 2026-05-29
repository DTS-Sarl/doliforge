# Hooks et Triggers

Principe fondateur : **un module ne modifie jamais un fichier du cœur**. Pour
réagir à Dolibarr ou étendre une page existante, deux mécanismes.

- **Trigger** : réagir à un *événement métier* (facture validée, tiers créé). Logique.
- **Hook** : *injecter du code* dans un point d'une page existante (onglet, bouton,
  colonne). Affichage/comportement.

## Choisir le bon mécanisme

| Besoin | Mécanisme |
|---|---|
| Agir quand un objet est créé/validé/supprimé | Trigger |
| Synchroniser des données après un événement | Trigger |
| Ajouter un onglet / bouton / colonne sur une page | Hook |
| Intercepter une action utilisateur sur une page | Hook (`doActions`) |
| Stocker une donnée supplémentaire sur un objet du cœur | Extrafield (aucun code) |

Question simple : « *quand X arrive, faire Y* » → trigger. « *sur la page Z,
afficher/permettre Y* » → hook.

## Triggers

Un trigger est une classe dans `core/triggers/`, nommée selon le motif strict
`interface_NN_modMonModule_NomTrigger.class.php` (`NN` = priorité 00-99). Activé par
`module_parts['triggers'] = 1` dans le descripteur.

```php
<?php
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

class InterfaceMonTrigger extends DolibarrTriggers
{
    public function __construct($db)
    {
        $this->db = $db;
        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = 'monmodule';
        $this->description = "Triggers du module MonModule";
        $this->version = '1.0.0';
    }

    public function runTrigger($action, $object, User $user, Translate $langs,
        Conf $conf)
    {
        if (!isModEnabled('monmodule')) return 0;   // indispensable

        switch ($action) {
            case 'BILL_VALIDATE':
                dol_syslog("MonModule: facture validée ".$object->id, LOG_DEBUG);
                return 1;
        }
        return 0;
    }
}
```

### Filtrer par `isModEnabled()` en début de `runTrigger`

Le trigger est appelé pour **tout** événement Dolibarr tant que ses fichiers sont
présents, même si le module est désactivé. Sans ce filtre, le code s'exécute hors
contexte.

Incorrect :
```php
public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
{
    switch ($action) { case 'BILL_VALIDATE': /* ... */ }
}
```

Correct :
```php
public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
{
    if (!isModEnabled('monmodule')) return 0;
    switch ($action) { case 'BILL_VALIDATE': /* ... */ }
}
```

### Garder un trigger léger

`runTrigger` s'exécute dans la transaction de l'objet. Un traitement lourd (appel
réseau, génération de document) y bloque tout. Déléguer ce travail à une tâche cron.
Retour : `> 0` traité, `0` non concerné, `< 0` erreur (messages dans `$this->errors[]`).

## Hooks

Un hook injecte du comportement dans une page existante sans la modifier.

**Étape 1 — déclarer les contextes** dans le descripteur :
```php
$this->module_parts['hooks'] = array('thirdpartycard', 'invoicecard');
```

**Étape 2 — créer la classe** `class/actions_monmodule.class.php`. Chaque méthode
porte le nom d'un point de hook et est appelée automatiquement.

```php
<?php
class ActionsMonModule
{
    public $results = array();
    public $resprints;
    public $errors = array();

    public function addMoreActionsButtons($parameters, &$object, &$action,
        $hookmanager)
    {
        global $user, $langs;
        if (!in_array('invoicecard', explode(':', $parameters['context']))) {
            return 0;
        }
        if ($user->hasRight('monmodule', 'monobjet', 'read')) {
            $this->resprints = '<a class="butAction" href="...">'
                .$langs->trans('MonAction').'</a>';
        }
        return 0;
    }
}
```

### Toujours vérifier le `context` reçu

La classe de hooks est appelée pour **tous** les contextes déclarés. Sans
vérification, le code s'exécute sur des pages non prévues.

Incorrect (s'exécute aussi sur `thirdpartycard`) :
```php
public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
{
    $this->resprints = '<a href="...">Mon bouton facture</a>';
    return 0;
}
```

Correct :
```php
public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
{
    if (!in_array('invoicecard', explode(':', $parameters['context']))) return 0;
    $this->resprints = '<a href="...">Mon bouton facture</a>';
    return 0;
}
```

### Conventions des hooks

- Signature toujours `($parameters, &$object, &$action, $hookmanager)`.
- Texte à afficher → `$this->resprints`. Données à remonter → `$this->results`.
- Retour `0` = poursuite normale ; `1` = remplace le traitement natif (à utiliser
  avec prudence) ; `< 0` = erreur.
- Contrôler les permissions à l'intérieur du hook.
- Points fréquents : `doActions`, `addMoreActionsButtons`, `formObjectOptions`,
  `printCommonFooter`, `getNomUrl`, `completeTabsHead`,
  `printFieldListWhere` / `printFieldListOption`.

## Points de hook fréquents — référence

| Point de hook | Contexte typique | Rôle |
|---|---|---|
| `doActions` | Toute page | Intercepter actions utilisateur |
| `addMoreActionsButtons` | Fiches (card.php) | Ajouter boutons d'action |
| `formObjectOptions` | Fiches | Ajouter champs au formulaire |
| `completeTabsHead` | Fiches | Modifier les onglets |
| `printFieldListWhere` | Listes (list.php) | Ajouter clauses WHERE |
| `printFieldListOption` | Listes | Ajouter filtres en en-tête |
| `printCommonFooter` | Global | Injecter JS/HTML en fin de page |
| `getNomUrl` | Global | Modifier le lien hypertexte d'un objet |
| `formBuilddocOptions` | Documents | Options supplémentaires génération doc |

## Exemple de hook `doActions` — intercepter une action

```php
public function doActions($parameters, &$object, &$action, $hookmanager)
{
    global $conf, $db, $user;

    if (!in_array('invoicecard', explode(':', $parameters['context']))) return 0;

    if ($action == 'monaction') {
        if (!$user->hasRight('monmodule', 'monobjet', 'write')) {
            accessforbidden();
            return -1;
        }

        // Traitement...
        $result = $this->doSomething($object);
        if ($result > 0) {
            setEventMessages('Action réalisée', null, 'mesgs');
        } else {
            setEventMessages('Erreur', null, 'errors');
        }
        $action = '';  // Empêcher le traitement natif
        return 1;      // 1 = remplace le traitement par défaut
    }

    return 0;
}
```

## Triggers — convention non-bloquant

Un trigger ne doit **jamais** bloquer l'opération principale. Pattern observé dans
les modules DTS SARL en production :

```php
public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
{
    if (!isModEnabled('monmodule')) return 0;

    switch ($action) {
        case 'BILL_VALIDATE':
            try {
                // Traitement...
                dol_syslog("MonModule::trigger BILL_VALIDATE id=".$object->id, LOG_DEBUG);
            } catch (Throwable $e) {
                // Logger mais ne JAMAIS bloquer
                dol_syslog("MonModule::trigger error: ".$e->getMessage(), LOG_ERR);
            }
            return 0;  // Toujours retourner 0 (non-bloquant)
    }

    return 0;
}
```

**Règle** : un trigger `return 0` même après traitement (non concerné = non
bloquant). Retourner `< 0` uniquement si l'opération principale **doit** être
annulée (cas rare). Envelopper le code métier dans `try/catch` et logger les erreurs
sans re-throw.

## Extrafields — étendre sans coder

Pour ajouter un champ à un objet du cœur **sans code**, utiliser les extrafields
(Administration > Champs supplémentaires). Cas d'usage :

- Ajouter un champ texte sur les tiers (pas de table personnalisée nécessaire)
- Ajouter un select sur les factures (configuration, pas code)

Quand préférer un hook/table séparée plutôt qu'un extrafield :

- Logique métier complexe associée au champ
- Relations entre plusieurs champs
- Données volumineuses (historique, lignes de détail)

## Événements trigger courants — référence

| Événement | Déclencheur |
|---|---|
| `COMPANY_CREATE` / `COMPANY_MODIFY` / `COMPANY_DELETE` | Tiers |
| `CONTACT_CREATE` / `CONTACT_MODIFY` / `CONTACT_DELETE` | Contact |
| `BILL_CREATE` / `BILL_VALIDATE` / `BILL_DELETE` | Facture |
| `BILL_PAYED` / `BILL_UNPAYED` | Paiement facture |
| `ORDER_CREATE` / `ORDER_VALIDATE` / `ORDER_DELETE` | Commande |
| `PROPAL_CREATE` / `PROPAL_VALIDATE` / `PROPAL_CLOSE_SIGNED` | Proposition |
| `CONTRACT_CREATE` / `CONTRACT_VALIDATE` | Contrat |
| `TASK_CREATE` / `TASK_MODIFY` / `TASK_DELETE` | Tâche projet |
| `USER_CREATE` / `USER_MODIFY` / `USER_DELETE` | Utilisateur |
| `FICHINTER_CREATE` / `FICHINTER_VALIDATE` | Intervention |
| `ACTION_CREATE` / `ACTION_MODIFY` | Événement agenda |

---

## Exemple complet — ajouter un onglet sur la fiche tiers

**Descripteur** :
```php
$this->module_parts['hooks'] = ['thirdpartycard'];
```

**`class/actions_monmodule.class.php`** :
```php
public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
{
    global $langs, $user;

    if (!in_array('thirdpartycard', explode(':', $parameters['context']))) return 0;
    if (!isModEnabled('monmodule')) return 0;
    if (!$user->hasRight('monmodule', 'monobjet', 'read')) return 0;

    $langs->load('monmodule@monmodule');

    $nbItems = 0; // Optionnel : nombre d'éléments pour le badge
    $this->results[] = [
        'url'      => dol_buildpath('/monmodule/monobjetlist.php', 1)
                      .'?fk_soc='.$object->id,
        'label'    => $langs->trans('MonObjets'),
        'badge'    => $nbItems ?: null,
        'badgeClass'=> 'badgeneutral',
        'key'      => 'monobjet',
    ];
    return 1;
}
```

---

## Exemple complet — ajouter des champs sur un formulaire (`formObjectOptions`)

Ajouter des champs supplémentaires sur la fiche de création/édition d'une facture :

```php
public function formObjectOptions(&$parameters, &$object, &$action, $hookmanager)
{
    global $db, $langs, $user;

    if (!in_array('invoicecard', explode(':', $parameters['context']))) return 0;
    if (!isModEnabled('monmodule')) return 0;

    $langs->load('monmodule@monmodule');

    if ($action == 'create' || $action == 'edit') {
        // Affichage du champ dans le formulaire
        $this->resprints .= '<tr class="oddeven">';
        $this->resprints .= '<td>'.$langs->trans('MonChamp').'</td>';
        $this->resprints .= '<td><input type="text" name="monchamp" class="minwidth200"';
        $this->resprints .= ' value="'.dol_escape_htmltag(GETPOST('monchamp', 'alphanohtml')).'"></td>';
        $this->resprints .= '</tr>';
    } else {
        // Affichage en lecture
        $val = ''; // Lire depuis la table du module
        $this->resprints .= '<tr class="oddeven">';
        $this->resprints .= '<td>'.$langs->trans('MonChamp').'</td>';
        $this->resprints .= '<td>'.dol_escape_htmltag($val).'</td>';
        $this->resprints .= '</tr>';
    }
    return 0;
}
```

Persister la valeur dans `doActions` (même classe) :

```php
public function doActions(&$parameters, &$object, &$action, $hookmanager)
{
    global $db, $user;

    if (!in_array('invoicecard', explode(':', $parameters['context']))) return 0;

    if (($action == 'add' || $action == 'update') && $object->id > 0) {
        $val = GETPOST('monchamp', 'alphanohtml');
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."monmodule_invoice_extra (fk_invoice, monchamp)";
        $sql .= " VALUES ('".(int)$object->id."', '".$db->escape($val)."')";
        $sql .= " ON DUPLICATE KEY UPDATE monchamp = '".$db->escape($val)."'";
        $db->query($sql);
    }
    return 0;
}
```

---

## Exemple complet — ajouter une colonne sur une liste (`printFieldListOption`)

Ajouter une colonne "Mon champ" sur la liste des tiers :

**Descripteur** :

```php
$this->module_parts['hooks'] = ['thirdpartylist'];
```

**Hook `printFieldListTitle`** (en-tête de colonne) :

```php
public function printFieldListTitle(&$parameters, &$object, &$action, $hookmanager)
{
    global $langs;

    if (!in_array('thirdpartylist', explode(':', $parameters['context']))) return 0;
    $langs->load('monmodule@monmodule');

    $this->resprints = '<th class="liste_titre">'.$langs->trans('MonChamp').'</th>';
    return 0;
}
```

**Hook `printFieldListValue`** (valeur par ligne) :
```php
public function printFieldListValue(&$parameters, &$object, &$action, $hookmanager)
{
    if (!in_array('thirdpartylist', explode(':', $parameters['context']))) return 0;

    $val = ''; // Récupérer la valeur depuis la table du module
    $this->resprints = '<td>'.dol_escape_htmltag($val).'</td>';
    return 0;
}
```

**Hook `printFieldListWhere`** (filtre SQL optionnel) :
```php
public function printFieldListWhere(&$parameters, &$object, &$action, $hookmanager)
{
    global $db;

    if (!in_array('thirdpartylist', explode(':', $parameters['context']))) return 0;

    // Jointure SQL additionnelle
    $this->resprints = ' LEFT JOIN '.MAIN_DB_PREFIX.'monmodule_extra AS me'
        .' ON me.fk_soc = t.rowid';
    return 0;
}
```
