<?php

namespace Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Backend;

use Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value;
use Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\CloudinaryProvider;
use Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Item\Item;
use Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Item\Location;
use Netgen\Bundle\RemoteMediaBundle\RemoteMedia\RemoteMediaProvider;
use Netgen\ContentBrowser\Backend\BackendInterface;
use Netgen\ContentBrowser\Item\LocationInterface;

class CloudinaryBackend implements BackendInterface
{
    /** @var CloudinaryProvider */
    protected $provider;

    public function __construct(RemoteMediaProvider $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Returns the default sections available in the backend.
     *
     * @return \Netgen\ContentBrowser\Item\LocationInterface[]
     */
    public function getDefaultSections()
    {
        return [$this->buildRootLocation()];
    }

    /**
     * Loads a  location by its ID.
     *
     * @param int|string $id
     *
     * @throws \Netgen\ContentBrowser\Exceptions\NotFoundException If location does not exist
     *
     * @return \Netgen\ContentBrowser\Item\LocationInterface
     */
    public function loadLocation($id)
    {
        if ('0' === $id) {
            return $this->buildRootLocation();
        }

        return new Location($id, $id, 0);
    }

    /**
     * Loads the item by its ID.
     *
     * @param int|string $id
     *
     * @throws \Netgen\ContentBrowser\Exceptions\NotFoundException If item does not exist
     *
     * @return \Netgen\ContentBrowser\Item\ItemInterface
     */
    public function loadItem($id)
    {
        if (0 === $id) {
            return $this->buildRootItem();
        }

        $resource = $this->provider->getRemoteResource($id, 'image');

        return $this->buildItem($resource);
    }

    /**
     * Returns the locations below provided location.
     *
     * @param \Netgen\ContentBrowser\Item\LocationInterface $location
     *
     * @return \Netgen\ContentBrowser\Item\LocationInterface[]
     */
    public function getSubLocations(LocationInterface $location)
    {
        $folders = $this->provider->listFolders();

        return $this->buildLocations($folders);
    }

    /**
     * Returns the count of locations below provided location.
     *
     * @param \Netgen\ContentBrowser\Item\LocationInterface $location
     *
     * @return int
     */
    public function getSubLocationsCount(LocationInterface $location)
    {
        if (0 !== $location->getLocationId()) {
            return 0;
        }

        $folders = $this->provider->listFolders();

        return count($folders);
    }

    /**
     * Returns the location items.
     *
     * @param \Netgen\ContentBrowser\Item\LocationInterface $location
     * @param int $offset
     * @param int $limit
     *
     * @return \Netgen\ContentBrowser\Item\ItemInterface[]
     */
    public function getSubItems(LocationInterface $location, $offset = 0, $limit = 25)
    {
        if ('0' === $location->getLocationId()) {
            $resources = $this->provider->listResources($offset, $limit);
        } else {
            $resources = $this->provider->searchResources($location->getLocationId(), $limit, $offset);
        }

        return $this->buildItems($resources);
    }

    /**
     * Returns the location items count.
     *
     * @param \Netgen\ContentBrowser\Item\LocationInterface $location
     *
     * @return int
     */
    public function getSubItemsCount(LocationInterface $location)
    {
        if ('0' === $location->getLocationId()) {
            return $this->provider->countResources();
        }

        return $this->provider->countResourcesInFolder($location->getLocationId());
    }

    /**
     * Searches for items.
     *
     * @param string $searchText
     * @param int $offset
     * @param int $limit
     *
     * @return \Netgen\ContentBrowser\Item\ItemInterface[]
     */
    public function search($searchText, $offset = 0, $limit = 25)
    {
        // TODO: Implement search() method.
    }

    /**
     * Returns the count of searched items.
     *
     * @param string $searchText
     *
     * @return int
     */
    public function searchCount($searchText)
    {
        // TODO: Implement searchCount() method.
    }

    /**
     * @return \Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Item\Location
     */
    protected function buildRootLocation()
    {
        return new Location(0, 'Root folder', null);
    }

    /**
     * @return \Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Item\Item
     */
    protected function buildRootItem()
    {
        return new Item();
    }

    /**
     * Builds the item from provided tag.
     *
     * @param Value $value
     *
     * @return \Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Item\Item
     */
    protected function buildItem(Value $value)
    {
        return new Item($value);
    }

    /**
     * Builds the items from provided tags.
     *
     * @param array $resources
     *
     * @return \Netgen\ContentBrowser\Item\EzTags\Item[]
     */
    protected function buildItems(array $resources)
    {
        return array_map(
            function ($resource) {
                $value = Value::createFromCloudinaryResponse($resource);

                return $this->buildItem($value);
            },
            $resources
        );
    }

    /**
     * @param $folder
     *
     * @return \Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Item\Location
     */
    protected function buildLocation($folder)
    {
        return new Location($folder['path'], $folder['name'], 0);
    }

    /**
     * @param array $folders
     *
     * @return \Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Provider\Cloudinary\ContentBrowser\Item\Location[]
     */
    protected function buildLocations(array $folders)
    {
        return array_map(
            function ($folder) {
                return $this->buildLocation($folder);
            },
            $folders
        );
    }
}
