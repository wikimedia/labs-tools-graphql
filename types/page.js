const { gql } = require( 'apollo-server-hapi' );
const startsWith = require( 'lodash/startsWith' );

const schema = gql`
	enum PageExtractSectionFormat {
		plain
		raw
		wiki
	}
	type PageList {
		continue: String
		pages: [Page!]!
	}
	type Page {
		pageid: Int
		ns: Int
		title: String
		contentmodel: String
		pagelanguage: String
		pagelanguagehtmlcode: String
		pagelanguagedir: String
		touched: String
		length: Int
		entity: Entity
		extract(
			chars: Int
			sentences: Int
			intro: Boolean,
			plaintext: Boolean,
			sectionformat: PageExtractSectionFormat
		): String
		linkshere(
			continue: String
			namespace: [Int!]
			show: [String!]
			limit: Int
		): PageList
	}
`;

const pageResolver = actionProp => prop => async ( page, args, { dataSources } ) => {
	const { __site: { dbname } } = page;
	const { pageid: id, title } = page;

	if ( prop in page && page[ prop ] ) {
		return page[ prop ];
	}

	page = await dataSources[ dbname ].getPage( { id, title }, actionProp );

	if ( !page ) {
		return null;
	}

	return page[ prop ];
};

const idResolver = pageResolver();
const infoResolver = pageResolver( 'info' );

const resolvers = {
	Page: {
		pageid: idResolver( 'pageid' ),
		ns: idResolver( 'ns' ),
		title: idResolver( 'title' ),
		contentmodel: infoResolver( 'contentmodel' ),
		pagelanguage: infoResolver( 'pagelanguage' ),
		pagelanguagehtmlcode: infoResolver( 'pagelanguagehtmlcode' ),
		pagelanguagedir: infoResolver( 'pagelanguagedir' ),
		touched: infoResolver( 'touched' ),
		length: infoResolver( 'length' ),
		entity: async ( page, args, context ) => {
			const { __site } = page;

			const [ title, ns, contentmodel ] = await Promise.all( [
				idResolver( 'title' )( page, args, context ),
				idResolver( 'ns' )( page, args, context ),
				pageResolver( 'info' )( 'contentmodel' )( page, args, context )
			] );

			if ( startsWith( contentmodel, 'wikibase' ) ) {
				return {
					__site,
					id: ns === 0 ? title : title.split( ':' ).slice( -1 ).pop()
				};
			}

			return null;
		},
		extract: async ( { pageid: id, title, __site: { dbname } }, args, { dataSources } ) => (
			dataSources[ dbname ].getPageExtract( { id, title }, args )
		),
		linkshere: async ( { pageid: id, title, __site }, args, { dataSources } ) => {
			const { dbname } = __site;
			const data = await dataSources[ dbname ].getPageLinksHere( { id, title }, args );

			return {
				...data,
				pages: data.pages.map( page => ( {
					__site,
					...page
				} ) )
			};
		}
	}
};

module.exports = {
	schema,
	resolvers
};
