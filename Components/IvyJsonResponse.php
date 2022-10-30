<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Components;

use Symfony\Component\HttpFoundation\JsonResponse;

class IvyJsonResponse extends JsonResponse
{
    /**
     * @param $data
     * @param int $status
     * @param array $headers
     * @param bool $json
     */
    public function __construct($data = null, $status = 200, array $headers = [], $json = false)
    {
        $this->encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        parent::__construct($data, $status, $headers, $json);
    }
}
