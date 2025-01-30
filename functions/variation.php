<?php
if (!defined('ABSPATH'))
  exit;
function process_variations($woocommerce, $product_id, $producto)
{
  // Verificamos si el producto es variable
  if ($producto['type'] === 'variable' && isset($producto['variations']) && is_array($producto['variations'])) {

    // Obtener variaciones existentes del producto
    $existing_variations = $woocommerce->get("products/$product_id/variations");
    echo "<pre>";
    print_r($existing_variations);
    echo "</pre>";

    foreach ($producto['variations'] as $variation) {
      // Preparamos los datos de la variación
      $variation_data = prepare_variation_data($variation);

      // Verificar si la variación ya existe
      $existing_variation_id = null;
      foreach ($existing_variations as $existing_variation) {
        if (compare_variations($existing_variation, $variation_data)) {
          $existing_variation_id = $existing_variation->id;
          break;
        }
      }

      try {
        if ($existing_variation_id) {
          // ACTUALIZAR VARIACIÓN
          $woocommerce->put("products/$product_id/variations/$existing_variation_id", $variation_data);
          echo '<div class="notice notice-info"><p>' . sprintf(__('-- Variación actualizada para el producto "%s": %s', 'jc-woocommerce-api'), $producto['name'], json_encode($variation['attributes'])) . '</p></div>';
        } else {
          // CREAR VARIACIÓN 
          $woocommerce->post("products/$product_id/variations", $variation_data);
          echo '<div class="notice notice-success"><p>' . sprintf(__('-- Nueva variación creada para el producto "%s": %s', 'jc-woocommerce-api'), $producto['name'], json_encode($variation['attributes'])) . '</p></div>';
        }
      } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>' . sprintf(__('Error al crear la variación del producto "%s": %s', 'jc-woocommerce-api'), $producto['name'], $e->getMessage()) . '</p></div>';
      }
    }
  }
}

function prepare_variation_data($variation)
{
  $has_stock_quantity = isset($variation['stock_quantity']) && is_numeric($variation['stock_quantity']);

  return [
    'regular_price' => $variation['regular_price'] ?? '',
    'image' => handle_product_images([$variation['image']])[0] ?? [],
    'attributes' => $variation['attributes'] ?? [],
    'stock_quantity' => $has_stock_quantity ? $variation['stock_quantity'] : null,
    'manage_stock' => $has_stock_quantity ? true : false,
    'stock_status' => $variation['stock_status'] ?? 'instock',
  ];
}

function compare_variations($existing_variation, $new_variation)
{
  if (!isset($existing_variation->attributes) || !isset($new_variation['attributes'])) {
    return false;
  }

  $existing_attributes = array_column($existing_variation->attributes, 'option', 'name');
  $new_attributes = array_column($new_variation['attributes'], 'option', 'name');

  return $existing_attributes == $new_attributes;
}
