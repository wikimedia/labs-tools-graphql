const sitematrix = require( '../utils/sitematrix' );
const getCodes = require( '../utils/codes' );

const siteResolvers = async () => {
	const { sites } = await sitematrix;

	const codes = getCodes( sites );

	return [ ...codes.entries() ].reduce( ( acc, [ key, { multi, code } ] ) => {
		if ( multi ) {
			return {
				...acc,
				[ key ]: ( obj, { language: languages }, { languages: acceptLanguages } ) => {
					languages = languages || acceptLanguages;

					for ( let i = 0; i < languages.length; i++ ) {
						const language = languages[ i ];
						const site = sites.find( site => (
							site.code === code && site.languageCode === language
						) );

						if ( site ) {
							return site;
						}
					}

					return null;
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
	sites: async ( language, { code: codes } ) => {
		let { sites } = await sitematrix;

		sites = sites.filter( site => site.languageCode === language.code );

		if ( codes ) {
			return codes.reduce( ( acc, code ) => {
				const site = sites.find( site => site.code === code );

				if ( site ) {
					return [
						...acc,
						site
					];
				}

				return acc;
			}, [] );
		}

		return sites;
	},
	site: async ( language, { code } ) => {
		const { sites } = await sitematrix;

		return sites.find( site => (
			site.code === code && site.languageCode === language.code
		) );
	}
};
