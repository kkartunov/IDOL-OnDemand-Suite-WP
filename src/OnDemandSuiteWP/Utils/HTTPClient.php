<?php
/*
 * This file is part of the HP IDOL OnDemand Suite for WP.
 *
 * (c) 2014 Kiril Kartunov
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace OnDemandSuiteWP\Utils;


/**
 * Represents abstract class wrapping the build in WP HTTP API
 * in easy to use bundle (the OOP way) capable of accessing the HP IDOL OnDemand API.
 * This class provides methods to work with the IDOL API under WP environment
 * and is designed to be easily used by any plugins/themes needing access to the API.
 */
abstract class HTTPClient
{
    /**
     * @var string The IDOL API key to use.
     */
    protected $APIkey;

    const API_URL = 'https://api.idolondemand.com/';

    /**
     * Constructor.
     *
     * @param string|null $APIkey The IDOL OnDemand API key to use in requests
     */
    public function __construct($APIkey = '')
    {
        $this -> APIkey = $APIkey;
    }

    /**
     * Builds and returns "valid" API URL.
     * Appends the current API key to the query if such was set but only when
     * none was given in the function call.
     *
     * @param array|null $args The parameters to build the URL
     *
     * @return string The complete URL qualified to make API request
     */
    final public function makeURL($args = array())
    {
        $params = array_merge(array(
            'platform' => 1,
            'sync' => true,
            'ident' => '',
            'version' => 'v1',
            'qparams' => array()
        ), $args);

        $query = http_build_query($params['qparams']);
        if( $query ){
            $query = '?' . $query;
            if( !array_key_exists('apikey', $params['qparams']) && $this -> APIkey )
                $query .= '&apikey=' . $this -> APIkey;
        }else if($this -> APIkey){
            $query = '?apikey=' . $this -> APIkey;
        }

        return self::API_URL . $params['platform'] . '/api/' . ($params['sync']? 'sync/' : 'async/') . $params['ident'].'/' . $params['version'] . $query;
    }

    /**
     * Helper usable to output JSON responses.
     *
     * @param string|int $status_code HTTP response status code
     * @param mixed $results The data to be outputed by the responce
     * @param string $errTxt Custom error message
     */
    final public function JSONrsp($status_code, $results=NULL, $errTxt=NULL){
        $response=array();
        switch($status_code){
            case 200:
                $response = $results;
            break;
            case 401:
                header("HTTP/1.1 401 Unauthorized");
                $response["errorTxt"] = 'The request requires user authentication';
            break;
            case 406:
                header("HTTP/1.1 406 Not Acceptable");
                $response["errorTxt"] = 'Required parameres are missing';
            break;
            case 409:
                header("HTTP/1.1 409 Conflict");
                $response["errorTxt"] = $errTxt;
            break;
            case 500:
                header("HTTP/1.1 500 Internal Server Error");
                $response["errorTxt"] = $errTxt;
                $response["details"] = $results;
            break;
        }
        header("Content-Type: application/json");
        echo json_encode($response);
        exit;
    }

    /**
     * Helper wrapping the HTTP response in easy to use object.
     *
     * @params array|WP_Error $rsp The response to wrap
     *
     * @return stdClass The wrapped response
     */
    private function wrapRsp($rsp)
    {
        $wrapped = new \stdClass();
        if( is_wp_error($rsp) ){
            // HTTP Error
            $wrapped -> isError = true;
            $wrapped -> errorMsg = $rsp -> get_error_message();
            $wrapped -> error = $rsp -> get_error_data();
        }else if($rsp['response']['code'] < 200 || $rsp['response']['code'] >= 300){
            // IDOL API Error
            $wrapped -> isError = true;
            $wrapped -> error = json_decode($rsp['body'], true);
            $wrapped -> response = $rsp['response'];
            $wrapped -> headers = $rsp['headers'];
            if( isset($wrapped -> error['reason']) )
                $wrapped -> errorMsg = $wrapped -> error['reason'];
            else if( isset($wrapped -> error['message']) )
                $wrapped -> errorMsg = $wrapped -> error['message'];
            else
                $wrapped -> errorMsg = $wrapped -> error['response']['message'];
        }else{
            // OK.
            $wrapped -> isError = false;
            $wrapped -> headers = $rsp['headers'];
            $wrapped -> body = json_decode($rsp['body'], true);
            $wrapped -> raw_body = $rsp['body'];
        }

        return $wrapped;
    }

    /**
     * Makes a GET request.
     *
     * @params array $args The URL build parameters
     * @params array $get_args|null The arguments passed to the WP's wp_remote_get function
     *
     * @return stdClass The response wrapped or WP_Error object on failure
     */
    final public function IDOLget($args, $get_args = NULL)
    {
        return $this -> wrapRsp( wp_remote_get($this -> makeURL($args), $get_args) );
    }

    /**
     * Makes a POST request.
     *
     * @params array $args The URL build parameters
     * @params array $post_args|null The arguments passed to the WP's wp_remote_post function
     *
     * @return stdClass The response wrapped or WP_Error object on failure
     */
    final public function IDOLpost($args, $post_args = NULL)
    {
        return $this -> wrapRsp( wp_remote_post($this -> makeURL($args), $post_args) );
    }

    /**
     * Retrieves the status of previously submited asynchronous IDOL OnDemand request.
     *
     * @params string $jobID UUID of the async job
     * @params string|int The IDOL platform to use
     * @params array $get_args|null The arguments passed to the WP's wp_remote_get function
     *
     * @return stdClass The response wrapped or WP_Error object on failure
     */
    final public function IDOLjob($jobID, $platform = '1', $get_args = NULL)
    {
        return $this -> wrapRsp(
            wp_remote_get(
                self::API_URL . $platform . '/job/status/' . $jobID . '?apikey=' . $this -> APIkey,
                $get_args
            )
        );
    }
}
