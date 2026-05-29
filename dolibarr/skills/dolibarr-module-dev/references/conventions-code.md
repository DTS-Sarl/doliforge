# Conventions de code

## Déclarer les globales en tête de fichier

Dolibarr fonctionne avec des variables globales, pas d'injection de dépendances.
Les fichiers qui les utilisent les déclarent explicitement.

```php
global $db, $conf, $user, $langs;
```

## Préférer les helpers `dol_*` aux fonctions natives

Dolibarr fournit des helpers qui gèrent les cas limites (UTF-8, formats de date,
configuration). Les utiliser plutôt que les fonctions PHP natives.

| Besoin | Helper Dolibarr |
| --- | --- |
| Date/heure courante | `dol_now()` |
| Formater une date | `dol_print_date($date, 'day')` |
| Journaliser | `dol_syslog($msg, LOG_DEBUG)` |
| Échapper du HTML | `dol_escape_htmltag($valeur)` |
| Échapper du JS | `dol_escape_js($valeur)` |
| Tronquer une chaîne | `dol_trunc($texte, 40)` |
| Longueur (UTF-8) | `dol_strlen($texte)` |

## Journaliser avec `dol_syslog()`, jamais `var_dump`/`error_log`

Incorrect :

```php
error_log('valeur = '.$x);
var_dump($object);
```

Correct :

```php
dol_syslog("MonObjet::create ref=".$this->ref, LOG_DEBUG);
```

Niveaux : `LOG_DEBUG`, `LOG_INFO`, `LOG_WARNING`, `LOG_ERR`. Préfixer le message du
nom de la classe et de la méthode.

## Respecter la convention de retour `> 0 / 0 / < 0`

Les méthodes métier ne retournent pas de booléens ni ne lèvent d'exception comme
en Laravel. Convention : `> 0` succès, `0` neutre, `< 0` erreur. Messages dans
`$this->error` (dernier) et `$this->errors[]` (liste).

Incorrect :

```php
public function valider(User $user)
{
    if (!$this->ref) throw new Exception('Ref manquante');
    return true;
}
```

Correct :

```php
public function valider(User $user)
{
    if (empty($this->ref)) {
        $this->errors[] = 'RefMissing';
        return -1;
    }
    return 1;
}
```

## Encadrer les écritures multi-tables par une transaction

```php
$this->db->begin();
$ok = $this->updateCommon($user);
if ($ok > 0) {
    $this->db->commit();
    return $ok;
}
$this->db->rollback();
return -1;
```

## Aucun texte affiché en dur — passer par les traductions

Tout texte présenté à l'utilisateur passe par un fichier `.lang` et `$langs->trans()`.

Incorrect :

```php
print 'Enregistrement sauvegardé';
```

Correct :

```php
// langs/fr_FR/monmodule.lang  ->  RecordSaved=Enregistrement sauvegardé
$langs->load('monmodule@monmodule');
print $langs->trans('RecordSaved');
```

Un fichier de langue est au format `Cle=Traduction`, une ligne par clé. Charger
avec `$langs->load('monmodule@monmodule')`. Pour une publication DoliStore, fournir
au minimum `en_US` en plus de `fr_FR`.

## Style d'écriture

- Indentation par **tabulations**.
- Classes en `CamelCase`, fichiers en minuscules.
- `print` plutôt que `echo`.
- Pas de commentaire qui paraphrase un code évident ; nommer clairement les
  variables et méthodes à la place. Les commentaires utiles expliquent le *pourquoi*.

## Vérifier la conformité avec `phpcs` (optionnel)

Dolibarr a son propre standard de codage, différent de PSR-12, publié sous forme de
ruleset PHP_CodeSniffer dans le dépôt Dolibarr : `dev/setup/codesniffer/ruleset.xml`.

Ce ruleset ne fait pas partie du projet de module et n'est pas présent dans
l'environnement de développement (un dossier autonome). Pour l'utiliser, le
récupérer séparément depuis le dépôt Dolibarr sur GitHub et disposer de phpcs
installé. C'est une vérification d'appoint : les conventions essentielles sont déjà
couvertes par cette fiche et appliquées pendant le développement.

## Page d'administration standard

Toute page d'administration doit vérifier `$user->admin` et suivre la structure
standard avec onglets :

```php
<?php
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/monmodule/lib/monmodule.lib.php');

global $db, $conf, $user, $langs;
$langs->load('monmodule@monmodule');
$langs->load('admin');

// Vérification droits admin
if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');

// Traitement des actions
if ($action == 'setvalue') {
    dolibarr_set_const($db, 'MONMODULE_OPTION', GETPOST('option', 'alphanohtml'),
        'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
}

// Affichage
llxHeader('', $langs->trans('MonModuleSetup'));

$head = monmodule_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans('MonModule'), -1, 'monmodule@monmodule');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="setvalue">';

print '<table class="noborder centpercent">';
// ... champs de configuration ...
print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();
llxFooter();
$db->close();
```

Toujours utiliser `dolibarr_set_const()` avec `$conf->entity` pour le multi-entité,
et `getDolGlobalString()` pour la lecture.

