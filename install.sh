#!/usr/bin/env bash
# ============================================================================
# DoliForge — Forge IA pour le développement de modules Dolibarr
# DTS SARL (Dywants Technologie & Services)
# ============================================================================
set -euo pipefail

DOLIFORGE_VERSION="1.4.0"
DOLIFORGE_REPO="DTS-Sarl/doliforge"
DOLIFORGE_BRANCH="main"
DOLIFORGE_DIR="${HOME}/.doliforge"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m'

print_banner() {
    echo ""
    echo -e "${BLUE}${BOLD}"
    echo "  ██████╗  ██████╗ ██╗     ██╗███████╗ ██████╗ ██████╗  ██████╗ ███████╗"
    echo "  ██╔══██╗██╔═══██╗██║     ██║██╔════╝██╔═══██╗██╔══██╗██╔════╝ ██╔════╝"
    echo "  ██║  ██║██║   ██║██║     ██║█████╗  ██║   ██║██████╔╝██║  ███╗█████╗  "
    echo "  ██║  ██║██║   ██║██║     ██║██╔══╝  ██║   ██║██╔══██╗██║   ██║██╔══╝  "
    echo "  ██████╔╝╚██████╔╝███████╗██║██║     ╚██████╔╝██║  ██║╚██████╔╝███████╗"
    echo "  ╚═════╝  ╚═════╝ ╚══════╝╚═╝╚═╝      ╚═════╝ ╚═╝  ╚═╝ ╚═════╝ ╚══════╝"
    echo -e "${NC}"
    echo -e "  ${DIM}Forge IA pour le développement de modules Dolibarr${NC}"
    echo -e "  ${DIM}DTS SARL — v${DOLIFORGE_VERSION}${NC}"
    echo ""
}

log_info()    { echo -e "  ${GREEN}✓${NC}  $1"; }
log_warn()    { echo -e "  ${YELLOW}!${NC}  $1"; }
log_error()   { echo -e "  ${RED}✗${NC}  $1"; }
log_step()    { echo -e "\n  ${CYAN}›${NC}  ${BOLD}$1${NC}"; }

# ============================================================================
# Détection de l'outil AI + choix interactif
# ============================================================================

# Vrai si stdin est un terminal (bash <(curl ...)) — faux si pipe (curl | bash)
is_interactive() { [ -t 0 ]; }

detect_tool() {
    if command -v claude &> /dev/null; then
        echo "claude"
    elif [ -d ".cursor" ] || [ -f ".cursorrules" ]; then
        echo "cursor"
    elif [ -f ".windsurfrules" ] || [ -d ".windsurf" ]; then
        echo "windsurf"
    elif [ -f ".clinerules" ]; then
        echo "cline"
    elif [ -f "codex.yaml" ] || [ -f ".codex" ] || [ -f "AGENTS.md" ]; then
        echo "codex"
    else
        echo "claude"  # Défaut
    fi
}

select_tool() {
    # Affiche le menu et stocke le résultat dans la variable globale SELECTED_TOOL
    # Appelée directement (pas dans $()) pour que echo et read fonctionnent normalement
    local detected="${1:-claude}"

    local labels=("Claude Code" "Cursor" "Windsurf" "Cline" "Codex (OpenAI)" "Tous les outils")
    local values=("claude" "cursor" "windsurf" "cline" "codex" "all")
    local default_idx=0
    case "$detected" in
        cursor)   default_idx=1 ;;
        windsurf) default_idx=2 ;;
        cline)    default_idx=3 ;;
        codex)    default_idx=4 ;;
    esac

    echo ""
    echo -e "  ${BOLD}Quel outil AI utilises-tu ?${NC}"
    for i in "${!labels[@]}"; do
        if [ "$i" -eq "$default_idx" ]; then
            echo -e "  ${CYAN}›${NC} $((i+1))) ${labels[$i]}  ${DIM}(détecté)${NC}"
        else
            echo -e "     $((i+1))) ${labels[$i]}"
        fi
    done
    echo ""
    printf "  Numéro + Entrée [%d] : " "$((default_idx+1))"

    local choice
    read -r choice < /dev/tty   # Lire depuis le terminal, pas stdin
    choice="${choice:-$((default_idx+1))}"

    if [[ "$choice" =~ ^[1-6]$ ]]; then
        SELECTED_TOOL="${values[$((choice-1))]}"
    else
        SELECTED_TOOL="${values[$default_idx]}"
    fi
}

