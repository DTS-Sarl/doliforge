<?php
/* Copyright (C) 2024 DTS SARL
 * Widget (box) du module monmodule — box_monmodule.php
 *
 * Affiche les derniers objets MonObjet sur le tableau de bord Dolibarr.
 * Déclaré dans le descripteur : $this->boxes[0]['file'] = 'box_monmodule@monmodule';
 */

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
		$this->hidden = !$user->hasRight('monmodule', 'monobjet', 'read');
	}

	/**
	 * Charger les données du widget
	 *
	 * @param int $max Nombre max de lignes
	 */
	public function loadBox($max = 5)
	{
		global $conf, $user, $langs;

		$langs->load('monmodule@monmodule');

		$this->max = $max;

		// ---- En-tête ----
		$this->info_box_head = array(
			'text'     => $langs->trans('MonModuleLatestObjects', $max),
			'sublink'  => dol_buildpath('/monmodule/monobjetlist.php', 1),
			'subtext'  => $langs->trans('ShowAll'),
			'subpicto' => 'object_monobjet@monmodule',
		);

		if (!$user->hasRight('monmodule', 'monobjet', 'read')) return;

		// ---- Requête ----
		$sql  = "SELECT t.rowid, t.ref, t.label, t.status, t.date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet AS t";
		$sql .= " WHERE t.entity IN (".getEntity('monobjet').")";
		$sql .= " ORDER BY t.date_creation DESC";
		$sql .= $this->db->plimit($max, 0);

		$result = $this->db->query($sql);
		if (!$result) {
			$this->info_box_contents[0][] = array(
				'td'   => '',
				'text' => $this->db->lasterror(),
			);
			return;
		}

		$num = $this->db->num_rows($result);

		if ($num == 0) {
			$this->info_box_contents[0][] = array(
				'td'   => 'class="center opacitymedium"',
				'text' => $langs->trans('NoRecordFound'),
			);
			return;
		}

		dol_include_once('/monmodule/class/monobjet.class.php');
		$objectstatic = new MonObjet($this->db);
		$line = 0;

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
	}

	/**
	 * Afficher le widget
	 */
	public function showBox($head = null, $contents = null, $nooutput = 0)
	{
		return parent::showBox($this->info_box_head, $this->info_box_contents, $nooutput);
	}
}
