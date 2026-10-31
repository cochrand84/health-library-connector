<?php
/**
 * "Find a specialist" sidebar data.
 *
 * Mayo article responses include meta.controlWords.category, a tree of
 * Mayo Controlled Vocabulary nodes each carrying an `mcvId`. Sites map the
 * IDs they care about to a specialty, and each specialty to providers.
 *
 * The mapping is stored as JSON in the plugin settings:
 *
 * {
 *   "fallback": "Internal Medicine",
 *   "specialties": { "1452": "Gastroenterology", "1104": "OB/GYN" },
 *   "providers": {
 *     "Gastroenterology": [
 *       { "name": "Dr. Jane Doe", "title": "Gastroenterologist",
 *         "phone": "(555) 555-0100", "url": "https://example.org/doctors/jane-doe" }
 *     ]
 *   }
 * }
 *
 * It can also be supplied in code via the `hlmc_provider_map` filter.
 * Carried over from the prototype's doctors.php.
 *
 * @package HealthLibraryForMayoClinic
 */

defined( 'ABSPATH' ) || exit;

class HLMC_Providers {

	/**
	 * Example mapping used to pre-fill the settings box and in demo mode.
	 * Names and numbers are fictional placeholders.
	 *
	 * @return array
	 */
	public static function example_map() {
		return array(
			'fallback'    => 'Internal Medicine',
			'specialties' => array(
				'1092'   => 'Internal Medicine',
				'1104'   => 'OB/GYN',
				'1137'   => 'Cardiology',
				'1255'   => 'Pulmonology',
				'1452'   => 'Gastroenterology',
				'1770'   => 'Pain Management',
				'1898'   => 'OB/GYN',
				'1006'   => 'Wellness',
				'1090'   => 'Sports Medicine',
				'1094'   => 'Nutrition',
				'1100'   => 'Sports Medicine',
				'2082'   => 'Oncology',
				'2375'   => 'Oncology',
				'2926'   => 'Gastroenterology',
				'127048' => 'Sports Medicine',
				'138500' => 'Gastroenterology',
			),
			'providers'   => array(
				'Cardiology'        => array(
					array( 'name' => 'Dr. Sarah Mitchell', 'title' => 'Cardiologist', 'phone' => '(555) 201-1000', 'url' => '' ),
					array( 'name' => 'Dr. James Harlow', 'title' => 'Cardiologist', 'phone' => '(555) 201-1001', 'url' => '' ),
				),
				'Gastroenterology'  => array(
					array( 'name' => 'Dr. Linda Park', 'title' => 'Gastroenterologist', 'phone' => '(555) 201-2000', 'url' => '' ),
					array( 'name' => 'Dr. Tom Reyes', 'title' => 'Gastroenterologist', 'phone' => '(555) 201-2001', 'url' => '' ),
				),
				'Oncology'          => array(
					array( 'name' => 'Dr. Rachel Nguyen', 'title' => 'Oncologist', 'phone' => '(555) 201-3000', 'url' => '' ),
					array( 'name' => 'Dr. Brian Foster', 'title' => 'Oncologist', 'phone' => '(555) 201-3001', 'url' => '' ),
				),
				'OB/GYN'            => array(
					array( 'name' => "Dr. Maria O'Brien", 'title' => 'OB/GYN', 'phone' => '(555) 201-4000', 'url' => '' ),
					array( 'name' => 'Dr. Susan Caldwell', 'title' => 'OB/GYN', 'phone' => '(555) 201-4001', 'url' => '' ),
				),
				'Pain Management'   => array(
					array( 'name' => 'Dr. Kevin Walsh', 'title' => 'Pain Specialist', 'phone' => '(555) 201-5000', 'url' => '' ),
				),
				'Pulmonology'       => array(
					array( 'name' => 'Dr. Alan Torres', 'title' => 'Pulmonologist', 'phone' => '(555) 201-6000', 'url' => '' ),
				),
				'Sports Medicine'   => array(
					array( 'name' => 'Dr. Chris Daniels', 'title' => 'Sports Medicine', 'phone' => '(555) 201-7000', 'url' => '' ),
				),
				'Nutrition'         => array(
					array( 'name' => 'Dr. Amy Chen', 'title' => 'Dietitian', 'phone' => '(555) 201-8000', 'url' => '' ),
				),
				'Internal Medicine' => array(
					array( 'name' => 'Dr. Robert Ellis', 'title' => 'Internal Medicine', 'phone' => '(555) 201-9000', 'url' => '' ),
					array( 'name' => 'Dr. Paula Greene', 'title' => 'Internal Medicine', 'phone' => '(555) 201-9001', 'url' => '' ),
				),
				'Wellness'          => array(
					array( 'name' => 'Dr. Diane Brooks', 'title' => 'Wellness Physician', 'phone' => '(555) 201-0000', 'url' => '' ),
				),
			),
		);
	}

	/**
	 * The active mapping.
	 *
	 * @return array
	 */
	public static function map() {
		$json = (string) HLMC_Settings::get( 'providers_json' );
		$map  = '' !== $json ? json_decode( $json, true ) : null;
		if ( ! is_array( $map ) ) {
			$map = HLMC_Settings::is_live() ? array() : self::example_map();
		}
		$map = wp_parse_args(
			$map,
			array(
				'fallback'    => '',
				'specialties' => array(),
				'providers'   => array(),
			)
		);
		/**
		 * Filter the mcvId → specialty → provider mapping.
		 *
		 * @param array $map { fallback: string, specialties: array, providers: array }.
		 */
		return apply_filters( 'hlmc_provider_map', $map );
	}

	/**
	 * Collect every mcvId anywhere in a controlWords tree.
	 *
	 * @param mixed $node Tree node.
	 * @return string[]
	 */
	public static function mcv_ids( $node ) {
		$ids = array();
		if ( ! is_array( $node ) ) {
			return $ids;
		}
		if ( isset( $node['mcvId'] ) && is_scalar( $node['mcvId'] ) ) {
			$ids[] = (string) $node['mcvId'];
		}
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				$ids = array_merge( $ids, self::mcv_ids( $value ) );
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Match an article to a specialty and its providers.
	 *
	 * @param array $article Article response.
	 * @return array|null { specialty: string, providers: array[] } or null.
	 */
	public static function match( array $article ) {
		$map = self::map();
		$ids = self::mcv_ids( $article['meta']['controlWords'] ?? array() );

		$specialty = '';
		foreach ( $ids as $id ) {
			$candidate = $map['specialties'][ $id ] ?? '';
			if ( $candidate && ! empty( $map['providers'][ $candidate ] ) ) {
				$specialty = $candidate;
				break;
			}
		}
		if ( ! $specialty && $map['fallback'] && ! empty( $map['providers'][ $map['fallback'] ] ) ) {
			$specialty = $map['fallback'];
		}

		$result = $specialty ? array(
			'specialty' => $specialty,
			'providers' => array_values( (array) $map['providers'][ $specialty ] ),
		) : null;

		/**
		 * Filter the specialist match for an article.
		 *
		 * @param array|null $result  Match or null.
		 * @param array      $article Article response.
		 * @param string[]   $ids     mcvIds found in the article.
		 */
		return apply_filters( 'hlmc_provider_match', $result, $article, $ids );
	}
}
