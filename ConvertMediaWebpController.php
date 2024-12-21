<?php

declare(strict_types=1);

namespace Drupal\convert_media_webp\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\file\Entity\File;
use Drupal\entity\EntityStorageInterface;
use Drupal\Core\File\FileSystemInterface;


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
function convert_all_files_to_webp() {
  // Load all file entities.
  $storage = \Drupal::entityTypeManager()->getStorage('file');
  $query = $storage->getQuery();
  $file_ids = $query->accessCheck(TRUE)->execute();


  foreach ($file_ids as $file_id) {
    $file = File::load($file_id);

    if ($file) {
      $uri = $file->getFileUri();
      $real_path = \Drupal::service('file_system')->realpath($uri);

      // Check if the file is an image (e.g., JPEG, PNG).
      $mime_type = mime_content_type($real_path);
      if (in_array($mime_type, ['image/jpeg', 'image/png'])) {
        // Define the WebP file path.
        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $real_path);

        // Convert to WebP.
        if ($this->convert_to_webp($real_path, $webp_path)) {
          // Save the WebP file as a new file entity.
          $webp_uri = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $uri);
          $webp_file = File::create([
            'uri' => $webp_uri,
            'status' => 1,
          ]);
          $webp_file->save();

          // Log the conversion.
          \Drupal::logger('custom_module')->notice('Converted file ID @id to WebP.', ['@id' => $file_id]);
        }
      }
    }
  }
}




/**
 * Convert all files in Drupal to WebP format and update references.
 */
function replace_all_files_with_webp() {
  // Load all file entities.
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $query = $file_storage->getQuery();
  $file_ids = $query->accessCheck(TRUE)->execute();

  foreach ($file_ids as $file_id) {
    $file = File::load($file_id);
    if ($file) {
      $original_uri = $file->getFileUri();
      $real_path = \Drupal::service('file_system')->realpath($original_uri);

      // Check if the file is a supported image (e.g., JPEG, PNG).
      $mime_type = mime_content_type($real_path);
      if (in_array($mime_type, ['image/jpeg', 'image/png'])) {
        // Define the WebP file path.
        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $real_path);

        // Convert to WebP.
        if ($this->convert_to_webp($real_path, $webp_path)) {
          // Replace the original file with the WebP file.
          \Drupal::service('file_system')->move($webp_path, $original_uri, FileSystemInterface::EXISTS_REPLACE);

          // Update the file entity's MIME type.
          $file->setMimeType('image/webp');
          $file->save();

          // Log the replacement.
          \Drupal::logger('custom_module')->notice('Replaced file ID @id with WebP.', ['@id' => $file_id]);
        }
      }
    }
  }
}

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
    if ($file) {
      $original_uri = $file->getFileUri();
      $real_path = \Drupal::service('file_system')->realpath($original_uri);

      // Check if the file is a supported image (e.g., JPEG, PNG).
      $mime_type = mime_content_type($real_path);
      if (in_array($mime_type, ['image/jpeg', 'image/png'])) {
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
function convert_to_webp($source_path, $webp_path) {
  $image = imagecreatefromstring(file_get_contents($source_path));
  if ($image) {
    $success = imagewebp($image, $webp_path, 80); // Adjust quality as needed.
    imagedestroy($image);
    return $success;
  }
  return FALSE;
}




}
