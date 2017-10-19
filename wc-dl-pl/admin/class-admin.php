<?php

namespace WC_DL_PL;

/**
 * The code used in the admin.
 */
class Admin
{
  private $plugin_slug;
  private $version;
  private $option_name;
  private $settings;
  private $settings_group;

  public function __construct($plugin_slug, $version, $option_name) {
    $this->plugin_slug = $plugin_slug;
    $this->version = $version;
    $this->option_name = $option_name;
    $this->settings = get_option($this->option_name);
    $this->settings_group = $this->option_name.'_group';
  }

    /**
     * Generate settings fields by passing an array of data (see the render method).
     *
     * @param array $field_args The array that helps build the settings fields
     * @param array $settings   The settings array from the options table
     *
     * @return string The settings fields' HTML to be output in the view
     */
  private function custom_settings_fields($field_args, $settings) {
    $output = '';

    foreach ($field_args as $field) {
      $slug = $field['slug'];
      $setting = $this->option_name.'['.$slug.']';
      $label = esc_attr__($field['label'], 'wc-dl-pl');
      $output .= '<h3><label for="'.$setting.'">'.$label.'</label></h3>';

      if ($field['type'] === 'text') {
        $output .= '<p><input type="text" id="'.$setting.'" name="'.$setting.'" value="'.$settings[$slug].'"></p>';
      } elseif ($field['type'] === 'textarea') {
        $output .= '<p><textarea id="'.$setting.'" name="'.$setting.'" rows="10">'.$settings[$slug].'</textarea></p>';
      }elseif ($field['type'] === 'checkbox') {
        $checked =  $settings[$slug] ? 'checked=true':'';
        $output .= '<p><input type="checkbox" id="'.$setting.'" name="'.$setting.'"'. $checked .'"></p>';
      }
    }

    return $output;
  }

  public function assets() {
    wp_enqueue_style($this->plugin_slug, plugin_dir_url(__FILE__).'css/wc-dl-pl-admin.css', [], $this->version);
    wp_enqueue_script($this->plugin_slug, plugin_dir_url(__FILE__).'js/wc-dl-pl-admin.js', ['jquery'], $this->version, true);
  }

  public function register_settings() {
    register_setting($this->settings_group, $this->option_name);
  }

