<?php

namespace Tptools\GraphQL;

use DataValues\DataValue;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\MonolingualTextValue;
use DataValues\QuantityValue;
use DataValues\StringValue;
use DataValues\TimeValue;
use DataValues\UnboundedQuantityValue;
use DataValues\UnknownValue;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use OutOfBoundsException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\SiteLinkList;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\DataModel\Term\Term;
use Wikibase\DataModel\Term\TermList;

class WikibaseDataModelRegistry {

	private $availableLanguageCodes;
	private $availableSites;
	private $propertiesByDatatype;
	private $entityLookup;
	private $propertyDataTypeLookup;
	private $entityIdParser;
	private $entityUriParser;

	private $value;
	private $valueType;
	private $entity;
	private $item;
	private $property;
	private $termList;
	private $term;
	private $aliasGroupList;
	private $siteLinkList;
	private $siteLink;
	private $statementList;
	private $statement;
	private $rank;
	private $reference;
	private $snakList;
	private $snak;
	private $snakType;
	private $propertyValueSnak;
	private $propertySomeValueSnak;
	private $propertyNoValueSnak;
	private $stringValue;
	private $monolingualTextValue;
	private $globeCoordinateValue;
	private $quantityValue;
	private $timeValue;
	private $timePrecision;
	private $unknownValue;

	/** .
		* @param string[] $availableLanguageCodes
		* @param string[] $availableSites
		* @param PropertyId[][] $propertiesByDatatype
		*/
	public function __construct(
		array $availableLanguageCodes, array $availableSites, array $propertiesByDatatype,
		EntityLookup $entityLookup, PropertyDataTypeLookup $propertyDataTypeLookup,
		EntityIdParser $entityIdParser, EntityIdParser $entityUriParser
	) {
		$this->availableSites = $availableSites;
		$this->availableLanguageCodes = $availableLanguageCodes;
		$this->propertiesByDatatype = $propertiesByDatatype;
		$this->entityLookup = $entityLookup;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->entityIdParser = $entityIdParser;
		$this->entityUriParser = $entityUriParser;
	}

	public function value() {
		return $this->value ?: ( $this->value = new InterfaceType( [
			'name' => 'Value',
			'description' => 'a value like an Entity or a DataValue',
			'fields' => [
				'type' => [
					'type' => Type::nonNull( $this->valueType() ),
					'description' => 'type of the value',
					'resolve' => function ( $value ) {
						if ( $value instanceof EntityDocument ) {
							return $value->getType();
						} elseif ( $value instanceof EntityId ) {
							return $value->getEntityType();
						} elseif ( $value instanceof EntityIdValue ) {
							return $value->getEntityId()->getEntityType();
						} elseif ( $value instanceof DataValue ) {
							return $value::getType();
						} else {
							throw new ApiException( 'Unsupported value: ' . Utils::printSafeJson( (string)$value ) );
						}
					}
				]
			],
			'resolveType' => function ( $value ) {
				if ( $value instanceof EntityDocument ) {
					return $this->entityObjectForType( $value->getType() );
				} elseif ( $value instanceof EntityId ) {
					return $this->entityObjectForType( $value->getEntityType() );
				} elseif ( $value instanceof EntityIdValue ) {
					return $this->entityObjectForType( $value->getEntityId()->getEntityType() );
				} elseif ( $value instanceof DataValue ) {
					return $this->dataValueObjectForType( $value::getType() );
				} else {
					throw new ApiException( 'Unsupported value: ' . Utils::printSafeJson( (string)$value ) );
				}
			}
		] ) );
	}

	public function valueType() {
		return $this->valueType ?: ( $this->valueType = new EnumType( [
			'name' => 'ValueType',
			'description' => 'type identifier of a value',
			'values' => [
				'ITEM' => [
					'value' => Item::ENTITY_TYPE
				],
				'PROPERTY' => [
					'value' => Property::ENTITY_TYPE
				],
				'STRING' => [
					'value' => StringValue::getType()
				],
				'MONOLINGUAL_TEXT' => [
					'value' => MonolingualTextValue::getType()
				],
				'GLOBE_COORDINATE' => [
					'value' => GlobeCoordinateValue::getType()
				],
				'QUANTITY' => [
					'value' => UnboundedQuantityValue::getType()
				],
				'TIME' => [
					'value' => TimeValue::getType()
				]
			]
		] ) );
	}

