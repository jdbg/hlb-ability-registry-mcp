=== HLB Ability Registry for MCP ===
Contributors: jdbg
Tags: abilities-api, mcp, ai, multisite, rest-api
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An admin-curated ability registry for the WordPress Abilities API, with a per-ability on/off switch and optional multisite network control.

== Description ==

Most "connect AI to WordPress" tools expose either everything or nothing: a single broad REST scope, or a fixed bundle of tools the site owner can't trim. HLB Ability Registry for MCP takes a different approach — it ships a **declarative catalogue** of individually-togglable [WordPress Abilities](https://make.wordpress.org/core/2025/09/09/introducing-the-wordpress-abilities-api/), and the *site owner* decides exactly which ones are live, per site.

= What it actually does =

* Registers a curated set of abilities against WordPress core's own Abilities API (`wp_register_ability()`) — content, media, comments, users, Site Editor templates & patterns, and optional WooCommerce, SEOPress and Gravity Forms integrations when those plugins are active.
* Every ability has its own admin toggle in **Settings → HLB Ability Registry for MCP**, searchable and grouped by category. Read-only abilities default on; write and destructive abilities default off.
* Read handlers do per-object capability checks (not just a blanket `current_user_can`), so a low-privilege caller can't read drafts or private posts by ID just because a coarse capability check passed. Listing abilities force unprivileged callers back to published content, and abilities only ever address post types the site already exposes publicly or over the REST API.
* On **multisite**, each subsite gets its own on/off set, inherited from a network default unless a subsite administrator explicitly overrides it. An optional **network mode** lets the main site's server target any subsite by id, with every permission and capability check re-run inside that subsite's own context — nothing is granted network-wide by default.
* Requires the [MCP Adapter](https://wordpress.org/plugins/mcp-adapter/) plugin, which projects the enabled abilities onto a standard MCP server at `/wp-json/{server-slug}/mcp`, so any MCP-speaking client or agent can call them. The enabled abilities are also reachable through core's own `/wp-json/wp-abilities/v1/` REST routes.

= Source code =

Development happens in the open: https://github.com/jdbg/hlb-ability-registry-mcp

= Try it without installing anything =

This plugin ships a [WordPress Playground](https://playground.wordpress.net/) blueprint so you can click through the settings screen and a live MCP endpoint in a disposable browser sandbox before installing anything on a real site. See the FAQ below for the link.

== Installation ==

1. Install and activate the [MCP Adapter plugin](https://wordpress.org/plugins/mcp-adapter/) (required). WordPress offers to install it for you from the plugin's card in **Plugins → Add New**.
2. Install and activate this plugin as usual (upload the zip, or `wp plugin install`).
3. Visit **Settings → HLB Ability Registry for MCP** to review and toggle the abilities available on this site.
4. On multisite, network-activate to set a network default; individual subsites can override it from their own settings screen unless network mode is enabled.

== Frequently Asked Questions ==

= Does this plugin require the MCP Adapter? =

Yes. It declares the MCP Adapter as a required plugin, so WordPress won't activate it until the adapter is installed and active, and won't let you deactivate the adapter while this plugin is active. On multisite, network-activate the adapter before network-activating this plugin.

= Which abilities are enabled by default? =

Read-only abilities (listing/getting posts, media, comments, taxonomies, templates, site info) default on. Anything that writes or deletes data defaults off until a site administrator turns it on explicitly.

= Can I try this before installing it? =

Yes — open it in WordPress Playground: https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/jdbg/hlb-ability-registry-mcp/main/blueprints/demo.json

= Is this safe on multisite? =

Yes. Per-subsite settings are always intersected with the currently-available ability registry, so a stale or renamed id can never be registered. In network mode, every permission and capability check still runs inside the target subsite's own context via `switch_to_blog()`, so a non-member is denied exactly as if they'd called the API on that subsite directly.

== Screenshots ==

1. Settings screen: abilities grouped into searchable, countable categories (Content — read/write, Media, Comments, Users, Site Editor, Site & diagnostics), each with its own toggle.
2. Live search narrows the list by name, id, or description across every category at once.

== Changelog ==

= 1.7.1 =
* The MCP Adapter is now a required plugin (`Requires Plugins: mcp-adapter`). WordPress will not activate this plugin until the adapter is installed and active, and offers to install it from wordpress.org. Existing installs with the adapter active are unaffected.

= 1.7.0 =
* Add Gravity Forms ability integration: list and inspect forms, query and read entries, update entry status, add entry notes and delete entries. Entry abilities contain personal data and are off by default.
* An ability's capability can now be a list, any one of which grants access.
* The MCP Adapter is now on wordpress.org: the dependency notice offers an Install button through the core plugin installer instead of linking to GitHub, and the Live Preview installs the adapter from wordpress.org.
* Bulgarian translation updated.

= 1.6.4 =
* Maintenance only — no changes to plugin behaviour.
* The Live Preview on the plugin page now installs the MCP Adapter alongside this plugin, so the preview opens on a working MCP endpoint rather than the dependency notice.

= 1.6.3 =
* Maintenance only — no changes to plugin behaviour.
* The WordPress Playground blueprint now sits where wordpress.org looks for it, so the plugin page offers a Live Preview.

= 1.6.2 =
* The MCP Adapter dependency notice no longer reports an adapter that is active but failed to load as "installed but not active", and no longer offers an Activate button that would do nothing.
* The notice now names the actual cause — missing bundled dependencies, a suppressed `WP_MCP_AUTOLOAD` autoloader, or an unexplained load failure — and gives the remedy that fits it.
* The post-activation success notice is only shown when the adapter really loaded, instead of appearing alongside the error notice.

= 1.6.1 =
* Fix a fatal error in network mode: abilities whose input schema has no properties (e.g. `get-current-user`) aborted registration, silently dropping every ability after them from the MCP tool list.
* Tested up to WordPress 7.1.

= 1.6.0 =
* Security: `wc-list-products` no longer returns draft, pending, private or trashed products to callers who cannot edit products.
* Security: abilities only address post types that are public or exposed in the REST API, so a coarse `read` capability cannot reach a plugin's private post types. Filterable with `hlb_mcp_allowed_post_types`.
* `get-active-theme` only reports the theme version and author to callers who can manage options, matching `get-site-info`.

= 1.5.0 =
* Rework the settings screen with tabbed categories, search, and the Settings API.

= 1.4.0 =
* Version bump.

= 1.3.0 =
* Add SEOPress ability integration.

= 1.2.0 =
* Add Frontend Gatekeeper integration.

= 1.1.0 =
* Restrict pattern category creation.
* Add Site Editor template and pattern abilities.
* Security refactor.

= 1.0.0 =
* Initial release.
