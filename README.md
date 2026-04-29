# ECS — Ele Custom Skin for Elementor

**Version:** 4.1.0 · [WordPress.org](https://wordpress.org/plugins/ele-custom-skin/) · [Changelog](https://wordpress.org/plugins/ele-custom-skin/#developers)

Seven free modules that extend Elementor where it stops. Enable only what you need from **ECS → Modules** — unused modules load zero CSS, zero JS, and register no hooks.

---

## Modules

| Module | Description |
|--------|-------------|
| **Dark Mode Colours** | Default + Dark Mode colour pickers inside Elementor Site Settings. Pure CSS variables, no JS flash. Includes a Dark Mode Switcher widget. |
| **Container Layout** | Slider mode (CSS scroll-snap) and Custom Layout mode (inject container children into an ECS template). |
| **Loop Custom Layout** | Arrange Loop Grid items inside a Custom Layout template using ECS Placeholder widgets. |
| **Menu Responsive** | Turn any Elementor Nav Menu into a hamburger menu with full style controls. |
| **Editorial Text** | Float images inside text blocks for magazine-style editorial layouts. |
| **Style Templates** | Save widget Style tab settings as named presets and apply across pages and sites. Export/import as JSON. |
| **JSON PowerEdit** | Edit any Elementor widget's raw settings JSON directly from the panel. Ctrl/Cmd+Enter to apply. |

> **Legacy module** — backward-compatible with ECS 3.x loop skins. Auto-activates on update when existing loop templates are detected.

---

## Requirements

- PHP 8.0+
- WordPress 6.0+
- Elementor (free) 3.20+
- Elementor Pro *(optional — required by Container Layout Custom mode and Loop Custom Layout)*

---

## Installation

1. Upload the `ele-custom-skin` folder to `/wp-content/plugins/`
2. Activate **ECS — Ele Custom Skin for Elementor** in the WordPress plugin screen
3. Go to **ECS → Modules** and toggle on only the modules you need

---

## Architecture

```
ele-custom-skin/
├── ele-custom-skin.php              # Plugin bootstrap
├── includes/
│   ├── class-ecs-core.php           # Singleton core
│   ├── class-ecs-module-base.php    # Abstract base for all modules
│   └── class-ecs-modules-manager.php
├── admin/                           # Modules toggle admin page
├── modules/
│   ├── color-scheme/
│   ├── container-layout/
│   ├── editorial-text/
│   ├── json-poweredit/
│   ├── legacy/
│   ├── loop-custom-layout/
│   ├── mobile-menu/
│   └── style-templates/
├── skins/                           # Legacy ECS 3.x skins
└── theme-builder/                   # Custom Layout document type
```

Each module extends `ECS_Module_Base` and is independently toggled from the admin page. Active module IDs are stored in the `ecs_active_modules` WordPress option.

---

## License

GPLv3 — see [LICENSE](http://www.gnu.org/licenses/gpl-3.0) for details.  
Copyright © [Dudaster.com](https://dudaster.com)
