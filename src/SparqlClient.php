<?php

namespace Tptools;

use GuzzleHttp\Client;
use Wikibase\DataModel\Entity\ItemId;

class SparqlClient {

	/** @var Client */
	private $client;

	public function __construct( $endpoint = 'https://query.wikidata.org/sparql' ) {
		$this->client = new Client( [
			'base_uri' => $endpoint
		] );
	}

	/**
	 * @param string $queryWhereClose
	 * @return ItemId[]
	 */
	public function getItemIds( $queryWhereClose ) {
		$query = 'SELECT ?item WHERE { ' . $queryWhereClose  . ' }';
		$sparqlResponse = $this->client->get(
			'https://query.wikidata.org/sparql?format=json&query=' . urlencode( $query )
		);
		$sparqlArray = json_decode( $sparqlResponse->getBody(), true );
		$itemIds = [];
		foreach ( $sparqlArray['results']['bindings'] as $binding ) {
			$itemIds[] = new ItemId(
				str_replace( 'http://www.wikidata.org/entity/', '', $binding['item']['value'] )
			);
		}
		return $itemIds;
	}
}
