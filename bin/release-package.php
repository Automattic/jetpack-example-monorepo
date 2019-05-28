<?php
/**
 * Release a Jetpack package.
 *
 * Example usage: `php bin/release-package.php example-package 1.2.3`, where:
 * - `1.2.3` is the tag version
 * - `example-package` is the name of the package that corresponds to:
 *   - A Jetpack package in `/packages/example-package` in the main Jetpack repository
 *   - A repository that lives in `automattic/jetpack-example-package`.
 *
 * This will:
 * - Push a new release in the main repository, with a tag `automattic/jetpack-example-package@1.2.3`.
 * - Push the latest contents and history of the package directory to the package repository.
 * - Push a new release in the package repository, with a tag `v1.2.3`.
 *
 * @package jetpack
 */

// We need the package name to be able to mirror its directory to a package.
if ( empty( $argv[1] ) ) {
	die( 'Error: Package name has not been specified.' );
}

// Package name should contain only alphanumeric characters and dashes (example: `example-package`).
if ( ! preg_match( '/^[A-Za-z0-9\-]+$/', $argv[1] ) ) {
	die( 'Error: Package name is incorrect.' );
}
$package_name = $argv[1];

$package_info = get_package_info( $package_name );
// just because
display_package_info( $package_info );

$current_version = get_current_version( $package_info );



