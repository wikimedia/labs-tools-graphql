const sitematrix = require( '../utils/sitematrix' );

const languageResolver = async ( obj, { code: codes }, { languages: acceptLanguages } ) => {
	const { languages } = await sitematrix;
	codes = codes || acceptLanguages;

	if ( codes ) {
		for ( let i = 0; i < codes.length; i++ ) {
			const code = codes[ i ];
			const language = languages.find( l => l.code === code );
			if ( language ) {
				return language;
			}
		}
	}

	return null;
};

module.exports = languageResolver;
