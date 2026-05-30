# Génération de documents (PDF / ODT)

Dolibarr dispose d'un système de modèles de documents pour générer des PDF, ODT
ou tout autre format à partir des données d'un objet métier. Un module peut
déclarer ses propres modèles et les rendre disponibles dans l'interface.

## Architecture

```
monmodule/
├── core/modules/monmodule/
│   ├── modules_monobjet.php          # Classe parente abstraite
│   └── doc/
│       ├── pdf_standard_monobjet.modules.php   # Modèle PDF (TCPDF)
│       └── doc_generic_monobjet_odt.modules.php # Modèle ODT
```

Le descripteur déclare les modèles via `module_parts` :

```php
$this->module_parts = array(
    'models' => 1,    // Active la détection des modèles de documents
);
```

## Classe parente abstraite

Créer `core/modules/monmodule/modules_monobjet.php` :

```php
<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

abstract class ModelePDFMonObjet extends CommonDocGenerator
{
    public $error = '';

    /**
     * Retourne la liste des modèles disponibles
     */
    public static function liste_modeles($db, $maxfilenamelength = 0)
    {
        $type = 'monobjet';
        $list = array();

        include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
        $list = getListOfModels($db, $type, $maxfilenamelength);

        return $list;
    }
}
```

## Modèle PDF avec TCPDF

Créer `core/modules/monmodule/doc/pdf_standard_monobjet.modules.php` :

```php
<?php
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
dol_include_once('/monmodule/core/modules/monmodule/modules_monobjet.php');

class pdf_standard_monobjet extends ModelePDFMonObjet
{
    public $db;
    public $name;
    public $description;
    public $type;

    public $page_largeur;
    public $page_hauteur;
    public $format;
    public $marge_gauche;
    public $marge_droite;
    public $marge_haute;
    public $marge_basse;

    /**
     * Constructor
     */
    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        $this->db          = $db;
        $this->name        = 'standard_monobjet';
        $this->description = $langs->trans('DocumentModelStandard');
        $this->type        = 'pdf';

        // Dimensions A4
        $this->page_largeur = 210;
        $this->page_hauteur = 297;
        $this->format       = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche = 10;
        $this->marge_droite = 10;
        $this->marge_haute  = 10;
        $this->marge_basse  = 10;
    }

    /**
     * Générer le document
     *
     * @param  MonObjet  $object     Objet source
     * @param  Translate $outputlangs Langue de sortie
     * @param  string    $srctemplatepath Chemin template ODT (vide pour PDF)
     * @param  int       $hidedetails    1 pour masquer les détails
     * @param  int       $hidedesc       1 pour masquer les descriptions
     * @param  int       $hideref        1 pour masquer la référence
     * @return int       1 si OK, <= 0 si erreur
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $langs, $user, $mysoc;

        if (!is_object($outputlangs)) $outputlangs = $langs;
        $outputlangs->loadLangs(['main', 'monmodule@monmodule']);

        // Répertoire de destination
        $dir = $conf->monmodule->dir_output.'/'.$object->ref.'/';
        if (!is_dir($dir)) dol_mkdir($dir);

        $file = $dir.$object->ref.'.pdf';

        // Charger TCPDF
        $pdf = pdf_getInstance($this->format);
        $pdf->SetAutoPageBreak(1, 0);
        $pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
        $pdf->SetCreator('Dolibarr '.DOL_VERSION);
        $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));

        $pdf->Open();
        $pdf->AddPage();

        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $pdf->SetFont('', '', $default_font_size);

        // ---- En-tête : logo + infos société ----
        $posy = $this->marge_haute;
        $posx = $this->marge_gauche;

        // Logo société
        $logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
        if (!empty($mysoc->logo) && is_readable($logo)) {
            $height = pdf_getHeightForLogo($logo);
            $pdf->Image($logo, $posx, $posy, 0, $height);
            $posy += $height + 5;
        }

        // Nom société
        $pdf->SetFont('', 'B', $default_font_size + 3);
        $pdf->SetXY($posx, $posy);
        $pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($mysoc->name), 0, 'L');
        $posy += 10;

        // ---- Titre du document ----
        $pdf->SetFont('', 'B', $default_font_size + 4);
        $pdf->SetXY($posx, $posy);
        $title = $outputlangs->transnoentities('MonObjetSingular').' '.$object->ref;
        $pdf->MultiCell(190, 6, $outputlangs->convToOutputCharset($title), 0, 'C');
        $posy += 15;

        // ---- Contenu ----
        $pdf->SetFont('', '', $default_font_size);

        // Tableau d'informations
        $fields = [
            'Ref'          => $object->ref,
            'Label'        => $object->label,
            'DateCreation' => dol_print_date($object->date_creation, 'day'),
            'Status'       => $object->getLibStatut(0),
        ];

        foreach ($fields as $label => $value) {
            $pdf->SetXY($posx, $posy);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->Cell(50, 6, $outputlangs->transnoentities($label).' :', 0, 0, 'L');
            $pdf->SetFont('', '', $default_font_size);
            $pdf->Cell(140, 6, $outputlangs->convToOutputCharset($value), 0, 1, 'L');
            $posy += 7;
        }

        // ---- Pied de page ----
        $this->_pagefoot($pdf, $object, $outputlangs);

        // Écrire le fichier
        $pdf->Close();
        $pdf->Output($file, 'F');

        // Permissions
        dolChmod($file);

        // Stocker les métadonnées dans l'objet
        $object->last_main_doc = $object->ref.'/'.$object->ref.'.pdf';

        return 1;
    }

    /**
     * Pied de page
     */
    protected function _pagefoot(&$pdf, $object, $outputlangs)
    {
        global $conf;

        $pdf->SetAutoPageBreak(0, 0);
        $pdf->SetFont('', '', 7);
        $pdf->SetXY($this->marge_gauche, $this->page_hauteur - 15);
        $pdf->MultiCell(190, 3, $conf->global->MAIN_INFO_SOCIETE_NOM.' — Document généré le '.dol_print_date(dol_now(), 'dayhour'), 0, 'C');
    }
}
```

