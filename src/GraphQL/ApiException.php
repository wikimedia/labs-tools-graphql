<?php

namespace Tptools\GraphQL;

use Exception;
use GraphQL\Error\ClientAware;
use Throwable;

class ApiException extends Exception implements ClientAware {

	public function __construct( $message = '', $code = 0, Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
	}

	public function isClientSafe() {
		return true;
	}

	public function getCategory() {
		return 'api';
	}
}
