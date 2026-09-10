</main>

<!-- Newsletter Modal -->
<?php if (is_single() || is_page() || is_front_page()) : ?>
<div id="bm-newsletter-modal" class="bm-modal" aria-hidden="true" role="dialog">
    <div class="bm-modal-backdrop"></div>
    <div class="bm-modal-content">
        <button class="bm-modal-close" aria-label="Close newsletter signup">×</button>
        <div class="bm-modal-header">
            <h3>Join Bitmomo Newsletter</h3>
            <p>Get the latest AI & Crypto insights delivered to your inbox</p>
        </div>
        <div class="bm-modal-form">
            <?php if (shortcode_exists('mailpoet_form')) : ?>
                <?php echo do_shortcode('[mailpoet_form id="2"]'); ?>
            <?php else : ?>
                <form class="bm-fallback-form" action="#" method="post">
                    <input type="email" placeholder="Enter your email" required>
                    <button type="submit">Subscribe</button>
                    <p class="bm-form-note">We hate spam. Unsubscribe anytime.</p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.bm-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(4px);
}

.bm-modal[aria-hidden="false"] {
    display: flex;
}

.bm-modal-backdrop {
    position: absolute;
    inset: 0;
    cursor: pointer;
}

.bm-modal-content {
    position: relative;
    background: #0f2233;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    max-width: 480px;
    width: 90%;
    max-height: 80vh;
    overflow: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    transform: translateY(20px);
    opacity: 0;
    transition: all 0.3s ease;
}

.bm-modal[aria-hidden="false"] .bm-modal-content {
    transform: translateY(0);
    opacity: 1;
}

.bm-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 32px;
    height: 32px;
    border: none;
    background: rgba(255, 255, 255, 0.1);
    color: #e6f0f2;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease;
}

.bm-modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.bm-modal-header {
    padding: 32px 32px 16px;
    text-align: center;
}

.bm-modal-header h3 {
    color: #26d0c6;
    font-size: 24px;
    font-weight: 800;
    margin: 0 0 8px;
}

.bm-modal-header p {
    color: #c7d4db;
    font-size: 16px;
    margin: 0;
}

.bm-modal-form {
    padding: 16px 32px 32px;
}

.bm-fallback-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.bm-fallback-form input[type="email"] {
    padding: 12px 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: #e6f0f2;
    font-size: 16px;
}

.bm-fallback-form button {
    padding: 12px 24px;
    background: #26d0c6;
    color: #062027;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.bm-fallback-form button:hover {
    background: #19b6ad;
}

.bm-form-note {
    font-size: 12px;
    color: #a0a0a0;
    text-align: center;
    margin: 0;
}

@media (max-width: 480px) {
    .bm-modal-content {
        width: 95%;
        margin: 20px auto;
    }
    
    .bm-modal-header,
    .bm-modal-form {
        padding-left: 20px;
        padding-right: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('bm-newsletter-modal');
    const triggers = document.querySelectorAll('.js-newsletter-trigger, [href="#subscribe"], [href*="subscribe"]');
    const closeBtn = modal.querySelector('.bm-modal-close');
    const backdrop = modal.querySelector('.bm-modal-backdrop');
    
    function openModal(e) {
        e.preventDefault();
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        // Focus management
        setTimeout(() => {
            const emailInput = modal.querySelector('input[type="email"]');
            if (emailInput) emailInput.focus();
        }, 300);
    }
    
    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    
    // Bind events
    triggers.forEach(trigger => {
        trigger.addEventListener('click', openModal);
    });
    
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    
    // Keyboard handling
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') {
            closeModal();
        }
    });
    
    // Check for hash on load
    if (window.location.hash === '#subscribe' || window.location.hash === '#newsletter') {
        setTimeout(openModal, 500);
    }
});
</script>
<?php endif; ?>

<footer class="bm-footer" role="contentinfo">
    <div class="bm-container">
        <div class="bm-footer-content">
            <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
            <p>Informasi edukasi, bukan saran investasi. Risiko aset kripto tinggi. DYOR.</p>
            
            <?php if (has_nav_menu('footer')) : ?>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'menu_class' => 'bm-footer-nav',
                    'container' => false,
                    'depth' => 1,
                ]);
                ?>
            <?php endif; ?>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>