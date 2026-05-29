# Tests et validation des modules Dolibarr

Valider un module avant livraison sans framework de test formel.
Dolibarr ne dispose pas de PHPUnit intégré — la validation se fait par pages de test
admin, fixtures SQL et checklists de recette manuelle.

## Page de test admin

Créer `admin/test.php` pour tester les fonctionnalités critiques directement dans
l'interface :

```php
<?php
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
$res = @include '../../main.inc.php';
if (!$res) $res = @include '../../../main.inc.php';
if (!$res) die('Include of main fails');
if (!$user->admin) accessforbidden();

llxHeader('', 'Test MonModule');

print load_fiche_titre('Tests MonModule');

// Test 1 : instanciation de la classe
$obj = new MonObjet($db);
$ok = is_object($obj);
print '<p>'.($ok ? '✓' : '✗').' Instanciation MonObjet</p>';

// Test 2 : connexion base de données
$sql = "SELECT COUNT(rowid) AS nb FROM ".MAIN_DB_PREFIX."monmodule_objet WHERE entity=".((int)$conf->entity);
$resql = $db->query($sql);
$row = $db->fetch_object($resql);
print '<p>✓ Table accessible — '.$row->nb.' enregistrements</p>';

// Test 3 : création d'un objet test
$obj->ref = 'TEST-'.dol_print_date(dol_now(), '%Y%m%d%H%M%S');
$obj->fk_soc = 0;
$obj->entity = $conf->entity;
$result = $obj->create($user);
print '<p>'.($result > 0 ? '✓' : '✗').' Création : '.($result > 0 ? 'rowid='.$result : $obj->error).'</p>';

// Test 4 : lecture
if ($result > 0) {
    $obj2 = new MonObjet($db);
    $res2 = $obj2->fetch($result);
    print '<p>'.($res2 > 0 ? '✓' : '✗').' Fetch : ref='.$obj2->ref.'</p>';

    // Nettoyage
    $obj2->delete($user);
    print '<p>✓ Nettoyage objet test effectué</p>';
}

llxFooter();
```

**Supprimer ou protéger cette page avant mise en production.**

## Fixtures SQL pour les tests

Créer `sql/data.sql` avec des données de test reproductibles :

```sql
-- Données de test — NE PAS exécuter en production
-- Société de test
INSERT INTO llx_societe (nom, entity, datec, fk_user_creat)
VALUES ('SOCIETE TEST MONMODULE', 1, NOW(), 1);

-- Objet de test
INSERT INTO llx_monmodule_objet (ref, fk_soc, entity, status, date_creation, fk_user_creat)
VALUES ('TEST-001', LAST_INSERT_ID(), 1, 1, NOW(), 1);
```

## Checklist de recette avant livraison

### Activation / désactivation

- [ ] Le module s'active sans erreur PHP ni SQL
- [ ] Les tables SQL sont créées correctement à l'activation
- [ ] Le module se désactive sans erreur
- [ ] Le module se réactive après désactivation

### Pages principales

- [ ] La liste s'affiche (avec et sans données)
- [ ] La pagination fonctionne
- [ ] Les filtres de recherche fonctionnent
- [ ] La fiche détail s'affiche
- [ ] Le formulaire de création s'affiche

### CRUD

- [ ] Créer un objet — la référence est générée correctement
- [ ] Modifier un objet — les modifications sont sauvegardées
- [ ] Changer le statut (valider, annuler...)
- [ ] Supprimer un objet — la suppression est confirmée
- [ ] Les champs obligatoires sont validés côté serveur

### Permissions

- [ ] Un utilisateur sans droit n'accède pas aux pages
- [ ] Un utilisateur en lecture seule ne peut pas créer/modifier/supprimer
- [ ] L'administrateur a accès à tout

### Hooks et triggers

- [ ] Les triggers se déclenchent sur les actions attendues
- [ ] Les hooks s'affichent aux bons endroits
- [ ] Les onglets supplémentaires s'affichent sur les fiches concernées

### Multi-entité

- [ ] Les données d'une entité ne sont pas visibles depuis une autre entité
- [ ] Le filtre `entity` est présent dans toutes les requêtes SELECT

### Formulaires et CSRF

- [ ] Les formulaires POST incluent le token CSRF
- [ ] Une soumission avec token invalide est rejetée (403)
- [ ] Les appels AJAX fonctionnent (pas de 403)

### Format et export

- [ ] La génération PDF fonctionne (si applicable)
- [ ] Le téléchargement fonctionne
- [ ] Les fichiers générés s'ouvrent correctement

### Administration

- [ ] La page de configuration admin sauvegarde les constantes
- [ ] Les constantes sauvegardées sont relues correctement après rechargement

## Tester sur l'hébergement cible avant livraison

Un test qui passe en local ne garantit pas le bon fonctionnement en production.
Toujours tester sur l'hébergement cible (o2switch, OVH, etc.) avant de livrer, car :

- Version PHP différente
- WAF (Tiger Protect, ModSecurity) peut bloquer des requêtes
- Permissions de fichiers différentes
- Configuration MySQL différente (mode strict, collation)