# Dashboard et widgets (boxes)

Dolibarr permet aux modules d'afficher des widgets (« boxes ») sur le tableau
de bord principal. Un widget affiche un compteur, une liste courte, un graphique
ou tout contenu HTML pertinent.

## Architecture

```
monmodule/
├── core/
│   └── boxes/
│       └── box_monmodule.php     # Widget du module
```

Le descripteur déclare le widget :

```php
// Dans modMonModule.class.php — __construct()
$this->boxes = array(
    0 => array(
        'file'  => 'box_monmodule@monmodule',
        'note'  => 'Widget MonModule — derniers objets',
        'enabledbydefaulton' => 'Home',  // Affiché par défaut sur le tableau de bord
    ),
);
```

## Créer un widget — classe complète

```php
<?php
/* Copyright (C) 2024 DTS SARL */

include_once DOL_DOCUMENT_ROOT.'/core/boxes/modules_boxes.php';

class box_monmodule extends ModeleBoxes
{
    public $boxcode  = 'monmodulelatest';
    public $boximg   = 'monobjet@monmodule';
    public $boxlabel = 'MonModuleLatestObjects';
    public $depends  = array('monmodule');

    public $info_box_head = array();
    public $info_box_contents = array();

    public $enabled = 1;

    public function __construct($db, $param = '')
    {
        global $user;
        $this->db = $db;

        // Désactiver si l'utilisateur n'a pas les droits
        $this->hidden = !$user->hasRight('monmodule', 'monobjet', 'read');
    }

    /**
     * Charger les données du widget
     *
     * @param int $max Nombre max de lignes à afficher
     */
    public function loadBox($max = 5)
    {
        global $conf, $user, $langs;

        $langs->load('monmodule@monmodule');

        $this->max = $max;

        // ---- En-tête du widget ----
        $this->info_box_head = array(
            'text'  => $langs->trans('MonModuleLatestObjects', $max),
            'sublink' => dol_buildpath('/monmodule/monobjetlist.php', 1),
            'subtext' => $langs->trans('ShowAll'),
            'subpicto' => 'object_monobjet@monmodule',
        );

        // ---- Contenu : requête SQL ----
        if ($user->hasRight('monmodule', 'monobjet', 'read')) {
            $sql  = "SELECT t.rowid, t.ref, t.label, t.status, t.date_creation";
            $sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet AS t";
            $sql .= " WHERE t.entity IN (".getEntity('monobjet').")";
            $sql .= " ORDER BY t.date_creation DESC";
            $sql .= $this->db->plimit($max, 0);

            $result = $this->db->query($sql);
            if ($result) {
                $num = $this->db->num_rows($result);
                $line = 0;

                dol_include_once('/monmodule/class/monobjet.class.php');
                $objectstatic = new MonObjet($this->db);

                while ($line < $num) {
                    $obj = $this->db->fetch_object($result);

                    $objectstatic->id     = $obj->rowid;
                    $objectstatic->ref    = $obj->ref;
                    $objectstatic->label  = $obj->label;
                    $objectstatic->status = (int) $obj->status;

                    // Colonne 1 : lien vers la fiche
                    $this->info_box_contents[$line][] = array(
                        'td'   => 'class="nowraponall"',
                        'text' => '<a href="'.dol_buildpath('/monmodule/monobjetcard.php', 1).'?id='.$obj->rowid.'">'
                                  .dol_escape_htmltag($obj->ref).'</a>',
                        'asis' => 1,
                    );

                    // Colonne 2 : libellé
                    $this->info_box_contents[$line][] = array(
                        'td'   => '',
                        'text' => dol_trunc($obj->label, 40),
                    );

                    // Colonne 3 : date
                    $this->info_box_contents[$line][] = array(
                        'td'   => 'class="right nowraponall"',
                        'text' => dol_print_date($this->db->jdate($obj->date_creation), 'day'),
                    );

                    // Colonne 4 : statut
                    $this->info_box_contents[$line][] = array(
                        'td'   => 'class="right"',
                        'text' => $objectstatic->getLibStatut(3),
                        'asis' => 1,
                    );

                    $line++;
                }

                if ($num == 0) {
                    $this->info_box_contents[0][] = array(
                        'td'   => 'class="center opacitymedium"',
                        'text' => $langs->trans('NoRecordFound'),
                    );
                }
            } else {
                $this->info_box_contents[0][] = array(
                    'td'   => '',
                    'text' => $this->db->lasterror(),
                );
            }
        }
    }

    /**
     * Afficher le widget (méthode standard)
     */
    public function showBox($head = null, $contents = null, $nooutput = 0)
    {
        return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
    }
}
```

## Widget avec compteurs (KPIs)

