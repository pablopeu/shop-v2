/**
 * Session Monitor - Auto-redirects to login if session expires
 * This script should be included in all admin pages
 */

(function() {
    'use strict';

    // Configuration
    const CHECK_INTERVAL = 60000; // Check every 60 seconds
    const SESSION_API = 'api/check_session.php';

    let checkInProgress = false;
    let lastCheckTime = Date.now();

    /**
     * Check if session is still valid
     */
    async function checkSession() {
        // Prevent multiple simultaneous checks
        if (checkInProgress) {
            return;
        }

        checkInProgress = true;

        try {
            const response = await fetch(SESSION_API, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                credentials: 'same-origin'
            });

            if (response.status === 401) {
                // Session expired or not authenticated
                const data = await response.json().catch(() => ({ reason: 'unknown' }));
                handleSessionExpired(data.reason);
                return;
            }

            if (!response.ok) {
                console.warn('Session check failed with status:', response.status);
                // Don't redirect on network errors, just log
                return;
            }

            const data = await response.json();

            if (!data.valid) {
                handleSessionExpired(data.reason || 'unknown');
            } else {
                // Session is valid, update last check time
                lastCheckTime = Date.now();
            }

        } catch (error) {
            // Network error or server down - don't redirect, just log
            console.warn('Session check error:', error);
        } finally {
            checkInProgress = false;
        }
    }

    /**
     * Handle session expiration
     */
    function handleSessionExpired(reason) {
        console.log('Session expired:', reason);

        // Stop the interval
        if (window.sessionCheckInterval) {
            clearInterval(window.sessionCheckInterval);
        }

        // Show a brief message before redirecting
        const message = reason === 'session_expired'
            ? 'Tu sesión ha expirado por inactividad.'
            : 'Tu sesión ha finalizado.';

        // Create overlay with message
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 18px;
        `;
        overlay.innerHTML = `
            <div style="text-align: center; padding: 40px; background: #2c3e50; border-radius: 12px; max-width: 400px;">
                <div style="font-size: 48px; margin-bottom: 20px;">🔒</div>
                <div style="font-weight: 600; margin-bottom: 20px;">${message}</div>
                <div style="color: #95a5a6;">Redirigiendo al login...</div>
            </div>
        `;
        document.body.appendChild(overlay);

        // Redirect to login after showing message
        setTimeout(() => {
            const basePath = window.BASE_PATH || '';
            window.location.href = basePath + '/admin/login.php?timeout=1';
        }, 1500);
    }

    /**
     * Check session on user activity
     */
    function checkOnActivity() {
        const timeSinceLastCheck = Date.now() - lastCheckTime;

        // Only check if it's been more than 30 seconds since last check
        if (timeSinceLastCheck > 30000) {
            checkSession();
        }
    }

    /**
     * Initialize session monitoring
     */
    function initSessionMonitor() {
        // Initial check
        checkSession();

        // Periodic check
        window.sessionCheckInterval = setInterval(checkSession, CHECK_INTERVAL);

        // Check on user activity (debounced)
        let activityTimeout;
        const activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart'];

        activityEvents.forEach(event => {
            document.addEventListener(event, () => {
                clearTimeout(activityTimeout);
                activityTimeout = setTimeout(checkOnActivity, 2000);
            }, { passive: true });
        });

        // Check when tab becomes visible again
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                checkOnActivity();
            }
        });

        // Check when window regains focus
        window.addEventListener('focus', () => {
            checkOnActivity();
        });
    }

    // Start monitoring when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSessionMonitor);
    } else {
        initSessionMonitor();
    }

    // Expose check function for manual calls
    window.checkAdminSession = checkSession;

})();
