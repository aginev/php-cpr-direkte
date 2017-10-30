<?php

namespace LasseRafn;

class CPR
{
	const ENDPOINT_DEMO = 'direkte-demo.cpr.dk';
	const ENDPOINT_LIVE = 'direkte-demo.cpr.dk';


	private $transCode;
	private $kundeNr;
	private $username;
	private $password;
	private $cprNr;

	private $pnrMode = false;
	private $authToken;
	private $demo    = false;

	private $START_REC_LEN = 28; // start of DATA section of response


	/**
	 * customerNumber must be exactly 4 numbers
	 * username must be exactly 8 characters, will pad if not.
	 * password must be exactly 8 characters, will pad if not.
	 *
	 * @param string $transCode
	 * @param string $customerNumber
	 * @param string $username
	 * @param string $password
	 */
	public function __construct( $transCode = '', $customerNumber = '', $username = '', $password = '' ) {


		$this->transCode = $transCode;
		$this->kundeNr   = substr( $customerNumber, 0, 4 );
		$this->username  = str_pad( $username, 8 );
		$this->password  = str_pad( $password, 8 );


		$context = stream_context_create();
		$fp      = $this->get_socket( $context );

		if ( ! $fp ) {
			// unable to get socket for reading/writing - abort program
			return EXIT_ERROR;
		}

		$isLoggedIn = $this->login( $fp );
		if ( $isLoggedIn === false ) {
			echo "Error when logging in. Check credentials and try again.", PHP_EOL;

			return EXIT_ERROR;
		}
	}

	public function findByCpr( $cpr ) {
		$response = $this->doLookup( $fp, $argv[5] );
	}

	/**
	 * Lookup person data using CPR number.
	 *
	 * @param string $name      Either the CPR number to lookup, or person's name
	 * @param null   $birthdate Person's birthdate in DDMMYYYY format
	 * @param null   $sex       Person's sex - either K or M
	 *
	 * @return string String containing person data on success, or NULL on error.
	 */
	public function searchByPerson( $name = '', $birthdate = null, $sex = null ) {

		$response = $this->doLookup( $fp, $name, $birthdate, $sex );
	}


	/**
	 * Create socket from given context and configure it.
	 *
	 * @param $context resource context used to open connection to CPR.
	 *
	 * @return resource Returns a pointer to a socket file descriptor that can be written to.
	 */
	private function &get_socket( &$context ) {
		$fp = stream_socket_client( "tls://" . static::ENDPOINT_DEMO . ":5000", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context );
		stream_set_blocking( $fp, 1 );

		if ( ! $fp ) {
			echo "$errstr ($errno)", PHP_EOL;
		}

		return $fp;
	}

	/**
	 * Login to CPR Direkte to obtain authentication token for data request
	 *
	 * @param $fp resource File pointer to read from and write to.
	 *
	 * @return bool Returns true if token retrieved, false on failure.
	 */
	private function login( &$fp ) {
		echo "Sending logon request:", PHP_EOL;

		// LOGONINDIVID record must be 35 characters in length
		fwrite( $fp, str_pad( $this->transCode . "," . $this->kundeNr . "90" . $this->username . $this->password, 35 ) );
		echo "Reading response:", PHP_EOL;

		// read at most 24 bytes - the length of the SVARINDIVID record
		$response = fread( $fp, 24 );

		echo $response, PHP_EOL;

		$requestError = intval( substr( $response, 22, 2 ) ); // get error code
		if ( $requestError !== 0 ) {
			echo "Error code: $requestError", PHP_EOL;

			return false;
		}

		// parse out token used to authenticate lookup request
		$this->authToken = substr( $response, 6, 8 );
		echo "Received token: " . $this->authToken, PHP_EOL;

		return true;
	}

	/**
	 * Lookup person data using CPR number.
	 *
	 * @param      $fp          resource File pointer to read from and write to.
	 * @param      $cprNrOrName string Either the CPR number to lookup, or person's name
	 * @param null $birthdate   string Person's birthdate in DDMMYYYY format
	 * @param null $sex         string Person's sex - either K or M
	 *
	 * @return string String containing person data on success, or NULL on error.
	 */
	private function doLookup( &$fp, $cprNrOrName, $birthdate = null, $sex = null ) {
		$sPaddedRequest = null;
		if ( $this->pnrMode === true ) {
			// build lookup request string - different if searching using CPR number as criteria
			$sRequest       = $this->kundeNr . "06" . $this->authToken . $this->username . "00PNR=" . $cprNrOrName;
			$sPaddedRequest = str_pad( $sRequest, 204 ); // pad the request for 204 bytes
		} else {
			// build lookup request string - different if searching using name, address, and sex as criteria
			$sRequest       = $this->kundeNr . "06" . $this->authToken . $this->username;
			$sPaddedRequest = str_pad( $sRequest . str_pad( "", 17 )
			                           . $sex . str_pad( $cprNrOrName, 66 )
			                           . $birthdate, 204 ); // pad the request for 204 bytes
		}
		echo "Sending data request:", PHP_EOL, $sPaddedRequest, PHP_EOL;

		fwrite( $fp, $sPaddedRequest );

		echo "Reading response:", PHP_EOL;
		// read response from CPR until end of stream
		$response = "";
		while ( ! feof( $fp ) ) {
			$response .= fread( $fp, 4096 );
		}
		fclose( $fp );

		echo $response, PHP_EOL;

		$requestError = $this->parseErrorCode( $response );
		if ( $requestError !== 0 ) {
			return null;
		}

		return $response;
	}

