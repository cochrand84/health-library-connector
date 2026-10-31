<?php
/**
 * Content source contract.
 *
 * Both the live Mayo API client and the bundled demo source implement this,
 * and both return data in the shape the Mayo Realtime API returns, so the
 * templates never need to know which one is active.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

interface HLMC_Source {

	/**
	 * Keyword search.
	 *
	 * @param string $query Search terms ('*' for "anything").
	 * @param int    $top   Number of results.
	 * @param int    $skip  Offset for paging.
	 * @return array|WP_Error { count: int, searchResults: array[] }
	 */
	public function search( $query, $top = 10, $skip = 0 );

	/**
	 * Fetch one piece of content by Mayo content ID (e.g. CON-20372695).
	 *
	 * @param string $content_id Content ID.
	 * @return array|WP_Error { meta: array, body: { section: array[] }, title, abstract, ... }
	 */
	public function get_content( $content_id );

	/**
	 * Fetch an image binary by its GUID.
	 *
	 * @param string $guid Image GUID (GUID-xxxxxxxx-...).
	 * @return array|WP_Error { type: string, body: string }
	 */
	public function get_image( $guid );

	/**
	 * Whether this source serves real Mayo Clinic content (controls the
	 * required attribution and canonical tag).
	 *
	 * @return bool
	 */
	public function is_live();
}
