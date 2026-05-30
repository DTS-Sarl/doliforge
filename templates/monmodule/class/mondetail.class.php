<?php
/* Copyright (C) 2024 DTS SARL
 * Objet MonDetail — lignes de détail rattachées à MonObjet (parent-enfant)
 *
 * Pattern courant dans Dolibarr : lignes de facture, lignes de commande, etc.
 * Un MonObjet contient N MonDetail (relation 1-N via fk_monobjet).
 */

dol_include_once('/monmodule/class/monobjet.class.php');

class MonDetail extends CommonObjectLine
{
	public $table_element = 'monmodule_mondetail';
	public $element       = 'mondetail';
	public $fk_element    = 'fk_monobjet';  // FK vers le parent

	// ---- Champs ----
	public $fields = array(
		'rowid'         => array('type' => 'integer',  'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'index' => 1),
		'fk_monobjet'   => array('type' => 'integer',  'label' => 'MonObjet',    'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 0, 'index' => 1, 'foreignkey' => 'monmodule_monobjet.rowid'),
		'rang'          => array('type' => 'integer',  'label' => 'Rang',        'enabled' => 1, 'position' => 20,  'notnull' => 1, 'default' => 0, 'visible' => 0),
		'label'         => array('type' => 'varchar(255)', 'label' => 'Label',   'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 1),
		'description'   => array('type' => 'text',     'label' => 'Description', 'enabled' => 1, 'position' => 40,  'notnull' => 0, 'visible' => 1),
		'qty'           => array('type' => 'double',   'label' => 'Qty',         'enabled' => 1, 'position' => 50,  'notnull' => 1, 'default' => 1, 'visible' => 1),
		'price'         => array('type' => 'double(24,8)', 'label' => 'Price',   'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 1),
		'total'         => array('type' => 'double(24,8)', 'label' => 'Total',   'enabled' => 1, 'position' => 70,  'notnull' => 0, 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation','enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0),
	);

	public $id;
	public $fk_monobjet;
	public $rang;
	public $label;
	public $description;
	public $qty;
	public $price;
	public $total;
	public $date_creation;

	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	// ============================================================
	// CRUD
	// ============================================================

	/**
	 * Créer une ligne de détail
	 *
	 * @param  User $user   Utilisateur
	 * @return int           > 0 si OK, < 0 si erreur
	 */
	public function create(User $user)
	{
		global $conf;

		$this->date_creation = dol_now();
		$this->total = (float) $this->qty * (float) $this->price;

		// Déterminer le rang (dernier + 1)
		if (empty($this->rang)) {
			$sql = "SELECT MAX(rang) AS maxrang FROM ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " WHERE fk_monobjet = ".((int) $this->fk_monobjet);
			$res = $this->db->query($sql);
			if ($res) {
				$obj = $this->db->fetch_object($res);
				$this->rang = ($obj->maxrang ?? 0) + 1;
			}
		}

		$sql  = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "fk_monobjet, rang, label, description, qty, price, total, date_creation";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->fk_monobjet);
		$sql .= ", ".((int) $this->rang);
		$sql .= ", '".$this->db->escape($this->label)."'";
		$sql .= ", ".($this->description ? "'".$this->db->escape($this->description)."'" : "NULL");
		$sql .= ", ".((float) $this->qty);
		$sql .= ", ".((float) $this->price);
		$sql .= ", ".((float) $this->total);
		$sql .= ", '".$this->db->idate($this->date_creation)."'";
		$sql .= ")";

		$result = $this->db->query($sql);
		if ($result) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			dol_syslog("MonDetail::create id=".$this->id, LOG_DEBUG);
			return $this->id;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Charger une ligne par ID
	 */
	public function fetch($id)
	{
		$sql  = "SELECT rowid, fk_monobjet, rang, label, description, qty, price, total, date_creation";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE rowid = ".((int) $id);

		$result = $this->db->query($sql);
		if ($result && $this->db->num_rows($result) > 0) {
			$obj = $this->db->fetch_object($result);
			$this->id            = $obj->rowid;
			$this->fk_monobjet   = (int) $obj->fk_monobjet;
			$this->rang          = (int) $obj->rang;
			$this->label         = $obj->label;
			$this->description   = $obj->description;
			$this->qty           = (float) $obj->qty;
			$this->price         = (float) $obj->price;
			$this->total         = (float) $obj->total;
			$this->date_creation = $this->db->jdate($obj->date_creation);
			return 1;
		}
		return 0;
	}

	/**
	 * Charger toutes les lignes d'un objet parent
	 *
	 * @param  int   $fk_monobjet   ID de l'objet parent
	 * @return array                 Tableau d'objets MonDetail
	 */
	public static function fetchAllByParent(DoliDB $db, $fk_monobjet)
	{
		$lines = [];

		$sql  = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_mondetail";
		$sql .= " WHERE fk_monobjet = ".((int) $fk_monobjet);
		$sql .= " ORDER BY rang ASC";

		$result = $db->query($sql);
		if ($result) {
			while ($obj = $db->fetch_object($result)) {
				$line = new self($db);
				$line->fetch($obj->rowid);
				$lines[] = $line;
			}
		}

		return $lines;
	}

	/**
	 * Mettre à jour une ligne
	 */
	public function update(User $user)
	{
		$this->total = (float) $this->qty * (float) $this->price;

		$sql  = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
		$sql .= " label = '".$this->db->escape($this->label)."'";
		$sql .= ", description = ".($this->description ? "'".$this->db->escape($this->description)."'" : "NULL");
		$sql .= ", qty = ".((float) $this->qty);
		$sql .= ", price = ".((float) $this->price);
		$sql .= ", total = ".((float) $this->total);
		$sql .= ", rang = ".((int) $this->rang);
		$sql .= " WHERE rowid = ".((int) $this->id);

		if ($this->db->query($sql)) {
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Supprimer une ligne
	 */
	public function delete(User $user)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
		$sql .= " WHERE rowid = ".((int) $this->id);

		if ($this->db->query($sql)) {
			return 1;
		}

		$this->error = $this->db->lasterror();
		return -1;
	}

	/**
	 * Supprimer toutes les lignes d'un objet parent
	 */
	public static function deleteAllByParent(DoliDB $db, $fk_monobjet)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."monmodule_mondetail";
		$sql .= " WHERE fk_monobjet = ".((int) $fk_monobjet);

		return $db->query($sql) ? 1 : -1;
	}
}
