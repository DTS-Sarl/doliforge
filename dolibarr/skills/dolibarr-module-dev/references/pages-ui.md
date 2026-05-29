# Pages UI : fiche et liste

Un module expose en général une page fiche (`monobjetcard.php`) et une page liste
(`monobjetlist.php`). Toutes les pages suivent la même ossature.

## Ossature standard d'une page

L'ordre compte : initialisation → entrées → contrôle de droits → traitement des
actions → affichage.

```php
<?php
// 1. Charger l'environnement Dolibarr
$res = @include '../main.inc.php';
if (!$res) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/monmodule/class/monobjet.class.php');

// 2. Globales
global $db, $conf, $user, $langs;
$langs->load('monmodule@monmodule');

// 3. Entrées — toujours via GETPOST
$id     = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

// 4. Contrôle de droits
if (!isModEnabled('monmodule')) accessforbidden('Module non activé');
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

$object = new MonObjet($db);
if ($id > 0) $object->fetch($id);

// 5. Traitement des actions (jeton CSRF déjà requis sur le formulaire)
if ($action == 'add') {
    if (!$user->hasRight('monmodule', 'monobjet', 'write')) accessforbidden();
    $object->label = GETPOST('label', 'alphanohtml');
    $result = $object->create($user);
    if ($result < 0) setEventMessages($object->error, $object->errors, 'errors');
}

// 6. Affichage
llxHeader('', $langs->trans('MonObjet'));
// … contenu …
llxFooter();
$db->close();
```

## Encadrer l'affichage par `llxHeader()` / `llxFooter()`

Toute page affiche son contenu entre `llxHeader()` et `llxFooter()`. C'est ce qui
fournit le menu, le thème et la structure HTML de Dolibarr.

Incorrect :

```php
print '<html><body>';
print '<h1>Ma page</h1>';
print '</body></html>';
```

Correct :

```php
llxHeader('', $langs->trans('MonObjet'));
print load_fiche_titre($langs->trans('MonObjet'));
// … contenu …
llxFooter();
```

## Utiliser les retours d'erreur via `setEventMessages()`

Les erreurs d'une opération ne s'affichent pas par `print` ni `echo` brut : les
empiler avec `setEventMessages()` pour qu'elles apparaissent dans le bandeau
standard de Dolibarr.

```php
if ($result < 0) {
    setEventMessages($object->error, $object->errors, 'errors');
} else {
    setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
}
```

## Listes : filtres en `GETPOST`, pagination par `$limit`/`$offset`

Une page liste récupère ses filtres et son tri via `GETPOST`, et pagine avec
`$limit` et `$offset`. Réutiliser le motif des listes du cœur.

```php
$limit     = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$page      = GETPOSTISSET('pageplusone')
    ? (GETPOST('pageplusone') - 1) : GETPOST('page', 'int');
if ($page < 0) $page = 0;
$offset    = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
```

## Construire les URLs avec `dol_buildpath()`

Une URL codée en dur casse selon que le module est en `htdocs/custom/` ou intégré.
Toujours passer par `dol_buildpath()`.

Incorrect :

```php
$url = '/custom/monmodule/monobjetcard.php?id='.$object->id;
```

Correct :

```php
$url = dol_buildpath('/monmodule/monobjetcard.php', 1).'?id='.$object->id;
```

## Inclure les classes avec `dol_include_once()`

Ne pas utiliser `require`/`include` avec un chemin absolu en dur. `dol_include_once()`
résout le chemin que le module soit en `custom/` ou intégré.

Incorrect :

```php
require_once DOL_DOCUMENT_ROOT.'/custom/monmodule/class/monobjet.class.php';
```

Correct :

```php
dol_include_once('/monmodule/class/monobjet.class.php');
```

## Ne jamais exécuter de requête dans l'affichage

Comme en Blade Laravel : aucune requête SQL ni `fetch` dans la partie affichage de
la page. Charger les données avant `llxHeader()`, n'afficher ensuite que des
variables déjà préparées.

## Redirection après traitement (pattern PRG)

