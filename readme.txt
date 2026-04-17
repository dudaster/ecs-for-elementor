=== ECS - Ele Custom Skin for Elementor ===
Contributors: dudaster
Tags: elementor, dark mode, color scheme, loop, container
Donate link: https://www.paypal.me/dudaster
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 4.0.0
Requires PHP: 8.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0

Extend Elementor. Build faster. Dark mode, colour controls, container layouts, mobile menu styling and more — all modular, all free.

== Description ==

**ECS is back — and bigger than ever.**

Version 4.0 is a complete rebuild. The original loop skin functionality is still here (now called Legacy, fully backward compatible), and on top of it we've added a full modular toolkit for Elementor: dark mode, colour controls, container layouts, a mobile menu module, an editorial text widget, and more.

Every feature is a module you can switch on or off from a single admin screen. Only activate what you need.

---

= 🎨 Default Colours & Dark Mode (NEW) =

A new **Default Colours** tab appears directly inside Elementor's Site Settings, right next to Global Colors.

* Set a **Default** and a **Dark Mode** colour for every global colour slot.
* Dark mode colours are written as CSS variables under `[data-dte-scheme="alt"]` — no JavaScript colour swapping, pure CSS, zero flash.
* A **fallback swatch** shows the default colour when the dark slot is empty, so you always have a visual reference.
* The **Dark Mode Switcher** widget lets visitors toggle between light and dark. Three display modes: **Toggle** (single button), **Dual** (side-by-side), or **Dropdown**. Each state has its own icon (any Elementor icon library) and label. Full style controls: typography, padding, border, box shadow, normal/active/hover colour tabs.

= 📐 Container Content Layout (NEW) =

Two new layout modes for Elementor Flex Containers:

**Custom Layout** — Pick any Theme Builder template as the layout frame. Place ECS Placeholder widgets inside that template; ECS distributes the container's children into those slots automatically. Works in the editor too — children stay live-editable. Supports cycling (more children than placeholders) and graceful fallback (fewer children). Appears as a new document type in Theme Builder alongside Header, Footer, Loop Item.

**Slider Mode** — Turn any container into a CSS-only slider with a single click. No JavaScript library, no dependencies.

= 📱 Mobile Menu Styling (NEW) =

Style Elementor's native nav menu for mobile directly from the Elementor panel — no custom CSS required.

= ✍️ Editorial Text Widget (NEW) =

A typographic widget for editorial-style layouts: drop caps, pull quotes, running text with fine controls over leading, tracking, and column flow.

= 🧩 Modular Architecture (NEW) =

All features live in independent modules. Head to **ECS → Modules** in the WordPress admin to enable or disable anything with a toggle. Unused modules load zero assets.

= ✅ Legacy Loop Skin (backward compatibility only) =

Elementor Pro now covers what ECS originally introduced — Loop Builder, Load More, Infinite Scroll, and Dynamic Tags are all built in natively. The Legacy module is kept **exclusively for existing sites** built with ECS 3.x so they don't break on update.

If you're updating from ECS 3.x, the Legacy module is **activated automatically**. If you're starting fresh, leave it off and use Elementor's native Loop Builder instead.

---

= Upgrade to ECS Pro =

