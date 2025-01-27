<?php
/*
Plugin Name: JC WooCommerce API Gateway
Description: Importa productos desde un JSON a WooCommerce usando la API REST.
Version: 1.1
Author: Joan Cochachi
*/
if (!defined('ABSPATH'))
  exit;

require_once __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;

// Añade un menú de administrador
add_action('admin_menu', 'aster_import_menu');

function aster_import_menu()
{
  add_menu_page(
    __('Importar Productos', 'jc-woocommerce-api'),
    __('Importar Productos', 'jc-woocommerce-api'),
    'manage_options',
    'aster-product-import',
    'aster_import_page',
    'dashicons-download',
    20
  );
}

function aster_import_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  echo '<div class="wrap"><h1>' . __('Importar Productos desde JSON', 'jc-woocommerce-api') . '</h1>';
  echo '<p>' . __('Haz clic en el botón para importar los productos desde el JSON.', 'jc-woocommerce-api') . '</p>';
  echo '<form method="post" action="">';
  wp_nonce_field('aster_import_action', 'aster_import_nonce');
  echo '<input type="submit" name="aster_import" class="button-primary" value="' . __('Importar Productos', 'jc-woocommerce-api') . '">';
  echo '</form></div>';

  if (isset($_POST['aster_import']) && check_admin_referer('aster_import_action', 'aster_import_nonce')) {
    aster_import_products();
  }
}

function aster_import_products()
{
  try {
    // Configura la API REST de WooCommerce
    $woocommerce = new Client(
      site_url(),  // URL de tu tienda
      'ck_53a81c639c0c5751361030f76f97e19a3e53b988',  // Reemplaza con tu consumer key
      'cs_cb0c74d812ad3aded991c50a894a60e8458c875e',  // Reemplaza con tu consumer secret
      [
        'version' => 'wc/v3',
        'verify_ssl' => false,  // Desactiva la verificación SSL (POR MIENTRAS)
      ]
    );

    // API URL
    $json_url = 'https://joancochachi.dev/aster.json';

    // Obtener datos del JSON
    $response = wp_remote_get($json_url);

    if (is_wp_error($response)) {
      echo '<div class="notice notice-error"><p>' . __('Error al obtener los productos: ', 'jc-woocommerce-api') . $response->get_error_message() . '</p></div>';
      return;
    }

    $productos = json_decode(wp_remote_retrieve_body($response), true);

    if (!is_array($productos)) {
      echo '<div class="notice notice-error"><p>' . __('Respuesta del JSON no válida.', 'jc-woocommerce-api') . '</p></div>';
      return;
    }

    // Array para rastrear los SKU procesados
    $processed = [];
    foreach ($productos as $producto)
      process_product_batch($woocommerce, $producto, $processed);

    echo '<div class="notice notice-success"><p>' . __('Importación completada.', 'jc-woocommerce-api') . '</p></div>';
  } catch (Exception $e) {
    echo '<div class="notice notice-error"><p>' . __('Error fatal en la importación: ', 'jc-woocommerce-api') . $e->getMessage() . '</p></div>';
  }
}

function process_product_batch($woocommerce, $producto, &$processed)
{
  // Si el producto no es válido, pasamos al siguiente
  if (!validate_product_data($producto, $processed))
    return;

  // Agregar SKU al array de productos procesados
  $processed[] = $producto['sku'];
  // obtener array de los datos del producto con sus validaciones
  $data = prepare_product_data($producto);
  // Crea o actualiza el producto y retorna el id del producto || retorna null en caso de error
  $product_id = create_or_update_product($woocommerce, $data, $producto);

  // Si el producto es variable, procesar variaciones
  if ($product_id) {
    process_variations($woocommerce, $product_id, $producto);
  }
}

function validate_product_data($producto, &$processed)
{
  // Si el array $producto no tiene la clave 'sku' || la clave 'sku' existe pero su valor es null, entonces se asigna null a $sku.
  $sku = $producto['sku'] ?? null;

  // Está vacío
  if (empty($sku)) {
    echo '<div class="notice notice-warning"><p>' . sprintf(
      __('Producto omitido (%s): El producto no tiene un SKU válido.', 'jc-woocommerce-api'),
      $producto['name'] ?? 'Sin nombre'
    ) . '</p></div>';
    return false;
  }

  // Verificamos si el SKU ya fue procesado
  if (in_array($sku, $processed)) {
    echo '<div class="notice notice-warning"><p>' . sprintf(
      __('Producto omitido (%s): El SKU "%s" ya fue procesado en este lote.', 'jc-woocommerce-api'),
      $producto['name'] ?? 'Sin nombre',
      $sku
    ) . '</p></div>';
    return false;
  }

  return true;
}

