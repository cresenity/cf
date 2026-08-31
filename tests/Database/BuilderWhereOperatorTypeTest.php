<?php

use Mockery as m;
use PHPUnit\Framework\TestCase;

/**
 * A where() clause built from raw request input (e.g. a scanner sending
 * "?operator[]=x" where a scalar is expected) used to crash strtolower()
 * inside invalidOperator()/isBitwiseOperator() with a TypeError instead of
 * being treated as just another unrecognized operator.
 */
class BuilderWhereOperatorTypeTest extends TestCase {
    protected function tearDown(): void {
        m::close();
    }

    /**
     * @return CDatabase_Query_Builder
     */
    protected function getBuilder() {
        $grammar = new CDatabase_Query_Grammar();
        $processor = new CDatabase_Query_Processor();
        $connection = m::mock(CDatabase_Connection::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $connection->shouldReceive('getDatabaseName')->andReturn('database');
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $grammar->setConnection($connection);

        return new CDatabase_Query_Builder($connection, $grammar, $processor);
    }

    public function testWhereWithAnArrayValuedOperatorDoesNotCrash() {
        $builder = $this->getBuilder()->select('*')->from('users');
        $builder->where('email', ['x', 'y'], 'foo@bar.com');

        // An array operator is unrecognized, so it falls back to being
        // treated as an equality comparison against the array itself -
        // the point here is only that building the query never throws.
        $this->assertStringContainsString('where "email" =', $builder->toSql());
    }
}
