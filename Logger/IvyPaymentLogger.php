<?php
/**
 * Implemented by HammerCode OÜ team https://www.hammercode.eu/
 *
 * @copyright HammerCode OÜ https://www.hammercode.eu/
 * @license proprietär
 * @link https://www.hammercode.eu/
 */

namespace IvyPaymentPlugin\Logger;


use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as BaseLogger;
use Monolog\Processor\UidProcessor;

class IvyPaymentLogger extends BaseLogger
{
    /**
     * @var RotatingFileHandler
     */
    private $handler;

    /**
     * @param array $config
     * @param string $name
     * @param UidProcessor $calback
     * @param LineFormatter $formatter
     * @return void
     */
    public function init(array $config, $name, UidProcessor $calback, LineFormatter $formatter)
    {
        $level = (int)$config['logLevel'];
        $loggerDays = (int)(isset($config['loggerDays']) ? $config['loggerDays'] : 60);
        $handler = new RotatingFileHandler($name, $loggerDays);
        $handler->pushProcessor($calback);
        $handler->setFormatter($formatter);
        $this->handler = $handler;
        $this->pushHandler($handler);
        $this->setLevel($level);
    }

    /**
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->handler->setLevel($level);
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->handler->getLevel();
    }
}
