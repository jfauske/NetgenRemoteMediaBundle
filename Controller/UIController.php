<?php

namespace Netgen\Bundle\RemoteMediaBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use Netgen\Bundle\RemoteMediaBundle\RemoteMedia\Helper;
use Netgen\Bundle\RemoteMediaBundle\RemoteMedia\RemoteMediaProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Cloudinary\Api\NotFound;
use Symfony\Component\Templating\EngineInterface;

class UIController extends Controller
{
    protected $provider;

    protected $helper;

    protected $templating;

    public function __construct(RemoteMediaProviderInterface $provider, Helper $helper, EngineInterface $templating)
    {
        $this->provider = $provider;
        $this->helper = $helper;
        $this->templating = $templating;
    }

    public function uploadFileAction(Request $request)
    {
        $file = $request->files->get('file', '');
        $fieldId = $request->get('AttributeID', '');
        $contentVersionId = $request->get('ContentObjectVersion', '');

        if (empty($file) || empty($fieldId) || empty($contentVersionId)) {
            return new JsonResponse(
                array(
                    'ok' => false,
                    'error' => 'Not all arguments where set (file, attribute Id, content version)',
                ),
                400
            );
        }

        $field = $this->helper->loadField($fieldId, $contentVersionId);

        if ($field->type !== 'ngremotemedia') {
            return new JsonResponse(
                array(
                    'error_text' => 'Field is of the wrong field type',
                )
            );
        }

        $fileUri = $file->getRealPath();
        $folder = $fieldId.'/'.$contentVersionId;

        // clean up file name
        $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $clean = preg_replace("/[^\p{L}|\p{N}]+/u", '_', $fileName);
        $cleanFileName = preg_replace("/[\p{Z}]{2,}/u", '_', $clean);
        $cleanFileName = rtrim($cleanFileName, '_');

        $options = array(
            'public_id' => $cleanFileName.'/'.$folder,
            'overwrite' => true,
            'context' => array(
                'alt' => '',
                'caption' => '',
            ),
            'resource_type' => 'auto'
        );

        $result = $this->provider->upload($fileUri, $options);

        $value = $this->provider->getValueFromResponse($result);
        $this->helper->updateField($value, $fieldId, $contentVersionId);
        $availableFormats = $this->helper->loadSPIFieldAvailableFormats($field);

        $content = $this->templating->render(
            'NetgenRemoteMediaBundle:ezexceed/edit:ngremotemedia.html.twig',
            array(
                'value' => $value,
                'fieldId' => $fieldId,
                'availableFormats' => $availableFormats
            )
        );

        $result['id'] = $result['public_id'];
        $result['scalesTo'] = array(
            'quality' => 100,
            'ending' => $result['format'],
        );

        return new JsonResponse(
            array(
                'error_text' => '',
                'content' => array(
                    'media' => $result,
                    'toScale' == ''/*$handler->attribute('toscale')*/,
                    'content' => $content,
                    'ok' => true,
                ),
            ),
            200
        );
    }

    public function fetchAction(Request $request, $fieldId, $contentVersionId)
    {
        $field = $this->helper->loadField($fieldId, $contentVersionId);
        $availableFormats = $this->helper->loadSPIFieldAvailableFormats($field);

        /** @var \Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value $value */
        $value = $field->value;
        $variations = $value->variations;

        $content = $this->templating->render(
            'NetgenRemoteMediaBundle:ezexceed/edit:ngremotemedia.html.twig',
            array(
                'value' => $value,
                'fieldId' => $fieldId,
                'availableFormats' => $availableFormats
            )
        );

        $scaling = array();
        foreach ($variations as $name => $coords) {
            $scaling[] = array(
                'name' => $name,
                'coords' => array(
                    (int) $coords['x'],
                    (int) $coords['y'],
                    (int) $coords['x'] + (int) $coords['w'],
                    (int) $coords['y'] + (int) $coords['h'],
                ),
            );
        }

        $responseData = array(
            'media' => !empty($value->resourceId) ? $value : false,
            'content' => $content,
            'toScale' => $scaling,
        );

        return new JsonResponse($responseData, 200);
    }

    // used for ezoe
//    public function fetchRemoteAction(Request $request, $id)
//    {
//
//        try {
//            $resource = $this->provider->getRemoteResource($id);
//        } catch (NotFound $e) {
//            return new JsonResponse(
//                array(
//                    'error' => $e->getMessage(),
//                )
//            );
//        }
//
//        $versions = $this->getConfigResolver()->getParameter('ezoe.variation_list', 'netgen_remote_media');
//        $toScale = array();
//        if (!empty($versions) && is_array($versions)) {
//            foreach ($versions as $name => $size) {
//                $size = array_map(function ($value) { return (int) $value;}, explode('x', $size));
//
//                if (count($size) != 2 || !is_integer($size[0]) && !is_integer($size[1])) {
//                    continue;
//                }
//                /*
//                 * Both dimensions can't be unbound
//                 */
//                if ($size[0] == 0 || $size[1] == 0) {
//                    continue;
//                }
//
//                $toScale[] = array(
//                    'name' => $name,
//                    'size' => $size,
//                );
//            }
//        }
//
//        $classList = $this->getConfigResolver()->getParameter('ezoe.class_list', 'netgen_remote_media');
//        $viewModes = $this->getConfigResolver()->getParameter('ezoe.view_modes', 'netgen_remote_media');
//
//        return new JsonResponse(
//            compact('resource', 'toScale', 'classList', 'viewModes'),
//            200
//        );
//    }

