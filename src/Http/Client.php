<?php
/**
 * This file is part of Paytrail.
 *
 * (c) 2013 Nord Software
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Paytrail\Http;

use GuzzleHttp\Psr7\Request;
use Paytrail\Common\Object;
use Paytrail\Exception\PaymentFailed;
use Paytrail\Exception\ApiVersionNotSupported;
use Paytrail\Object\Payment;

/**
 * Class Client.
 *
 * @package Paytrail\Http
 */
class Client extends Object
{

    /**
     * The Paytrail API endpoint.
     *
     * @var string API_ENDPOINT
     */
    const API_ENDPOINT = 'https://payment.paytrail.com';

    /**
     * The Paytrail API version.
     *
     * @var int $apiVersion
     */
    protected $apiVersion = 1;

    /**
     * Test mode on/off.
     *
     * @var bool $testMode
     */
    protected $testMode = false;

    /**
     * The Paytrail API key.
     *
     * @var string $_apiKey
     */
    private $_apiKey = '13466';

    /**
     * The Paytrail API secret.
     *
     * @var string $_apiSecret
     */
    private $_apiSecret = '6pKF4jkv97zmqBJ3ZL8gUw5DfT2NMQ';

    /**
     * @var \GuzzleHttp\Client
     */
    private $_client;

    /**
     * @var array
     */
    static $supportedApiVersions = array(1);

    /**
     * Connect client.
     *
     * @param string $url The URL to connect to, defaults to API_ENDPOINT.
     */
    public function connect($url = self::API_ENDPOINT)
    {
        $this->_client = new \GuzzleHttp\Client(array('base_uri' => $url));
    }

    /**
     * Processes the payment.
     *
     * @param Payment $payment The payment object.
     *
     * @return \Paytrail\Http\Result
     *
     * @throws \Paytrail\Exception\PaymentFailed
     */
    public function processPayment(Payment $payment)
    {
        $response = $this->postRequest('/api-payment/create', $payment->toJson());

        if ( ! in_array('application/json', $response->getHeader('Content-Type'))) {
            throw new PaymentFailed('Server returned a non-JSON result.');
        }

        $body = json_decode((string)$response->getBody());

        if ($response->getStatusCode() !== 201) {
            throw new PaymentFailed(
                sprintf('Paytrail request failed: %s (%d)', $body->errorMessage, $body->errorCode)
            );
        }

        $result = new Result;
        $result->configure(
            array(
                'orderNumber' => $body->orderNumber,
                'token'       => $body->token,
                'url'         => $body->url,
            )
        );

        return $result;
    }

    /**
     * Validates the given checksum against the order.
     *
     * @param string $checksum    Checksum to validate.
     * @param string $orderNumber The order number.
     * @param int    $timestamp   The timestamp of the order.
     * @param string $paid        Payment paid.
     * @param int    $method      The method.
     *
     * @return bool
     */
    public function validateChecksum($checksum, $orderNumber, $timestamp, $paid, $method)
    {
        return $checksum === $this->calculateChecksum($orderNumber, $timestamp, $paid, $method);
    }

    /**
     * Calculates the checksum.
     *
     * @param string $orderNumber The order number.
     * @param int    $timestamp   The timestamp of the order.
     * @param string $paid        Payment paid.
     * @param int    $method      The method.
     *
     * @return string
     */
    protected function calculateChecksum($orderNumber, $timestamp, $paid, $method)
    {
        return strtoupper(md5("{$orderNumber}|{$timestamp}|{$paid}|{$method}|{$this->_apiSecret}"));
    }

    /**
     * Runs a POST request.
     *
     * @param string      $uri     The URI to post to.
     * @param string|null $body    The body to post.
     * @param array       $options The options.
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function postRequest($uri, $body = null, $options = array())
    {
        if ($this->testMode) {
            $options['debug'] = true;
        }

        $headers = array(
            'Content-Type'               => 'application/json',
            'Accept'                     => 'application/json',
            'X-Verkkomaksut-Api-Version' => $this->apiVersion,
        );

        $options = array_merge(
            array(
                'auth'            => array($this->_apiKey, $this->_apiSecret),
                'timeout'         => 30,
                'connect_timeout' => 10,
            ),
            $options
        );

        $request = new Request('POST', $uri, $headers, $body);

        return $this->_client->send($request, $options);
    }

    /**
     * Set the Paytrail API key.
     *
     * @param string $apiKey The API key.
     */
    public function setApiKey($apiKey)
    {
        $this->_apiKey = $apiKey;
    }

    /**
     * Set the Paytrail API secret.
     *
     * @param string $apiSecret The API secret.
     */
    public function setApiSecret($apiSecret)
    {
        $this->_apiSecret = $apiSecret;
    }

    /**
     * Get the client.
     *
     * @return \Guzzle\Http\Client
     */
    public function getClient()
    {
        return $this->_client;
    }

    /**
     * Set the Paytrail API version.
     *
     * @param int $apiVersion The API version.
     *
     * @throws \Paytrail\Exception\ApiVersionNotSupported
     */
    public function setApiVersion($apiVersion)
    {
        if ( ! in_array($apiVersion, self::$supportedApiVersions)) {
            throw new ApiVersionNotSupported(sprintf('API version %d is not supported.', $apiVersion));
        }
        $this->apiVersion = $apiVersion;
    }
}
