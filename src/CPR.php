<?php

namespace LasseRafn;

class CPR
{
    const ENDPOINT_DEMO = 'direkte-demo.cpr.dk';
    const ENDPOINT_LIVE = 'direkte.cpr.dk';

    /**
     * Error numbers and descriptions
     *
     * Errors from 12 to 15 and 18 to 98 are listed as reserved at the service documentation
     */
    const ERROR_CODES = [
        '0'  => 'No errors.',
        '1'  => 'User ID or password are incorrect.',
        '2'  => 'Password expired (new password required).',
        '3'  => 'Password format error.',
        '4'  => 'No access to CPR (CTSERVICE is temporarily closed).',
        '5'  => 'PNR not found in CPR.',
        '6'  => 'Unknown customer.',
        '7'  => 'Timeout (new login required).',
        '8'  => 'DEAD-LOCK while retrieving data from the CPR system.',
        '9'  => 'Serious problem. There is no connection between the client and the CPR system. Contact CSC Service Center on +45 36 14 61 92.',
        '10' => 'Subscription indicator (ABON_TYPE) unknown.',
        '11' => 'Output format (DATA_TYPE) unknown.',
        '16' => 'No access for your IP address.',
        '17' => 'PNR is not specified.',
        '99' => 'User ID has not access to the transaction.'
    ];

    private $transCode;
    private $kundeNr;
    private $username;
    private $password;
    private $socket;
    private $authToken;
    private $demo = false;

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
    public function __construct($transCode = '', $customerNumber = '', $username = '', $password = '')
    {
        $this->transCode = $transCode;
        $this->kundeNr = substr($customerNumber, 0, 4);
        $this->username = str_pad($username, 8);
        $this->password = str_pad($password, 8);
    }

    public function findByCpr($cpr)
    {
        return $this->getResponseData($this->doLookup((string) $cpr));
    }

    private function prepare()
    {
        $context = stream_context_create();
        $this->socket = $this->get_socket($context);

        if (!$this->socket) {
            throw new \Exception('No socket.');
        }

        if (!$this->login()) {
            throw new \Exception('Error when logging in. Check credentials and try again.');
        }

        $this->socket = $this->get_socket($context);
    }

