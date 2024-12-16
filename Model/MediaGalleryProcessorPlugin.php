<?php
/**
 * SyncEngine
 * Copyright (C) SyncEngine
 *
 * NOTICE OF LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see http://opensource.org/licenses/gpl-3.0.html
 *
 * @category SyncEngine
 * @package SyncEngine_Connector
 * @copyright Copyright (c) SyncEngine
 * @license http://opensource.org/licenses/gpl-3.0.html GNU General Public License,version 3 (GPL-3.0)
 * @author Jory Hogeveen <info@syncengine.io>
 *
 * Concept of overwriting the MediaGalleryProcessor as a plugin taken from: Orangecat_MediaGalleryProcessor
 */

namespace SyncEngine\Connector\Model;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Gallery\DeleteValidator;
use Magento\Catalog\Model\Product\Gallery\Processor;
use Magento\Catalog\Model\Product\Media\ConfigInterface;
use Magento\Catalog\Model\ProductRepository\MediaGalleryProcessor;
use Magento\Framework\Api\Data\ImageContentInterface;
use Magento\Framework\Api\Data\ImageContentInterfaceFactory;
use Magento\Framework\Api\ImageContentFactory;
use Magento\Framework\Api\ImageProcessorInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Client\Curl;
use SyncEngine\Connector\Helper\Data;

class MediaGalleryProcessorPlugin extends MediaGalleryProcessor
{
    private Data $syncengineData;
    private ObjectManager $objectManager;
    private ConfigInterface $mediaConfig;
    private Filesystem $filesystem;

    public function __construct(
        Processor $processor,
        ImageContentInterfaceFactory $contentFactory,
        ImageProcessorInterface $imageProcessor,
        ?DeleteValidator $deleteValidator = null
    ) {
        // @todo Fixme, could not load with autowiring for some reason.
        $this->objectManager = ObjectManager::getInstance();
        $this->syncengineData = $this->objectManager->get(Data::class);

        parent::__construct($processor, $contentFactory, $imageProcessor, $deleteValidator);
    }

    public function _getProductMediaPath( $path ): string
    {
        if ( ! isset( $this->mediaConfig ) ) {
            $this->mediaConfig = $this->objectManager->get( ConfigInterface::class );
        }
        if ( ! isset( $this->filesystem ) ) {
            $this->filesystem = $this->objectManager->get(Filesystem::class);
        }

        $dir = $this->filesystem->getDirectoryRead( DirectoryList::MEDIA );
        return $dir->getAbsolutePath( $this->mediaConfig->getMediaPath( $path ) );
    }

    /**
     * @return ImageContentInterface
     */
    public function _createImageContent()
    {
        $imageFactory = $this->objectManager->create( ImageContentFactory::class );
        return $imageFactory->create();
    }

    /**
     * @param $url
     *
     * @return ImageContentInterface|null
     */
    public function fetchImageContentFromUrl( $url )
    {
        if ( ! $this->syncengineData->isMediaGalleryApiPassUrlEnabled() ) {
            return null;
        }

        /** @var Curl $curl */
        $curl = $this->objectManager->create(Curl::class);
        $curl->get( $url );

        $name     = pathinfo( $url, PATHINFO_FILENAME );
        $image    = base64_encode( $curl->getBody() );
        $headers  = $curl->getHeaders();
        $mimeType = $headers['Content-Type'] ?? $headers['content-type'] ?? $headers['Content_Type'] ?? $headers['content_type'];

        return $this->_createImageContent()->setType( $mimeType )->setName( $name )->setBase64EncodedData( $image );
    }

    /**
     * @param $path
     *
     * @return ImageContentInterface|null
     */
    public function fetchImageContentFromPath( $path )
    {
        if ( ! $this->syncengineData->isMediaGalleryApiPassPathEnabled() ) {
            return null;
        }

        $base = $this->syncengineData->getMediaGalleryApiBasePath();
        $base = rtrim( $base, '/' ) . '/';

        $file = $base . ltrim( $path, '/' );

        $name     = pathinfo( $file, PATHINFO_FILENAME );
        $image    = base64_encode( file_get_contents( $file ) );
        $mimeType = mime_content_type( $file );

        return $this->_createImageContent()->setType( $mimeType )->setName( $name )->setBase64EncodedData( $image );
    }

    /**
     * @param $path
     *
     * @return ImageContentInterface|null|string
     */
    public function fetchImageContent( $path_or_url )
    {
        try {
            if ( ! pathinfo( $path_or_url, PATHINFO_EXTENSION ) ) {
                return $path_or_url;
            }
        } catch ( \Exception $e ) {
            return $path_or_url;
        }

        if ( ! str_starts_with( $path_or_url, 'http' ) ) {
            return $this->fetchImageContentFromPath( $path_or_url );
        }

        return $this->fetchImageContentFromUrl( $path_or_url );
    }

