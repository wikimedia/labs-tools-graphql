const { RESTDataSource } = require( 'apollo-datasource-rest' );
const DataLoader = require( 'dataloader' );

class API extends RESTDataSource {
	constructor( siteUrl ) {
		super();
		this.baseURL = siteUrl;

		// @TODO Move the data loaders into a different file!
		this.pageDataLoader = new DataLoader( async ( keys ) => {
			const requests = keys.reduce( ( acc, key ) => {
				// If there are unique props, find the first request where they match,
				// or they are non-existant.
				// @WARNING This will not work with default properties. To ensure that
				//          the proper result is returned, always explicity set the
				//          property even if the property is undefined!
				const item = acc.find( ( request ) => {
					if ( !!key.id !== request.id ) {
						return false;
					}

					if ( !!key.title !== request.title ) {
						return false;
					}

					if ( key.uniqueProps ) {
						for ( const prop in key.uniqueProps ) {
							if (
								prop in request.uniqueProps &&
								request.uniqueProps[ prop ] !== key.uniqueProps[ prop ]
							) {
								return false;
							}
						}
					}

					return true;
				} );

				if ( item ) {
					item.uniqueProps = {
						...item.uniqueProps || {},
						...key.uniqueProps || {}
					};
					item.keys.push( key );
					return acc;
				}

				acc.push(
					{
						id: !!key.id,
						title: !!key.title,
						uniqueProps: key.uniqueProps || {},
						keys: [
							key
						]
					}
				);

				return acc;
			}, [] );

			const requestList = await Promise.all(
				requests.map( ( { keys, id, title, uniqueProps } ) => {
					const props = [ ...new Set( keys.map( ( { prop } ) => prop ) ) ];

					if ( id ) {
						uniqueProps = {
							...uniqueProps,
							pageids: [ ...new Set( keys.map( ( { id } ) => id ) ) ].join( '|' )
						};
					}

					if ( title ) {
						uniqueProps = {
							...uniqueProps,
							titles: [ ...new Set( keys.map( ( { title } ) => title ) ) ].map(
								title => encodeURIComponent( title )
							).join( '|' )
						};
					}

					// Remove any boolean keys that are set. Missing is false, but
					// sending 'false' is considered true. Also, remove any undefined keys.
					for ( const prop in uniqueProps ) {
						if ( typeof uniqueProps[ prop ] === 'undefined' ) {
							delete uniqueProps[ prop ];
						}

						if ( uniqueProps[ prop ] === false ) {
							delete uniqueProps[ prop ];
						}

						if ( uniqueProps[ prop ] === true ) {
							uniqueProps[ prop ] = 1;
						}
					}

					return this.get( 'w/api.php', {
						action: 'query',
						format: 'json',
						formatversion: 2,
						prop: props.join( '|' ),
						...uniqueProps
					} );
				} )
			);

			return keys.map( ( key ) => {
				// First, find the index of the request it was on.
				const index = requests.findIndex( request => request.keys.includes( key ) );

				if ( !( 'query' in requestList[ index ] ) ) {
					return null;
				}

				if ( !( 'pages' in requestList[ index ].query ) ) {
					return null;
				}

				return requestList[ index ].query.pages.find(	page =>
					key.id === page.pageid || key.title === page.title
				);
			} );
		} );

		this.entityDataLoader = new DataLoader( async ( keys ) => {
			const ids = [ ...new Set( keys.map( ( { id } ) => id ) ) ];
			const props = [ ...new Set( keys.map( ( { prop } ) => prop ) ) ];
			let languages = [
				...new Set( keys.reduce( ( acc, curr ) => [ ...acc, ...curr.languages ], [] ) )
			];

			// If any item has requested all languages, then request them all.
			if ( languages.includes( '*' ) ) {
				languages = [ '*' ];
			}

			const entityList = await this.get( 'w/api.php', {
				action: 'wbgetentities',
				format: 'json',
				formatversion: 2,
				ids: ids.join( '|' ),
				props: props.join( '|' ),
				languages: languages.join( '|' )
			} );

			return keys.map( ( { id } ) => {
				if ( id in entityList.entities ) {
					return entityList.entities[ id ];
				}

				return null;
			} );
		} );
	}

	willSendRequest( request ) {
		// Set the Accept-Language header if there is one.
		if ( this.context.acceptLanguage ) {
			request.headers.set( 'Accept-Language', this.context.acceptLanguage );
		}
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
		const page = await this.pageDataLoader.load( {
			id,
			title,
			prop: 'extracts',
			uniqueProps: {
				exchars: args.chars,
				exsentences: args.sentences,
				exintro: args.intro || false,
				explaintext: args.plaintext || false,
				exsectionformat: args.sectionformat
			}
		} );

		if ( !page ) {
			return null;
		}

		return page.extract;
	}

	async getEntity( id, prop, languages ) {
		// Convert the language to an array.
		if ( typeof languages === 'undefined' ) {
			languages = [];
		}
		if ( typeof languages === 'string' ) {
			languages = [ languages ];
		}

		return this.entityDataLoader.load( { id, prop, languages: languages } );
	}
}

module.exports = API;
