const sitematrix = require( '../utils/sitematrix' );
const getCodes = require( '../utils/codes' );

const siteResolvers = async () => {
	const { sites } = await sitematrix;

	const codes = getCodes( sites );

	return [ ...codes.entries() ].reduce( ( acc, [ key, { multi, code } ] ) => {
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
};

module.exports = {
	resolvers: siteResolvers,
	sites: async ( language ) => {
		const { sites } = await sitematrix;

		return sites.filter( site => site.languageCode === language.code );
	},
	site: async ( language, { code } ) => {
		const { sites } = await sitematrix;

		return sites.find( site => (
			site.code === code && site.languageCode === language.code
		) );
	}
};
