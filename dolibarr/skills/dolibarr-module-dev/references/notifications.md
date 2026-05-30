# Notifications et emails

Dolibarr permet d'envoyer des emails et de créer des événements agenda
automatiquement lors d'actions métier, via triggers ou appels directs.

## Envoyer un email via `CMailFile`

```php
require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

global $conf, $langs, $user;

$from    = getDolGlobalString('MAIN_MAIL_EMAIL_FROM', 'noreply@example.com');
$to      = 'destinataire@example.com';
$subject = $langs->trans('MonObjetValidated', $object->ref);
$body    = $langs->trans('MonObjetValidatedBody', $object->ref, $object->label);

// Corps HTML (optionnel)
$bodyhtml = '<p>'.$body.'</p>';

// Pièce jointe (optionnel)
$filedir  = $conf->monmodule->dir_output.'/'.dol_sanitizeFileName($object->ref);
$filename = $object->ref.'.pdf';
$filepath = $filedir.'/'.$filename;
$mimetype = 'application/pdf';

$mailfile = new CMailFile(
    $subject,
    $to,
    $from,
    $body,
    array($filepath),         // Fichiers joints (tableau de chemins)
    array($mimetype),         // Types MIME correspondants
    array($filename),         // Noms des fichiers joints
    '',                       // CC
    '',                       // BCC
    0,                        // Priorité (0 = normale)
    !empty($bodyhtml) ? 1 : 0, // 1 si HTML
    '',                       // Erreurs
    '',                       // CSS inline
    '',                       // Tracking ID
    '',                       // Morphy
    'monmodule'               // Origine (pour le log)
);

if (!empty($bodyhtml)) {
    $mailfile->msgHTML = $bodyhtml;
}

$result = $mailfile->sendfile();
if ($result) {
    dol_syslog('MonModule::email envoyé à '.$to, LOG_INFO);
} else {
    dol_syslog('MonModule::email erreur: '.$mailfile->error, LOG_ERR);
    $this->errors[] = $mailfile->error;
}
```

## Envoyer depuis un trigger (cas le plus courant)

Déclencher un email automatiquement lors d'un événement :

```php
// Dans le trigger
public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
{
    if (!isModEnabled('monmodule')) return 0;

    switch ($action) {
        case 'MONOBJET_VALIDATE':
            return $this->sendNotificationEmail($object, $user, $langs, $conf);
    }
    return 0;
}

private function sendNotificationEmail($object, $user, $langs, $conf)
{
    try {
        // Vérifier si les notifications sont activées
        if (!getDolGlobalString('MONMODULE_NOTIFY_ON_VALIDATE')) return 0;

        require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

        $langs->load('monmodule@monmodule');

        $from    = getDolGlobalString('MAIN_MAIL_EMAIL_FROM');
        $to      = getDolGlobalString('MONMODULE_NOTIFY_EMAIL', $user->email);
        $subject = $langs->trans('MonObjetValidatedSubject', $object->ref);

        // Corps avec variables substituées
        $body  = $langs->trans('MonObjetValidatedBody',
            $object->ref,
            $object->label,
            dol_print_date(dol_now(), 'dayhour'),
            $user->getFullName($langs)
        );

        $mailfile = new CMailFile($subject, $to, $from, $body);
        $mailfile->sendfile();

    } catch (Throwable $e) {
        // Ne jamais bloquer l'opération principale pour un email
        dol_syslog('MonModule::trigger email error: '.$e->getMessage(), LOG_ERR);
    }

    return 0; // Non-bloquant
}
```

## Créer un événement agenda automatiquement

Dolibarr a un système d'événements agenda (`actioncomm`) pour tracer les actions :