	private function dataValueObjectForType( $type ) {
		switch ( $type ) {
			case StringValue::getType():
				return $this->stringValue();
			case MonolingualTextValue::getType():
				return $this->monolingualTextValue();
			case GlobeCoordinateValue::getType():
				return $this->globeCoordinateValue();
			case UnboundedQuantityValue::getType():
				return $this->quantityValue();
			case TimeValue::getType():
				return $this->timeValue();
			case UnknownValue::getType():
				return $this->unknownValue();
			default:
				throw new ApiException( 'Unsupported value type: ' . Utils::printSafeJson( $type ) );
		}
	}

	public function entity() {
		return $this->entity ?: ( $this->entity = new InterfaceType( [
			'name' => 'Entity',
			'description' => 'an entity like an item or a property',
			'fields' => $this->value()->getFields() + [
				'id' => [
					'type' => Type::nonNull( Type::id() ),
					'description' => 'id of the entity',
					'resolve' => function ( EntityDocument $value ) {
						return $value->getId()->getSerialization();
					}
				]
			],
			'resolveType' => function ( EntityDocument $value ) {
				return $this->entityObjectForType( $value->getType() );
			}
		] ) );
	}

	private function entityObjectForType( $type ) {
		switch ( $type ) {
			case Item::ENTITY_TYPE:
				return $this->item();
			case Property::ENTITY_TYPE:
				return $this->property();
			default:
				throw new ApiException( 'Unsupported entity type: ' . Utils::printSafeJson( $type ) );
		}
	}

	public function item() {
		return $this->item ?: ( $this->item = new ObjectType( [
			'name' => 'Item',
			'interfaces' => [ $this->value(), $this->entity() ],
			'fields' =>
				$this->value()->getFields() +
				$this->entity()->getFields() +
				$this->fingerprintProviderFields() +
				$this->statementListProviderFields() + [
					'sitelinks' => [
						'type' => Type::nonNull( $this->siteLinkList() ),
						'resolve' => function ( Item $value ) {
							return $value->getSiteLinkList();
						}
					]
				]
		] ) );
	}

	public function property() {
		return $this->property ?: ( $this->property = new ObjectType( [
			'name' => 'Property',
			'interfaces' => [ $this->value(), $this->entity() ],
			'fields' =>
				$this->value()->getFields() +
				$this->entity()->getFields() +
				$this->fingerprintProviderFields() +
				$this->statementListProviderFields() + [
					'datatype' => [
						'type' => Type::nonNull( Type::id() ),
						'resolve' => function ( Property $value ) {
							return $value->getDataTypeId();
						}
					]
				]
		] ) );
	}

	private function fingerprintProviderFields() {
		return [
			'labels' => [
				'type' => Type::nonNull( $this->termList() ),
				'description' => 'labels of the entity (unique per language)',
				'resolve' => function ( FingerprintProvider $value ) {
					return $value->getFingerprint()->getLabels();
				}
			],
			'descriptions' => [
				'type' => Type::nonNull( $this->termList() ),
				'description' => 'descriptions of the entity (unique per language)',
				'resolve' => function ( FingerprintProvider $value ) {
					return $value->getFingerprint()->getDescriptions();
				}
			],
			'aliases' => [
				'type' => Type::nonNull( $this->aliasGroupList() ),
				'description' => 'aliases of the entity)',
				'resolve' => function ( FingerprintProvider $value ) {
					return $value->getFingerprint()->getAliasGroups();
				}
			],
		];
	}

	public function termList() {
		return $this->termList ?: ( $this->termList = new ObjectType( [
			'name' => 'TermList',
			'fields' => function () {
				return $this->buildFieldsForAllLanguages( function ( $languageCode ) {
					return [
						'type' => $this->term(),
						'resolve' => function ( TermList $value ) use ( $languageCode ) {
							try {
								return $value->getByLanguage( $languageCode );
							} catch ( OutOfBoundsException $e ) {
								return null;
							}
						}
					];
				} );
			}
		] ) );
	}

	private function buildFieldsForAllLanguages( callable $fieldBuilder ) {
		$fields = [];
		foreach ( $this->availableLanguageCodes as $languageCode ) {
			$fields[str_replace( '-', '_', $languageCode )] = $fieldBuilder( $languageCode );
		}
		return $fields;
	}

