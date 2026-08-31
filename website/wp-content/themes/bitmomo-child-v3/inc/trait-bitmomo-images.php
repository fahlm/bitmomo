<?php
/** Bitmomo Images Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Images_Trait {
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
        if (!$this->should_optimize_content() || !class_exists('WP_HTML_Tag_Processor')) {
            return $content;
        }

        $processor = new WP_HTML_Tag_Processor($content);
        while ($processor->next_tag('img')) {
            if ($processor->get_attribute('loading') === null) {
                $processor->set_attribute('loading', 'lazy');
            }
            if ($processor->get_attribute('decoding') === null) {
                $processor->set_attribute('decoding', 'async');
            }
        }

        return $processor->get_updated_html();
    }

    public function set_lcp_image_priority($content) {
        static $done = false;
        if ($done || !$this->should_optimize_content() || !class_exists('WP_HTML_Tag_Processor')) {
            return $content;
        }

        $processor = new WP_HTML_Tag_Processor($content);
        if (!$processor->next_tag('img')) {
            return $content;
        }

        $done = true;
        $processor->set_attribute('loading', 'eager');
        $processor->set_attribute('fetchpriority', 'high');

        return $processor->get_updated_html();
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
        if (is_front_page()) {
            // As of the customer-facing UI cleanup sprint, front-page.php no
            // longer renders the "BIG STORIES" tag grid (replaced by the
            // Research section, which is text-only -- no post thumbnails).
            // The homepage has no first-card image to preload, so this
            // intentionally leaves first_card_post_id unset here rather than
            // preloading an image that's never actually rendered.
        } elseif (is_home()) {
            $p = get_posts(['post_type'=>'post','posts_per_page'=>1,'orderby'=>'date','order'=>'DESC','no_found_rows'=>true,'post_status'=>'publish']);
            if (!empty($p)) $this->first_card_post_id = (int)$p[0]->ID;
        } elseif ((is_category()||is_tag()||is_archive()) && $wp_query instanceof WP_Query && !empty($wp_query->posts)) {
            $this->first_card_post_id = (int)$wp_query->posts[0]->ID;
        }
    }

    public function preload_critical_assets() {
        if (is_admin()) return;
        if (is_singular()) {
            $this->preload_featured_image();
        } elseif ($this->first_card_post_id) {
            $this->preload_first_card_image();
        }
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
        if (!class_exists('WP_HTML_Tag_Processor')) {
            return $html;
        }

        $processor = new WP_HTML_Tag_Processor($html);
        if (!$processor->next_tag('img')) {
            return $html;
        }

        if ($this->first_card_post_id && (int)$post_id === $this->first_card_post_id) {
            $processor->set_attribute('loading', 'eager');
            $processor->set_attribute('fetchpriority', 'high');
        } else {
            if ($processor->get_attribute('loading') === null) {
                $processor->set_attribute('loading', 'lazy');
            }
            if ($processor->get_attribute('decoding') === null) {
                $processor->set_attribute('decoding', 'async');
            }
        }

        return $processor->get_updated_html();
    }
}
