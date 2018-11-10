const { gql } = require( 'apollo-server-hapi' );
const sitematrix = require( '../utils/sitematrix' );

const schema = gql`
	type Site {
		dbname: ID!
		url: String!
		code: String!
		sitename: String!
		closed: Boolean!
		fishbowl: Boolean!
		private: Boolean!
		language: Language
		page(id: Int, title: String): Page
		entity(id: ID!): Entity
		sparql(
			distinct: Boolean = true
			select: String = "?entity"
			where: String!
			orderBy: String
			limit: Int
		): [Entity!]!
	}
`;

const resolvers = {
	Site: {
		language: async ( site ) => {
			const { languages } = await sitematrix;

			return languages.find( lang => lang.code === site.languageCode );
		},
		page: ( site, { id, title } ) => ( {
			__site: site,
			title,
			pageid: id
		} ),
		entity: ( site, { id } ) => ( {
			__site: site,
			id
		} ),
		sparql: async ( site, args, { dataSources } ) => {
			const source = `${site.dbname}.sparql`;
			if ( !( source in dataSources ) ) {
				return [];
			}

			const ids = await dataSources[ source ].query( args );

			return ids.map( id => ( {
				__site: site,
				id
			} ) );
		}
	}
};

module.exports = {
	schema,
	resolvers
};
