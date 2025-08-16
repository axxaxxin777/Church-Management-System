// Custom Toastify Notification System for Joy Bible Fellowship
class JoyToastify {
    constructor() {
        this.init();
    }
    
    init() {
        // Create toast container if it doesn't exist
        if (!document.getElementById('toast-container')) {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
        
        // Add CSS styles
        this.addStyles();
    }
    
    addStyles() {
        if (document.getElementById('toastify-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'toastify-styles';
        style.textContent = `
            .joy-toast {
                background: linear-gradient(135deg, #6d4c41 0%, #8d6e63 100%);
                color: white;
                padding: 16px 20px;
                border-radius: 8px;
                margin-bottom: 10px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                font-family: 'Montserrat', sans-serif;
                font-size: 14px;
                font-weight: 500;
                line-height: 1.4;
                max-width: 350px;
                pointer-events: auto;
                cursor: pointer;
                transform: translateX(400px);
                opacity: 0;
                transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                position: relative;
                overflow: hidden;
            }
            
            .joy-toast::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 3px;
                background: linear-gradient(90deg, #d7ccc8, #efebe9);
            }
            
            .joy-toast.show {
                transform: translateX(0);
                opacity: 1;
            }
            
            .joy-toast.hide {
                transform: translateX(400px);
                opacity: 0;
            }
            
            .joy-toast.success {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            }
            
            .joy-toast.error {
                background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%);
            }
            
            .joy-toast.warning {
                background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            }
            
            .joy-toast.info {
                background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
            }
            
            .joy-toast .toast-content {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            
            .joy-toast .toast-icon {
                font-size: 18px;
                flex-shrink: 0;
            }
            
            .joy-toast .toast-message {
                flex: 1;
                word-wrap: break-word;
            }
            
            .joy-toast .toast-close {
                background: rgba(255, 255, 255, 0.2);
                border: none;
                color: white;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
                transition: all 0.2s ease;
                flex-shrink: 0;
            }
            
            .joy-toast .toast-close:hover {
                background: rgba(255, 255, 255, 0.3);
                transform: scale(1.1);
            }
            
            .joy-toast .toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: rgba(255, 255, 255, 0.3);
                width: 100%;
                transform-origin: left;
                animation: toast-progress 5s linear forwards;
            }
            
            @keyframes toast-progress {
                from { transform: scaleX(1); }
                to { transform: scaleX(0); }
            }
            
            .joy-toast:hover .toast-progress {
                animation-play-state: paused;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                #toast-container {
                    top: 10px;
                    right: 10px;
                    left: 10px;
                }
                
                .joy-toast {
                    max-width: none;
                    margin-bottom: 8px;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    show(message, type = 'info', duration = 5000) {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        
        // Set toast class and type
        toast.className = `joy-toast ${type}`;
        
        // Get icon based on type
        const icon = this.getIcon(type);
        
        // Create toast content
        toast.innerHTML = `
            <div class="toast-content">
                <div class="toast-icon">${icon}</div>
                <div class="toast-message">${message}</div>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <div class="toast-progress"></div>
        `;
        
        // Add to container
        container.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                this.hide(toast);
            }, duration);
        }
        
        // Click to dismiss
        toast.addEventListener('click', (e) => {
            if (e.target !== toast.querySelector('.toast-close')) {
                this.hide(toast);
            }
        });
        
        return toast;
    }
    
    hide(toast) {
        toast.classList.add('hide');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }
    
    getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }
    
    // Convenience methods
    success(message, duration = 5000) {
        return this.show(message, 'success', duration);
    }
    
    error(message, duration = 5000) {
        return this.show(message, 'error', duration);
    }
    
    warning(message, duration = 5000) {
        return this.show(message, 'warning', duration);
    }
    
    info(message, duration = 5000) {
        return this.show(message, 'info', duration);
    }
    
    // Clear all toasts
    clear() {
        const container = document.getElementById('toast-container');
        if (container) {
            container.innerHTML = '';
        }
    }
}

// Initialize Toastify
const toastify = new JoyToastify();

// Global function for easy access
window.showToast = (message, type = 'info', duration = 5000) => {
    return toastify.show(message, type, duration);
};

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = JoyToastify;
}
