<?php
/**
 * Copyright 2004-2014 Facebook. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package WebDriver
 *
 * @author Justin Bishop <jubishop@gmail.com>
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 * @author Fabrizio Branca <mail@fabrizio-branca.de>
 */

namespace WebDriver\Service;

use Exception;
use WebDriver\Exception as WebDriverException;

/**
 * WebDriver\Service\CurlService class
 *
 * @package WebDriver
 */
class CurlService implements CurlServiceInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute($requestMethod, $url, $parameters = null, $extraOptions = array())
    {
        $curl = $this->createCurl($requestMethod, $url, $parameters, $extraOptions);
        $result = $this->processCurl($curl);

        $curlSuccess = false;
        $limit = 10;
        $i = 0;

        while(!$curlSuccess && $i < $limit) {
            try {
                $this->handleResponse($curl, $requestMethod, $url, $parameters);
                $curlSuccess = true;
                curl_close($curl);
            } catch (WebDriverException $e) {
                if (curl_errno($curl) != CURLE_COULDNT_CONNECT) {
                    curl_close($curl);
                    throw $e;
                }
            }

            $i++;
        }


        return $result;
    }


    /**
     * Create curl resource.
     *
     * @param string $requestMethod HTTP request method, e.g., 'GET', 'POST', or 'DELETE'
     * @param string $url           Request URL
     * @param array  $parameters    If an array(), they will be posted as JSON parameters
     *                              If a number or string, "/$params" is appended to url
     * @param array  $extraOptions  key=>value pairs of curl options to pass to curl_setopt()
     *
     * @return resource
     */
    protected function createCurl($requestMethod, $url, $parameters = null, $extraOptions = array())
    {
        $customHeaders = array(
            'Content-Type: application/json;charset=UTF-8',
            'Accept: application/json;charset=UTF-8',
        );

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, ini_get('default_socket_timeout'));

        switch ($requestMethod) {
            case 'GET':
                break;

            case 'POST':
                if ($parameters && is_array($parameters)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($parameters));
                } else {
                    $customHeaders[] = 'Content-Length: 0';
                }

                // Suppress "Expect: 100-continue" header automatically added by cURL that
                // causes a 1 second delay if the remote server does not support Expect.
                $customHeaders[] = 'Expect:';

                curl_setopt($curl, CURLOPT_POST, true);
                break;

            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;

            case 'PUT':
                if ($parameters && is_array($parameters)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($parameters));
                } else {
                    $customHeaders[] = 'Content-Length: 0';
                }

                // Suppress "Expect: 100-continue" header automatically added by cURL that
                // causes a 1 second delay if the remote server does not support Expect.
                $customHeaders[] = 'Expect:';

                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
        }

        foreach ($extraOptions as $option => $value) {
            curl_setopt($curl, $option, $value);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $customHeaders);

        return $curl;
    }

    /**
     * Handle a response from a curl resource after execute has been made.
     *
     * @param resource $curl          The curl resource to retrieve the response information from.
     * @param string   $requestMethod HTTP request method, e.g., 'GET', 'POST', or 'DELETE'
     * @param string   $url           Request URL
     * @param array    $parameters    If an array(), they will be posted as JSON parameters
     *                                If a number or string, "/$params" is appended to url
     * @throws Exception If there was an error.
     */
    protected function handleResponse($curl, $requestMethod, $url, $parameters)
    {
        if (CURLE_GOT_NOTHING !== curl_errno($curl) && $error = curl_error($curl)) {
            $message = sprintf(
                'Curl error thrown for http %s to %s%s',
                $requestMethod,
                $url,
                $parameters && is_array($parameters)
                    ? ' with params: ' . json_encode($parameters) : ''
            );

            throw WebDriverException::factory(WebDriverException::CURL_EXEC, $message . "\n\n" . $error);
        }
    }

    /**
     * Returns tuple of result when the provided curl resource is executed. The key is the raw result while the value
     * contains the info returned from `curl_getinfo`.
     *
     * @param resource $curl
     * @return array
     */
    protected function processCurl($curl)
    {
        $rawResult = trim(curl_exec($curl));
        $info = curl_getinfo($curl);

        return [$rawResult, $info];
    }
}
