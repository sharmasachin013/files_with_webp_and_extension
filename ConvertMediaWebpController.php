<?php

declare(strict_types=1);

namespace Drupal\convert_media_webp\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Drupal\entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\node\Entity\Node;
use Drupal\Component\Utility\Html;


/**
 * Returns responses for Convert media webp routes.
 */
final class ConvertMediaWebpController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $this->replace_all_files_with_webp_and_extension();

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  /**
 * Convert all image files to WebP format.
 */





/**
 * Convert all files in Drupal to WebP format and update references.
 */

/**
 * Convert all image files to WebP format and update their extensions.
 */
function replace_all_files_with_webp_and_extension() {
  // Load all file entities.
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $query = $file_storage->getQuery();
  $file_ids =$query->accessCheck(TRUE)->execute();

  foreach ($file_ids as $file_id) {
    $file = File::load($file_id);
  //  dump($file);
   
    if ($file) {
      $original_uri = $file->getFileUri();

     // dump($original_uri);
      $real_path = \Drupal::service('file_system')->realpath($original_uri);

      // Check if the file is a supported image (e.g., JPEG, PNG).
      //dump($real_path);
      if($file)
      $mime_type = $file->getMimeType();
     //dump($mime_type);
      if (in_array($mime_type, ['image/jpeg', 'image/png'])) {
       //  dump($mime_type);

        // Define the new WebP file path with the .webp extension.
        $webp_uri = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $original_uri);
        $webp_path = \Drupal::service('file_system')->realpath($webp_uri);

        // Convert to WebP.
        if ($this->convert_to_webp($real_path, $webp_path)) {
          // Delete the original file.
          \Drupal::service('file_system')->delete($original_uri);

          // Update the file entity with the new WebP file.
          $file->setFileUri($webp_uri);
          $file->setMimeType('image/webp');
          $file->save();
          $this->update_inline_references_in_nodes($original_uri, $webp_uri);

          // Log the replacement.
          \Drupal::logger('custom_module')->notice('Replaced file ID @id with WebP and updated extension.', ['@id' => $file_id]);
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

function convert_to_webp($source_path, $webp_path) {
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
  return FALSE;
}

/**
 * Update inline file references in nodes.
 *
 * @param string $old_uri
 *   The old file URI.
 * @param string $new_uri
 *   The new WebP file URI.
 */
function update_inline_references_in_nodes($old_uri, $new_uri) {
  $old_path = \Drupal::service('file_system')->realpath($old_uri);
  $new_path = \Drupal::service('file_system')->realpath($new_uri);


  // Load all nodes that might reference the file.
  $node_storage = \Drupal::entityTypeManager()->getStorage('node');
 //$query->accessCheck(TRUE)->execute();
  $node_ids = $node_storage->getQuery()->accessCheck(TRUE)->execute();
  

  foreach ($node_ids as $node_id) {
    $node = Node::load($node_id);
    $updated = FALSE;
    //dump($node->get('body')->value);

    // Iterate through all fields in the node.
    foreach ($node->getFields() as $field_name => $field) {
      //dump($field->getFieldDefinition()->getType());
      if ($field->getFieldDefinition()->getType() === 'text_with_summary' || $field->getFieldDefinition()->getType() === 'text_long') {
        $field_value =$node->get('body')->value;
      //  /dump($node->get('body')->value);
     
        $string = $old_path;
        $substring = '/app/web/';
       // dump($field_value);
       // dump($old_path);
        $old_path = 'png';
        $new_path = 'webp';

// Substring to remove

       // $field_value = str_replace($substring, "", $string);
      //  dump($field_value);
       dump($node);
        if (strpos($field_value, $old_path) !== FALSE) {
           dump($field_value);
          $field_value = str_replace($old_path, $new_path, $field_value);
         // dump($field_value);exit;
         //dump($field_value);
         //$updated_body = strip_tags($body, '<p>');
         $field_value  = preg_replace('/<\/?p[^>]*>/', '', $field_value);
         dump($field_value);
         
         //$node->set('body', $field_value);
         $node->set('body', [
          'value' => $field_value,
          'format' => $node->get('body')->format, // Retain the text format.
        ]);
          $updated = TRUE;
        }
      }
    }
   

    // Save the node if updated.
    if ($updated) {
     $node->save();
      \Drupal::logger('custom_module')->notice('Updated node @node_id with new WebP file references.', ['@node_id' => $node_id]);
    }
  }
}





}
