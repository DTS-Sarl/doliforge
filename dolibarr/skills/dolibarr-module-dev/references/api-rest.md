# API REST Dolibarr

Consommer et exposer des endpoints REST dans un module Dolibarr.

## Authentification

L'API REST Dolibarr utilise une clé API par utilisateur.

### Obtenir la clé API

Administration → Utilisateurs → fiche utilisateur → onglet "Autres informations" →
champ "Clé API".

Ou générer via SQL :
```sql
SELECT login, api_key FROM llx_user WHERE api_key IS NOT NULL LIMIT 10;
```

### Passer la clé dans les requêtes

```bash
# En header (recommandé)
curl -H "DOLAPIKEY: votre_cle_api" https://dolibarr.exemple.com/api/index.php/thirdparties

# En paramètre GET (moins sécurisé)
curl "https://dolibarr.exemple.com/api/index.php/thirdparties?api_key=votre_cle_api"
```

## Consommer l'API Dolibarr depuis PHP

Depuis un module, appeler l'API interne sans HTTP (accès direct aux classes) :

```php
// Accès direct — plus performant qu'un appel HTTP
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$soc = new Societe($db);
$result = $soc->fetch($id);
if ($result > 0) {
    // $soc->nom, $soc->email, etc.
}
```

Réserver les appels HTTP REST aux intégrations **externes** (autre application, webhook).

## Appel HTTP vers l'API Dolibarr depuis PHP

```php
function doliApiCall($endpoint, $method = 'GET', $data = [])
{
    global $conf;

    $apiKey = getDolGlobalString('MON_MODULE_API_KEY');
    $baseUrl = getDolGlobalString('MON_MODULE_API_URL'); // Ex: https://dolibarr.exemple.com/api/index.php

    $ch = curl_init($baseUrl.'/'.$endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'DOLAPIKEY: '.$apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        dol_syslog("API ERROR $httpCode: ".$response, LOG_ERR);
        return false;
    }

    return json_decode($response, true);
}

// Utilisation
$societes = doliApiCall('thirdparties?limit=10&sortfield=t.nom&sortorder=ASC');
$nouvelleFacture = doliApiCall('invoices', 'POST', ['socid' => 42, 'type' => 0]);
```

## Exposer un endpoint REST depuis un module

### Créer le fichier API

```
monmodule/
└── api/
    └── api_monmodule.class.php
```

```php
<?php
// api/api_monmodule.class.php

if (!defined('DOL_VERSION')) die('Acces interdit');

require_once DOL_DOCUMENT_ROOT.'/api/class/dolgraph.class.php';

class MonModule extends DolibarrApi
{
    public static $FIELDS = ['ref', 'label', 'fk_soc'];

    public $monobjet;

    public function __construct()
    {
        global $db;
        $this->db = $db;
        parent::__construct($this->db, 'monmodule', 'monobjet', 'read');
        $this->monobjet = new MonObjet($this->db);
    }

    /**
     * Retourne un objet par son ID
     *
     * @param int $id ID de l'objet
     * @return array Données de l'objet
     *
     * @throws RestException
     */
    public function get($id)
    {
        if (!DolibarrApiAccess::$user->hasRight('monmodule', 'monobjet', 'read')) {
            throw new RestException(403, 'Accès refusé');
        }

        $result = $this->monobjet->fetch($id);
        if ($result <= 0) {
            throw new RestException(404, 'Objet non trouvé');
        }

        return $this->_cleanObjectDatas($this->monobjet);
    }

    /**
     * Liste des objets
     *
     * @param int    $limit  Nombre max de résultats
     * @param int    $page   Page (0 = première)
     * @return array Liste d'objets
     */
    public function index($limit = 100, $page = 0)
    {
        if (!DolibarrApiAccess::$user->hasRight('monmodule', 'monobjet', 'read')) {
            throw new RestException(403, 'Accès refusé');
        }

        $obj_ret = [];
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."monmodule_objet WHERE entity=".((int) DolibarrApiAccess::$user->entity);
        $sql .= $this->db->plimit($limit, $page * $limit);

        $resql = $this->db->query($sql);
        if (!$resql) throw new RestException(500, $this->db->lasterror());

        while ($obj = $this->db->fetch_object($resql)) {
            $tmp = new MonObjet($this->db);
            $tmp->fetch($obj->rowid);
            $obj_ret[] = $this->_cleanObjectDatas($tmp);
        }

        return $obj_ret;
    }
}
```

### Déclarer l'API dans le descripteur

```php
// Dans modMonModule.class.php
$this->module_parts = [
    'apis' => 1,  // Activer le support API REST
];
```

### Endpoints générés automatiquement

Après déclaration, les endpoints sont accessibles sous :
```
GET    /api/index.php/monmodule/{id}     → get($id)
GET    /api/index.php/monmodule          → index()
POST   /api/index.php/monmodule          → post()
PUT    /api/index.php/monmodule/{id}     → put($id)
DELETE /api/index.php/monmodule/{id}     → delete($id)
```

## Endpoints Dolibarr courants

| Ressource | Endpoint |
|---|---|
| Tiers | `/thirdparties` |
| Factures | `/invoices` |
| Commandes | `/orders` |
| Produits | `/products` |
| Utilisateurs | `/users` |
| Documents | `/documents` |
| Paiements | `/payments` |

Documentation complète : `https://votre-dolibarr.com/api/index.php` (interface Swagger intégrée).