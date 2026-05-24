#!/usr/bin/env bash
# ============================================================================
# DoliForge — Installateur de skills et agents Dolibarr pour AI Coding Tools
# DTS SARL (Dywants Technologie & Services)
# ============================================================================
set -euo pipefail

DOLIFORGE_VERSION="1.1.0"
DOLIFORGE_REPO="DTS-Sarl/doliforge"
DOLIFORGE_BRANCH="main"
DOLIFORGE_DIR="${HOME}/.doliforge"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_banner() {
    echo ""
    echo -e "${BLUE}╔══════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║${NC}  ${GREEN}DoliForge v${DOLIFORGE_VERSION}${NC}                                 ${BLUE}║${NC}"
    echo -e "${BLUE}║${NC}  Skills & agents Dolibarr pour AI Coding Tools   ${BLUE}║${NC}"
    echo -e "${BLUE}║${NC}  DTS SARL — dywants.com                          ${BLUE}║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════════════════╝${NC}"
    echo ""
}

log_info()    { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[!!]${NC} $1"; }
log_error()   { echo -e "${RED}[ERR]${NC} $1"; }
log_step()    { echo -e "${BLUE}[>>]${NC} $1"; }

# ============================================================================
# Etape 1 : Telecharger ou mettre a jour DoliForge
# ============================================================================
install_doliforge() {
    log_step "Installation de DoliForge dans ${DOLIFORGE_DIR}..."

    if [ -d "${DOLIFORGE_DIR}/.git" ]; then
        log_info "DoliForge deja installe, mise a jour..."
        cd "${DOLIFORGE_DIR}"
        git pull origin "${DOLIFORGE_BRANCH}" --quiet 2>/dev/null || {
            log_warn "Impossible de pull — utilisation de la version locale"
        }
        cd - > /dev/null
    elif [ -d "${DOLIFORGE_DIR}" ]; then
        log_warn "Dossier ${DOLIFORGE_DIR} existe sans git — reinstallation..."
        rm -rf "${DOLIFORGE_DIR}"
        git clone --depth 1 --branch "${DOLIFORGE_BRANCH}" \
            "https://github.com/${DOLIFORGE_REPO}.git" "${DOLIFORGE_DIR}" 2>/dev/null || {
            log_error "Clone GitHub echoue. Installation depuis source locale..."
            install_from_local
            return
        }
    else
        git clone --depth 1 --branch "${DOLIFORGE_BRANCH}" \
            "https://github.com/${DOLIFORGE_REPO}.git" "${DOLIFORGE_DIR}" 2>/dev/null || {
            log_error "Clone GitHub echoue. Installation depuis source locale..."
            install_from_local
            return
        }
    fi

    log_info "DoliForge v${DOLIFORGE_VERSION} installe dans ${DOLIFORGE_DIR}"
}

install_from_local() {
    # Fallback : copier depuis le repertoire courant (si execute depuis le repo)
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [ -f "${SCRIPT_DIR}/dolibarr/skills/dolibarr-module-dev/SKILL.md" ]; then
        mkdir -p "${DOLIFORGE_DIR}"
        cp -R "${SCRIPT_DIR}/"* "${DOLIFORGE_DIR}/" 2>/dev/null || true
        cp -R "${SCRIPT_DIR}/."* "${DOLIFORGE_DIR}/" 2>/dev/null || true
        log_info "Installe depuis source locale"
    else
        log_error "Aucune source trouvee. Cloner manuellement :"
        echo "  git clone https://github.com/${DOLIFORGE_REPO}.git ${DOLIFORGE_DIR}"
        exit 1
    fi
}

# ============================================================================
# Etape 2 : Detecter l'outil AI et configurer
# ============================================================================
detect_tool() {
    local tool=""

    if [ -n "${1:-}" ]; then
        tool="$1"
    elif command -v claude &> /dev/null; then
        tool="claude"
    elif [ -d ".cursor" ] || [ -f ".cursorrules" ]; then
        tool="cursor"
    elif [ -f "codex.yaml" ] || [ -f ".codex" ]; then
        tool="codex"
    else
        tool="claude"  # Defaut
    fi

    echo "$tool"
}

# ============================================================================
# Etape 3 : Installer les skills dans le projet courant
# ============================================================================
setup_project() {
    local tool="${1:-claude}"
    local project_dir="${2:-.}"

    log_step "Configuration du projet $(basename "$(cd "$project_dir" && pwd)") pour ${tool}..."

    case "$tool" in
        claude)
            setup_claude_code "$project_dir"
            ;;
        cursor)
            setup_cursor "$project_dir"
            ;;
        codex)
            setup_codex "$project_dir"
            ;;
        all)
            setup_claude_code "$project_dir"
            setup_cursor "$project_dir"
            setup_codex "$project_dir"
            ;;
        *)
            log_error "Outil inconnu : ${tool}. Utiliser: claude, cursor, codex, all"
            exit 1
            ;;
    esac
}

