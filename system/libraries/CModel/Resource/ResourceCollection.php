<?php
use Illuminate\Contracts\Support\Htmlable;

/**
 * @template TKey of array-key
 * @template TModel of \CApp_Model_Resource
 *
 * @extends Collection<TKey, TModel>
 */
class CModel_Resource_ResourceCollection extends CModel_Collection implements Htmlable {
    public ?string $collectionName = null;

    public ?string $formFieldName = null;

    /**
     * @return $this
     */
    public function collectionName(string $collectionName): self {
        $this->collectionName = $collectionName;

        return $this;
    }

    /**
     * @return $this
     */
    public function formFieldName(string $formFieldName): self {
        $this->formFieldName = $formFieldName;

        return $this;
    }

    public function totalSizeInBytes(): int {
        return $this->sum('size');
    }

    public function toHtml(): string {
        return c::e(json_encode(c::old($this->formFieldName ?? $this->collectionName) ?? $this->serializeItems()));
    }

    /**
     * One entry per resource, keyed by uuid when the table has one and by primary key otherwise.
     *
     * @return CCollection
     */
    protected function serializeItems() {
        return $this->map(function (CModel_Resource_ResourceInterface $resource) {
            return [
                'name' => $resource->name,
                'file_name' => $resource->file_name,
                'uuid' => $resource->uuid,
                'preview_url' => $resource->preview_url,
                'original_url' => $resource->original_url,
                'order' => $resource->order_column,
                'custom_properties' => $resource->custom_properties,
                'extension' => $resource->extension,
                'size' => $resource->size,
            ];
        })->keyBy(function ($item, $key) {
            return $item['uuid'] ?: (string) $this->items[$key]->getKey();
        });
    }

    public function jsonSerialize(): array {
        if (CF::config('resource.use_default_collection_serialization')) {
            return parent::jsonSerialize();
        }

        if (!($this->formFieldName ?? $this->collectionName)) {
            return [];
        }

        return c::old($this->formFieldName ?? $this->collectionName) ?? $this->serializeItems()->toArray();
    }
}
