<?php

/**
 * Pengganti CQueue_PendingDispatch untuk CExporter::fake(): meniru API fluent-nya tanpa pernah mengirim job.
 */
class CExporter_Fake_PendingDispatch {
    /**
     * @var array
     */
    protected $chain = [];

    /**
     * @param array|string $chain
     *
     * @return $this
     */
    public function chain($chain) {
        $this->chain = array_merge($this->chain, (array) $chain);

        return $this;
    }

    /**
     * @return array
     */
    public function getChain() {
        return $this->chain;
    }

    /**
     * onConnection(), onQueue(), delay(), afterCommit(), dst. diterima dan diabaikan.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return $this
     */
    public function __call($method, $parameters) {
        return $this;
    }
}
