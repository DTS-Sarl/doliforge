# Structure d'un module

## Placer le module dans `htdocs/custom/`

Un module développé en interne ne va jamais dans `htdocs/` à côté du cœur. Il vit
dans `htdocs/custom/<monmodule>/`. Cela garantit qu'une mise à jour de Dolibarr
n'écrase pas le module et que le cœur reste intact.

```
htdocs/custom/monmodule/
├── core/modules/modMonModule.class.php   # descripteur (obligatoire)
├── core/triggers/                        # triggers
├── core/boxes/                           # widgets tableau de bord
├── class/                                # objets métier, hooks, API
├── sql/                                  # schéma de base de données
├── admin/setup.php                       # page de configuration
├── lib/monmodule.lib.php                 # fonctions utilitaires
├── langs/fr_FR/monmodule.lang            # traductions
├── css/  js/  img/                       # ressources statiques
├── monobjetcard.php  monobjetlist.php    # pages UI
└── README.md  ChangeLog
```

## Garder les noms cohérents

Le nom du dossier, le préfixe des classes, le `rights_class` du descripteur et le
suffixe `@monmodule` des fichiers de langue doivent tous correspondre. Une
incohérence casse le chargement des droits, des traductions ou des pictogrammes.

Incorrect (noms divergents) :
```
htdocs/custom/gestionhotel/
  core/modules/modHotel.class.php      # "Hotel" ≠ "gestionhotel"
  langs/fr_FR/dolihotel.lang           # "dolihotel" ≠ tout le reste
```

Correct (un seul nom partout) :
```
htdocs/custom/dolihotel/
  core/modules/modDoliHotel.class.php
  langs/fr_FR/dolihotel.lang
  $this->rights_class = 'dolihotel';
```

## Le descripteur est le seul fichier obligatoire

Un module minimal valide ne contient que `core/modules/modMonModule.class.php`.
Tout le reste (objets, pages, triggers) est optionnel et déclaré via ce descripteur.
Voir `descripteur.md`.

## Générer le squelette avec le Module Builder

Pour un module neuf, ne pas créer l'arborescence à la main. Activer le **Module
Builder** (Accueil > Configuration > Modules > Module Builder) et générer le
squelette : il produit une structure conforme, le descripteur, et les objets avec
le pattern `$fields`. Intervenir ensuite sur le code généré. Cela évite les erreurs
de structure et reflète les conventions de la version Dolibarr installée.

## Un module ne touche rien en dehors de son dossier

Tout le code, les ressources et le schéma du module restent sous
`htdocs/custom/<monmodule>/`. Aucune écriture dans `htdocs/core/`, `htdocs/compta/`
ou le dossier d'un autre module. Les seules exceptions légitimes (dossiers de
données, fichiers temporaires) sont déclarées dans `$this->dirs` du descripteur et
créées à l'activation.

## Déclarer les répertoires de données

Le descripteur peut créer des dossiers de données persistantes dans `DOL_DATA_ROOT`
via `$this->dirs`. Ces dossiers accueilleront les documents générés, fichiers
uploadés, etc.

```php
$this->dirs = array(
    '/monmodule/temp',
    '/monmodule/output',
);
```

Ces dossiers sont créés à l'activation du module et ne sont **pas** supprimés à la
désactivation (sécurité). Pour les utiliser dans le code :
```php
$dir = $conf->monmodule->dir_output;  // DOL_DATA_ROOT/monmodule/
```

## Organiser les fonctions utilitaires dans `lib/`

Le fichier `lib/monmodule.lib.php` contient typiquement :

- **Fonctions `prepare_head()`** : génèrent les onglets d'administration et de fiches
- **Fonctions helper** : logique réutilisable entre pages

Exemple observé en production :
```php
function monmodule_admin_prepare_head()
{
    global $langs, $conf;

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/monmodule/admin/setup.php', 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath('/monmodule/admin/about.php', 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';

    return $head;
}
```

Ne pas confondre avec `class/` : `lib/` contient des **fonctions procédurales**
réutilisables, `class/` des **classes métier** avec héritage CommonObject.

## Organiser les ressources statiques (`css/`, `js/`, `img/`)

- **JS et CSS locaux obligatoirement** : jamais de CDN pour un module commercial
  (le module doit être 100% self-contained). Placer les bibliothèques tierces dans
  `js/vendor/` ou `js/libs/`.
- **Pictogrammes** : un fichier `img/object_monobjet.png` pour le pictogramme
  déclaré dans le descripteur.
- **Versioning des assets** : ajouter un paramètre de version pour contourner le
  cache navigateur :

```php
// Dans module_parts du descripteur
'css' => array('/monmodule/css/monmodule.css'),
'js'  => array('/monmodule/js/monmodule.js'),
```

## Dossier `ajax/` pour les handlers AJAX

Les pages AJAX se placent dans un sous-dossier `ajax/` :

```text
htdocs/custom/monmodule/
├── ajax/
│   ├── actions.php       # traitement d'actions AJAX
│   └── data.php          # retour de données JSON
```

Ces pages nécessitent des constantes spéciales — voir `securite.md`.