# ---- Claude Code ----
setup_claude_code() {
    local project_dir="$1"

    # 1. Skills
    mkdir -p "${project_dir}/.claude/skills"
    if [ -L "${project_dir}/.claude/skills/dolibarr-module-dev" ]; then
        rm "${project_dir}/.claude/skills/dolibarr-module-dev"
    fi
    ln -sf "${DOLIFORGE_DIR}/dolibarr/skills/dolibarr-module-dev" \
           "${project_dir}/.claude/skills/dolibarr-module-dev"
    log_info "Skill dolibarr-module-dev installe (symlink)"

    # 2. Slash commands
    mkdir -p "${project_dir}/.claude/commands"
    create_claude_commands "${project_dir}"
    log_info "Slash commands creees (/dolibarr-audit, /dolibarr-create, /dolibarr-debug)"

    # 3. CLAUDE.md — ajouter la section DoliForge si absente
    inject_claude_md "${project_dir}"
    log_info "CLAUDE.md configure"
}

create_claude_commands() {
    local project_dir="$1"
    local cmd_dir="${project_dir}/.claude/commands"

    # /dolibarr-audit — Auditer un module
    cat > "${cmd_dir}/dolibarr-audit.md" << 'CMDEOF'
---
description: Auditer un module Dolibarr (securite, compatibilite, conventions)
---

Charge le skill `dolibarr-module-dev` et execute un audit complet du module
dans le dossier courant ou du fichier/dossier specifie par l'utilisateur.

Deroule les 3 passes d'audit dans l'ordre :
1. **Securite** → charge `references/securite.md` et verifie chaque regle
2. **Compatibilite ecosysteme** → charge `references/compatibilite-ecosysteme.md`
3. **Qualite / conventions** → charge `references/conventions-code.md`

Verifie aussi `references/pieges.md`.

Pour chaque probleme trouve :
- Cite le fichier et la ligne
- Explique le risque concret
- Propose le correctif minimal

Argument optionnel : $ARGUMENTS (chemin du module ou fichier a auditer)
CMDEOF

    # /dolibarr-create — Creer un module
    cat > "${cmd_dir}/dolibarr-create.md" << 'CMDEOF'
---
description: Creer un nouveau module Dolibarr depuis zero
---

Charge le skill `dolibarr-module-dev` et guide la creation d'un nouveau module
Dolibarr en suivant le workflow de creation dans l'ordre :

1. Structure du module → `references/structure-module.md`
2. Descripteur → `references/descripteur.md`
3. Objets metier → `references/objets-metier.md`
4. Base de donnees → `references/base-de-donnees.md`
5. Pages UI → `references/pages-ui.md`
6. Integration (hooks/triggers) → `references/hooks-et-triggers.md`
7. Securite + conventions → `references/securite.md` + `references/conventions-code.md`

Demande a l'utilisateur le nom du module et les objets metier souhaites
avant de commencer.

Argument optionnel : $ARGUMENTS (nom du module a creer)
CMDEOF

    # /dolibarr-debug — Debugger un probleme
    cat > "${cmd_dir}/dolibarr-debug.md" << 'CMDEOF'
---
description: Diagnostiquer un probleme dans un module Dolibarr
---

Charge le skill `dolibarr-module-dev` et applique la methodologie de debug
decrite dans `references/debug.md`.

Procedure :
1. Identifier le symptome (erreur 500, 403, page blanche, trigger inactif, etc.)
2. Consulter les logs
3. Isoler la cause avec la checklist appropriee
4. Proposer le correctif

Ne jamais deviner — toujours verifier. Suivre la checklist de debug rapide :
logs → cache → permissions → CSRF → SQL → chemin → version.

Argument optionnel : $ARGUMENTS (description du probleme)
CMDEOF

    # /dolibarr-publish — Preparer pour DoliStore
    cat > "${cmd_dir}/dolibarr-publish.md" << 'CMDEOF'
---
description: Preparer un module pour publication sur DoliStore
---

Charge le skill `dolibarr-module-dev` et execute la checklist de publication
decrite dans `references/dolistore-publication.md`.

Procedure :
1. Execute l'audit complet (3 passes)
2. Verifie la checklist DoliStore point par point
3. Prepare le paquet ZIP avec le bon nommage
4. Liste les problemes bloquants restants

Argument optionnel : $ARGUMENTS (nom du module a publier)
CMDEOF
}

