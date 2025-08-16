<?php
/**
 * Pusher.js Integration for Joy Bible Fellowship
 * Provides real-time features like live notifications and updates
 */

// Check if Composer autoloader exists before requiring it
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} else {
    // If Composer dependencies are not installed, disable Pusher functionality
    error_log("Composer dependencies not installed. Pusher functionality disabled.");
}

class ChurchPusher {
    private $pusher;
    private $isAvailable = false;
    
    public function __construct() {
        // Check if Pusher class exists (Composer dependencies installed)
        if (!class_exists('Pusher\\Pusher')) {
            error_log("Pusher library not available. Real-time features disabled.");
            $this->isAvailable = false;
            return;
        }
        
        try {
            $this->pusher = new Pusher\Pusher(
                PUSHER_KEY,
                PUSHER_SECRET,
                PUSHER_APP_ID,
                [
                    'cluster' => PUSHER_CLUSTER,
                    'useTLS' => true
                ]
            );
            $this->isAvailable = true;
        } catch (Exception $e) {
            error_log("Pusher initialization error: " . $e->getMessage());
            $this->isAvailable = false;
        }
    }
    
    public function isAvailable() {
        return $this->isAvailable;
    }
    
    /**
     * Send notification to all users
     */
    public function notifyAll($event, $data) {
        if (!$this->isAvailable) {
            return false;
        }
        
        try {
            $this->pusher->trigger('church-notifications', $event, $data);
            return true;
        } catch (Exception $e) {
            error_log("Pusher notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification to specific user
     */
    public function notifyUser($userId, $event, $data) {
        if (!$this->isAvailable) {
            return false;
        }
        
        try {
            $this->pusher->trigger("private-user-{$userId}", $event, $data);
            return true;
        } catch (Exception $e) {
            error_log("Pusher user notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send prayer request notification
     */
    public function notifyPrayerRequest($prayerData) {
        $data = [
            'type' => 'prayer_request',
            'title' => $prayerData['title'] ?? 'New Prayer Request',
            'message' => 'A new prayer request has been submitted.',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $prayerData
        ];
        
        return $this->notifyAll('prayer_request', $data);
    }
    
    /**
     * Send event notification
     */
    public function notifyEvent($eventData) {
        $data = [
            'type' => 'event_update',
            'title' => $eventData['title'] ?? 'Event Update',
            'message' => 'An event has been updated or created.',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $eventData
        ];
        
        return $this->notifyAll('event_update', $data);
    }
    
    /**
     * Send sermon notification
     */
    public function notifySermon($sermonData) {
        $data = [
            'type' => 'sermon_update',
            'title' => $sermonData['title'] ?? 'New Sermon',
            'message' => 'A new sermon has been uploaded.',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $sermonData
        ];
        
        return $this->notifyAll('sermon_update', $data);
    }
    
    /**
     * Send user registration notification
     */
    public function notifyNewUser($userData) {
        $data = [
            'type' => 'new_user',
            'title' => 'New Member Joined',
            'message' => 'A new member has joined the fellowship.',
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $userData
        ];
        
        return $this->notifyAll('new_user', $data);
    }
    
    /**
     * Get authentication data for private channels
     */
    public function getAuthData($channelName, $socketId) {
        if (!$this->isAvailable) {
            return false;
        }
        
        try {
            return $this->pusher->socket_auth($channelName, $socketId);
        } catch (Exception $e) {
            error_log("Pusher auth error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get client-side configuration
     */
    public function getClientConfig() {
        return [
            'app_id' => PUSHER_APP_ID,
            'key' => PUSHER_KEY,
            'cluster' => PUSHER_CLUSTER,
            'encrypted' => true
        ];
    }
}

/**
 * Helper function to get Pusher instance
 */
function getPusher() {
    static $pusher = null;
    if ($pusher === null) {
        $pusher = new ChurchPusher();
    }
    return $pusher;
}

/**
 * Send a quick notification
 */
function sendNotification($event, $data) {
    $pusher = getPusher();
    return $pusher->notifyAll($event, $data);
}

/**
 * Send user-specific notification
 */
function sendUserNotification($userId, $event, $data) {
    $pusher = getPusher();
    return $pusher->notifyUser($userId, $event, $data);
}
?>
