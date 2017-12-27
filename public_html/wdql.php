<?php

use GraphQL\Error\Debug;
use GraphQL\Server\StandardServer;
use Tptools\GraphQL\WikibaseRegistry;

include __DIR__ . '/../vendor/autoload.php';

$server = new StandardServer([
    'schema' => WikibaseRegistry::newForWikidata()->schema(),
    'debug' => Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE
]);
$server->handleRequest();
