<?php
/**
 * MCP Adapter dependency detection.
 *
 * The MCP Adapter is distributed via GitHub, not the wordpress.org directory. This
 * plugin never fetches or installs it automatically — that would mean downloading and
 * running executable code from a third-party source, which wordpress.org's guidelines
 * disallow. Instead this class only detects the adapter's state and, if it's missing,
 * shows an admin notice linking to the adapter's GitHub releases page for manual
 * install. If the adapter is already installed but inactive, activating it is a local
 * operation (no code is downloaded), so that action is still offered directly.
 *
 * @package HLB\MCP
 */

namespace HLB\MCP;

defined( 'ABSPATH' ) || exit;

/**
 * Detects the MCP Adapter dependency and offers to activate it if already installed.
 */
class Dependency {

	const ADAPTER_CLASS    = '\\WP\\MCP\\Core\\McpAdapter';
	const ADAPTER_BASENAME = 'mcp-adapter/mcp-adapter.php';
	const ADAPTER_REPO_URL = 'https://github.com/WordPress/mcp-adapter';

	const ACTION_ACTIVATE = 'hlb_mcp_activate_adapter';

	/**
	 * Register admin hooks (notices + action handler).
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_notices', [ $this, 'render_notice' ] );
		add_action( 'network_admin_notices', [ $this, 'render_notice' ] );
		add_action( 'admin_post_' . self::ACTION_ACTIVATE, [ $this, 'handle_activate' ] );
	}

	/* --------------------------------------------------------------------- Detection */

	/**
	 * Whether the adapter is loaded (installed and active).
	 *
	 * @return bool
	 */
	public function is_active() {
		return class_exists( self::ADAPTER_CLASS );
	}

	/**
	 * The adapter's plugin basename if it is installed, else empty string.
	 *
	 * @return string
	 */
	public function installed_basename() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();

		if ( isset( $plugins[ self::ADAPTER_BASENAME ] ) ) {
			return self::ADAPTER_BASENAME;
		}

