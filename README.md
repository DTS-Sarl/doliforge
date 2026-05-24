# DoliForge

La forge a modules Dolibarr de **DTS SARL** (Dywants Technologie & Services) :
skills, agents et fiches de reference pour developper et auditer des modules
Dolibarr 18-23 avec les outils de coding IA.

**Outils supportes** : Claude Code, Cursor, Codex (OpenAI), et tout outil
supportant les fichiers d'instructions projet.

## Installation rapide

### Depuis GitHub

```bash
# Telecharger et installer
curl -fsSL https://raw.githubusercontent.com/dts-sarl/doliforge/main/install.sh | bash -s install

# Ou cloner et installer
git clone https://github.com/dts-sarl/doliforge.git ~/.doliforge
cd mon-projet-dolibarr
~/.doliforge/install.sh install
```

### Depuis le repo local

```bash
cd /chemin/vers/doliforge
./install.sh install               # Claude Code (defaut)
./install.sh install cursor        # Cursor
./install.sh install codex         # Codex (OpenAI)
./install.sh install all           # Tous les outils
```

### Que fait l'installation ?

1. **Telecharge DoliForge** dans `~/.doliforge/` (ou copie depuis local)
2. **Cree un symlink** `.claude/skills/dolibarr-module-dev` vers les fiches
3. **Installe 4 slash commands** dans `.claude/commands/`
4. **Injecte une section** dans `CLAUDE.md` (ou `.cursorrules` / `AGENTS.md`)

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
./install.sh status

# Mettre a jour les skills depuis GitHub
./install.sh update

# Desinstaller d'un projet
./install.sh uninstall

# Installer sur un autre projet
./install.sh install claude /chemin/vers/autre-projet
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
