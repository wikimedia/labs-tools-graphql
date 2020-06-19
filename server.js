const { ApolloServer } = require( 'apollo-server' );
const Accept = require( 'accept' );
const Action = require( './sources/action' );
const Sparql = require( './sources/sparql' );
const Query = require( './types/query' );
const Site = require( './types/site' );
const Page = require( './types/page' );
const Language = require( './types/language' );
const Entity = require( './types/entity' );
const sitematrix = require( './utils/sitematrix' );

const PORT = process.env.PORT || 3000;

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

	const { sites } = await sitematrix;

	// @TODO Add a caching server.
	// @see https://www.apollographql.com/docs/apollo-server/features/data-sources.html#Using-Memcached-Redis-as-a-cache-storage-backend
	const server = new ApolloServer( {
		typeDefs,
		resolvers,
		dataSources: () => sites.reduce( ( acc, { dbname, url } ) => {
			// @TODO Resolve this dynamicly!
			if ( dbname === 'wikidatawiki' ) {
				return {
					...acc,
					[ dbname ]: new Action( url ),
					[ `${dbname}.sparql` ]: new Sparql( 'https://query.wikidata.org/' )
				};
			}

			return {
				...acc,
				[ dbname ]: new Action( url )
			};
		}, {} ),
		playground: {
			theme: 'light'
		},
		introspection: true,
		context: ( { req } ) => {
			const languages = Accept.languages( req.headers[ 'accept-language' ] ).reduce( ( tags, tag ) => (
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
				acceptLanguage: req.headers[ 'accept-language' ]
			};
		}
	} );

	const { url } = await server.listen( {
		port: PORT
	} );

	console.log( `Server ready at ${url}` );
}

main();
