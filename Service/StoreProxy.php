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
use GuzzleHttp\Message\ResponseInterface;
use IvyPaymentPlugin\Components\IvyJsonResponse;
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
     * @throws \Exception
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
            'headers' => $request->headers->all(),
        ];
        $params = $request->request->all();
        if (!empty($params)) {
            $options['form_params'] = $params;
        } elseif (!empty((string)$request->getContent())) {
            $options['body'] = (string)$request->getContent();
        }
        switch ($request->getMethod()) {
            case 'GET':
                $response = $client->get($url, $options);
                break;
            case 'POST':
                $response = $client->post($url, $options);
                break;
            default:
                throw new \Exception('method not supported ' . $request->getMethod());
        }

        $decoded = \json_decode($response->getBody(), true);

        if ($response->getStatusCode() === IvyJsonResponse::HTTP_FOUND) {
            $url = $this->router->assemble($decoded['redirect']);
            if ($request->isSecure()) {
                $url = \str_replace('http:', 'https:', $url);
            }
            return $client->post($url, $options);
        }
        return $response;
    }
}