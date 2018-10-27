const { gql } = require( 'apollo-server-hapi' );

const schema = gql`
	enum PageExtractSectionFormat {
		plain
		raw
		wiki
	}
	type Page {
		pageid: Int
		ns: Int
		title: String
		extract(
			chars: Int
			sentences: Int
			intro: Boolean,
			plaintext: Boolean,
			sectionformat: PageExtractSectionFormat
		): String
	}
`;

const idResolver = prop => async ( {
	pageid: id,
	title,
	__site: { dbname }
}, args, { dataSources } ) => {
	if ( prop === 'title' && title ) {
		return title;
	}
	if ( prop === 'pageid' && id ) {
		return id;
	}
	const page = await dataSources[ dbname ].getPageIds( { id, title } );

	if ( !page ) {
		return null;
	}

	return page[ prop ];
};

const resolvers = {
	Page: {
		pageid: idResolver( 'pageid' ),
		ns: idResolver( 'ns' ),
		title: idResolver( 'title' ),
		extract: async ( { pageid: id, title, __site: { dbname } }, args, { dataSources } ) => (
			dataSources[ dbname ].getPageExtract( { id, title }, args )
		)
	}
};

module.exports = {
	schema,
	resolvers
};
