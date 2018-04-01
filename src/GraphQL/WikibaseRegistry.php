<?php

namespace Tptools\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Utils\Utils;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use Tptools\InProcessCachingEntityLookup;
use Tptools\SparqlClient;
use Tptools\WikidataUtils;
use Wikibase\Api\Service\LabelSetter;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Term\Term;

class WikibaseRegistry {

	// 1 day
	const CONFIGURATION_CACHE_TTL = 86400;

	// Query limit
	const MAX_QUERY_SIZE = 500;

	private $wikibaseDataModelRegistry;
	private $entityIdParser;
	private $entityLookup;
	private $sparqlClient;
	private $labelSetter;

	public function __construct(
		EntityIdParser $entityIdParser, EntityIdParser $entityUriParser, EntityLookup $entityLookup,
		PropertyDataTypeLookup $propertyDataTypeLookup, SparqlClient $sparqlClient,
		LabelSetter $labelSetter
	) {
		$this->wikibaseDataModelRegistry = new WikibaseDataModelRegistry(
			$entityLookup, $propertyDataTypeLookup,
			$entityIdParser, $entityUriParser
		);
		$this->entityIdParser = $entityIdParser;
		$this->entityLookup = $entityLookup;
		$this->sparqlClient = $sparqlClient;
		$this->labelSetter = $labelSetter;
	}

	public function schema() {
		$config = SchemaConfig::create()
			->setQuery( $this->query() )
			->setMutation( $this->mutation() )
			->setTypes( [
				$this->wikibaseDataModelRegistry->propertyValueSnak(),
				$this->wikibaseDataModelRegistry->propertySomeValueSnak(),
				$this->wikibaseDataModelRegistry->propertyNoValueSnak(),
				$this->wikibaseDataModelRegistry->stringValue(),
				$this->wikibaseDataModelRegistry->monolingualTextValue(),
				$this->wikibaseDataModelRegistry->globeCoordinateValue(),
				$this->wikibaseDataModelRegistry->quantityValue(),
				$this->wikibaseDataModelRegistry->timeValue(),
				$this->wikibaseDataModelRegistry->unknownValue()
			] );
		return new Schema( $config );
	}

	private function query() {
		return new ObjectType( [
			'name' => 'Query',
			'fields' => [
				'node' => $this->wikibaseDataModelRegistry->nodeField(),
				'entity' => [
					'type' => $this->wikibaseDataModelRegistry->entity(),
					'args' => [
						'id' => [
							'type' => Type::nonNull( Type::id() )
						]
					],
					'resolve' => function ( $value, $args ) {
						$entityId = $this->wikibaseDataModelRegistry->parseEntityId( $args['id'] );
						return $this->entityLookup->getEntity( $entityId );
					}
				],
				'findEntitiesWithSPARQL' => [
					'type' => Relay::connectionDefinitions( [
						'nodeType' => $this->wikibaseDataModelRegistry->entity()
					] )['connectionType'],
					'description' => 'Entities retrieved with a SPARQL query',
					'args' => Relay::forwardConnectionArgs() + [
						'where' => [
							'type' => Type::nonNull( Type::string() ),
							'description' => 'The WHERE close of the SPARQL query ' .
								'with ?entity the variable that should be returned. ' .
								'It is the "..." part of "SELECT ?entity WHERE { ... }"'
						]
					],
					'resolve' => function ( $value, $args ) {
						// TODO: support backward?
						$after = self::getArgSafe( $args, 'after' );
						$first = self::getArgSafe( $args, 'first' );

						$offset = ArrayConnection::getOffsetWidthDefault( $after, 0 );
						$limit = $first === null
							? self::MAX_QUERY_SIZE
							: min( self::MAX_QUERY_SIZE, $first );

						$data = array_map( function ( EntityId $entityId ) {
							return $this->entityLookup->getEntity( $entityId );
						}, $this->sparqlClient->getEntityIds( $args['where'], $limit, $offset ) );
						return Relay::connectionFromArraySlice( $data, $args, [
							'sliceStart' => $offset,
							'arrayLength' => $this->sparqlClient->countEntities( $args['where'] )
						] );
					}
				],
				'item' => [
					'type' => $this->wikibaseDataModelRegistry->item(),
					'args' => [
						'id' => [
							'type' => Type::nonNull( Type::id() )
						]
					],
					'resolve' => function ( $value, $args ) {
						$entityId = $this->wikibaseDataModelRegistry->parseEntityId( $args['id'] );
						if ( $entityId instanceof ItemId ) {
							return $this->entityLookup->getEntity( $entityId );
						} else {
							throw new ApiException(
								Utils::printSafeJson( $entityId->getSerialization() ) . ' is not an item id.'
							);
						}
					}
				],
				'property' => [
					'type' => $this->wikibaseDataModelRegistry->property(),
					'args' => [
						'id' => [
							'type' => Type::nonNull( Type::id() )
						]
					],
					'resolve' => function ( $value, $args ) {
						$entityId = $this->wikibaseDataModelRegistry->parseEntityId( $args['id'] );
						if ( $entityId instanceof PropertyId ) {
							return $this->entityLookup->getEntity( $entityId );
						} else {
							throw new ApiException(
								Utils::printSafeJson( $entityId->getSerialization() ) . ' is not a property id.'
							);
						}
					}
				]
			]
		] );
	}

	private function mutation() {
		return new ObjectType( [
			'name' => 'Mutation',
			'fields' => [
				'setLabel' => Relay::mutationWithClientMutationId( [
					'name' => 'SetLabel',
					'description' => 'Sets a label for a single Wikibase entity',
					'inputFields' => [
						'id' => [
							'type' => Type::nonNull( Type::id() ),
							'description' => 'The identifier for the entity, including the prefix'
						],
						'language' => [
							'type' => Type::nonNull( Type::string() ),
							'description' => 'Language of the label'
						],
						'value' => [
							'type' => Type::nonNull( Type::string() ),
							'description' => 'The value of the label'
						]
					],
					'outputFields' => [
					],
					'mutateAndGetPayload' => function ( $args ) {
						$entityId = $this->wikibaseDataModelRegistry->parseEntityId( $args['id'] );
						$this->labelSetter->set( new Term( $args['language'], $args['value'] ), $entityId );
						return [];
					}
				] )
			]
		] );
	}

	public static function newForWikidata() {
		$wikidataUtils = new WikidataUtils();
		$wikibaseFactory = $wikidataUtils->getWikibaseFactory();
		$sparqlClient = new SparqlClient();
		$entityLookup = new InProcessCachingEntityLookup( $wikibaseFactory->newEntityLookup() );

		return new self(
			$wikidataUtils->newEntityIdParser(),
			$wikidataUtils->newEntityUriParser(),
			$entityLookup,
			new EntityRetrievingDataTypeLookup( $entityLookup ),
			$sparqlClient,
			$wikibaseFactory->newLabelSetter()
		);
	}

	private function getArgSafe( $args, $name ) {
		return array_key_exists( $name, $args ) ? $args[$name] : null;
	}
}
