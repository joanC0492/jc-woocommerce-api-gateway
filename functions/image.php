<?php
if (!defined('ABSPATH'))
exit;
function handle_product_images($images)
{
  $image_urls = [];

  if (!empty($images)) {
    foreach ($images as $image) {
      $image_urls[] = get_image_url($image);
    }
  }
  return $image_urls;
}

function get_image_url($image)
{
  $image_url = $image['url'] ?? '';

  // Verificamos si la URL de la imagen es válida
  if (filter_var($image_url, FILTER_VALIDATE_URL)) {
    return $image_url;
  }

  return ''; // Si no es válida, retornamos vacío
}
