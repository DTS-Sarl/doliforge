# DoliForge

La forge IA de **DTS SARL** (Dywants Technologie & Services) :
skills, agents et fiches de reference pour developper et auditer des modules
Dolibarr 18-23 avec Claude Code, Cursor, Windsurf, Cline, RooCode, Codex
ou tout outil de coding IA.

**Outils supportes** : Claude Code, Cursor, Windsurf, Cline, RooCode, Codex (OpenAI),
et tout outil supportant les fichiers d'instructions projet.

## Installation

Depuis la **racine** d'un projet Dolibarr, une seule commande :

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/DTS-Sarl/doliforge/main/install.sh)
```

Le script :

1. **Telecharge DoliForge** dans `~/.doliforge/` (clone GitHub automatique)
2. **Detecte l'outil AI** et te propose de confirmer ou choisir (Claude Code, Cursor, Windsurf, Cline, RooCode, Codex)
3. **Detecte la racine git** du projet et configure dans le bon dossier

Pas besoin de cloner manuellement. Pas besoin de passer d'arguments.

> **Pourquoi `bash <(curl ...)` et pas `curl | bash` ?**
> Le premier garde stdin ouvert pour le menu interactif. Le second pipe stdin
> et empeche toute saisie — DoliForge bascule alors en auto-detection silencieuse.

### Que fait l'installation ?

| Outil | Fichiers configures |
| --- | --- |
| **Claude Code** | `.claude/skills/` (symlink) + `.claude/commands/` (6 commandes) + `CLAUDE.md` |
| **Cursor** | `.cursorrules` (regles Dolibarr injectees) |
| **Windsurf** | `.windsurfrules` (regles Dolibarr injectees) |
| **Cline** | `.clinerules` (regles Dolibarr injectees) |
| **RooCode** | `.roo/rules/dolibarr.md` (regles Dolibarr injectees) |
| **Codex** | `AGENTS.md` (instructions agent injectees) |

## Commandes disponibles

Apres installation, dans Claude Code :

| Commande | Usage |
| --- | --- |
| `/dolibarr-audit` | Auditer un module (securite, compatibilite, conventions) |
| `/dolibarr-create` | Creer un nouveau module Dolibarr depuis zero |
| `/dolibarr-debug` | Diagnostiquer un probleme (erreur 500, trigger inactif, etc.) |
| `/dolibarr-publish` | Preparer un module pour publication DoliStore |
| `/dolibarr-upgrade` | Migrer un module vers une version Dolibarr superieure |
| `/dolibarr-review` | Relire et critiquer du code (score /10 + corrections prioritaires) |

Le skill se declenche aussi **automatiquement** quand Claude detecte du travail
sur un module Dolibarr.

## Gestion

```bash
# Voir l'etat de l'installation
~/.doliforge/install.sh status

# Mettre a jour les skills depuis GitHub
~/.doliforge/install.sh update

# Desinstaller d'un projet
~/.doliforge/install.sh uninstall

