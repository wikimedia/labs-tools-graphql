const { ApolloServer } = require( 'apollo-server-hapi' );
const { Server } = require( 'hapi' );
const Accept = require( 'accept' );
const next = require( 'next' );
const API = require( './sources/api' );
const Query = require( './types/query' );
const Site = require( './types/site' );
const Page = require( './types/page' );
const Language = require( './types/language' );
const Entity = require( './types/entity' );
const sitematrix = require( './utils/sitematrix' );
const { pathWrapper, defaultHandlerWrapper, nextHandlerWrapper } = require( './next-wrapper' );

async function main() {
	const typeDefs = await Promise.all( [
		Language.schema,
		Query.schema,
		Site.schema,
		Page.schema,
		Entity.schema
	] );

	// Resolvers define the technique for fetching the types in the
	// schema.
	const resolvers = {
		...await Query.resolvers,
		...await Site.resolvers,
		...await Language.resolvers,
		...await Page.resolvers,
		...await Entity.resolvers
	};

	const dev = process.env.NODE_ENV !== 'production';
	const app = next( { dev } );

	await app.prepare();

	const { sites } = await sitematrix;

	// @TODO Add a caching server.
	// @see https://www.apollographql.com/docs/apollo-server/features/data-sources.html#Using-Memcached-Redis-as-a-cache-storage-backend
	const apollo = new ApolloServer( {
		typeDefs,
		resolvers,
		dataSources: () => {
			return sites.reduce( ( acc, { dbname, url } ) => (
				{
					...acc,
					[ dbname ]: new API( url )
				}
			), {} );
		},
		playground: false,
		introspection: true,
		context: ( { request } ) => {
			const languages = Accept.languages( request.headers[ 'accept-language' ] ).reduce( ( tags, tag ) => (
				[
					...tags,
					// Add the non-region tags to the list of tags. Keep the more specific
					// code at the top of the list. This implies that if the user specifies
					// only 'en-US', they would also accept 'en'.
					...tag.toLowerCase().split( '-' ).reduce( ( acc, curr ) => (
						[
							...acc,
							acc.length > 0 ? `${acc.join( '-' )}-${curr}` : curr
						]
					), [] ).reverse()
				]
			), [] );

			// add the languages to the context.
			return {
				languages,
				acceptLanguage: request.headers[ 'accept-language' ]
			};
		}
	} );

	const server = new Server( {
		port: 80
	} );

	await apollo.applyMiddleware( {
		app: server,
		path: '/',
		route: {
			pre: [
				// If there is no query to execute, and the request is from the browser,
				// take over the request before throwing an error.
				async ( request, h ) => {
					if ( request.method !== 'get' ) {
						return h.continue;
					}

					const mediaTypes = Accept.mediaTypes( request.headers.accept );
					if ( mediaTypes.length === 0 ) {
						return h.continue;
					}

					if ( mediaTypes[ 0 ] !== 'text/html' ) {
						return h.continue;
					}

					if ( request.query.query ) {
						return h.continue;
					}

					const response = await pathWrapper( app, '/' )( request, h );

					return h.response( response ).takeover();
				}
			],
			ext: {
				// If the request is from the browser, wrap the response in an in-browser
				// IDE.
				onPostHandler: {
					method: async ( request, h ) => {
						if ( request.method !== 'get' ) {
							return request.response;
						}

						const mediaTypes = Accept.mediaTypes( request.headers.accept );
						if ( mediaTypes.length === 0 ) {
							return request.response;
						}

						if ( mediaTypes[ 0 ] !== 'text/html' ) {
							return request.response;
						}

						const response = await pathWrapper( app, '/' )( request, h );

						return h.response( response );
					}
				}
			}
		}
	} );

	server.route( {
		method: 'GET',
		path: '/_next/{p*}', /* next specific routes */
		handler: nextHandlerWrapper( app )
	} );

	server.route( {
		method: 'GET',
		path: '/{p*}', /* catch all route */
		handler: defaultHandlerWrapper( app )
	} );

	await apollo.installSubscriptionHandlers( server.listener );

	try {
		await server.start();
		console.log( '> Ready on http://localhost:80' );
	} catch ( error ) {
		console.log( 'Error starting server' );
		console.log( error );
	}
}

main();