inject_claude_md() {
    local project_dir="$1"
    local claude_md="${project_dir}/CLAUDE.md"
    local marker="<!-- DOLIFORGE -->"

    # Verifier si deja injecte
    if [ -f "$claude_md" ] && grep -q "$marker" "$claude_md" 2>/dev/null; then
        log_warn "CLAUDE.md contient deja la section DoliForge — pas de modification"
        return
    fi

    # Creer ou appendre
    local section
    section=$(cat << 'SECTIONEOF'

<!-- DOLIFORGE -->
## DoliForge — Skills Dolibarr

Ce projet utilise **DoliForge**, la forge a modules Dolibarr de DTS SARL.
Les skills et fiches de reference sont charges automatiquement via
`.claude/skills/dolibarr-module-dev/`.

### Regles imposees par DoliForge

Quand tu travailles sur du code de module Dolibarr, **toujours** consulter
les fiches de reference appropriees du skill `dolibarr-module-dev` :

- Nouveau code → lire la fiche correspondante (structure, descripteur, objets, etc.)
- Correction de bug → lire `references/debug.md` pour la methodologie
- Audit / review → lire `references/securite.md` + `references/conventions-code.md`
- Publication → lire `references/dolistore-publication.md`

### Commandes disponibles

| Commande | Usage |
|---|---|
| `/dolibarr-audit` | Auditer un module (securite, compatibilite, conventions) |
| `/dolibarr-create` | Creer un nouveau module depuis zero |
| `/dolibarr-debug` | Diagnostiquer un probleme |
| `/dolibarr-publish` | Preparer pour publication DoliStore |
<!-- /DOLIFORGE -->
SECTIONEOF
    )

    if [ -f "$claude_md" ]; then
        echo "$section" >> "$claude_md"
    else
        echo "$section" > "$claude_md"
    fi
}

# ---- Cursor ----
setup_cursor() {
    local project_dir="$1"

    # .cursorrules — equivalent de CLAUDE.md pour Cursor
    local rules_file="${project_dir}/.cursorrules"
    local marker="# DOLIFORGE"

    if [ -f "$rules_file" ] && grep -q "$marker" "$rules_file" 2>/dev/null; then
        log_warn ".cursorrules contient deja DoliForge"
        return
    fi

    cat >> "$rules_file" << 'CURSOREOF'

# DOLIFORGE — Regles de developpement Dolibarr
# Genere par DoliForge (DTS SARL) — ne pas modifier manuellement

Quand tu travailles sur un module Dolibarr, respecte ces regles :

## Securite obligatoire
- Toute entree via GETPOST('nom', 'type') — jamais $_GET/$_POST
- Jeton CSRF newToken() sur tout formulaire POST
- Echappement SQL : $db->escape() pour chaines, (int) pour entiers
- Permissions : hasRight() + restrictedArea() avant tout traitement
- Sortie : dol_escape_htmltag() sur contenu dynamique

## Structure
- Module dans htdocs/custom/<module>/
- Descripteur : core/modules/modXxx.class.php extends DolibarrModules
- Classes metier : extends CommonObject avec pattern $fields
- Pages : llxHeader()/llxFooter(), setEventMessages()
- SQL : MAIN_DB_PREFIX en PHP, llx_ dans fichiers .sql
- Entity obligatoire pour multi-societe

## Conventions
- Globales : global $db, $conf, $user, $langs;
- Retours : > 0 succes, 0 neutre, < 0 erreur (jamais booleens)
- Helpers : dol_syslog(), dol_now(), dol_print_date(), dol_buildpath()
- Transactions : $db->begin() / commit() / rollback()
- Traductions : $langs->trans() — jamais texte en dur
- Indentation : tabulations (pas espaces)

## Pages AJAX
- Declarer NOCSRFCHECK, NOTOKENRENEWAL, NOREQUIREMENU AVANT main.inc.php
- Toujours verifier hasRight() meme en AJAX
- Retourner JSON via top_httphead('application/json') + json_encode()

## Triggers
- Toujours if (!isModEnabled('monmodule')) return 0; en debut
- try/catch non-bloquant, jamais re-throw
- Retourner 0 (non-bloquant) sauf cas critique

## References detaillees
Les fiches completes sont dans ~/.doliforge/dolibarr/skills/dolibarr-module-dev/references/
# /DOLIFORGE
CURSOREOF

    log_info ".cursorrules configure pour Cursor"
}

