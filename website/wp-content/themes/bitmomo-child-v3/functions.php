<?php
/**
 * Bitmomo Child Theme — ULTRA v4.2
 * - Solid LCP/preload + loading policy
 * - Universal SUBSCRIBE trigger + modal MailPoet
 * - Safe assets optimization (tanpa merusak Gutenberg/Elementor)
 * - Optional critical.css inline
 * - HAMBURGER MENU INJECTION (NEW)
 */

if (!defined('ABSPATH')) exit;

define('BM_VERSION', '4.2');
define('BM_MAILPOET_FORM_ID', 2);
define('BM_ARCHIVE_POSTS_PER_PAGE', 18);
define('BM_CARD_IMAGE_WIDTH', 800);
define('BM_CARD_IMAGE_HEIGHT', 450);
define('BM_CONTENT_WIDTH', 1120);
define('BM_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

class Bitmomo_Performance_Optimizer {

    private static $instance = null;
    private $first_card_post_id = 0;
    private $performance_timer = 0;
    private $did_preload_first_card = false;
    private $did_preload_featured  = false;
    private $did_preload_hero      = false;

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->performance_timer = microtime(true);
        $this->init_hooks();
    }

    private function init_hooks() {
        // Core
        add_action('after_setup_theme',   [$this, 'theme_setup'], 5);
        add_action('wp_enqueue_scripts',  [$this, 'enqueue_styles'], 20);

        // Bloat/Assets
        add_action('init',                   [$this, 'remove_bloat'], 1);
        add_action('wp_enqueue_scripts',     [$this, 'optimize_assets'], 100);
        add_action('wp_default_scripts',     [$this, 'optimize_jquery']);

        // Images
        add_filter('wp_img_tag_add_decoding_attr',          [$this, 'set_image_decoding']);
        add_filter('wp_get_attachment_image_attributes',    [$this, 'force_image_dimensions'], 10, 3);
        add_filter('the_content',                           [$this, 'optimize_content_images'], 8);
        add_filter('the_content',                           [$this, 'set_lcp_image_priority'], 9);
        add_filter('wp_calculate_image_sizes',              [$this, 'optimize_image_sizes'], 10, 5);

        // Advanced assets
        add_filter('script_loader_tag', [$this, 'defer_javascript'], 10, 3);
        add_filter('style_loader_src',  [$this, 'filter_loader_src']);
        add_filter('script_loader_src', [$this, 'filter_loader_src']);
        add_filter('wp_resource_hints', [$this, 'add_resource_hints'], 10, 2);

        // Content/nav
        add_action('wp',                    [$this, 'detect_first_card_post']);
        add_action('wp_head',               [$this, 'preload_critical_assets'], 5);
        add_filter('post_thumbnail_html',   [$this, 'optimize_thumbnail_loading'], 10, 5);
        add_filter('the_content',           [$this, 'add_content_enhancements'], 15);
        add_action('pre_get_posts',         [$this, 'modify_archive_query']);
        add_action('template_redirect',     [$this, 'handle_subscribe_redirect']);

        // Universal subscribe triggers + fallback CSS
        add_filter('nav_menu_link_attributes', [$this, 'force_subscribe_link_attrs'], 10, 3);
        add_filter('the_content',               [$this, 'force_subscribe_links_in_content'], 11);
        add_action('wp_head',                   [$this, 'inline_img_fallback_css'], 1);

        // Modal/footer + debug
        add_action('wp_footer', [$this, 'render_mailpoet_modal'], 100);
        add_action('wp_footer', [$this, 'performance_debug'], 999);
        
        // === HAMBURGER MENU INJECTION (NEW) ===
        add_action('wp_footer', [$this, 'inject_hamburger_menu'], 98);
    }

    /* ---------- Setup ---------- */
    public function theme_setup() {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('responsive-embeds');
        add_theme_support('editor-styles');

        register_nav_menus([
            'primary' => __('Primary Menu', 'bitmomo'),
            'footer'  => __('Footer Menu', 'bitmomo')
        ]);

        add_image_size('bm-card', BM_CARD_IMAGE_WIDTH, BM_CARD_IMAGE_HEIGHT, true);

        add_theme_support('custom-logo', [
            'height'      => 40,
            'width'       => 160,
            'flex-height' => true,
            'flex-width'  => true
        ]);

        $GLOBALS['content_width'] = BM_CONTENT_WIDTH;
    }

    public function enqueue_styles() {
        // Parent (Hello Elementor) style
        wp_enqueue_style('hello-elementor-style',
            get_template_directory_uri() . '/style.css', [], null);

        // Critical CSS inline (optional)
        $critical_path = get_stylesheet_directory() . '/critical.css';
        if (file_exists($critical_path)) {
            printf("<style>%s</style>\n", @file_get_contents($critical_path));
        }

        // Child custom.css (cache-busting via mtime)
        $custom_css_path = get_stylesheet_directory() . '/custom.css';
        $custom_css_uri  = get_stylesheet_directory_uri() . '/custom.css';
        $version = $this->get_file_version($custom_css_path);
        wp_enqueue_style('bitmomo-child', $custom_css_uri, ['hello-elementor-style'], $version);
    }

    /* ---------- Bloat / Assets ---------- */
    public function remove_bloat() {
        $acts = [
            ['wp_head','print_emoji_detection_script',7],
            ['wp_print_styles','print_emoji_styles'],
            ['admin_print_scripts','print_emoji_detection_script'],
            ['admin_print_styles','print_emoji_styles'],
            ['wp_head','wp_oembed_add_discovery_links'],
            ['wp_head','rest_output_link_wp_head'],
            ['wp_head','wp_generator'],
            ['wp_head','rsd_link'],
            ['wp_head','wlwmanifest_link'],
        ];
        foreach ($acts as $a) {
            if (count($a)===3) remove_action($a[0],$a[1],$a[2]); else remove_action($a[0],$a[1]);
        }
    }

    public function optimize_assets() {
        if (is_admin()) return;

        wp_dequeue_style('hello-elementor-fonts');
        wp_dequeue_style('classic-theme-styles');

        add_filter('elementor/frontend/print_google_fonts','__return_false',99);
    }

    public function defer_javascript($tag,$handle,$src) {
        if (is_admin() || empty($src)) return $tag;

        $skip = [
            'jquery','jquery-core','jquery-migrate',
            'elementor-frontend','elementor-webpack-runtime','elementor-sticky','elementor-waypoints',
            'imagesloaded','swiper',
            'mailpoet-form','mailpoet-public','litespeed-cache',
            'wp-polyfill','wp-i18n'
        ];
        if (in_array($handle, $skip, true)) return $tag;

        if (str_contains($tag,'defer') || str_contains($tag,'async')) return $tag;

        return sprintf("<script src=\"%s\" defer></script>\n", esc_url($src));
    }

    public function filter_loader_src($src) {
        if (!$src) return $src;
        $src = remove_query_arg('ver',$src);
        if (str_contains($src, 'fonts.googleapis.com')) {
            $src = add_query_arg('display','swap',$src);
        }
        return $src;
    }

    public function optimize_jquery($scripts) {
        if (!is_admin() && isset($scripts->registered['jquery'])) {
            $deps = $scripts->registered['jquery']->deps;
            $scripts->registered['jquery']->deps = array_diff($deps, ['jquery-migrate']);
        }
    }

    public function add_resource_hints($urls,$rel) {
        if ($rel==='preconnect') {
            $urls[] = ['href'=>'https://fonts.gstatic.com','crossorigin'=>true];
            $urls[] = ['href'=>'https://cdn.bitmomo.id','crossorigin'=>true];
        }
        return $urls;
    }

    /* ---------- Images ---------- */
    public function set_image_decoding($v){ return 'async'; }

    public function force_image_dimensions($attr,$attachment,$size) {
        if (empty($attr['width']) || empty($attr['height'])) {
            $meta = wp_get_attachment_metadata($attachment);
            if (is_array($meta) && !empty($meta['width']) && !empty($meta['height'])) {
                $attr['width']  = (int)$meta['width'];
                $attr['height'] = (int)$meta['height'];
            }
        }
        return $attr;
    }

    public function optimize_content_images($content) {
        if (!$this->should_optimize_content()) return $content;
        $content = preg_replace('/<img(?![^>]+loading=)/i',  '<img loading="lazy" ',  $content);
        $content = preg_replace('/<img(?![^>]+decoding=)/i', '<img decoding="async" ', $content);
        return $content;
    }

    public function set_lcp_image_priority($content) {
        static $done=false;
        if ($done || !$this->should_optimize_content()) return $content;
        $done=true;
        return preg_replace('/<img(?![^>]*\bfetchpriority=)([^>]+)>/i',
            '<img loading="eager" fetchpriority="high"$1>', $content, 1);
    }

    public function optimize_image_sizes($sizes,$size,$src,$meta,$id) {
        $w = is_array($size) ? (int)($size[0] ?? 0) : 0;
        $is_card = (is_string($size) && $size==='bm-card') || $w>=700;
        if ($is_card) return '(max-width:640px) 92vw, (max-width:980px) 46vw, 33vw';
        return $sizes;
    }

    /* ---------- Preload & detection ---------- */
    public function detect_first_card_post() {
        global $wp_query;
        if (is_home() || is_front_page()) {
            $p = get_posts(['post_type'=>'post','posts_per_page'=>1,'orderby'=>'date','order'=>'DESC','no_found_rows'=>true,'post_status'=>'publish']);
            if (!empty($p)) $this->first_card_post_id = (int)$p[0]->ID;
        } elseif ((is_category()||is_tag()||is_archive()) && $wp_query instanceof WP_Query && !empty($wp_query->posts)) {
            $this->first_card_post_id = (int)$wp_query->posts[0]->ID;
        }
    }

    public function preload_critical_assets() {
        if (is_admin()) return;
        if (is_front_page()) $this->preload_hero_image();
        if ($this->first_card_post_id) $this->preload_first_card_image();
        $this->preload_featured_image();
    }

    private function preload_hero_image() {
        if ($this->did_preload_hero) return;
        $path = get_stylesheet_directory().'/assets/hero.webp';
        $uri  = get_stylesheet_directory_uri().'/assets/hero.webp';
        if (file_exists($path)) {
            printf("\n<link rel=\"preload\" as=\"image\" href=\"%s\" fetchpriority=\"high\">\n", esc_url($uri));
            $this->did_preload_hero = true;
        }
    }

    private function preload_first_card_image() {
        if ($this->did_preload_first_card) return;
        $tid = get_post_thumbnail_id($this->first_card_post_id);
        if ($tid) {
            $url = wp_get_attachment_image_url($tid,'large');
            if ($url) {
                printf("\n<link rel=\"preload\" as=\"image\" href=\"%s\">\n", esc_url($url));
                $this->did_preload_first_card = true;
            }
        }
    }

    private function preload_featured_image() {
        if ($this->did_preload_featured) return;
        $post_id = 0;
        if (is_front_page()) {
            $front = (int)get_option('page_on_front');
            if ($front) $post_id = $front;
        } elseif (is_singular()) {
            $post_id = get_queried_object_id();
        }
        if ($post_id && has_post_thumbnail($post_id)) {
            $id = get_post_thumbnail_id($post_id);
            $full = wp_get_attachment_image_src($id,'full');
            $srcset = wp_get_attachment_image_srcset($id,'full');
            if (!empty($full[0])) {
                printf("\n<link rel=\"preload\" as=\"image\" href=\"%s\" %s fetchpriority=\"high\">\n",
                    esc_url($full[0]),
                    $srcset ? 'imagesrcset="'.esc_attr($srcset).'" imagesizes="(max-width:768px) 92vw, (max-width:1200px) 1100px, 1200px"' : ''
                );
                $this->did_preload_featured = true;
            }
        }
    }

    public function optimize_thumbnail_loading($html,$post_id,$thumb_id,$size,$attr) {
        if ($this->first_card_post_id && (int)$post_id === $this->first_card_post_id) {
            $html = preg_replace('/<img /','<img loading="eager" fetchpriority="high" ', $html, 1);
        } else {
            $html = preg_replace('/<img(?![^>]+loading=)/','<img loading="lazy" ', $html, 1);
            $html = preg_replace('/<img(?![^>]+decoding=)/','<img decoding="async" ', $html, 1);
        }
        return $html;
    }

    /* ---------- Content Enhancements ---------- */
    public function add_content_enhancements($content) {
        if (!is_single() || !in_the_loop() || !is_main_query()) return $content;

        $cta = sprintf(
            '<div class="bm-subscribe-cta">
                <a href="#subscribe" class="bm-btn bm-btn-subscribe js-open-subscribe">🚀 %s</a>
                <p class="bm-subscribe-caption">%s</p>
            </div>',
            __('Subscribe Newsletter Bitmomo','bitmomo'),
            __('Ringkasan AI &amp; Crypto langsung ke inbox.','bitmomo')
        );
        $disc = sprintf('<div class="bm-disclaimer"><p><em>%s</em></p></div>',
            __('Informasi edukasi, bukan saran investasi. Risiko aset kripto tinggi. DYOR.','bitmomo'));

        return $content.$cta.$disc;
    }

    public function modify_archive_query($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if ($q->is_category() || $q->is_tag()) $q->set('posts_per_page', BM_ARCHIVE_POSTS_PER_PAGE);
    }

    public function handle_subscribe_redirect() {
        $req  = sanitize_text_field($_SERVER['REQUEST_URI'] ?? '');
        $path = trim(parse_url($req, PHP_URL_PATH) ?? '/', '/');
        if (strcasecmp($path,'subscribe')===0) { wp_safe_redirect(home_url('/#subscribe'),302); exit; }
    }

    /* ---------- Universal subscribe ---------- */
    public function force_subscribe_link_attrs($atts, $item, $args) {
        if (empty($atts['href'])) return $atts;
        $href = strtolower($atts['href']);
        if (strpos($href, '#subscribe') !== false || preg_match('~(^|/)subscribe/?$~', $href)) {
            $atts['href'] = '#subscribe';
            $atts['class'] = (isset($atts['class']) ? $atts['class'].' ' : '') . 'js-open-subscribe';
        }
        return $atts;
    }

    public function force_subscribe_links_in_content($html) {
        if (empty($html)) return $html;
        return preg_replace_callback(
            '~<a\s+([^>]*?\bhref=["\']?([^"\'>\s#]*?/subscribe/?|#subscribe)["\']?[^>]*)>~i',
            function($m){
                $tag = $m[0];
                $tag = preg_replace('~href=["\']?[^"\'>\s#]*?/subscribe/?["\']?~i', 'href="#subscribe"', $tag);
                if (stripos($tag,'href=')===false) $tag = str_ireplace('<a ','<a href="#subscribe" ',$tag);
                if (stripos($tag,'class=')!==false) {
                    $tag = preg_replace('~class=["\']([^"\']*)["\']~i','class="$1 js-open-subscribe"',$tag);
                } else {
                    $tag = str_ireplace('<a ','<a class="js-open-subscribe" ',$tag);
                }
                return $tag;
            }, $html
        );
    }

    /* ---------- Fallback CSS ---------- */
    public function inline_img_fallback_css() {
        echo "<style>img:not([src]),img[src=''],img[src='#']{display:none!important}</style>\n";
    }

    /* ========== HAMBURGER MENU INJECTION (NEW) ========== */
    public function inject_hamburger_menu() {
        if (is_admin()) return;
        ?>
        <script>
        (function() {
            'use strict';
            if (document.getElementById('bm-hamburger')) return;
            
            var header = document.querySelector('.bm-header .bm-container');
            if (!header) return;
            
            // Fix nested <a> in brand
            var brand = header.querySelector('.bm-brand');
            if (brand && brand.tagName === 'A') {
                var innerLink = brand.querySelector('a.custom-logo-link');
                if (innerLink) {
                    var div = document.createElement('div');
                    div.className = 'bm-brand';
                    div.innerHTML = innerLink.outerHTML;
                    brand.parentNode.replaceChild(div, brand);
                    brand = div;
                }
            }
            
            // Create hamburger
            var hamburger = document.createElement('button');
            hamburger.id = 'bm-hamburger';
            hamburger.className = 'bm-hamburger';
            hamburger.setAttribute('aria-label', 'Menu');
            hamburger.innerHTML = '<span></span><span></span><span></span>';
            
            // Insert
            var cta = header.querySelector('.bm-cta');
            if (cta) cta.before(hamburger);
            else header.appendChild(hamburger);
            
            var nav = document.querySelector('.bm-nav');
            if (!nav) return;
            
            hamburger.addEventListener('click', function() {
                var isOpen = nav.classList.toggle('open');
                hamburger.classList.toggle('active', isOpen);
                document.body.classList.toggle('menu-open', isOpen);
            });
            
            nav.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    nav.classList.remove('open');
                    hamburger.classList.remove('active');
                    document.body.classList.remove('menu-open');
                });
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    nav.classList.remove('open');
                    hamburger.classList.remove('active');
                    document.body.classList.remove('menu-open');
                }
            });
        })();
        </script>
        <?php
    }

    /* ---------- Modal ---------- */
    public function render_mailpoet_modal() {
        if (is_admin() || !shortcode_exists('mailpoet_form')) return;
        if (!$this->should_load_modal()) return;

        $form_id = BM_MAILPOET_FORM_ID; ?>
        <span id="subscribe" hidden></span><span id="newsletter" hidden></span>
        <div id="bm-subscribe-modal" aria-hidden="true" role="dialog">
            <a class="bm-subscribe-backdrop" href="#" aria-hidden="true" tabindex="-1"></a>
            <div class="bm-subscribe-dialog" role="document" aria-modal="true" tabindex="-1">
                <a class="bm-subscribe-close" href="#" data-close="1" aria-label="<?php echo esc_attr__('Tutup','bitmomo'); ?>">×</a>
                <h3 class="bm-subscribe-title"><?php echo esc_html__('Gabung Newsletter Bitmomo','bitmomo'); ?></h3>
                <div class="bm-subscribe-form"><?php echo do_shortcode("[mailpoet_form id=\"{$form_id}\"]"); ?></div>
                <p class="bm-subscribe-note"><?php echo esc_html__('Kami anti spam. Bisa unsubscribe kapan saja.','bitmomo'); ?></p>
            </div>
        </div>
        <script>
        (function(){
          'use strict';
          var m=document.getElementById('bm-subscribe-modal'); if(!m) return;
          var b=document.body;
          function open(e){ if(e&&e.preventDefault)e.preventDefault(); m.setAttribute('aria-hidden','false'); b.style.overflow='hidden';
            setTimeout(function(){var x=m.querySelector('input[type="email"]'); if(x&&x.focus)x.focus();},100); }
          function close(e){ if(e&&e.preventDefault)e.preventDefault(); m.setAttribute('aria-hidden','true'); b.style.overflow='';
            if(/#(subscribe|newsletter)$/i.test(location.hash||'')){ try{history.replaceState(null,'',location.pathname+location.search);}catch(_){}} }
          function isSub(h){ if(!h) return false; try{var u=new URL(h,location.origin); var p=(u.pathname||'').replace(/\/+$/,'').toLowerCase(), hh=(u.hash||'').toLowerCase();
            return p==='/subscribe'||hh==='#subscribe'||hh==='#newsletter'; }catch(_){ return false; } }
          document.addEventListener('click',function(e){
            var a=e.target&&e.target.closest('a[href]'); if(!a) return; var href=a.getAttribute('href')||'';
            if(a.classList.contains('js-open-subscribe')||isSub(href)){ e.preventDefault(); e.stopPropagation(); open(e); }
            if(a.matches('.bm-subscribe-close,[data-close="1"],.bm-subscribe-backdrop')) close(e);
          },true);
          document.addEventListener('keydown',function(e){ if(e.key==='Escape') close(e); });
          if(/#(subscribe|newsletter)$/i.test(location.hash||'')) open();
        })();
        </script>
        <?php
    }

    /* ---------- Debug ---------- */
    public function performance_debug() {
        if (!BM_DEBUG) return;
        $ms = (microtime(true)-$this->performance_timer)*1000;
        $mem = memory_get_peak_usage(true)/1048576;
        printf("\n<!-- Bitmomo Performance: %.2fms | Memory: %.2fMB -->\n",$ms,$mem);
    }

    /* ---------- Helpers ---------- */
    private function should_optimize_content(){ return !is_admin() && in_the_loop() && is_main_query(); }
    private function should_load_modal(){ return is_single() || is_page() || is_home() || is_front_page() || is_archive(); }
    private function get_file_version($file){ try{ return file_exists($file)? filemtime($file): BM_VERSION; }catch(Exception $e){ return BM_VERSION; } }
}

/* Boot */
Bitmomo_Performance_Optimizer::getInstance();

/* Legacy stub */
if (!function_exists('bitmomo_mailpoet_modal_footer')) {
    function bitmomo_mailpoet_modal_footer() {
        Bitmomo_Performance_Optimizer::getInstance()->render_mailpoet_modal();
    }
}

/* Health endpoints */
add_action('wp_ajax_nopriv_bitmomo_health', function(){ wp_send_json(['status'=>'ok','version'=>BM_VERSION]); });
add_action('wp_ajax_bitmomo_health',        function(){ wp_send_json(['status'=>'ok','version'=>BM_VERSION]); });