const { gql } = require( 'apollo-server-hapi' );

const schema = gql`
	type PageSummaryCordinates {
		lat: Int!
		long: Int!
	}
	type PageSummaryImage {
		source: String!
		width: Int!
		height: Int!
	}
	type PageSummary {
		title: String!
		displaytitle: String!
		pageid: Int!
		extract: String!
		extract_html: String!
		thumbnail: PageSummaryImage
		originalimage: PageSummaryImage
		lang: String!
		dir: String!
		timestamp: String!
		description: String!
		coordinates: PageSummaryCordinates
	}
	type Page {
		summary: PageSummary
	}
`;

const resolvers = {
	Page: {
		summary: async ( { title, __site }, args, { dataSources } ) => (
			dataSources[ __site.dbname ].getPageSummary( title )
		)
	}
};

module.exports = {
	schema,
	resolvers
};
