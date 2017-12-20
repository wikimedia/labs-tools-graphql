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

class WikidataUtils {
	private $wikidataFactory;

	public function getWikibaseFactory() {
		if ( $this->wikidataFactory === null ) {
			$this->wikidataFactory = new WikibaseFactory(
				new MediawikiApi( 'https://www.wikidata.org/w/api.php' ),
				$this->newDataValueDeserializer(),
				$this->newDataValueSerializer()
			);
		}
		return $this->wikidataFactory;
	}

	public function getSerializerFactory() {
		return new SerializerFactory( $this->newDataValueSerializer() );
	}

	public function getDeserializerFactory() {
		return new DeserializerFactory( $this->newDataValueDeserializer(), new BasicEntityIdParser() );
	}

	private function newDataValueSerializer() {
		return new DataValueSerializer();
	}

	private function newDataValueDeserializer() {
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
}
