const code = sites => (
	sites.reduce( ( map, site ) => {
		return map.set( site.code.replace( /-/g, '_' ), {
			code: site.code,
			multi: !!site.languageCode
		} );
	}, new Map() )
);

module.exports = code;
