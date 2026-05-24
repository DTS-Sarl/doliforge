# Base de données

## Un schéma = des fichiers `.sql` dans `sql/`

Le schéma d'un module est livré sous forme de fichiers `.sql` dans `sql/`, chargés
à l'activation par `_load_tables('/monmodule/sql/')` (appelé depuis `init()` du
descripteur). Il n'y a pas de système de migration façon Laravel.

Deux fichiers par table : un pour la création, un pour les clés/index.

`sql/llx_monmodule_monobjet.sql` :
```sql
CREATE TABLE llx_monmodule_monobjet(
    rowid         integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    ref           varchar(128) NOT NULL,
    label         varchar(255),
    fk_soc        integer,
    status        integer DEFAULT 0 NOT NULL,
    date_creation datetime NOT NULL,
    fk_user_creat integer NOT NULL,
    entity        integer DEFAULT 1 NOT NULL
) ENGINE=innodb;
```

`sql/llx_monmodule_monobjet.key.sql` :
```sql
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_monobjet_ref (ref);
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_monobjet_fk_soc (fk_soc);
ALTER TABLE llx_monmodule_monobjet ADD CONSTRAINT fk_monobjet_fk_soc
    FOREIGN KEY (fk_soc) REFERENCES llx_societe(rowid);
```

Séparer `CREATE TABLE` et les clés dans deux fichiers évite les erreurs d'ordre de
chargement.

## `llx_` dans les fichiers SQL, `MAIN_DB_PREFIX` dans le PHP

Le préfixe `llx_` est une **convention d'écriture des fichiers `.sql`** : Dolibarr
le remplace à l'installation par la valeur réelle de `MAIN_DB_PREFIX`. Mais dans le
code PHP, ne jamais écrire `llx_` en dur — toujours la constante.

Incorrect (PHP) :
```php
$sql = "SELECT rowid FROM llx_monmodule_monobjet";
```

Correct (PHP) :
```php
$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_monobjet";
```

## Inclure un champ `entity` sur toute table d'objet métier

Sans `entity`, les données d'un module fuitent entre les sociétés d'une
installation multicompany. La colonne est obligatoire dès que l'objet déclare
`ismultientitymanaged = 1`.

```sql
entity integer DEFAULT 1 NOT NULL
```

## Indexer les colonnes filtrées, triées ou jointes

Toute colonne apparaissant dans un `WHERE`, `ORDER BY`, `JOIN` ou `GROUP BY` doit
être indexée. `entity` et les clés étrangères (`fk_*`) en particulier.

Incorrect (table interrogée sur `status` et `fk_soc`, sans index) :
```sql
CREATE TABLE llx_monmodule_monobjet(
    rowid  integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
    fk_soc integer,
    status integer
) ENGINE=innodb;
```

Correct (index dans le `.key.sql`) :
```sql
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_monobjet_fk_soc (fk_soc);
ALTER TABLE llx_monmodule_monobjet ADD INDEX idx_monobjet_status (status);
```

Pour un filtre + tri combinés (`WHERE status = ? ORDER BY date_creation`), créer un
index composé dans l'ordre des colonnes.

## Faire évoluer le schéma entre deux versions du module

Ne jamais modifier un fichier `.sql` déjà déployé : les installations existantes ne
le rejoueront pas. Ajouter un nouveau fichier `.sql` de migration ; les fichiers du
dossier `sql/` sont rejoués au changement de version du module.

Incorrect (édition d'un fichier déjà livré) :
```sql
-- llx_monmodule_monobjet.sql, déjà en production
ALTER TABLE ... ADD COLUMN nouvelle_colonne ...   -- ajouté après coup
```

Correct (nouveau fichier de migration) :
```sql
-- sql/migration-1.1.0.sql
ALTER TABLE llx_monmodule_monobjet ADD COLUMN nouvelle_colonne varchar(64);
```

## Ne jamais mélanger schéma (DDL) et données (DML) dans un même fichier

Un fichier qui crée une table puis y insère des lignes laisse un état
irrécupérable en cas d'échec partiel. Séparer : un fichier pour la structure, un
fichier pour les données initiales.

## La sécurité des requêtes : échappement obligatoire

La couche `$db` n'a pas de requêtes préparées. Toute valeur issue d'une entrée doit
être échappée. Voir `securite.md` pour le détail (`$db->escape()`, casts `(int)`,
`escapeforlike()`).

## Conventions de colonnes — référence

| Colonne | Type | Obligatoire | Rôle |
|---|---|---|---|
| `rowid` | `integer AUTO_INCREMENT PRIMARY KEY` | Oui | Clé primaire |
| `ref` | `varchar(128)` | Recommandé | Référence unique de l'objet |
| `entity` | `integer DEFAULT 1 NOT NULL` | Si multi-entité | Identifiant société |
| `status` | `smallint DEFAULT 0 NOT NULL` | Recommandé | Statut de l'objet |
| `date_creation` | `datetime NOT NULL` | Recommandé | Date de création |
| `tms` | `timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` | Recommandé | Modification auto |
| `fk_user_creat` | `integer NOT NULL` | Recommandé | Créateur |
| `fk_user_modif` | `integer` | Optionnel | Dernier modificateur |
| `import_key` | `varchar(14)` | Optionnel | Clé d'import externe |

Toujours ajouter les colonnes d'audit (`date_creation`, `tms`, `fk_user_creat`).
Elles ne coûtent rien et sont indispensables au debugging.

## Requêtes SQL dans le code PHP

Toujours construire les requêtes avec `MAIN_DB_PREFIX` et utiliser les méthodes
de `$db` pour l'exécution :

```php
$sql  = "SELECT t.rowid, t.ref, t.label, t.status";
$sql .= " FROM ".MAIN_DB_PREFIX."monmodule_monobjet as t";
$sql .= " WHERE t.entity IN (".getEntity('monobjet').")";
$sql .= " AND t.status = ".((int) $status);
if (!empty($search_ref)) {
    $sql .= " AND t.ref LIKE '%".$db->escape($db->escapeforlike($search_ref))."%'";
}
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    $i = 0;
    while ($i < min($num, $limit)) {
        $obj = $db->fetch_object($resql);
        // ... traitement
        $i++;
    }
    $db->free($resql);
} else {
    dol_syslog("SQL error: ".$db->lasterror(), LOG_ERR);
}
```

Helpers SQL utiles :

| Helper | Rôle |
|---|---|
| `$db->order($sortfield, $sortorder)` | Clause ORDER BY sécurisée |
| `$db->plimit($limit, $offset)` | Clause LIMIT portable (MySQL/PostgreSQL) |
| `$db->idate($timestamp)` | Formate une date pour insertion SQL |
| `$db->jdate($sqldate)` | Convertit une date SQL en timestamp PHP |
| `$db->lasterror()` | Dernier message d'erreur |
| `$db->free($resql)` | Libère le résultat |
| `$db->num_rows($resql)` | Nombre de lignes retournées |
| `$db->fetch_object($resql)` | Récupère une ligne en objet |

## Moteur et charset

Toujours spécifier `ENGINE=innodb` (minuscules acceptées). Dolibarr gère le charset
au niveau de la connexion — ne pas ajouter `CHARACTER SET` ou `COLLATE` dans les
CREATE TABLE (risque de conflit avec la configuration Dolibarr).