Après un traitement réussi (création, modification), toujours rediriger pour éviter
la re-soumission du formulaire (Post-Redirect-Get) :

```php
if ($action == 'add') {
    $result = $object->create($user);
    if ($result > 0) {
        setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
        header('Location: '.dol_buildpath('/monmodule/monobjetcard.php', 1).'?id='.$result);
        exit;
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
        $action = 'create';  // Reste sur le formulaire
    }
}
```

## Structure HTML d'un formulaire fiche

```php
// En-tête de fiche avec onglets
$head = monobjet_prepare_head($object);
print dol_get_fiche_head($head, 'card', $langs->trans('MonObjet'), -1, 'monobjet@monmodule');

if ($action == 'create') {
    // Formulaire de création
    print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="add">';

    print '<table class="border centpercent">';

    print '<tr><td class="titlefieldcreate fieldrequired">';
    print $langs->trans('Ref').'</td><td>';
    print '<input type="text" name="ref" value="'.GETPOST('ref', 'alphanohtml').'">';
    print '</td></tr>';

    print '<tr><td>'.$langs->trans('Label').'</td><td>';
    print '<input type="text" name="label" class="minwidth300"';
    print ' value="'.GETPOST('label', 'alphanohtml').'">';
    print '</td></tr>';

    print '<tr><td>'.$langs->trans('ThirdParty').'</td><td>';
    print $form->select_company(GETPOST('fk_soc', 'int'), 'fk_soc', '', 'SelectThirdParty');
    print '</td></tr>';

    print '</table>';

    print '<div class="center">';
    print '<input type="submit" class="button" name="add" value="'.$langs->trans('Create').'">';
    print '&nbsp;<input type="submit" class="button button-cancel" name="cancel"';
    print ' value="'.$langs->trans('Cancel').'">';
    print '</div>';
    print '</form>';

} else {
    // Affichage en lecture
    print '<table class="border centpercent">';
    print '<tr><td class="titlefield">'.$langs->trans('Ref').'</td>';
    print '<td>'.dol_escape_htmltag($object->ref).'</td></tr>';
    print '<tr><td>'.$langs->trans('Label').'</td>';
    print '<td>'.dol_escape_htmltag($object->label).'</td></tr>';
    print '</table>';
}

print dol_get_fiche_end();
```

## Classes CSS Dolibarr — référence

| Classe | Usage |
| --- | --- |
| `border centpercent` | Table avec bordures, largeur 100% |
| `noborder centpercent` | Table sans bordures internes |
| `liste_titre` | Ligne d'en-tête de tableau |
| `oddeven` | Alternance de couleurs lignes |
| `titlefield` | Colonne de label (largeur fixe) |
| `titlefieldcreate` | Colonne de label en mode création |
| `fieldrequired` | Marque un champ comme obligatoire |
| `minwidth300` | Largeur minimale 300px |
| `maxwidth500` | Largeur maximale 500px |
| `butAction` | Bouton d'action (vert) |
| `butActionDelete` | Bouton de suppression (rouge) |
| `button` | Bouton de formulaire standard |
| `button-cancel` | Bouton d'annulation |

## `GETPOSTISSET()` vs `GETPOST()`

- `GETPOST('x', 'type')` : récupère la valeur du paramètre (retourne chaîne vide
  si absent).
- `GETPOSTISSET('x')` : retourne `true`/`false` — teste l'existence sans lire la
  valeur.

