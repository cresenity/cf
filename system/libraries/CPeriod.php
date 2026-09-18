<?php

defined('SYSPATH') or die('No direct access allowed.');

class CPeriod implements IteratorAggregate {
    use CPeriod_Trait_FactoryTrait;
    use CPeriod_Trait_OperationTrait;
    use CPeriod_Trait_ComparisonTrait;
    use CPeriod_Trait_GetterTrait;
    const INTERVAL_MONTH = 'month';

    const INTERVAL_DAY = 'day';

    const INTERVAL_HOUR = 'hour';

    /**
     * @var \CCarbon
     */
    public $startDate;

    /**
     * @var \CCarbon
     */
    public $endDate;

    /**
     * @var DateInterval
     */
    protected $interval;

    /**
     * @var CPeriod_Precision
     */
    protected $precision;

    /**
     * @var CPeriod_Boundaries
     */
    protected $boundaries;

    /**
     * @var CPeriod_Duration
     */
    protected $duration;

    /**
     * @var CCarbon
     */
    protected $includedStart;

    /**
     * @var CCarbon
     */
    protected $includedEnd;

    /**
     * @param CCarbon|DateTime        $startDate
     * @param CCarbon|DateTime        $endDate
     * @param null|CPeriod_Precision  $precision
     * @param null|CPeriod_Boundaries $boundaries
     */
    public function __construct($startDate, $endDate, ?CPeriod_Precision $precision = null, ?CPeriod_Boundaries $boundaries = null) {
        // any DateTimeInterface (the factory hands over DateTimeImmutable) becomes a CCarbon so the
        // iterator's copy()/add() and the getters behave the same whichever way the period was built
        if ($startDate instanceof DateTimeInterface && !($startDate instanceof CCarbon)) {
            $startDate = new CCarbon($startDate->format('Y-m-d H:i:s.u'), $startDate->getTimezone());
        }
        if ($endDate instanceof DateTimeInterface && !($endDate instanceof CCarbon)) {
            $endDate = new CCarbon($endDate->format('Y-m-d H:i:s.u'), $endDate->getTimezone());
        }
        if ($startDate > $endDate) {
            throw CPeriod_Exception_InvalidPeriodException::startDateCannotBeAfterEndDate($startDate, $endDate);
        }
        if ($precision == null) {
            $precision = CPeriod_Precision::DAY();
        }
        if ($boundaries == null) {
            $boundaries = CPeriod_Boundaries::EXCLUDE_NONE();
        }

        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->precision = $precision;
        $this->boundaries = $boundaries;
        $this->interval = $this->precision->interval();
        $this->includedStart = $boundaries->startIncluded() ? $startDate : $startDate->add($this->interval);
        $this->includedEnd = $boundaries->endIncluded() ? $endDate : $endDate->sub($this->interval);
        $this->duration = new CPeriod_Duration($this);
    }

    /**
     * @param string|DateTime $startDate
     * @param string|DateTime $endDate
     *
     * @return CPeriod
     */
    public static function create($startDate, $endDate) {
        return new static($startDate, $endDate);
    }

    /**
     * @param null|CCarbon|DateTime $minimumDate unused
     *
     * @return CPeriod
     */
    public static function lifetime($minimumDate = null) {
        $endDate = CCarbon::today();
        $startDate = CCarbon::createFromTimestamp(0);

        return new static($startDate, $endDate);
    }

    /**
     * @param int $numberOfHours
     *
     * @return CPeriod
     */
    public static function hours($numberOfHours) {
        $endDate = CCarbon::now();
        $startDate = CCarbon::now()->subHours($numberOfHours);

        return new static($startDate, $endDate);
    }