# ============================================================================
# Détection de la racine du projet
# ============================================================================
find_project_root() {
    # Remonter jusqu'à la racine git si disponible
    local git_root
    git_root=$(git rev-parse --show-toplevel 2>/dev/null) && echo "$git_root" && return
    # Sinon dossier courant
    pwd
}

# ============================================================================
# Télécharger ou mettre à jour DoliForge
# ============================================================================
install_doliforge() {
    log_step "Installation de DoliForge dans ${DOLIFORGE_DIR}"

    if [ -d "${DOLIFORGE_DIR}/.git" ]; then
        log_info "DoliForge déjà installé — mise à jour..."
        cd "${DOLIFORGE_DIR}"
        git pull --rebase origin "${DOLIFORGE_BRANCH}" --quiet 2>/dev/null || \
            log_warn "Impossible de pull — utilisation de la version locale"
        cd - > /dev/null
    elif [ -d "${DOLIFORGE_DIR}" ]; then
        log_warn "Dossier existant sans git — réinstallation..."
        rm -rf "${DOLIFORGE_DIR}"
        git clone --depth 1 --branch "${DOLIFORGE_BRANCH}" \
            "https://github.com/${DOLIFORGE_REPO}.git" "${DOLIFORGE_DIR}" --quiet 2>/dev/null || {
            install_from_local ; return
        }
    else
        git clone --depth 1 --branch "${DOLIFORGE_BRANCH}" \
            "https://github.com/${DOLIFORGE_REPO}.git" "${DOLIFORGE_DIR}" --quiet 2>/dev/null || {
            install_from_local ; return
        }
    fi

    log_info "DoliForge v${DOLIFORGE_VERSION} prêt dans ${DOLIFORGE_DIR}"
}

install_from_local() {
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [ -f "${SCRIPT_DIR}/dolibarr/skills/dolibarr-module-dev/SKILL.md" ]; then
        mkdir -p "${DOLIFORGE_DIR}"
        cp -R "${SCRIPT_DIR}/"* "${DOLIFORGE_DIR}/" 2>/dev/null || true
        cp -R "${SCRIPT_DIR}/."* "${DOLIFORGE_DIR}/" 2>/dev/null || true
        log_info "Installé depuis source locale"
    else
        log_error "Aucune source trouvée. Cloner manuellement :"
        echo ""
        echo "    git clone https://github.com/${DOLIFORGE_REPO}.git ${DOLIFORGE_DIR}"
        echo ""
        exit 1
    fi
}

# ============================================================================
# Configurer le projet courant selon l'outil choisi
# ============================================================================
setup_project() {
    local tool="${1:-claude}"
    local project_dir="${2:-.}"
    local project_name
    project_name="$(basename "$(cd "$project_dir" && pwd)")"

    log_step "Configuration de ${project_name} pour ${tool}"

    case "$tool" in
        claude)   setup_claude_code "$project_dir" ;;
        cursor)   setup_cursor "$project_dir" ;;
        windsurf) setup_windsurf "$project_dir" ;;
        cline)    setup_cline "$project_dir" ;;
        codex)    setup_codex "$project_dir" ;;
        all)
            setup_claude_code "$project_dir"
            setup_cursor "$project_dir"
            setup_windsurf "$project_dir"
            setup_cline "$project_dir"
            setup_codex "$project_dir"
            ;;
        *)
            log_error "Outil inconnu : ${tool}"
            exit 1
            ;;
    esac
}

# ---- Claude Code ----
setup_claude_code() {
    local project_dir="$1"

    mkdir -p "${project_dir}/.claude/skills"
    [ -L "${project_dir}/.claude/skills/dolibarr-module-dev" ] && \
        rm "${project_dir}/.claude/skills/dolibarr-module-dev"
    ln -sf "${DOLIFORGE_DIR}/dolibarr/skills/dolibarr-module-dev" \
           "${project_dir}/.claude/skills/dolibarr-module-dev"
    log_info "Skill dolibarr-module-dev installé (symlink)"

    mkdir -p "${project_dir}/.claude/commands"
    create_claude_commands "${project_dir}"
    log_info "Slash commands créées"

    inject_claude_md "${project_dir}"
    log_info "CLAUDE.md configuré"
}

