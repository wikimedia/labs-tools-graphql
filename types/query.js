const { gql } = require( 'apollo-server-hapi' );
const sitematrix = require( '../utils/sitematrix' );

const getCodes = sites => (
	sites.reduce( ( map, site ) => {
		return map.set( site.code.replace( /-/g, '_' ), {
			code: site.code,
			multi: !!site.languageCode
		} );
	}, new Map() )
);

const schema = Promise.resolve().then( async () => {
	const { sites } = await sitematrix;

	const codes = getCodes( sites );

	const siteTypes = [ ...codes.entries() ].map( ( [ code, options ] ) => {
		if ( options.multi ) {
			return `
				${code} (language: ID!): Site
			`;
		}

		return `
			${code}: Site
		`;
	} );

	return gql`
		type Query {
			${siteTypes.join( '' )}
			sites: [Site]
			language (code: ID!): Language
			languages: [Language]
		}
	`;
} );

const resolvers = Promise.resolve().then( async () => {
	const { sites, languages } = await sitematrix;

	const codes = getCodes( sites );

	const siteResolvers = [ ...codes.entries() ].reduce( ( acc, [ key, { multi, code } ] ) => {
		if ( multi ) {
			return {
				...acc,
				[ key ]: ( obj, { language } ) => {
					// Order the tags by most specific to least specific
					const tags = language.toLowerCase().split( '-' ).reduce( ( acc, curr ) => (
						[
							...acc,
							acc.length > 0 ? `${acc.join( '-' )}-${curr}` : curr
						]
					), [] ).reverse();

					return sites.filter( site => (
						// Remove irelevant sites.
						site.code === code
					) ).sort( ( a, b ) => (
						// Sort by number of breaks in the language code.
						b.languageCode.split( '-' ).length - a.languageCode.split( '-' ).length
					) ).find( site => (
						// Find the tag (starting with the most specific tag)
						!!tags.find( tag => tag === site.languageCode )
					) );
				}
			};
		}

		return {
			...acc,
			[ key ]: () => (
				sites.find( site => site.code === code )
			)
		};
	}, {} );

	return {
		Query: {
			...siteResolvers,
			sites: () => sites,
			language: ( obj, { code } ) => languages.find( l => l.code === code ),
			languages: () => languages
		}
	};
} );

module.exports = {
	schema,
	resolvers
};
