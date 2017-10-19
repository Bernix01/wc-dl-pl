<?php

namespace WC_DL_PL;

/**
 * The main plugin class.
 */
class Plugin
{

  private $loader;
  private $plugin_slug;
  private $version;
  private $option_name;

  public function __construct() {
    $this->plugin_slug = Info::SLUG;
    $this->version     = Info::VERSION;
    $this->option_name = Info::OPTION_NAME;
    $this->load_dependencies();
    $this->define_admin_hooks();
    $this->define_frontend_hooks();
  }

  private function load_dependencies() {
    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-loader.php';
    require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-admin.php';
    require_once plugin_dir_path(dirname(__FILE__)) . 'frontend/class-frontend.php';
    $this->loader = new Loader();
  }

  private function define_admin_hooks() {
    $plugin_admin = new Admin($this->plugin_slug, $this->version, $this->option_name);
    $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'assets');
    $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
    $this->loader->add_action('admin_menu', $plugin_admin, 'add_menus');
    $this->loader->add_action( 'woocommerce_order_status_completed', $plugin_admin, 'submit_invoice' );
    $this->loader->add_filter( 'woocommerce_admin_order_data_after_billing_address', $plugin_admin, 'my_custom_billing_fields_display_admin_order_meta' );
    $this->loader->add_filter( 'woocommerce_checkout_update_order_meta', $plugin_admin, 'my_custom_checkout_field_update_order_meta' );
    $this->loader->add_filter( 'woocommerce_checkout_fields', $plugin_admin, 'custom_override_checkout_fields' );
    $this->loader->add_filter( 'woocommerce_checkout_process', $plugin_admin, 'my_custom_checkout_field_process' );
    $this->loader->add_action( 'woocommerce_email', $plugin_admin, 'unhook_those_pesky_emails' );
  }

  private function define_frontend_hooks() {
    $plugin_frontend = new Frontend($this->plugin_slug, $this->version, $this->option_name);
    $this->loader->add_action('wp_enqueue_scripts', $plugin_frontend, 'assets');
    $this->loader->add_action('wp_footer', $plugin_frontend, 'render');
  }

  public function run() {
    $this->loader->run();
  }
}
