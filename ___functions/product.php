<?php
if (!defined('ABSPATH'))
  exit;
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
    echo '<div class="notice notice-error"><p>' . sprintf(__('Error al importar el producto "%s": %s', 'jc-woocommerce-api'), $producto['name'], $e->getMessage()) . '</p></div>';
    return null;
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


  // echo $has_stock_quantity ? "true" : "false";
  // echo "<br>";

  $data = [
    'name' => $producto['name'] ?? "New Product - {$producto['sku']}",
    'type' => $producto['type'] ?? 'simple',
    'regular_price' => $producto['type'] === 'simple' && $has_regular_price ? $producto['regular_price'] : '',
    'description' => $producto['description'] ?? '',
    'sku' => $producto['sku'],
    'stock_quantity' => $has_stock_quantity ? $producto['stock_quantity'] : null,
    // true: woo controla el stock, false: no controla el stock
    'manage_stock' => $has_stock_quantity ? true : false,
    'images' => handle_product_images($producto['images']),  // Añadir imágenes si existen
    'categories' => $producto['categories'] ?? [], // Añadir categorías si existen
    'attributes' => $producto['attributes'] ?? [], // Añadir atributos si existen
    'default_attributes' => $producto['default_attributes'] ?? [], // Añadir atributos por defecto si existen
    'status' => $producto['status'] ?? 'draft', // Agregar estado
    'catalog_visibility' => $producto['catalog_visibility'] ?? "visible", // Agregar visibilidad
    // Agregar estado de stock "hay existencias" "sin existencias"
    // El manage_stock debe ser false para que esto funcione
    // Si no le mando sotck_quantity, entonces el stock_status es "instock" por defecto
    'stock_status' => $producto['stock_status'] ?? 'instock',
  ];
  return $data;
}