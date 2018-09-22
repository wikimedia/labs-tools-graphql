const { gql } = require( 'apollo-server-hapi' );
const sitematrix = require( '../utils/sitematrix' );

const schema = gql`
	interface Site {
		dbname: ID!
		url: String!
		code: String!
		sitename: String!
		closed: Boolean!
		fishbowl: Boolean!
		private: Boolean!
		language: Language
		page(title: String!): Page
	}

	# @TODO Split into multilingual and monolingual sites.
	type MediaWikiSite implements Site {
		dbname: ID!
		url: String!
		code: String!
		sitename: String!
		closed: Boolean!
		fishbowl: Boolean!
		private: Boolean!
		language: Language
		page(title: String!): Page
	}

	type WikibaseSite implements Site {
		dbname: ID!
		url: String!
		code: String!
		sitename: String!
		closed: Boolean!
		fishbowl: Boolean!
		private: Boolean!
		language: Language
		page(title: String!): Page
		entity(id: ID!): Entity
	}
`;

const languageResolver = async ( site ) => {
	const { languages } = await sitematrix;

	return languages.find( lang => lang.code === site.languageCode );
};

const pageResolver = ( site, { title } ) => ( {
	__site: site,
	title
} );

const resolvers = {
	Site: {
		__resolveType: ( { code } ) => {
			switch ( code ) {
				case 'wikidata':
					return 'WikibaseSite';
				default:
					return 'MediaWikiSite';
			}
		}
	},
	MediaWikiSite: {
		language: languageResolver,
		page: pageResolver
	},
	WikibaseSite: {
		language: languageResolver,
		page: pageResolver,
		entity: ( site, { id } ) => ( {
			__site: site,
			id
		} )
	}
};

module.exports = {
	schema,
	resolvers
};
