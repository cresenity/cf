<?php
use PHPUnit\Framework\TestCase;

/**
 * Mutex tanpa penyimpanan untuk menguji ekspresi cron; tidak pernah menganggap ada yang berjalan.
 */
class UjiCron_NullMutex implements CCron_Contract_EventMutexInterface {
    /** @var int */
    public $created = 0;

    /** @var int */
    public $forgotten = 0;

    /** @var bool */
    public $existing = false;

    public function create(CCron_Event $event) {
        $this->created++;

        return !$this->existing;
    }

    public function exists(CCron_Event $event) {
        return $this->existing;
    }

    public function forget(CCron_Event $event) {
        $this->forgotten++;
    }
}

/**
 * Port FrequencyTest hulu ke CCron_Event / CCron_Trait_ManagesFrequenciesTrait: setiap
 * pembantu frekuensi harus menghasilkan ekspresi cron 5 kolom yang persis sama.
 */
class CronFrequencyPortTest extends TestCase {
    /**
     * @return CCron_Event
     */
    protected function event() {
        return new CCron_Event(new UjiCron_NullMutex(), 'php foo');
    }

    public function testEveryMinute() {
        $this->assertSame('* * * * *', $this->event()->getExpression());
        $this->assertSame('* * * * *', $this->event()->everyMinute()->getExpression());
    }

    public function testEveryXMinutes() {
        $this->assertSame('*/2 * * * *', $this->event()->everyTwoMinutes()->getExpression());
        $this->assertSame('*/3 * * * *', $this->event()->everyThreeMinutes()->getExpression());
        $this->assertSame('*/4 * * * *', $this->event()->everyFourMinutes()->getExpression());
        $this->assertSame('*/5 * * * *', $this->event()->everyFiveMinutes()->getExpression());
        $this->assertSame('*/10 * * * *', $this->event()->everyTenMinutes()->getExpression());
        $this->assertSame('*/15 * * * *', $this->event()->everyFifteenMinutes()->getExpression());
        $this->assertSame('*/30 * * * *', $this->event()->everyThirtyMinutes()->getExpression(), 'CF memakai */30 (hulu 0,30) - hasil sama');
    }

    public function testEveryXHours() {
        $this->assertSame('0 */2 * * *', $this->event()->everyTwoHours()->getExpression());
        $this->assertSame('0 */3 * * *', $this->event()->everyThreeHours()->getExpression());
        $this->assertSame('0 */4 * * *', $this->event()->everyFourHours()->getExpression());
        $this->assertSame('0 */6 * * *', $this->event()->everySixHours()->getExpression());
        $this->assertSame('0 1-23/2 * * *', $this->event()->everyOddHour()->getExpression());
    }

    public function testDaily() {
        $this->assertSame('0 0 * * *', $this->event()->daily()->getExpression());
    }

    public function testDailyAt() {
        $this->assertSame('8 13 * * *', $this->event()->dailyAt('13:08')->getExpression());
        $this->assertSame('0 13 * * *', $this->event()->dailyAt('13')->getExpression(), 'jam saja = menit 0');
    }

    public function testDailyAtParsesMinutesAndIgnoresSecondsWhenSecondsAreDefined() {
        $this->assertSame('8 13 * * *', $this->event()->dailyAt('13:08:15')->getExpression());
    }

    public function testTwiceDaily() {
        $this->assertSame('0 1,13 * * *', $this->event()->twiceDaily()->getExpression());
        $this->assertSame('0 3,15 * * *', $this->event()->twiceDaily(3, 15)->getExpression());
    }

    public function testTwiceDailyAt() {
        $this->assertSame('5 3,15 * * *', $this->event()->twiceDailyAt(3, 15, 5)->getExpression());
    }

    public function testWeekly() {
        $this->assertSame('0 0 * * 0', $this->event()->weekly()->getExpression());
    }

    public function testWeeklyOn() {
        $this->assertSame('0 8 * * 1', $this->event()->weeklyOn(1, '8:00')->getExpression());
        $this->assertSame('0 0 * * 1', $this->event()->weeklyOn(1)->getExpression());
        $this->assertSame('0 8 * * 1,3', $this->event()->weeklyOn([1, 3], '8:00')->getExpression(), 'daftar hari');
    }

    public function testOverrideWithHourly() {
        // divergensi CF: hourly() hanya mengisi kolom menit (dikunci oleh aturan komutatif
        // daily()->hourly() == hourly()->daily() di ScheduledEventTest); hulu mengembalikan '0 * * * 1'
        $this->assertSame('0 8 * * 1', $this->event()->weeklyOn(1, '8:00')->hourly()->getExpression());
        $this->assertSame('0 * * * *', $this->event()->hourly()->getExpression());
    }

    public function testHourly() {
        $this->assertSame('0 * * * *', $this->event()->hourly()->getExpression());
        $this->assertSame('37 * * * *', $this->event()->hourlyAt(37)->getExpression());
        $this->assertSame('15,30,45 * * * *', $this->event()->hourlyAt([15, 30, 45])->getExpression());
    }

    public function testMonthly() {
        $this->assertSame('0 0 1 * *', $this->event()->monthly()->getExpression());
    }

