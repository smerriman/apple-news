<?php
namespace Push_API;

require_once __DIR__ . '/class-mime-builder.php';
require_once __DIR__ . '/class-request-curl.php';

/**
 * An object capable of sending signed HTTP requests to the Push API.
 *
 * @since 0.0.0
 */
class Request {

	/**
	 * Helper class used to build the MIME parts of the request.
	 *
	 * @var MIME_Builder
	 * @since 0.0.0
	 */
	private $mime_builder;

	/**
	 * Whether or not we are debugging using a reverse proxy, like Charles.
	 *
	 * @var boolean
	 * @since 0.0.0
	 */
	private $debug;

	/**
	 * The credentials that will be used to sign sent requests.
	 *
	 * @var Credentials
	 * @since 0.0.0
	 */
	private $credentials;

	function __construct( $credentials, $debug = false, $mime_builder = null ) {
		$this->credentials  = $credentials;
		$this->debug        = $debug;
		$this->mime_builder = $mime_builder ?: new MIME_Builder();
	}

	public function post( $url, $article, $bundles = array() ) {
		$curl = new Request_CURL( $url, $this->debug );

		$content   = $this->build_content( $article, $bundles );
		$signature = $this->sign( $url, $content );
		$response  = $curl->post( $content, $this->mime_builder->boundary(), $signature );

		if ( property_exists( $response, 'errors' ) ) {
			$string_errors = '';
			foreach ( $response->errors as $error ) {
				$string_errors .= $error->code . "\n";
			}
			throw new Request_Exception( "There has been an error with your request:\n$string_errors" );
		}

		return $response;
	}

	public function get( $url ) {
		$curl = new Request_CURL( $url, $this->debug );

		$signature = $this->sign( $url );
		$response  = $curl->get( $signature );

		if ( property_exists( $response, 'errors' ) ) {
			$string_errors = '';
			foreach ( $response->errors as $error ) {
				$string_errors .= $error->code . "\n";
			}
			throw new Request_Exception( "There has been an error with your request:\n$string_errors" );
		}

		return $response;
	}


	// TODO The exporter has an abstracted article class. Should we have
	// something similar here? That way this method could live there.
	private function build_content( $article, $bundles = array() ) {
		$content = $this->mime_builder->add_json_string( 'my_article', 'article.json', $article );
		foreach ( $bundles as $bundle ) {
			$content .= $this->mime_builder->add_content_from_file( $bundle );
		}
		$content .= $this->mime_builder->close();

		return $content;
	}

	private function sign( $url, $content = null ) {
		$current_date = date( 'c' );
		$verb         = is_null( $content ) ? 'GET' : 'POST';

		$request_info = $verb . $url . $current_date;
		if ( 'POST' == $verb ) {
			$content_type = 'multipart/form-data; boundary=' . $this->mime_builder->boundary();
			$request_info .= $content_type . $content;
		}

		$secret_key = base64_decode( $this->credentials->secret() );
		$hash       = hash_hmac( 'sha256', $request_info, $secret_key, true );
		$signature  = base64_encode( $hash );

		return 'Authorization: HHMAC; key=' . $this->credentials->key() . '; signature=' . $signature . '; date=' . $current_date;
	}

}

class Request_Exception extends \Exception {}
