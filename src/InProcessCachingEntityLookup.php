<?php

namespace Tptools;

use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;

/**
 * TODO: move to WikibaseDataModelServices?
 */
class InProcessCachingEntityLookup implements EntityLookup {

	private $entities = [];

	private $lookup;

	public function __construct( EntityLookup $lookup ) {
		$this->lookup = $lookup;
	}

	/**
	 * @inheritDoc
	 */
	public function getEntity( EntityId $entityId ) {
		$serializedId = $entityId->getSerialization();

		if ( !array_key_exists( $serializedId, $this->entities ) ) {
			$this->entities[$serializedId] = $this->lookup->getEntity( $entityId );
		}

		return $this->entities[$serializedId];
	}

	/**
	 * @inheritDoc
	 */
	public function hasEntity( EntityId $entityId ) {
		$serializedId = $entityId->getSerialization();

		if ( array_key_exists( $serializedId, $this->entities ) ) {
			return $this->entities[$serializedId] !== null;
		} else {
			return $this->lookup->hasEntity( $entityId );
		}
	}
}
