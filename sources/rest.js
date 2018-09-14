const { RESTDataSource } = require( 'apollo-datasource-rest' );

class REST extends RESTDataSource {
	constructor( siteUrl ) {
		super();
		this.baseURL = siteUrl + '/api/rest_v1/';
	}

	willSendRequest( request ) {
		// Set the Accept-Language header if there is one.
		if ( this.context.acceptLanguage ) {
			request.headers.set( 'Accept-Language', this.context.acceptLanguage );
		}
	}

	async getPageSummary( title ) {
		return this.get( `page/summary/${encodeURIComponent( title.replace( / /g, '_' ) )}` );
	}
}

module.exports = REST;