		// Resilient fallback: match by folder or plugin name in case it was installed
		// under a differently-named directory.
		foreach ( $plugins as $file => $data ) {
			if ( 0 === strpos( $file, 'mcp-adapter/' ) ) {
				return $file;
			}
			if ( isset( $data['Name'] ) && false !== stripos( $data['Name'], 'MCP Adapter' ) ) {
				return $file;
			}
		}
		return '';
	}

	/**
	 * Whether the adapter is installed (regardless of active state).
	 *
	 * @return bool
	 */
	public function is_installed() {
		return '' !== $this->installed_basename();
	}

	/**
	 * Whether the adapter plugin is switched on in WordPress.
	 *
	 * This is deliberately distinct from is_active(): the adapter bootstrap bails out
	 * early (before defining any class) when its bundled autoloader is missing, so a
	 * plugin that is very much "active" on the Plugins screen can still leave
	 * class_exists() false. Telling the two apart is what keeps the notice honest.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$basename = $this->installed_basename();
		if ( ! $basename ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $basename );
	}

	/**
	 * Whether the adapter loaded its plugin file but failed to boot its classes.
	 *
	 * @return bool
	 */
	public function is_broken() {
		return ! $this->is_active() && $this->is_enabled();
	}

	/**
	 * Whether the adapter's bundled autoloader is switched off by constant.
	 *
	 * WP_MCP_AUTOLOAD short-circuits the adapter's loader on the promise that another
	 * autoloader already registers WP\MCP\. When the classes are missing anyway, that
	 * promise was not kept — typically a project-level Composer autoloader that is not
	 * reachable at runtime — and reinstalling the adapter fixes nothing.
	 *
	 * @return bool
	 */
	private function autoload_suppressed() {
		return defined( 'WP_MCP_AUTOLOAD' ) && ! WP_MCP_AUTOLOAD;
	}

	/**
	 * Whether the adapter's bundled autoloader is absent.
	 *
	 * The adapter defines WP_MCP_DIR before it guards on its autoloader, so the constant
	 * being set means its plugin file ran; a missing autoload_packages.php then pins the
	 * cause down to unbuilt dependencies rather than some other early bail.
	 *
	 * @return bool
	 */
	private function missing_dependencies() {
		if ( ! defined( 'WP_MCP_DIR' ) ) {
			return false;
		}
		return ! file_exists( rtrim( WP_MCP_DIR, '/' ) . '/vendor/autoload_packages.php' );
	}

	/**
	 * Whether this plugin is network-activated (so the adapter should be too).
	 *
	 * @return bool
	 */
	private function is_network_context() {
		if ( ! is_multisite() ) {
			return false;
		}
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active_for_network( HLB_MCP_BASENAME );
	}

	/* ----------------------------------------------------------------------- Notices */

	/**
	 * Render the dependency notice appropriate to the current state.
	 *
	 * @return void
	 */
	public function render_notice() {
		$this->maybe_result_notice();

		if ( $this->is_active() ) {
			return; // Dependency satisfied — nothing to show.
		}

		if ( $this->is_broken() ) {
			$this->broken_notice();
			return;
		}

		$installed = $this->is_installed();

		if ( $installed && current_user_can( 'activate_plugins' ) ) {
			$this->activate_notice_box();
			return;
		}

		if ( $installed ) {
			// Installed but this user can't activate it — inform only.
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'HLB Ability Registry for MCP', 'hlb-ability-registry-mcp' ),
				esc_html__( 'the MCP Adapter plugin is installed but not active. Ask a site administrator to activate it.', 'hlb-ability-registry-mcp' )
			);
			return;
		}

		// Not installed — never fetched automatically; point to manual install.
		if ( current_user_can( 'install_plugins' ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
				esc_html__( 'HLB Ability Registry for MCP', 'hlb-ability-registry-mcp' ),
				esc_html__( 'the MCP Adapter plugin is not installed, so no MCP endpoint is exposed yet. Abilities still register normally and are available via the core Abilities REST API.', 'hlb-ability-registry-mcp' ),
				sprintf(
					/* translators: %s: link to the MCP Adapter GitHub releases page. */
					esc_html__( 'Download the latest release from %s, then install it from Plugins → Add New → Upload Plugin.', 'hlb-ability-registry-mcp' ),
					'<a href="' . esc_url( self::ADAPTER_REPO_URL . '/releases/latest' ) . '" target="_blank" rel="noopener">' . esc_html__( 'the MCP Adapter GitHub releases page', 'hlb-ability-registry-mcp' ) . '</a>'
				)
			);
			return;
		}

		// User lacks the capability to install it — inform only.
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'HLB Ability Registry for MCP', 'hlb-ability-registry-mcp' ),
			esc_html__( 'the MCP Adapter plugin is not installed. Ask a site administrator to install and activate it.', 'hlb-ability-registry-mcp' )
		);
	}

	/**
	 * Render a notice for an adapter that is active but failed to load its classes.
	 *
	 * Activating it again would be a no-op, so no action button is offered. The remedy
	 * depends entirely on why the classes are missing — reinstalling the adapter is the
	 * wrong advice when its loader was deliberately suppressed — so cause and remedy are
	 * chosen together, most specific first.
	 *
	 * @return void
	 */
	private function broken_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'HLB Ability Registry for MCP', 'hlb-ability-registry-mcp' ),
				esc_html__( 'the MCP Adapter plugin is active but failed to load, so no MCP endpoint is exposed. Ask a site administrator to reinstall it.', 'hlb-ability-registry-mcp' )
			);
			return;
		}

		$releases = '<a href="' . esc_url( self::ADAPTER_REPO_URL . '/releases/latest' ) . '" target="_blank" rel="noopener">' . esc_html__( 'the MCP Adapter GitHub releases page', 'hlb-ability-registry-mcp' ) . '</a>';

		if ( $this->autoload_suppressed() ) {
			$cause  = esc_html__( 'the MCP Adapter plugin is active but registered no classes, so no MCP endpoint is exposed. Its bundled autoloader is switched off by the WP_MCP_AUTOLOAD constant, and nothing else loaded the adapter in its place.', 'hlb-ability-registry-mcp' );
			$remedy = esc_html__( 'Check that the autoloader meant to supply the WP\MCP\ namespace — usually a project-level Composer autoloader — is actually loaded at runtime, or remove the WP_MCP_AUTOLOAD constant so the adapter loads its own.', 'hlb-ability-registry-mcp' );
		} elseif ( $this->missing_dependencies() ) {
			$cause  = esc_html__( 'the MCP Adapter plugin is active but could not load — its bundled dependencies are missing, so no MCP endpoint is exposed. This happens when the adapter is installed from a source checkout instead of a packaged release.', 'hlb-ability-registry-mcp' );
			$remedy = sprintf(
				/* translators: %s: link to the MCP Adapter GitHub releases page. */
				esc_html__( 'Install the latest release from %s over the current copy, or run "composer install" in the adapter\'s plugin folder.', 'hlb-ability-registry-mcp' ),
				$releases
			);
		} else {
			$cause  = esc_html__( 'the MCP Adapter plugin is active but did not load its classes, so no MCP endpoint is exposed.', 'hlb-ability-registry-mcp' );
			$remedy = sprintf(
				/* translators: %s: link to the MCP Adapter GitHub releases page. */
				esc_html__( 'Check the site error log for a fatal in the adapter, then reinstall it from %s.', 'hlb-ability-registry-mcp' ),
				$releases
			);
		}

		printf(
			'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
			esc_html__( 'HLB Ability Registry for MCP', 'hlb-ability-registry-mcp' ),
			$cause, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			$remedy // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		);
	}

	/**
	 * Render a notice offering to activate an already-installed adapter.
	 *
	 * @return void
	 */
	private function activate_notice_box() {
		// admin-post.php only exists at /wp-admin/admin-post.php (there is no network
		// variant); the redirect_to field returns the user to the originating screen.
		$post_url = admin_url( 'admin-post.php' );
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'HLB Ability Registry for MCP', 'hlb-ability-registry-mcp' ); ?></strong>
				<?php esc_html_e( 'the MCP Adapter plugin is installed but not active.', 'hlb-ability-registry-mcp' ); ?>
			</p>
			<p>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:inline;">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_ACTIVATE ); ?>" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ); ?>" />
					<?php wp_nonce_field( self::ACTION_ACTIVATE ); ?>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Activate MCP Adapter', 'hlb-ability-registry-mcp' ); ?></button>
					<a href="<?php echo esc_url( self::ADAPTER_REPO_URL ); ?>" target="_blank" rel="noopener" class="button-link" style="margin-left:.5em;"><?php esc_html_e( 'View plugin', 'hlb-ability-registry-mcp' ); ?></a>
				</form>
			</p>
		</div>
		<?php
	}

	/**
	 * Show a one-time result notice after an activation attempt.
	 *
	 * @return void
	 */
	private function maybe_result_notice() {
		if ( empty( $_GET['hlb_dep'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$state = sanitize_key( wp_unslash( $_GET['hlb_dep'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'activated' === $state ) {
			// Plugins have loaded normally on this request, so is_active() is trustworthy
			// here. If the adapter still isn't there, stay quiet and let broken_notice()
			// give the one accurate account instead of contradicting it with a success.
			if ( $this->is_active() ) {
				$this->result( 'success', __( 'MCP Adapter activated. Your MCP endpoint is now live.', 'hlb-ability-registry-mcp' ) );
			}
		} elseif ( 'failed' === $state ) {
			$detail = isset( $_GET['hlb_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['hlb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->result( 'error', __( 'Could not activate the MCP Adapter.', 'hlb-ability-registry-mcp' ) . ( $detail ? ' ' . $detail : '' ) );
		}
	}

	/**
	 * Print a simple result notice.
	 *
	 * @param string $type    success|error.
	 * @param string $message Message.
	 * @return void
	 */
	private function result( $type, $message ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_attr( $type ),
			esc_html__( 'HLB Ability Registry for MCP:', 'hlb-ability-registry-mcp' ),
			esc_html( $message )
		);
	}

	/* --------------------------------------------------------------------- Handlers */

	/**
	 * Activate an already-installed MCP Adapter.
	 *
	 * @return void
	 */
	public function handle_activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to activate plugins.', 'hlb-ability-registry-mcp' ) );
		}
		check_admin_referer( self::ACTION_ACTIVATE );

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$basename = $this->installed_basename();
		if ( ! $basename ) {
			$this->redirect_result( 'failed', __( 'The MCP Adapter is no longer installed.', 'hlb-ability-registry-mcp' ) );
		}

		if ( $this->is_enabled() ) {
			$this->redirect_result( 'failed', __( 'The MCP Adapter is already active but failed to load its bundled dependencies. Reinstall it from a packaged release.', 'hlb-ability-registry-mcp' ) );
		}

		$activated = activate_plugin( $basename, '', $this->is_network_context() );
		if ( is_wp_error( $activated ) ) {
			$this->redirect_result( 'failed', $activated->get_error_message() );
		}

		$this->redirect_result( 'activated' );
	}

	/**
	 * Redirect back to the originating admin page with a result flag.
	 *
	 * @param string $state activated|failed.
	 * @param string $detail Optional detail message.
	 * @return void
	 */
	private function redirect_result( $state, $detail = '' ) {
		$fallback = is_network_admin() ? network_admin_url() : admin_url();
		$target   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by caller.
		if ( ! $target ) {
			$target = wp_get_referer() ? wp_get_referer() : $fallback;
		}

		$args = [ 'hlb_dep' => $state ];
		if ( 'failed' === $state && $detail ) {
			$args['hlb_msg'] = rawurlencode( wp_strip_all_tags( $detail ) );
		}

		wp_safe_redirect( add_query_arg( $args, $target ) );
		exit;
	}
}
