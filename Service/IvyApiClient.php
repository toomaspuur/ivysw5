<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use IvyPaymentPlugin\Exception\IvyApiException;
use Monolog\Logger;

class IvyApiClient
{
    /**
     * @var Logger
     */
    private $apiLogger;

    /**
     * @var mixed|string
     */
    private $ivyServiceUrl;

    /**
     * @var mixed
     */
    private $ivyApiKey;

    /**
     * @param array $config
     * @param Logger $apiLogger
     */
    public function __construct(array $config, Logger $apiLogger)
    {
        $this->apiLogger = $apiLogger;
        $isSandboxActive = (int)$config["isSandboxActive"] === 1;
        if ($isSandboxActive) {
            $this->ivyServiceUrl = isset($config["IvyApiUrlSandbox"]) ? $config["IvyApiUrlSandbox"] : IvyPaymentHelper::TEST_URL;
            $this->ivyApiKey = $config["SandboxIvyApiKey"];
        } else {
            $this->ivyServiceUrl = IvyPaymentHelper::LIVE_URL;
            $this->ivyApiKey = $config["IvyApiKey"];
        }
    }

    /**
     * @param string $endpoint
     * @param string $jsonContent
     * @return array
     * @throws IvyApiException
     */
    public function sendApiRequest($endpoint, $jsonContent)
    {
        $this->apiLogger->info('send ' . $endpoint . ' ' . $jsonContent);

        $client = new Client([
            'base_uri' => $this->ivyServiceUrl,
            'headers' => [
                'X-Ivy-Api-Key' => $this->ivyApiKey,
            ],
        ]);

        $headers['content-type'] = 'application/json';
        $options = [
            'headers' => $headers,
            RequestOptions::BODY => $jsonContent,
        ];

        try {
            $response = $client->post($endpoint, $options);
            $this->apiLogger->info('response: ' . (string)$response->getBody());
            if ($response->getStatusCode() === 200) {
                $response = \json_decode((string)$response->getBody(), true, 512);
            } else {
                $message = 'invalis response status: ' . $response->getStatusCode();
                $this->apiLogger->error($message);
                throw new IvyApiException($message);
            }
        } catch (GuzzleException $e) {
            $this->apiLogger->error($e->getMessage());
            throw new IvyApiException($e->getMessage());
        } catch (\Exception $e) {
            $this->apiLogger->error($e->getMessage());
            throw new IvyApiException($e->getMessage());
        }
        if (!\is_array($response)) {
            $message = 'invalid json response (is not array)';
            $this->apiLogger->error($message);
            throw new IvyApiException($message);
        }
        return $response;
    }
}