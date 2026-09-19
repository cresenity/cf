<?php
interface CException_Contract_RunnableSolutionInterface extends CException_Contract_SolutionInterface {
    /**
     * @return string
     */
    public function getSolutionActionDescription();

    /**
     * @return string
     */
    public function getRunButtonText();

    /**
     * @return void
     */
    public function run(array $parameters = []);

    /**
     * @return array
     */
    public function getRunParameters();
}
