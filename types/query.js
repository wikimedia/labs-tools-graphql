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
				${code} (
					"If no language is specified, the language tag from the 'Accept-Language' header will be used."
					language: ID
				): MediaWikiSite
			`;
		}

		// @TODO Use the __resolveType method.
		switch ( code ) {
			case 'wikidata':
				return `
					${code}: WikibaseSite
				`;
			default:
				return `
					${code}: MediaWikiSite
				`;
		}
	} );

	return gql`
		type Query {
			${siteTypes.join( '' )}
			sites: [Site]

			language (
				"If no code is specified, the language tag from the 'Accept-Language' header will be used."
				code: ID
			): Language
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
				[ key ]: ( obj, { language }, { languages: acceptLanguages } ) => {
					if ( language ) {
						return sites.find( site => (
							site.code === code && site.languageCode === language
						) );
					}

					const preferedSites = sites.filter( site => (
						// Remove irelevant sites.
						site.code === code && acceptLanguages.includes( site.languageCode )
					) ).sort( ( a, b ) => (
						// Sort by preference.
						acceptLanguages.findIndex(
							tag => tag === a.languageCode
						) - acceptLanguages.findIndex(
							tag => tag === b.languageCode
						)
					) );

					return preferedSites.length > 0 ? preferedSites[ 0 ] : undefined;
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
			language: ( obj, { code }, { languages: acceptLanguages } ) => {
				if ( code ) {
					return languages.find( l => l.code === code );
				}

				const preferedLanguages = languages.filter( lang => (
					acceptLanguages.includes( lang.code )
				) ).sort( ( a, b ) => (
					// Sort by number of breaks in the language code.
					// Sort by preference.
					acceptLanguages.findIndex(
						tag => tag === a.code
					) - acceptLanguages.findIndex(
						tag => tag === b.code
					)
				) );

				return preferedLanguages.length > 0 ? preferedLanguages[ 0 ] : undefined;
			},
			languages: () => languages
		}
	};
} );

module.exports = {
	schema,
	resolvers
};
