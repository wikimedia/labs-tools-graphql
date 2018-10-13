const { RESTDataSource } = require( 'apollo-datasource-rest' );
const DataLoader = require( 'dataloader' );
const get = require( 'lodash/get' );

class Action extends RESTDataSource {
	constructor( siteUrl ) {
		super();
		this.baseURL = siteUrl;

		// @TODO Move the data loaders into a different file!
		this.dataLoader = new DataLoader( async ( keys ) => {
			const requests = keys.reduce( ( acc, key ) => {
				const merge = {};
				for ( const prop in key.merge ) {
					let data;
					if ( typeof key.merge[ prop ] === 'undefined' ) {
						continue;
					}
					// Convert the data to an array if it isn't already.
					data = Array.isArray( key.merge[ prop ] ) ?
						key.merge[ prop ] :
						[ key.merge[ prop ] ];

					// Remove any duplicate entries.
					data = [ ...new Set( data ) ];

					// If the
					if ( data.length === 0 ) {
						continue;
					}

					merge[ prop ] = data;
				}

				// If there are unique props, find the first request where they match,
				// or they are non-existant.
				// @WARNING This will not work with default properties. To ensure that
				//          the proper result is returned, always explicity set the
				//          property even if the property is undefined!
				const item = acc.find( ( request ) => {
					// A request cannot have both an id and title parameter. These keys
					// are merge keys since they are a list, but they are unique in the
					// fact that you can have one or the other.
					// @TODO Figure out a better way to handle mutually exclusive properties.
					if ( !!request.merge.pageids && !!merge.titles ) {
						return false;
					}

					if ( !!request.merge.titles && !!merge.pageids ) {
						return false;
					}

					// Each unique key must only appear on the request a single time
					// with the same value.
					if ( key.unique ) {
						for ( const prop in key.unique ) {
							if (
								prop in request.unique &&
								request.unique[ prop ] !== key.unique[ prop ]
							) {
								return false;
							}
						}
					}

					return true;
				} );

				if ( item ) {
					for ( const prop in merge ) {
						// Use a set to dedupe the values.
						item.merge[ prop ] = [ ...new Set( [
							...item.merge[ prop ] || [],
							...merge[ prop ]
						] ) ];
					}
					item.unique = {
						...item.unique || {},
						...key.unique || {}
					};
					item.keys.push( key );
					return acc;
				}

				acc.push(
					{
						merge: merge || {},
						unique: key.unique || {},
						keys: [
							key
						]
					}
				);

				return acc;
			}, [] );

			const responses = await Promise.all(
				requests.map( ( { merge, unique } ) => {
					// Conver the merge props to a string.
					for ( const prop in merge ) {
						// If any item has requested all languages, then request them all.
						if ( prop === 'languages' && merge[ prop ].includes( '*' ) ) {
							merge[ prop ] = '*';
							continue;
						}

						// Delete empty arrays.
						if ( merge[ prop ].length === 0 ) {
							delete merge[ prop ];
							continue;
						}

						merge[ prop ] = merge[ prop ].join( '|' );
					}

					// Remove any boolean keys that are set. Missing is false, but
					// sending 'false' is considered true. Also, remove any undefined keys.
					for ( const prop in unique ) {
						if ( typeof unique[ prop ] === 'undefined' ) {
							delete unique[ prop ];
						}

						if ( unique[ prop ] === false ) {
							delete unique[ prop ];
						}

						if ( unique[ prop ] === true ) {
							unique[ prop ] = 1;
						}
					}

					return this.get( 'w/api.php', {
						format: 'json',
						formatversion: 2,
						...merge,
						...unique
					} );
				} )
			);

			return keys.map( ( key ) => {
				// First, find the index of the request it was on.
				const index = requests.findIndex( request => request.keys.includes( key ) );

				return responses[ index ];
			} );
		} );
	}

	willSendRequest( request ) {
		// Set the Accept-Language header if there is one.
		if ( this.context.acceptLanguage ) {
			request.headers.set( 'Accept-Language', this.context.acceptLanguage );
		}
	}

	getPageFromData( { id, title }, data ) {
		const pages = get( data, [ 'query', 'pages' ] );

		if ( !pages ) {
			return null;
		}

		return pages.find(	page =>
			id === page.pageid || title === page.title
		);
	}

	async getPageIds( { id, title } ) {
		const data = await this.dataLoader.load( {
			merge: {
				pageids: id,
				titles: title
			},
			unique: {
				action: 'query'
			}
		} );

		return this.getPageFromData( { id, title }, data );
	}

	/**
	 * @param {object} page
	 * @param {int} [page.id]
	 * @param {string} [page.title]
	 * @param {object} [args={}]
	 * @param {int} [args.chars]
	 * @param {int} [args.sentences]
	 * @param {bool} [args.intro=false]
	 * @param {bool} [args.plaintext=false]
	 * @param {int} [args.sectionformat]
	 */
	async getPageExtract( { id, title }, args = {} ) {
		const data = await this.dataLoader.load( {
			merge: {
				pageids: id,
				titles: title,
				prop: 'extracts'
			},
			unique: {
				action: 'query',
				exchars: args.chars,
				exsentences: args.sentences,
				exintro: args.intro || false,
				explaintext: args.plaintext || false,
				exsectionformat: args.sectionformat
			}
		} );

		const page = this.getPageFromData( { id, title }, data );

		return page ? page.extract : null;
	}

	async getEntity( id, prop, languages = [] ) {
		const data = await this.dataLoader.load( {
			merge: {
				ids: id,
				props: prop,
				languages
			},
			unique: {
				action: 'wbgetentities'
			}
		} );

		return get( data, [ 'entities', id ] );
	}
}

module.exports = Action;
