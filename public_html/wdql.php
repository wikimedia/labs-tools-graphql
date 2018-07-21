<?php

use GraphQL\Error\Debug;
use GraphQL\Server\StandardServer;
use Tptools\GraphQL\WikibaseRegistry;

// TODO: bad but useful for deeply nested fields
ini_set( 'xdebug.max_nesting_level', 200 );

header( 'Access-Control-Allow-Origin: *' );
header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
header( 'Access-Control-Allow-Headers: Content-Type' );

if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
	exit;
}

include __DIR__ . '/../vendor/autoload.php';

$server = new StandardServer( [
	'schema' => WikibaseRegistry::newForWikidata()->schema(),
	'debug' => Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE
] );

$server->handleRequest();
