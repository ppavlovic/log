<?php

namespace G4\Log\Buffer;

use G4\Log\Adapter\RedisElasticsearchCurl;
use G4\Log\Adapter\Redis;
use G4\ValueObject\IntegerNumber;

class RedisToElastic
{
    const LOG_TITLE = '[log]';

    /**
     * @var IntegerNumber
     */
    private $batchsize;

    /**
     * @var RedisElasticsearchCurl
     */
    private $elasticClient;

    /**
     * @var \Redis
     */
    private $redisClient;

    /**
     * @var array
     */
    private $data;

    /**
     * @var int
     */
    private $countFromRedis;

    /**
     * RedisToElastic constructor.
     *
     * @param Redis $redisClient
     * @param RedisElasticsearchCurl $elasticClient
     * @param IntegerNumber $batchsize
     */
    public function __construct(Redis $redisClient, RedisElasticsearchCurl $elasticClient, IntegerNumber $batchsize)
    {
        $this->redisClient      = $redisClient;
        $this->elasticClient    = $elasticClient;
        $this->batchsize        = $batchsize;
    }

    public function transferData()
    {
        $data = $this->redisClient->fetchAndClear($this->batchsize);
        $this->countFromRedis = count($data);
        if (!empty($data)) {
            foreach ($data as $key => $log) {
                $logData = json_decode($log, 1);
                $this->data[$key] = $logData;
            }
        }

        return $this;
    }

    public function insertIntoES()
    {
        if (empty($this->data)) {
            return $this;
        }

        if ($this->elasticClient->isElasticsearchAvailable()) {
            $this->elasticClient->sendAll($this->data);

            return $this;
        }

        $this->rollbackToRedis();

        return $this;
    }

    public function getInfo()
    {
        echo self::LOG_TITLE . ' The number of ' . (string) $this->redisClient->getKey()
            . ' logs read from redis: ' . $this->countFromRedis . PHP_EOL;
        echo self::LOG_TITLE . ' The number of ' . (string) $this->redisClient->getKey()
            . ' logs inserted in ES: ' . PHP_EOL . $this->elasticClient->getCountInfo() . PHP_EOL;
        return $this;
    }

    private function rollbackToRedis()
    {
        $this->redisClient->doRPushBatch($this->data);

        echo self::LOG_TITLE . ' ES Cluster is not available at the moment.' . PHP_EOL;
    }
}
