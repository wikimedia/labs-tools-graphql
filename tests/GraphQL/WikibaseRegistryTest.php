<?php

namespace Tptools\GraphQL;

use PHPUnit_Framework_TestCase;

class WikibaseRegistryTest extends PHPUnit_Framework_TestCase {
    public function testSchemaValidity() {
        WikibaseRegistry::newForWikidata()->schema()->assertValid();
    }
}