function prepare_product_data($producto)
{
  // VALIDANDO CAMPOS
  // El precio debe venir en la clave del array y ser numerico
  $has_regular_price = isset($producto['regular_price']) && is_numeric($producto['regular_price']);
  // La cantidad en stock debe venir en la clave del array y ser numerico
  $has_stock_quantity = isset($producto['stock_quantity']) && is_numeric($producto['stock_quantity']);

  return [
    'name' => $producto['name'] ?? "New Product - {$producto['sku']}",
    'type' => $producto['type'] ?? 'simple',
    'regular_price' => $producto['type'] === 'simple' && $has_regular_price ? $producto['regular_price'] : '',
    'description' => $producto['description'] ?? '',
    'sku' => $producto['sku'],
    'stock_quantity' => $has_stock_quantity ? $producto['stock_quantity'] : 0,
    'manage_stock' => true,
    // 'images' => $producto['images'] ?? [],  // Añadir imágenes si existen    
    'images' => handle_product_images($producto['images']),  // Añadir imágenes si existen
    'categories' => $producto['categories'] ?? [], // Añadir categorías si existen
    'attributes' => $producto['attributes'] ?? [], // Añadir atributos si existen
    'default_attributes' => $producto['default_attributes'] ?? [], // Añadir atributos por defecto si existen
    'status' => $producto['status'] ?? 'draft', // Agregar estado
  ];
}

function handle_product_images($images)
{

  // Verificar si las imágenes son un array y no está vacío
  if (!is_array($images) || empty($images))
    return [];

  // Si no hay imágenes, retornar un array vacío
  $image_ids = [];

  // Reccorrer las imagenes
  foreach ($images as $image) {
    // Si no viene la clave src || la clave src no es una URL válida
    if (!isset($image['src']) || !filter_var($image['src'], FILTER_VALIDATE_URL))
      continue;

    // Obtener la URL de la imagen
    $image_url = $image['src'];

    // Verificar si la imagen ya existe en WordPress
    // si existe retorna el id , sino retorna null
    // $existing_image_id = image_exists_by_name($image_url);
    $existing_image_id = image_exists_by_url($image_url);

    if ($existing_image_id) {
      // Si la imagen ya existe, agregar su ID a la lista
      $image_ids[] = ['id' => $existing_image_id];
    } else {
      // Si no existe, subir la imagen y obtener su ID
      $attachment_id = upload_image_from_url($image_url);
      if ($attachment_id) {
        $image_ids[] = ['id' => $attachment_id];
      }
    }
  }
  // elimina los elementos duplicados de un array
  // return $image_ids;
  return array_unique($image_ids, SORT_REGULAR);
}
// function image_exists_by_name($image_url)
function image_exists_by_url($image_url)
{
  global $wpdb;

  // Extraer el nombre base del archivo desde la URL
  // $image_name = pathinfo($image_url, PATHINFO_FILENAME);
  $image_name = pathinfo($image_url, PATHINFO_BASENAME);

  // Consultar la base de datos para verificar si ya existe un post con ese post_name
  // $query = $wpdb->prepare(
  //   "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'attachment'",
  //   $image_name
  // );
  $query = $wpdb->prepare(
    "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'attachment'",
    $image_name
  );

  // Ejecutar la consulta y obtener el ID de la imagen
  // En caso no encontrar el valor es null
  // $attachment_id = $wpdb->get_var($query);

  // Retornar el ID si existe, o null si no
  return $wpdb->get_var($query);
}

