const { RESTDataSource } = require( 'apollo-datasource-rest' );

class API extends RESTDataSource {
	constructor( siteUrl ) {
		super();
		this.baseURL = siteUrl;
		this.restBase = 'api/rest_v1';
		this.entityDataBase = 'wiki/Special:EntityData';
	}

	willSendRequest( request ) {
		// Set the Accept-Language header if there is one.
		if ( this.context.acceptLanguage ) {
			request.headers.set( 'Accept-Language', this.context.acceptLanguage );
		}
	}

	async getPageSummary( title ) {
		return this.get( `${this.restBase}/page/summary/${encodeURIComponent( title.replace( / /g, '_' ) )}` );
	}

	async getEntity( id ) {
		const query = await this.get( `${this.entityDataBase}/${id}.json` );

		return query.entities[ id ];
	}
}

module.exports = API;
