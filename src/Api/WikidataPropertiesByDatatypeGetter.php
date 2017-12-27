<?php

namespace Tptools\Api;

use Tptools\SparqlClient;

class WikidataPropertiesByDatatypeGetter {

	private $client;

	public function __construct( SparqlClient $client ) {
		$this->client = $client;
	}

	/**
	 * @return string[]
	 */
	public function get() {
		$tuples = $this->client->getTuples(
			'SELECT ?property ?datatype WHERE { ?property wikibase:propertyType ?datatype }'
		);
		$results = [];
		foreach ( $tuples as $tuple ) {
			$results[$this->convertPropertyType( $tuple['datatype'] )][] = $tuple['property'];
		}
		return $results;
	}

	private function convertPropertyType( $propertyType ) {
		$propertyType = str_replace( 'http://wikiba.se/ontology#', '', $propertyType );
		$result = '';
		$len = strlen( $propertyType );
		for ( $i = 0; $i < $len; $i++ ) {
			if ( 'A' <= $propertyType[$i] && $propertyType[$i] <= 'Z' ) {
				$result .= '-' . strtolower( $propertyType[$i] );
			} else {
				$result .= $propertyType[$i];
			}
		}
		return $result;
	}
}