    public function saveAttributeAction(Request $request, $objectId, $fieldId, $contentVersionId)
    {
        // make all coords int
        $variantName = $request->get('name', '');
        $crop_x = $request->get('crop_x', 0);
        $crop_y = $request->get('crop_y', 0);
        $crop_w = $request->get('crop_w', 0);
        $crop_h = $request->get('crop_h', 0);

        if (empty($variantName) || empty($crop_w) || empty($crop_h)) {
            throw new \InvalidArgumentException('Missing one of the arguments: variant name, crop width, crop height');
        }

        $field = $this->helper->loadField($fieldId, $contentVersionId);
        $availableFormats = $this->helper->loadSPIFieldAvailableFormats($field);

        /** @var \Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value $value */
        $value = $field->value;
        $variations = $value->variations;

        if ($field->type !== 'ngremotemedia') {
            return new JsonResponse('Error', 500);
        }

        $variationCoords = array(
            $variantName => array(
                'x' => $crop_x,
                'y' => $crop_y,
                'w' => $crop_w,
                'h' => $crop_h,
            ),
        );

        $emptyCoords = array(
            'x' => 0,
            'y' => 0,
            'w' => 0,
            'h' => 0,
        );

        $initalVariations = array();
        foreach ($availableFormats as $name => $key) {
            $initalVariations[$name] = $emptyCoords;
        }

        $variations = $variationCoords + $variations + $initalVariations;
        $value->variations = $variations;

        $this->helper->updateField($value, $fieldId, $contentVersionId);

        $variation = $this->provider->getVariation(
            $value,
            $availableFormats,
            $variantName
        );

        $responseData = array(
            'error_text' => '',
            'content' => array(
                'name' => $variantName,
                'url' => $variation->url,
                'coords' => array(
                    (int) $crop_x,
                    (int) $crop_y,
                    (int) $crop_x + (int) $crop_w,
                    (int) $crop_y + (int) $crop_h,
                ),
            ),
        );

        return new JsonResponse($responseData, 200);
    }

    public function browseRemoteMediaAction(Request $request, $fieldId, $contentVersionId)
    {
        $offset = $request->get('offset', 0);

        $hardLimit = 500;
        $limit = 25;
        $query = $request->get('q', '');

        if (empty($query)) {
            $list = $this->provider->listResources($hardLimit);
        } else {
            $list = $this->provider->searchResources($query, 'image', $hardLimit);
        }

        $count = count($list);

        $list = array_slice($list, $offset, $limit);

        $listFormatted = array();
        foreach ($list as $hit) {
            $fileName = explode('/', $hit['public_id']);
            $fileName = $fileName[0];

            $options = array();
            $options['crop'] = 'fit';
            $options['width'] = 160;
            $options['height'] = 120;

            $listFormatted[] = array(
                'id' => $hit['public_id'],
                'tags' => $hit['tags'],
                'type' => $hit['resource_type'],
                'filesize' => $hit['bytes'],
                'width' => $hit['width'],
                'height' => $hit['height'],
                'filename' => $fileName,
                'shared' => array(),
                'scalesTo' => array('quality' => 100, 'ending' => $hit['format']),
                'host' => 'cloudinary',
                'thumb' => array(
                    'url' => $this->provider->getFormattedUrl($hit['public_id'], $options),
                ),
            );
        }

        $results = array(
            'total' => $count,
            'hits' => $listFormatted,
        );

        $responseData = array(
            'keymediaId' => 0,
            'results' => $results,
        );

        return new JsonResponse($responseData, 200);
    }

    public function addTagsAction(Request $request, $fieldId, $contentVersionId)
    {
        $resourceId = $request->get('id', '');
        $tag = $request->get('tag', '');

        if (empty($resourceId) || empty($tag)) {
            return new JsonResponse(
                array(
                    'error_text' => 'Not enough arguments - neither resource id, nor tag can be empty',
                    'content' => null,
                ),
                400
            );
        }

        $field = $this->helper->loadField($fieldId, $contentVersionId);

        /** @var \Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value $value */
        $value = $field->value;
        $metaData = $value->metaData;
        $attributeTags = !empty($metaData['tags']) ? $metaData['tags'] : array();

        $result = $this->provider->addTagToResource($resourceId, $tag);
        $attributeTags[] = $tag;

        $metaData['tags'] = $attributeTags;
        $value->metaData = $metaData;

        $this->helper->updateField($value, $fieldId, $contentVersionId);

        return new JsonResponse($attributeTags, 200);
    }

    public function removeTagsAction(Request $request, $fieldId, $contentVersionId)
    {

        $resourceId = $request->get('id', '');
        $tag = $request->get('tag', '');

        if (empty($resourceId) || empty($tag)) {
            return new JsonResponse(
                array(
                    'error_text' => 'Not enough arguments - neither resource id, nor tag can be empty',
                    'content' => null,
                ),
                400
            );
        }

        $field = $this->helper->loadField($fieldId, $contentVersionId);

        /** @var \Netgen\Bundle\RemoteMediaBundle\Core\FieldType\RemoteMedia\Value $value */
        $value = $field->value;
        $metaData = $value->metaData;
        $attributeTags = !empty($metaData['tags']) ? $metaData['tags'] : array();

        $result = $this->provider->removeTagFromResource($resourceId, $tag);
        $attributeTags = array_diff($attributeTags, array($tag));

        $metaData['tags'] = $attributeTags;
        $value->metaData = $metaData;

        $this->helper->updateField($value, $fieldId, $contentVersionId);

        return new JsonResponse($attributeTags, 200);

    }
}