    /**
     * @param int $numberOfDays
     *
     * @return CPeriod
     */
    public static function days($numberOfDays) {
        $endDate = CCarbon::today()->endOfDay();
        $startDate = CCarbon::today()->subDays($numberOfDays)->startOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function today() {
        $endDate = CCarbon::today()->endOfDay();
        $startDate = CCarbon::today()->startOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function yesterday() {
        $endDate = CCarbon::today()->subDays(1)->endOfDay();
        $startDate = CCarbon::today()->subDays(1)->startOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @param int $numberOfMonths
     *
     * @return CPeriod
     */
    public static function months($numberOfMonths) {
        $endDate = CCarbon::today()->endOfDay();
        $startDate = CCarbon::today()->subMonths($numberOfMonths)->startOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @param int $numberOfYears
     *
     * @return CPeriod
     */
    public static function years($numberOfYears) {
        $endDate = CCarbon::today()->endOfDay();
        $startDate = CCarbon::today()->subYears($numberOfYears)->startOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @param int $numberOfMinutes
     *
     * @return CPeriod
     */
    public static function minutes($numberOfMinutes) {
        $endDate = CCarbon::now();
        $startDate = CCarbon::now()->subMinutes($numberOfMinutes);

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function thisWeek() {
        $startDate = CCarbon::now()->modify('this week')->startOfDay();
        $endDate = CCarbon::now()->modify('this week +6 days')->endOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function lastWeek() {
        $startDate = CCarbon::now()->modify('last week')->startOfDay();
        $endDate = CCarbon::now()->modify('last week +6 days')->endOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function last7Days() {
        return self::days(6);
    }

    /**
     * @return CPeriod
     */
    public static function last14Days() {
        return self::days(13);
    }

    /**
     * @return CPeriod
     */
    public static function last30Days() {
        return self::days(29);
    }

    /**
     * @return CPeriod
     */
    public static function thisMonth() {
        $startDate = CCarbon::now()->modify('first day of this month')->startOfDay();
        $endDate = CCarbon::now()->modify('last day of this month')->endOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function lastMonth() {
        $startDate = CCarbon::now()->modify('first day of last month')->startOfDay();
        $endDate = CCarbon::now()->modify('last day of last month')->endOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function last3Month() {
        return static::months(3);
    }

    /**
     * @return CPeriod
     */
    public static function thisYear() {
        $startDate = CCarbon::now()->startOfYear()->startOfDay();
        $endDate = CCarbon::now()->endOfYear()->endOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @return CPeriod
     */
    public static function untilDateNow() {
        $startDate = CCarbon::createFromTimestamp(0)->startOfDay();
        $endDate = CCarbon::now()->endOfDay();

        return new static($startDate, $endDate);
    }

    /**
     * @param string             $interval
     * @param int                $count
     * @param CCarbon|string|int $start
     *
     * @return CPeriod
     */
    public static function createFromInterval($interval = 'month', $count = 1, $start = '') {
        if (empty($start)) {
            $start = CCarbon::now();
        } elseif (!$start instanceof CCarbon) {
            $start = new CCarbon($start);
        } else {
            $start = $start;
        }

        $startCloned = clone $start;
        $method = 'add' . ucfirst($interval) . 's';
        $end = $startCloned->{$method}($count);
        if (strtolower($interval) == 'month') {
            if ($start->format('d') != $end->format('d')) {
                if ((int) $end->format('d') === 1) {
                    $end = $end->subMonths(1)->endOfMonth();
                }
            }
        }

        return new static($start, $end);
    }

    /**
     * @return CCarbon
     */
    public function getEndDate() {
        return $this->endDate;
    }

    /**
     * @return CCarbon
     */
    public function getStartDate() {
        return $this->startDate;
    }

    /**
     * @return CCarbon[]
     */
    public function toArray() {
        return [$this->startDate, $this->endDate];
    }

    /**
     * @return DatePeriod
     */
    #[\ReturnTypeWillChange]
    public function getIterator() {
        return new DatePeriod(
            $this->includedStart(),
            $this->interval,
            // We need to add 1 second (the smallest unit available within this package) to ensure entries are counted correctly
            // ->copy() first: includedEnd() returns a mutable CCarbon, and add() mutates in place - without cloning,
            // every iteration (or repeated length() call on month/year precision) would push includedEnd 1 second further
            $this->includedEnd()->copy()->add(new DateInterval('PT1S'))
        );
    }

    /**
     * @param CPeriod $other
     *
     * @return void
     */
    protected function ensurePrecisionMatches(CPeriod $other) {
        if ($this->precision->equals($other->precision)) {
            return;
        }

        throw CPeriod_Exception_CannotComparePeriodException::precisionDoesNotMatch();
    }

    /**
     * @param  array{
     *             monday?: array<string|array>,
     *             tuesday?: array<string|array>,
     *             wednesday?: array<string|array>,
     *             thursday?: array<string|array>,
     *             friday?: array<string|array>,
     *             saturday?: array<string|array>,
     *             sunday?: array<string|array>,
     *             exceptions?: array<array<string|array>>,
     *             filters?: callable[],
     *             overflow?: bool,
     *         }                         $data
     * @param null|string|DateTimeZone $timezone
     * @param null|string|DateTimeZone $outputTimezone
     *
     * @return CPeriod_OpeningHours
     */
    public static function openingHours(array $data, $timezone = null, $outputTimezone = null) {
        return CPeriod_OpeningHours::create($data, $timezone, $outputTimezone);
    }
}