// We need the tag name (version) to be able to mirror a package to its corresponding version.
if ( empty( $argv[2] ) ) {
	die( 'Error: Tag name (version) has not been specified. Please specify "major", "minor" or "patch" or a specific ' );
}
if ( in_array( strtolower ( $argv[2] ), array( 'major', 'minor', 'patch' ) )  {
	$argv[2] = bump_version_number( $current_version, $argv[2] );
}

// Tag name (version) should match the format `1.2.3`.
if ( ! preg_match( '/^[0-9.]+$/', $argv[2] ) ) {
	die( 'Error: Tag name (version) is incorrect.' );
}
$tag_version = $argv[2];

die( 'END!' );
// Create the new tag in the main repository.
$main_repo_tag = 'automattic/jetpack-' . $package_name . '@' . $tag_version;
$command       = sprintf(
	'git tag -a %1$s -m "%1$s"',
	escapeshellarg( $main_repo_tag )
);
execute( $command, 'Could not tag the new package version in the main repository.' );

// Do the magic: bring the subdirectory contents (and history of non-empty commits) onto the master branch.
$command = sprintf(
	'git filter-branch -f --prune-empty --subdirectory-filter %s master',
	escapeshellarg( 'packages/' . $package_name )
);
execute( $command, 'Could not filter the branch to the package contents.', true );

// Add the corresponding package repository as a remote.
$package_repo_url = sprintf(
	'git@github.com:Automattic/jetpack-%s.git',
	$package_name
);
$command          = sprintf(
	'git remote add package %s',
	escapeshellarg( $package_repo_url )
);
execute( $command, 'Could not add the new package repository remote.', true, true );

// Push the contents to the package repository.
execute( 'git push package master --force', 'Could not push to the new package repository.', true, true );

// Grab all the existing tags from the package repository.
execute( 'git fetch --tags', 'Could not fetch the existing tags of the package.', true, true );

// Create the new tag.
$command = sprintf(
	'git tag -a v%1$s -m "Version %1$s"',
	escapeshellarg( $tag_version )
);
execute( $command, 'Could not tag the new version in the package repository.', true, true );

// Push the new package tag to the main repository.
$command = sprintf(
	'git push origin %s',
	escapeshellarg( $main_repo_tag )
);
execute( $command, 'Could not push the new package version tag to the main repository.', true, true );

// Push the new tag to the package repository.
$command = sprintf(
	'git push package v%s',
	escapeshellarg( $tag_version )
);
execute( $command, 'Could not push the new version tag to the package repository.', true, true );

// Reset the main repository to the original state, and remove the package repository remote.
cleanup( true, true );

/**
 * Execute a command.
 * On failure, throw an exception with the specified message (if specified).
 *
 * @throws Exception With the specified message if the command fails.
 *
 * @param string $command           Command to execute.
 * @param string $error             Error message to be thrown if command fails.
 * @param bool   $cleanup_repo      Whether to cleaup repo on error.
 * @param bool   $cleanup_remotes   Whether to cleanup remotes on error.
 */
function execute( $command, $error = '', $cleanup_repo = false, $cleanup_remotes = false ) {
	// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru
	passthru( $command, $status );
	// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru

	if ( $error && 0 !== $status ) {
		cleanup( $cleanup_repo, $cleanup_remotes );

		echo( 'Error: ' . $error . PHP_EOL );
		exit;
	}
}

/**
 * Cleanup repository and remotes.
 * Should be called at any error that changes the repo, or at success at the end.
 *
 * @param bool $cleanup_repo    Whether to cleaup repo on error.
 * @param bool $cleanup_remotes Whether to cleanup remotes on error.
 */
function cleanup( $cleanup_repo = false, $cleanup_remotes = false ) {
	if ( $cleanup_repo ) {
		// Reset the main repository to the original state.
		execute( 'git reset --hard refs/original/refs/heads/master', 'Could not reset the repository to its original state.' );

		// Pull the latest master from the main repository.
		execute( 'git pull', 'Could not pull the latest master from the repository.' );
	}

	if ( $cleanup_remotes ) {
		// Remove the temporary repository package remote.
		execute( 'git remote rm package', 'Could not clean up the package repository remote.' );
	}
}


 /**
 * Semantically bump a version number, defaults to "minor".
 *
 * If your version number includes a prefix, (e.g. "v" in "v0.1.0")
 * this will be preserved and returned.  Any suffix (e.g. "-beta"
 * in "v0.5.2-beta") will be lost.
 *
 * You can provide version numbers in the following formats:
 * '0.1.2', 'v1.2-patched', '2.3', '3', 4.1, 5
 * And you will get back (assuming a minor bump):
 * '0.2.2', 'v1.3.0', '2.4.0', '3.1.0', '4.2.0', '5.1.0'
 *
 * @param String|Null $version the current version number, defaults to '0.0.0'
 * @param String|Null $type the type of bump (major|minor|patch), defaults to 'minor'
 * @return String the new version number, e.g. '0.1.0'
 */
function bump_version_number( $version='0.0.0', $type='patch' ) {
    $version = ''.$version;
    $prefix = preg_replace('|[0-9].*$|', '', $version);
    $version = preg_replace('|[^0-9.]*([0-9.]+).*|', '$1', $version);
    while ( count(explode('.', $version)) < 3 ) {
        $version .= '.0';
    }
    list($major, $minor, $patch) = explode('.', $version);
    $major = (int) $major;
    $minor = (int) $minor;
    $patch = (int) $patch;
    switch ($type) {
        case 'major' : $major++; break;
        case 'minor' : $minor++; break;
        case 'patch' : $patch++; break;
    }
    return "$prefix$major.$minor.$patch";
}

function get_current_version( $package_info ) {
	if ( isset( $package_info->version ) ) {
		return $package_info->version;
	}
	die( 'PACKAGE ERROR: Package version is not defined!' . PHP_EOL );
}
function display_package_info( $package_info ) {
	echo PHP_EOL;
	foreach( (array) $package_info as $title => $key ) {
		if ( is_string( $key ) ) {
			echo( ucfirst ( $title ) . ': ' . $key . PHP_EOL );
		}
	}
	echo PHP_EOL;
}
function get_package_info( $package ) {
	$package = trim( $package );

	$file = '';
	if ( '-package' === substr( $package, - strlen( '-package' ) ) ) {
		$file = dirname ( __FILE__ ) . '/../packages/' . $package . '/composer.json';
	}

	if ( empty( $file ) ) {
		$file = dirname ( __FILE__ ) . '/../packages/' . $package . '-package/composer.json';
	}

	if ( ! file_exists ( $file ) ) {
		die( 'PACKAGE: ' . $package . ' could not be found! - ' . $file . ' does not exits!' . PHP_EOL );
	}

	return json_decode( file_get_contents( $file, FILE_USE_INCLUDE_PATH ) );
}