# ---- Codex (OpenAI) ----
setup_codex() {
    local project_dir="$1"

    local codex_file="${project_dir}/AGENTS.md"
    local marker="<!-- DOLIFORGE -->"

    if [ -f "$codex_file" ] && grep -q "$marker" "$codex_file" 2>/dev/null; then
        log_warn "AGENTS.md contient deja DoliForge"
        return
    fi

    # Codex utilise AGENTS.md comme instructions
    cat >> "$codex_file" << 'CODEXEOF'

<!-- DOLIFORGE -->
## DoliForge — Regles Dolibarr

Quand tu travailles sur un module Dolibarr :

1. **Securite** : GETPOST() avec filtre, newToken() CSRF, $db->escape(),
   hasRight()/restrictedArea(), dol_escape_htmltag()
2. **Structure** : htdocs/custom/, CommonObject + $fields, MAIN_DB_PREFIX,
   entity obligatoire, llxHeader()/llxFooter()
3. **Conventions** : global $db,$conf,$user,$langs; retours >0/0/<0;
   dol_syslog(); $langs->trans(); tabulations
4. **Triggers** : isModEnabled() en debut, try/catch non-bloquant
5. **AJAX** : NOCSRFCHECK avant main.inc.php, hasRight() toujours

References detaillees : ~/.doliforge/dolibarr/skills/dolibarr-module-dev/references/
<!-- /DOLIFORGE -->
CODEXEOF

    log_info "AGENTS.md configure pour Codex"
}

# ============================================================================
# Etape 4 : Desinstallation
# ============================================================================
uninstall_project() {
    local project_dir="${1:-.}"

    log_step "Desinstallation de DoliForge du projet..."

    # Supprimer le symlink skill
    rm -f "${project_dir}/.claude/skills/dolibarr-module-dev"
    log_info "Skill symlink supprime"

    # Supprimer les commands
    rm -f "${project_dir}/.claude/commands/dolibarr-audit.md"
    rm -f "${project_dir}/.claude/commands/dolibarr-create.md"
    rm -f "${project_dir}/.claude/commands/dolibarr-debug.md"
    rm -f "${project_dir}/.claude/commands/dolibarr-publish.md"
    log_info "Slash commands supprimees"

    # Nettoyer CLAUDE.md
    if [ -f "${project_dir}/CLAUDE.md" ]; then
        sed -i.bak '/<!-- DOLIFORGE -->/,/<!-- \/DOLIFORGE -->/d' "${project_dir}/CLAUDE.md"
        rm -f "${project_dir}/CLAUDE.md.bak"
        log_info "Section DoliForge retiree de CLAUDE.md"
    fi

    # Nettoyer .cursorrules
    if [ -f "${project_dir}/.cursorrules" ]; then
        sed -i.bak '/# DOLIFORGE/,/# \/DOLIFORGE/d' "${project_dir}/.cursorrules"
        rm -f "${project_dir}/.cursorrules.bak"
        log_info "Section DoliForge retiree de .cursorrules"
    fi

    # Nettoyer AGENTS.md
    if [ -f "${project_dir}/AGENTS.md" ]; then
        sed -i.bak '/<!-- DOLIFORGE -->/,/<!-- \/DOLIFORGE -->/d' "${project_dir}/AGENTS.md"
        rm -f "${project_dir}/AGENTS.md.bak"
        log_info "Section DoliForge retiree de AGENTS.md"
    fi

    log_info "Desinstallation terminee"
}

# ============================================================================
# Etape 5 : Mise a jour
# ============================================================================
update_doliforge() {
    log_step "Mise a jour de DoliForge..."

    if [ -d "${DOLIFORGE_DIR}/.git" ]; then
        cd "${DOLIFORGE_DIR}"
        git pull origin "${DOLIFORGE_BRANCH}" --quiet
        cd - > /dev/null
        log_info "DoliForge mis a jour depuis GitHub"
    else
        log_warn "DoliForge non installe via git — reinstallation..."
        rm -rf "${DOLIFORGE_DIR}"
        install_doliforge
    fi

    echo ""
    log_info "Les projets utilisant des symlinks sont automatiquement a jour."
}

