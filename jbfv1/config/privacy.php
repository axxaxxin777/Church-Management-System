<?php
/**
 * Privacy Configuration for Joy Bible Fellowship
 * Controls external requests and tracking settings
 */

// External CDN Settings
define('USE_LOCAL_FONTS', false);          // Use Google Fonts CDN
define('USE_LOCAL_ICONS', false);          // Use Font Awesome CDN
define('USE_PUSHER_CDN', true);            // Use Pusher CDN (required for real-time features)

// Analytics and Tracking
define('ENABLE_ANALYTICS', false);         // Disable analytics to reduce external requests
define('ENABLE_SOCIAL_TRACKING', false);   // Disable social media tracking

// YouTube and External Media
define('USE_YOUTUBE_EMBEDS', false);       // Disable YouTube embeds to reduce tracking
define('USE_VIMEO_EMBEDS', false);         // Disable Vimeo embeds

// Privacy Headers
define('SET_PRIVACY_HEADERS', true);       // Set privacy-focused HTTP headers

// Function to get privacy-friendly external resources
function getPrivacyFriendlyResources() {
    $resources = [];
    
    if (!USE_LOCAL_FONTS) {
        $resources['fonts'] = 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap';
    }
    
    if (!USE_LOCAL_ICONS) {
        $resources['icons'] = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css';
    }
    
    if (USE_PUSHER_CDN) {
        $resources['pusher'] = 'https://js.pusher.com/8.2.0/pusher.min.js';
    }
    
    return $resources;
}

// Function to set privacy headers
function setPrivacyHeaders() {
    if (SET_PRIVACY_HEADERS) {
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}

// Initialize privacy settings
if (SET_PRIVACY_HEADERS) {
    setPrivacyHeaders();
}
?>
