<?php

class FyndiqAPI
{

    const API_URL = 'https://api.fyndiq.com/v2/';

    public static $error_messages;

    /**
     * Handling the API Call to Fyndiq using path
     *
     * @param String $userAgent
     * @param String $username
     * @param String $token
     * @param String $method
     * @param String $path
     * @param stdClass|array $data
     * @return array
     */
    public static function call($userAgent, $username, $token, $method, $path, $data)
    {
        return self::callURL($userAgent, $username, $token, $method, self::getURL($path), $data);
    }

    /**
     * Handling the API Call to Fyndiq using full URL
     *
     * @param String $userAgent
     * @param String $username
     * @param String $token
     * @param String $method
     * @param String $url
     * @param stdClass|array $data
     * @return array
     */
    public static function callURL($userAgent, $username, $token, $method, $url, $data)
    {
        self::$error_messages = array();

        $requestBody = json_encode($data);

        $curlOpts = array(
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array('Content-type: application/json'),
            CURLOPT_POSTFIELDS => $requestBody,

            CURLOPT_VERBOSE => true,
            CURLOPT_HEADER => true,

            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $username . ':' . $token,

            #CURLOPT_SSLVERSION => 3,
            #CURLOPT_SSL_VERIFYPEER => true,
            #CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_RETURNTRANSFER => 1,
        );

        # make the call
        $ch = curl_init();
        curl_setopt_array($ch, $curlOpts);
        $response['data'] = curl_exec($ch);

        # extract different parts of the response
        $response['http_status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response['header_size'] = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response['header'] = substr($response['data'], 0, $response['header_size']);
        $response['body'] = substr($response['data'], $response['header_size']);

        if (!function_exists('http_parse_headers')) {
            $headers = self::http_parse_headers($response['header']);
        } else {
            $headers = http_parse_headers($response['header']);
        }

        curl_close($ch);

        if (isset($headers['Content-Type'])) {
            # try to json decode response data
            if ($headers['Content-Type'] == 'application/pdf') {
                $result = $response['body'];
            } else {
                $result = json_decode($response['body']);
            }
        } else {
            $result = json_decode($response['body']);
        }

        return array(
            'status' => $response['http_status'],
            'header' => $headers,
            'data' => $result
        );
    }

    /**
     * Return the API Request URL
     *
     * @param $path
     * @return string
     */
    private static function getURL($path)
    {
        $baseURL = isset($_SERVER['FYNDIQ_API_URL']) ? $_SERVER['FYNDIQ_API_URL'] : self::API_URL;
        return $baseURL . $path;
    }

    /**
     * Parsing the http header to handle it easier.
     *
     * @param $rawHeaders
     * @return array
     */
    private static function http_parse_headers($rawHeaders)
    {
        $headers = array();
        $key = ''; // [+]

        foreach (explode("\n", $rawHeaders) as $i => $h) {
            $h = explode(':', $h, 2);

            if (isset($h[1])) {
                if (!isset($headers[$h[0]])) {
                    $headers[$h[0]] = trim($h[1]);
                } elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
                } else {
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
                }

                $key = $h[0];
            } else {
                if (substr($h[0], 0, 1) == "\t") {
                    $headers[$key] .= "\r\n\t" . trim($h[0]);
                } elseif (!$key) {
                    $headers[0] = trim($h[0]);
                }
                trim($h[0]);
            }
        }

        return $headers;
    }
}
