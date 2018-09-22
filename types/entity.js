const { gql } = require( 'apollo-server-hapi' );

// @TODO Figure out if labels are on every entity!
const schema = gql`
	type ItemEntity implements Entity {
		# Entity
		pageid: Int!
		ns: Int!
		title: String!
		lastrevid: Int!
		modified: String!
		type: String!
		id: ID!
		# EntityLabel
		label(language: String): EntityLabel
		labels: [EntityLabel]
	}
	type EntityLabel {
		language: String!
		value: String!
	}
	interface Entity {
		pageid: Int!
		ns: Int!
		title: String!
		lastrevid: Int!
		modified: String!
		type: String!
		id: ID!
		label(language: String): EntityLabel
		labels: [EntityLabel]
	}
`;

const infoResolver = prop => async ( { id, __site: { dbname } }, args, { dataSources } ) => {
	const entity = await dataSources[ dbname ].getEntity( id, 'info' );

	return entity[ prop ];
};

const labelResolver = async (
	{ id, __site: { dbname } },
	{ language },
	{ dataSources, languages: acceptLanguages }
) => {
	const entity = await dataSources[ dbname ].getEntity( id, 'labels', language || acceptLanguages );

	if ( !( 'labels' in entity ) ) {
		return null;
	}

	if ( language ) {
		if ( language in entity.labels ) {
			return entity.labels[ language ];
		}

		return null;
	}

	const preferedLabels = Object.values( entity.labels ).filter( label => (
		// Remove irelevant sites.
		acceptLanguages.includes( label.language )
	) ).sort( ( a, b ) => (
		// Sort by preference.
		acceptLanguages.findIndex(
			tag => tag === a.language
		) - acceptLanguages.findIndex(
			tag => tag === b.language
		)
	) );

	return preferedLabels.length > 0 ? preferedLabels[ 0 ] : undefined;
};

const labelsResolvers = async ( { id, __site: { dbname } }, args, { dataSources } ) => {
	const entity = await dataSources[ dbname ].getEntity( id, 'labels', '*' );

	if ( !( 'labels' in entity ) ) {
		return null;
	}

	return Object.values( entity.labels );
};

const entityTypeResolver = async ( { id } ) => {
	if ( id.startsWith( 'Q' ) ) {
		return 'ItemEntity';
	}

	return null;
};

const resolvers = {
	Entity: {
		__resolveType: entityTypeResolver
	},
	ItemEntity: {
		pageid: infoResolver( 'pageid' ),
		ns: infoResolver( 'ns' ),
		title: infoResolver( 'title' ),
		lastrevid: infoResolver( 'lastrevid' ),
		modified: infoResolver( 'modified' ),
		type: infoResolver( 'type' ),
		id: infoResolver( 'id' ),
		label: labelResolver,
		labels: labelsResolvers
	}
};

module.exports = {
	schema,
	resolvers
};