    /**
     * Create socket from given context and configure it.
     *
     * @param $context resource context used to open connection to CPR.
     *
     * @return resource Returns a pointer to a socket file descriptor that can be written to.
     */
    private function &get_socket(&$context)
    {
        $this->socket = stream_socket_client($this->getEndpoint(), $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        stream_set_blocking($this->socket, 1);

        if (!$this->socket) {
            throw new \Exception("SOCKET ERROR: $errstr", $errno);
        }

        return $this->socket;
    }

    private function getResponseData($response)
    {
        if ($response === null || $response === '') {
            throw new \Exception('No response.');
        }

        /* one or more data records are present, so we need to determine which based on
           record numbers. Can also be hardcoded based on which records you have agreed
           to receive from CPR system */
        $records = $this->getAvailableRecords($response);

        if (array_key_exists('003', $records)) {
            $REC_KONTAKT_START = $records['003'];
            $this->printFullContactAddress($response, $REC_KONTAKT_START);
        }

        if (array_key_exists('050', $records)) {
            $this->printCreditWarning(substr($response, $records['050'], 43));
        } else {
            $START_CURR_DATA = $records['001'];

            return new PersonResponse($START_CURR_DATA, $response);
        }
    }

    /**
     * Login to CPR Direkte to obtain authentication token for data request.
     *
     * @return bool Returns true if token retrieved, false on failure.
     */
    private function login()
    {
        fwrite($this->socket, str_pad($this->transCode.','.$this->kundeNr.'90'.$this->username.$this->password, 35));

        $response = fread($this->socket, 24);

        $requestError = (int) substr($response, 22, 2); // get error code
        if ($requestError !== 0) {
            throw new \Exception("Login error: $requestError");
        }

        $this->authToken = substr($response, 6, 8);

        return true;
    }

    /**
     * Lookup person data using CPR number.
     *
     * @param string $cpr CPR number to lookup
     *
     * @return string String containing person data on success, or NULL on error.
     */
    private function doLookup($cpr)
    {
        $this->prepare();

        $requestData = str_pad($this->transCode.','.$this->kundeNr.'06'.$this->authToken.$this->username.'00'.$cpr, 39);

        fwrite($this->socket, $requestData);

        $response = '';

        while (!feof($this->socket)) {
            $response .= fread($this->socket, 4096);
        }

        fclose($this->socket);

        $this->parseErrorCode($response);

        return $response;
    }

    /**
     * Parses an error code and, if failure code present, prints error text from
     * CPR Direkte.
     *
     * @param $response string The response to our previous request from CPR Direkte.
     *
     * @throws \Exception
     *
     * @return int Returns the error code if none (0)
     */
    private function parseErrorCode($response)
    {
        $code = (int) substr($response, 22, 2); // get error code from response

        if ($code !== 0) {
            $errorText = substr($response, $this->START_REC_LEN, strlen($response) - $this->START_REC_LEN);

            throw new \Exception($errorText !== '' ? $errorText : (static::ERROR_CODES[$code] ?? ''), $code);
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
     *               record begins.
     */
    private function getAvailableRecords($response)
    {
        $records = [];
        $start = $this->START_REC_LEN;

        /* if we find the start of a record, save it's starting position in the response string
           as a key in associative array so we can parse it later */
        while ($start < strlen($response)) {
            $recordType = substr($response, $start, 3);

            if ($recordType === '000') { // START record

                $records['000'] = $start; // mandatory in response
                $start += 35; // end of record
            } elseif ($recordType === '001') { // CURRENT_DATA record

                $records['001'] = $start; // mandatory in response
                $start += 469; // end of record
            } elseif ($recordType === '002') { // FOREIGN_ADDRESS record

                $records['002'] = $start; // NOTE: length is either 195/199 depending on record type 'A' or 'B'
                $start += 195; // end of record
            } elseif ($recordType === '003') { // KONTAKT_ADDRESS record

                $records['003'] = $start;
                $start += 195; // end of record
            } elseif ($recordType === '004') { // MARRITAL_STATUS record

                $records['004'] = $start;
                $start += 26; // end of record
            } elseif ($recordType === '005') { // GUARDIAN record

                $records['005'] = $start;
                $start += 217; // end of record
            } elseif ($recordType === '011') { // CUSTOMERNUM_REF record

                $records['011'] = $start;
                $start += 88; // end of record
            } elseif ($recordType === '050') { // CREDIT_WARNING record
                // NB: Credit warning data (050 record) is first available in production from 1/1/2017

                $records['050'] = $start;
                $start += 29; // end of record
            } elseif ($recordType === '999') { // END record

                $records['999'] = $start; // mandatory in response
                $start += 21; // end of record
            } else {
                $start += strlen($recordType); // so we don't loop infinitely
            }
        }

        return $records;
    }

    /**
     * Return full address as given in the contact record.
     *
     * @param $response    string The response to pretty print contact address from.
     * @param $recordStart integer The start of the contact record.
     */
    private function printFullContactAddress($response, $recordStart)
    {
        $FIELD_LENGTH = 34;

        // start position and length from PRIV specification
        // due to php 0-indexing in strings, subtract 1 from 'Pos.' as shown in record specification
        $contactFields = [
            // KONTAKTADR1 field in position 14, length 34
            trim(substr($response, $recordStart + 27, $FIELD_LENGTH)),
            // KONTAKTADR2 field in position 48, length 34
            trim(substr($response, $recordStart + 61, $FIELD_LENGTH)),
            // KONTAKTADR3 field in position 82, length 34
            trim(substr($response, $recordStart + 95, $FIELD_LENGTH)),
            // KONTAKTADR4 field in position 116, length 34
            trim(substr($response, $recordStart + 129, $FIELD_LENGTH)),
            // KONTAKTADR5 field in position 150, length 34
            trim(substr($response, $recordStart + 163, $FIELD_LENGTH)),
        ];

        // loop over available fields in contact record and print contents if not empty
        foreach ($contactFields as $field) {
            if (!$field === '') {
                echo $field, PHP_EOL;
            }
        }
    }

    /**
     * Pretty print a credit warning record.
     * NB: Credit warning data (050 record) is first available in production from 1/1/2017.
     *
     * @param $creditRecord string Subset of CPR Direkte response containing only credit warning record.
     */
    private function printCreditWarning($creditRecord)
    {
        $startDate = date_create_from_format('YmdHi', substr($creditRecord, 31, 12));

        // person can potentially have credit warning in the future
        if ($startDate > new DateTime()) {
            //echo "Person will have credit warning in future. In effect from: " . $startDate->format( 'd. M Y' ), PHP_EOL;
        } else {
            //echo "Person has credit warning record. In effect from: " . $startDate->format( 'd. M Y' ), PHP_EOL;
        }

        //echo "Warning type: ", substr( $creditRecord, 27, 4 ), PHP_EOL; // always 0005 -
        //echo "Start date: ", $startDate->format( 'd. M Y' ), PHP_EOL;
    }

    private function getEndpoint()
    {
        if ($this->isDemo()) {
            return sprintf('tls://%s:5000', static::ENDPOINT_DEMO);
        }

        return sprintf('tls://%s:5000', static::ENDPOINT_LIVE);
    }

    public function isDemo()
    {
        return $this->demo;
    }

    public function setDemo()
    {
        $this->demo = true;

        return $this;
    }

    public function setLive()
    {
        $this->demo = false;

        return $this;
    }
}
