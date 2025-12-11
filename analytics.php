<?php
/**
 * Analytics and Tracking Helper
 * Add Google Analytics, Facebook Pixel, and other tracking codes
 */

function renderGoogleAnalytics($trackingId = null)
{
    require_once 'seo_config.php';

    // Use config if no tracking ID provided
    if (!$trackingId) {
        $trackingId = getGoogleAnalyticsId();
    }

    if (!$trackingId)
        return;

    echo "
    <!-- Google Analytics -->
    <script async src=\"https://www.googletagmanager.com/gtag/js?id={$trackingId}\"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{$trackingId}');
    </script>
    ";
}

function renderFacebookPixel($pixelId = null)
{
    require_once 'seo_config.php';

    // Use config if no pixel ID provided
    if (!$pixelId) {
        $pixelId = getFacebookPixelId();
    }

    if (!$pixelId)
        return;

    echo "
    <!-- Facebook Pixel -->
    <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{$pixelId}');
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height=\"1\" width=\"1\" style=\"display:none\" 
             src=\"https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1\"/>
    </noscript>
    ";
}

function trackEvent($eventName, $parameters = [])
{
    echo "<script>
        if (typeof gtag !== 'undefined') {
            gtag('event', '{$eventName}', " . json_encode($parameters) . ");
        }
        if (typeof fbq !== 'undefined') {
            fbq('track', '{$eventName}', " . json_encode($parameters) . ");
        }
    </script>";
}

// Usage examples:
// renderGoogleAnalytics('GA_TRACKING_ID');
// renderFacebookPixel('FB_PIXEL_ID');
// trackEvent('game_start', ['club_name' => 'Manchester United']);
?>