Pour un widget affichant des indicateurs chiffrés :

```php
public function loadBox($max = 5)
{
    global $conf, $user, $langs, $db;

    $langs->load('monmodule@monmodule');

    $this->info_box_head = array(
        'text' => $langs->trans('MonModuleStats'),
    );

    if (!$user->hasRight('monmodule', 'monobjet', 'read')) return;

    // Compter par statut
    $stats = [];
    $sql  = "SELECT status, COUNT(rowid) AS nb";
    $sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
    $sql .= " WHERE entity IN (".getEntity('monobjet').")";
    $sql .= " GROUP BY status";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $stats[(int) $obj->status] = (int) $obj->nb;
        }
    }

    $line = 0;

    // Ligne : Brouillons
    $this->info_box_contents[$line][] = array(
        'td'   => '',
        'text' => $langs->trans('StatusDraft'),
    );
    $this->info_box_contents[$line][] = array(
        'td'   => 'class="right"',
        'text' => '<span class="badge badge-status0">'.($stats[0] ?? 0).'</span>',
        'asis' => 1,
    );
    $line++;

    // Ligne : Validés
    $this->info_box_contents[$line][] = array(
        'td'   => '',
        'text' => $langs->trans('StatusValidated'),
    );
    $this->info_box_contents[$line][] = array(
        'td'   => 'class="right"',
        'text' => '<span class="badge badge-status4">'.($stats[1] ?? 0).'</span>',
        'asis' => 1,
    );
    $line++;

    // Ligne : Clôturés
    $this->info_box_contents[$line][] = array(
        'td'   => '',
        'text' => $langs->trans('StatusClosed'),
    );
    $this->info_box_contents[$line][] = array(
        'td'   => 'class="right"',
        'text' => '<span class="badge badge-status6">'.($stats[9] ?? 0).'</span>',
        'asis' => 1,
    );
}
```

## Page `index.php` du module (dashboard personnalisé)

Pour une page d'accueil du module avec des statistiques :

```php
<?php
$res = 0;
if (!$res && file_exists('../main.inc.php')) $res = @include '../main.inc.php';
if (!$res && file_exists('../../main.inc.php')) $res = @include '../../main.inc.php';
if (!$res) die('Include of main fails');

global $db, $conf, $user, $langs;
$langs->load('monmodule@monmodule');

if (!isModEnabled('monmodule')) accessforbidden();
if (!$user->hasRight('monmodule', 'monobjet', 'read')) accessforbidden();

llxHeader('', $langs->trans('MonModuleDashboard'));

print load_fiche_titre($langs->trans('MonModuleDashboard'), '', 'monobjet@monmodule');

// ---- KPIs en grille ----
print '<div class="fichecenter">';
print '<div class="fichethirdleft">';

// Compteur : total objets
$sql = "SELECT COUNT(rowid) AS nb FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
$sql .= " WHERE entity IN (".getEntity('monobjet').")";
$res = $db->query($sql);
$total = $res ? (int) $db->fetch_object($res)->nb : 0;

print '<div class="info-box">';
print '<span class="info-box-icon bg-infobox-action"><i class="fas fa-list"></i></span>';
print '<div class="info-box-content">';
print '<span class="info-box-text">'.$langs->trans('TotalObjects').'</span>';
print '<span class="info-box-number">'.$total.'</span>';
print '</div></div>';

print '</div>';
print '<div class="fichethirdleft">';

// Compteur : brouillons
$sql = "SELECT COUNT(rowid) AS nb FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
$sql .= " WHERE entity IN (".getEntity('monobjet').") AND status = 0";
$res = $db->query($sql);
$drafts = $res ? (int) $db->fetch_object($res)->nb : 0;

print '<div class="info-box">';
print '<span class="info-box-icon bg-infobox-warning"><i class="fas fa-pencil-alt"></i></span>';
print '<div class="info-box-content">';
print '<span class="info-box-text">'.$langs->trans('StatusDraft').'</span>';
print '<span class="info-box-number">'.$drafts.'</span>';
print '</div></div>';

print '</div>';
print '</div>';

llxFooter();
$db->close();
```

## Bonnes pratiques

- **Performances** : limiter les requêtes dans `loadBox()` — un widget ne doit pas ralentir le dashboard
- **Permissions** : vérifier `hasRight()` avant d'afficher des données
- **Pagination** : ne jamais afficher plus de 10 lignes dans un widget
- **`$this->hidden`** : mettre à `true` si l'utilisateur n'a pas les droits
- **Multi-entité** : filtrer par `entity` dans toutes les requêtes
- **Labels** : utiliser `$langs->trans()` pour tous les textes
- **Liens** : chaque élément doit être cliquable vers la fiche correspondante
