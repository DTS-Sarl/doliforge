# Squelette de module Dolibarr — DoliForge

Module skeleton conforme aux conventions Dolibarr 18-23 et aux règles DoliForge.

## Utilisation

### 1. Copier le dossier

```bash
cp -r ~/.doliforge/templates/monmodule htdocs/custom/tonmodule
```

### 2. Remplacer les placeholders (case-sensitive)

| Placeholder | Remplacer par | Exemple |
| --- | --- | --- |
| `monmodule` | nom technique (minuscules) | `dolipresence` |
| `Monmodule` | CamelCase | `Dolipresence` |
| `MONMODULE` | MAJUSCULES | `DOLIPRESENCE` |
| `MonObjet` | objet métier CamelCase | `Presence` |
| `monobjet` | objet métier minuscules | `presence` |
| `500000` | numéro unique de module | `500042` |

Commande de remplacement en masse :

```bash
cd htdocs/custom/
# Renommer le dossier
mv monmodule tonmodule

# Renommer les fichiers
mv tonmodule/core/modules/modMonmodule.class.php \
   tonmodule/core/modules/modTonmodule.class.php
mv tonmodule/class/monobjet.class.php \
   tonmodule/class/tonobjet.class.php
mv tonmodule/langs/fr_FR/monmodule.lang \
   tonmodule/langs/fr_FR/tonmodule.lang
mv tonmodule/langs/en_US/monmodule.lang \
   tonmodule/langs/en_US/tonmodule.lang

# Remplacer le contenu (macOS)
find tonmodule/ -type f \( -name "*.php" -o -name "*.sql" -o -name "*.lang" -o -name "*.css" -o -name "*.js" \) \
  -exec sed -i '' \
    -e 's/monmodule/tonmodule/g' \
    -e 's/Monmodule/Tonmodule/g' \
    -e 's/MONMODULE/TONMODULE/g' \
    -e 's/MonObjet/TonObjet/g' \
    -e 's/monobjet/tonobjet/g' \
    {} \;
```

### 3. Choisir un numéro de module unique

Ouvrir `core/modules/modTonmodule.class.php` et remplacer `500000` par un
numéro unique dans la plage `500000-999999`. Vérifier qu'aucun autre module
actif n'utilise ce numéro.

### 4. Exécuter le SQL

```bash
mysql -u root -p dolibarr < tonmodule/sql/llx_tonmodule_tonobjet.sql
mysql -u root -p dolibarr < tonmodule/sql/llx_tonmodule_tonobjet.key.sql
```

Ou activer le module depuis l'interface — Dolibarr exécute automatiquement
les fichiers SQL au moment de l'activation.

### 5. Activer le module

Accueil → Configuration → Modules → chercher "Ton Module" → Activer.

---

## Structure des fichiers

```
tonmodule/
├── core/modules/modTonmodule.class.php   ← Descripteur module
├── class/tonobjet.class.php              ← Objet métier
├── admin/
│   ├── setup.php                         ← Page de configuration
│   └── about.php                         ← Page à propos
├── sql/
│   ├── llx_tonmodule_tonobjet.sql        ← Création table
│   └── llx_tonmodule_tonobjet.key.sql    ← Index
├── langs/
│   ├── fr_FR/tonmodule.lang
│   └── en_US/tonmodule.lang
├── lib/tonmodule.lib.php                 ← Fonctions utilitaires + prepare_head
├── css/tonmodule.css                     ← Styles (variables :root, pas de dégradés)
├── js/tonmodule.js                       ← JS (namespace unique)
├── tonobjetcard.php                      ← Page fiche
├── tonobjetlist.php                      ← Page liste
└── ChangeLog
```

## Checklist avant activation

- [ ] Numéro de module unique (500000-999999)
- [ ] Tous les `monmodule`/`MonObjet` remplacés
- [ ] SQL exécuté (ou module activé)
- [ ] Traductions `fr_FR` et `en_US` complètes
- [ ] CSS : aucun dégradé, couleurs en variables `:root`
- [ ] JS : namespace unique, pas de `console.log`
