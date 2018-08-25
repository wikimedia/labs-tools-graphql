const { gql } = require( 'apollo-server-hapi' );
const sitematrix = require( '../utils/sitematrix' );

const schema = gql`
	type Language {
		code: ID!
		name: String!
		localname: String!
		dir: String!
		site(code: ID!): Site
		sites: [Site]
	}
`;

const resolvers = {
	Language: {
		site: async ( language, { code } ) => {
			const { sites } = await sitematrix;

			return sites.find( site => (
				site.code === code && site.languageCode === language.code
			) );
		},
		sites: async ( language ) => {
			const { sites } = await sitematrix;

			return sites.filter( site => site.languageCode === language.code );
		}
	}
};

module.exports = {
	schema,
	resolvers
};
