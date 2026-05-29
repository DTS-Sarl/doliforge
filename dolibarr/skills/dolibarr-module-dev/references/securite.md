# Sécurité

Dolibarr n'a ni ORM ni requêtes préparées qui sécurisent par défaut. La sécurité
**dépend entièrement de la discipline du développeur**. C'est le poste où un
débutant Dolibarr fait le plus de dégâts.

## Toute entrée passe par `GETPOST()`

Ne jamais lire `$_GET`, `$_POST` ou `$_REQUEST` en direct. `GETPOST($param, $check)`
filtre l'entrée selon un type. Choisir le filtre le plus strict compatible avec
l'usage.

Incorrect :
```php
$id = $_GET['id'];
$label = $_POST['label'];
```

Correct :
```php
$id     = GETPOST('id', 'int');
$ref    = GETPOST('ref', 'aZ09');
$label  = GETPOST('label', 'alphanohtml');
$action = GETPOST('action', 'aZ09');
$note   = GETPOST('note', 'restricthtml');   // champ WYSIWYG uniquement
```

Filtres `$check` courants : `int` (entier), `alpha` (chaîne sans balise
dangereuse), `alphanohtml` (chaîne, tout HTML retiré — cas le plus fréquent),
`aZ09` (lettres et chiffres), `nohtml`, `restricthtml` (HTML limité, réservé aux
champs éditeur riche), `array`. Le 3e argument `$method` vaut `2` pour forcer une
lecture POST uniquement.

## Toute action qui écrit est protégée par jeton CSRF

Sans jeton, un site tiers peut déclencher l'action à l'insu de l'utilisateur
connecté. Le formulaire inclut le jeton, la page le valide.

Incorrect :
```php
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="action" value="add">';
print '</form>';
```

Correct :
```php
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';
print '</form>';
```

Pour un lien d'action en GET (suppression…), ajouter `&token='.newToken()` dans
l'URL et préférer convertir en POST. Dolibarr peut rejeter automatiquement les POST
sans jeton si `MAIN_SECURITY_CSRF_WITH_TOKEN` est actif, mais ne jamais se reposer
uniquement sur la configuration.

## Toute valeur en SQL est échappée

Trois outils selon le contexte : `$db->escape()` pour une chaîne entre apostrophes,
cast `(int)` pour un entier, `$db->escapeforlike()` pour le contenu d'un `LIKE`.

Incorrect (injection SQL) :
```php
$sql = "SELECT * FROM ".MAIN_DB_PREFIX."monmodule_monobjet
        WHERE label = '".$label."' AND fk_soc = ".$socid;
```

Correct :
```php
$sql  = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
$sql .= " WHERE entity IN (".getEntity('monobjet').")";
$sql .= " AND fk_soc = ".((int) $socid);
$sql .= " AND label = '".$db->escape($label)."'";
$sql .= " AND ref LIKE '%".$db->escape($db->escapeforlike($search))."%'";
```

Un entier reçu en entrée se caste `(int)`, il ne s'échappe pas comme une chaîne.

## Toute page contrôle les permissions avant de traiter

Vérifier les droits dès le début de la page, et de nouveau avant chaque écriture.
Masquer un bouton dans l'UI ne suffit pas : l'action serveur doit être protégée.

Incorrect (aucun contrôle) :
```php
$object->label = GETPOST('label', 'alphanohtml');
$object->create($user);
```

Correct :
```php
if (!isModEnabled('monmodule')) accessforbidden('Module non activé');
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