create_claude_commands() {
    local project_dir="$1"
    local cmd_dir="${project_dir}/.claude/commands"

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

    cat > "${cmd_dir}/dolibarr-debug.md" << 'CMDEOF'
---
description: Diagnostiquer un probleme dans un module Dolibarr
---

Charge le skill `dolibarr-module-dev` et applique la methodologie de debug
decrite dans `references/debug.md`.

Procedure :
1. Identifier le symptome (erreur 500, 403, page blanche, trigger inactif, etc.)
2. Creer un point de debug visuel pour obtenir des donnees reelles
3. Lire la sortie et isoler la cause
4. Corriger chirurgicalement — ne toucher que le code identifie comme source du bug

Ne jamais avancer des theories sans donnees. Ne jamais modifier du code
qui fonctionnait. Ne jamais insinuer que le developpeur n'a pas deploye
ses fichiers ou que le navigateur bloque.

Argument optionnel : $ARGUMENTS (description du probleme)
CMDEOF

    cat > "${cmd_dir}/dolibarr-publish.md" << 'CMDEOF'
---
description: Preparer un module pour publication sur DoliStore
---

Charge le skill `dolibarr-module-dev` et execute la checklist de publication
decrite dans `references/dolistore-publication.md`.

Procedure :
1. Execute l'audit complet (4 passes : securite, compat, conventions, CSS/JS)
2. Verifie la checklist DoliStore point par point
3. Prepare le paquet ZIP avec le bon nommage
4. Liste les problemes bloquants restants

Argument optionnel : $ARGUMENTS (nom du module a publier)
CMDEOF

    cat > "${cmd_dir}/dolibarr-upgrade.md" << 'CMDEOF'
---
description: Migrer un module Dolibarr vers une version superieure
---

Charge le skill `dolibarr-module-dev` et guide la migration du module
vers une version Dolibarr cible.

Procedure :
1. Identifier la version source et la version cible
2. Verifier les API et methodes depreciees entre les deux versions
3. Adapter les requetes SQL si necessaire (migrations additives uniquement)
4. Verifier la compatibilite multi-entite et les hooks/triggers
5. Executer l'audit complet sur les fichiers modifies
6. Valider la checklist de publication DoliStore

References : `references/refactoring.md` + `references/base-de-donnees.md`

Argument optionnel : $ARGUMENTS (ex: "migrer de Dolibarr 18 vers 22")
CMDEOF
}

inject_claude_md() {
    local project_dir="$1"
    local claude_md="${project_dir}/CLAUDE.md"
    local marker="<!-- DOLIFORGE -->"

    if [ -f "$claude_md" ] && grep -q "$marker" "$claude_md" 2>/dev/null; then
        log_warn "CLAUDE.md contient déjà DoliForge — ignoré"
        return
    fi

    cat >> "$claude_md" << 'SECTIONEOF'

<!-- DOLIFORGE -->
## DoliForge — Skills Dolibarr

Ce projet utilise **DoliForge**, la forge IA de DTS SARL pour le développement
de modules Dolibarr. Les skills et fiches de référence sont chargés via
`.claude/skills/dolibarr-module-dev/`.

### Règles imposées par DoliForge

Quand tu travailles sur du code de module Dolibarr, **toujours** consulter
les fiches de référence appropriées du skill `dolibarr-module-dev` :

- Nouveau code → lire la fiche correspondante (structure, descripteur, objets, etc.)
- Correction de bug → lire `references/debug.md` pour la méthodologie
- Audit / review → lire `references/securite.md` + `references/conventions-code.md`
- Publication → lire `references/dolistore-publication.md`

### Commandes disponibles

| Commande | Usage |
|---|---|
| `/dolibarr-audit` | Auditer un module (sécurité, compatibilité, conventions) |
| `/dolibarr-create` | Créer un nouveau module depuis zéro |
| `/dolibarr-debug` | Diagnostiquer un problème |
| `/dolibarr-publish` | Préparer pour publication DoliStore |
| `/dolibarr-upgrade` | Migrer un module vers une version Dolibarr supérieure |
<!-- /DOLIFORGE -->
SECTIONEOF
}

