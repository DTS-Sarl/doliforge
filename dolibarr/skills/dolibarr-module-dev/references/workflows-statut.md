# Workflows et machines à états

Un objet métier Dolibarr suit un cycle de vie à travers des statuts. Le pattern
standard définit des constantes de statut, des méthodes de transition, et des
boutons conditionnels dans l'interface.

## Déclarer les statuts

Dans la classe objet, déclarer les constantes et les méthodes de transition :

```php
class MonObjet extends CommonObject
{
    // ---- Constantes de statut ----
    const STATUS_DRAFT     = 0;
    const STATUS_VALIDATED = 1;
    const STATUS_APPROVED  = 5;   // Optionnel : workflow d'approbation
    const STATUS_CLOSED    = 9;
    const STATUS_CANCELLED = -1;  // Optionnel : annulation

    // ---- Tableau $fields — colonne status ----
    public $fields = array(
        // ...
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1,
            'notnull' => 1, 'default' => 0, 'index' => 1, 'position' => 500,
            'arrayofkeyval' => array(
                0 => 'Draft',
                1 => 'Validated',
                5 => 'Approved',
                9 => 'Closed',
                -1 => 'Cancelled',
            )),
    );
}
```

## Méthodes de transition

Chaque transition est une méthode dédiée avec vérification de l'état source :

```php
/**
 * Valider l'objet (Draft → Validated)
 *
 * @param  User $user   Utilisateur qui valide
 * @param  int  $notrigger 1 = ne pas déclencher les triggers
 * @return int  > 0 si OK, < 0 si erreur
 */
public function validate(User $user, $notrigger = 0)
{
    global $conf, $langs;

    // Vérifier l'état source
    if ($this->status != self::STATUS_DRAFT) {
        $this->error = $langs->trans('ErrorObjectNotInDraftStatus');
        return -1;
    }

    // Vérifier les prérequis métier
    if (empty($this->ref)) {
        $this->error = $langs->trans('ErrorFieldRequired', $langs->transnoentities('Ref'));
        return -1;
    }

    $this->db->begin();

    // Générer la référence définitive si nécessaire
    if (preg_match('/^[\(]?PROV/', $this->ref)) {
        $num = $this->getNextNumRef();
        if (empty($num) || $num < 0) {
            $this->db->rollback();
            return -1;
        }
        $this->ref = $num;
    }

    $sql  = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
    $sql .= " SET status = ".self::STATUS_VALIDATED;
    $sql .= ", ref = '".$this->db->escape($this->ref)."'";
    $sql .= ", date_validation = '".$this->db->idate(dol_now())."'";
    $sql .= ", fk_user_valid = ".((int) $user->id);
    $sql .= " WHERE rowid = ".((int) $this->id);
    $sql .= " AND status = ".self::STATUS_DRAFT;  // Protection contre double validation

    $result = $this->db->query($sql);
    if (!$result || $this->db->affected_rows($result) == 0) {
        $this->db->rollback();
        $this->error = $this->db->lasterror();
        return -1;
    }

    $this->status = self::STATUS_VALIDATED;

    // Déclencher le trigger
    if (!$notrigger) {
        $result = $this->call_trigger('MONOBJET_VALIDATE', $user);
        if ($result < 0) {
            $this->db->rollback();
            return -1;
        }
    }

    $this->db->commit();
    return 1;
}

/**
 * Remettre en brouillon (Validated → Draft)
 */
public function setDraft(User $user, $notrigger = 0)
{
    if ($this->status != self::STATUS_VALIDATED) {
        $this->error = 'ErrorObjectNotValidated';
        return -1;
    }

    $this->db->begin();

    $sql  = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
    $sql .= " SET status = ".self::STATUS_DRAFT;
    $sql .= " WHERE rowid = ".((int) $this->id);
    $sql .= " AND status = ".self::STATUS_VALIDATED;

    if (!$this->db->query($sql)) {
        $this->db->rollback();
        return -1;
    }

    $this->status = self::STATUS_DRAFT;

    if (!$notrigger) {
        $this->call_trigger('MONOBJET_UNVALIDATE', $user);
    }

    $this->db->commit();
    return 1;
}

/**
 * Approuver (Validated → Approved) — workflow optionnel
 */
public function approve(User $user, $notrigger = 0)
{
    if ($this->status != self::STATUS_VALIDATED) {
        $this->error = 'ErrorObjectMustBeValidatedFirst';
        return -1;
    }

    $this->db->begin();

    $sql  = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
    $sql .= " SET status = ".self::STATUS_APPROVED;
    $sql .= ", fk_user_approve = ".((int) $user->id);
    $sql .= ", date_approve = '".$this->db->idate(dol_now())."'";
    $sql .= " WHERE rowid = ".((int) $this->id);

    if (!$this->db->query($sql)) {
        $this->db->rollback();
        return -1;
    }

    $this->status = self::STATUS_APPROVED;

    if (!$notrigger) {
        $this->call_trigger('MONOBJET_APPROVE', $user);
    }

    $this->db->commit();
    return 1;
}

/**
 * Clôturer (Validated/Approved → Closed)
 */
public function close(User $user, $notrigger = 0)
{
    if (!in_array($this->status, [self::STATUS_VALIDATED, self::STATUS_APPROVED])) {
        $this->error = 'ErrorObjectMustBeValidatedOrApproved';
        return -1;
    }

    $this->db->begin();

    $sql  = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
    $sql .= " SET status = ".self::STATUS_CLOSED;
    $sql .= ", date_cloture = '".$this->db->idate(dol_now())."'";
    $sql .= ", fk_user_cloture = ".((int) $user->id);
    $sql .= " WHERE rowid = ".((int) $this->id);

    if (!$this->db->query($sql)) {
        $this->db->rollback();
        return -1;
    }

    $this->status = self::STATUS_CLOSED;

    if (!$notrigger) {
        $this->call_trigger('MONOBJET_CLOSE', $user);
    }

    $this->db->commit();
    return 1;
}
```

