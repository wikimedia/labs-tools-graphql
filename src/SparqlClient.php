<?php

namespace Tptools;

use GuzzleHttp\Client;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;

class SparqlClient {

	private $client;
	private $entityUriParser;

	public function __construct(
		$endpoint = 'https://query.wikidata.org/sparql',
		EntityIdParser $entityUriParser = null
	) {
		$this->client = new Client( [
			'base_uri' => $endpoint
		] );
		$this->entityUriParser = $entityUriParser ?: ( new WikidataUtils() )->newEntityUriParser();
	}

	/**
	 * @param string $query
	 * @return mixed[]
	 */
	public function getTuples( $query ) {
		$sparqlResponse = $this->client->get( '?format=json&query=' . urlencode( $query ) );
		$sparqlArray = json_decode( $sparqlResponse->getBody(), true );
		return array_map( function ( $binding ) {
			return array_map( function ( $value ) {
				if ( strpos( $value['value'], WikidataUtils::ENTITY_URI ) === 0 ) {
					return $this->entityUriParser->parse( $value['value'] );
				} else {
					return $value['value'];
				}
			}, $binding );
		},  $sparqlArray['results']['bindings'] );
	}

	/**
	 * @param string $queryWhereClose with ?entity the selected variable
	 * @param int $limit
	 * @return EntityId[]
	 */
	public function getEntityIds( $queryWhereClose, $limit = 100 ) {
		return array_map( function ( $tuple ) {
			return $tuple['entity'];
		}, $this->getTuples( 'SELECT ?entity WHERE { ' . $queryWhereClose  . ' } LIMIT ' . $limit ) );
	}
}
