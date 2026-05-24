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
|---|---|
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
