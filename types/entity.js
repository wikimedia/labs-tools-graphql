const { gql } = require( 'apollo-server-hapi' );

const schema = gql`
	type Entity {
		pageid: Int!
		ns: Int!
		title: String!
		lastrevid: Int!
		modified: String!
		type: String!
		id: ID!
	}
`;

const resolvers = {};

module.exports = {
	schema,
	resolvers
};
