<?php
/* Copyright (C) 2024 DTS SARL
 * Objet métier : MonObjet
 * Remplacer : monmodule → nom_technique, MonObjet/monobjet → nom_objet
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class MonObjet extends CommonObject
{
	public $element        = 'monobjet';
	public $table_element  = 'monmodule_monobjet';
	public $picto          = 'generic';

	public $ismultientitymanaged = 1;
	public $isextrafieldmanaged  = 1;

	// Statuts
	const STATUS_DRAFT     = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CLOSED    = 9;

	/**
	 * @var array Définition des champs — pattern $fields Dolibarr
	 */
	public $fields = [
		'rowid'         => ['type' => 'integer',      'label' => 'TechnicalID',  'enabled' => 1, 'position' => 1,  'notnull' => 1, 'visible' => 0],
		'ref'           => ['type' => 'varchar(64)',   'label' => 'Ref',          'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'noteditable' => 1, 'index' => 1, 'searchall' => 1, 'default' => ''],
		'entity'        => ['type' => 'integer',       'label' => 'Entity',       'enabled' => 1, 'position' => 15, 'notnull' => 1, 'visible' => 0],
		'label'         => ['type' => 'varchar(255)',  'label' => 'Label',        'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300', 'cssview' => 'wordbreak'],
		'description'   => ['type' => 'text',          'label' => 'Description',  'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 3],
		'fk_soc'        => ['type' => 'integer:Societe:societe/class/societe.class.php:1', 'label' => 'ThirdParty', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'index' => 1],
		'date_creation' => ['type' => 'datetime',      'label' => 'DateCreation', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => -2],
		'tms'           => ['type' => 'timestamp',     'label' => 'DateModification', 'enabled' => 1, 'position' => 81, 'notnull' => 0, 'visible' => -2],
		'fk_user_creat' => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 82, 'notnull' => 0, 'visible' => -2],
		'fk_user_modif' => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'enabled' => 1, 'position' => 83, 'notnull' => 0, 'visible' => -2],
		'status'        => ['type' => 'smallint',      'label' => 'Status',       'enabled' => 1, 'position' => 90, 'notnull' => 1, 'visible' => 4, 'default' => '0', 'arrayofkeyval' => [0 => 'Draft', 1 => 'Validated', 9 => 'Closed'], 'css' => 'minwidth75'],
		'note_public'   => ['type' => 'html',          'label' => 'NotePublic',   'enabled' => 1, 'position' => 95, 'notnull' => 0, 'visible' => 3],
		'note_private'  => ['type' => 'html',          'label' => 'NotePrivate',  'enabled' => 1, 'position' => 96, 'notnull' => 0, 'visible' => 3],
		'import_key'    => ['type' => 'varchar(14)',   'label' => 'ImportId',     'enabled' => 1, 'position' => 99, 'notnull' => 0, 'visible' => -2],
	];

	// Propriétés mappées automatiquement depuis $fields
	public $rowid;
	public $ref        = '';
	public $entity;
	public $label      = '';
	public $description;
	public $fk_soc;
	public $date_creation;
	public $tms;
	public $fk_user_creat;
	public $fk_user_modif;
	public $status     = self::STATUS_DRAFT;
	public $note_public;
	public $note_private;
	public $import_key;

	public function __construct(DoliDB $db)
	{
		parent::__construct($db);
	}

	public function create(User $user, $notrigger = 0)
	{
		return $this->createCommon($user, $notrigger);
	}

	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		return $result;
	}

	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = [], $filtermode = 'AND')
	{
		global $conf;

		$records = [];
		$sql = 'SELECT ';
		$sql .= $this->getFieldList('t');
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' AS t';
		$sql .= ' WHERE t.entity IN ('.getEntity($this->table_element).')';

		foreach ($filter as $key => $value) {
			if ($key == 't.rowid') {
				$sql .= ' AND '.$key.'='.((int) $value);
			} elseif (strpos($key, 'date') !== false) {
				$sql .= ' AND '.$key.' = \''.$this->db->idate($value).'\'';
			} elseif ($key == 'customsql') {
				$sql .= ' AND '.$value;
			} else {
				$sql .= ' AND '.$key.' LIKE \'%'.$this->db->escape($value).'%\'';
			}
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if ($limit > 0 || $offset > 0) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);
				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);
				$records[$record->id] = $record;
				$i++;
			}
			$this->db->free($resql);
			return $records;
		} else {
			$this->errors[] = $this->db->lasterror();
			return -1;
		}
	}

	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	public function validate(User $user, $notrigger = 0)
	{
		global $conf;

		$this->db->begin();

		if (empty($this->ref) || $this->ref == '(PROV)') {
			$this->ref = $this->getNextNumRef();
		}

		$this->status = self::STATUS_VALIDATED;
		$this->date_valid = dol_now();
		$this->fk_user_valid = $user->id;

		$result = $this->update($user, $notrigger);
		if ($result > 0) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	public function getNextNumRef()
	{
		// Générer une référence simple — adapter selon les besoins
		global $conf;
		$prefix = 'MON-';
		$sql = 'SELECT MAX(rowid) FROM '.MAIN_DB_PREFIX.$this->table_element;
		$sql .= ' WHERE entity = '.((int) $conf->entity);
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$next = ($obj && $obj->{'MAX(rowid)'}) ? ($obj->{'MAX(rowid)'} + 1) : 1;
			return $prefix.str_pad($next, 5, '0', STR_PAD_LEFT);
		}
		return $prefix.'00001';
	}

	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	public function LibStatut($status, $mode = 0)
	{
		global $langs;
		$langs->load('monmodule@monmodule');

		if ($mode == 0) {
			$label = [
				self::STATUS_DRAFT     => $langs->trans('Draft'),
				self::STATUS_VALIDATED => $langs->trans('Validated'),
				self::STATUS_CLOSED    => $langs->trans('Closed'),
			];
			return $label[$status] ?? '';
		}
		if ($mode >= 1) {
			$params = ['css' => 'minwidth75'];
			switch ($status) {
				case self::STATUS_DRAFT:
					return dolGetStatus($langs->trans('Draft'),     '', '', 'status0', $mode, 'dot', $params);
				case self::STATUS_VALIDATED:
					return dolGetStatus($langs->trans('Validated'), '', '', 'status1', $mode, 'dot', $params);
				case self::STATUS_CLOSED:
					return dolGetStatus($langs->trans('Closed'),    '', '', 'status6', $mode, 'dot', $params);
			}
		}
		return '';
	}

	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		global $langs;
		$langs->load('monmodule@monmodule');

		$url = dol_buildpath('/monmodule/monobjetcard.php', 1).'?id='.$this->id;
		$label = '<u>'.$langs->trans('MonObjet').'</u>';
		$label .= '<br><b>'.$langs->trans('Ref').'</b> : '.dol_escape_htmltag($this->ref);

		$linkclose = '"'.($morecss ? ' class="'.$morecss.'"' : '');
		$linkclose .= ($notooltip ? '' : ' title="'.dol_escape_htmltag($label).'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'"');
		$linkclose .= '>';

		$linkstart = '<a href="'.$url.$linkclose;
		$linkend   = '</a>';

		$result = $linkstart;
		if ($withpicto) {
			$result .= img_object(($notooltip ? '' : $label), $this->picto, ($notooltip ? '' : 'class="classfortooltip"'), 0, 0, $notooltip ? 0 : 1);
		}
		if ($withpicto != 2) {
			$result .= dol_escape_htmltag($this->ref);
		}
		$result .= $linkend;

		return $result;
	}

	// ============================================================
	// Génération de documents
	// ============================================================

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
}
