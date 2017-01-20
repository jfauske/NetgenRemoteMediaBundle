<?php

use Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value;

class NgRemoteMediaType extends eZDataType
{
	const DATA_TYPE_STRING = 'ngremotemedia';
    const FIELD_VALUE = 'data_text';

    /**
     * Construction of the class, note that the second parameter in eZDataType
     * is the actual name showed in the datatype dropdown list.
     */
    function __construct()
    {
        parent::__construct(
            self::DATA_TYPE_STRING,
            ezpI18n::tr( 'extension/ngremotemedia/datatypes', 'Remote Media' ),
            array(
                'serialize_supported' => true
            )
        );
    }

    /**
     * Validations for when a ContentObject containing this datatype
     * as an attribute is saved
     *
     * @param eZHTTPTool $http
     * @param mixed $base
     * @param object $contentObjectAttribute
     *
     * @return bool
     */
    public function validateObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        return eZInputValidator::STATE_ACCEPTED;
    }

    /**
     * Fetches the HTTP input for the content object attribute.
     *
     * @param $http
     * @param $base
     * @param $contentObjectAttribute
     *
     * @return mixed
     */
    public function fetchObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        $attributeId = $contentObjectAttribute->attribute('id');
        $data = array(
            'id' =>  $http->variable($base . '_media_id_' . $attributeId),
            'alttext' => $http->variable($base . '_alttext_' . $attributeId, '')
        );

        $container = ezpKernel::instance()->getServiceContainer();
        $provider = $container->get( 'netgen_remote_media.provider' );

        $value = $contentObjectAttribute->Content();

        $updatedValue = new Value();
        if ($data['mediaRemove'] !== 1 && $data['id'] !== 'removed') {
            $updatedValue = $value;
        }

        if ($updatedValue->metaData['alt_text'] != $data['alttext'] && $data['changeMedia'] !== 1) {
            $provider->updateResourceContext(
                $updatedValue->resourceId,
                $value->metaData['resource_type'],
                array(
                    'alt' => $data['alttext']
                )
            );
            $updatedValue->metaData['alt_text'] = $data['alttext'];
        }

        $contentObjectAttribute->setAttribute(self::FIELD_VALUE, json_encode($updatedValue));
        $this->saveExternalData($contentObjectAttribute, $updatedValue, $provider);

        return true;
    }

    protected function saveExternalData($contentObjectAttribute, $value, $provider)
    {
        $db = eZDB::instance();
        $result = $db->arrayQuery(
            "SELECT COUNT(*) as count FROM ngremotemedia_field_link WHERE field_id = " . (int)$contentObjectAttribute->attribute('id') .
                " AND version = " . (int)$contentObjectAttribute->attribute('version') .
                " AND provider = '". $provider->getIdentifier() ."'"
        );
        $count = $result[0]['count'];

        $id = $value->resourceId;
        if (empty($id)) {
            $db->query(
                "DELETE FROM ngremotemedia_field_link WHERE field_id = " . (int)$contentObjectAttribute->attribute('id') .
                " AND version = " . (int)$contentObjectAttribute->attribute('version') . " AND provider = '". $provider->getIdentifier() ."'"
            );

            return;
        }

        if ($count > 0) {
            $db->query(
                "UPDATE ngremotemedia_field_link SET resource_id = '" . $id .
                "' WHERE field_id = " . (int)$contentObjectAttribute->attribute('id') .
                " AND version = " . (int)$contentObjectAttribute->attribute('version')
            );
        } else {
            $db->query(
                "INSERT INTO ngremotemedia_field_link (contentobject_id, field_id, version, resource_id, provider)" .
                " VALUES (" . (int)$contentObjectAttribute->attribute('contentobject_id') . ", " .
                (int)$contentObjectAttribute->attribute('id') . ", " .
                (int)$contentObjectAttribute->attribute('version') . ", '" .
                $id . "', '" .
                $provider->getIdentifier() .
                "')"
            );
        }
    }

    /**
     * Deletes $objectAttribute datatype data, optionally in version $version.
     *
     * @param eZContentObjectAttribute $objectAttribute
     * @param int $version
     */
    function deleteStoredObjectAttribute( $objectAttribute, $version = null )
    {
        $container = ezpKernel::instance()->getServiceContainer();
        $provider = $container->get( 'netgen_remote_media.provider' );

        $db = eZDB::instance();

        if (!empty($version)) {
            $result = $db->arrayQuery(
                "DELETE FROM ngremotemedia_field_link WHERE field_id = " . (int)$objectAttribute->attribute('id') .
                " AND version = " . $version .
                " AND provider = '" . $provider->getIdentifier() . "'"
            );
        } else {
            $result = $db->arrayQuery(
                "DELETE FROM ngremotemedia_field_link WHERE field_id = " . (int)$objectAttribute->attribute('id') .
                " AND provider = '" . $provider->getIdentifier() . "'"
            );
        }
    }

    /**
     * Stores the datatype data to the database which is related to the
     * object attribute.
     *
     * Must return True if the value was stored correctly.
     *
     * @param $objectAttribute
     */
    function storeObjectAttribute( $objectAttribute )
    {
    }

    /**
     * Check if attribute has content
     * Called before {$attribute.content} in templates delegates to
     * `objectAttributeContent` to actuall fetch the content
     *
     * @param object $attribute
     *
     * @return bool
     */
    public function hasObjectAttributeContent($attribute)
    {
        $value = $this->objectAttributeContent($attribute);
        return !empty($value->resourceId);
    }

    /**
     * Fetch content contained in this attribute when its stored
     * This method is triggered when a template states {$attribute.content}
     *
     * @param object $attribute
     *
     * @return \Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value
     */
    public function objectAttributeContent($attribute)
    {
        $attributeValue = json_decode($attribute->attribute(self::FIELD_VALUE), true);
        $attributeValue = $attributeValue ?: array();
        $value = new Value($attributeValue);

        return $value;
    }
}

eZDataType::register(
    NgRemoteMediaType::DATA_TYPE_STRING,
    'NgRemoteMediaType'
);
