<?php

ini_set( 'display_errors', 1 );
ini_set( 'display_startup_errors', 1 );
error_reporting( E_ALL );

use Tptools\MicrodataToWikidataConverter;

include __DIR__ . '/../vendor/autoload.php';

header( 'Access-Control-Allow-Origin: https://www.wikidata.org' );

if ( !array_key_exists( 'title', $_GET ) ) {
	http_response_code( 400 );
	print 'You should set the "title" query parameter';
	exit();
}

$title = $_GET['title'];
header( 'Content-Type: application/json' );
print json_encode(
	( new MicrodataToWikidataConverter() )->toWikidata( $title ),
	JSON_PRETTY_PRINT
);
