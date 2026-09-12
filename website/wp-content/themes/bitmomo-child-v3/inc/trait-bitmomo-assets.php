<?php
/** Bitmomo Assets Trait. @package Bitmomo */
if (!defined('ABSPATH')) exit;
trait Bitmomo_Assets_Trait {
  public function theme_setup() {
    add_theme_support('title-tag');add_theme_support('post-thumbnails');add_theme_support('responsive-embeds');add_theme_support('editor-styles');
    register_nav_menus(['primary'=>__('Primary Menu','bitmomo'),'footer'=>__('Footer Menu','bitmomo')]);
    add_image_size('bm-card',BM_CARD_IMAGE_WIDTH,BM_CARD_IMAGE_HEIGHT,true);
    add_theme_support('custom-logo',['height'=>40,'width'=>160,'flex-height'=>true,'flex-width'=>true]);$GLOBALS['content_width']=BM_CONTENT_WIDTH;
  }
  /** Canonical graph: parent compatibility -> tokens -> foundation -> components -> one route owner. */
  public function enqueue_styles() {
    wp_enqueue_style('hello-elementor-style',get_template_directory_uri().'/style.css',[],null);
    $this->enqueue_theme_style('bitmomo-tokens','assets/css/tokens.css',['hello-elementor-style']);
    $this->enqueue_theme_style('bitmomo-foundation','assets/css/foundation.css',['bitmomo-tokens']);
    $this->enqueue_theme_style('bitmomo-components','assets/css/components.css',['bitmomo-foundation']);
    if (is_front_page()) {
      $this->enqueue_theme_style('bitmomo-home','assets/css/pages/home.css',['bitmomo-components']);
    } elseif (is_category('riset') || is_tag('ai-lab')) {
      $this->enqueue_theme_style('bitmomo-research','assets/css/pages/research.css',['bitmomo-components']);
    } elseif (is_single()) {
      $this->enqueue_theme_style('bitmomo-article','assets/css/pages/article.css',['bitmomo-components']);
      $this->enqueue_theme_style('bitmomo-research-shared','assets/css/pages/research.css',['bitmomo-article']);
    } elseif (!$this->is_product_surface_route()) {
      $this->enqueue_theme_style('bitmomo-public','assets/css/pages/public.css',['bitmomo-components']);
    }
    $js=get_stylesheet_directory().'/assets/js/bitmomo-frontend.js';
    if(file_exists($js)){wp_enqueue_script('bitmomo-frontend',get_stylesheet_directory_uri().'/assets/js/bitmomo-frontend.js',[],$this->get_file_version($js),true);wp_localize_script('bitmomo-frontend','bitmomoConfig',['ajaxUrl'=>admin_url('admin-ajax.php'),'ctaNonce'=>wp_create_nonce('bitmomo_cta_click')]);}
  }
  private function enqueue_theme_style($handle,$relative,$deps=[]){$path=get_stylesheet_directory().'/'.ltrim($relative,'/');if(!file_exists($path))return;wp_enqueue_style($handle,get_stylesheet_directory_uri().'/'.ltrim($relative,'/'),$deps,$this->get_file_version($path));}
  private function is_product_surface_route(){return is_page(['pro','btc-intelligence','help'])||is_page('pro/account');}
  public function remove_bloat(){foreach([['wp_head','print_emoji_detection_script',7],['wp_print_styles','print_emoji_styles'],['admin_print_scripts','print_emoji_detection_script'],['admin_print_styles','print_emoji_styles'],['wp_head','wp_oembed_add_discovery_links'],['wp_head','rest_output_link_wp_head'],['wp_head','wp_generator'],['wp_head','rsd_link'],['wp_head','wlwmanifest_link']] as $a){count($a)===3?remove_action($a[0],$a[1],$a[2]):remove_action($a[0],$a[1]);}}
  public function optimize_assets(){if(is_admin())return;wp_dequeue_style('hello-elementor-fonts');wp_dequeue_style('classic-theme-styles');add_filter('elementor/frontend/print_google_fonts','__return_false',99);}
  public function filter_loader_src($src){return $src&&str_contains($src,'fonts.googleapis.com')?add_query_arg('display','swap',$src):$src;}
  public function add_resource_hints($urls,$rel){return $urls;}
}
