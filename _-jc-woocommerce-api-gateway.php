<?php
/*
Plugin Name: JC WooCommerce API Gateway
Description: Importa productos desde una API a WooCommerce.
Version: 1.0
Author: Joan Cochachi
*/

// Evita el acceso directo al archivo
if (!defined('ABSPATH'))
  exit;

// Hook para ejecutar la importación cuando el administrador lo solicite
add_action('admin_menu', 'aster_import_menu');

function aster_import_menu()
{
  add_menu_page(
    // 'Importar Productos',
    // 'Importar Productos',
    __('Importar Productos', 'jc-woocommerce-api-gateway'),
    __('Importar Productos', 'jc-woocommerce-api-gateway'),
    'manage_options',
    'aster-product-import',
    'aster_import_page',
    'dashicons-download',
    20
  );
}

/*
function aster_import_page()
{
  echo '<div class="wrap"><h1>Importar Productos desde Aster</h1>';
  echo '<p>Haz clic en el botón para importar los productos desde la API de Aster.</p>';
  echo '<form method="post" action="">
            <input type="submit" name="aster_import" class="button-primary" value="Importar Productos">
          </form></div>';
  // Verifica si se ha enviado el formulario
  if (isset($_POST['aster_import'])) {
    aster_import_products();
  }
}
*/
function aster_import_page()
{
  if (!current_user_can('manage_options')) {
    return;
  }

  echo '<div class="wrap"><h1>' . __('Importar Productos desde Aster', 'jc-woocommerce-api-gateway') . '</h1>';
  echo '<p>' . __('Haz clic en el botón para importar los productos desde la API de Aster.', 'jc-woocommerce-api-gateway') . '</p>';
  echo '<form method="post" action="">';
  wp_nonce_field('aster_import_action', 'aster_import_nonce');
  echo '<input type="submit" name="aster_import" class="button-primary" value="' . __('Importar Productos', 'jc-woocommerce-api-gateway') . '">';
  echo '</form></div>';

  if (isset($_POST['aster_import']) && check_admin_referer('aster_import_action', 'aster_import_nonce')) {
    aster_import_products();
  }
}

function aster_import_products()
{
  // URL de la API de Aster
  $api_url = 'https://joancochachi.dev/aster.json';

  // Realiza la solicitud GET
  $response = wp_remote_get($api_url);

  // if (is_wp_error($response)) {
  //   echo '<p>Error al obtener los productos: ' . $response->get_error_message() . '</p>';
  //   return;
  // }
  if (is_wp_error($response)) {
    echo '<div class="notice notice-error"><p>' . __('Error al obtener los productos: ', 'jc-woocommerce-api-gateway') . $response->get_error_message() . '</p></div>';
    return;
  }

  $productos = json_decode(wp_remote_retrieve_body($response), true);

  // if (!is_array($productos)) {
  //   echo '<p>Error: Respuesta de la API no válida.</p>';
  //   return;
  // }
  if (!is_array($productos)) {
    echo '<div class="notice notice-error"><p>' . __('Respuesta de la API no válida.', 'jc-woocommerce-api-gateway') . '</p></div>';
    return;
  }

  foreach ($productos as $producto) {
    if ($producto['status'] === 'active') {
      $wc_response = wp_insert_post([
        'post_title' => $producto['name'],
        'post_content' => $producto['description'],
        'post_status' => 'publish',
        'post_type' => 'product',
        'meta_input' => [
          '_regular_price' => $producto['regular_price'],
          '_price' => $producto['regular_price'],
          '_stock' => $producto['stock_quantity'],
          '_manage_stock' => 'yes'
        ]
      ]);
      if ($wc_response) {
        echo '<p>Producto importado: ' . $producto['name'] . '</p>';
      } else {
        echo '<p>Error al importar el producto: ' . $producto['name'] . '</p>';
      }
    }
  }

  echo '<p>Importación completada.</p>';
}
