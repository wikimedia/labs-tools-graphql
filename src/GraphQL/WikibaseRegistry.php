<?php

namespace Tptools\GraphQL;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Utils\Utils;
use Tptools\Api\WikibaseLanguageCodesGetter;
use Tptools\Api\WikibaseSitesGetter;
use Tptools\Api\WikidataPropertiesByDatatypeGetter;
use Tptools\SparqlClient;
use Tptools\WikidataUtils;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\EntityRetrievingDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\ItemLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyLookup;

class WikibaseRegistry {

	private $wikibaseDataModelRegistry;
	private $entityIdParser;
	private $entityLookup;
	private $itemLookup;
	private $propertyLookup;

	public function __construct(
		array $availableLanguageCodes, array $availableSites, array $propertiesByDatatype,
		EntityIdParser $entityIdParser, EntityIdParser $entityUriParser, EntityLookup $entityLookup,
		ItemLookup $itemLookup, PropertyLookup $propertyLookup,
		PropertyDataTypeLookup $propertyDataTypeLookup
	) {
		$this->wikibaseDataModelRegistry = new WikibaseDataModelRegistry(
			$availableLanguageCodes, $availableSites, $propertiesByDatatype,
			$entityLookup, $propertyDataTypeLookup, $entityIdParser, $entityUriParser
		);
		$this->entityIdParser = $entityIdParser;
		$this->entityLookup = $entityLookup;
		$this->itemLookup = $itemLookup;
		$this->propertyLookup = $propertyLookup;
	}

	public function schema() {
		$config = SchemaConfig::create()
			->setQuery( $this->query() )
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
				'entity' => [
					'type' => $this->wikibaseDataModelRegistry->entity(),
					'args' => [
						'id' => [
							'type' => Type::nonNull( Type::id() )
						]
					],
					'resolve' => function ( $value, $args ) {
						$entityId = $this->parseEntityId( $args['id'] );
						return $this->entityLookup->getEntity( $entityId );
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
						$entityId = $this->parseEntityId( $args['id'] );
						if ( $entityId instanceof ItemId ) {
							return $this->itemLookup->getItemForId( $entityId );
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
						$entityId = $this->parseEntityId( $args['id'] );
						if ( $entityId instanceof PropertyId ) {
							return $this->propertyLookup->getPropertyForId( $entityId );
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

	private function parseEntityId( $serialization ) {
		try {
		   return $this->entityIdParser->parse( $serialization );
		} catch ( EntityIdParsingException $e ) {
			throw new ApiException( $e->getMessage(), $e->getCode(), $e );
		}
	}

	public static function newForWikidata() {
		$wikidataUtils = new WikidataUtils();
		$wikibaseFactory = $wikidataUtils->getWikibaseFactory();
		$languageCodesGetter = new WikibaseLanguageCodesGetter( $wikidataUtils->getMediawikiApi() );
		$sitesGetter = new WikibaseSitesGetter( $wikidataUtils->getMediawikiApi() );
		$propertiesByDatatypeGetter = new WikidataPropertiesByDatatypeGetter( new SparqlClient() );
		// TODO: cache
		return new self(
			$languageCodesGetter->get(),
			$sitesGetter->get(),
			$propertiesByDatatypeGetter->get(),
			$wikidataUtils->newEntityIdParser(),
			$wikidataUtils->newEntityUriParser(),
			$wikibaseFactory->newEntityLookup(),
			$wikibaseFactory->newItemLookup(),
			$wikibaseFactory->newPropertyLookup(),
			new EntityRetrievingDataTypeLookup( $wikibaseFactory->newEntityLookup() )
		);
	}
}
