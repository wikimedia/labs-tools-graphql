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
use GraphQLRelay\Relay;
use OutOfBoundsException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Statement\Filter\PropertySetStatementFilter;
use Wikibase\DataModel\SiteLink;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\DataModel\Term\FingerprintProvider;
use Wikibase\DataModel\Term\Term;

class WikibaseDataModelRegistry {

	private $entityLookup;
	private $propertyDataTypeLookup;
	private $entityIdParser;
	private $entityUriParser;
	private $nodeDefinition;

	private $value;
	private $valueType;
	private $entity;
	private $item;
	private $property;
	private $siteLink;
	private $statement;
	private $rank;
	private $reference;
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

	public function __construct(
		EntityLookup $entityLookup, PropertyDataTypeLookup $propertyDataTypeLookup,
		EntityIdParser $entityIdParser, EntityIdParser $entityUriParser
	) {
		$this->entityLookup = $entityLookup;
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
		$this->entityIdParser = $entityIdParser;
		$this->entityUriParser = $entityUriParser;

		$this->nodeDefinition = Relay::nodeDefinitions(
			function ( $id ) {
				$entityId = $this->parseEntityId( $id );
				return $this->entityLookup->getEntity( $entityId );
			},
			function ( EntityDocument $object ) {
				return $this->entityObjectForType( $object->getType() );
			}
		);
	}

	public function nodeField() {
		return $this->nodeDefinition['nodeField'];
	}

