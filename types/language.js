const { gql } = require( 'apollo-server-hapi' );
const { site: siteResolver, sites: sitesResolver } = require( '../resolvers/site' );

const schema = gql`
	type Language {
		code: ID!
		name: String!
		localname: String!
		dir: String!
		site(code: ID!): Site
		sites: [Site]!
	}
`;

const resolvers = {
	Language: {
		site: siteResolver,
		sites: sitesResolver
	}
};

module.exports = {
	schema,
	resolvers
};