## Diagramme de transitions

```
                 ┌──────────────┐
                 │    DRAFT     │ (0)
                 │  Brouillon   │
                 └──────┬───────┘
                        │ validate()
                        ▼
                 ┌──────────────┐
          ┌──────│  VALIDATED   │ (1)
          │      │   Validé     │──────────┐
          │      └──────┬───────┘          │
          │             │ approve()         │ close()
          │             ▼                  │
          │      ┌──────────────┐          │
          │      │  APPROVED    │ (5)      │
          │      │  Approuvé    │──────┐   │
          │      └──────────────┘      │   │
          │                    close() │   │
          │             ┌──────────────┘   │
          │             ▼                  ▼
          │      ┌──────────────┐
          │      │   CLOSED     │ (9)
          │      │   Clôturé    │
          │      └──────────────┘
          │
          │ setDraft()
          ▼
   Retour à DRAFT
```

## Boutons conditionnels dans la page fiche

Afficher les boutons d'action selon le statut actuel :

```php
// Dans monobjetcard.php — section tabsAction
if ($object->id > 0 && $action != 'edit') {
    print '<div class="tabsAction">';

    // Retour à la liste
    print dolGetButtonAction($langs->trans('BackToList'), '', 'default',
        dol_buildpath('/monmodule/monobjetlist.php', 1), '', true);

    // Modifier (seulement si brouillon)
    if ($object->status == MonObjet::STATUS_DRAFT) {
        if ($user->hasRight('monmodule', 'monobjet', 'write')) {
            print dolGetButtonAction($langs->trans('Modify'), '', 'default',
                $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=edit&token='.newToken(), '', true);
        }
    }

    // Valider (Draft → Validated)
    if ($object->status == MonObjet::STATUS_DRAFT) {
        if ($user->hasRight('monmodule', 'monobjet', 'write')) {
            print dolGetButtonAction($langs->trans('Validate'), '', 'default',
                $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_validate&token='.newToken(), '', true);
        }
    }

    // Approuver (Validated → Approved) — workflow optionnel
    if ($object->status == MonObjet::STATUS_VALIDATED) {
        if ($user->hasRight('monmodule', 'monobjet', 'approve')) {
            print dolGetButtonAction($langs->trans('Approve'), '', 'default',
                $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_approve&token='.newToken(), '', true);
        }
    }

    // Remettre en brouillon (Validated → Draft)
    if ($object->status == MonObjet::STATUS_VALIDATED) {
        if ($user->hasRight('monmodule', 'monobjet', 'write')) {
            print dolGetButtonAction($langs->trans('SetToDraft'), '', 'default',
                $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_setdraft&token='.newToken(), '', true);
        }
    }

    // Clôturer (Validated/Approved → Closed)
    if (in_array($object->status, [MonObjet::STATUS_VALIDATED, MonObjet::STATUS_APPROVED])) {
        if ($user->hasRight('monmodule', 'monobjet', 'write')) {
            print dolGetButtonAction($langs->trans('Close'), '', 'default',
                $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_close&token='.newToken(), '', true);
        }
    }

    // Supprimer (seulement en brouillon)
    if ($object->status == MonObjet::STATUS_DRAFT) {
        if ($user->hasRight('monmodule', 'monobjet', 'delete')) {
            print dolGetButtonAction($langs->trans('Delete'), '', 'delete',
                $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=confirm_delete&token='.newToken(), '', true);
        }
    }

    print '</div>';
}
```

