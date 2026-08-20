<?php
/** Bitmomo Frontend Trait. @package Bitmomo */

if (!defined('ABSPATH')) exit;

trait Bitmomo_Frontend_Trait {
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

            var hamburger = document.getElementById('bm-hamburger');
            var nav = document.getElementById('bm-nav') || document.querySelector('.bm-nav');
            if (!hamburger || !nav) return;

            if (!nav.id) nav.id = 'bm-nav';
            hamburger.setAttribute('aria-controls', nav.id);
            hamburger.setAttribute('aria-expanded', 'false');

            function setOpen(isOpen, returnFocus) {
                nav.classList.toggle('open', isOpen);
                hamburger.classList.toggle('active', isOpen);
                document.body.classList.toggle('menu-open', isOpen);
                hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                if (!isOpen && returnFocus) hamburger.focus();
            }

            hamburger.addEventListener('click', function() {
                setOpen(hamburger.getAttribute('aria-expanded') !== 'true', false);
            });

            nav.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    setOpen(false, false);
                });
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && hamburger.getAttribute('aria-expanded') === 'true') {
                    setOpen(false, true);
                }
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) setOpen(false, false);
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
}
