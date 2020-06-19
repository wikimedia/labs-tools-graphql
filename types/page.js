const { gql } = require( 'apollo-server' );
const startsWith = require( 'lodash/startsWith' );
const get = require( 'lodash/get' );

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
	type ImageThumb {
		url: String
		width: Int
		height: Int
		mime: String
	}
	type Image {
		archivename: String
		bitdepth: Int
		canonicaltitle: String
		comment: String
		parsedcomment: String
		timestamp: String
		url: String
		descriptionurl: String
		descriptionshorturl: String
		size: Int
		width: Int
		height: Int
		pagecount: Int
		sha1: String
		mime: String
		mediatype: String
		thumb(width: Int, height: Int): ImageThumb
		# @TODO Implement User!
		# user: User
		# metadata: ?
		# commonmetadata: ?
		# extmetadata: ?
	}
	type ImageInfo {
		start: String
		images: [Image!]!
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
			intro: Boolean
			plaintext: Boolean
			sectionformat: PageExtractSectionFormat
		): String
		linkshere(
			continue: String
			namespace: [Int!]
			show: [String!]
			limit: Int
		): PageList
		imageinfo(
			limit: Int
			start: String
			end: String
			continue: String
			localonly: Boolean
		): ImageInfo
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

const imageResolver = actionProp => prop => async ( image, args, { dataSources } ) => {
	const { __site: { dbname } } = image;
	const { __page: { pageid: id, title } } = image;
	const { __args } = image;
	const { __index } = image;

	if ( prop in image && image[ prop ] ) {
		return image[ prop ];
	}

	const { images } = await dataSources[ dbname ].getImageInfo(
		{ id, title },
		__args,
		actionProp
	);

	return get( images, [ __index, prop ] );
};

const imageUrlResolver = imageResolver( 'url' );
const imageSizeResolver = imageResolver( 'size' );

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
		},
		imageinfo: async ( page, args, { dataSources } ) => {
			const { __site } = page;
			const { dbname } = __site;
			const { pageid: id, title } = page;
			const data = await dataSources[ dbname ].getImageInfo( { id, title }, args );

			return {
				...data,
				images: data.images.map( ( image, __index ) => ( {
					__site,
					__page: page,
					__args: args,
					__index,
					...image
				} ) )
			};
		}
	},
	Image: {
		archivename: imageResolver( 'archivename' )( 'archivename' ),
		bitdepth: imageResolver( 'bitdepth' )( 'bitdepth' ),
		canonicaltitle: imageResolver( 'canonicaltitle' )( 'canonicaltitle' ),
		comment: imageResolver( 'comment' )( 'comment' ),
		parsedcomment: imageResolver( 'parsedcomment' )( 'parsedcomment' ),
		timestamp: imageResolver( 'timestamp' )( 'timestamp' ),
		url: imageUrlResolver( 'url' ),
		descriptionurl: imageUrlResolver( 'url' ),
		descriptionshorturl: imageUrlResolver( 'url' ),
		size: imageSizeResolver( 'size' ),
		width: imageSizeResolver( 'width' ),
		height: imageSizeResolver( 'height' ),
		pagecount: imageSizeResolver( 'pagecount' ),
		sha1: imageResolver( 'sha1' )( 'sha1' ),
		mime: imageResolver( 'mime' )( 'mime' ),
		mediatype: imageResolver( 'mediatype' )( 'mediatype' ),
		thumb: async ( image, args, { dataSources } ) => {
			const { __site: { dbname } } = image;
			const { __page: { pageid: id, title } } = image;
			const { __args } = image;
			const { __index } = image;
			const { images } = await dataSources[ dbname ].getImageInfoThumb( { id, title }, {
				...__args,
				...args
			} );

			const data = get( images, [ __index ] );

			if ( !data ) {
				return null;
			}

			return {
				url: data.thumburl,
				width: data.thumbwidth,
				height: data.height,
				mime: data.thumbmime
			};
		}
	}
};

module.exports = {
	schema,
	resolvers
};
