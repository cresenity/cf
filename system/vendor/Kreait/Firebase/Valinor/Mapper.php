<?php

declare(strict_types=1);

namespace Kreait\Firebase\Valinor;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use Kreait\Firebase\Exception\InvalidArgumentException;
use Kreait\Firebase\Valinor\Converter\SnakeCaseToCamelCaseConverter;

/**
 * @internal
 */
final class Mapper
{
    private MapperBuilder $mapperBuilder;

    /**
     * @var mixed
     */
    private $cache;


    public function __construct($cache = null, ?MapperBuilder $builder = null)
    {
        $this->cache = $cache;
        $builder ??= new MapperBuilder();

        if ($cache !== null) {
            $builder = $builder->withCache($this->cache);
        }

        $this->mapperBuilder = $builder;
    }

    public function withConverter(callable $converter): self
    {
        $mapperBuilder = $this->mapperBuilder->registerConverter($converter); // @phpstan-ignore-line argument.type

        return new self($this->cache, $mapperBuilder);
    }

    public function snakeToCamelCase(): self
    {
        return $this->withConverter(new SnakeCaseToCamelCaseConverter());
    }

    public function allowSuperfluousKeys(): self
    {
        return new self($this->cache, $this->mapperBuilder->allowSuperfluousKeys());
    }

    /**
     * @template T
     * @param class-string<T> $signature
     *
     * @throws InvalidArgumentException
     *
     * @return T
     */
    public function map(string $signature, $source)
    {
        try {
            return $this->mapperBuilder->mapper()->map($signature, $source);
        } catch (MappingError $e) {
            $errorMessages = [];
            foreach ($e->messages() as $message) {
                $errorMessages[] = '- `'.$message->path().'`: '.$message->toString();
            }

            $message = "Could not map type `$signature`:".PHP_EOL;
            $message .= implode(PHP_EOL, $errorMessages);

            throw new InvalidArgumentException($message, 0, $e);
        }
    }
}