	public function term() {
		return $this->term ?: ( $this->term = new ObjectType( [
			'name' => 'Term',
			'fields' => [
				'language' => [
					'type' => Type::nonNull( Type::string() ),
					'description' => 'language code of the text',
					'resolve' => function ( $value ) {
						if ( $value instanceof Term ) {
							return $value->getLanguageCode();
						} else {
							throw new InvariantViolation( 'Term.language should get a Term' );
						}
					}
				],
				'value' => [
					'type' => Type::nonNull( Type::string() ),
					'description' => 'text',
					'resolve' => function ( $value ) {
						if ( $value instanceof Term ) {
							return $value->getText();
						} else {
							throw new InvariantViolation( 'Term.text should get a Term' );
						}
					}
				]
			]
		] ) );
	}

	public function aliasGroupList() {
		return $this->aliasGroupList ?: ( $this->aliasGroupList = new ObjectType( [
			'name' => 'AliasGroupList',
			'fields' => function () {
				return $this->buildFieldsForAllLanguages( function ( $languageCode ) {
					return [
						'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->term() ) ) ),
						'resolve' => function ( AliasGroupList $value ) use ( $languageCode ) {
							try {
								return array_map( function ( $alias ) use ( $languageCode ) {
									return new Term( $languageCode, $alias );
								}, $value->getByLanguage( $languageCode )->getAliases() );
							} catch ( OutOfBoundsException $e ) {
								return [];
							}
						}
					];
				} );
			}
		] ) );
	}

	public function siteLinkList() {
		return $this->siteLinkList ?: ( $this->siteLinkList = new ObjectType( [
			'name' => 'SiteLinkList',
			'fields' => function () {
				return $this->buildFieldsForAllSites( function ( $siteId ) {
					return [
						'type' => $this->siteLink(),
						'resolve' => function ( SiteLinkList $value ) use ( $siteId ) {
							try {
								return $value->getBySiteId( $siteId );
							} catch ( OutOfBoundsException $e ) {
								return null;
							}
						}
					];
				} );
			}
		] ) );
	}

	private function buildFieldsForAllSites( callable $fieldBuilder ) {
		$fields = [];
		foreach ( $this->availableSites as $site ) {
			$fields[str_replace( '-', '_', $site )] = $fieldBuilder( $site );
		}
		return $fields;
	}

	public function siteLink() {
		return $this->siteLink ?: ( $this->siteLink = new ObjectType( [
			'name' => 'SiteLink',
			'fields' => [
				'site' => [
					'type' => Type::nonNull( Type::string() ),
					'description' => 'site id',
					'resolve' => function ( SiteLink $value ) {
						return $value->getSiteId();
					}
				],
				'title' => [
					'type' => Type::nonNull( Type::string() ),
					'description' => 'page title',
					'resolve' => function ( SiteLink $value ) {
						return $value->getPageName();
					}
				],
				'badges' => [
					'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->item() ) ) ),
					'description' => 'any "badges" associated with the page (such as "featured article")',
					'resolve' => function ( SiteLink $value ) {
						return array_map( function ( ItemId $itemId ) {
							return $this->getEntityWithEmpty( $itemId );
						}, $value->getBadges() );
					}
				]
			]
		] ) );
	}

	private function statementListProviderFields() {
		return [
			'claims' => [
				'type' => Type::nonNull( $this->statementList() ),
				'description' => 'labels of the entity (unique per language)',
				'resolve' => function ( StatementListProvider $value ) {
					return $value->getStatements();
				}
			]
		];
	}

	private function statementList() {
		return $this->statementList ?: ( $this->statementList = new ObjectType( [
			'name' => 'StatementList',
			'fields' => function () {
				return $this->buildFieldsForAllProperties( function ( PropertyId $propertyId ) {
					return [
						'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->statement() ) ) ),
						'resolve' => function ( StatementList $value ) use ( $propertyId ) {
							return $value->getByPropertyId( $propertyId );
						}
					];
				} );
			}
		] ) );
	}

	private function buildFieldsForAllProperties( callable $fieldBuilder ) {
		$fields = [];
		foreach ( $this->propertiesByDatatype as $properties ) {
			/** @var PropertyId $propertyId */
			foreach ( $properties as $propertyId ) {
				$fields[$propertyId->getSerialization()] = $fieldBuilder( $propertyId );
			}
		}
		return $fields;
	}

