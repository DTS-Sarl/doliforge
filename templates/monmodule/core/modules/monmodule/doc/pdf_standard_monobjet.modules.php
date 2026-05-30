<?php
/* Copyright (C) 2024 DTS SARL
 * Modèle PDF standard pour MonObjet
 *
 * Génère un document PDF à partir des données d'un objet MonObjet.
 * Utilise TCPDF intégré à Dolibarr.
 */

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
	 * Générer le document PDF
	 *
	 * @param  MonObjet  $object      Objet source
	 * @param  Translate $outputlangs Langue de sortie
	 * @param  string    $srctemplatepath Chemin template (vide pour PDF)
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
		$dir = $conf->monmodule->dir_output.'/'.dol_sanitizeFileName($object->ref).'/';
		if (!is_dir($dir)) dol_mkdir($dir);

		$file = $dir.dol_sanitizeFileName($object->ref).'.pdf';

		// ---- Initialiser TCPDF ----
		$pdf = pdf_getInstance($this->format);
		$pdf->SetAutoPageBreak(1, 0);
		$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));

		$pdf->Open();
		$pdf->AddPage();

		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$pdf->SetFont('', '', $default_font_size);

		$posy = $this->marge_haute;
		$posx = $this->marge_gauche;

		// ---- Logo société ----
		if (!empty($mysoc->logo)) {
			$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
			if (is_readable($logo)) {
				$height = pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $posx, $posy, 0, $height);
				$posy += $height + 5;
			}
		}

		// ---- Nom société ----
		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->SetXY($posx, $posy);
		$pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($mysoc->name), 0, 'L');
		$posy += 10;

		// ---- Titre du document ----
		$pdf->SetFont('', 'B', $default_font_size + 4);
		$pdf->SetXY($posx, $posy);
		$title = $outputlangs->transnoentities('MonObjet').' '.$object->ref;
		$pdf->MultiCell(190, 6, $outputlangs->convToOutputCharset($title), 0, 'C');
		$posy += 15;

		// ---- Informations de l'objet ----
		$pdf->SetFont('', '', $default_font_size);

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

		// ---- Lignes de détail (MonDetail) ----
		dol_include_once('/monmodule/class/mondetail.class.php');
		$lines = MonDetail::fetchAllByParent($this->db, $object->id);

		if (!empty($lines)) {
			$posy += 10;
			$pdf->SetXY($posx, $posy);
			$pdf->SetFont('', 'B', $default_font_size + 1);
			$pdf->Cell(190, 6, $outputlangs->transnoentities('DetailLines'), 0, 1, 'L');
			$posy += 8;

			// En-tête du tableau
			$pdf->SetFont('', 'B', $default_font_size - 1);
			$pdf->SetXY($posx, $posy);
			$pdf->SetFillColor(230, 230, 230);
			$pdf->Cell(80, 5, $outputlangs->transnoentities('Label'), 1, 0, 'L', 1);
			$pdf->Cell(30, 5, $outputlangs->transnoentities('Qty'), 1, 0, 'C', 1);
			$pdf->Cell(40, 5, $outputlangs->transnoentities('UnitPrice'), 1, 0, 'R', 1);
			$pdf->Cell(40, 5, $outputlangs->transnoentities('Total'), 1, 1, 'R', 1);
			$posy += 5;

			// Lignes
			$pdf->SetFont('', '', $default_font_size - 1);
			$grandTotal = 0;
			foreach ($lines as $line) {
				$pdf->SetXY($posx, $posy);
				$pdf->Cell(80, 5, $outputlangs->convToOutputCharset(dol_trunc($line->label, 40)), 1, 0, 'L');
				$pdf->Cell(30, 5, $line->qty, 1, 0, 'C');
				$pdf->Cell(40, 5, price($line->price), 1, 0, 'R');
				$pdf->Cell(40, 5, price($line->total), 1, 1, 'R');
				$posy += 5;
				$grandTotal += $line->total;

				// Saut de page si nécessaire
				if ($posy > $this->page_hauteur - 30) {
					$this->_pagefoot($pdf, $object, $outputlangs);
					$pdf->AddPage();
					$posy = $this->marge_haute;
				}
			}

			// Total général
			$pdf->SetFont('', 'B', $default_font_size);
			$pdf->SetXY($posx + 110, $posy);
			$pdf->Cell(40, 6, $outputlangs->transnoentities('Total').' :', 0, 0, 'R');
			$pdf->Cell(40, 6, price($grandTotal), 0, 1, 'R');
		}

		// ---- Pied de page ----
		$this->_pagefoot($pdf, $object, $outputlangs);

		// ---- Écrire le fichier ----
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);

		$object->last_main_doc = dol_sanitizeFileName($object->ref).'/'.dol_sanitizeFileName($object->ref).'.pdf';

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
		$companyName = getDolGlobalString('MAIN_INFO_SOCIETE_NOM', '');
		$pdf->MultiCell(190, 3, $companyName.' — Document généré le '.dol_print_date(dol_now(), 'dayhour'), 0, 'C');
	}
}