    /**
     * @throws \Exception
     *
     * @param ProductAttributeMediaGalleryEntryInterface $entry
     */
    public function fetchProductImageContent( $entry ): ?string
    {
        $existingEntryContent = $entry->getContent();

        if ( $existingEntryContent instanceof ImageContentInterface ) {
            $existingBase64image = $existingEntryContent->getBase64EncodedData();
        } elseif ( is_scalar( $existingEntryContent ) ) {
            throw new \Exception( 'SyncEngine: Existing content: ' . substr( (string) $existingEntryContent, 0, 20 ) );
        }

        if ( empty( $existingBase64image ) ) {
            $path = $this->_getProductMediaPath( $entry->getFile() );
            $existingBase64image = base64_encode( file_get_contents( $path ) );
        }

        if ( empty( $existingBase64image ) ) {
            throw new \Exception( 'SyncEngine: Could not load existing image content: ' . $path );
        }

        return $existingBase64image;
    }

    public function aroundProcessMediaGallery(
        MediaGalleryProcessor $subject,
        \Closure $proceed,
        ProductInterface $product,
        $mediaGalleryEntries
    ) {
        if ( $this->syncengineData?->isMediaGalleryApiEnabled() ) {
            foreach ($mediaGalleryEntries as $k => $entry) {
                $base64image = $entry['content']['data'][ImageContentInterface::BASE64_ENCODED_DATA] ?? null;

                if ( '' !== $base64image || empty( $entry['file'] ) ) {
                    continue;
                }

                $imageContent = $this->fetchImageContent( $entry['file'] );

                if ( $imageContent instanceof ImageContentInterface ) {
                    $entry['content']['data'] = [
                        ImageContentInterface::BASE64_ENCODED_DATA => $imageContent->getBase64EncodedData(),
                        ImageContentInterface::TYPE => $imageContent->getType(),
                        ImageContentInterface::NAME => $imageContent->getName(),
                    ];
                } else {
                    unset( $entry['content'] );
                    unset( $entry['file'] );
                }

                $mediaGalleryEntries[$k] = $entry;
            }
        }

        if ( $this->syncengineData->isMediaGalleryApiSkipUnchangedEnabled() ) {

            $existingMediaGallery = $product->getMediaGalleryEntries();

            if ( ! empty( $existingMediaGallery ) ) {

                $existingById = [];
                $existingBase64images = [];

                foreach ( $existingMediaGallery as $existingMediaGalleryItem ) {
                    $existingById[ $existingMediaGalleryItem->getId() ] = $existingMediaGalleryItem;
                }

                $getExistingImageContent = function( $id = null ) use ( &$existingById, &$existingBase64images ) {
                    if ( ! empty( $existingBase64images[ $id ] ) ) {
                        return $existingBase64images[ $id ];
                    }
                    if ( empty( $existingById[ $id ] ) ) {
                        return null;
                    }
                    $existingBase64images[ $id ] = $this->fetchProductImageContent( $existingById[ $id ] );
                    return $existingBase64images[ $id ];
                };

                $imageContentExists = function( $content, $id = null ) use ( &$existingById, &$existingBase64images, $getExistingImageContent ) {
                    if ( $id ) {
                        return $content === $getExistingImageContent( $id ) ? $id : false;
                    }
                    foreach ( $existingById as $id => $existingImage ) {
                        if ( empty( $existingBase64images[ $id ] ) ) {
                            $getExistingImageContent( $id );
                        }
                        if ( $content === $existingBase64images[ $id ] ?? null ) {
                            return $id;
                        }
                    }
                    return false;
                };

                foreach ( $mediaGalleryEntries as $k => $entry ) {
                    $id = $entry['value_id'] ?? null;

                    if ( empty( $entry['content'] ) ) {
                        continue;
                    }

                    $base64image = $entry['content']['data'][ImageContentInterface::BASE64_ENCODED_DATA] ?? null;

                    if ( empty( $base64image ) ) {
                        continue;
                    }

                    $exists = $imageContentExists( $base64image, $id );

                    if ( $exists ) {
                        unset( $entry['content'] ); // Remove base64 content.
                        $entry['file'] = $existingById[ $exists ]->getFile();
                        $entry['value_id'] = $exists;
                        $mediaGalleryEntries[ $k ] = $entry;
                    }
                }
            }
        }

        $returnValue = $proceed($product, $mediaGalleryEntries);
        return $returnValue;
    }

}
