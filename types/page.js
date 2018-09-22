const { gql } = require( 'apollo-server-hapi' );

const schema = gql`
	enum PageExtractSectionFormat {
		plain
		raw
		wiki
	}
	type Page {
		extract(
			chars: Int
			sentences: Int
			intro: Boolean,
			plaintext: Boolean,
			sectionformat: PageExtractSectionFormat
		): String
	}
`;

const resolvers = {
	Page: {
		extract: async ( { title, __site: { dbname } }, args, { dataSources } ) => (
			dataSources[ dbname ].getPageExtract( { title }, args )
		)
	}
};

module.exports = {
	schema,
	resolvers
};
