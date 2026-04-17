# ECS — Ele Custom Skin for Elementor

**Version:** 4.0.0  
**Requires WordPress:** 6.0+  
**Requires PHP:** 8.0+  
**Elementor tested up to:** 3.35 (free + Pro)  
**License:** GPLv3

A modular toolkit that extends Elementor with custom loop skins, color schemes, container layouts, mobile menus, and more. Each feature ships as an independent module that can be toggled from the ECS admin panel.

---

## Modules

### Color Scheme
Replaces Elementor's Global Colors with a two-tier color system:

- **Default Colors** tab in Site Settings — per-color Default + Dark Mode pickers
- CSS variables injected under `[data-dte-scheme="alt"]` for dark mode without JS
- Mode switcher (sun / adjust icon) inside the tab to preview both palettes
- Fallback swatch shows the Default color when Dark Mode field is empty

### Container Layout
Extends Elementor Containers with three extra layout modes (desktop + tablet + mobile):

| Mode | Description |
|------|-------------|
| **Flex** | Standard flex container (mirrors Elementor's own flexbox) |
| **Grid** | CSS Grid with column/row controls |
| **Slider** | Swiper-powered horizontal carousel with arrows, pagination, autoplay |
| **Custom Layout** | Distributes container children into slot placeholders defined in an ECS Custom Layout template |

#### ECS Custom Layout document type
A new Elementor template type (`ecs_custom_layout`) that appears in Theme Builder (when Pro is active) or the Template Library. Build a layout with **ECS Container Placeholder** widgets as named slots; children are injected at render time via a cycling do-while loop.

#### Editor preview
The editor JS (`ecs-container-layout-editor.js`) detects `.e-dte-custom` containers in the preview iframe, sends children HTML via AJAX, and DOM-injects the server-rendered template back — keeping all children editable and Backbone bindings intact.

### Loop Custom Layout
Replaces the standard Loop Grid item output with a fully custom Elementor template:

- Adds **Use Custom Layout** + **Layout Template** controls to Loop Grid widgets
- Frontend: `elementor/frontend/the_content` filter replaces loop output with template-rendered HTML, cycling through all loop items
- **Editor preview**: JS detects Loop Grid widgets with `ecs_use_custom_layout=yes`, POSTs item HTML to an AJAX handler, and displays the server-rendered template in a `.ecs-loop-injection` wrapper while preserving the original items hidden in `.ecs-loop-original`
- "Edit Template" document-handle button is re-attached and repositioned to the first injected loop item
- In-place editing mode (clicking Edit Template) automatically switches back to the original loop view, then restores injection on exit

### Editorial Text
A rich editorial typography widget with:

- Multi-style text blocks (display, title, lead, body, caption, label presets)
- Per-block font family, size, weight, line-height, letter-spacing controls
- Inline color control with conditional display rules
- Image support with independent sizing

### Mobile Menu
A dedicated responsive navigation widget:

- Hamburger toggle with animated icon (3-bar ↔ X)
- Off-canvas / dropdown panel modes
- Full Elementor Template support for menu content
- Breakpoint-aware show/hide with CSS custom property integration

### Style Templates
Reusable style presets saved at the site level and applied to widgets via a single dropdown:

- Covers typography (font family, size, weight, line-height, letter-spacing, color)
- One-click sync — changing the template updates every widget using it
- Works in the Elementor editor via AJAX without a full page reload

### Legacy
Backward-compatibility layer for sites using ECS 3.x loop templates. Activated automatically on update when existing loop templates are detected; disabled on fresh installs.

---

## Installation

1. Upload the `ele-custom-skin` folder to `/wp-content/plugins/`
2. Activate **ECS — Ele Custom Skin for Elementor** in the WordPress plugin screen
3. Navigate to **Elementor → ECS Modules** to enable/disable individual modules

---

## Architecture

```
ele-custom-skin/
├── ele-custom-skin.php          # Plugin bootstrap, constants, init hook
├── includes/
│   ├── class-ecs-core.php       # Singleton core: loads managers
│   ├── class-ecs-module-base.php  # Abstract base for all modules
│   ├── class-ecs-modules-manager.php  # Registers, activates, boots modules
│   ├── dynamic-style.php        # Runtime CSS generation helpers
│   ├── ajax-pagination.php      # Frontend AJAX pagination for Loop Grid
│   └── ...
├── admin/                       # Admin UI (modules toggle page)
├── modules/
│   ├── color-scheme/
│   ├── container-layout/
│   ├── editorial-text/
│   ├── legacy/
│   ├── loop-custom-layout/
│   ├── loop-item/
│   ├── mobile-menu/
│   └── style-templates/
├── skins/                       # Elementor widget skins (legacy)
├── theme-builder/               # Theme Builder document types
└── tests/                       # PHP unit/integration tests
```

### Module lifecycle

Each module extends `ECS_Module_Base` and exposes:

| Method | Description |
|--------|-------------|
| `get_id()` | Unique string ID stored in `ecs_active_modules` option |
| `get_name()` | Display name shown in admin |
| `boot()` | Called once on activation — registers hooks |
| `register_widgets()` | Hooked into `elementor/widgets/register` |
| `register_controls()` | Hooked into `elementor/controls/register` |
| `enqueue_frontend_assets()` | Hooked into `wp_enqueue_scripts` |
| `enqueue_editor_assets()` | Hooked into `elementor/editor/after_enqueue_scripts` |

Active module IDs are stored in the `ecs_active_modules` WordPress option as a PHP array.

---

## Development

### Requirements
- PHP 8.0+
- WordPress 6.0+
- Elementor (free) 3.20+
- Elementor Pro 3.20+ *(optional — required by Loop Custom Layout, Theme Builder integration)*

### Running PHP tests
```bash
cd ele-custom-skin
php tests/runner.php
```

### JavaScript
Editor JS files are plain ES5 IIFEs (no build step required). Assets live in each module's `assets/js/` and `assets/css/` directories.

---

## Changelog

### 4.0.0
- Complete rewrite as a modular architecture (replaces ECS 3.x monolithic plugin)
- New modules: Color Scheme (dark mode), Container Layout (slider + custom layout), Loop Custom Layout, Style Templates
- Editor preview JS for Container Layout and Loop Custom Layout
- Elementor 4.x compatibility

---

## License

GPLv3 — see [LICENSE](http://www.gnu.org/licenses/gpl-3.0) for details.  
Copyright © Dudaster.com