if ($action == 'add') {
    if (!$user->hasRight('monmodule', 'monobjet', 'write')) accessforbidden();
    $object->label = GETPOST('label', 'alphanohtml');
    $object->create($user);
}
```

Pour un contrôle lié à un enregistrement précis (restrictions par société/entité) :
```php
restrictedArea($user, 'monmodule', $object->id, 'monmodule_monobjet');
```

## Échapper le contenu dynamique en sortie

Tout contenu affiché doit être échappé selon le contexte, sinon faille XSS.

Incorrect :
```php
print '<td>'.$object->label.'</td>';
```

Correct :
```php
print '<td>'.dol_escape_htmltag($object->label).'</td>';
print '<script>var ref = "'.dol_escape_js($object->ref).'";</script>';
```

## Nettoyer les noms de fichiers et chemins

Toute manipulation de fichier à partir d'une entrée utilisateur risque la traversée
de répertoire. Nettoyer avec `dol_sanitizeFileName()` et `dol_sanitizePathName()`,
et ne jamais concaténer une entrée brute dans un chemin.

Incorrect :
```php
$path = $conf->monmodule->dir_output.'/'.GETPOST('file');
```

Correct :
```php
$filename = dol_sanitizeFileName(GETPOST('file', 'alphanohtml'));
$path = $conf->monmodule->dir_output.'/'.$filename;
```

## Ne jamais faire confiance à un champ `hidden`

Une valeur dans un `<input type="hidden">` est entièrement modifiable côté client.
La filtrer et la contrôler exactement comme toute autre entrée — y compris les
contrôles de droits et de cohérence.

## Pages AJAX — constantes de sécurité

Les pages AJAX (retournant JSON) doivent déclarer des constantes **avant**
l'inclusion de `main.inc.php` pour optimiser le chargement et gérer le CSRF :

```php
<?php
// Déclarations AVANT l'include de main.inc.php
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");
```

| Constante | Rôle |
|---|---|
| `NOCSRFCHECK` | Désactive vérification CSRF (nécessaire pour appels AJAX non-formulaire) |
| `NOTOKENRENEWAL` | Empêche le renouvellement du token de session |
| `NOREQUIREMENU` | N'inclut pas le menu (performance) |
| `NOREQUIREHTML` | N'inclut pas les helpers HTML |
| `NOREQUIREAJAX` | N'inclut pas la bibliothèque AJAX |

**Important** : même avec `NOCSRFCHECK`, toujours vérifier les permissions
(`hasRight()`) dans le handler AJAX. Ne jamais supposer que l'appel vient d'une
page autorisée.

Retourner du JSON proprement :
```php
top_httphead('application/json');
echo json_encode(array('success' => true, 'data' => $result));
exit;
```

## Filtres `GETPOST` — référence complète

| Filtre | Comportement | Usage typique |
|---|---|---|
| `int` | Cast en entier | IDs, quantités, statuts |
| `intcomma` | Entiers séparés par virgules | Listes d'IDs |
| `alpha` | Alphanumérique, HTML basique retiré | Actions, modes, clés |
| `aZ` | Lettres uniquement | Noms de champs |
| `aZ09` | Lettres et chiffres | Actions, identifiants simples |
| `aZ09comma` | aZ09 + virgules | Sort fields (multi-colonnes) |
| `alphanohtml` | Alphanumérique, **tout HTML retiré** | **Cas le plus fréquent** pour textes |
| `nohtml` | Tout HTML retiré | Textes purs |
| `restricthtml` | HTML limité (liste blanche de balises) | **Éditeurs WYSIWYG uniquement** |
| `text` | Texte brut (peu de filtrage) | Utiliser avec prudence |
| `array` | Retourne un tableau | Checkboxes multiples |
| `san_alpha` | Sanitize alpha | Noms de fichiers |
| `none` | Aucun filtrage | **DÉCONSEILLÉ** sauf cas exceptionnel |

Règle simple : utiliser le filtre **le plus restrictif** compatible avec l'usage.
En cas de doute, `alphanohtml` est le choix sûr par défaut pour du texte.

## Handler AJAX complet — gabarit de référence

Voici la structure complète d'un fichier `ajax/monmodule.ajax.php` :

```php
<?php
// Déclarer AVANT l'include de main.inc.php
if (!defined('NOCSRFCHECK'))    define('NOCSRFCHECK',    '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU',  '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML',  '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX',  '1');

$res = 0;
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');

dol_include_once('/monmodule/class/monobjet.class.php');

global $db, $user;

// Lire l'action (POST ou JSON)
$action = '';
$input  = null;
$ct     = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($ct, 'application/json') !== false) {
    $input  = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = isset($input['action']) ? (string) $input['action'] : '';
} else {
    $action = GETPOST('action', 'aZ09');
}

