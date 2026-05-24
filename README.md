# DoliForge

La forge a modules Dolibarr de **DTS SARL** (Dywants Technologie & Services) :
skills, agents et fiches de reference pour developper et auditer des modules
Dolibarr 18-23 avec les outils de coding IA.

**Outils supportes** : Claude Code, Cursor, Codex (OpenAI), et tout outil
supportant les fichiers d'instructions projet.

## Installation rapide

Une seule commande, depuis n'importe quel projet Dolibarr :

```bash
# Claude Code (defaut)
curl -fsSL https://raw.githubusercontent.com/DTS-Sarl/doliforge/main/install.sh | bash -s install

# Cursor
curl -fsSL https://raw.githubusercontent.com/DTS-Sarl/doliforge/main/install.sh | bash -s install cursor

# Codex (OpenAI)
curl -fsSL https://raw.githubusercontent.com/DTS-Sarl/doliforge/main/install.sh | bash -s install codex

# Tous les outils d'un coup
curl -fsSL https://raw.githubusercontent.com/DTS-Sarl/doliforge/main/install.sh | bash -s install all
```

Le script :
1. **Telecharge DoliForge** dans `~/.doliforge/` (clone GitHub automatique)
2. **Configure le projet courant** (skills, commands, instructions)

Pas besoin de cloner manuellement. Pas besoin de telecharger quoi que ce soit.

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

12 fiches de reference couvrant tout le cycle de vie d'un module :

| Fiche | Sujet |
|---|---|
| `structure-module.md` | Arborescence, nommage, dossier `ajax/`, assets |
| `descripteur.md` | `modXxx.class.php`, droits, menus, tabs, cronjobs |
| `objets-metier.md` | `CommonObject`, `$fields`, CRUD, relations FK, validation |
| `base-de-donnees.md` | SQL, prefixe, `entity`, migrations, helpers SQL |
| `securite.md` | `GETPOST`, CSRF, SQL, permissions, pages AJAX, filtres |
| `hooks-et-triggers.md` | Hooks, triggers, extrafields, evenements courants |
| `pages-ui.md` | Ossature page, formulaires, CSS Dolibarr, PRG, confirmation |
| `conventions-code.md` | Helpers, retours, transactions, `.lang`, admin, fallbacks |
| `compatibilite-ecosysteme.md` | Multi-entite, dependances, cycle de test |
| `pieges.md` | Pieges courants, cache, WAF, `restrictedArea` |
| `dolistore-publication.md` | Checklist, ZIP, versionning |
| `debug.md` | Methodologie debug, logs, erreurs courantes, diagnostic |

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
│           └── references/        # 12 fiches thematiques
│               ├── structure-module.md
│               ├── descripteur.md
│               ├── objets-metier.md
│               ├── base-de-donnees.md
│               ├── securite.md
│               ├── hooks-et-triggers.md
│               ├── pages-ui.md
│               ├── conventions-code.md
│               ├── compatibilite-ecosysteme.md
│               ├── pieges.md
│               ├── dolistore-publication.md
│               └── debug.md
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
