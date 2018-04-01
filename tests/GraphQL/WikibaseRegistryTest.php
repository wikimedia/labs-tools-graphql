<?php

namespace Tptools\GraphQL;

use PHPUnit\Framework\TestCase;

/**
 * @covers WikibaseRegistry
 */
class WikibaseRegistryTest extends TestCase {
	public function testSchemaValidity() {
		WikibaseRegistry::newForWikidata()->schema()->assertValid();
	}
}