	public function statement() {
		return $this->statement ?: ( $this->statement = new ObjectType( [
			'name' => 'Statement',
			'fields' => [
				'id' => [
					// TODO: not nullable?
					'type' => Type::id(),
					'description' =>
						'an arbitrary identifier for the claim, which is unique across the repository',
					'resolve' => function ( Statement $value ) {
						return $value->getGuid();
					}
				],
				'rank' => [
					'type' => Type::nonNull( $this->rank() ),
					'resolve' => function ( Statement $value ) {
						return $value->getRank();
					}
				],
				'mainsnak' => [
					'type' => Type::nonNull( $this->snak() ),
					'resolve' => function ( Statement $value ) {
						return $value->getMainSnak();
					}
				],
				'qualifiers' => [
					'type' => Type::nonNull( $this->snakList() ),
					'resolve' => function ( Statement $value ) {
						return $value->getQualifiers();
					}
				],
				'references' => [
					'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->reference() ) ) ),
					'resolve' => function ( Statement $value ) {
						return $value->getReferences();
					}
				]
			]
		] ) );
	}

	public function rank() {
		return $this->rank ?: ( $this->rank = new EnumType( [
			'name' => 'Rank',
			'description' => 'expresses whether this value will be used in queries, ' .
				'and shown be visible per default on a client system',
			'values' => [
				'PREFERRED' => [
					'value' => Statement::RANK_PREFERRED
				],
				'NORMAL' => [
					'value' => Statement::RANK_NORMAL
				],
				'DEPRECATED' => [
					'value' => Statement::RANK_DEPRECATED
				]
			]
		] ) );
	}

	public function reference() {
		return $this->reference ?: ( $this->reference = new ObjectType( [
			'name' => 'Reference',
			'fields' => [
				'hash' => [
					'type' => Type::nonNull( Type::id() ),
					'resolve' => function ( Reference $value ) {
						return $value->getHash();
					}
				],
				'snaks' => [
					'type' => Type::nonNull( $this->snakList() ),
					'resolve' => function ( Reference $value ) {
						return $value->getSnaks();
					}
				]
			]
		] ) );
	}

	public function snakList() {
		return $this->snakList ?: ( $this->snakList = new ObjectType( [
			'name' => 'SnakList',
			'fields' => function () {
				return $this->buildFieldsForAllProperties( function ( PropertyId $propertyId ) {
					return [
						'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->snak() ) ) ),
						'resolve' => function ( SnakList $value ) use ( $propertyId ) {
							return array_filter( $value->getArrayCopy(), function ( Snak $snak ) use ( $propertyId ) {
								return $snak->getPropertyId()->equals( $propertyId );
							} );
						}
					];
				} );
			}
		] ) );
	}

	public function snak() {
		return $this->snak ?: ( $this->snak = new InterfaceType( [
			'name' => 'Snak',
			'description' => 'provides some kind of information about a specific Property of a given Entity',
			'fields' => [
				'type' => [
					'type' => Type::nonNull( $this->snakType() ),
					'description' => 'type of the snak',
					'resolve' => function ( Snak $value ) {
						return $value->getType();
					}
				],
				'property' => [
					'type' => Type::nonNull( $this->property() ),
					'resolve' => function ( PropertyValueSnak $value ) {
						return $this->getEntityWithEmpty( $value->getPropertyId() )
							?: new Property( $value->getPropertyId() );
					}
				]
			],
			'resolveType' => function ( Snak $value ) {
				return $this->snakObjectForType( $value->getType() );
			}
		] ) );
	}

	public function snakType() {
		return $this->snakType ?: ( $this->snakType = new EnumType( [
			'name' => 'SnakType',
			'description' => 'type identifier of the snak like "value" or "somevalue"',
			'values' => [
				'VALUE' => [
					'value' => 'value'
				],
				'SOME_VALUE' => [
					'value' => 'somevalue'
				],
				'NO_VALUE' => [
					'value' => 'novalue'
				]
			]
		] ) );
	}

	private function snakObjectForType( $type ) {
		switch ( $type ) {
			case 'value':
				return $this->propertyValueSnak();
			case 'somevalue':
				return $this->propertySomeValueSnak();
			case 'novalue':
				return $this->propertyNoValueSnak();
			default:
				throw new ApiException( 'Unsupported snak type: ' . Utils::printSafeJson( $type ) );
		}
	}

