<?php

use GraphQL\Error\Debug;
use GraphQL\Server\StandardServer;
use Tptools\GraphQL\WikibaseRegistry;

// TODO: bad but useful for deeply nested fields
ini_set( 'xdebug.max_nesting_level', 200 );

include __DIR__ . '/../vendor/autoload.php';

$server = new StandardServer( [
	'schema' => WikibaseRegistry::newForWikidata()->schema(),
	'debug' => Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE
] );

header( 'Access-Control-Allow-Origin: *' );
$server->handleRequest();
