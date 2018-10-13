const { gql } = require( 'apollo-server-hapi' );
const sitematrix = require( '../utils/sitematrix' );
const getCodes = require( '../utils/codes' );
const { resolvers: siteResolvers } = require( '../resolvers/site' );
const languageResolver = require( '../resolvers/language' );

const schema = Promise.resolve().then( async () => {
	const { sites } = await sitematrix;

	const codes = getCodes( sites );

	const siteTypes = [ ...codes.entries() ].map( ( [ code, options ] ) => {
		if ( options.multi ) {
			return `
				${code} (
					"If no language is specified, the language tag from the 'Accept-Language' header will be used."
					language: ID
				): SiteLink
			`;
		}

		return `
			${code}: SiteLink
		`;
	} );

	return gql`
		type SiteLinkMap {
			${siteTypes.join( '' )}
			sites: [SiteLink]!

			language (
				"If no code is specified, the language tag from the 'Accept-Language' header will be used."
				code: ID
			): SiteLinkLanguage
			languages: [SiteLinkLanguage]!
		}
		type SiteLinkLanguage {
			# Language. GraphQL doesn't support type inheritence.
			code: ID!
			name: String!
			localname: String!
			dir: String!
			site(code: ID!): SiteLink
			sites: [SiteLink]!
		}
		type EntityLabel {
			language: String!
			value: String!
		}
		type SiteLink {
			# Site (sans 'page' which is different). GraphQL doesn't support type inheritence.
			dbname: ID!
			url: String!
			code: String!
			sitename: String!
			closed: Boolean!
			fishbowl: Boolean!
			private: Boolean!
			language: Language
			# Page with no argument since it's provided by the sitelink.
			page: Page
		}
		type Entity {
			pageid: Int!
			ns: Int!
			title: String!
			lastrevid: Int!
			modified: String!
			type: String!
			id: ID!
			label(language: String): EntityLabel
			labels: [EntityLabel]!
			sitelinks: SiteLinkMap
		}
	`;
} );

const infoResolver = prop => async ( { id, __site: { dbname } }, args, { dataSources } ) => {
	const entity = await dataSources[ dbname ].getEntity( id, 'info' );

	if ( !entity ) {
		return null;
	}

	return entity[ prop ];
};

const labelResolver = async (
	{ id, __site: { dbname } },
	{ language },
	{ dataSources, languages: acceptLanguages }
) => {
	const entity = await dataSources[ dbname ].getEntity( id, 'labels', language || acceptLanguages );

	if ( !entity ) {
		return null;
	}

	if ( !( 'labels' in entity ) ) {
		return null;
	}

	if ( language ) {
		if ( language in entity.labels ) {
			return entity.labels[ language ];
		}

		return null;
	}

	const preferedLabels = Object.values( entity.labels ).filter( label => (
		// Remove irelevant sites.
		acceptLanguages.includes( label.language )
	) ).sort( ( a, b ) => (
		// Sort by preference.
		acceptLanguages.findIndex(
			tag => tag === a.language
		) - acceptLanguages.findIndex(
			tag => tag === b.language
		)
	) );

	return preferedLabels.length > 0 ? preferedLabels[ 0 ] : undefined;
};

const labelsResolvers = async ( { id, __site: { dbname } }, args, { dataSources } ) => {
	const entity = await dataSources[ dbname ].getEntity( id, 'labels', '*' );

	if ( !entity ) {
		return [];
	}

	if ( !( 'labels' in entity ) ) {
		return [];
	}

	return Object.values( entity.labels );
};

const resolveSiteLink = callback => async ( sitelinks, args, info, context ) => {
	const site = callback( sitelinks, args, info, context );

	if ( !site ) {
		return null;
	}

	if ( !( site.dbname in sitelinks ) ) {
		return null;
	}

	return {
		...site,
		__sitelink: sitelinks[ site.dbname ]
	};
};

const resolveSiteLinks = callback => async ( sitelinks, args, info, context ) => (
	( await callback( sitelinks, args, info, context ) ).map( ( site ) => {
		if ( !( site.dbname in sitelinks ) ) {
			return null;
		}

		return {
			...site,
			__sitelink: sitelinks[ site.dbname ]
		};
	} ).filter( data => !!data )
);

const siteLinkLanguage = ( sitelinks, language, sites ) => {
	const langSiteLinks = sites.filter( site => (
		site.languageCode === language.code && ( site.dbname in sitelinks )
	) );

	if ( langSiteLinks.length === 0 ) {
		return null;
	}

	return {
		...language,
		sites: langSiteLinks.map( site => ( {
			...site,
			__sitelink: sitelinks[ site.dbname ]
		} ) )
	};
};

const resolveLanguageSite = async ( sitelinks, args, info, context ) => {
	const [ language, { sites } ] = await Promise.all( [
		languageResolver( sitelinks, args, info, context ),
		sitematrix
	] );

	return siteLinkLanguage( sitelinks, language, sites );
};

const resolveLanguageSites = async ( sitelinks ) => {
	const { languages, sites } = await sitematrix;

	return languages.map(
		language => siteLinkLanguage( sitelinks, language, sites )
	).filter( language => !!language );
};

const resolvers = Promise.resolve().then( async () => {
	const { sites } = await sitematrix;

	const siteResolverMap = await siteResolvers();
	for ( const key in siteResolverMap ) {
		siteResolverMap[ key ] = resolveSiteLink( siteResolverMap[ key ] );
	}

	return {
		SiteLink: {
			page: ( site ) => {
				const { __sitelink: sitelink } = site;

				if ( !( 'title' in sitelink ) ) {
					return null;
				}

				return {
					__site: site,
					title: sitelink.title
				};
			}
		},
		SiteLinkMap: {
			...siteResolverMap,
			sites: resolveSiteLinks( () => sites ),
			language: resolveLanguageSite,
			languages: resolveLanguageSites
		},
		SiteLinkLanguage: {
			site: ( language, { code } ) => language.sites.find( site => site.code === code )
		},
		Entity: {
			pageid: infoResolver( 'pageid' ),
			ns: infoResolver( 'ns' ),
			title: infoResolver( 'title' ),
			lastrevid: infoResolver( 'lastrevid' ),
			modified: infoResolver( 'modified' ),
			type: infoResolver( 'type' ),
			id: infoResolver( 'id' ),
			label: labelResolver,
			labels: labelsResolvers,
			sitelinks: async ( { id, __site: { dbname } }, args, { dataSources } ) => {
				const entity = await dataSources[ dbname ].getEntity( id, 'sitelinks' );

				if ( !entity ) {
					return {};
				}

				return entity.sitelinks;
			}
		}
	};
} );

module.exports = {
	schema,
	resolvers
};
