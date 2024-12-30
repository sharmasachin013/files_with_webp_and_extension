<?php

declare(strict_types=1);

namespace Drupal\convert_media_webp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Drupal\entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Html;
use Drupal\media\Entity\Media;

/**
 * Returns responses for Convert media webp routes.
 */
final class ConvertMediaWebpController extends ControllerBase
{
    /**
     * Builds the response.
     */
    public function __invoke(): array
    {
        $this->replace_all_files_with_webp_and_extension();

        $build["content"] = [
            "#type" => "item",
            "#markup" => $this->t("It works!"),
        ];

        return $build;
    }

    /**
     * Convert all image files to WebP format.
     */

    function replace_all_files_with_webp_and_extension()
    {
        // Load all file entities.
        //  $media_ids = [1, 2, 3];
        $fileStorage = \Drupal::entityTypeManager()->getStorage("media");
        // $media_entities = $file_storage->loadMultiple($media_ids);
        $query = $fileStorage->getQuery();
        $mediaIds = $query
            ->accessCheck(true)
            ->condition("bundle", "image")
            ->sort("created", "DESC")
            ->range(0, 2)
            ->execute();
            // dump($mediaIds);

        foreach ($mediaIds as $mediaId) {
            $media = Media::load($mediaId);
            $imageField = $media->get("field_media_image")->getValue()[0]['target_id'];
          //  dump($imageField);exit;
            if ($imageField) {
                $file = File::load($imageField);
                if ($file) {
                    $originalUri = $file->getFileUri();
                    //dump($originalUri);
                    $realPath = \Drupal::service("file_system")->realpath(
                        $originalUri
                    );
                    $mimeType = $file->getMimeType();
                    $webpUri = preg_replace(
                        '/\.(jpg|jpeg|png)$/i',
                        ".webp",
                        $originalUri
                    );
                     $webpPath = \Drupal::service('file_system')->realpath($webpUri);
                    // Convert to WebP.
                    if ($this->convert_to_webp($realPath, $webpPath)) {
                        // Delete the original file.
                        \Drupal::service("file_system")->delete($originalUri);

                        // Update the file entity with the new WebP file.
                        $file->setFileUri($webpUri);
                        $file->setMimeType("image/webp");
                        $file->save();
                        \Drupal::logger("custom_module")->notice(
                            "Replaced file ID @id with WebP and updated extension.",
                            ["@id" => $mediaId]
                        );
                    } else {
                        \Drupal::logger("custom_module")->notice(
                            "Not Updated!!!.",
                            ["@id" => $mediaId]
                        );
                    }
                }
            }
        }
    }

    /**
     * Convert an image to WebP format.
     *
     * @param string $source_path
     *   The path to the source image.
     * @param string $webp_path
     *   The path to save the WebP image.
     *
     * @return bool
     *   TRUE if conversion is successful, FALSE otherwise.
     */
    // function convert_to_webp($source_path, $webp_path) {

    //   $image = imagecreatefromstring(file_get_contents($source_path));
    //   dump($image);
    //   if ($image) {
    //     $success = imagewebp($image, $webp_path, 80); // Adjust quality as needed.
    //     imagedestroy($image);
    //     return $success;
    //   }
    //   return FALSE;
    // }

    function convert_to_webp($source_path, $webp_path)
    {
        $image = imagecreatefromstring(file_get_contents($source_path));
        if ($image) {
            // Convert palette-based images to true color.
            if (!imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }
            // Save as WebP.
            $success = imagewebp($image, $webp_path, 80); // Adjust quality as needed.
            imagedestroy($image);
            return $success;
        }
        return false;
    }

    /**
     * Update inline file references in nodes.
     *
     * @param string $old_uri
     *   The old file URI.
     * @param string $new_uri
     *   The new WebP file URI.
     */
    function update_inline_references_in_nodes($old_uri, $new_uri)
    {
        $old_path = \Drupal::service("file_system")->realpath($old_uri);
        $new_path = \Drupal::service("file_system")->realpath($new_uri);

        // Load all nodes that might reference the file.
        $node_storage = \Drupal::entityTypeManager()->getStorage("node");
        //$query->accessCheck(TRUE)->execute();
        $node_ids = $node_storage
            ->getQuery()
            ->accessCheck(true)
            ->execute();

        foreach ($node_ids as $node_id) {
            $node = Node::load($node_id);
            $updated = false;
            //dump($node->get('body')->value);

            // Iterate through all fields in the node.
            foreach ($node->getFields() as $field_name => $field) {
                //dump($field->getFieldDefinition()->getType());
                if (
                    $field->getFieldDefinition()->getType() ===
                        "text_with_summary" ||
                    $field->getFieldDefinition()->getType() === "text_long"
                ) {
                    $field_value = $node->get("body")->value;
                    //  /dump($node->get('body')->value);

                    $string = $old_path;
                    $substring = "/app/web/";
                    // dump($field_value);
                    // dump($old_path);
                    $old_path = "png";
                    $new_path = "webp";

                    // Substring to remove

                    // $field_value = str_replace($substring, "", $string);
                    //  dump($field_value);
                    dump($node);
                    if (strpos($field_value, $old_path) !== false) {
                        dump($field_value);
                        $field_value = str_replace(
                            $old_path,
                            $new_path,
                            $field_value
                        );
                        // dump($field_value);exit;
                        //dump($field_value);
                        //$updated_body = strip_tags($body, '<p>');
                        $field_value = preg_replace(
                            "/<\/?p[^>]*>/",
                            "",
                            $field_value
                        );
                        dump($field_value);

                        //$node->set('body', $field_value);
                        $node->set("body", [
                            "value" => $field_value,
                            "format" => $node->get("body")->format, // Retain the text format.
                        ]);
                        $updated = true;
                    }
                }
            }

            // Save the node if updated.
            if ($updated) {
                $node->save();
                \Drupal::logger("custom_module")->notice(
                    "Updated node @node_id with new WebP file references.",
                    ["@node_id" => $node_id]
                );
            }
        }
    }
}