	public function propertyValueSnak() {
		return $this->propertyValueSnak ?: ( $this->propertyValueSnak = new ObjectType( [
			'name' => 'PropertyValueSnak',
			'interfaces' => [ $this->snak() ],
			'fields' => $this->snak()->getFields() + [
					'value' => [
						'type' => Type::nonNull( $this->value() ),
						'resolve' => function ( PropertyValueSnak $value ) {
							$dataValue = $value->getDataValue();
							if ( $dataValue instanceof EntityIdValue ) {
								return $this->getEntityWithEmpty( $dataValue->getEntityId() );
							} else {
								return $dataValue;
							}
						}
					],
					'datatype' => [
						'type' => Type::nonNull( Type::id() ),
						'resolve' => function ( PropertyValueSnak $value ) {
							return $this->propertyDataTypeLookup->getDataTypeIdForProperty( $value->getPropertyId() );
						}
					]
				]
		] ) );
	}

	public function propertySomeValueSnak() {
		return $this->propertySomeValueSnak ?: ( $this->propertySomeValueSnak = new ObjectType( [
			'name' => 'PropertySomeValueSnak',
			'interfaces' => [ $this->snak() ],
			'fields' => $this->snak()->getFields()
		] ) );
	}

	public function propertyNoValueSnak() {
		return $this->propertyNoValueSnak ?: ( $this->propertyNoValueSnak = new ObjectType( [
			'name' => 'PropertyNoValueSnak',
			'interfaces' => [ $this->snak() ],
			'fields' => $this->snak()->getFields()
		] ) );
	}

	public function stringValue() {
		return $this->stringValue ?: ( $this->stringValue = new ObjectType( [
			'name' => 'StringValue',
			'interfaces' => [ $this->value() ],
			'fields' => $this->value()->getFields() + [
					'value' => [
						'type' => Type::nonNull( Type::string() ),
						'resolve' => function ( StringValue $value ) {
							return $value->getValue();
						}
					]
				]
		] ) );
	}

	public function monolingualTextValue() {
		// TODO: merge with Term?
		return $this->monolingualTextValue ?: ( $this->monolingualTextValue = new ObjectType( [
			'name' => 'MonolingualTextValue',
			'interfaces' => [ $this->value() ],
			'fields' => $this->value()->getFields() + [
					'language' => [
						'type' => Type::nonNull( Type::string() ),
						'resolve' => function ( MonolingualTextValue $value ) {
							return $value->getLanguageCode();
						}
					],
					'text' => [
						'type' => Type::nonNull( Type::string() ),
						'resolve' => function ( MonolingualTextValue $value ) {
							return $value->getText();
						}
					]
				]
		] ) );
	}

	public function globeCoordinateValue() {
		return $this->globeCoordinateValue ?: ( $this->globeCoordinateValue = new ObjectType( [
			'name' => 'GlobeCoordinateValue',
			'interfaces' => [ $this->value() ],
			'fields' => $this->value()->getFields() + [
					'latitude' => [
						'type' => Type::nonNull( Type::float() ),
						'resolve' => function ( GlobeCoordinateValue $value ) {
							return $value->getLatitude();
						}
					],
					'longitude' => [
						'type' => Type::nonNull( Type::float() ),
						'resolve' => function ( GlobeCoordinateValue $value ) {
							return $value->getLongitude();
						}
					],
					'precision' => [
						'type' => Type::float(),
						'resolve' => function ( GlobeCoordinateValue $value ) {
							return $value->getPrecision();
						}
					],
					'globe' => [
						'type' => Type::nonNull( $this->item() ),
						'resolve' => function ( GlobeCoordinateValue $value ) {
							return $this->getEntityWithEmpty(
								$this->entityUriParser->parse( $value->getGlobe() )
							);
						}
					]
				]
		] ) );
	}

	public function quantityValue() {
		return $this->quantityValue ?: ( $this->quantityValue = new ObjectType( [
			'name' => 'QuantityValue',
			'interfaces' => [ $this->value() ],
			'fields' => $this->value()->getFields() + [
					'amount' => [
						'type' => Type::nonNull( Type::string() ),
						'resolve' => function ( UnboundedQuantityValue $value ) {
							return $value->getAmount()->getValue();
						}
					],
					'upperBound' => [
						'type' => Type::string(),
						'resolve' => function ( UnboundedQuantityValue $value ) {
							return ( $value instanceof QuantityValue ) ? $value->getUpperBound()->getValue() : null;
						}
					],
					'lowerBound' => [
						'type' => Type::string(),
						'resolve' => function ( UnboundedQuantityValue $value ) {
							return ( $value instanceof QuantityValue ) ? $value->getLowerBound()->getValue() : null;
						}
					],
					'unit' => [
						'type' => $this->item(),
						'description' => 'Unit of the quantity. null if there is no unit.',
						'resolve' => function ( UnboundedQuantityValue $value ) {
							$unit = $value->getUnit();
							if ( $unit === '1' ) {
								return null;
							}
							return $this->getEntityWithEmpty( $this->entityUriParser->parse( $unit ) );
						}
					]
				]
		] ) );
	}

