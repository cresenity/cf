<?php

defined('SYSPATH') or die('No direct access allowed.');

class CGeo_Model_Address implements CGeo_Interface_LocationInterface {
    /**
     * @var null|Coordinates
     */
    private $coordinates;

    /**
     * @var null|CGeo_Model_Bounds
     */
    private $bounds;

    /**
     * @var null|string|int
     */
    private $streetNumber;

    /**
     * @var null|string
     */
    private $streetName;

    /**
     * @var null|string
     */
    private $subLocality;

    /**
     * @var null|string
     */
    private $locality;

    /**
     * @var null|string
     */
    private $postalCode;

    /**
     * @var CGeo_Model_AdminLevelCollection
     */
    private $adminLevels;

    /**
     * @var null|CGeo_Model_Country
     */
    private $country;

    /**
     * @var null|string
     */
    private $timezone;

    /**
     * @var string
     */
    private $providedBy;

    /**
     * @param string                          $providedBy
     * @param CGeo_Model_AdminLevelCollection $adminLevels
     * @param null|CGeo_Model_Coordinates     $coordinates
     * @param null|CGeo_Model_Bounds          $bounds
     * @param null|string                     $streetNumber
     * @param null|string                     $streetName
     * @param null|string                     $postalCode
     * @param null|string                     $locality
     * @param null|string                     $subLocality
     * @param null|CGeo_Model_Country         $country
     * @param null|string                     $timezone
     */
    public function __construct($providedBy, CGeo_Model_AdminLevelCollection $adminLevels, ?CGeo_Model_Coordinates $coordinates = null, ?CGeo_Model_Bounds $bounds = null, $streetNumber = null, $streetName = null, $postalCode = null, $locality = null, $subLocality = null, ?CGeo_Model_Country $country = null, $timezone = null) {
        $this->providedBy = $providedBy;
        $this->adminLevels = $adminLevels;
        $this->coordinates = $coordinates;
        $this->bounds = $bounds;
        $this->streetNumber = $streetNumber;
        $this->streetName = $streetName;
        $this->postalCode = $postalCode;
        $this->locality = $locality;
        $this->subLocality = $subLocality;
        $this->country = $country;
        $this->timezone = $timezone;
    }

    /**
     * @return string
     */
    public function getProvidedBy() {
        return $this->providedBy;
    }

    /**
     * @inheritdoc
     */
    public function getCoordinates() {
        return $this->coordinates;
    }

    /**
     * @inheritdoc
     */
    public function getBounds() {
        return $this->bounds;
    }

    /**
     * @inheritdoc
     */
    public function getStreetNumber() {
        return $this->streetNumber;
    }

    /**
     * @inheritdoc
     */
    public function getStreetName() {
        return $this->streetName;
    }

    /**
     * @inheritdoc
     */
    public function getLocality() {
        return $this->locality;
    }

    /**
     * @inheritdoc
     */
    public function getPostalCode() {
        return $this->postalCode;
    }

    /**
     * @inheritdoc
     */
    public function getSubLocality() {
        return $this->subLocality;
    }

    /**
     * @inheritdoc
     */
    public function getAdminLevels() {
        return $this->adminLevels;
    }

    /**
     * @inheritdoc
     */
    public function getCountry() {
        return $this->country;
    }

    /**
     * @inheritdoc
     */
    public function getTimezone() {
        return $this->timezone;
    }

    /**
     * Create an Address with an array. Useful for testing.
     *
     * @param array $data
     *
     * @return static
     */
    public static function createFromArray(array $data) {
        $defaults = [
            'adminLevels' => [],
            'providedBy' => 'n/a',
            'latitude' => null,
            'longitude' => null,
            'bounds' => [
                'south' => null,
                'west' => null,
                'north' => null,
                'east' => null,
            ],
            'streetNumber' => null,
            'streetName' => null,
            'locality' => null,
            'postalCode' => null,
            'subLocality' => null,
            'country' => null,
            'countryCode' => null,
            'timezone' => null,
        ];
        $data = array_merge($defaults, $data);
        $adminLevels = [];
        foreach ($data['adminLevels'] as $adminLevel) {
            if (empty($adminLevel['level'])) {
                continue;
            }
            $name = isset($adminLevel['name']) ? $adminLevel['name'] : (isset($adminLevel['code']) ? $adminLevel['code'] : null);
            if (empty($name)) {
                continue;
            }
            $adminLevels[] = new CGeo_Model_AdminLevel($adminLevel['level'], $name, isset($adminLevel['code']) ? $adminLevel['code'] : null);
        }

        return new static(
            $data['providedBy'],
            new CGeo_Model_AdminLevelCollection($adminLevels),
            self::createCoordinates(
                $data['latitude'],
                $data['longitude']
            ),
            self::createBounds(
                $data['bounds']['south'],
                $data['bounds']['west'],
                $data['bounds']['north'],
                $data['bounds']['east']
            ),
            $data['streetNumber'],
            $data['streetName'],
            $data['postalCode'],
            $data['locality'],
            $data['subLocality'],
            self::createCountry($data['country'], $data['countryCode']),
            $data['timezone']
        );
    }

    /**
     * @param float $latitude
     * @param float $longitude
     *
     * @return null|Coordinates
     */
    private static function createCoordinates($latitude, $longitude) {
        if (null === $latitude || null === $longitude) {
            return null;
        }

        return new CGeo_Model_Coordinates($latitude, $longitude);
    }

    /**
     * @param null|string $name
     * @param null|string $code
     *
     * @return null|Country
     */
    private static function createCountry($name, $code) {
        if (null === $name && null === $code) {
            return null;
        }

        return new CGeo_Model_Country($name, $code);
    }

    /**
     * @param float $south
     * @param float $west
     * @param float $north
     * @param mixed $east
     *
     * @return null|CGeo_Model_Bounds
     */
    private static function createBounds($south, $west, $north, $east) {
        if (null === $south || null === $west || null === $north || null === $east) {
            return null;
        }

        return new CGeo_Model_Bounds($south, $west, $north, $east);
    }

    /**
     * @inheritdoc
     */
    public function toArray() {
        $adminLevels = [];
        foreach ($this->adminLevels as $adminLevel) {
            $adminLevels[$adminLevel->getLevel()] = [
                'name' => $adminLevel->getName(),
                'code' => $adminLevel->getCode(),
                'level' => $adminLevel->getLevel(),
            ];
        }
        $lat = null;
        $lon = null;
        if (null !== $coordinates = $this->getCoordinates()) {
            $lat = $coordinates->getLatitude();
            $lon = $coordinates->getLongitude();
        }
        $countryName = null;
        $countryCode = null;
        if (null !== $country = $this->getCountry()) {
            $countryName = $country->getName();
            $countryCode = $country->getCode();
        }
        $noBounds = [
            'south' => null,
            'west' => null,
            'north' => null,
            'east' => null,
        ];

        return [
            'providedBy' => $this->providedBy,
            'latitude' => $lat,
            'longitude' => $lon,
            'bounds' => null !== $this->bounds ? $this->bounds->toArray() : $noBounds,
            'streetNumber' => $this->streetNumber,
            'streetName' => $this->streetName,
            'postalCode' => $this->postalCode,
            'locality' => $this->locality,
            'subLocality' => $this->subLocality,
            'adminLevels' => $adminLevels,
            'country' => $countryName,
            'countryCode' => $countryCode,
            'timezone' => $this->timezone,
        ];
    }
}