## Fichiers `.lang` — conventions détaillées

Format : une clé par ligne, `Cle=Traduction`. Commentaires avec `#`.

```ini
CHARSET=UTF-8

# ---- Administration ----
MonModuleSetup=Configuration MonModule
MonModuleAbout=À propos de MonModule

# ---- Objets métier ----
MonObjetSingular=Mon Objet
MonObjetPlural=Mes Objets
MonObjetList=Liste des objets
NewMonObjet=Nouvel objet

# ---- Messages ----
RecordSaved=Enregistrement sauvegardé
ConfirmDelete=Êtes-vous sûr de vouloir supprimer ?

# ---- Permissions ----
Permission500001=Lire les objets
Permission500002=Créer/modifier les objets
Permission500003=Supprimer les objets
```

Conventions de nommage des clés :

- **CamelCase** (pas snake_case) : `MonModuleSetup` et non `mon_module_setup`
- **Permissions** : `Permission` + ID du droit (ex : `Permission500001`)
- **Singulier/Pluriel** : fournir les deux formes
- **Préfixer** avec le nom du module pour éviter les collisions

Pour publication DoliStore, fournir au minimum `en_US` + `fr_FR`.

## Inclusion de `main.inc.php` — fallback obligatoire

Dolibarr peut être installé dans différentes arborescences. Toujours utiliser un
fallback multi-niveaux :

```php
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");
```

Adapter le nombre de niveaux selon la profondeur du fichier dans le module (racine :
2 niveaux, `admin/` : 3 niveaux, `ajax/` : 3 niveaux).

## Inclusion de classes — fallback avec `dol_buildpath`

Pour inclure des classes du module, combiner `dol_buildpath` et fallback `custom/` :

```php
$classFile = dol_buildpath('/monmodule/class/monobjet.class.php', 0);
if (!file_exists($classFile)) {
    $classFile = DOL_DOCUMENT_ROOT.'/custom/monmodule/class/monobjet.class.php';
}
require_once $classFile;
```

Alternative plus simple quand `dol_include_once` est disponible :

```php
dol_include_once('/monmodule/class/monobjet.class.php');
```

## Page d'administration multi-onglets

Quand l'admin comporte plusieurs onglets (Configuration, À propos, Logs…),
centraliser la construction des onglets dans `lib/monmodule.lib.php` :

```php
// lib/monmodule.lib.php
function monmodule_admin_prepare_head()
{
    global $langs, $conf;
    $langs->load('monmodule@monmodule');

    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/monmodule/admin/setup.php', 1);
    $head[$h][1] = $langs->trans('Settings');
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath('/monmodule/admin/logs.php', 1);
    $head[$h][1] = $langs->trans('Logs');
    $head[$h][2] = 'logs';
    $h++;

    $head[$h][0] = dol_buildpath('/monmodule/admin/about.php', 1);
    $head[$h][1] = $langs->trans('About');
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'monmodule_admin');

    return $head;
}
```

Utilisation dans chaque page admin (3e argument = clé de l'onglet actif) :

```php
$head = monmodule_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $langs->trans('MonModule'), -1, 'cog');
// … contenu de l'onglet …
print dol_get_fiche_end();
```

## Déclarer et lire plusieurs constantes de configuration

Pour un module avec plusieurs options, regrouper la lecture et l'écriture :

```php
// Lire toutes les constantes du module en une passe
$conf_provider  = getDolGlobalString('MONMODULE_PROVIDER',    'openai');
$conf_api_key   = getDolGlobalString('MONMODULE_API_KEY',     '');
$conf_max_items = (int) getDolGlobalString('MONMODULE_MAX_ITEMS', '50');
$conf_debug     = (int) getDolGlobalString('MONMODULE_DEBUG',    '0');
```

Écriture (page setup.php, action `setvalue`) :

```php
if ($action == 'setvalue') {
    $db->begin();

    $ok  = dolibarr_set_const($db, 'MONMODULE_PROVIDER',
               GETPOST('provider',   'alphanohtml'), 'chaine', 0, '', $conf->entity);
    $ok += dolibarr_set_const($db, 'MONMODULE_API_KEY',
               GETPOST('api_key',    'alphanohtml'), 'chaine', 0, '', $conf->entity);
    $ok += dolibarr_set_const($db, 'MONMODULE_MAX_ITEMS',
               (int) GETPOST('max_items', 'int'),    'chaine', 0, '', $conf->entity);
    $ok += dolibarr_set_const($db, 'MONMODULE_DEBUG',
               (int) GETPOST('debug',     'int'),    'chaine', 0, '', $conf->entity);

    if ($ok >= 0) {
        $db->commit();
        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
}
```

Conventions :

- Nommer les constantes `MODULENAME_OPTION` (majuscules, underscore)
- Toujours passer `$conf->entity` pour le multi-entité
- Type `'chaine'` pour toutes les valeurs (Dolibarr les stocke toujours en texte)
- Envelopper plusieurs `dolibarr_set_const` dans une transaction
