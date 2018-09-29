const sitematrix = require( '../utils/sitematrix' );

const languageResolver = async ( obj, { code }, { languages: acceptLanguages } ) => {
	const { languages } = await sitematrix;

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
};

module.exports = languageResolver;
