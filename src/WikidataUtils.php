<?php

namespace Tptools;

use DataValues\Deserializers\DataValueDeserializer;
use DataValues\Geo\Values\LatLongValue;
use DataValues\GlobeCoordinateValue;
use DataValues\MonolingualTextValue;
use DataValues\MultilingualTextValue;
use DataValues\NumberValue;
use DataValues\QuantityValue;
use DataValues\Serializers\DataValueSerializer;
use DataValues\StringValue;
use DataValues\TimeValue;
use Mediawiki\Api\MediawikiApi;
use Wikibase\Api\WikibaseFactory;
use Wikibase\DataModel\DeserializerFactory;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\SerializerFactory;
use Wikibase\DataModel\Services\EntityId\SuffixEntityIdParser;

class WikidataUtils {

	const ENTITY_URI = 'http://www.wikidata.org/entity/';
	private $mediawikiApi;
	private $wikidataFactory;

	public function getWikibaseFactory() {
		return $this->wikidataFactory ?: ( $this->wikidataFactory = new WikibaseFactory(
			$this->getMediawikiApi(),
			$this->newDataValueDeserializer(),
			$this->newDataValueSerializer()
		) );
	}

	public function getMediawikiApi() {
		return $this->mediawikiApi ?: ( $this->mediawikiApi =
			new MediawikiApi( 'https://www.wikidata.org/w/api.php' )
		);
	}

	public function newSerializerFactory() {
		return new SerializerFactory( $this->newDataValueSerializer() );
	}

	public function newDeserializerFactory() {
		return new DeserializerFactory( $this->newDataValueDeserializer(), $this->newEntityIdParser() );
	}

	public function newDataValueSerializer() {
		return new DataValueSerializer();
	}

	public function newDataValueDeserializer() {
		return new DataValueDeserializer( [
			NumberValue::getType() => NumberValue::class,
			StringValue::getType() => StringValue::class,
			LatLongValue::getType() => LatLongValue::class,
			GlobeCoordinateValue::getType() => GlobeCoordinateValue::class,
			MonolingualTextValue::getType() => MonolingualTextValue::class,
			MultilingualTextValue::getType() => MultilingualTextValue::class,
			QuantityValue::getType() => QuantityValue::class,
			TimeValue::getType() => TimeValue::class,
			EntityIdValue::getType() => EntityIdValue::class
		] );
	}

	public function newEntityIdParser() {
		return new BasicEntityIdParser();
	}

	public function newEntityUriParser() {
		return new SuffixEntityIdParser( self::ENTITY_URI, $this->newEntityIdParser() );
	}
}
