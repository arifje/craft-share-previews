<?php

namespace alps\sharepreviews\models;

use alps\sharepreviews\SharePreviews;
use Craft;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\Entry;
use craft\fields\Assets;

/**
 * @property int $assetId
 */
class AssetLayer extends ImageLayer
{
    private ?int $assetId = null;

    public ?int $fieldId = null;

    public ?string $expression = null;

    private ?Asset $asset = null;

    public function getTitle(): string
    {
        return Craft::t('share-previews', 'Asset');
    }

    public function isAvailableInTemplateEditor(): bool
    {
        return true;
    }

    public function getPropertiesWithVariables(): array
    {
        return array_merge(parent::getPropertiesWithVariables(), [
            'expression',
        ]);
    }

    public function willRender(array $vars)
    {
        parent::willRender($vars);

        $asset = null;
        $entry = $vars['entry'] ?? null;

        if ($this->expression !== null) {
            $asset = $this->fetchAssetFromExpression($this->expression);
        }

        if (!$asset && $this->fieldId !== null && $entry instanceof Entry) {
            $asset = $this->fetchAssetFromEntryField($entry, $this->fieldId);
        }

        if (! $asset || ! $asset instanceof Asset) {
            return;
        }

        $this->assetId = $asset->id;
        $this->asset = $asset;
    }

    private function fetchAssetFromEntryField(Entry $entry, int $fieldId): ?Asset
    {
        $field = Craft::$app->getFields()->getFieldById($fieldId);

        if (! $field) {
            return null;
        }

        /** @var AssetQuery|null $query */
        $query = $entry->getFieldValues([$field->handle])[$field->handle] ?? null;

        if (! $query) {
            return null;
        }

        return $query->one();
    }

    private function fetchAssetFromExpression(string $expression = null): ?Asset
    {
        $potentialId = (int) $expression;

        if ($potentialId < 1) {
            return null;
        }

        return Asset::findOne($potentialId);
    }

    public function setAssetId($assetId): self
    {
        $this->asset = null;

        if ($assetId instanceof Asset) {
            $this->asset = $assetId;
            $this->assetId = $assetId->id;

            return $this;
        }

        if (is_array($assetId)) {
            $assetId = $assetId[0] ?? null;
        }

        $assetId = (int) $assetId;

        $this->assetId = $assetId > 0 ? $assetId : null;

        return $this;
    }

    public function getAssetId(): ?int
    {
        return $this->assetId;
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'assetId',
        ]);
    }

    public function getAsset(): ?Asset
    {
        if (! $this->assetId) {
            return null;
        }

        if ($this->asset && $this->asset->id === $this->assetId) {
            return $this->asset;
        }

        return $this->asset = Asset::findOne($this->assetId);
    }

    protected function getPath(): ?string
    {
        $asset = $this->getAsset();

        if (! $asset) {
            return null;
        }

        return $asset->getTransformSource();
    }

    public function getAvailableAssetFieldsAsOptions(bool $optional = false): array
    {
        $fields = Craft::$app->getFields()->getFieldsByElementType(Entry::class);

        $fields = array_filter($fields, function ($field) {
            return $field instanceof Assets;
        });

        $fields = array_values($fields);

        $options = array_map(function (Assets $field) {
            return [
                'value' => $field->id,
                'label' => sprintf('%s [%s]', $field->name, $field->handle),
            ];
        }, $fields);

        $options = SharePreviews::getInstance()->helpers->sortOptions($options);

        if (! $optional) {
            return $options;
        }

        array_unshift($options, [
            'value' => null,
            'label' => Craft::t('share-previews', 'No Replacement'),
        ]);

        return $options;
    }
}
