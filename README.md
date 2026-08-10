# HLB Ability Registry for MCP

[![Lint](https://github.com/jdbg/hlb-ability-registry-mcp/actions/workflows/lint.yml/badge.svg)](https://github.com/jdbg/hlb-ability-registry-mcp/actions/workflows/lint.yml)

A WordPress plugin that exposes a curated, admin-controlled set of WordPress **Abilities** to
the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter), so third-party tools and
AI agents can interact with your site over the Model Context Protocol (MCP). Multisite-ready and
network-activatable, with network-wide defaults each subsite can inherit or override.

- **Author:** [Jordan Hlebarov](https://jdbg.com/)
- **Source:** [github.com/jdbg/hlb-ability-registry-mcp](https://github.com/jdbg/hlb-ability-registry-mcp)
- **License:** GPL-2.0-or-later

### Try it without installing anything

[Open in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/jdbg/hlb-ability-registry-mcp/main/blueprints/demo.json) —
a disposable in-browser WordPress instance with this plugin and the MCP Adapter pre-installed and
activated.

## How it works

Three systems, wired together by this plugin:

1. **Abilities API** (WordPress core ≥ 6.9) defines *what the site can do*.
2. **MCP Adapter** projects selected abilities onto an MCP REST endpoint for external clients.
3. **This plugin** owns the ability registry, the settings UI, and a single resolver that decides
   which abilities are live — feeding both the Abilities registration and the MCP server tool list.

Read-only abilities are enabled by default; write and destructive abilities are off by default.
Every ability maps to a real WordPress capability, checked per request.

## Requirements

- WordPress **6.9+** (Abilities API in core)
- PHP **7.4+**
- The **MCP Adapter** plugin, active (network-wide if this plugin is network-activated)

The MCP Adapter is **not** in the wordpress.org directory, so this plugin never downloads or
installs it automatically — that would mean fetching and running executable code from a
third-party source, which this plugin (and the wordpress.org guidelines) avoid. If the adapter is
missing, an admin notice links to its GitHub releases page for a manual upload-install; if it's
already installed but inactive, the notice offers a one-click **Activate** button (a local
operation — no code is downloaded). Until the adapter is active, the plugin still registers its
abilities against core's Abilities API — only the MCP endpoint is skipped, never a fatal error.

## Installation

1. Copy the plugin folder to `wp-content/plugins/hlb-ability-registry-mcp` (the distributed files
   are `hlb-ability-registry-mcp.php`, `uninstall.php`, and `inc/` — no Composer dependencies are
   needed at runtime).
2. Activate it (single site or **Network Activate** on multisite).
3. (Optional) Download the [MCP Adapter](https://github.com/WordPress/mcp-adapter/releases/latest)
   and install it via **Plugins → Add New → Upload Plugin**, then activate it — or use the
   one-click **Activate** button in the admin notice if it's already installed but inactive.
4. Configure which abilities are exposed under the settings page (see below).

## MCP endpoint & authentication

The plugin registers one MCP server per site:

| Context | Server name | Endpoint |
| --- | --- | --- |
| Single site / network main site | `hlb_{domain}` | `/wp-json/hlb_{domain}/mcp` |
| Multisite subsite | `hlb_{maindomain}_{subsiteslug}` | `/wp-json/hlb_{maindomain}_{subsiteslug}/mcp` |

For example `example.com` → `hlb_example_com`, and `example.com/site-a` → `hlb_example_com_site_a`.
The slug is filterable via `hlb_mcp_server_slug`.

Third-party agents authenticate as a WordPress user. The recommended path is **Application
Passwords** (WordPress core) over HTTP Basic auth; each ability is additionally gated by that
user's capabilities. See [`examples/.mcp.json`](examples/.mcp.json) for a ready-to-use client
config (Claude Code format) and [`examples/README.md`](examples/README.md) for setup steps.

## Included abilities

Grouped by category. ✅ = enabled by default (read-only); ⬜ = off by default (write/destructive
or contains personal data).

| Category | Ability | Default |
| --- | --- | --- |
| Content — read | `hlb/get-post`, `hlb/list-posts`, `hlb/search-content`, `hlb/get-taxonomies` | ✅ |
| Content — write | `hlb/create-post`, `hlb/update-post`, `hlb/set-post-status`, `hlb/delete-post`, `hlb/assign-terms`, `hlb/create-term` | ⬜ |
| Media | `hlb/list-media` ✅ · `hlb/upload-media` ⬜ | |
| Comments | `hlb/list-comments` ✅ · `hlb/moderate-comment` ⬜ | |
| Users | `hlb/get-current-user` ✅ · `hlb/list-users` ⬜ | |
| Site & diagnostics | `hlb/get-site-info`, `hlb/get-active-theme`, `hlb/list-active-plugins` | ✅ |
| Network *(network mode only)* | `hlb/list-sites` | ✅ |
| WooCommerce *(when active)* | `hlb/wc-list-products` ✅ · `hlb/wc-get-order` ⬜ | |
| SEOPress *(when active)* | `hlb/seopress-get-meta` ✅ · `hlb/seopress-update-meta` ⬜ | |

Add your own via the `hlb_mcp_abilities` filter — see [Extending](#extending) below.

## Extending

Other plugins/mu-plugins can register additional abilities by filtering `hlb_mcp_abilities`.
Each entry uses the same shape as the built-in registry: a label/description, an existing
category id (categories themselves aren't filterable — reuse one from the table above), a
capability, a `default` on/off state, `readonly`/`destructive`/`idempotent` annotations, a
`handler` callable, and a JSON Schema `input_schema`. Give your ability id its own prefix
(e.g. `acme/…`) so it can't collide with `hlb/…` ids.

```php
add_filter( 'hlb_mcp_abilities', function ( array $defs ) {
	$defs['acme/count-posts'] = [
		'label'        => __( 'Count posts', 'acme' ),
		'description'  => __( 'Return the number of published posts of a given type.', 'acme' ),
		'category'     => 'content-read', // must match a category id from Registry::categories().
		'capability'   => 'read',
		'default'      => true, // read-only abilities default on; write/destructive default off.
		'annotations'  => [
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		],
		'handler'      => 'acme_count_posts',
		'input_schema' => [
			'type'       => 'object',
			'properties' => [
				'post_type' => [ 'type' => 'string', 'default' => 'post' ],
			],
		],
	];

	return $defs;
} );

function acme_count_posts( array $input ) {
	$type   = isset( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
	$counts = wp_count_posts( $type );

	if ( ! $counts ) {
		return new WP_Error( 'acme_invalid_post_type', __( 'Unknown post type.', 'acme' ), [ 'status' => 400 ] );
	}

	return [
		'post_type' => $type,
		'published' => (int) $counts->publish,
	];
}
```

The handler receives the request's `input` array and returns either a value (array/scalar) or a
`WP_Error`. Once registered, the ability shows up in the settings UI like any built-in one and is
gated by the same enabled-set resolver — nothing else to wire up.

## Multisite

- **Network defaults** are set on the Network Admin → Settings → *HLB Ability Registry for MCP*
  page.
- Each subsite has an **HLB Ability Registry for MCP** settings page with an *Override network
  defaults* toggle. When off, the subsite inherits the network defaults (shown read-only); when
  on, it uses its own selection.
- Uninstalling removes the network option and every per-site option.

### Two ways to reach subsites

By default the plugin registers **one MCP server per subsite** — to act on a subsite you connect
that subsite's own endpoint (`hlb_{maindomain}_{subsiteslug}`). Each server only ever touches its
own subsite.

Alternatively, enable **Network mode** (Network Admin → HLB Ability Registry for MCP):

- Connect **only the main site's endpoint** to your client — no per-subsite registration.
- Every enabled ability gains an optional `site` argument (blog ID, path slug, or domain); the
  handler runs against that subsite via `switch_to_blog()`. Omit `site` to act on the main site.
- A `hlb/list-sites` ability lets the agent discover subsites (id, slug, domain, name).
- Capabilities are still checked **on the target subsite**, so use a Super Admin credential.
  Adding a new client subsite then needs zero client-config changes — just say "…on `evrotrust`".

## Development

Coding standard: [`humanmade/coding-standards`](https://github.com/humanmade/coding-standards),
enforced by PHPCS. Composer is dev-only tooling.

```bash
composer install     # install PHPCS + the coding-standards ruleset
composer lint        # run phpcs (must pass clean)
composer lint:fix    # auto-fix with phpcbf
```

CI runs `composer lint` on every push to `main` and on pull requests
(`.github/workflows/lint.yml`).

See [`CLAUDE.md`](CLAUDE.md) for the architecture overview and the disposable-WordPress runtime
verification workflow. The plugin has been verified end-to-end on **WordPress 7.0**: both plugins
activate, abilities register and execute, the MCP server route is live, and toggling abilities in
settings changes the exposed tool set.

## Changelog

### 1.0.0

- Initial release.
- Network mode (multisite): a single main-site server can target any subsite via a `site`
  argument (`switch_to_blog()`), plus a `hlb/list-sites` ability for discovery. Opt-in from the
  network settings page; capabilities are enforced on the target subsite.
- Verified end-to-end on WordPress 7.0 (PHP 8.3): plugin + MCP Adapter activate cleanly,
  abilities register, an ability executes, the MCP server registers at `/wp-json/hlb_{site}/mcp`,
  and enabling/disabling abilities in settings changes the exposed tool set.
- Verified end-to-end on a subdirectory multisite (WP 7.0): site-targeted reads/writes hit the
  right subsite with no leakage, and cross-site permission checks are enforced.
- Ability categories are registered with a description, as required by WP 6.9/7.0.
