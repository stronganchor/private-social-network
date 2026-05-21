<?php
/**
 * GitHub-hosted plugin update integration.
 *
 * @package LittleWorksOfMercy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class LWorks_Update_Checker {
	/**
	 * Register Plugin Update Checker.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$checker = PucFactory::buildUpdateChecker(
			LWORKS_GITHUB_REPO,
			LWORKS_PLUGIN_FILE,
			'private-social-network'
		);

		$checker->setBranch( apply_filters( 'lworks_update_branch', 'main' ) );

		$vcs_api = $checker->getVcsApi();
		if ( $vcs_api && method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
			$vcs_api->enableReleaseAssets();
		}

		$token = self::github_token();
		if ( $token ) {
			$checker->setAuthentication( $token );
		}
	}

	/**
	 * Get an optional GitHub token for private repository updates.
	 *
	 * Prefer defining LWORKS_GITHUB_TOKEN in wp-config.php over storing a token
	 * in the database.
	 *
	 * @return string
	 */
	private static function github_token() {
		$token = '';

		if ( defined( 'LWORKS_GITHUB_TOKEN' ) && LWORKS_GITHUB_TOKEN ) {
			$token = LWORKS_GITHUB_TOKEN;
		} elseif ( getenv( 'LWORKS_GITHUB_TOKEN' ) ) {
			$token = getenv( 'LWORKS_GITHUB_TOKEN' );
		}

		return (string) apply_filters( 'lworks_github_token', $token );
	}
}
