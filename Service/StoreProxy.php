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
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use IvyPaymentPlugin\Components\IvyJsonResponse;
use Psr\Http\Message\ResponseInterface;
use Shopware\Components\Routing\Router;
use Shopware\Models\Shop\Shop;
use Symfony\Component\HttpFoundation\Request;


class StoreProxy
{
    /**
     * @var Shop
     */
    private $shop;

    /**
     * @var Router
     */
    private $router;

    /**
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->shop = Shopware()->Shop();
        $this->router = $router;
    }

    /**
     * @param Request $request
     * @param $swContextToken
     * @return ResponseInterface
     * @throws GuzzleException
     */
    public function proxy(Request $request, $swContextToken)
    {
        $query = $request->query->all();
        $query['controller'] = 'IvyProxy';

        $client = new Client([]);
        $url = $this->router->assemble($query);
        if ($request->isSecure()) {
            $url = \str_replace('http:', 'https:', $url);
        }
        $cookieJar = CookieJar::fromArray(\json_decode(\base64_decode($swContextToken), true), $this->shop->getHost());
        $options = [
            'cookies' => $cookieJar,
            RequestOptions::HEADERS => $request->headers->all(),
        ];
        $params = $request->request->all();
        if (!empty($params)) {
            $options[RequestOptions::FORM_PARAMS] = $params;
        } elseif (!empty((string)$request->getContent())) {
            $options[RequestOptions::BODY] = (string)$request->getContent();
        }
        $response = $client->request($request->getMethod(), $url, $options);

        $decoded = \json_decode($response->getBody(), true);

        if ($response->getStatusCode() === IvyJsonResponse::HTTP_FOUND) {
            $url = $this->router->assemble($decoded['redirect']);
            if ($request->isSecure()) {
                $url = \str_replace('http:', 'https:', $url);
            }
            return $client->request('POST', $url, $options);
        }
        return $response;
    }
}