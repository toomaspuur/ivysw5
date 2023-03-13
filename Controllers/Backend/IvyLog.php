<?php

use Bcremer\LineReader\LineReader;
use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Backend_IvyLog extends Shopware_Controllers_Backend_Log
{
    public function getLogFileListAction()
    {
        $logDir = $this->get('kernel')->getLogDir() . '/ivypayment';
        $files = $this->getLogFiles($logDir);
        $defaultFile = $this->getDefaultLogFile($files);

        // filter against input
        $query = trim($this->Request()->getParam('query', ''));
        foreach ($files as $k => $file) {
            if ($query !== '' && mb_stripos($file[0], $query) === false) {
                unset($files[$k]);
                continue;
            }
            $files[$k] = [
                'name' => $file[0],
                'channel' => $file['channel'],
                'environment' => $file['environment'],
                'date' => $file['date'],
                'default' => $file[0] === $defaultFile,
            ];
        }

        $start = $this->Request()->getParam('start', 0);
        $limit = $this->Request()->getParam('limit', 100);

        $count = \count($files);
        $files = \array_slice($files, $start, $limit);

        $this->View()->assign([
            'success' => true,
            'data' => $files,
            'total' => $count,
        ]);
    }


    private function getLogFiles($logDir)
    {
        $finder = new Symfony\Component\Finder\Finder();
        $finder->files()->name('*.log')->in($logDir);

        $matches = [];
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            $name = $file->getBasename();
            if (preg_match('/^(?P<channel>[^_]+)\-(?P<date>[0-9-]+)\.log$/', $name, $match)) {
                $matches[implode('-', [$match['date'], $match['channel']])] = $match;
            }
        }
        krsort($matches);
        return array_values($matches);
    }

    public function downloadLogFileAction()
    {
        $logDir = $this->get('kernel')->getLogDir() . '/ivypayment';
        $files = $this->getLogFiles($logDir);

        $logFile = $this->Request()->getParam('logFile');
        $logFile = $this->getLogFile($files, $logFile);

        if (!$logFile) {
            throw new RuntimeException('Log file not found.');
        }

        $logFilePath = $logDir . '/' . $this->getLogFile($files, $logFile);

        $response = $this->Response();
        $response->headers->set('cache-control', 'public', true);
        $response->headers->set('content-type', 'application/octet-stream');
        $response->headers->set('content-description', 'File Transfer');
        $response->headers->set('content-disposition', 'attachment; filename=' . $logFile);
        $response->headers->set('content-transfer-encoding', 'binary');
        $response->headers->set('content-length', (string) filesize($logFilePath));
        $response->sendHeaders();

        $this->Front()->Plugins()->ViewRenderer()->setNoRender();

        $out = fopen('php://output', 'wb');
        $file = fopen($logFilePath, 'rb');

        stream_copy_to_stream($file, $out);
    }

    public function getLogListAction()
    {
        $logDir = $this->get('kernel')->getLogDir() . '/ivypayment';;
        $files = $this->getLogFiles($logDir);

        $logFile = $this->Request()->getParam('logFile');
        $logFile = $this->getLogFile($files, $logFile);

        if (!$logFile) {
            $this->View()->assign([
                'success' => true,
                'data' => [],
                'count' => 0,
            ]);

            return;
        }

        $file = $logDir . '/' . $logFile;
        $start = $this->Request()->getParam('start', 0);
        $limit = $this->Request()->getParam('limit', 100);
        $sort = $this->Request()->getParam('sort');

        $reverse = false;
        if (!isset($sort[0]['direction']) || $sort[0]['direction'] === 'DESC') {
            $reverse = true;
        }

        $data = $this->parseLogFile(
            $file,
            $start,
            $limit,
            $reverse
        );

        $this->View()->assign([
            'success' => true,
            'data' => $data,
            'count' => $this->countLogFile($file),
        ]);
    }

    public function countLogFile($filePath)
    {
        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);

        return $file->key();
    }

    /**
     * @param $file
     * @param $offset
     * @param $limit
     * @param $reverse
     * @return array
     */
    public function parseLogFile($file, $offset, $limit, $reverse)
    {

        if ($reverse) {
            $lineGenerator = LineReader::readLinesBackwards($file);
        } else {
            $lineGenerator = LineReader::readLines($file);
        }

        $reader = new LimitIterator($lineGenerator, $offset, $limit);

        $result = [];
        foreach ($reader as $line) {
            $result[] = $this->parseLine($line);
        }

        return $result;
    }

    /**
     * @param $log
     * @return array
     */
    private function parseLine($log): array
    {
        $pattern = '/\[(?P<date>[^\[]*)\] (?P<channel>\w+).(?P<level>\w+): (?P<message>.+) (?P<context>\[\] \{.*:.*\}$)/';

        preg_match($pattern, $log, $data);

        if (!isset($data['date'])) {
            return [
                'raw' => $log,
            ];
        }

        return [
            'date' => $data['date'],
            'channel' => $data['channel'],
            'level' => $data['level'],
            'message' => $data['message'],
            'context' => json_decode($data['context'], true),
            'extra' => json_decode($data['extra'], true),
            'raw' => $log,
        ];
    }

    private function getLogFile($files, $name)
    {
        foreach ($files as $file) {
            if ($name == $file[0]) {
                return $name;
            }
        }

        return false;
    }

    private function getDefaultLogFile(array $files)
    {
        return isset($files[0]) ? $files[0] : false;
    }
}