<?php

use Illuminate\Contracts\Support\Htmlable;

/**
 * @property-read int                         $resource_id
 * @property      null|int                    $org_id
 * @property      null|string                 $model_type
 * @property      null|int                    $model_id
 * @property      null|string                 $collection_name
 * @property      null|string                 $name
 * @property      null|string                 $file_name
 * @property      null|string                 $mime_type
 * @property      null|string                 $disk
 * @property      null|int                    $size
 * @property      null|string                 $manipulations
 * @property      null|string                 $custom_properties
 * @property      null|string                 $responsive_images
 * @property      null|int                    $order_column
 * @property      null|int                    $is_active
 * @property      null|CCarbon|\Carbon\Carbon $deleted
 * @property      null|string                 $deletedby
 *
 * @method mixed  getKey()
 * @method string getKeyName()
 * @method int    delete()
 *
 * @see CApp_Model_Interface_ResourceInterface
 * @see CApp_Model_Resource
 */
interface CModel_Resource_ResourceInterface extends CInterface_Responsable, Htmlable {
    const TYPE_OTHER = 'other';

    public function getExtensionAttribute();

    public function getDiskDriverName();

    public function getConversionsDiskDriverName(): string;

    public function getPath($conversionName = '');

    public function markAsConversionGenerated($conversionName, $generated);

    public function getImageGenerators();

    public function getResourceConversionNames();

    public function getCustomHeaders();

    public function hasResponsiveImages($conversionName = '');

    public function responsiveImages($conversionName = '');

    public function getUrl($conversionName = '');

    public function hasGeneratedConversion($conversionName);

    public function getPathRelativeToRoot(string $conversionName = ''): string;

    public function getFullUrl($conversionName = '');

    public function getTemporaryUrl(?DateTimeInterface $expiration = null, $conversionName = '', array $options = []);
}
