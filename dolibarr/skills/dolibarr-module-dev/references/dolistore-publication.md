# Publication DoliStore

Checklist à valider avant de soumettre un module sur DoliStore, en plus des passes
sécurité, compatibilité et conventions.

## Checklist

- [ ] **Numéro de module officiel** : `$this->numero` correspond à un identifiant
      réservé auprès de l'association Dolibarr (page de réservation du wiki). Ne pas
      publier avec un numéro arbitraire.
- [ ] **Licence GPL v3+** : en-tête de licence dans les fichiers source, fichier
      `COPYING`/`licence` présent. Exigence de l'écosystème.
- [ ] **Descripteur complet** : `editor_name`, `editor_url`, `version`,
      `descriptionlong`, `family`, `picto` renseignés.
- [ ] **Plage de versions déclarée et testée** : indiquer les versions Dolibarr
      supportées et avoir testé dessus (cible : Dolibarr 21 à 23).
- [ ] **Traductions** : au minimum `en_US` en plus de `fr_FR`. Aucun texte affiché
      en dur ; toutes les clés utilisées existent dans les fichiers `.lang`.
- [ ] **Documentation** : `README.md` clair (installation, configuration, usage) et
      `ChangeLog` à jour.
- [ ] **Zéro modification du cœur** : passe compatibilité entièrement verte — un
      module qui patche le cœur est rejeté.
- [ ] **Cycle d'installation propre** : activation → utilisation → désactivation →
      suppression sans résidu ni erreur.
- [ ] **Aucune dépendance non déclarée**, aucun chemin absolu codé en dur, aucun
      `llx_` en dur dans le PHP.
- [ ] **Conformité `phpcs`** au standard de codage Dolibarr, sans erreur bloquante.
- [ ] **Pas de données sensibles** dans le paquet : clés, identifiants, mots de
      passe, données de test réelles.
- [ ] **Captures d'écran** et description commerciale prêtes pour la fiche.

## Vérifier la conformité au standard de codage (optionnel)

Dolibarr a son propre standard de codage, différent de PSR-12, publié sous forme de
ruleset PHP_CodeSniffer dans le dépôt Dolibarr : `dev/setup/codesniffer/ruleset.xml`.

Ce fichier n'est ni dans le projet de module ni dans l'environnement de
développement (un dossier autonome). Pour l'utiliser avant publication : récupérer
le ruleset depuis le dépôt officiel Dolibarr sur GitHub, installer phpcs, puis
lancer le contrôle sur le dossier du module. C'est un contrôle d'appoint — la
conformité de fond est assurée par le respect des fiches du skill.

## Avant publication : repasser les trois audits

Une publication DoliStore n'est valide que si les trois fiches d'audit sont
vertes : `securite.md`, `compatibilite-ecosysteme.md`, `conventions-code.md`. La
checklist ci-dessus s'y ajoute, elle ne les remplace pas.

## Préparer le paquet ZIP

Le ZIP doit contenir un **unique dossier racine** correspondant au nom du module :

```text
monmodule_v1.0.0.zip
└── monmodule/
    ├── core/modules/modMonModule.class.php
    ├── class/
    ├── sql/
    ├── ...
    └── README.md
```

Incorrect (nom de dossier différent du module) :

```text
monmodule_v1.0.0.zip
└── monmodule_v1.0.0/     # FAUX — le dossier doit être "monmodule"
```

Commande de création :

```bash
cd htdocs/custom/
zip -r monmodule_v1.0.0.zip monmodule/ \
    -x "monmodule/.git/*" \
    -x "monmodule/node_modules/*" \
    -x "monmodule/.env"
```

## Fichiers à inclure / exclure

**Toujours inclure** :
- `core/`, `class/`, `sql/`, `admin/`, `lib/`, `langs/`, `css/`, `js/`, `img/`
- `README.md`, `ChangeLog`, `COPYING` (licence GPL)
- Pages PHP racine (`*card.php`, `*list.php`)

**Toujours exclure** :
- `.git/`, `.gitignore`, `node_modules/`
- `.env`, fichiers de config locale
- Fichiers de test/debug (`test_*.php`, `debug.php`)
- Données sensibles (clés API, mots de passe, dumps SQL)
- Fichiers IDE (`.vscode/`, `.idea/`)

## Numérotation de version

Suivre le versionnage sémantique :

- **MAJEURE** (`2.0.0`) : changements incompatibles (migration SQL, API cassée)
- **MINEURE** (`1.1.0`) : nouvelles fonctionnalités rétro-compatibles
- **PATCH** (`1.0.1`) : corrections de bugs uniquement

La version doit être cohérente entre le descripteur (`$this->version`), le
`ChangeLog` et le nom du fichier ZIP.
