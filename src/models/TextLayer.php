<?php

namespace alps\sharepreviews\models;

use alps\sharepreviews\behaviors\HasColors;
use alps\sharepreviews\SharePreviews;
use Craft;
use Imagine\Gd\Font;
use Imagine\Image\ImageInterface;

class TextLayer extends AbstractRectangleLayer
{
    public string $content = '{{ entry.title }}';

    public string $fontFamily = 'roboto';

    public string $fontVariant = 'regular';

    public int $maxFontSize = 60;

    public bool $shrinkToFit = true;

    public function getTitle(): string
    {
        return Craft::t('share-previews', 'Text');
    }

    public function isAvailableInTemplateEditor(): bool
    {
        return true;
    }

    public function setFontFamilyWithVariant(array $familyWithVariant): self
    {
        $familyWithVariant[1] = $familyWithVariant[1] ?? $this->fontVariant;

        [$familyId, $variantId] = $familyWithVariant;

        $fontsService = SharePreviews::getInstance()->fonts;

        $family = $fontsService->getFontFamily($familyId) ?? $fontsService->getDefaultFontFamily();

        $variant = $family->hasVariant($variantId)
            ? $family->getVariant($variantId)
            : $family->getDefaultVariant();

        $this->fontFamily = $variant->family->id;
        $this->fontVariant = $variant->id;

        return $this;
    }

    public function apply(ImageInterface $image): ImageInterface
    {
        if (empty($this->content)) {
            return $image;
        }

        [$font, $content] = $this->getFont();

        $box = $font->box($content);

        $point = $this->getAlignedOriginPoint($box->getWidth(), $box->getHeight());

        $image->draw()->text($content, $font, $point);

        return $image;
    }

    private function getFont(): array
    {
        [$maxWidth, $maxHeight] = $this->getCanvasDimensions();

        $fontFile = $this->getFontFile();
        $content = $this->content;
        $maxFontSize = $this->maxFontSize;
        $color = $this->toColor($this->color);
        $shrinkToFit = $this->shrinkToFit;

        if ($shrinkToFit) {
            $maxFontSize += 5;

            do {
                $maxFontSize -= 5;
                $font = new Font($fontFile, $maxFontSize, $color);
                $box = $font->box(wordwrap($content, 1));

                if ($maxFontSize <= 10) {
                    break;
                }
            } while ($box->getWidth() > $maxWidth);
        }

        $maxFontSize += 5;

        do {
            $maxFontSize -= 5;

            $font = new Font($fontFile, $maxFontSize, $color);

            $wrapAfter = 110;

            do {
                $wrapAfter -= 10;
                $box = $font->box(wordwrap($content, $wrapAfter));

                if ($wrapAfter <= 10) {
                    break;
                }
            } while ($box->getWidth() > $maxWidth);

            if (! $shrinkToFit || $maxFontSize <= 10) {
                break;
            }
        } while ($box->getHeight() > $maxHeight);

        return [$font, wordwrap($content, $wrapAfter)];
    }

    private function getFontFile(): string
    {
        $fonts = SharePreviews::getInstance()->fonts;

        $family = $fonts->getFontFamily($this->fontFamily) ?? $fonts->getDefaultFontFamily();

        return $family
            ->getVariant($this->fontVariant)
            ->getPathToFontFile();
    }

    protected function getPropertiesWithVariables(): array
    {
        return [
            'content',
        ];
    }

    public function attributes()
    {
        return array_merge(
            parent::attributes(),
            $this->getColorAttributes(),
        );
    }

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            ['class' => HasColors::class, 'properties' => ['color']],
        ]);
    }
}
