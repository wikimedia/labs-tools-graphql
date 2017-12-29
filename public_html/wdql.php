<?php

use GraphQL\Error\Debug;
use GraphQL\Server\StandardServer;
use Symfony\Component\Cache\Simple\ArrayCache;
use Symfony\Component\Cache\Simple\ChainCache;
use Symfony\Component\Cache\Simple\RedisCache;
use Tptools\GraphQL\WikibaseRegistry;

include __DIR__ . '/../vendor/autoload.php';

$cache = new ArrayCache();
if ( extension_loaded( 'redis' ) ) {
	$cache = new ChainCache( [
		$cache,
		new RedisCache( RedisCache::createConnection( 'redis://tools-redis:6379' ), 'wdql' )
	] );
}

$server = new StandardServer([
    'schema' => WikibaseRegistry::newForWikidata( $cache )->schema(),
    'debug' => Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE
]);
$server->handleRequest();