## Intégration dans la fiche objet

Ajouter le bloc de génération de document sur `monobjetcard.php` :

```php
// Après l'affichage de la fiche, avant llxFooter()
if ($object->id > 0 && $action != 'edit' && $action != 'create') {
    // Section documents liés
    $objref    = dol_sanitizeFileName($object->ref);
    $dir_files = $conf->monmodule->dir_output.'/'.$objref.'/';
    $urlsource = $_SERVER['PHP_SELF'].'?id='.$object->id;

    print '<div class="fichecenter"><div class="fichehalfleft">';

    // Liste des documents générés
    $filedir = $conf->monmodule->dir_output.'/'.$objref;
    print $formfile->showdocuments(
        'monmodule:MonObjet',    // modulepart:objecttype
        $objref,                 // sous-répertoire
        $filedir,                // répertoire physique
        $urlsource,              // URL pour regénérer
        $user->hasRight('monmodule', 'monobjet', 'write'),  // droit de générer
        $user->hasRight('monmodule', 'monobjet', 'delete'),  // droit de supprimer
        $object->model_pdf,      // modèle par défaut
        1,                       // afficher la section
        0, 0, 0, 0, '', '', '', '', ''
    );

    print '</div></div>';
}
```

Variables nécessaires en haut de page :

```php
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
$formfile = new FormFile($db);
```

## Traitement de l'action de génération

```php
// Dans la section actions de monobjetcard.php
if ($action == 'builddoc' && $user->hasRight('monmodule', 'monobjet', 'write')) {
    $object->fetch($id);

    $outputlangs = $langs;
    $newlang = GETPOST('lang_id', 'aZ09');
    if (!empty($newlang)) {
        $outputlangs = new Translate('', $conf);
        $outputlangs->setDefaultLang($newlang);
    }

    $model = GETPOST('model', 'alphanohtml');
    if (!empty($model)) {
        $object->model_pdf = $model;
        $object->update($user);
    }

    // Appeler le générateur
    $result = $object->generateDocument($model, $outputlangs);
    if ($result <= 0) {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

if ($action == 'remove_file' && $user->hasRight('monmodule', 'monobjet', 'write')) {
    require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
    $file = GETPOST('file', 'alphanohtml');
    $file = $conf->monmodule->dir_output.'/'.dol_sanitizeFileName($object->ref).'/'.dol_sanitizeFileName($file);
    dol_delete_file($file);
}
```