	/**
	 * Parses an error code and, if failure code present, prints error text from
	 * CPR Direkte.
	 *
	 * @param $response string The response to our previous request from CPR Direkte.
	 *
	 * @return int Returns the error code received in response.
	 */
	function parseErrorCode( $response ) {
		$code = intval( substr( $response, 22, 2 ) ); // get error code from response
		echo "Error number: $code", PHP_EOL;

		if ( $code != 0 ) {
			$errorText = substr( $response, $this->START_REC_LEN, strlen( $response ) - $this->START_REC_LEN );
			echo "Error: $errorText", PHP_EOL;
		}

		return $code;
	}

	/**
	 * Method for getting the records sent from CPR system. Note that the records available
	 * to you are determined when you are setup as a customer with CPR.
	 *
	 * @param $response string The response to parse records from.
	 *
	 * @return array Returns a Map of records found, as well as the index in the response where the
	 * record begins.
	 */
	function getAvailableRecords( $response ) {
		$records = [];
		$start   = $this->START_REC_LEN;

		/* if we find the start of a record, save it's starting position in the response string
		   as a key in associative array so we can parse it later */
		while ( $start < strlen( $response ) ) {
			$recordType = substr( $response, $start, 3 );

			if ( $recordType === "000" ) { // START record

				$records["000"] = $start; // mandatory in response
				$start          += 48; // end of record
			} else if ( $recordType === "001" ) { // CURRENT_DATA record

				$records["001"] = $start; // mandatory in response
				$start          += 404; // end of record
			} else if ( $recordType === "002" ) { // FOREIGN_ADDRESS record

				$records["002"] = $start;
				$start          += 209; // end of record
			} else if ( $recordType === "003" ) { // KONTAKT_ADDRESS record

				$records["003"] = $start;
				$start          += 209; // end of record
			} else if ( $recordType === "004" ) { // MARITAL_STATUS record

				$records["004"] = $start;
				$start          += 40; // end of record
			} else if ( $recordType === "005" ) { // GUARDIAN record

				$records["005"] = $start;
				$start          += 243; // end of record
			} else if ( $recordType === "011" ) { // CUSTOMERNUM_REF record

				$records["011"] = $start;
				$start          += 102; // end of record
			} else if ( $recordType === "012" ) { // SUBSCRIPTION record

				$records["012"] = $start;
				$start          += 37; // end of record
			} else if ( $recordType === "013" ) { // CPRNR_INFO record

				$records["013"] = $start;
				$start          += 56; // end of record
			} else if ( $recordType === "050" ) { // CREDIT_WARNING record
				// NB: Credit warning data (050 record) is first available in production from 1/1/2017

				$records["050"] = $start;
				$start          += 43; // end of record
			} else if ( $recordType === "999" ) { // END record

				$records["999"] = $start; // mandatory in response
				$start          += 34; // end of record
			} else {
				echo "Unknown record code: $recordType", PHP_EOL;

				$start += strlen( $recordType ); // so we don't loop infinitely
			}
		}

		// display list of records found in response
		echo "Found records in response:", PHP_EOL;
		foreach ( $records as $key => $value ) {
			echo "- $key", PHP_EOL;
		}

		return $records;
	}

	/**
	 * Return full address as given in the contact record.
	 *
	 * @param $response    string The response to pretty print contact address from.
	 * @param $recordStart integer The start of the contact record.
	 */
	function printFullContactAddress( $response, $recordStart ) {
		$FIELD_LENGTH = 34;

		// start position and length from PRIV specification
		// due to php 0-indexing in strings, subtract 1 from 'Pos.' as shown in record specification
		$contactFields = [
			// KONTAKTADR1 field in position 14, length 34
			trim( substr( $response, $recordStart + 27, $FIELD_LENGTH ) ),
			// KONTAKTADR2 field in position 48, length 34
			trim( substr( $response, $recordStart + 61, $FIELD_LENGTH ) ),
			// KONTAKTADR3 field in position 82, length 34
			trim( substr( $response, $recordStart + 95, $FIELD_LENGTH ) ),
			// KONTAKTADR4 field in position 116, length 34
			trim( substr( $response, $recordStart + 129, $FIELD_LENGTH ) ),
			// KONTAKTADR5 field in position 150, length 34
			trim( substr( $response, $recordStart + 163, $FIELD_LENGTH ) )
		];

		// loop over available fields in contact record and print contents if not empty
		foreach ( $contactFields as $field ) {
			if ( ! $field === '' ) {
				echo $field, PHP_EOL;
			}
		}
	}

	/**
	 * Pretty print a credit warning record.
	 * NB: Credit warning data (050 record) is first available in production from 1/1/2017
	 *
	 * @param $creditRecord string Subset of CPR Direkte response containing only credit warning record.
	 */
	function printCreditWarning( $creditRecord ) {
		$startDate = date_create_from_format( 'YmdHi', substr( $creditRecord, 31, 12 ) );

		// person can potentially have credit warning in the future
		if ( $startDate > new DateTime() ) {
			echo "Person will have credit warning in future. In effect from: " . $startDate->format( 'd. M Y' ), PHP_EOL;
		} else {
			echo "Person has credit warning record. In effect from: " . $startDate->format( 'd. M Y' ), PHP_EOL;
		}

		echo "Warning type: ", substr( $creditRecord, 27, 4 ), PHP_EOL; // always 0005 -
		echo "Start date: ", $startDate->format( 'd. M Y' ), PHP_EOL;
	}

	private function getEndpoint() {
		if ( $this->isDemo() ) {
			return static::ENDPOINT_DEMO;
		}

		return static::ENDPOINT_LIVE;
	}

	public function isDemo() {
		return $this->demo;
	}

	public function setDemo() {
		$this->demo = true;

		return $this;
	}

	public function setLive() {
		$this->demo = false;

		return $this;
	}
}