# ---- Cursor ----
setup_cursor() {
    local project_dir="$1"
    local rules_file="${project_dir}/.cursorrules"

    if [ -f "$rules_file" ] && grep -q "# DOLIFORGE" "$rules_file" 2>/dev/null; then
        log_warn ".cursorrules contient déjà DoliForge — ignoré"
        return
    fi

    cat >> "$rules_file" << 'CURSOREOF'

# DOLIFORGE — Règles de développement Dolibarr
# Généré par DoliForge (DTS SARL) — ne pas modifier manuellement

Quand tu travailles sur un module Dolibarr, respecte ces règles :

## Sécurité obligatoire
- Toute entrée via GETPOST('nom', 'type') — jamais $_GET/$_POST
- Jeton CSRF newToken() sur tout formulaire POST
- Échappement SQL : $db->escape() pour chaînes, (int) pour entiers
- Permissions : hasRight() + restrictedArea() avant tout traitement
- Sortie : dol_escape_htmltag() sur contenu dynamique

## Structure
- Module dans htdocs/custom/<module>/
- Descripteur : core/modules/modXxx.class.php extends DolibarrModules
- Classes métier : extends CommonObject avec pattern $fields
- Pages : llxHeader()/llxFooter(), setEventMessages()
- SQL : MAIN_DB_PREFIX en PHP, llx_ dans fichiers .sql
- Entity obligatoire pour multi-société

## Conventions
- Globales : global $db, $conf, $user, $langs;
- Retours : > 0 succès, 0 neutre, < 0 erreur (jamais booléens)
- Helpers : dol_syslog(), dol_now(), dol_print_date(), dol_buildpath()
- Transactions : $db->begin() / commit() / rollback()
- Traductions : $langs->trans() — jamais texte en dur
- Indentation : tabulations (pas espaces)

## Pages AJAX
- Déclarer NOCSRFCHECK, NOTOKENRENEWAL, NOREQUIREMENU AVANT main.inc.php
- Toujours vérifier hasRight() même en AJAX
- Retourner JSON via top_httphead('application/json') + json_encode()

## Triggers
- Toujours if (!isModEnabled('monmodule')) return 0; en début
- try/catch non-bloquant, jamais re-throw
- Retourner 0 (non-bloquant) sauf cas critique

## Références détaillées
Les fiches complètes sont dans ~/.doliforge/dolibarr/skills/dolibarr-module-dev/references/
# /DOLIFORGE
CURSOREOF

    log_info ".cursorrules configuré pour Cursor"
}

# ---- Windsurf ----
setup_windsurf() {
    local project_dir="$1"
    local rules_file="${project_dir}/.windsurfrules"

    if [ -f "$rules_file" ] && grep -q "# DOLIFORGE" "$rules_file" 2>/dev/null; then
        log_warn ".windsurfrules contient déjà DoliForge — ignoré"
        return
    fi

    cat >> "$rules_file" << 'WINDSURFEOF'

# DOLIFORGE — Règles de développement Dolibarr
# Généré par DoliForge (DTS SARL) — ne pas modifier manuellement

Quand tu travailles sur un module Dolibarr, respecte ces règles :

## Sécurité obligatoire
- Toute entrée via GETPOST('nom', 'type') — jamais $_GET/$_POST
- Jeton CSRF newToken() sur tout formulaire POST
- Échappement SQL : $db->escape() pour chaînes, (int) pour entiers
- Permissions : hasRight() + restrictedArea() avant tout traitement
- Sortie : dol_escape_htmltag() sur contenu dynamique

## Structure
- Module dans htdocs/custom/<module>/
- Descripteur : core/modules/modXxx.class.php extends DolibarrModules
- Classes métier : extends CommonObject avec pattern $fields
- Pages : llxHeader()/llxFooter(), setEventMessages()
- SQL : MAIN_DB_PREFIX en PHP, llx_ dans fichiers .sql
- Entity obligatoire pour multi-société

## Conventions
- Globales : global $db, $conf, $user, $langs;
- Retours : > 0 succès, 0 neutre, < 0 erreur (jamais booléens)
- Helpers : dol_syslog(), dol_now(), dol_print_date(), dol_buildpath()
- Transactions : $db->begin() / commit() / rollback()
- Traductions : $langs->trans() — jamais texte en dur
- Indentation : tabulations (pas espaces)

## CSS/JS
- Jamais de dégradés CSS (linear-gradient interdit)
- Couleurs en variables :root --monmodule-*
- Pas de CDN — librairies dans js/vendor/
- JS dans namespace unique
- Continuité visuelle : hériter des styles natifs Dolibarr

## Références détaillées
Les fiches complètes sont dans ~/.doliforge/dolibarr/skills/dolibarr-module-dev/references/
# /DOLIFORGE
WINDSURFEOF

    log_info ".windsurfrules configuré pour Windsurf"
}

