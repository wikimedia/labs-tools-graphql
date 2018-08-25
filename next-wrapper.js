const { parse } = require( 'url' );

const nextHandlerWrapper = ( app ) => {
	const handler = app.getRequestHandler();
	return async ( { raw, url }, h ) => {
		await handler( raw.req, raw.res, url );
		return h.close;
	};
};

const defaultHandlerWrapper = app => async ( { raw: { req, res }, url } ) => {
	const { pathname, query } = parse( url, true );
	return app.renderToHTML( req, res, pathname, query );
};

const pathWrapper = ( app, pathName, opts ) => async ( {
	raw,
	query,
	params,
	response
} ) => {
	let data;
	if ( response && response.source ) {
		// Add proper spacing to JSON response.
		data = JSON.stringify( JSON.parse( response.source ), null, 2 );
	}

	return app.renderToHTML( raw.req, raw.res, pathName, {
		...query,
		...params,
		response: data
	}, opts );
};

module.exports = { pathWrapper, defaultHandlerWrapper, nextHandlerWrapper };