// Toujours vérifier les droits, même en AJAX
if (!isModEnabled('monmodule')) {
    top_httphead('application/json');
    echo json_encode(['success' => false, 'error' => 'Module disabled']);
    exit;
}
if (!$user->hasRight('monmodule', 'monobjet', 'read')) {
    top_httphead('application/json');
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

top_httphead('application/json');

switch ($action) {
    case 'getlist':
        $object = new MonObjet($db);
        $list   = $object->fetchAll('', '', 0, 0, ['entity' => $db->entity]);
        echo json_encode(['success' => true, 'data' => $list]);
        break;

    case 'update':
        if (!$user->hasRight('monmodule', 'monobjet', 'write')) {
            echo json_encode(['success' => false, 'error' => 'Forbidden']);
            break;
        }
        $id    = (int) ($input['id'] ?? GETPOST('id', 'int'));
        $label = $db->escape(($input['label'] ?? GETPOST('label', 'alphanohtml')));

        $object = new MonObjet($db);
        if ($object->fetch($id) > 0) {
            $object->label = $label;
            $result = $object->update($user);
            echo json_encode($result > 0
                ? ['success' => true]
                : ['success' => false, 'error' => $object->error]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Not found']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}
exit;
```

Points clés :

- Constantes déclarées **avant** `include main.inc.php`
- Droits vérifiés **après** l'include (on a besoin de `$user`)
- Lire le corps JSON `php://input` si `Content-Type: application/json`
- `top_httphead('application/json')` avant tout `echo`
- `exit` final systématique

## Upload de fichier — sécurité

Valider extension, MIME et taille avant d'écrire sur le disque.

Incorrect (aucun contrôle) :
```php
move_uploaded_file($_FILES['file']['tmp_name'], $conf->monmodule->dir_output.'/'.$_FILES['file']['name']);
```

Correct :
```php
$uploadedFile = $_FILES['file'] ?? null;

if (!$uploadedFile || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
    setEventMessages($langs->trans('ErrorUpload'), null, 'errors');
    break;
}

// Extensions autorisées (liste blanche)
$allowedExt  = ['pdf', 'docx', 'odt', 'png', 'jpg', 'jpeg'];
$ext         = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    setEventMessages($langs->trans('ErrorBadFileType'), null, 'errors');
    break;
}

// MIME réel (pas celui déclaré par le client)
$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($uploadedFile['tmp_name']);
$allowed = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.oasis.opendocument.text', 'image/png', 'image/jpeg'];
if (!in_array($mime, $allowed, true)) {
    setEventMessages($langs->trans('ErrorBadFileType'), null, 'errors');
    break;
}

// Taille max (ex : 10 Mo)
if ($uploadedFile['size'] > 10 * 1024 * 1024) {
    setEventMessages($langs->trans('ErrorFileTooLarge'), null, 'errors');
    break;
}

// Nom de fichier sécurisé
$filename  = dol_sanitizeFileName($uploadedFile['name']);
$destDir   = $conf->monmodule->dir_output.'/'.$object->ref.'/';
if (!is_dir($destDir)) dol_mkdir($destDir);
$destPath  = $destDir.$filename;

if (!move_uploaded_file($uploadedFile['tmp_name'], $destPath)) {
    setEventMessages($langs->trans('ErrorMoveFile'), null, 'errors');
    break;
}
dol_syslog("MonModule::upload fichier ".$destPath, LOG_INFO);
```

Règles clés :

- **Liste blanche** d'extensions, pas liste noire
- **MIME réel** via `finfo`, pas `$_FILES['type']` (contrôlable par le client)
- **Taille max** validée côté serveur
- **Nom de fichier** passé par `dol_sanitizeFileName()`
- **Répertoire** créé avec `dol_mkdir()` (respecte les permissions)

## À ne jamais faire

- `dol_eval()` sur une donnée non maîtrisée — exécution de code.
- `error_reporting(0)` ou `@` pour masquer des erreurs.
- Stocker un secret (clé API, mot de passe) en clair dans le code.
- Désactiver un contrôle de jeton ou de droits « pour debug » et oublier de le
  remettre.
- Utiliser `$_SERVER['HTTP_REFERER']` ou `$_SERVER['REQUEST_URI']` sans échapper —
  ces valeurs sont contrôlables par le client.
- Charger des bibliothèques JS/CSS depuis un CDN externe dans un module commercial —
  le module doit être 100% self-contained.
