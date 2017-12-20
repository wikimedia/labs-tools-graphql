<?php

namespace Tptools;

use DataValues\DataValue;
use DataValues\TimeValue;
use linclark\MicrodataPHP\MicrodataPhp;
use Serializers\Serializer;
use Wikibase\Api\Service\RevisionGetter;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\ReferenceList;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;

class MicrodataToWikidataConverter {
	/** @var RevisionGetter */
	private $wikidataRevisionGetter;
	/** @var ItemId */
	private $wikiItem;
	/** @var Serializer */
	private $itemSerializer;
	/** @var SparqlClient */
	private $sparqlClient;

	public function __construct() {
		$wikidataUtils = new WikidataUtils();
		$this->wikidataRevisionGetter = $wikidataUtils->getWikibaseFactory()->newRevisionGetter();
		$this->wikiItem = new ItemId( 'Q15156541' );
		$this->itemSerializer = $wikidataUtils->getSerializerFactory()->newItemSerializer();
		$this->sparqlClient = new SparqlClient();
	}

	public function toWikidata( $title ) {
		$microdata = ( new MicrodataPhp(
			'https://fr.wikisource.org/wiki/' . str_replace( ' ', '_', $title ) )
		)->obj();
		if ( count( $microdata->items ) ) {
			$entity = $microdata->items[0];
		} else {
			$entity = (object)[
				'properties' => []
			];
		}

		$revision = $this->wikidataRevisionGetter->getFromSiteAndTitle( 'frwikisource', $title );
		if ( !$revision ) {
			// print("$title has no item\n");
			$item = new Item();
			$item->getSiteLinkList()->addNewSiteLink( 'frwikisource', $title );
		} else {
			/** @var Item $item */
			$item = $revision->getContent()->getData();
		}

		$fingerprint = $item->getFingerprint();
		if ( array_key_exists( 'name', $entity->properties ) && !$fingerprint->hasLabel( 'fr' ) ) {
			// TODO: safe?
			$fingerprint->setLabel( 'fr', $entity->properties['name'][0] );
		}
		// TODO: type
		$this->addItemRelation( $entity, $item, 'author', 'P50' );
		$this->addItemRelation( $entity, $item, 'editor', 'P98' );
		$this->addItemRelation( $entity, $item, 'translator', 'P655' );
		$this->addItemRelation( $entity, $item, 'illustrator', 'P110' );
		$this->addItemRelation( $entity, $item, 'publisher', 'P98', 'Q2085381' );
		$this->addYearRelation( $entity, $item, 'datePublished', 'P577' );
		// TODO $this->addItemRelation(
		// $entity, $item, 'http://purl.org/library/placeOfPublication', 'P291', 'Q2221906');
		// TODO: badges

		return $this->itemSerializer->serialize( $item );
	}

	private function addItemRelation( $entity, $item, $schemaRelation, $wdProperty, $wdClass = null ) {
		if ( array_key_exists( $schemaRelation, $entity->properties ) ) {
			foreach ( $entity->properties[$schemaRelation] as $childEntity ) {
				$authorItemId = $this->getItemIdForEntity( $childEntity, $wdClass );
				if ( $authorItemId !== null ) {
					$this->addStatement( $item, new EntityIdValue( $authorItemId ), $wdProperty );
				}
			}
		}
	}

	private function getItemIdForEntity( $entity, $wdClass = null ) {
		if ( is_string( $entity ) ) {
			$entity = (object)[
				'properties' => [ 'name' => [ $entity ] ]
			];
		}
		if ( array_key_exists( 'url', $entity->properties ) ) {
			$title = str_replace( 'https://fr.wikisource.org/wiki/', '', $entity->properties['url'][0] );
			$revision = $this->wikidataRevisionGetter->getFromSiteAndTitle(
				'frwikisource', urldecode( $title )
			);
			if ( $revision ) {
				return $revision->getContent()->getData()->getId();
			}
		} elseif ( $wdClass !== null && array_key_exists( 'name', $entity->properties ) ) {
			$name = json_encode( $entity->properties['name'][0] );
			$itemIds = $this->sparqlClient->getItemIds(
				'{ ?item rdfs:label ' . $name . '@fr } UNION 
				{ ?item skos:altLabel ' . $name . '@fr } .
				?item wdt:P31/wdt:P279* wd:' . $wdClass
			);
			if ( count( $itemIds ) === 1 ) {
				return $itemIds[0];
			}
		}
		return null;
	}

	private function addStatement( Item $item, DataValue $value, $prop ) {
		$propertyId = new PropertyId( $prop );
		if ( !$item->getStatements()->getByPropertyId( $propertyId )->isEmpty() ) {
			// We already have a value, we skip
			return;
		}

		$statement = new Statement( new PropertyValueSnak( $propertyId, $value ) );
		// TODO $statement->setGuid((new GuidGenerator())->newGuid($item->getId()));
		$statement->setReferences( $this->buildImportedFromReferences( $this->wikiItem ) );
		$item->getStatements()->addStatement( $statement );
	}

	private function buildImportedFromReferences( ItemId $itemId ) {
		$snaks = new SnakList();
		$snaks->addSnak( new PropertyValueSnak(
			new PropertyId( 'p143' ),
			new EntityIdValue( $itemId )
		) );
		$references = new ReferenceList();
		$references->addReference( new Reference( $snaks ) );
		return $references;
	}

	private function addYearRelation( $entity, $item, $schemaRelation, $wdProperty ) {
		if ( array_key_exists( $schemaRelation, $entity->properties ) ) {
			foreach ( $entity->properties[$schemaRelation] as $year ) {
				if ( is_numeric( $year ) && strlen( $year ) === 4 ) {
					$date = new TimeValue(
						'+' . $year . '-00-00T00:00:00Z',
						0, 0, 0,
						TimeValue::PRECISION_YEAR,
						TimeValue::CALENDAR_GREGORIAN
					);
					$this->addStatement( $item, $date, $wdProperty );
				}
				// TODO: broader support?
			}
		}
	}
}