	public function node() {
		return $this->nodeDefinition['nodeInterface'];
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

	public function parseEntityId( $serialization ) {
		try {
			return $this->entityIdParser->parse( $serialization );
		} catch ( EntityIdParsingException $e ) {
			throw new ApiException( $e->getMessage(), $e->getCode(), $e );
		}
	}

	public function item() {
		return $this->item ?: ( $this->item = new ObjectType( [
			'name' => 'Item',
			'interfaces' => [ $this->node(), $this->value(), $this->entity() ],
			'fields' =>
				$this->value()->getFields() +
				$this->entity()->getFields() +
				$this->fingerprintProviderFields() +
				$this->statementsProviderFields() + [
					'sitelink' => [
						'type' => $this->siteLink(),
						'description' => 'sitelink of the entity (unique per site id)',
						'args' => [
							'site' => [
								'type' => Type::nonNull( Type::string() ),
								'description' => 'Site id of the sitelink like "enwiki".'
							]
						],
						'resolve' => function ( Item $value, $args ) {
							try {
								return $value->getSiteLinkList()->getBySiteId( $args['site'] );
							} catch ( OutOfBoundsException $e ) {
								return null;
							}
						}
					],
					'sitelinks' => [
						'type' => Type::nonNull( Type::listOf( $this->siteLink() ) ),
						'description' => 'sitelinks of the entity (unique per site id)',
						'args' => [
							'sites' => [
								'type' => Type::listOf( Type::nonNull( Type::string() ) ),
								'description' => 'List of site ids to returns the sitelinks for. ' .
									'If null all sitelinks are going to be returned. ' .
									'If not null the result array has the same size as the input array ' .
									'and the sitelinks are in the same position as their site id in the input array.'
							]
						],
						'resolve' => function ( Item $value, $args ) {
							$siteIds = $this->getArgSafe( $args, 'sites' );
							if ( $siteIds === null ) {
								return $value->getSiteLinkList();
							}
							return array_map( function ( $siteId ) use ( $value ) {
								try {
									return $value->getSiteLinkList()->getBySiteId( $siteId );
								} catch ( OutOfBoundsException $e ) {
									return null;
								}
							}, $siteIds );
						}
					]
				]
		] ) );
	}

	public function property() {
		return $this->property ?: ( $this->property = new ObjectType( [
			'name' => 'Property',
			'interfaces' => [ $this->node(), $this->value(), $this->entity() ],
			'fields' =>
				$this->value()->getFields() +
				$this->entity()->getFields() +
				$this->fingerprintProviderFields() +
				$this->statementsProviderFields() + [
					'datatype' => [
						'type' => Type::nonNull( Type::string() ),
						'resolve' => function ( Property $value ) {
							return $value->getDataTypeId();
						}
					]
				]
		] ) );
	}

	private function fingerprintProviderFields() {
		return [
			'label' => [
				'type' => $this->monolingualTextValue(),
				'description' => 'label of the entity',
				'args' => [
					'language' => [
						'type' => Type::nonNull( Type::string() )
					]
				],
				'resolve' => function ( FingerprintProvider $value, $args ) {
					try {
						return $value->getFingerprint()->getLabel( $args['language'] );
					} catch ( OutOfBoundsException $e ) {
						return null;
					}
				}
			],
			'labels' => [
				'type' => Type::nonNull( Type::listOf( $this->monolingualTextValue() ) ),
				'description' => 'labels of the entity (unique per language)',
				'args' => [
					'languages' => [
						'type' => Type::listOf( Type::nonNull( Type::string() ) ),
						'description' => 'List of languages to returns the labels in. ' .
							'If null all labels are going to be returned. ' .
							'If not null the result array has the same size as the input array ' .
							'and the labels are in the same position as their language code in the input array.'
					]
				],
				'resolve' => function ( FingerprintProvider $value, $args ) {
					$languages = $this->getArgSafe( $args, 'languages' );
					if ( $languages === null ) {
						return $value->getFingerprint()->getLabels();
					}
					return array_map( function ( $languageCode ) use ( $value ) {
						try {
							return $value->getFingerprint()->getLabel( $languageCode );
						} catch ( OutOfBoundsException $e ) {
							return null;
						}
					}, $languages );
				}
			],
			'description' => [
				'type' => $this->monolingualTextValue(),
				'description' => 'description of the entity',
				'args' => [
					'language' => [
						'type' => Type::nonNull( Type::string() )
					]
				],
				'resolve' => function ( FingerprintProvider $value, $args ) {
					try {
						return $value->getFingerprint()->getDescription( $args['language'] );
					} catch ( OutOfBoundsException $e ) {
						return null;
					}
				}
			],
			'descriptions' => [
				'type' => Type::nonNull( Type::listOf( $this->monolingualTextValue() ) ),
				'description' => 'descriptions of the entity (unique per language)',
				'args' => [
					'languages' => [
						'type' => Type::listOf( Type::nonNull( Type::string() ) ),
						'description' => 'List of languages to returns the descriptions in. ' .
							'If null all descriptions are going to be returned. ' .
							'If not null the result array has the same size as the input array ' .
							'and the descriptions are in the same position as their language code in the input array.'
					]
				],
				'resolve' => function ( FingerprintProvider $value, $args ) {
					$languages = $this->getArgSafe( $args, 'languages' );
					if ( $languages === null ) {
						return $value->getFingerprint()->getDescriptions();
					}
					return array_map( function ( $languageCode ) use ( $value ) {
						try {
							return $value->getFingerprint()->getDescription( $languageCode );
						} catch ( OutOfBoundsException $e ) {
							return null;
						}
					}, $languages );
				}
			],
			'aliases' => [
				'type' => Type::nonNull( Type::listOf( $this->monolingualTextValue() ) ),
				'description' => 'aliases of the entity (unique per language)',
				'args' => [
					'languages' => [
						'type' => Type::listOf( Type::nonNull( Type::string() ) ),
						'description' => 'List of languages to returns the aliases in. ' .
							'If null all aliases are going to be returned.'
					]
				],
				'resolve' => function ( FingerprintProvider $value, $args ) {
					$languages = $this->getArgSafe( $args, 'languages' );
					$aliasGroups = $value->getFingerprint()->getAliasGroups();
					if ( $languages !== null ) {
						$aliasGroups = $aliasGroups->getWithLanguages( $languages );
					}
					$results = [];
						foreach ( $aliasGroups as $aliasGroup ) {
							foreach ( $aliasGroup->getAliases() as $alias ) {
								$results[] = new Term( $aliasGroup->getLanguageCode(), $alias );
							}
						}
					return $results;
				}
			],
		];
	}

	public function siteLink() {
		return $this->siteLink ?: ( $this->siteLink = new ObjectType( [
			'name' => 'SiteLink',
			'fields' => function () {
				return [
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
								return $this->getEntityWithEmptyDefault( $itemId );
							}, $value->getBadges() );
						}
					]
				];
			}
		] ) );
	}

	private function statementsProviderFields() {
		return [
			'statements' => [
				'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->statement() ) ) ),
				'description' => 'statements of the entity',
				'args' => [
					'propertyIds' => [
						'type' => Type::listOf( Type::nonNull( Type::id() ) ),
						'description' => 'List of the property ids in order to filter the statements returned. ' .
							'If null all statements are going to be returned.'
					],
					'best' => [
						'type' => Type::boolean(),
						'description' => 'If true only returns the best statements will be returned. ' .
							'This works by returning for each main snak properties ' .
							'the statements with the prefered rank or, if there is none, ' .
							'the statements with the normal rank'
					]
				],
				'resolve' => function ( StatementListProvider $value, $args ) {
					$statements = $value->getStatements();
					$propertyIds = $this->getArgSafe( $args, 'propertyIds' );
					if ( $propertyIds !== null ) {
						$statements = $statements->filter( new PropertySetStatementFilter( $propertyIds ) );
					}
					if ( $this->getArgSafe( $args, 'best' ) ) {
						$statements = $this->getBestStatementsForEachProperty( $statements );
					}
					return $statements;
				}
			]
		];
	}

	/**
	 * TODO: migrate to WikibaseDataModel
	 */
	private function getBestStatementsForEachProperty( StatementList $statements ) {
		$filteredStatements = new StatementList();
		foreach ( $statements->getPropertyIds() as $propertyId ) {
			foreach ( $statements->getByPropertyId( $propertyId )->getBestStatements() as $statement ) {
				$filteredStatements->addStatement( $statement );
			}
		}
		return $filteredStatements;
	}

	public function statement() {
		return $this->statement ?: ( $this->statement = new ObjectType( [
			'name' => 'Statement',
			'fields' => function () {
				return [
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
						'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->snak() ) ) ),
						'args' => [
							'propertyIds' => [
								'type' => Type::listOf( Type::nonNull( Type::id() ) ),
								'description' => 'List of the property ids in order to filter the snaks returned. ' .
									'If null all snaks are returned.'
							]
						],
						'resolve' => function ( Statement $value, $args ) {
							$propertyIds = $this->getArgSafe( $args, 'propertyIds' );
							if ( $propertyIds === null ) {
								return $value->getQualifiers();
							} else {
								return array_filter(
									iterator_to_array( $value->getQualifiers() ),
									function ( Snak $snak ) use ( $propertyIds ) {
										return in_array( $snak->getPropertyId()->getSerialization(), $propertyIds );
									}
								);
							}
						}
					],
					'references' => [
						'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->reference() ) ) ),
						'resolve' => function ( Statement $value ) {
							return $value->getReferences();
						}
					]
				];
			}
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
					'type' => Type::nonNull( Type::listOf( Type::nonNull( $this->snak() ) ) ),
					'args' => [
						'propertyIds' => [
							'type' => Type::listOf( Type::nonNull( Type::id() ) ),
							'description' => 'List of the property ids in order to filter the snaks returned. ' .
								'If null all snaks are returned.'
						]
					],
					'resolve' => function ( Reference $value, $args ) {
						$propertyIds = $this->getArgSafe( $args, 'propertyIds' );
						if ( $propertyIds === null ) {
							return $value->getSnaks();
						} else {
							return array_filter(
								iterator_to_array( $value->getSnaks() ),
								function ( Snak $snak ) use ( $propertyIds ) {
									return in_array( $snak->getPropertyId()->getSerialization(), $propertyIds );
								}
							);
						}
					}
				]
			]
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
					'resolve' => function ( Snak $value ) {
						return $this->getEntityWithEmptyDefault( $value->getPropertyId() )
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
								return $this->getEntityWithEmptyDefault( $dataValue->getEntityId() );
							} else {
								return $dataValue;
							}
						}
					],
					'datatype' => [
						'type' => Type::nonNull( Type::string() ),
						'deprecationReason' => 'Duplicates of property.datatype',
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
		return $this->monolingualTextValue ?: ( $this->monolingualTextValue = new ObjectType( [
			'name' => 'MonolingualTextValue',
			'interfaces' => [ $this->value() ],
			'fields' => $this->value()->getFields() + [
					'language' => [
						'type' => Type::nonNull( Type::string() ),
						'description' => 'language code of the text',
						'resolve' => function ( $value ) {
							if ( $value instanceof MonolingualTextValue ) {
								return $value->getLanguageCode();
							} elseif ( $value instanceof Term ) {
								return $value->getLanguageCode();
							} else {
								throw new InvariantViolation( 'Not expected MonolingualTermValue input: ' . $value );
							}
						}
					],
					'text' => [
						'type' => Type::nonNull( Type::string() ),
						'resolve' => function ( $value ) {
							if ( $value instanceof MonolingualTextValue ) {
								return $value->getText();
							} elseif ( $value instanceof Term ) {
								return $value->getText();
							} else {
								throw new InvariantViolation( 'Not expected MonolingualTermValue input: ' . $value );
							}
						}
					],
					'value' => [
						'type' => Type::nonNull( Type::string() ),
						'deprecationReason' => 'Duplicates of MonolingualTextValue.text',
						'resolve' => function ( $value ) {
							if ( $value instanceof MonolingualTextValue ) {
								return $value->getText();
							} elseif ( $value instanceof Term ) {
								return $value->getText();
							} else {
								throw new InvariantViolation( 'Not expected MonolingualTermValue input: ' . $value );
							}
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
							return $this->getEntityWithEmptyDefault(
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
							return $this->getEntityWithEmptyDefault( $this->entityUriParser->parse( $unit ) );
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
							return $this->getEntityWithEmptyDefault(
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
	private function getEntityWithEmptyDefault( EntityId $entityId ) {
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

	private function getArgSafe( $args, $name ) {
		return array_key_exists( $name, $args ) ? $args[$name] : null;
	}
}
