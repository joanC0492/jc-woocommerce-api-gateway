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
  foreach ($productos as $producto) {
    // Verificamos si el SKU viene en la clave del array && no está vacío
    $has_sku = isset($producto['sku']) && !empty($producto['sku']);

    if (!$has_sku) {
      echo '<div class="notice notice-warning"><p>' . sprintf(
        __('Producto omitido (%s): El producto no tiene un SKU válido.', 'jc-woocommerce-api'),
        $producto['name'] ?? 'Sin nombre'
      ) . '</p></div>';
      continue;
    }

    $sku = $producto['sku'];
    // Verificar si el SKU ya fue procesado
    if (in_array($sku, $processed)) {
      echo '<div class="notice notice-warning"><p>' . sprintf(
        __('Producto omitido (%s): El SKU "%s" ya fue procesado en este lote.', 'jc-woocommerce-api'),
        $producto['name'] ?? 'Sin nombre',
        $sku
      ) . '</p></div>';
      continue;
    }

    // Agregar el SKU al arreglo de procesados si es válido
    $processed[] = $sku;

    // VALIDANDO CAMPOS
    // El precio debe venir en la clave del array y ser numerico    
    $has_regular_price = isset($producto['regular_price']) && is_numeric($producto['regular_price']);
    // La cantidad en stock debe venir en la clave del array y ser numerico
    $has_stock_quantity = isset($producto['stock_quantity']) && is_numeric($producto['stock_quantity']);

    try {
      // Arreglo de datos para el producto
      $data = [
        'name' => $producto['name'] ?? "New Product - {$producto['sku']}",
        'type' => $producto['type'] ?? 'simple',
        'regular_price' => $producto['type'] === 'simple' && $has_regular_price ? $producto['regular_price'] : '',
        'description' => $producto['description'] ?? '',
        'sku' => $producto['sku'],
        'stock_quantity' => $has_stock_quantity ? $producto['stock_quantity'] : 0,
        'manage_stock' => true,
        'images' => $producto['images'] ?? [], // Añadir imágenes si existen
        'categories' => $producto['categories'] ?? [], // Añadir categorías si existen
        'attributes' => $producto['attributes'] ?? [], // Añadir atributos si existen
        'default_attributes' => $producto['default_attributes'] ?? [], // Añadir atributos por defecto si existen
        'status' => $producto['status'] ?? 'draft', // Agregar estado
      ];

      // Buscamos el producto por SKU
      $existing_products = $woocommerce->get('products', ['sku' => $producto['sku']]);
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

      // Crear variaciones si el producto es variable
      // Verificamos si el producto es variable
      $is_variable = $producto['type'] === 'variable';
      // Verificamos si variations viene en la clave del array && es un array
      $has_variations = isset($producto['variations']) && is_array($producto['variations']) && !empty($producto['variations']);
      if ($is_variable && $has_variations) {
        foreach ($producto['variations'] as $variation) {
          $variation_data = [
            'regular_price' => $variation['regular_price'] ?? '',
            'image' => $variation['image'] ?? [],
            'attributes' => $variation['attributes'] ?? []
          ];

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
    } catch (Exception $e) {
      echo '<div class="notice notice-error"><p>' . sprintf(__('Error al importar el producto: %s', 'jc-woocommerce-api'), $e->getMessage()) . '</p></div>';
    }
  }

  echo '<div class="notice notice-success"><p>' . __('Importación completada.', 'jc-woocommerce-api') . '</p></div>';
}


function process_product_batch()
{
  
}