<?php

namespace Tptools\GraphQL;

use PHPUnit_Framework_TestCase;
use Symfony\Component\Cache\Simple\ArrayCache;

class WikibaseRegistryTest extends PHPUnit_Framework_TestCase {
    public function testSchemaValidity() {
        WikibaseRegistry::newForWikidata( new ArrayCache() )->schema()->assertValid();
    }
}