  public function submit_invoice($id) {
    $order = wc_get_order( $id );
    // var_dump($order);
    $items = $order->get_items(); 
    // var_dump($items);
    $url = 'https://link.datil.co/invoices/issue';
    $razon_social = $order->get_meta('_billing_ruc');
    if(!$razon_social)
      $razon_social = $order->data["billing"]["first_name"]." ".$order->data["billing"]["last_name"];
    $base_imponible = intval($order->data["total"]) - intval($order->data["total_tax"]);
    $obligado_contabilidad =$this->settings['obligado_contabilidad'] === 'on' ? 'true':'false' ;
    $ambiente = $this->settings['ambiente'] === 'on' ? '1':'2' ;
    $data = '{
      "ambiente":'.$ambiente.',
      "tipo_emision":1,
      "fecha_emision":"'.date(DATE_ISO8601).'",
      "emisor":{
        "ruc":"'. $this->settings['ruc'] . '",
        "obligado_contabilidad":'. $obligado_contabilidad. ',
        "contribuyente_especial":"'. $this->settings['contribuyente_especial'] . '",
        "nombre_comercial":"'. $this->settings['nombre_comercial'] . '",
        "razon_social":"'. $this->settings['razon_social'] . '",
        "direccion":"'. $this->settings['direccion'] . '",
        "establecimiento":{
          "punto_emision":"'. $this->settings['punto_emision'] . '",
          "codigo":"'. $this->settings['codigo'] . '",
          "direccion":"'. $this->settings['direccion'] . '"
        }
      },
      "moneda":"'.$order->data["currency"].'",
      "totales":{
        "total_sin_impuestos":'.$base_imponible.',
        "impuestos":[
          {
            "base_imponible":'.$base_imponible.',
            "valor":'.$order->data["total_tax"].',
            "codigo":"2",
            "codigo_porcentaje":"2"
          }
        ],
        "importe_total":'.$order->data["total"].',
        "propina":0.0,
        "descuento":'.$order->data["discount_total"].'
      },
      "comprador":{
        "email":"'.$order->data["billing"]["email"].'",
        "identificacion":"'.$order->get_meta('_billing_ruc').'",
        "tipo_identificacion":"'.$order->get_meta('_billing_tipo_identificacion').'",
        "razon_social":"'.$razon_social.'",
        "direccion":"'.$order->data["billing"]["address_1"]." ".$order->data["billing"]["address_2"].'",
        "telefono":"'.$order->data["billing"]["phone"].'"
      },
      "items":['; //TODO tipo_identificacion
    $items = $order->get_items();
    $totalitems = count($items);
    $i = 0;
    foreach ( $items as $item ) {

      $data .= ' {
        "cantidad":'.$item->get_quantity().',
        "precio_unitario": '.$item->get_product()->get_price().',
        "descripcion": "'.$item->get_product()->get_name().'",
        "precio_total_sin_impuestos": '.$item->get_subtotal().',
        "impuestos": [
          {
            "base_imponible":'.$base_imponible.',
            "valor":'.$item->get_total_tax().',
            "tarifa":12.0,
            "codigo":"2",
            "codigo_porcentaje":"2"
          }
        ],
        "descuento": 0.0
      }';
      if (++$i !== $totalitems) {
          // last element
          $data.= ',';
      }
    }
      $data .= '
      ],
      "pagos": [
        {
          "medio": "'.$this->clean_medio($order->data["payment_method"]).'",
          "total": '.intval($order->data["total"]).',
          "propiedades": {
          }
        }
      ]
    }';
    // use key 'http' even if you send the request to https://...
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/json\r\n".
                         "X-Key: " . $this->settings['api-key'] . "\r\n".
                         "X-Password: ". $this->settings['key-password'] . "\r\n",
            'method'  => 'POST',
            'content' => $data
        )
    );
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
      /* Handle error */
      $a = 1;
      
    }
    // var_dump($order->get_meta_data());
    // echo $data;
    // var_dump($result);
    // die();
  }

  private function clean_medio($medio){
    switch ($medio) {
      case 'cod':
        return "efectivo";
      
      default:
        return $medio;
    }
  }

  public function my_custom_billing_fields_display_admin_order_meta($order) {

    echo "<p><strong>C.I. / RUC:</strong> " .$order->get_meta('_billing_ruc') . "</p>";
    echo "<p><strong>Razón Social:</strong> " .$order->get_meta('_billing_razon_social') . "</p>";
  }

  public function my_custom_checkout_field_update_order_meta($order_id) {
    if (!empty($_POST['billing_ruc'])) {
      update_post_meta($order_id, 'C.I. / RUC', esc_attr($_POST['billing_ruc']));
    }
    if (!empty($_POST['billing_razon_social'])) {
      update_post_meta($order_id, 'Razon social', esc_attr($_POST['billing_razon_social']));
    }
    if (!empty($_POST['billing_tipo_identificacion'])) {
      update_post_meta($order_id, '_billing_tipo_identificacion', esc_attr($_POST['billing_tipo_identificacion']));
    }
  }

  public function my_custom_checkout_field_process() {
    if (!$_POST['billing_ruc']) {
       $_POST['billing_ruc'] = '99999999999';
       $_POST['billing_tipo_identificacion'] = "07";
    }elseif($_POST['billing_ruc']){
     $_POST['billing_tipo_identificacion'] = "05";
    }
  }

  public function custom_override_checkout_fields($fields) {
    $fields['billing']['billing_ruc'] = array(
      'label'     => __('C.I. / RUC', 'woocommerce'),
      'placeholder'   => _x('Leave blank for final customer', 'placeholder', 'woocommerce'),
      'required'  => false,
      'class'     => array('form-row-wide'),
      'clear'     => true
     );
     $fields['billing']['billing_razon_social'] = array(
      'label'     => __('Razón social', 'woocommerce'),
      'placeholder'   => _x('Leave blank for final customer', 'placeholder', 'woocommerce'),
      'required'  => false,
      'class'     => array('form-row-wide'),
      'clear'     => true
     );

    return $fields;
  }

  public function add_menus() {
    $plugin_name = Info::get_plugin_title();
    add_submenu_page(
        'options-general.php',
        $plugin_name,
        $plugin_name,
        'manage_options',
        $this->plugin_slug,
        [$this, 'render']
    );
  }

    /**
     * Render the view using MVC pattern.
     */
  public function render() {

    // Generate the settings fields
    $field_args = [
      [
        'label' => 'MODO DE PRUEBAS',
        'slug'=> "ambiente",
        'type' => 'checkbox'
        ]  ,
        [
          'label' => 'Api Key',
            'slug'  => 'api-key',
            'type'  => 'text'
        ] ,
        [
          'label' => 'Key password',
            'slug'  => 'key-password',
            'type'  => 'text'
        ]         ,[
        'label' => 'RUC del emisor',
        'slug'=> "ruc",
        'type' => 'text'
        ]        ,[
          'label' => 'Obligado a llevar contabilidad',
          'slug'=> "obligado_contabilidad",
          'type' => 'checkbox'
          ]        ,[
        'label' => 'Número de resolución. En blanco si no es contribuyente especial',
        'slug'=> "contribuyente_especial",
        'type' => 'text'
        ]        ,[
        'label' => 'Nombre comercial',
        'slug'=> "nombre_comercial",
        'type' => 'text'
        ]        ,[
        'label' => 'Razón social',
        'slug'=> "razon_social",
        'type' => 'text'
        ],[
        'label' => 'Dirección registrada en el SRI',
        'slug'=> "direccion",
        'type' => 'text'
        ],
        [
          'label'=>'Código numérico de 3 caracteres que representa al punto de emisión, o punto de venta. Ejemplo: 001',
        'slug'=>"punto_emision",
        'type'=>'text'
        ],
        [
        'label'=>'Código numérico de 3 caracteres que representa al establecimiento. Ejemplo: 001',
        'slug'=>"codigo",
        'type'=>'text'
        ],
        [
        'label'=>'Dirección de establecimiento registrada en el SRI',
        'slug'=>"direccion",
        'type'=>'text'
        ]
        
        // [
        //     'label' => 'Textarea Label',
        //     'slug'  => 'textarea-slug',
        //     'type'  => 'textarea'
        // ]
    ];

    // Model
    $settings = $this->settings;

    // Controller
    $fields = $this->custom_settings_fields($field_args, $settings);
    $settings_group = $this->settings_group;
    $heading = Info::get_plugin_title();
    $submit_text = esc_attr__('Submit', 'wc-dl-pl');

    // View
    require_once plugin_dir_path(dirname(__FILE__)).'admin/partials/view.php';
  }
}
