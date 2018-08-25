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
		page(title: String!): Page
	}
`;

const resolvers = {
	Site: {
		language: async ( site ) => {
			const { languages } = await sitematrix;

			return languages.find( lang => lang.code === site.languageCode );
		},
		page: ( site, { title } ) => ( {
			__site: site,
			title
		} )
	}
};

module.exports = {
	schema,
	resolvers
};
