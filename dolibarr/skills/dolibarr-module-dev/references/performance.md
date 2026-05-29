# Performance des modules Dolibarr

Identifier et corriger les problèmes de performance SQL, mémoire et affichage.

## Repérer les requêtes lentes

### Activer le log des requêtes lentes

Dans `conf.php` en développement :

```php
$dolibarr_main_prod = '0';
$dolibarr_syslog_level = 7;
```

Puis ajouter temporairement dans le code suspect :

```php
$start = microtime(true);
$resql = $db->query($sql);
$duration = microtime(true) - $start;
if ($duration > 0.1) {
    dol_syslog("SLOW QUERY (".round($duration*1000)."ms): ".$sql, LOG_WARNING);
}
```

### Analyser avec EXPLAIN

Avant d'optimiser une requête, l'analyser dans phpMyAdmin :

```sql
EXPLAIN SELECT * FROM llx_monmodule_objet
WHERE fk_soc = 42 AND entity = 1 AND status = 1;
```

Les colonnes `key` (index utilisé) et `rows` (lignes scannées) indiquent le problème.

## Le problème N+1 — à éviter absolument

### Mauvais pattern (N+1)

```php
// 1 requête pour la liste
$resql = $db->query("SELECT rowid, fk_soc FROM llx_monmodule_objet WHERE entity=1");
while ($obj = $db->fetch_object($resql)) {
    // 1 requête SUPPLÉMENTAIRE par ligne = N+1 requêtes au total
    $resql2 = $db->query("SELECT nom FROM llx_societe WHERE rowid=".$obj->fk_soc);
    $soc = $db->fetch_object($resql2);
    echo $soc->nom;
}
```

### Bon pattern (JOIN)

```php
$sql = "SELECT o.rowid, o.ref, s.nom AS soc_nom";
$sql .= " FROM ".MAIN_DB_PREFIX."monmodule_objet AS o";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = o.fk_soc";
$sql .= " WHERE o.entity = ".((int) $conf->entity);
$sql .= " AND o.status = 1";
```

## Index SQL

Ajouter des index sur les colonnes filtrées fréquemment dans le SQL de création :

```sql
-- Dans llx_monmodule_objet.sql
ALTER TABLE llx_monmodule_objet ADD INDEX idx_monmodule_fk_soc (fk_soc);
ALTER TABLE llx_monmodule_objet ADD INDEX idx_monmodule_status (status);
ALTER TABLE llx_monmodule_objet ADD INDEX idx_monmodule_entity (entity);
-- Index combiné pour les filtres courants
ALTER TABLE llx_monmodule_objet ADD INDEX idx_monmodule_entity_status (entity, status);
```

Les colonnes systématiquement filtrées : `entity`, `fk_soc`, `status`, `fk_user_creat`,
colonnes de date pour les tris.

## Pagination — ne jamais charger toutes les lignes

Toujours paginer les listes avec `LIMIT` / `OFFSET` :

```php
$limit = $conf->liste_limit ?: 25;  // Respecter la préférence utilisateur
$page = GETPOSTINT('page') ?: 0;
$offset = $page * $limit;

$sql .= $db->plimit($limit, $offset);

// Compter le total séparément (pour l'affichage de la pagination)
$sqlCount = "SELECT COUNT(rowid) AS nb FROM ".MAIN_DB_PREFIX."monmodule_objet WHERE ...";
```

Ne jamais utiliser `fetchall()` sur une table potentiellement volumineuse.

## Cache des constantes Dolibarr

`getDolGlobalString()` lit depuis `$conf->global` — déjà en mémoire, pas de requête SQL.
Ne pas recréer une requête pour lire une constante déjà disponible via cette fonction.

Pour les données calculées coûteuses, utiliser une variable statique :

```php
function monmoduleGetBareme($annee)
{
    static $cache = [];
    if (isset($cache[$annee])) return $cache[$annee];

    // Calcul coûteux une seule fois
    $cache[$annee] = /* ... */;
    return $cache[$annee];
}
```

## Chargement des objets — éviter les fetch inutiles

Ne pas appeler `$object->fetch($id)` plusieurs fois dans la même page.
Charger une fois, passer l'objet en paramètre aux fonctions qui en ont besoin.

```php
// Charger une seule fois
$object = new MonObjet($db);
$object->fetch($id);

// Passer l'objet aux fonctions — pas l'ID
afficherDetail($object, $langs);
calculerTotaux($object);
```

## Assets CSS/JS — versionner pour le cache navigateur

Toujours versionner les assets pour forcer le rechargement après mise à jour :

```php
print '<link rel="stylesheet" href="'.dol_buildpath('/monmodule/css/monmodule.css', 1).'?v='.MONMODULE_VERSION.'">';
print '<script src="'.dol_buildpath('/monmodule/js/monmodule.js', 1).'?v='.MONMODULE_VERSION.'"></script>';
```

Définir `MONMODULE_VERSION` dans le descripteur ou en constante dans `main.inc.php`.