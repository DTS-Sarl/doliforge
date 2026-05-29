# CSS et JavaScript dans les modules Dolibarr

Bonnes pratiques pour des styles cohérents, maintenables et sans duplication.

## RÈGLE ABSOLUE — jamais de dégradés CSS

Il est **INTERDIT** d'utiliser des dégradés CSS dans un module Dolibarr :

```css
/* INTERDIT */
background: linear-gradient(135deg, #3498db, #2980b9);
background: -webkit-gradient(linear, left top, right bottom, ...);
background: radial-gradient(...);
```

Utiliser **uniquement des couleurs plates** (solides, unies) :

```css
/* CORRECT */
background: #3498db;
background-color: #2980b9;
```

Les dégradés donnent un rendu inconsistant entre navigateurs, vieillissent mal et
ne s'intègrent pas proprement dans l'interface Dolibarr.

---

## Structure des fichiers CSS/JS

Un module = **un fichier CSS** et **un fichier JS** :

```
monmodule/
├── css/
│   └── monmodule.css      ← tout le CSS du module ici
└── js/
    └── monmodule.js       ← tout le JS du module ici
```

Ne pas créer un fichier CSS ou JS par page. Tout centraliser.

### Inclusion dans les pages

```php
// En tête de page, avant llxHeader()
$arrayofcss = [dol_buildpath('/monmodule/css/monmodule.css', 1).'?v='.MONMODULE_VERSION];
$arrayofjs  = [dol_buildpath('/monmodule/js/monmodule.js',  1).'?v='.MONMODULE_VERSION];

llxHeader('', $langs->trans('MonTitre'), '', '', 0, 0, $arrayofjs, $arrayofcss);
```

Le `?v=` force le rechargement après une mise à jour — ne jamais oublier.

### Jamais de CDN

Toutes les librairies JS (jQuery plugins, charts, etc.) doivent être **vendorisées
localement** dans `js/vendor/`. Un module commercialisé doit fonctionner sans
accès internet :

```
js/
├── monmodule.js
└── vendor/
    ├── chart.min.js
    └── flatpickr.min.js
```

---

## Variables CSS — un seul endroit

Définir toutes les couleurs et valeurs réutilisables en variables CSS au début
du fichier `monmodule.css`, dans `:root` :

```css
/* ============================================================
   Variables — monmodule
   ============================================================ */
:root {
    /* Couleurs principales */
    --monmodule-primary:      #3c5a9a;
    --monmodule-primary-dark: #2d4478;
    --monmodule-secondary:    #e8442d;
    --monmodule-accent:       #f39c12;

    /* Couleurs d'état */
    --monmodule-success:  #27ae60;
    --monmodule-warning:  #e67e22;
    --monmodule-danger:   #e74c3c;
    --monmodule-info:     #2980b9;

    /* Neutres */
    --monmodule-bg:          #f5f6fa;
    --monmodule-bg-card:     #ffffff;
    --monmodule-border:      #dde1e7;
    --monmodule-text:        #2c3e50;
    --monmodule-text-light:  #7f8c8d;

    /* Espacements */
    --monmodule-radius: 6px;
    --monmodule-shadow: 0 2px 4px rgba(0,0,0,0.08);
}
```

**Ne jamais coder une couleur en dur dans le reste du fichier** — toujours utiliser
les variables. Si une couleur revient deux fois, elle doit être une variable.

```css
/* INTERDIT — couleur en dur répétée */
.mon-btn        { background: #3c5a9a; }
.mon-header     { background: #3c5a9a; }

/* CORRECT — variable réutilisée */
.mon-btn        { background: var(--monmodule-primary); }
.mon-header     { background: var(--monmodule-primary); }
```

---

## Cohérence des styles entre pages

Toutes les pages du module doivent utiliser les mêmes composants visuels.
Ne pas inventer un style de bouton ou de carte différent par page.

Définir une fois les composants réutilisables :

```css
/* Carte */
.monmodule-card {
    background: var(--monmodule-bg-card);
    border: 1px solid var(--monmodule-border);
    border-radius: var(--monmodule-radius);
    padding: 16px;
    box-shadow: var(--monmodule-shadow);
}

/* Badge de statut */
.monmodule-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.monmodule-badge-active  { background: var(--monmodule-success); color: #fff; }
.monmodule-badge-pending { background: var(--monmodule-warning); color: #fff; }
.monmodule-badge-closed  { background: var(--monmodule-text-light); color: #fff; }
```

Utiliser ces classes sur toutes les pages — jamais recréer localement.

---

## Organisation du fichier CSS

Structurer le fichier en sections commentées dans un ordre logique :

```css
/* 1. Variables                  */
/* 2. Reset / base               */
/* 3. Layout (grilles, conteneurs) */
/* 4. Composants (cartes, badges, boutons) */
/* 5. Pages spécifiques          */
/* 6. Responsive                 */
/* 7. Utilitaires                */
```

---

## JavaScript — bonnes pratiques

### Un seul namespace global

Encapsuler tout le JS dans un objet unique pour éviter les conflits avec Dolibarr
ou d'autres modules :

```javascript
var MonModule = MonModule || {};

MonModule.init = function() {
    MonModule.bindEvents();
};

MonModule.bindEvents = function() {
    $(document).on('click', '.monmodule-btn-action', MonModule.handleAction);
};

MonModule.handleAction = function(e) {
    e.preventDefault();
    // ...
};

// Initialisation au chargement
$(document).ready(function() {
    MonModule.init();
});
```

Ne jamais définir des fonctions au niveau global (`function maFonction() {}`).

### AJAX vers les handlers du module

```javascript
MonModule.callAjax = function(action, data, callback) {
    $.ajax({
        url:  monmodule_ajax_url,  // Variable PHP injectée dans la page
        type: 'POST',
        data: Object.assign({ action: action, token: monmodule_token }, data),
        success: function(response) {
            if (response.success) {
                callback(null, response.data);
            } else {
                callback(response.error || 'Erreur inconnue');
            }
        },
        error: function(xhr) {
            callback('Erreur HTTP ' + xhr.status);
        }
    });
};
```

Injecter l'URL et le token depuis PHP :

```php
// Dans la page PHP, avant llxHeader()
print '<script>';
print 'var monmodule_ajax_url = "'.dol_buildpath('/monmodule/ajax/monmodule.ajax.php', 1).'";';
print 'var monmodule_token = "'.newToken().'";';
print '</script>';
```

### Jamais de `console.log` en production

Les `console.log` de debug doivent être retirés avant livraison. Utiliser un flag :

```javascript
MonModule.debug = false;  // Passer à true en développement

MonModule.log = function(msg) {
    if (MonModule.debug) console.log('[MonModule]', msg);
};
```

---

## Checklist CSS/JS avant livraison

- [ ] Aucun dégradé CSS (`linear-gradient`, `radial-gradient`)
- [ ] Toutes les couleurs définies en variables `:root`
- [ ] Aucune couleur codée en dur hors des variables
- [ ] Un seul fichier CSS, un seul fichier JS
- [ ] Assets versionnés avec `?v=MONMODULE_VERSION`
- [ ] Aucun CDN — toutes les librairies en `js/vendor/`
- [ ] JS encapsulé dans un namespace unique
- [ ] Aucun `console.log` laissé en production
- [ ] Styles cohérents entre toutes les pages du module