const fetch = require( 'node-fetch' );

const buildMap = ( obj ) => {
	const map = new Map();
	Object.keys( obj ).forEach( ( key ) => {
		map.set( key, obj[ key ] );
	} );
	return map;
};

const formatLanguage = ( { code, name, localname, dir } ) => ( {
	code,
	name,
	localname,
	dir
} );

// Staticlly cache the site data to the request. The server will need to be
// restarted to refresh this list!
const sitesLanguages = Promise.resolve().then( async () => {
	const url = 'https://meta.wikimedia.org/w/api.php?action=sitematrix&format=json&formatversion=2';
	const response = await fetch( url );
	const data = await response.json();

	if ( !response.ok ) {
		console.log( data );
		throw Error( 'Could not retrieve sites!' );
	}

	// Get a map of all the lanugages.
	const languages = buildMap( data.sitematrix );
	languages.delete( 'count' );

	// Pull the specials out.
	const specials = languages.get( 'specials' );
	languages.delete( 'specials' );

	const sites = [
		...[ ...languages.values() ].reduce( ( acc, lang ) => (
			[
				...acc,
				...lang.site.map( ( site ) => {
					return {
						...site,
						languageCode: lang.code
					};
				} )
			]
		), [] ),
		...specials
	];

	return {
		sites: sites.map( site => ( {
			...site,
			closed: site.closed || false,
			fishbowl: site.fishbowl || false,
			'private': site.private || false
		} ) ),
		languages: [ ...languages.values() ].map( formatLanguage )
	};
} );

module.exports = sitesLanguages;
