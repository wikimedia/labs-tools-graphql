import React, { Fragment } from 'react';
import GraphiQL, { Logo } from 'graphiql';
import Head from 'next/head';
import Router from 'next/router';
import 'graphiql/graphiql.css';
import '../styles/styles.css';
/* global window fetch */

const fetcher = params => (
	fetch( '/', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify( params )
	} ).then( response => response.json() )
);

const onEdit = ( key, value ) => {
	if ( typeof window !== 'object' ) {
		return;
	}

	const params = new URLSearchParams( window.location.search.substring( 1 ) );
	if ( value ) {
		params.set( key, value );
	} else {
		params.delete( key );
	}

	const qs = params.toString();
	let path = '/';
	if ( qs ) {
		path = '/?' + qs;
	}

	Router.replace( path, path, { shallow: true } );
};

const mockStorage = {
	getItem: () => undefined
};

class Index extends React.Component {

	shouldComponentUpdate() {
		// Never re-render the compoment, even if the state/props change.
		return false;
	}

	static async getInitialProps( { query: { query, variables, response }, req } ) {
		if ( !req ) {
			const data = await fetcher( query );
			response = JSON.stringify( data, null, 2 );
		}

		return {
			query,
			variables,
			response
		};
	}

	render() {
		let storage;
		if ( typeof window !== 'object' ) {
			storage = {
				...mockStorage
			};
		}

		return (
			<Fragment>
				<Head>
					<link rel="stylesheet" href="/_next/static/style.css" />
				</Head>
				<GraphiQL
					fetcher={fetcher}
					storage={storage}
					onEditQuery={query => onEdit( 'query', query )}
					onEditVariables={variables => onEdit( 'variables', variables )}
					{...this.props}
				>
					<Logo>
						<img
							src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2d/Wikimedia-logo-blackandwhite.png/145px-Wikimedia-logo-blackandwhite.png"
							alt="Wikimedia"
						/>
					</Logo>
				</GraphiQL>
			</Fragment>
		);
	}
}

export default Index;
