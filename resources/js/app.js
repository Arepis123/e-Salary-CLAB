import './bootstrap';

// CSRF Token Handling
document.addEventListener('DOMContentLoaded', function() {
    // Refresh CSRF token periodically (every 30 minutes)
    setInterval(refreshCsrfToken, 30 * 60 * 1000);

    // Add CSRF token to all AJAX requests
    setupAjaxErrorHandling();
});

/**
 * Refresh CSRF token from server
 */
function refreshCsrfToken() {
    fetch('/csrf-token', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.token) {
            // Update all CSRF token inputs
            document.querySelectorAll('input[name="_token"]').forEach(input => {
                input.value = data.token;
            });

            // Update meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', data.token);
            }

            console.log('CSRF token refreshed successfully');
        }
    })
    .catch(error => {
        console.warn('Failed to refresh CSRF token:', error);
    });
}

/**
 * Setup global AJAX error handling for 419 errors
 */
function setupAjaxErrorHandling() {
    // For Livewire AJAX requests
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, content }) => {
                if (status === 419) {
                    showSessionExpiredNotification();
                }
            });
        });
    });

    // For regular fetch requests
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                if (response.status === 419) {
                    showSessionExpiredNotification();
                }
                return response;
            });
    };
}

/**
 * Show user-friendly notification when session expires
 */
function showSessionExpiredNotification() {
    // Try to use Flux toast (wait for it to be available)
    if (typeof $flux !== 'undefined' && $flux.toast) {
        $flux.toast({
            heading: 'Session Expired',
            text: 'Your session has expired. The page will refresh automatically in 3 seconds.',
            variant: 'warning',
            duration: 5000
        });

        // Auto-reload after showing the message
        setTimeout(() => {
            window.location.reload();
        }, 3000);
    } else if (typeof Livewire !== 'undefined' && Livewire.dispatch) {
        // Try Livewire event approach
        Livewire.dispatch('flux:toast', {
            heading: 'Session Expired',
            text: 'Your session has expired. The page will refresh automatically.',
            variant: 'warning'
        });

        setTimeout(() => {
            window.location.reload();
        }, 3000);
    } else {
        // Create a custom toast element as final fallback
        showCustomToast();
    }
}

/**
 * Show custom toast notification (fallback when Flux is not available)
 */
function showCustomToast() {
    // Create toast container if it doesn't exist
    let container = document.getElementById('custom-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'custom-toast-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(container);
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.style.cssText = `
        background: #f59e0b;
        color: white;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 10px;
        min-width: 300px;
        animation: slideIn 0.3s ease-out;
    `;
    toast.innerHTML = `
        <div style="font-weight: 600; margin-bottom: 4px;">Session Expired</div>
        <div style="font-size: 14px;">Your session has expired. Refreshing page in 3 seconds...</div>
    `;

    // Add animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);

    container.appendChild(toast);

    // Auto-reload after 3 seconds
    setTimeout(() => {
        window.location.reload();
    }, 3000);
}

/**
 * Show session warning before expiry (optional - if you want to warn users)
 */
function showSessionWarning(minutesLeft) {
    if (window.Flux && window.Flux.toast) {
        window.Flux.toast({
            heading: 'Session Expiring Soon',
            text: `Your session will expire in ${minutesLeft} minutes. Save your work!`,
            variant: 'info',
            duration: 8000
        });
    }
}

// Optional: Warn users before session expires
// Uncomment if you want to add this feature
// setTimeout(() => showSessionWarning(5), (115 * 60 * 1000)); // 5 min warning if session is 120 min
