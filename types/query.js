const { gql } = require( 'apollo-server-hapi' );
const sitematrix = require( '../utils/sitematrix' );
const { resolvers: siteResolvers } = require( '../resolvers/site' );
const languageResolver = require( '../resolvers/language' );
const getCodes = require( '../utils/codes' );

const schema = Promise.resolve().then( async () => {
	const { sites } = await sitematrix;

	const codes = getCodes( sites );

	const siteTypes = [ ...codes.entries() ].map( ( [ code, options ] ) => {
		if ( options.multi ) {
			return `
				${code} (
					"If no language is specified, the language tag from the 'Accept-Language' header will be used."
					language: ID
				): Site
			`;
		}

		return `
			${code}: Site
		`;
	} );

	return gql`
		type Query {
			${siteTypes.join( '' )}
			sites: [Site]!

			language (
				"If no code is specified, the language tag from the 'Accept-Language' header will be used."
				code: ID
			): Language
			languages: [Language]!
		}
	`;
} );

const resolvers = Promise.resolve().then( async () => {
	const { sites, languages } = await sitematrix;

	return {
		Query: {
			...await siteResolvers(),
			sites: () => sites,
			language: languageResolver,
			languages: () => languages
		}
	};
} );

module.exports = {
	schema,
	resolvers
};