## Méthode `generateDocument` dans la classe objet

Ajouter dans `class/monobjet.class.php` :

```php
/**
 * Générer un document PDF/ODT
 *
 * @param  string    $modele      Nom du modèle
 * @param  Translate $outputlangs Langue
 * @param  int       $hidedetails Masquer détails
 * @param  int       $hidedesc    Masquer descriptions
 * @param  int       $hideref     Masquer référence
 * @return int       1 si OK, <= 0 si erreur
 */
public function generateDocument($modele, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
{
    global $conf;

    $outputlangs->loadLangs(['monmodule@monmodule']);

    if (empty($modele)) {
        $modele = getDolGlobalString('MONMODULE_ADDON_PDF', 'standard_monobjet');
    }

    $modelpath = 'core/modules/monmodule/doc/';
    return $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref);
}
```

## Modèle ODT (alternative au PDF)

Pour les modèles ODT, Dolibarr utilise un fichier `.odt` comme template avec des
balises de substitution. Le générateur remplace les balises par les données de l'objet.

Créer `core/modules/monmodule/doc/doc_generic_monobjet_odt.modules.php` :

```php
<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/commondocs/CommonDocGenerator.class.php';
dol_include_once('/monmodule/core/modules/monmodule/modules_monobjet.php');

class doc_generic_monobjet_odt extends ModelePDFMonObjet
{
    public $emetteur;

    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        $this->db          = $db;
        $this->name        = 'generic_monobjet_odt';
        $this->description = $langs->trans('ODTDefaultTemplate');
        $this->type        = 'odt';
        $this->scandir     = 'MONMODULE_MONOBJET_ADDON_PDF_ODT_PATH';
        $this->emetteur    = $mysoc;
    }

    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $user;

        if (empty($srctemplatepath)) {
            $this->error = 'ErrorNoTemplateDefined';
            return -1;
        }

        // Le moteur ODT de Dolibarr gère la substitution automatiquement
        // via commonGenerateDocument() dans la classe objet

        return 1;
    }
}
```

## Variables de substitution ODT

Dans un template ODT, les balises suivantes sont remplacées automatiquement :

| Balise | Valeur |
| --- | --- |
| `{object_ref}` | Référence de l'objet |
| `{object_label}` | Libellé |
| `{object_date_creation}` | Date de création |
| `{object_note_public}` | Note publique |
| `{object_note_private}` | Note privée |
| `{mycompany_name}` | Nom de la société émettrice |
| `{mycompany_logo}` | Logo de la société |
| `{mycompany_address}` | Adresse complète |
| `{date}` | Date du jour |

Pour ajouter des variables personnalisées, surcharger `get_substitutionarray_object()` :

```php
// Dans la classe du modèle ODT
public function get_substitutionarray_object($object, $outputlangs)
{
    $array = parent::get_substitutionarray_object($object, $outputlangs);

    // Variables personnalisées
    $array['monobjet_custom_field'] = $object->custom_field;
    $array['monobjet_status_label'] = $object->getLibStatut(0);

    return $array;
}
```

## Constantes de configuration

```php
// Dans le descripteur — modèle par défaut
dolibarr_set_const($db, 'MONMODULE_ADDON_PDF', 'standard_monobjet', 'chaine', 0, '', $conf->entity);
// Pour ODT — répertoire des templates
dolibarr_set_const($db, 'MONMODULE_MONOBJET_ADDON_PDF_ODT_PATH', 'DOL_DATA_ROOT/doctemplates/monmodule/', 'chaine', 0, '', $conf->entity);
```

## Checklist document

- [ ] Classe parente `modules_monobjet.php` avec `liste_modeles()`
- [ ] Modèle PDF dans `doc/pdf_standard_monobjet.modules.php`
- [ ] `module_parts['models'] = 1` dans le descripteur
- [ ] `generateDocument()` dans la classe objet
- [ ] Actions `builddoc` et `remove_file` dans la page fiche
- [ ] `showdocuments()` pour l'affichage de la section documents
- [ ] Répertoire de sortie `$conf->monmodule->dir_output` initialisé
- [ ] Permissions vérifiées avant génération et suppression