<?php
class CAnalytics_Google_Analytic_BetaGA4Analytic {
    use CTrait_Macroable;

    /**
     * @var CAnalytics_Google_GA4_Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $propertyId;

    private $dateRanges = [];

    private $metrics = [];

    private $dimensions = [];

    private $report;

    /**
     * @param \Teodoriu\Analytics\CAnalytics_Google_GA4_Client $client
     * @param string                              $propertyId
     */
    public function __construct(CAnalytics_Google_GA4_Client $client, string $propertyId) {
        $this->client = $client;

        $this->propertyId = $propertyId;
    }

    /**
     * @param string $propertyId
     *
     * @return $this
     */
    public function setPropertyId(string $propertyId) {
        $this->propertyId = $propertyId;

        return $this;
    }

    public function getPropertyId() {
        return $this->propertyId;
    }

    public function setDateRanges(DateTimeInterface $startDate, DateTimeInterface $endDate) {
        $this->dateRanges = new DateRange(
            [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]
        );

        return $this;
    }

    public function setMetrics(array $metrics = []) {
        $this->metrics = [];

        foreach ($metrics as $metric)
            :$this->metrics[] = new Metric(
                [
                    'name' => $metric,
                ]
            );
        endforeach;

        return $this;
    }

    public function setDimensions(array $dimensions = []) {
        $this->dimensions = [];

        foreach ($dimensions as $dimension)
            :$this->dimensions[] = new Dimension(
                [
                    'name' => $dimension,
                ]
            );
        endforeach;

        return $this;
    }

    /**
     * Call the query method on the authenticated client.
     */
    public function runReport() {
        $this->report = $this->client->runReport(
            $this->propertyId,
            $this->dateRanges,
            $this->metrics,
            $this->dimensions,
        );

        return $this;
    }

    public function raw() {
        return $this->report;
    }

    public function toArray() {
        $data = [];

        foreach ($this->report->getRows() as $row)
            : if (count($this->dimensions) === 1)
                :$data[$row->getDimensionValues()[0]->getValue()] = collect($row->getMetricValues() ?? [])->map(
                    function ($value) {
                        return $value->getValue();
                    }
                ); else
                :$dimensions = collect($row->getDimensionValues() ?? [])->map(
                    function ($value) {
                        return $value->getValue();
                    }
                );
        $metrics = collect($row->getMetricValues() ?? [])->map(
            function ($value) {
                return $value->getValue();
            }
        );

        $data[] = $dimensions->merge($metrics);
        endif;
        endforeach;

        return $data;
    }

    public function toCollection() {
        return collect($this->toArray());
    }

    public function getAnalyticsService(): BetaAnalyticsDataClient {
        return $this->client->getAnalyticsService();
    }
}
