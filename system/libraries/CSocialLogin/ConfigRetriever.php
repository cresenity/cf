<?php

class CSocialLogin_ConfigRetriever implements CSocialLogin_Contract_ConfigRetrieverInterface {
    /**
     * @var string
     */
    protected $providerName;

    /**
     * @var string
     */
    protected $providerIdentifier;

    /**
     * @var array
     */
    protected $servicesArray;

    /**
     * @var array
     */
    protected $additionalConfigKeys;

    /**
     * @param string $providerName
     * @param array  $additionalConfigKeys
     *
     * @return \CSocialLogin_Contract_ConfigInterface
     */
    public function fromServices($providerName, array $additionalConfigKeys = []) {
        $this->providerName = $providerName;
        $this->getConfigFromServicesArray($providerName);

        $this->additionalConfigKeys = $additionalConfigKeys = array_unique($additionalConfigKeys + ['guzzle']);

        return new CSocialLogin_Config(
            $this->getFromServices('client_id'),
            $this->getFromServices('client_secret'),
            $this->getFromServices('redirect'),
            $this->getConfigItems($additionalConfigKeys, function ($key) {
                return $this->getFromServices(strtolower($key));
            })
        );
    }

    /**
     * @param array    $configKeys
     * @param \Closure $keyRetrievalClosure
     *
     * @return array
     */
    protected function getConfigItems(array $configKeys, Closure $keyRetrievalClosure) {
        return $this->retrieveItemsFromConfig($configKeys, $keyRetrievalClosure);
    }

    /**
     * @param array    $keys
     * @param \Closure $keyRetrievalClosure
     *
     * @return array
     */
    protected function retrieveItemsFromConfig(array $keys, Closure $keyRetrievalClosure) {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = $keyRetrievalClosure($key);
        }

        return $out;
    }

    /**
     * @param string $key
     *
     * @throws \CSocialLogin_Exception_MissingConfigException
     *
     * @return null|string
     */
    protected function getFromServices($key) {
        $keyExists = array_key_exists($key, $this->servicesArray);

        // ADDITIONAL value is empty
        if (!$keyExists && $this->isAdditionalConfig($key)) {
            return $key == 'guzzle' ? [] : null;
        }

        // REQUIRED value is empty
        if (!$keyExists) {
            throw new CSocialLogin_Exception_MissingConfigException("Missing services entry for {$this->providerName}.{$key}");
        }

        return $this->servicesArray[$key];
    }

    /**
     * @param string $providerName
     *
     * @throws \CSocialLogin_Exception_MissingConfigException
     *
     * @return array
     */
    protected function getConfigFromServicesArray($providerName) {
        $configArray = CF::config("vendor.{$providerName}");

        if (empty($configArray)) {
            throw new CSocialLogin_Exception_MissingConfigException("There is no services entry for {$providerName}");
        }

        return $this->servicesArray = $configArray;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function isAdditionalConfig($key) {
        return in_array(strtolower($key), $this->additionalConfigKeys, true);
    }
}
