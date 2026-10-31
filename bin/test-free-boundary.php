<?php
/**
 * Self-check for the Free/Pro boundary.
 *
 * Every Pro feature is meant to be physically absent from this plugin, not switched off.
 * The easy ways to break that are a rebuild from a stale source tree or a file copied
 * across from the Pro plugin. This asserts that neither has happened.
 *
 *   php bin/test-free-boundary.php
 *
 * @package DevDiggers Buy Now Pay Later for WooCommerce
 */

$root   = dirname( __DIR__ ) . '/';
$errors = [];

/**
 * Read a file, or return '' when it is absent.
 *
 * @param string $path Absolute path.
 * @return string
 */
function ddwcpp_read( $path ) {
	return file_exists( $path ) ? (string) file_get_contents( $path ) : '';
}

/**
 * Every PHP file that belongs to this plugin, excluding the bundled framework and tooling.
 *
 * @param string $root Plugin root.
 * @return string[]
 */
function ddwcpp_plugin_php( $root ) {
	$files    = [];
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $iterator as $file ) {
		$path = $file->getPathname();

		if ( 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		foreach ( [ '/node_modules/', '/devdiggers-framework/', '/bin/' ] as $skip ) {
			if ( false !== strpos( $path, $skip ) ) {
				continue 2;
			}
		}

		$files[ str_replace( $root, '', $path ) ] = ddwcpp_read( $path );
	}

	return $files;
}

/**
 * Report every file that contains one of the needles.
 *
 * @param array  $files   relative path => contents.
 * @param array  $needles Strings that must not appear.
 * @param string $label   What a hit means.
 * @param array  $errors  Collected errors.
 * @return void
 */
function ddwcpp_forbid( $files, $needles, $label, &$errors ) {
	foreach ( $files as $relative => $contents ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $contents, $needle ) ) {
				$errors[] = "{$label} '{$needle}' is present in {$relative}.";
			}
		}
	}
}

$php = ddwcpp_plugin_php( $root );

// 1. Pro-only files are gone, not merely unhooked.
foreach ( [ 'helper/verification/verification-helper.php', 'templates/admin/configuration/license-configuration-template.php', 'tests/workflow-test.php' ] as $file ) {
	if ( file_exists( $root . $file ) ) {
		$errors[] = "Pro file {$file} is shipped.";
	}
}

// 2. No licensing, updater or remote notifications.
ddwcpp_forbid( $php, [ 'ddfw_is_license_activated', 'ddfw_register_plugin_for_updates', '_ddwcpp_purchase_code', '_ddwcpp_license_activated', 'DevDiggers_Notifications', 'DDFW_Plugin_Updater', 'wp_remote_' ], 'Licensing reference', $errors );

foreach ( [ 'includes/class-ddfw-plugin-updater.php', 'includes/class-devdiggers-notifications.php', 'templates/layout/license.php' ] as $file ) {
	if ( file_exists( $root . 'devdiggers-framework/' . $file ) ) {
		$errors[] = "Bundled framework ships {$file}.";
	}
}

// 3. No handler for a Pro action. Locked UI describes these; nothing may run them.
ddwcpp_forbid(
	$php,
	[
		'admin_post_ddwcpp_view_document',
		"case 'upload_document'",
		'get_bulk_actions',
		'process_bulk_action',
		"'export'",
		"'remind'",
		"'delete' === \$action",
		'ddwcpp_export_csv',
		'woocommerce_cart_calculate_fees',
		'ddwcpp_update_plans',
		'ddwcpp_update_limit',
		'ddwcpp_set_limit',
	],
	'Pro registration',
	$errors
);

// 4. No Pro business logic left in shared files.
ddwcpp_forbid(
	$php,
	[
		'DDWCPP_Verification_Helper',
		'ddwcpp_get_fee',
		'ddwcpp_calculate_charge',
		"'plan_fee',",
		"'reference'      => 'late_fee'",
		'interest_charges',
		'partial_payment_enabled',
		'min_repayment_amount',
		'grace_days',
		'allowed_roles',
		'ddwcpp_is_product_excluded',
		'excluded_products',
		"get_option( '_ddwcpp_appearance'",
		'message_audience',
		"'message_plan'",
		"'dialog_icon'",
		'ddwcpp_send_installment_reminder',
		'ddwcpp_migrate_dues',
		'reverification_enabled',
		// Configurable values pinned to a fixed value read as locked features (Guideline 5).
		'ddwcpp_get_appearance(',
		'Editing the wording is a Pro feature',
		'Bulk actions are a Pro feature',
	],
	'Pro logic',
	$errors
);

// 5. No Pro-only setting is registered, so a crafted options.php POST has nothing to write.
$admin_functions = ddwcpp_read( $root . 'includes/admin/admin-functions.php' );

