# DoliForge

La forge IA de **DTS SARL** (Dywants Technologie & Services) :
skills, agents et fiches de reference pour developper et auditer des modules
Dolibarr 18-23 avec Claude Code, Cursor, Codex ou tout outil de coding IA.

**Outils supportes** : Claude Code, Cursor, Codex (OpenAI), et tout outil
supportant les fichiers d'instructions projet.

## Installation

Depuis n'importe quel projet Dolibarr, une seule commande :

```bash
curl -fsSL https://raw.githubusercontent.com/DTS-Sarl/doliforge/main/install.sh | bash
```

Le script :
1. **Telecharge DoliForge** dans `~/.doliforge/` (clone GitHub automatique)
2. **Detecte automatiquement** l'outil AI utilise (Claude Code, Cursor, Codex)
3. **Configure le projet courant** en consequence

Pas besoin de cloner manuellement. Pas besoin de passer d'arguments.

### Que fait l'installation ?

| Outil | Fichiers configures |
|---|---|
| **Claude Code** | `.claude/skills/` (symlink) + `.claude/commands/` (4 commandes) + `CLAUDE.md` |
| **Cursor** | `.cursorrules` (regles Dolibarr injectees) |
| **Codex** | `AGENTS.md` (instructions agent injectees) |

## Commandes disponibles

Apres installation, dans Claude Code :

| Commande | Usage |
|---|---|
| `/dolibarr-audit` | Auditer un module (securite, compatibilite, conventions) |
| `/dolibarr-create` | Creer un nouveau module Dolibarr depuis zero |
| `/dolibarr-debug` | Diagnostiquer un probleme (erreur 500, trigger inactif, etc.) |
| `/dolibarr-publish` | Preparer un module pour publication DoliStore |

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
|---|---|
| `structure-module.md` | Arborescence, nommage, dossier `ajax/`, assets |
| `descripteur.md` | `modXxx.class.php`, droits, menus, tabs, cronjobs |
| `objets-metier.md` | `CommonObject`, `$fields`, CRUD, relations FK, validation |
| `base-de-donnees.md` | SQL, prefixe, `entity`, migrations, helpers SQL |
| `securite.md` | `GETPOST`, CSRF, SQL, permissions, pages AJAX, filtres |
| `hooks-et-triggers.md` | Hooks, triggers, extrafields, evenements courants |
| `pages-ui.md` | Ossature page, formulaires, CSS Dolibarr, PRG, confirmation |
| `css-js.md` | Variables CSS, pas de degrades, coherence inter-pages, namespace JS |
| `conventions-code.md` | Helpers, retours, transactions, `.lang`, admin, fallbacks |
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

### Agent `dolibarr-auditeur`

Agent specialise pour auditer du code de module Dolibarr (securite,
compatibilite, conventions).

## Structure du depot

```text
doliforge/
├── install.sh                     # Script d'installation multi-outils
├── README.md
├── .claude-plugin/                # Config plugin Claude Code
├── dolibarr/
│   ├── .claude-plugin/
│   ├── agents/
│   │   └── dolibarr-auditeur.md   # Agent d'audit
│   └── skills/
│       └── dolibarr-module-dev/
│           ├── SKILL.md           # Index principal du skill
│           └── references/        # 19 fiches thematiques
│               ├── structure-module.md
│               ├── descripteur.md
│               ├── objets-metier.md
│               ├── base-de-donnees.md
│               ├── securite.md
│               ├── hooks-et-triggers.md
│               ├── pages-ui.md
│               ├── css-js.md
│               ├── conventions-code.md
│               ├── internationalisation.md
│               ├── compatibilite-ecosysteme.md
│               ├── pieges.md
│               ├── debug.md
│               ├── refactoring.md
│               ├── performance.md
│               ├── tests.md
│               ├── api-rest.md
│               ├── versioning-changelog.md
│               └── dolistore-publication.md
```

## Compatibilite multi-outils

| Outil | Fichier configure | Methode |
|---|---|---|
| **Claude Code** | `.claude/skills/` + `.claude/commands/` + `CLAUDE.md` | Skills + slash commands |
| **Cursor** | `.cursorrules` | Regles injectees |
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