Utiliser `GETPOSTISSET()` pour distinguer « paramètre absent » de « paramètre
vide ». Exemple typique : pagination (`pageplusone` peut ne pas exister dans l'URL).

## Boîtes de confirmation

Pour les actions destructives (suppression), afficher une confirmation :

```php
if ($action == 'delete') {
    $formconfirm = $form->formconfirm(
        $_SERVER['PHP_SELF'].'?id='.$object->id,
        $langs->trans('DeleteObject'),
        $langs->trans('ConfirmDeleteObject'),
        'confirm_delete',
        '', 0, 1
    );
    print $formconfirm;
}

if ($action == 'confirm_delete' && GETPOST('confirm', 'alpha') == 'yes') {
    $result = $object->delete($user);
    // ...
}
```

---

## Barre d'actions (`tabsAction`) — position et ordre natifs

La barre d'actions est **toujours** dans un `<div class="tabsAction">`, après
`dol_get_fiche_end()`. L'ordre des boutons est immuable : retour liste à gauche,
actions à droite, suppression en dernier.

```php
print dol_get_fiche_end();

print '<div class="tabsAction">';

// 1. Retour liste — toujours premier, à gauche
print dolGetButtonAction('', $langs->trans('BackToList'), 'default',
    dol_buildpath('/monmodule/monobjetlist.php', 1), '');

// 2. Bouton Modifier
if ($user->hasRight('monmodule', 'monobjet', 'write')) {
    print dolGetButtonAction('', $langs->trans('Modify'), 'default',
        $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken(), '');
}

// 3. Bouton Valider
if ($object->status == MonObjet::STATUS_DRAFT
    && $user->hasRight('monmodule', 'monobjet', 'write')) {
    print dolGetButtonAction('', $langs->trans('Validate'), 'default',
        $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&token='.newToken(),
        '', 1);
}

// 4. Suppression — toujours dernier
if ($user->hasRight('monmodule', 'monobjet', 'delete')) {
    print dolGetButtonAction('', $langs->trans('Delete'), 'delete',
        $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '');
}

print '</div>';
```

Ne jamais recréer des boutons `<a>` ou `<button>` manuels pour ces actions —
`dolGetButtonAction()` gère le style, la grisure et le tooltip natif Dolibarr.

---

## Onglets de fiche — `prepare_head()` + `dol_get_fiche_head()`

Les onglets sont déclarés dans `lib/monmodule.lib.php` :

```php
// lib/monmodule.lib.php
function monobjet_prepare_head($object)
{
    global $langs, $conf, $user;
    $langs->load('monmodule@monmodule');

    $h = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/monmodule/monobjetcard.php', 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans('Card');
    $head[$h][2] = 'card';
    $h++;

    // Onglet conditionnel (si module activé)
    if (isModEnabled('ecm')) {
        $head[$h][0] = dol_buildpath('/monmodule/monobjetcard.php', 1)
            .'?id='.$object->id.'&tab=documents';
        $head[$h][1] = $langs->trans('Documents');
        $head[$h][2] = 'documents';
        $h++;
    }

    complete_head_from_modules($conf, $langs, $object, $head, $h,
        'monobjet@monmodule');

    return $head;
}
```

Utilisation dans la page fiche :

```php
$head = monobjet_prepare_head($object);
print dol_get_fiche_head($head, 'card', $langs->trans('MonObjet'),
    -1, 'monobjet@monmodule');

// ... contenu de l'onglet ...

print dol_get_fiche_end();
```

---

## Page liste complète — structure recommandée

```php
<?php
// 1. Environnement + droits
$res = @include '../main.inc.php';
if (!$res) $res = @include '../../main.inc.php';
dol_include_once('/monmodule/class/monobjet.class.php');

global $db, $conf, $user, $langs;
$langs->load('monmodule@monmodule');

if (!isModEnabled('monmodule')) accessforbidden();
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

// 2. Paramètres de liste
$limit     = GETPOST('limit', 'int') ?: $conf->liste_limit;
$page      = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST('page', 'int');
if ($page < 0) $page = 0;
$offset    = $limit * $page;
$sortfield = GETPOST('sortfield', 'aZ09comma') ?: 't.rowid';
$sortorder = GETPOST('sortorder', 'aZ09comma') ?: 'DESC';

// 3. Filtres
$search_ref   = GETPOST('search_ref', 'alphanohtml');
$search_label = GETPOST('search_label', 'alphanohtml');

// Reset filtres
if (GETPOST('button_removefilter')) {
    $search_ref = $search_label = '';
}

// 4. Requête
$object = new MonObjet($db);
$sql = 'SELECT t.rowid, t.ref, t.label, t.date_creation, t.status';
$sql .= ' FROM '.MAIN_DB_PREFIX.'monmodule_monobjet AS t';
$sql .= ' WHERE t.entity IN ('.getEntity('monobjet').')';
if ($search_ref)   $sql .= " AND t.ref LIKE '%".$db->escape($search_ref)."%'";
if ($search_label) $sql .= " AND t.label LIKE '%".$db->escape($search_label)."%'";
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit, $offset);

$resql = $db->query($sql);

// 5. Affichage
llxHeader('', $langs->trans('MonObjets'));

$param = '';
if ($search_ref)   $param .= '&search_ref='.urlencode($search_ref);
if ($search_label) $param .= '&search_label='.urlencode($search_label);

// Bouton créer
print load_fiche_titre($langs->trans('ListOf', $langs->transnoentitiesnoconv('MonObjets')), '', 'monobjet@monmodule');

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print '<table class="noborder centpercent">';

// Ligne en-têtes colonnes avec tri
print '<tr class="liste_titre">';
print getTitleFieldOfList('Ref', 0, $_SERVER['PHP_SELF'], 't.ref', '', $param, '', $sortfield, $sortorder).'</tr>';

// Ligne filtres
print '<tr class="liste_titre_add">';
print '<td><input type="text" class="flat maxwidth75" name="search_ref"';
print ' value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td><input type="submit" class="button smallpadding" value="'.$langs->trans('Search').'"></td>';
print '</tr>';

// Lignes de données
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td><a href="monobjetcard.php?id='.$obj->rowid.'">';
        print dol_escape_htmltag($obj->ref).'</a></td>';
        print '</tr>';
    }
    $db->free($resql);
}

print '</table>';
print '</form>';

// Pagination
print_barre_liste('', $page, $_SERVER['PHP_SELF'], $param, $sortfield,
    $sortorder, '', $db->num_rows($resql), $limit, 'monobjet@monmodule');

llxFooter();
$db->close();
```

---

## Badges de statut — utiliser le système natif

Utiliser `$object->getLibStatut(5)` qui retourne un badge HTML natif Dolibarr.
Définir les badges dans la classe métier :

```php
// Dans class/monobjet.class.php
const STATUS_DRAFT     = 0;
const STATUS_VALIDATED = 1;
const STATUS_CLOSED    = 9;

public function getLibStatut($mode = 0)
{
    return $this->LibStatut($this->status, $mode);
}

public function LibStatut($status, $mode = 0)
{
    if ($mode == 0) {
        $label = '';
        if ($status == self::STATUS_DRAFT)     $label = $this->langs->trans('Draft');
        if ($status == self::STATUS_VALIDATED) $label = $this->langs->trans('Validated');
        if ($status == self::STATUS_CLOSED)    $label = $this->langs->trans('Closed');
        return $label;
    }
    if ($mode == 1 || $mode == 2 || $mode == 3 || $mode == 4 || $mode == 5) {
        $params = ['css' => 'minwidth75'];
        $label  = '';
        $picto  = '';
        if ($status == self::STATUS_DRAFT) {
            return dolGetStatus($this->langs->trans('Draft'), '', '', 'status0', $mode, 'dot', $params);
        }
        if ($status == self::STATUS_VALIDATED) {
            return dolGetStatus($this->langs->trans('Validated'), '', '', 'status1', $mode, 'dot', $params);
        }
        if ($status == self::STATUS_CLOSED) {
            return dolGetStatus($this->langs->trans('Closed'), '', '', 'status6', $mode, 'dot', $params);
        }
    }
}
```

Couleurs de statut standards (`status0` à `status9`) :

| Code | Couleur | Usage typique |
| --- | --- | --- |
| `status0` | gris | Brouillon |
| `status1` | vert | Validé / Actif |
| `status3` | jaune | En attente |
| `status4` | bleu | En cours |
| `status6` | orange | Clôturé / Archivé |
| `status8` | rouge | Annulé / Erreur |

Ne jamais créer ses propres badges CSS custom pour les statuts — utiliser
`dolGetStatus()` pour rester cohérent avec l'interface native Dolibarr.
