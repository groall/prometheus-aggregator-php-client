<?php

namespace PrometheusAggregator;

/**
 * Client for the Prometheus aggregator
 * https://github.com/peterbourgon/prometheus-aggregator
 */
class Client
{
    /**
     * @var string The address of the aggregator
     */
    private static string $host = 'localhost';
    /**
     * @var integer The port of the aggregator
     */
    private static int $port = 8191;
    /**
     * @var integer The level of compression. 0 - no compression, 9 - maximum.
     */
    private static int $compressLevel = 5;

    /**
     * Initializes the client
     *
     * @param string $host
     * @param int $port
     * @param int $compressLevel
     * @return void
     * @throws \Exception
     */
    public static function init(string $host, int $port, int $compressLevel = 5)
    {
        self::$host = $host;
        self::$port = $port;
        self::$compressLevel = $compressLevel;

        self::checkConfig();
        self::checkModules();
    }

    /**
     * Checks if the config is valid
     *
     * @return void
     * @throws \Exception
     */
    private static function checkConfig() {
        if (empty(self::$host)) {
            throw new \Exception('Host is empty');
        }
        if (empty(self::$port)) {
            throw new \Exception('Port is empty');
        }
        if (self::$port < 0 || self::$port > 65535) {
            throw new \Exception('Port must be between 0 and 65535');
        }
        if (self::$compressLevel < 0 || self::$compressLevel > 9) {
            throw new \Exception('Compress level must be between 0 and 9');
        }
    }

    /**
     * Checks if the needed modules are loaded
     *
     * @return void
     * @throws \Exception
     */
    private static function checkModules() {
        if (!extension_loaded('sockets')) {
            throw new \Exception('Extension sockets not loaded');
        }
        if (!extension_loaded('json')) {
            throw new \Exception('Extension json not loaded');
        }
        if (self::$compressLevel > 0 && !extension_loaded('zlib')) {
            throw new \Exception('Extension zlib not loaded');
        }
    }

    /**
     * Отправляет текущее значение метрики в агрегатор
     * @param string $name Название метрики
     * @param mixed $value Текущее значение метрики
     * @param array $labels Теги, ключи метрики
     */
    public static function send(string $name, mixed $value, array $labels = [])
    {
        if (!($sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))) {
            $errorCode = socket_last_error();
            $errorMsg = socket_strerror($errorCode);
            throw new \Exception("Couldn't create socket: [{$errorCode}] {$errorMsg}");
        }

        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 200]);
        $msg = json_encode([
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ]);

        if (self::$compressLevel > 0) {
            $msg = gzencode($msg, self::$compressLevel);
        }

        $len = strlen($msg);
        if (!socket_sendto($sock, $msg, $len, 0, self::$host, self::$port)) {
            $errorCode = socket_last_error();
            $errorMsg = socket_strerror($errorCode);
            throw new \Exception("Could not send data to metrics.nodasrv.net: [{$errorCode}] {$errorMsg}");
        }

        socket_close($sock);
    }
}