# Installer sur un autre projet
cd /chemin/vers/autre-projet
~/.doliforge/install.sh install
```

## Contenu des skills

### Skill `dolibarr-module-dev`

19 fiches couvrant tout le cycle de vie d'un module :

| Fiche | Sujet |
| --- | --- |
| `structure-module.md` | Arborescence, nommage, dossier `ajax/`, assets |
| `descripteur.md` | `modXxx.class.php`, droits, menus, tabs, cronjobs |
| `objets-metier.md` | `CommonObject`, `$fields`, CRUD, relations FK, validation |
| `base-de-donnees.md` | SQL, prefixe, `entity`, migrations, helpers SQL |
| `securite.md` | `GETPOST`, CSRF, SQL, permissions, pages AJAX, upload fichier |
| `hooks-et-triggers.md` | Hooks, triggers, extrafields, evenements courants |
| `pages-ui.md` | Ossature page, barre d'actions, onglets, liste complete, badges statut |
| `css-js.md` | Variables CSS, pas de degrades, coherence inter-pages, namespace JS |
| `conventions-code.md` | Helpers, retours, transactions, `.lang`, admin multi-onglets, constantes |
| `internationalisation.md` | Fichiers `.lang`, `$langs->trans()`, parametres, pluriels |
| `compatibilite-ecosysteme.md` | Multi-entite, dependances, cycle de test |
| `pieges.md` | Pieges courants, cache, WAF, `restrictedArea` |
| `debug.md` | Methodologie debug, instruments visuels, erreurs courantes |
| `refactoring.md` | Restructurer sans casser, extraction de classes, migrations SQL |
| `performance.md` | Requetes lentes, N+1, index SQL, pagination, cache |
| `tests.md` | Page de test admin, fixtures SQL, checklist recette |
| `api-rest.md` | Consommer et exposer des endpoints REST Dolibarr |
| `versioning-changelog.md` | Numerotation versions, format ChangeLog, nommage ZIP |
| `dolistore-publication.md` | Checklist, ZIP, publication DoliStore |

### Agents

| Agent | Role |
| --- | --- |
| `dolibarr-auditeur` | Audit securite, compatibilite, conventions, CSS/JS |
| `dolibarr-createur` | Creation module from scratch — guide 9 etapes, produit le code complet |
| `dolibarr-optimiseur` | Audit performance : N+1, index manquants, pagination, assets |
| `dolibarr-migrateur` | Refactoring et migration de modules vers une version Dolibarr cible |

### Template de module

Un squelette de module pret a l'emploi est disponible dans `templates/monmodule/` :

- `core/modules/modMonmodule.class.php` — descripteur complet
- `class/monobjet.class.php` — CommonObject avec `$fields`, CRUD, statuts
- `sql/` — table SQL + index + FK
- `langs/fr_FR/` et `langs/en_US/` — fichiers de traduction complets
- `lib/monmodule.lib.php` — `prepare_head()` objet + admin
- `admin/setup.php` et `admin/about.php` — pages admin standard
- `monobjetcard.php` — fiche CRUD complete avec `tabsAction`
- `monobjetlist.php` — liste avec filtres, pagination, badges statut
- `ajax/monmodule.ajax.php` — handler AJAX complet (JSON in/out, permissions)
- `class/actions_monmodule.class.php` — handler de hooks (onglet, champs, colonne)
- `core/triggers/interface_99_modMonmodule_Trigger.class.php` — trigger non-bloquant
- `css/monmodule.css` — variables `:root`, pas de degrades
- `js/monmodule.js` — namespace JS + helper AJAX
- `index.php` dans chaque sous-dossier — protection repertoire (convention Dolibarr)

Pour creer un nouveau module, copier le dossier et remplacer `monmodule` / `MonModule` / `monobjet` / `MonObjet` par les noms de ton module (voir `templates/monmodule/README.md`).

## Structure du depot

```text
doliforge/
├── install.sh                     # Script d'installation multi-outils
├── README.md
├── .claude-plugin/                # Config plugin Claude Code
├── templates/
│   └── monmodule/                 # Squelette de module complet
│       ├── core/modules/
│       ├── class/
│       ├── sql/
│       ├── langs/
│       ├── lib/
│       ├── admin/
│       ├── css/
│       ├── js/
│       ├── monobjetcard.php
│       ├── monobjetlist.php
│       ├── index.php
│       ├── ajax/
│       ├── class/
│       └── core/triggers/
└── dolibarr/
    ├── .claude-plugin/
    ├── agents/
    │   ├── dolibarr-auditeur.md   # Agent d'audit
    │   ├── dolibarr-createur.md   # Agent de creation
    │   ├── dolibarr-optimiseur.md # Agent de performance
    │   └── dolibarr-migrateur.md  # Agent de refactoring/migration
    └── skills/
        └── dolibarr-module-dev/
            ├── SKILL.md           # Index principal du skill
            └── references/        # 19 fiches thematiques
```

## Compatibilite multi-outils

| Outil | Fichier configure | Methode |
| --- | --- | --- |
| **Claude Code** | `.claude/skills/` + `.claude/commands/` + `CLAUDE.md` | Skills + slash commands |
| **Cursor** | `.cursorrules` | Regles injectees |
| **Windsurf** | `.windsurfrules` | Regles injectees |
| **Cline** | `.clinerules` | Regles injectees |
| **RooCode** | `.roo/rules/dolibarr.md` | Regles injectees |
| **Codex** | `AGENTS.md` | Instructions agent |

L'installateur detecte automatiquement l'outil ou accepte un argument explicite.

## Mettre a jour

```bash
# Depuis n'importe quel projet configure
~/.doliforge/install.sh update
```

Les projets utilisant des symlinks sont **automatiquement a jour** apres
`update` — pas besoin de reinstaller.

## Desinstaller

```bash
# Retirer d'un projet
cd mon-projet
~/.doliforge/install.sh uninstall

# Supprimer completement DoliForge
rm -rf ~/.doliforge
```

## Licence

Usage interne DTS SARL. Adapter avant toute distribution publique.