    public function testMonthlyOn() {
        $this->assertSame('0 15 4 * *', $this->event()->monthlyOn(4, '15:00')->getExpression());
        $this->assertSame('0 0 1 * *', $this->event()->monthlyOn()->getExpression());
    }

    public function testLastDayOfMonth() {
        CCarbon::setTestNow('2020-10-10 10:10:10');
        $this->assertSame('0 0 31 * *', $this->event()->lastDayOfMonth()->getExpression());
        $this->assertSame('0 15 31 * *', $this->event()->lastDayOfMonth('15:00')->getExpression());
        CCarbon::setTestNow('2021-02-10 10:10:10');
        $this->assertSame('0 0 28 * *', $this->event()->lastDayOfMonth()->getExpression(), 'mengikuti bulan sekarang');
        CCarbon::setTestNow(null);
    }

    public function testTwiceMonthly() {
        $this->assertSame('0 0 1,16 * *', $this->event()->twiceMonthly()->getExpression());
        $this->assertSame('0 0 3,17 * *', $this->event()->twiceMonthly(3, 17)->getExpression());
    }

    public function testTwiceMonthlyAtTime() {
        $this->assertSame('30 1 1,16 * *', $this->event()->twiceMonthly(1, 16, '1:30')->getExpression());
    }

    public function testMonthlyOnWithMinutes() {
        $this->assertSame('15 15 4 * *', $this->event()->monthlyOn(4, '15:15')->getExpression());
    }

    public function testWeekdaysDaily() {
        $this->assertSame('0 0 * * 1-5', $this->event()->weekdays()->daily()->getExpression());
    }

    public function testWeekdaysHourly() {
        $this->assertSame('0 * * * 1-5', $this->event()->weekdays()->hourly()->getExpression());
    }

    public function testWeekdays() {
        $this->assertSame('* * * * 1-5', $this->event()->weekdays()->getExpression());
    }

    public function testWeekends() {
        $this->assertSame('* * * * 6,0', $this->event()->weekends()->getExpression());
    }

    public function testEachDayOfWeekHelper() {
        $this->assertSame('* * * * 0', $this->event()->sundays()->getExpression());
        $this->assertSame('* * * * 1', $this->event()->mondays()->getExpression());
        $this->assertSame('* * * * 2', $this->event()->tuesdays()->getExpression());
        $this->assertSame('* * * * 3', $this->event()->wednesdays()->getExpression());
        $this->assertSame('* * * * 4', $this->event()->thursdays()->getExpression());
        $this->assertSame('* * * * 5', $this->event()->fridays()->getExpression());
        $this->assertSame('* * * * 6', $this->event()->saturdays()->getExpression());
    }

    public function testDaysAcceptsListAndVariadic() {
        $this->assertSame('* * * * 1,3', $this->event()->days([1, 3])->getExpression());
        $this->assertSame('* * * * 1,3', $this->event()->days(1, 3)->getExpression());
        $this->assertSame('* * * * 1', $this->event()->days(CCron_Schedule::MONDAY)->getExpression());
    }

    public function testQuarterly() {
        $this->assertSame('0 0 1 1-12/3 *', $this->event()->quarterly()->getExpression());
    }

    public function testQuarterlyOn() {
        $this->assertSame('0 15 4 1-12/3 *', $this->event()->quarterlyOn(4, '15:00')->getExpression());
    }

    public function testYearly() {
        $this->assertSame('0 0 1 1 *', $this->event()->yearly()->getExpression());
    }

    public function testYearlyOn() {
        $this->assertSame('8 15 5 4 *', $this->event()->yearlyOn(4, 5, '15:08')->getExpression());
    }

    public function testYearlyOnMondaysOnly() {
        $this->assertSame('1 9 * 7 1', $this->event()->mondays()->yearlyOn(7, '*', '09:01')->getExpression());
    }

    public function testYearlyOnTuesdaysAndDayOfMonth20() {
        $this->assertSame('1 9 20 7 2', $this->event()->tuesdays()->yearlyOn(7, 20, '09:01')->getExpression());
    }

    public function testAtIsAnAliasOfDailyAt() {
        $this->assertSame('30 7 * * *', $this->event()->at('7:30')->getExpression());
    }

    public function testCronSetsTheRawExpression() {
        $this->assertSame('5 4 * * sun', $this->event()->cron('5 4 * * sun')->getExpression());
    }

    public function testChainedRulesAreCommutative() {
        $this->assertSame($this->event()->daily()->hourly()->getExpression(), $this->event()->hourly()->daily()->getExpression());
        $this->assertSame($this->event()->weekdays()->hourly()->getExpression(), $this->event()->hourly()->weekdays()->getExpression());
    }

    public function testTimezoneIsKeptOnTheEvent() {
        $event = $this->event()->timezone('Asia/Jakarta');
        $this->assertSame('Asia/Jakarta', $event->timezone);
        $event = $this->event()->timezone(new DateTimeZone('UTC'));
        $this->assertInstanceOf(DateTimeZone::class, $event->timezone);
    }

    public function testFrequencyMacro() {
        CCron_Event::macro('ujiEveryFiveHours', function () {
            return $this->spliceIntoPosition(1, 0)->spliceIntoPosition(2, '*/5');
        });
        $this->assertSame('0 */5 * * *', $this->event()->ujiEveryFiveHours()->getExpression());
    }
}
