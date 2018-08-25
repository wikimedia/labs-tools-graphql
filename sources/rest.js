const { RESTDataSource } = require( 'apollo-datasource-rest' );

class REST extends RESTDataSource {
	constructor( siteUrl ) {
		super();
		this.baseURL = siteUrl + '/api/rest_v1/';
	}

	async getPageSummary( title ) {
		return this.get( `page/summary/${title.replace( / /g, '_' )}` );
	}
}

module.exports = REST;
