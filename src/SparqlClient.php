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
				} elseif ( array_key_exists( 'datatype', $value ) ) {
					switch ( $value['datatype'] ) {
						case 'http://www.w3.org/2001/XMLSchema#boolean':
							return $value['value'] === 'true' || $value['value'] === '1';
						case 'http://www.w3.org/2001/XMLSchema#double':
						case 'http://www.w3.org/2001/XMLSchema#float':
							return (float)$value['value'];
						case 'http://www.w3.org/2001/XMLSchema#integer':
							return (int)$value['value'];
						default:
							$value['value'];
					}
				} else {
					return $value['value'];
				}
			}, $binding );
		},  $sparqlArray['results']['bindings'] );
	}

	/**
	 * @param string $queryWhereClose with ?entity the selected variable
	 * @param int $limit
	 * @param int $offset
	 * @return EntityId[]
	 */
	public function getEntityIds( $queryWhereClose, $limit = 100, $offset = 0 ) {
		$query = 'SELECT ?entity WHERE { ' . $queryWhereClose  .
			' } LIMIT ' . $limit . ' OFFSET ' . $offset;
		return array_map( function ( $tuple ) {
			return $tuple['entity'];
		}, $this->getTuples( $query ) );
	}

	/**
	 * @param string $queryWhereClose with ?entity the selected variable
	 * @return int
	 */
	public function countEntities( $queryWhereClose ) {
		$query = 'SELECT (COUNT(?entity) AS ?c) WHERE { ' . $queryWhereClose  . ' }';
		$result = $this->getTuples( $query );
		return empty( $result ) ? null : end( $result )['c'];
	}
}
