<?php

class CGeo_Spatial_Type_GeometryCollection extends CGeo_Spatial_Type_Geometry implements ArrayAccess {
    /**
     * @var CCollection<int, Geometry>
     */
    protected CCollection $geometries;

    /**
     * @var string
     */
    protected string $collectionOf = CGeo_Spatial_Type_Geometry::class;

    protected int $minimumGeometries = 0;

    /**
     * @param Collection<int, Geometry>|array<int, Geometry> $geometries
     * @param int                                            $srid
     *
     * @throws InvalidArgumentException
     */
    public function __construct($geometries, int $srid = 0) {
        if (is_array($geometries)) {
            $geometries = c::collect($geometries);
        }

        $this->geometries = $geometries;
        $this->srid = $srid;

        $this->validateGeometriesType();
        $this->validateGeometriesCount();
    }

    public function toWkt(): string {
        $wktData = $this->getWktData();

        return "GEOMETRYCOLLECTION({$wktData})";
    }

    public function getWktData(): string {
        return $this->geometries
            ->map(static function (CGeo_Spatial_Type_Geometry $geometry): string {
                return $geometry->toWkt();
            })
            ->join(', ');
    }

    /**
     * @return array<mixed>
     */
    public function getCoordinates(): array {
        return $this->geometries
            ->map(static function (CGeo_Spatial_Type_Geometry $geometry): array {
                return $geometry->getCoordinates();
            })
            ->all();
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array {
        if ($this->isExtended()) {
            return parent::toArray();
        }

        return [
            'type' => c::classBasename(static::class),
            'geometries' => $this->geometries->map(static function (CGeo_Spatial_Type_Geometry $geometry): array {
                return $geometry->toArray();
            }),
        ];
    }

    /**
     * @return CCollection<int, Geometry>
     */
    public function getGeometries(): CCollection {
        return new CCollection($this->geometries->all());
    }

    /**
     * @param int $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool {
        return isset($this->geometries[$offset]);
    }

    /**
     * @param int $offset
     *
     * @return Geometry
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset): CGeo_Spatial_Type_Geometry {
        // @phpstan-ignore-next-line
        return $this->geometries[$offset];
    }

    /**
     * @param int      $offset
     * @param Geometry $value
     */
    public function offsetSet($offset, $value): void {
        $this->geometries[$offset] = $value;
        $this->validateGeometriesType();
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset): void {
        $this->geometries->splice($offset, 1);
        $this->validateGeometriesCount();
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateGeometriesCount(): void {
        $geometriesCount = $this->geometries->count();
        if ($geometriesCount < $this->minimumGeometries) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s must contain at least %s %s',
                    static::class,
                    $this->minimumGeometries,
                    cstr::plural('entries', $geometriesCount)
                )
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function validateGeometriesType(): void {
        $this->geometries->each(function ($geometry): void {
            /** @var mixed $geometry */
            if (!is_object($geometry) || !($geometry instanceof $this->collectionOf)) {
                throw new InvalidArgumentException(
                    sprintf('%s must be a collection of %s', static::class, $this->collectionOf)
                );
            }
        });
    }

    /**
     * Checks whether the class is used directly or via a sub-class.
     *
     * @return bool
     */
    private function isExtended(): bool {
        return is_subclass_of(static::class, self::class);
    }
}