	public function timeValue() {
		return $this->timeValue ?: ( $this->timeValue = new ObjectType( [
			'name' => 'TimeValue',
			'interfaces' => [ $this->value() ],
			'fields' => $this->value()->getFields() + [
					'time' => [
						'type' => Type::nonNull( Type::string() ),
						'resolve' => function ( TimeValue $value ) {
							return $value->getTime();
						}
					],
					'timezone' => [
						'type' => Type::nonNull( Type::int() ),
						'resolve' => function ( TimeValue $value ) {
							return $value->getTimezone();
						}
					],
					'before' => [
						'type' => Type::nonNull( Type::int() ),
						'resolve' => function ( TimeValue $value ) {
							return $value->getBefore();
						}
					],
					'after' => [
						'type' => Type::nonNull( Type::int() ),
						'resolve' => function ( TimeValue $value ) {
							return $value->getAfter();
						}
					],
					'precision' => [
						'type' => Type::nonNull( $this->timePrecision() ),
						'resolve' => function ( TimeValue $value ) {
							return $value->getPrecision();
						}
					],
					'calendarmodel' => [
						'type' => Type::nonNull( $this->item() ),
						'resolve' => function ( TimeValue $value ) {
							return $this->getEntityWithEmpty(
								$this->entityUriParser->parse( $value->getCalendarModel() )
							);
						}
					]
				]
		] ) );
	}

	public function timePrecision() {
		return $this->timePrecision ?: ( $this->timePrecision = new EnumType( [
			'name' => 'TimePrecision',
			'values' => [
				'YEAR1G' => [
					'value' => TimeValue::PRECISION_YEAR1G
				],
				'YEAR100M' => [
					'value' => TimeValue::PRECISION_YEAR100M
				],
				'YEAR10M' => [
					'value' => TimeValue::PRECISION_YEAR10M
				],
				'YEAR1M' => [
					'value' => TimeValue::PRECISION_YEAR1M
				],
				'YEAR100K' => [
					'value' => TimeValue::PRECISION_YEAR100K
				],
				'YEAR10K' => [
					'value' => TimeValue::PRECISION_YEAR10K
				],
				'YEAR1K' => [
					'value' => TimeValue::PRECISION_YEAR1K
				],
				'YEAR100' => [
					'value' => TimeValue::PRECISION_YEAR100
				],
				'YEAR10' => [
					'value' => TimeValue::PRECISION_YEAR10
				],
				'YEAR' => [
					'value' => TimeValue::PRECISION_YEAR
				],
				'MONTH' => [
					'value' => TimeValue::PRECISION_MONTH
				],
				'DAY' => [
					'value' => TimeValue::PRECISION_DAY
				],
				'HOUR' => [
					'value' => TimeValue::PRECISION_HOUR
				],
				'MINUTE' => [
					'value' => TimeValue::PRECISION_MINUTE
				],
				'SECOND' => [
					'value' => TimeValue::PRECISION_SECOND
				]
			]
		] ) );
	}

	public function unknownValue() {
		return $this->unknownValue ?: ( $this->unknownValue = new ObjectType( [
			'name' => 'UnknownValue',
			'interfaces' => [ $this->value() ],
			'fields' => $this->value()->getFields()
		] ) );
	}

	/**
	 * @return EntityDocument
	 * @throws ApiException
	 */
	private function getEntityWithEmpty( EntityId $entityId ) {
		$entity = $this->entityLookup->getEntity( $entityId );
		if ( $entity !== null ) {
			return $entity;
		}
		switch ( $entityId->getEntityType() ) {
			case Item::ENTITY_TYPE:
				return new Item( $entityId );
			case Property::ENTITY_TYPE:
				return new Property( $entityId, null, 'unknown' );
			default:
				throw new ApiException( 'Unsupported entity type: ' . $entityId->getEntityType() );
		}
	}
}