# ---- Cline ----
setup_cline() {
    local project_dir="$1"
    local rules_file="${project_dir}/.clinerules"

    if [ -f "$rules_file" ] && grep -q "# DOLIFORGE" "$rules_file" 2>/dev/null; then
        log_warn ".clinerules contient déjà DoliForge — ignoré"
        return
    fi

    cat >> "$rules_file" << 'CLINEEOF'

# DOLIFORGE — Règles de développement Dolibarr
# Généré par DoliForge (DTS SARL) — ne pas modifier manuellement

Quand tu travailles sur un module Dolibarr, respecte ces règles :

## Sécurité obligatoire
- Toute entrée via GETPOST('nom', 'type') — jamais $_GET/$_POST
- Jeton CSRF newToken() sur tout formulaire POST
- Échappement SQL : $db->escape() pour chaînes, (int) pour entiers
- Permissions : hasRight() + restrictedArea() avant tout traitement
- Sortie : dol_escape_htmltag() sur contenu dynamique

## Structure
- Module dans htdocs/custom/<module>/
- Descripteur : core/modules/modXxx.class.php extends DolibarrModules
- Classes métier : extends CommonObject avec pattern $fields
- Pages : llxHeader()/llxFooter(), setEventMessages()
- SQL : MAIN_DB_PREFIX en PHP, llx_ dans fichiers .sql
- Entity obligatoire pour multi-société

## Conventions
- Globales : global $db, $conf, $user, $langs;
- Retours : > 0 succès, 0 neutre, < 0 erreur (jamais booléens)
- Helpers : dol_syslog(), dol_now(), dol_print_date(), dol_buildpath()
- Transactions : $db->begin() / commit() / rollback()
- Traductions : $langs->trans() — jamais texte en dur
- Indentation : tabulations (pas espaces)

## CSS/JS
- Jamais de dégradés CSS (linear-gradient interdit)
- Couleurs en variables :root --monmodule-*
- Pas de CDN — librairies dans js/vendor/
- JS dans namespace unique
- Continuité visuelle : hériter des styles natifs Dolibarr

## Références détaillées
Les fiches complètes sont dans ~/.doliforge/dolibarr/skills/dolibarr-module-dev/references/
# /DOLIFORGE
CLINEEOF

    log_info ".clinerules configuré pour Cline"
}

# ---- Codex (OpenAI) ----
setup_codex() {
    local project_dir="$1"
    local codex_file="${project_dir}/AGENTS.md"

    if [ -f "$codex_file" ] && grep -q "<!-- DOLIFORGE -->" "$codex_file" 2>/dev/null; then
        log_warn "AGENTS.md contient déjà DoliForge — ignoré"
        return
    fi

    cat >> "$codex_file" << 'CODEXEOF'

<!-- DOLIFORGE -->
## DoliForge — Règles Dolibarr

Quand tu travailles sur un module Dolibarr :

1. **Sécurité** : GETPOST() avec filtre, newToken() CSRF, $db->escape(),
   hasRight()/restrictedArea(), dol_escape_htmltag()
2. **Structure** : htdocs/custom/, CommonObject + $fields, MAIN_DB_PREFIX,
   entity obligatoire, llxHeader()/llxFooter()
3. **Conventions** : global $db,$conf,$user,$langs; retours >0/0/<0;
   dol_syslog(); $langs->trans(); tabulations
4. **Triggers** : isModEnabled() en début, try/catch non-bloquant
5. **AJAX** : NOCSRFCHECK avant main.inc.php, hasRight() toujours

Références détaillées : ~/.doliforge/dolibarr/skills/dolibarr-module-dev/references/
<!-- /DOLIFORGE -->
CODEXEOF

    log_info "AGENTS.md configuré pour Codex"
}

