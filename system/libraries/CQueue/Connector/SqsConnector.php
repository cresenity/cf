<?php

defined('SYSPATH') OR die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @since Nov 5, 2019, 8:03:39 PM
 * @license Ittron Global Teknologi <ittron.co.id>
 */
use Aws\Sqs\SqsClient;

class CQueue_Connector_SqsConnector extends CQueue_AbstractConnector {

    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return CQueue_AbstractQueue
     */
    public function connect(array $config) {
        $config = $this->getDefaultConfiguration($config);
        if (!empty($config['key']) && !empty($config['secret'])) {
            $config['credentials'] = carr::only($config, ['key', 'secret', 'token']);
        }
        return new CQueue_Queue_SqsQueue(
                new SqsClient($config), $config['queue'], carr::get($config,'prefix','')
        );
    }

    /**
     * Get the default configuration for SQS.
     *
     * @param  array  $config
     * @return array
     */
    protected function getDefaultConfiguration(array $config) {
        return array_merge([
            'version' => 'latest',
            'curl.options' => [CURLOPT_SSL_VERIFYPEER => false],
            'http' => [
                'timeout' => 60,
                'connect_timeout' => 60,
            ],
                ], $config);
    }

}