# ============================================================================
# Aide
# ============================================================================
show_help() {
    print_banner
    echo "Usage: $(basename "$0") <commande> [options]"
    echo ""
    echo "Commandes :"
    echo "  install [tool] [path]   Installer DoliForge + configurer un projet"
    echo "                          tool: claude (defaut), cursor, codex, all"
    echo "                          path: chemin du projet (defaut: .)"
    echo "  uninstall [path]        Desinstaller DoliForge d'un projet"
    echo "  update                  Mettre a jour DoliForge depuis GitHub"
    echo "  status                  Afficher l'etat de l'installation"
    echo "  help                    Afficher cette aide"
    echo ""
    echo "Exemples :"
    echo "  $(basename "$0") install                    # Claude Code, projet courant"
    echo "  $(basename "$0") install cursor             # Cursor, projet courant"
    echo "  $(basename "$0") install all ./mon-module   # Tous les outils, chemin specifie"
    echo "  $(basename "$0") update                     # Mettre a jour les skills"
    echo "  $(basename "$0") uninstall                  # Retirer du projet courant"
    echo ""
    echo "Apres installation, commandes disponibles dans Claude Code :"
    echo "  /dolibarr-audit      Auditer un module (securite, compat, conventions)"
    echo "  /dolibarr-create     Creer un nouveau module Dolibarr"
    echo "  /dolibarr-debug      Diagnostiquer un probleme"
    echo "  /dolibarr-publish    Preparer pour publication DoliStore"
}

show_status() {
    print_banner

    echo "Installation globale :"
    if [ -d "${DOLIFORGE_DIR}" ]; then
        log_info "DoliForge installe dans ${DOLIFORGE_DIR}"
        if [ -d "${DOLIFORGE_DIR}/.git" ]; then
            local version
            version=$(cd "${DOLIFORGE_DIR}" && git log -1 --format="%h %s" 2>/dev/null || echo "inconnu")
            echo "    Dernier commit : ${version}"
        fi
    else
        log_error "DoliForge non installe"
    fi

    echo ""
    echo "Projet courant ($(pwd)) :"

    if [ -L ".claude/skills/dolibarr-module-dev" ]; then
        log_info "Skill Claude Code : actif"
    else
        log_warn "Skill Claude Code : non configure"
    fi

    if [ -f ".claude/commands/dolibarr-audit.md" ]; then
        log_info "Slash commands  : installees"
    else
        log_warn "Slash commands  : non installees"
    fi

    if [ -f "CLAUDE.md" ] && grep -q "DOLIFORGE" "CLAUDE.md" 2>/dev/null; then
        log_info "CLAUDE.md       : configure"
    else
        log_warn "CLAUDE.md       : non configure"
    fi

    if [ -f ".cursorrules" ] && grep -q "DOLIFORGE" ".cursorrules" 2>/dev/null; then
        log_info ".cursorrules    : configure"
    else
        echo "    .cursorrules    : non configure (optionnel)"
    fi

    if [ -f "AGENTS.md" ] && grep -q "DOLIFORGE" "AGENTS.md" 2>/dev/null; then
        log_info "AGENTS.md       : configure"
    else
        echo "    AGENTS.md       : non configure (optionnel)"
    fi
}

# ============================================================================
# Main
# ============================================================================
main() {
    local command="${1:-help}"

    case "$command" in
        install)
            print_banner
            install_doliforge
            local tool
            tool=$(detect_tool "${2:-}")
            setup_project "$tool" "${3:-.}"
            echo ""
            log_info "Installation terminee !"
            echo ""
            echo "Commandes disponibles :"
            echo "  /dolibarr-audit    — Auditer un module"
            echo "  /dolibarr-create   — Creer un module"
            echo "  /dolibarr-debug    — Debugger un probleme"
            echo "  /dolibarr-publish  — Publier sur DoliStore"
            ;;
        uninstall)
            print_banner
            uninstall_project "${2:-.}"
            ;;
        update)
            print_banner
            update_doliforge
            ;;
        status)
            show_status
            ;;
        help|--help|-h)
            show_help
            ;;
        *)
            log_error "Commande inconnue : ${command}"
            show_help
            exit 1
            ;;
    esac
}

main "$@"