# ============================================================================
# Désinstallation
# ============================================================================
uninstall_project() {
    local project_dir="${1:-.}"

    log_step "Désinstallation de DoliForge du projet"

    rm -f "${project_dir}/.claude/skills/dolibarr-module-dev"
    log_info "Symlink skill supprimé"

    rm -f "${project_dir}/.claude/commands/dolibarr-audit.md"
    rm -f "${project_dir}/.claude/commands/dolibarr-create.md"
    rm -f "${project_dir}/.claude/commands/dolibarr-debug.md"
    rm -f "${project_dir}/.claude/commands/dolibarr-publish.md"
    rm -f "${project_dir}/.claude/commands/dolibarr-upgrade.md"
    log_info "Slash commands supprimées"

    if [ -f "${project_dir}/CLAUDE.md" ]; then
        sed -i.bak '/<!-- DOLIFORGE -->/,/<!-- \/DOLIFORGE -->/d' "${project_dir}/CLAUDE.md"
        rm -f "${project_dir}/CLAUDE.md.bak"
        log_info "Section DoliForge retirée de CLAUDE.md"
    fi

    if [ -f "${project_dir}/.cursorrules" ]; then
        sed -i.bak '/# DOLIFORGE/,/# \/DOLIFORGE/d' "${project_dir}/.cursorrules"
        rm -f "${project_dir}/.cursorrules.bak"
        log_info "Section DoliForge retirée de .cursorrules"
    fi

    if [ -f "${project_dir}/.windsurfrules" ]; then
        sed -i.bak '/# DOLIFORGE/,/# \/DOLIFORGE/d' "${project_dir}/.windsurfrules"
        rm -f "${project_dir}/.windsurfrules.bak"
        log_info "Section DoliForge retirée de .windsurfrules"
    fi

    if [ -f "${project_dir}/.clinerules" ]; then
        sed -i.bak '/# DOLIFORGE/,/# \/DOLIFORGE/d' "${project_dir}/.clinerules"
        rm -f "${project_dir}/.clinerules.bak"
        log_info "Section DoliForge retirée de .clinerules"
    fi

    if [ -f "${project_dir}/AGENTS.md" ]; then
        sed -i.bak '/<!-- DOLIFORGE -->/,/<!-- \/DOLIFORGE -->/d' "${project_dir}/AGENTS.md"
        rm -f "${project_dir}/AGENTS.md.bak"
        log_info "Section DoliForge retirée de AGENTS.md"
    fi

    echo ""
    log_info "Désinstallation terminée"
}

# ============================================================================
# Mise à jour
# ============================================================================
update_doliforge() {
    log_step "Mise à jour de DoliForge depuis GitHub"

    if [ -d "${DOLIFORGE_DIR}/.git" ]; then
        cd "${DOLIFORGE_DIR}"
        git pull --rebase origin "${DOLIFORGE_BRANCH}" --quiet
        cd - > /dev/null
        log_info "DoliForge mis à jour"
    else
        log_warn "Non installé via git — réinstallation..."
        rm -rf "${DOLIFORGE_DIR}"
        install_doliforge
    fi

    echo ""
    log_info "Les projets utilisant des symlinks sont automatiquement à jour."
}

