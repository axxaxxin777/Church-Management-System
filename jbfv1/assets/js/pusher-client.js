/**
 * Pusher.js Client Integration for Joy Bible Fellowship
 * Provides real-time notifications and updates on the client side
 */

class ChurchPusherClient {
    constructor() {
        this.pusher = null;
        this.channel = null;
        this.userChannel = null;
        this.isConnected = false;
        this.notifications = [];
        this.maxNotifications = 10;
        
        this.init();
    }
    
    async init() {
        try {
            // Get Pusher configuration from server
            const response = await fetch('includes/pusher-config.php');
            const config = await response.json();
            
            if (config && config.key) {
                this.pusher = new Pusher(config.key, {
                    cluster: config.cluster,
                    encrypted: config.encrypted
                });
                
                this.setupChannels();
                this.setupEventHandlers();
                this.isConnected = true;
                
                console.log('Pusher connected successfully');
            }
        } catch (error) {
            console.error('Failed to initialize Pusher:', error);
        }
    }
    
    setupChannels() {
        // Main notifications channel
        this.channel = this.pusher.subscribe('church-notifications');
        
        // User-specific channel (if logged in)
        const userId = this.getUserId();
        if (userId) {
            this.userChannel = this.pusher.subscribe(`private-user-${userId}`);
        }
    }
    
    setupEventHandlers() {
        // Handle general notifications
        this.channel.bind('new_user', (data) => {
            this.showNotification('New Member Joined', `${data.first_name} ${data.last_name} has joined the fellowship!`, 'success');
        });
        
        this.channel.bind('prayer_request', (data) => {
            this.showNotification('Prayer Request', data.message, 'info');
        });
        
        this.channel.bind('event_update', (data) => {
            this.showNotification('Event Update', data.message, 'info');
        });
        
        this.channel.bind('sermon_update', (data) => {
            this.showNotification('New Sermon', data.message, 'success');
        });
        
        // Handle user-specific notifications
        if (this.userChannel) {
            this.userChannel.bind('user_notification', (data) => {
                this.showNotification(data.title, data.message, data.type || 'info');
            });
        }
    }
    
    showNotification(title, message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `pusher-notification pusher-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-header">
                <strong>${title}</strong>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
            <div class="notification-message">${message}</div>
        `;
        
        // Add to notifications container
        this.addNotification(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    addNotification(notification) {
        // Get or create notifications container
        let container = document.getElementById('pusher-notifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pusher-notifications';
            container.className = 'pusher-notifications-container';
            document.body.appendChild(container);
        }
        
        // Add notification
        container.appendChild(notification);
        
        // Limit number of notifications
        if (container.children.length > this.maxNotifications) {
            container.removeChild(container.firstChild);
        }
        
        // Add animation
        notification.style.animation = 'slideInRight 0.3s ease-out';
    }
    
    getUserId() {
        // Try to get user ID from various sources
        const userIdElement = document.querySelector('[data-user-id]');
        if (userIdElement) {
            return userIdElement.dataset.userId;
        }
        
        // Check session storage
        return sessionStorage.getItem('user_id');
    }
    
    // Send notification to server
    async sendNotification(event, data) {
        try {
            const response = await fetch('includes/send-notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event: event,
                    data: data
                })
            });
            
            return response.ok;
        } catch (error) {
            console.error('Failed to send notification:', error);
            return false;
        }
    }
    
    // Disconnect
    disconnect() {
        if (this.pusher) {
            this.pusher.disconnect();
            this.isConnected = false;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Only initialize if Pusher is available
    if (typeof Pusher !== 'undefined') {
        window.churchPusher = new ChurchPusherClient();
    }
});

// Add CSS for notifications
const style = document.createElement('style');
style.textContent = `
    .pusher-notifications-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 400px;
    }
    
    .pusher-notification {
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        margin-bottom: 10px;
        padding: 15px;
        border-left: 4px solid #6d4c41;
        animation: slideInRight 0.3s ease-out;
    }
    
    .pusher-notification-success {
        border-left-color: #28a745;
    }
    
    .pusher-notification-info {
        border-left-color: #17a2b8;
    }
    
    .pusher-notification-warning {
        border-left-color: #ffc107;
    }
    
    .pusher-notification-error {
        border-left-color: #dc3545;
    }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    
    .notification-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #666;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-close:hover {
        color: #333;
    }
    
    .notification-message {
        color: #333;
        font-size: 14px;
        line-height: 1.4;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @media (max-width: 768px) {
        .pusher-notifications-container {
            right: 10px;
            left: 10px;
            max-width: none;
        }
    }
`;

document.head.appendChild(style);