## Confirmations avant action

Toute action critique (validation, suppression, clôture) doit demander confirmation :

```php
// En haut de la page, après le fetch de l'objet
if ($action == 'confirm_validate' && GETPOST('confirm', 'alpha') == 'yes') {
    $result = $object->validate($user);
    if ($result > 0) {
        setEventMessages($langs->trans('ObjectValidated'), null, 'mesgs');
    } else {
        setEventMessages($object->error, $object->errors, 'errors');
    }
    $action = '';
}

// Boîte de confirmation (avant le contenu de la page)
if ($action == 'confirm_validate') {
    $formconfirm = $form->formconfirm(
        $_SERVER['PHP_SELF'].'?id='.$object->id,
        $langs->trans('ValidateObject'),
        $langs->trans('ConfirmValidateObject', $object->ref),
        'confirm_validate', '', 0, 1
    );
    print $formconfirm;
}
```

## Badges de statut — `LibStatut` et `getLibStatut`

```php
// Dans la classe objet
public static $statusLabels = array(
    self::STATUS_DRAFT     => 'Draft',
    self::STATUS_VALIDATED => 'Validated',
    self::STATUS_APPROVED  => 'Approved',
    self::STATUS_CLOSED    => 'Closed',
    self::STATUS_CANCELLED => 'Cancelled',
);

public static $statusColors = array(
    self::STATUS_DRAFT     => 'status0',    // Gris
    self::STATUS_VALIDATED => 'status4',    // Bleu
    self::STATUS_APPROVED  => 'status6',    // Vert clair
    self::STATUS_CLOSED    => 'status6',    // Vert
    self::STATUS_CANCELLED => 'status5',    // Rouge
);

public function getLibStatut($mode = 0)
{
    return self::LibStatut($this->status, $mode);
}

public static function LibStatut($status, $mode = 0)
{
    global $langs;

    $label  = isset(self::$statusLabels[$status]) ? $langs->transnoentities(self::$statusLabels[$status]) : '';
    $color  = isset(self::$statusColors[$status]) ? self::$statusColors[$status] : 'status0';

    return dolGetStatus($label, '', '', $color, $mode);
}
```

## Bonnes pratiques

- **Vérifier l'état source** en début de chaque méthode de transition
- **Clause WHERE sur le statut** dans le UPDATE SQL — protection contre les exécutions concurrentes
- **Transaction** systématique autour de chaque transition
- **Trigger** à chaque changement de statut (pour permettre aux autres modules de réagir)
- **Ne pas sauter d'étape** : forcer le passage par les états intermédiaires
- **Permissions distinctes** : séparer le droit de valider du droit de modifier si nécessaire
- **Audit trail** : stocker `fk_user_valid`, `date_validation`, etc. pour la traçabilité