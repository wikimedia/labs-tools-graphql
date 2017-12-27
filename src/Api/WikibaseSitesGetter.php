<?php

namespace Tptools\Api;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use UnexpectedValueException;

class WikibaseSitesGetter {

	private $api;

	public function __construct( MediawikiApi $api ) {
		$this->api = $api;
	}

	/**
	 * @return string[]
	 */
	public function get() {
		$result = $this->api->getRequest( new SimpleRequest( 'paraminfo', [
			'modules' => 'wbsetsitelink'
		] ) );
		foreach ( $result['paraminfo']['modules'] as $module ) {
			foreach ( $module['parameters'] as $parameter ) {
				if ( $parameter['name'] === 'site' ) {
					return $parameter['type'];
				}
			}
		}
		throw new UnexpectedValueException( 'Unexpected description of the wbsetsitelink module' );
	}
}
