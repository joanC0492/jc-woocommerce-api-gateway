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

  foreach ($productos as $producto) {
    // Verificamos si el producto está activo
    $is_active = $producto['status'] === 'active';
    // Verificamos si el SKU viene en la clave del array && no está vacío
    $has_sku = isset($producto['sku']) && !empty($producto['sku']);

    if (!$is_active) {
      echo '<div class="notice notice-warning"><p>' . sprintf(
        __('Producto omitido (%s): El producto no está activo.', 'jc-woocommerce-api'),
        $producto['name'] ?? 'Sin nombre'
      ) . '</p></div>';
      continue;
    }

    if (!$has_sku) {
      echo '<div class="notice notice-warning"><p>' . sprintf(
        __('Producto omitido (%s): El producto no tiene un SKU válido.', 'jc-woocommerce-api'),
        $producto['name'] ?? 'Sin nombre'
      ) . '</p></div>';
      continue;
    }

    try {
      $data = [
        'name' => $producto['name'],
        'type' => $producto['type'],
        'regular_price' => $producto['type'] === 'simple' ? $producto['regular_price'] : '',
        'description' => $producto['description'],
        'sku' => $producto['sku'],
        'stock_quantity' => $producto['stock_quantity'],
        'manage_stock' => true,
        'images' => $producto['images'],
        'categories' => $producto['categories'] ?? [],
        'attributes' => $producto['attributes'] ?? [], // Añadir atributos si existen
      ];

      // Buscamos el producto por SKU
      $existing_products = $woocommerce->get('products', ['sku' => $producto['sku']]);
      // Lo convertimos en un array
      $existing_products = json_decode(json_encode($existing_products), true);

      // Si no es falsy
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

      // Si el producto es variable
      $is_variable = $producto['type'] === 'variable';
      // Verificamos si default_attributes viene en la clave del array && y que sea array
      $has_default_attributes = isset($producto['default_attributes']) && is_array($producto['default_attributes']);

      if ($is_variable && $has_default_attributes) {
        // Agregar variaciones
        foreach ($producto['default_attributes'] as $variation) {
          $variation_data = [
            'regular_price' => $producto['regular_price'],
            'attributes' => [$variation],
            'stock_quantity' => $producto['stock_quantity'],
            'manage_stock' => true,
          ];
          $woocommerce->post("products/$product_id/variations", $variation_data);
        }
        echo '<div class="notice notice-success"><p>' . sprintf(__('Variaciones creadas para el producto: %s', 'jc-woocommerce-api'), $producto['name']) . '</p></div>';
      }
    } catch (Exception $e) {
      echo '<div class="notice notice-error"><p>' . sprintf(__('Error al importar el producto: %s', 'jc-woocommerce-api'), $e->getMessage()) . '</p></div>';
    }
  }

  echo '<div class="notice notice-success"><p>' . __('Importación completada.', 'jc-woocommerce-api') . '</p></div>';
}