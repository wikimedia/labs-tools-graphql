jest.mock( 'node-fetch' );

const mockLanguage = {
	code: 'qqq',
	name: 'Q',
	dir: 'ltr',
	localname: 'Q'
};

const mockWiki = {
	url: 'https://qqq.wikipedia.org',
	dbname: 'qqqwiki',
	code: 'wiki',
	sitename: 'Wikipedia'
};

const mockSpecialWiki = {
	url: 'https://qqqwiki.org',
	dbname: 'qqqwiki',
	code: 'wiki',
	sitename: 'Wikipedia'
};

const mockData = {
	sitematrix: {
		1: {
			...mockLanguage,
			site: [
				mockWiki
			]
		},
		specials: [
			mockSpecialWiki
		]
	}
};

const mockJson = jest.fn().mockResolvedValue( mockData );

const mockResponse = {
	ok: true,
	json: mockJson
};

beforeEach( () => {
	jest.resetModules();
} );

test( 'returns sitematrix', () => {
	const fetch = require( 'node-fetch' );
	fetch.mockResolvedValue( mockResponse );

	const sitematrix = require( './sitematrix' );
	return expect( sitematrix ).resolves.toEqual( {
		languages: [
			mockLanguage
		],
		sites: [
			{
				...mockWiki,
				languageCode: 'qqq',
				closed: false,
				fishbowl: false,
				'private': false
			},
			{
				...mockSpecialWiki,
				closed: false,
				fishbowl: false,
				'private': false
			}
		]
	} );
} );

test( 'fails to retrieve sitematrix', () => {
	const fetch = require( 'node-fetch' );
	fetch.mockResolvedValueOnce( {
		ok: false,
		json: mockJson
	} );

	const sitematrix = require( './sitematrix' );
	return expect( sitematrix ).rejects.toEqual( new Error( 'Could not retrieve sites!' ) );
} );
