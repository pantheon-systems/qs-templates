<?php
/**
 * Quicksilver action to update the sites
 */

echo 'Replacing domain names in wp_blogs table' . PHP_EOL;

$env = getenv( 'PANTHEON_ENVIRONMENT' );

// If we don't have a pantheon environment, bail.
if ( empty( $env ) ) {
	return;
}

$domains = [
	'live'    => 'domain.com',
	'test'    => 'test.domain.com',
	'dev'     => 'dev.domain.com',
	'lando'   => 'sitename.lndo.site',
	'default' => $env . '-sitename.pantheonsite.io',
];

// Figure out what the domain is for the current site.
$domain_new = $domains[ $env ] ?: $domains['default'];

// Common wp-cli flags. Make it faster & quieter.
$flags = '--skip-plugins --skip-themes --quiet';

// Get the primary blog's domain.
exec( "wp db query \"SELECT domain FROM wp_blogs WHERE site_id=1 AND blog_id = 1\" $flags", $primary_domain );

// If the database isn't coming from the live site, skip processing.
// We can't trust that we know what to set the blog path to if it's not the live database.
if ( $domains['live'] !== $primary_domain[1] ) {
	echo "Database isn't from live, skipping table processing.";
	return;
}

// Get the list of sites.
exec( "wp db query \"SELECT blog_id, CONCAT(domain, path) as url FROM wp_blogs WHERE site_id=1\" $flags", $sites );

// Remove the header entry.
unset( $sites[0] );

// Update individual site urls.
foreach ( $sites as $site_raw ) {
	$site = explode( "\t", $site_raw );
	$blog_id = intval( $site[0] );
	$url = $site[1];

	if ( 0 >= $blog_id ) {
		continue;
	}

	echo "Processing site #$blog_id $url\n";

	// Strip https:// from the start and / from the end of the url.
	$domain_orig = preg_replace( '/^https?:\/\/(.*)\/$/', '$1', $url );

	// First blog gets root path. All the others get the site url as a path.
	$path = 1 === intval( $blog_id )
		? '/'
		: '/' . str_replace( ['.', '/' ], '-', $domain_orig ) . '/';

	// Trim off leading & trailing dashes.
	$path = trim( $path, '-' );

	// Run search-replace on all of the blog's tables.
	// Search-replace limited to just the blog's tables for speed.
	// Excludes wp_blogs and wp_site as we handle them next.
	passthru( "wp search-replace //{$domain_orig} //{$domain_new}/{$path} --skip-tables=wp_blogs,wp_site --url={$url} $flags" );

	// Update wp_blogs record.
	passthru( "wp db query \"UPDATE wp_blogs SET domain='{$domain_new}', path='{$path}' WHERE site_id=1 AND blog_id={$blog_id}\" $flags" );
}

// Update wp_site domain to the new domain.
passthru( "wp db query \"UPDATE wp_site SET domain='{$domain_new}', path='/' WHERE id=1\" $flags" );