/*
function upload_image_from_url($url)
{
  // Descargar la imagen temporalmente
  $tmp = download_url($url);

  // Si hay un error en la descarga, retornar null
  if (is_wp_error($tmp))
    return null;

  $file = [
    'name' => basename($url), // Nombre del archivo con extension
    'type' => mime_content_type($tmp), // Tipo MIME del archivo
    'tmp_name' => $tmp, // Ruta temporal del archivo
    'error' => 0, // Código de error
    'size' => filesize($tmp), // Tamaño del archivo
  ];

  // Subimos la imagen a WordPress wp-content/uploads/2025/01
  // 'test_form' => false , no valida formulario ya que llamamos de una API
  $upload = wp_handle_sideload($file, ['test_form' => false]);

  // Si hay un error en la subida, retornar null
  if (isset($upload['error']))
    return null;

  // Un array de metadatos con detalles del archivo para que WordPress lo registre correctamente en su base de datos como un archivo adjunto.
  $attachment = [
    'post_mime_type' => $upload['type'],
    'post_title' => pathinfo($upload['file'], PATHINFO_FILENAME),
    'post_content' => '',
    'post_status' => 'inherit',
  ];

  // Insertar la imagen en la biblioteca de medios(ya se registra en la BD)
  $attach_id = wp_insert_attachment($attachment, $upload['file']);
  // Actualizar los metadatos de la imagen
  require_once(ABSPATH . 'wp-admin/includes/image.php');
  wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));

  // Retornar el ID de la imagen
  return $attach_id;
}
*/
function upload_image_from_url($url)
{
  // Descargar la imagen temporalmente
  $tmp = download_url($url);

  // Si hay un error en la descarga, retornar null
  if (is_wp_error($tmp)) {
    return null;
  }

  $file = [
    'name' => basename($url), // Nombre del archivo con extensión
    'type' => mime_content_type($tmp), // Tipo MIME del archivo
    'tmp_name' => $tmp, // Ruta temporal del archivo
    'error' => 0, // Código de error
    'size' => filesize($tmp), // Tamaño del archivo
  ];

  // Subimos la imagen a WordPress wp-content/uploads/YYYY/MM
  $upload = wp_handle_sideload($file, ['test_form' => false]);

  // Si hay un error en la subida, retornar null
  if (isset($upload['error'])) {
    @unlink($tmp); // Eliminar archivo temporal en caso de error
    return null;
  }

  // Obtener la información del archivo subido
  $filename = basename($upload['file']); // Nombre del archivo con extensión
  $filepath = $upload['file']; // Ruta completa del archivo
  $filetype = $upload['type']; // Tipo MIME del archivo
  $fileurl = $upload['url']; // URL final del archivo

  // Crear un array con los datos del adjunto
  $attachment = [
    'post_mime_type' => $filetype, // Tipo MIME del archivo
    'post_title' => $filename, // Título del archivo con la extensión
    'post_content' => '',
    'post_status' => 'inherit',
    'guid' => $fileurl, // Establecer explícitamente el GUID
  ];

  // Insertar la imagen en la biblioteca de medios
  $attach_id = wp_insert_attachment($attachment, $filepath);

  // Actualizar los metadatos de la imagen
  require_once(ABSPATH . 'wp-admin/includes/image.php');
  $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
  wp_update_attachment_metadata($attach_id, $attach_data);

  // Retornar el ID del adjunto
  return $attach_id;
}




function process_variations($woocommerce, $product_id, $producto)
{
  // Verificamos si el producto es variable
  // Verificamos si variations viene en la clave del array && es un array
  if ($producto['type'] === 'variable' && isset($producto['variations']) && is_array($producto['variations'])) {
    foreach ($producto['variations'] as $variation) {
      // Preparamos los datos de la variación
      $variation_data = prepare_variation_data($variation);
      try {
        $woocommerce->post("products/$product_id/variations", $variation_data);
        echo '<div class="notice notice-success"><p>' . sprintf(
          __('-- Variación creada para el producto "%s": %s', 'jc-woocommerce-api'),
          $producto['name'] ?? 'Sin nombre',
          json_encode($variation['attributes'])
        ) . '</p></div>';
      } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>' . sprintf(
          __('Error al crear la variación del producto "%s": %s', 'jc-woocommerce-api'),
          $producto['name'] ?? 'Sin nombre',
          $e->getMessage()
        ) . '</p></div>';
      }
    }
  }
}

function prepare_variation_data($variation)
{
  return [
    'regular_price' => $variation['regular_price'] ?? '',
    // 'image' => $variation['image'] ?? [],
    // 'image' => isset($variation['image']['id']) ? ['id' => $variation['image']['id']] : [],
    'image' => handle_product_images([$variation['image']])[0] ?? [],
    'attributes' => $variation['attributes'] ?? [],
  ];
}

function create_or_update_product($woocommerce, $data, $producto)
{
  try {
    // Buscamos el producto por SKU
    $existing_products = $woocommerce->get('products', ['sku' => $data['sku']]);
    // Lo convertimos en un array
    $existing_products = json_decode(json_encode($existing_products), true);

    // Si es Truthy, entonces el producto ya existe
    if (!empty($existing_products)) {
      // ACTUALIZAR PRODUCTO
      $product_id = $existing_products[0]['id']; // Obtenemos el ID del producto existente
      // Actualizar producto en WooCommerce
      $woocommerce->put("products/$product_id", $data);

      echo '<div class="notice notice-info"><p>' . sprintf(__('Producto actualizado: %s', 'jc-woocommerce-api'), $producto['name']) . '</p></div>';
    } else {
      // CREAR PRODUCTO
      $response = $woocommerce->post('products', $data);
      $product_id = $response->id; // Obtenemos el ID del producto importado

      echo '<div class="notice notice-success"><p>' . sprintf(__('Producto importado: %s', 'jc-woocommerce-api'), $producto['name']) . '</p></div>';
    }
    // Retornamos el ID del producto
    return $product_id;
  } catch (Exception $e) {
    echo '<div class="notice notice-error"><p>' . sprintf(__('Error al importar el producto: %s', 'jc-woocommerce-api'), $e->getMessage()) . '</p></div>';
    return null;
  }
}