foreach ( [ '_ddwcpp_reverification_enabled', '_ddwcpp_allowed_roles', '_ddwcpp_excluded_products', '_ddwcpp_excluded_categories', '_ddwcpp_interest_charges', '_ddwcpp_partial_payment_enabled', '_ddwcpp_min_repayment_amount', '_ddwcpp_grace_days', '_ddwcpp_late_fee', '_ddwcpp_appearance', '_ddwcpp_message_audience', '_ddwcpp_message_plan', '_ddwcpp_dialog_icon', '_ddwcpp_dialog_title', '_ddwcpp_dialog_steps', '_ddwcpp_dialog_note' ] as $needle ) {
	if ( false !== strpos( $admin_functions, "'{$needle}'" ) ) {
		$errors[] = "Pro setting '{$needle}' is registered in includes/admin/admin-functions.php.";
	}
}

// 6. Only the two built-in plans exist, and their schedule shape cannot be edited.
$plans = ddwcpp_read( $root . 'helper/plans/plans-helper.php' );

foreach ( [ 'pay-monthly', 'pay-6-months', "'fee'", "'fee_type'" ] as $needle ) {
	if ( false !== strpos( $plans, $needle ) ) {
		$errors[] = "Pro plan data '{$needle}' is present in helper/plans/plans-helper.php.";
	}
}

// Plans are not created or edited here: no plan screens, no save path, no per-plan limits.
foreach ( [ 'templates/admin/plans/plans-list-template.php', 'templates/admin/plans/manage-plan-template.php' ] as $file ) {
	if ( file_exists( $root . $file ) ) {
		$errors[] = "Pro plan screen {$file} is shipped.";
	}
}

ddwcpp_forbid( $php, [ 'ddwcpp_save_plan', 'ddwcpp_sanitize_plan', 'ddwcpp_process_plan_actions', 'EDITABLE_KEYS', 'min_amount', 'max_amount' ], 'Pro plan editing', $errors );

// 7. The built bundles carry no Pro flow, and still do the Free job.
$bundles = [
	'assets/js/admin.js'  => ddwcpp_read( $root . 'assets/js/admin.js' ),
	'assets/js/front.js'  => ddwcpp_read( $root . 'assets/js/front.js' ),
	'assets/js/blocks.js' => ddwcpp_read( $root . 'assets/js/blocks.js' ),
];

foreach ( $bundles as $bundle => $contents ) {
	if ( '' === $contents ) {
		$errors[] = "{$bundle} is missing. Run `npm run build`.";
	}
}

ddwcpp_forbid( $bundles, [ 'ddwcpp_update_limit', 'feeType', 'ddwcpp-document', 'confirmDelete', 'ddwcpp-transaction-id[]', 'console.log', 'document.cookie' ], 'Pro identifier', $errors );

foreach ( [ 'assets/js/front.js' => 'ddwcppPlan', 'assets/js/blocks.js' => 'ddwcpp_postpaid', 'assets/js/admin.js' => 'ddwcpp_adjust_balance' ] as $bundle => $needle ) {
	if ( '' !== $bundles[ $bundle ] && false === strpos( $bundles[ $bundle ], $needle ) ) {
		$errors[] = "Free behaviour '{$needle}' is missing from {$bundle}.";
	}
}

// 8. Every built asset maps to a webpack entry.
$webpack = ddwcpp_read( $root . 'webpack.config.js' );

foreach ( [ 'assets/js', 'assets/css' ] as $dir ) {
	foreach ( (array) glob( $root . $dir . '/*' ) as $path ) {
		$name = basename( $path );

		if ( 'index.php' === $name ) {
			continue;
		}

		if ( false === strpos( $webpack, '"' . pathinfo( $name, PATHINFO_FILENAME ) . '"' ) ) {
			$errors[] = "{$dir}/{$name} has no webpack entry.";
		}
	}
}

// 9. The Free bootstrap yields to Pro and loads the framework on its own pages.
$bootstrap = ddwcpp_read( $root . 'functions.php' );

foreach ( [ "class_exists( 'DDWCPP_Init' )", "'ddwcpp' !== \$prefix", 'DDWCPP_Free_Init' ] as $needle ) {
	if ( false === strpos( $bootstrap, $needle ) ) {
		$errors[] = "Bootstrap guard '{$needle}' is missing from functions.php.";
	}
}

// 10. No constants or upgrade routine a first release has no use for.
ddwcpp_forbid( $php, [ 'DDWCPP_VERSION', 'DDWCPP_PRO_URL', 'ddwcpp_maybe_upgrade', "'ddwcpp_version'" ], 'Unneeded', $errors );

if ( $errors ) {
	echo "Free/Pro boundary check FAILED:\n";

	foreach ( $errors as $error ) {
		echo ' - ' . $error . "\n";
	}

	exit( 1 );
}

echo "Free/Pro boundary check passed.\n";
