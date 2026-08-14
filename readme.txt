=== HLB Ability Registry for MCP ===
Contributors: jdbg
Tags: abilities-api, mcp, ai, multisite, rest-api
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An admin-curated ability registry for the WordPress Abilities API, with a per-ability on/off switch and optional multisite network control.

== Description ==

Most "connect AI to WordPress" tools expose either everything or nothing: a single broad REST scope, or a fixed bundle of tools the site owner can't trim. HLB Ability Registry for MCP takes a different approach — it ships a **declarative catalogue** of individually-togglable [WordPress Abilities](https://make.wordpress.org/core/2025/09/09/introducing-the-wordpress-abilities-api/), and the *site owner* decides exactly which ones are live, per site.

= What it actually does =

* Registers a curated set of abilities against WordPress core's own Abilities API (`wp_register_ability()`) — content, media, comments, users, Site Editor templates & patterns, and optional WooCommerce and SEOPress integrations when those plugins are active.
* Every ability has its own admin toggle in **Settings → HLB Ability Registry for MCP**, searchable and grouped by category. Read-only abilities default on; write and destructive abilities default off.
* Read handlers do per-object capability checks (not just a blanket `current_user_can`), so a low-privilege caller can't read drafts or private posts by ID just because a coarse capability check passed. Listing abilities force unprivileged callers back to published content, and abilities only ever address post types the site already exposes publicly or over the REST API.
* On **multisite**, each subsite gets its own on/off set, inherited from a network default unless a subsite administrator explicitly overrides it. An optional **network mode** lets the main site's server target any subsite by id, with every permission and capability check re-run inside that subsite's own context — nothing is granted network-wide by default.
* If the [MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin is active, the enabled abilities are projected onto a standard MCP server at `/wp-json/{server-slug}/mcp`, so any MCP-speaking client or agent can call them. Without the MCP Adapter, the abilities you enable are still fully registered and reachable through core's own `/wp-json/wp-abilities/v1/` REST routes — this plugin has value on a bare WordPress 6.9 install, the MCP Adapter is an optional extra hop for MCP clients specifically, not a hard requirement.

= Source code =

Development happens in the open: https://github.com/jdbg/hlb-ability-registry-mcp

= Try it without installing anything =

This plugin ships a [WordPress Playground](https://playground.wordpress.net/) blueprint so you can click through the settings screen and a live MCP endpoint in a disposable browser sandbox before installing anything on a real site. See the FAQ below for the link.

== Installation ==

1. Install and activate the plugin as usual (upload the zip, or `wp plugin install`).
2. Visit **Settings → HLB Ability Registry for MCP** to review and toggle the abilities available on this site.
3. (Optional) Install the [MCP Adapter plugin](https://github.com/WordPress/mcp-adapter) — it's not in the wordpress.org directory, so download it from its GitHub releases page and upload it via **Plugins → Add New → Upload Plugin**. Once it's active, this plugin's admin notice clears and your MCP endpoint goes live automatically; no extra configuration needed.
4. On multisite, network-activate to set a network default; individual subsites can override it from their own settings screen unless network mode is enabled.

== Frequently Asked Questions ==

= Does this plugin require the MCP Adapter to do anything? =

No. Abilities register with WordPress core's Abilities API regardless, and are reachable via `/wp-json/wp-abilities/v1/`. The MCP Adapter is only needed if you want the dedicated MCP protocol endpoint. This plugin never downloads or installs the MCP Adapter automatically — it only detects whether it's present and links to its GitHub releases page if not.

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
