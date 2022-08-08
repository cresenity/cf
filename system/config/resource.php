<?php

defined('SYSPATH') or die('No direct access allowed.');

/**
 * @author Hery Kurniawan
 * @license Ittron Global Teknologi <ittron.co.id>
 *
 * @since May 2, 2019, 12:31:21 AM
 */
return [
    /*
     * The disk on which to store added files and derived images by default. Choose
     * one or more of the disks you've configured in config/filesystems.php.
     */
    'disk' => 'local',
    /*
     * The maximum file size of an item in bytes.
     * Adding a larger file will result in an exception.
     */
    'max_file_size' => 1024 * 1024 * 10,
    /*
     * This queue will be used to generate derived and responsive images.
     * Leave empty to use the default queue.
     */
    'queue_name' => '',
    /*
     * The fully qualified class name of the media model.
     */
    'resource_model' => 'CApp_Model_Resource',
    's3' => [
        /*
        * The domain that should be prepended when generating urls.
        */
        //'domain' => 'https://' . env('AWS_BUCKET') . '.s3.amazonaws.com',
    ],
    'remote' => [
        /*
         * Any extra headers that should be included when uploading media to
         * a remote disk. Even though supported headers may vary between
         * different drivers, a sensible default has been provided.
         *
         * Supported by S3: CacheControl, Expires, StorageClass,
         * ServerSideEncryption, Metadata, ACL, ContentEncoding
         */
        'extra_headers' => [
            'CacheControl' => 'max-age=604800',
        ],
    ],

    /*
     * This is the class that is responsible for naming generated files.
     */
    'file_namer' => CResources_FileNamer_DefaultFileNamer::class,
    /*
     * When urls to files get generated, this class will be called. Leave empty
     * if your files are stored locally above the site root or on s3.
     */
    'url_generator' => null,
    /*
     * The class that contains the strategy for determining a media file's path.
     */
    'path_generator' => null,
    /*
     * Medialibrary will try to optimize all converted images by removing
     * metadata and applying a little bit of compression. These are
     * the optimizers that will be used by default.
     */
    'image_optimizers' => [
        CImage_Optimizer_Jpegoptim::class => [
            '--strip-all', // this strips out all text information such as comments and EXIF data
            '--all-progressive', // this will make sure the resulting image is a progressive one
        ],
        //        CImage_Optimizer_Pngquant::class => [
        //            '--force', // required parameter for this package
        //        ],
        //        CImage_Optimizer_Optipng::class => [
        //            '-i0', // this will result in a non-interlaced, progressive scanned image
        //            '-o2', // this set the optimization level to two (multiple IDAT compression trials)
        //            '-quiet', // required parameter for this package
        //        ],
        //        CImage_Optimizer_Svgo::class => [
        //            '--disable=cleanupIDs', // disabling because it is known to cause troubles
        //        ],
        //        CImage_Optimizer_Gifsicle::class => [
        //            '-b', // required parameter for this package
        //            '-O3', // this produces the slowest but best results
        //        ],
    ],
    /*
     * These generators will be used to create an image of media files.
     */
    'image_generators' => [
        CResources_ImageGenerator_FileType_ImageType::class,
        //        CResources_ImageGenerator_FileType_WebpType::class,
        //        CResources_ImageGenerator_FileType_PdfType::class,
        //        CResources_ImageGenerator_FileType_SvgType::class,
        //        CResources_ImageGenerator_FileType_VideoType::class,
    ],
    /*
     * The engine that should perform the image conversions.
     * Should be either `gd` or `imagick`.
     */
    'image_driver' => 'gd',
    /*
     * FFMPEG & FFProbe binaries paths, only used if you try to generate video
     * thumbnails and have installed the php-ffmpeg/php-ffmpeg composer
     * dependency.
     */
    'ffmpeg_path' => '/usr/bin/ffmpeg',
    'ffprobe_path' => '/usr/bin/ffprobe',
    /*
     * The path where to store temporary files while performing image conversions.
     * If set to null, DOCROOT.'temp/resource/temp/ will be used.
     */
    'temporary_directory_path' => null,
    /*
     * Here you can override the class names of the jobs used by this package. Make sure
     * your custom jobs extend the ones provided by the package.
     */
    'task_queue' => [
        'perform_conversions' => CResources_TaskQueue_PerformConversions::class,
        'generate_responsive_images' => CResources_TaskQueue_GenerateResponsiveImage::class,
    ],
    'remote' => [
        /*
         * Any extra headers that should be included when uploading resource to
         * a remote disk. Even though supported headers may vary between
         * different drivers, a sensible default has been provided.
         *
         * Supported by S3: CacheControl, Expires, StorageClass,
         * ServerSideEncryption, Metadata, ACL, ContentEncoding
         */
        'extra_headers' => [
            'CacheControl' => 'max-age=604800',
        ],
    ],
    'responsive_images' => [
        /*
         * This class is responsible for calculating the target widths of the responsive
         * images. By default we optimize for filesize and create variations that each are 30%
         * smaller than the previous one. More info in the documentation.
         *
         * https://docs.spatie.be/laravel-medialibrary/v9/advanced-usage/generating-responsive-images
         */
        'width_calculator' => CResources_ResponsiveImage_WidthCalculator_FileSizeOptimizedWidthCalculator::class,

        /*
         * By default rendering media to a responsive image will add some javascript and a tiny placeholder.
         * This ensures that the browser can already determine the correct layout.
         */
        'use_tiny_placeholders' => true,

        /*
         * This class will generate the tiny placeholder used for progressive image loading. By default
         * the media library will use a tiny blurred jpg image.
         */
        'tiny_placeholder_generator' => CResources_ResponsiveImage_TinyPlaceholderGenerator_Blurred::class,
    ],
    /*
     * When using the addMediaFromUrl method you may want to replace the default downloader.
     * This is particularly useful when the url of the image is behind a firewall and
     * need to add additional flags, possibly using curl.
     */
    'media_downloader' => CResources_Downloader_DefaultDownloader::class,
    /*
     * When converting Resource instances to response the resource library will add
     * a `loading` attribute to the `img` tag. Here you can set the default
     * value of that attribute.
     *
     * Possible values: 'lazy', 'eager', 'auto' or null if you don't want to set any loading instruction.
     *
     * More info: https://css-tricks.com/native-lazy-loading/
     */
    'default_loading_attribute_value' => null,
];
