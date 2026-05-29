# Versioning et ChangeLog des modules Dolibarr

Conventions de numérotation des versions, format du ChangeLog et nommage du ZIP
pour une cohérence entre modules et une publication DoliStore propre.

## Numérotation des versions — SemVer adapté Dolibarr

Format : `MAJEUR.MINEUR.CORRECTIF`

| Incrément | Quand l'utiliser | Exemple |
|---|---|---|
| **MAJEUR** | Refonte importante, rupture de compatibilité, migration SQL lourde | `1.0.0` → `2.0.0` |
| **MINEUR** | Nouvelle fonctionnalité, nouveau type de document, nouvel objet | `1.2.0` → `1.3.0` |
| **CORRECTIF** | Bugfix, correction CSS, ajustement texte, fix performance | `1.3.0` → `1.3.1` |

### Règles

- Commencer à `1.0.0` pour la première version publiée
- Ne jamais sauter de numéro (`1.2.0` → `1.4.0` est interdit)
- Un correctif ne peut pas ajouter de fonctionnalité — si c'est le cas, passer en MINEUR
- La version est définie dans le descripteur `modMonModule.class.php` :

```php
$this->version = '1.3.2';
```

Et dans une constante accessible depuis le CSS/JS :

```php
// Dans modMonModule.class.php ou en tête de lib/
define('MONMODULE_VERSION', '1.3.2');
```

## Format du ChangeLog

Le fichier `ChangeLog` (sans extension) se trouve à la racine du module :

```
monmodule/
└── ChangeLog
```

### Structure

```
Date: 2026-05-29
Version: 1.3.2

  - Fix: correction calcul montant net sur bulletin de paie multi-taux
  - Fix: affichage incorrect du statut sur la liste en mode multi-société

---

Date: 2026-04-15
Version: 1.3.0

  - New: ajout type de document "Attestation de congés"
  - New: export PDF avec logo société configurable
  - Enh: amélioration performances liste (index SQL sur fk_soc)
  - Fix: erreur 403 sur page AJAX en contexte WAF Tiger Protect

---

Date: 2026-03-01
Version: 1.2.0

  - New: support multi-pays (CM, CI, SN, GA)
  - New: page de configuration avancée admin
  - Enh: refactoring services en classes dédiées
  - Fix: variable entity manquante dans requête SQL liste

---

Date: 2026-01-10
Version: 1.0.0

  - Initial release
```

### Préfixes de ligne

| Préfixe | Signification |
|---|---|
| `New:` | Nouvelle fonctionnalité |
| `Enh:` | Amélioration d'une fonctionnalité existante |
| `Fix:` | Correction de bug |
| `Sec:` | Correctif de sécurité (toujours mentionner) |
| `Dep:` | Fonctionnalité dépréciée |

Ne jamais écrire de phrases vagues comme "diverses corrections" ou
"améliorations générales" — chaque entrée doit décrire un changement précis.

## Nommage du fichier ZIP

Format standard pour DoliStore et la distribution :

```
module_nomdumodule-X.Y.Z.zip
```

Exemples :
```
module_dolidocpro-3.1.0.zip
module_dolipaieafrica-4.2.0.zip
module_dolitable-3.5.0.zip
```

### Structure interne du ZIP

Le ZIP doit contenir **un seul dossier** à la racine, dont le nom correspond au
nom technique du module :

```
module_dolidocpro-3.1.0.zip
└── dolidocpro/
    ├── core/modules/modDolidocpro.class.php
    ├── class/
    ├── admin/
    ├── langs/
    ├── sql/
    └── ...
```

Ne jamais mettre les fichiers directement à la racine du ZIP sans dossier
conteneur — Dolibarr ne pourra pas l'installer correctement.

## Créer le ZIP

```bash
# Depuis le répertoire parent du module
cd htdocs/custom/
zip -r module_monmodule-X.Y.Z.zip monmodule/ \
    --exclude "monmodule/.git/*" \
    --exclude "monmodule/.DS_Store" \
    --exclude "monmodule/**/.gitkeep" \
    --exclude "monmodule/tests/*"
```

Vérifier le contenu avant envoi :

```bash
zipinfo module_monmodule-X.Y.Z.zip | head -20
# Doit commencer par : monmodule/
```

## Checklist versioning avant publication

- [ ] Version mise à jour dans `modMonModule.class.php`
- [ ] Constante `MONMODULE_VERSION` mise à jour
- [ ] ChangeLog mis à jour avec la date du jour et toutes les modifications
- [ ] ZIP nommé `module_nomdumodule-X.Y.Z.zip`
- [ ] ZIP contient un seul dossier racine au nom du module
- [ ] Aucun fichier `.git`, `.DS_Store`, `tests/` ou debug dans le ZIP