[ECS Pro](https://dudaster.com/ecs-pro/) adds:

* **Display Conditions** — show a different loop template based on post type, taxonomy, author, and more.
* **Alternating Templates** — template A for post 1, 4, 7…; template B for post 2, 5, 8…
* **Dynamic Fields** — pull ACF, WooCommerce attributes, custom taxonomies, and query vars into your loop templates.
* **Infinite Scroll** — scroll-based pagination on top of the free Load More.
* **Colour Schemes** — save named colour palettes and switch them globally or per-page.
* **Font Schemes** — same concept for typography.
* **Alt Logos** — serve a different logo per page condition or time range.
* **Custom Look & Feel** — conditional CSS snippets and look presets that activate based on page conditions and time schedules.
* **Responsive Container Type** — set Flex / Grid / Slider independently for desktop, tablet, and mobile.

---

Note: This plugin requires Elementor (free). Some features (Legacy loop skin, Container Layout) also require Elementor Pro.

== Installation ==

1. Install and activate **Elementor** (free).
2. Upload and activate **ECS - Ele Custom Skin for Elementor** through the Plugins screen.
3. Go to **ECS → Modules** in the WordPress admin to see all available modules and toggle them on or off.
4. That's it — activated modules register their controls, widgets, and assets automatically.

**Updating from ECS 3.x?** Just update the plugin. The Legacy module activates automatically; all your existing loop skins keep working.

== Frequently Asked Questions ==

= I'm updating from ECS 3.x. Will my loop skins break? =

No. When you update, ECS detects existing loop templates in your database and activates the Legacy module automatically. Everything works exactly as before.

= Can I disable features I don't use? =

Yes. Go to **ECS → Modules** and toggle anything off. Disabled modules load zero CSS, zero JS, and register no hooks.

= Does Dark Mode require JavaScript? =

No JavaScript is needed for the colour switch itself. The CSS variables are written server-side under `[data-dte-scheme="alt"]`. The Dark Mode Switcher widget adds a small script only to set that attribute on `<html>` when the visitor toggles — the cookie is read on page load to prevent any flash.

= Does Custom Layout (Container) work in the Elementor editor? =

Yes. In the editor, the layout injection is disabled so you can edit children normally. A preview is generated via AJAX when you switch away from a child, keeping the live preview accurate without breaking drag-and-drop.

= Does the Slider Mode require a JS library? =

No. It uses CSS-only sliding via `prefix_class` — no jQuery plugin, no swipe library. For touch support and advanced controls, use the Pro version.

= Where do I create a Custom Layout template? =

Go to **Templates → Theme Builder** in Elementor. You'll see a new document type called **Custom Layout**. Create your frame there using any Elementor widgets, and place the **ECS Placeholder** widget wherever you want a child to appear.

= I used ECS for the loop skin. Should I keep using it? =

If your site was built with ECS 3.x, keep the Legacy module active — everything keeps working. For new projects, use Elementor Pro's native Loop Builder instead; it now covers the same functionality. Focus ECS on the new modules (Dark Mode, Container Layout, etc.) that Elementor doesn't offer.

= Do I need Elementor Pro? =

The Default Colours, Dark Mode Switcher, Mobile Menu, and Editorial Text modules work with Elementor free. Container Layout requires Elementor Pro.

= Is ECS Pro a separate plugin? =

Yes — [ECS Pro](https://dudaster.com/ecs-pro/) is a standalone addon that requires ECS free to be active. Its modules appear in the same **ECS → Modules** admin screen alongside the free ones.

== Screenshots ==

1. ECS → Modules admin screen — enable or disable each feature with a toggle.
2. Default Colours tab in Elementor Site Settings — set default and dark-mode colours side by side.
3. Dark Mode Switcher widget — toggle, dual, and dropdown display modes.
4. Container Layout — Custom Layout mode with ECS Placeholder widgets distributing children into a template.
5. Legacy loop skin — custom skin for Posts widget using an Elementor Loop Template.

== Changelog ==

= 4.0.0 =
* **Rebuilt from the ground up** — modular architecture, every feature is a toggleable module.
* NEW: Default Colours tab in Elementor Site Settings with per-colour dark mode support.
* NEW: Dark Mode Switcher widget (toggle / dual / dropdown modes, full style controls).
* NEW: Container Layout module — Custom Layout mode with template + ECS Placeholder widget, Slider mode.
* NEW: Mobile Menu styling module.
* NEW: Editorial Text widget.
* NEW: Style Templates module.
* NEW: ECS → Modules admin page.
* Legacy (ECS 3.x): loop skin, Custom Grid, Ajax Load More, dynamic style — preserved in the Legacy module, auto-activated on update from 3.x.
* Tested with Elementor 3.35 and Elementor Pro 3.35.
* Requires PHP 8.0+, WordPress 6.0+.

= 3.1.9 =
* Minor fixes.

= 3.1.8 =
* Replaced deprecated code.

= 3.1.7 =
* Fixed errors with Elementor 3.7.
* Added support for dynamic media breakpoint CSS.

= 3.1.6 =
* Fixed issue with Container Element style.

= 3.1.5 =
* Fixed issue with Custom Grid tab missing when WooCommerce is active.
* Fixed deprecated errors with newer Elementor versions.

= 3.1.4 =
* Fixed error with Custom Grid Loop Item Widget when added to a template.
* Fixed issues with the new Theme Builder view.

= 3.1.3 =
* Fixed CSS issue with Elementor Pro 3.4.

= 3.1.2 =
* Fixed URL error message issues.

= 3.1.1 =
* Tested for newer Elementor versions.

= 3.1.0 =
* Ajax Pagination URL change is now optional.
* Experimental reinitialization of Elementor JavaScript after Ajax requests.
* Fixed issues with some ACF dynamic values.
* Fixed CSS issues with Ocean WP and Storefront.
* Added support for latest WordPress and Elementor.

= 3.0.0 =
* Added compatibility with Elementor Pro 3.0.
* Added pagination history (URL change on page change).

= 2.2.0 =
* NEW: Ajax Load More pagination.
* Fixed multiple Custom Grid on one page.

= 2.0.0 =
* NEW: Custom Grid Template with Loop Item placeholder widget.

= 1.4.0 =
* Dynamic background and colour support for widgets, sections, and columns.

= 1.0.0 =
* Initial release — Loop skin for Elementor Posts and Archive Posts widgets.
