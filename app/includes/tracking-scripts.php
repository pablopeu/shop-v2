<?php
/**
 * Tracking Scripts Integration
 * Google Analytics, Facebook Pixel, Google Tag Manager
 *
 * Include this file in the <head> section of all public pages
 * Example: <?php include __DIR__ . '/includes/tracking-scripts.php'; ?>
 */

// Load analytics configuration
$analytics_config = read_json(__DIR__ . '/../config/analytics.json');

// Google Analytics 4 (GA4)
if (($analytics_config['google_analytics']['enabled'] ?? false) && !empty($analytics_config['google_analytics']['measurement_id'])) {
    $ga_id = htmlspecialchars($analytics_config['google_analytics']['measurement_id']);
    ?>
    <!-- Google Analytics 4 (GA4) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $ga_id; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo $ga_id; ?>');
    </script>
    <?php
}

// Facebook Pixel
if (($analytics_config['facebook_pixel']['enabled'] ?? false) && !empty($analytics_config['facebook_pixel']['pixel_id'])) {
    $fb_pixel_id = htmlspecialchars($analytics_config['facebook_pixel']['pixel_id']);
    ?>
    <!-- Facebook Pixel Code -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '<?php echo $fb_pixel_id; ?>');
        <?php if ($analytics_config['facebook_pixel']['track_page_view'] ?? true): ?>
        fbq('track', 'PageView');
        <?php endif; ?>
    </script>
    <noscript>
        <img height="1" width="1" style="display:none"
             src="https://www.facebook.com/tr?id=<?php echo $fb_pixel_id; ?>&ev=PageView&noscript=1"/>
    </noscript>
    <!-- End Facebook Pixel Code -->
    <?php
}

// Google Tag Manager - Head section
if (($analytics_config['google_tag_manager']['enabled'] ?? false) && !empty($analytics_config['google_tag_manager']['container_id'])) {
    $gtm_id = htmlspecialchars($analytics_config['google_tag_manager']['container_id']);
    ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo $gtm_id; ?>');</script>
    <!-- End Google Tag Manager -->
    <?php
}
?>