```php
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

$actioncomm = new ActionComm($db);
$actioncomm->type_code  = 'AC_OTH_AUTO';  // Action automatique
$actioncomm->code       = 'AC_MONOBJET_VALIDATE';
$actioncomm->label      = $langs->trans('MonObjetValidatedAction', $object->ref);
$actioncomm->note_private = $langs->trans('MonObjetValidatedBy', $user->getFullName($langs));
$actioncomm->datep      = dol_now();        // Date de début
$actioncomm->datef      = dol_now();        // Date de fin
$actioncomm->fk_user_author = $user->id;
$actioncomm->userownerid    = $user->id;

// Lier à l'objet source
$actioncomm->fk_element  = $object->id;
$actioncomm->elementtype = 'monobjet@monmodule';

// Lier au tiers si applicable
if (!empty($object->fk_soc)) {
    $actioncomm->socid = $object->fk_soc;
}

$actioncomm->entity = $conf->entity;

$result = $actioncomm->create($user);
if ($result < 0) {
    dol_syslog('MonModule::actioncomm error: '.$actioncomm->error, LOG_ERR);
}
```

## Utiliser le système de notifications natif Dolibarr

Dolibarr dispose d'un système de notifications configurable par l'utilisateur
(Accueil > Configuration > Notifications). Pour l'intégrer :

### Déclarer les événements dans le descripteur

```php
// Dans modMonModule.class.php — méthode init()
// Enregistrer les types d'actions pour le système de notification
$sql = "INSERT INTO ".MAIN_DB_PREFIX."c_action_trigger";
$sql .= " (code, label, description, elementtype, rang)";
$sql .= " VALUES ('MONOBJET_VALIDATE', 'MonObjet validated',";
$sql .= " 'Triggered when a MonObjet is validated',";
$sql .= " 'monobjet@monmodule', 50)";
// Exécuter seulement si le code n'existe pas déjà
$resql = $this->db->query("SELECT rowid FROM ".MAIN_DB_PREFIX."c_action_trigger WHERE code='MONOBJET_VALIDATE'");
if ($this->db->num_rows($resql) == 0) {
    $this->db->query($sql);
}
```

### Déclencher via `call_trigger`

Dans la méthode `validate()` de l'objet :

```php
$result = $this->call_trigger('MONOBJET_VALIDATE', $user);
```

Dolibarr envoie automatiquement les notifications configurées pour cet événement.

## Templates email

Pour des emails formatés, créer des templates dans `langs/` :

```ini
# langs/fr_FR/monmodule.lang

# ---- Notifications ----
MonObjetValidatedSubject=Objet %s validé
MonObjetValidatedBody=L'objet %s (%s) a été validé le %s par %s.
MonObjetValidatedAction=Validation de l'objet %s
MonObjetValidatedBy=Validé par %s
```

Usage avec `$langs->trans()` :

```php
$subject = $langs->trans('MonObjetValidatedSubject', $object->ref);
// Résultat : "Objet OBJ-001 validé"

$body = $langs->trans('MonObjetValidatedBody',
    $object->ref,           // %s 1
    $object->label,         // %s 2
    dol_print_date(dol_now(), 'dayhour'),  // %s 3
    $user->getFullName($langs)             // %s 4
);
```

## Constantes de configuration

```php
// Activer les notifications par email à la validation
dolibarr_set_const($db, 'MONMODULE_NOTIFY_ON_VALIDATE', '1', 'chaine', 0, '', $conf->entity);
// Adresse email de notification (par défaut : email de l'utilisateur)
dolibarr_set_const($db, 'MONMODULE_NOTIFY_EMAIL', 'admin@example.com', 'chaine', 0, '', $conf->entity);
```

## Bonnes pratiques

- **Ne jamais bloquer** une opération métier à cause d'un échec email — `try/catch` dans les triggers
- **Utiliser `$langs->trans()`** pour tous les textes — jamais de chaînes en dur
- **Vérifier** que l'email est configuré (`MAIN_MAIL_EMAIL_FROM`) avant d'envoyer
- **Logger** les envois et erreurs via `dol_syslog()`
- **Pièces jointes** : générer le document avant d'envoyer, vérifier que le fichier existe
- **Multi-entité** : passer `$conf->entity` dans les constantes de configuration
- **Événements agenda** : toujours lier à l'objet source via `fk_element` + `elementtype`