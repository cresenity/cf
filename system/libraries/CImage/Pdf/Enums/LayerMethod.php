<?php


class CImage_Pdf_Enums_LayerMethod {
    const NONE = -1;

    const UNDEFINED = Imagick::LAYERMETHOD_UNDEFINED;

    const COALESCE = Imagick::LAYERMETHOD_COALESCE;

    const COMPAREANY = Imagick::LAYERMETHOD_COMPAREANY;

    const COMPARECLEAR = Imagick::LAYERMETHOD_COMPARECLEAR;

    const COMPAREOVERLAY = Imagick::LAYERMETHOD_COMPAREOVERLAY;

    const DISPOSE = Imagick::LAYERMETHOD_DISPOSE;

    const OPTIMIZE = Imagick::LAYERMETHOD_OPTIMIZE;

    const OPTIMIZEPLUS = Imagick::LAYERMETHOD_OPTIMIZEPLUS;

    const OPTIMIZETRANS = Imagick::LAYERMETHOD_OPTIMIZETRANS;

    const COMPOSITE = Imagick::LAYERMETHOD_COMPOSITE;

    const OPTIMIZEIMAGE = Imagick::LAYERMETHOD_OPTIMIZEIMAGE;

    const REMOVEDUPS = Imagick::LAYERMETHOD_REMOVEDUPS;

    const REMOVEZERO = Imagick::LAYERMETHOD_REMOVEZERO;

    const MERGE = Imagick::LAYERMETHOD_MERGE;

    const FLATTEN = Imagick::LAYERMETHOD_FLATTEN;

    const MOSAIC = Imagick::LAYERMETHOD_MOSAIC;

    const TRIMBOUNDS = Imagick::LAYERMETHOD_TRIMBOUNDS;

    public static function isValid(int $value): bool {
        return in_array($value, [
            self::NONE,
            self::UNDEFINED,
            self::COALESCE,
            self::COMPAREANY,
            self::COMPARECLEAR,
            self::COMPAREOVERLAY,
            self::DISPOSE,
            self::OPTIMIZE,
            self::OPTIMIZEPLUS,
            self::OPTIMIZETRANS,
            self::COMPOSITE,
            self::OPTIMIZEIMAGE,
            self::REMOVEDUPS,
            self::REMOVEZERO,
            self::MERGE,
            self::FLATTEN,
            self::MOSAIC,
            self::TRIMBOUNDS,
        ]);
    }
}
