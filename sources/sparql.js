const { RESTDataSource } = require( 'apollo-datasource-rest' );
const get = require( 'lodash/get' );

class Sparql extends RESTDataSource {
	constructor( siteUrl ) {
		super();
		this.baseURL = siteUrl;
	}

	async query( { distinct, select, where, orderBy, limit } ) {
		let query = 'SELECT';

		if ( distinct ) {
			query = `${query} DISTINCT`;
		}

		if ( select ) {
			query = `${query} ${select}`;
		}

		if ( where ) {
			query = `${query} WHERE { ${where} }`;
		}

		if ( orderBy ) {
			query = `${query} ORDER BY ${orderBy}`;
		}

		if ( limit ) {
			query = `${query} LIMIT ${limit}`;
		}

		const result = await this.get( 'sparql', {
			query
		}, {
			headers: {
				Accept: 'application/json'
			}
		} );

		const data = JSON.parse( result );

		const bindings = get( data, [ 'results', 'bindings' ], [] );

		return bindings.reduce( ( acc, binding ) => {
			const uri = get( binding, [ 'entity', 'value' ] );

			if ( !uri ) {
				return acc;
			}

			const url = new URL( uri );
			const id = url.pathname.split( '/' ).slice( -1 ).pop();

			return [
				...acc,
				id
			];
		}, [] );
	}
}

module.exports = Sparql;
