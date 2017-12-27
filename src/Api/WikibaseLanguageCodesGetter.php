<?php

namespace Tptools\Api;

use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\SimpleRequest;
use UnexpectedValueException;

class WikibaseLanguageCodesGetter {

	private $api;

	public function __construct( MediawikiApi $api ) {
		$this->api = $api;
	}

	/**
	 * @return string[]
	 */
	public function get() {
		$result = $this->api->getRequest( new SimpleRequest( 'paraminfo', [
			'modules' => 'wbsetlabel'
		] ) );
		foreach ( $result['paraminfo']['modules'] as $module ) {
			foreach ( $module['parameters'] as $parameter ) {
				if ( $parameter['name'] === 'language' ) {
					return $parameter['type'];
				}
			}
		}
		throw new UnexpectedValueException( 'Unexpected description of the wbsetlabel module' );
	}
}