# ============================================================================
# Statut
# ============================================================================
show_status() {
    print_banner

    echo -e "  ${BOLD}Installation globale${NC}"
    if [ -d "${DOLIFORGE_DIR}" ]; then
        log_info "DoliForge installé dans ${DOLIFORGE_DIR}"
        if [ -d "${DOLIFORGE_DIR}/.git" ]; then
            local version
            version=$(cd "${DOLIFORGE_DIR}" && git log -1 --format="%h — %s" 2>/dev/null || echo "inconnu")
            echo -e "  ${DIM}    commit : ${version}${NC}"
        fi
    else
        log_error "DoliForge non installé"
    fi

    echo ""
    echo -e "  ${BOLD}Projet courant${NC} ${DIM}($(pwd))${NC}"

    if [ -L ".claude/skills/dolibarr-module-dev" ]; then
        log_info "Skill Claude Code    actif"
    else
        log_warn "Skill Claude Code    non configuré"
    fi

    if [ -f ".claude/commands/dolibarr-audit.md" ]; then
        log_info "Slash commands       installées"
    else
        log_warn "Slash commands       non installées"
    fi

    if [ -f "CLAUDE.md" ] && grep -q "DOLIFORGE" "CLAUDE.md" 2>/dev/null; then
        log_info "CLAUDE.md            configuré"
    else
        log_warn "CLAUDE.md            non configuré"
    fi

    if [ -f ".cursorrules" ] && grep -q "DOLIFORGE" ".cursorrules" 2>/dev/null; then
        log_info ".cursorrules         configuré"
    else
        echo -e "  ${DIM}—  .cursorrules         non configuré (optionnel)${NC}"
    fi

    if [ -f "AGENTS.md" ] && grep -q "DOLIFORGE" "AGENTS.md" 2>/dev/null; then
        log_info "AGENTS.md            configuré"
    else
        echo -e "  ${DIM}—  AGENTS.md            non configuré (optionnel)${NC}"
    fi

    echo ""
}

# ============================================================================
# Main
# ============================================================================
main() {
    local command="${1:-install}"

    case "$command" in
        install)
            print_banner
            install_doliforge

            # Racine du projet (git root ou dossier courant)
            local project_dir
            if [ -n "${3:-}" ]; then
                project_dir="$3"
            else
                project_dir=$(find_project_root)
            fi
            local project_name
            project_name="$(basename "$(cd "$project_dir" && pwd)")"
            if [ "$project_dir" != "$(pwd)" ]; then
                log_info "Racine du projet : ${project_dir}"
            fi

            # Choix de l'outil
            local tool
            if [ -n "${2:-}" ]; then
                tool="$2"
            elif is_interactive; then
                SELECTED_TOOL=""
                select_tool "$(detect_tool)"
                tool="$SELECTED_TOOL"
            else
                # Mode non-interactif (curl | bash) — auto-détection silencieuse
                tool=$(detect_tool)
                log_info "Outil détecté : ${tool}"
            fi

            setup_project "$tool" "$project_dir"

            echo ""
            echo -e "  ${GREEN}${BOLD}DoliForge installé !${NC}"
            echo ""
            echo -e "  ${DIM}Commandes disponibles dans ton outil AI :${NC}"
            echo -e "  ${CYAN}/dolibarr-audit${NC}    — Auditer un module"
            echo -e "  ${CYAN}/dolibarr-create${NC}   — Créer un module"
            echo -e "  ${CYAN}/dolibarr-debug${NC}    — Débugger un problème"
            echo -e "  ${CYAN}/dolibarr-publish${NC}  — Publier sur DoliStore"
            echo ""
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
            print_banner
            echo -e "  ${BOLD}Usage${NC} : bash <(curl -fsSL https://raw.githubusercontent.com/${DOLIFORGE_REPO}/${DOLIFORGE_BRANCH}/install.sh)"
            echo ""
            echo -e "  ${BOLD}Commandes avancées${NC} (depuis ~/.doliforge/install.sh) :"
            echo "  install [tool] [path]   Installer sans menu interactif"
            echo "                          tool: claude, cursor, codex, all"
            echo "  uninstall [path]        Désinstaller d'un projet"
            echo "  update                  Mettre à jour depuis GitHub"
            echo "  status                  État de l'installation"
            echo ""
            ;;
        *)
            log_error "Commande inconnue : ${command}"
            echo "  Utilise : ~/.doliforge/install.sh help"
            exit 1
            ;;
    esac
}

main "$@"