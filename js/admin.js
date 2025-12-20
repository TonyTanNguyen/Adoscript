/**
 * Admin Panel JavaScript
 * Shared functionality for all admin pages
 */

// API base URL
const API_BASE = '../api';

// Check authentication on page load
async function checkAuth() {
    try {
        const response = await fetch(`${API_BASE}/auth.php?action=check`);
        const data = await response.json();

        if (!data.authenticated) {
            window.location.href = 'login.html';
            return false;
        }

        // Update user info in header if exists
        const userName = document.querySelector('.admin-user-name');
        if (userName && data.user) {
            userName.textContent = data.user.name || data.user.email;
        }

        return true;
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'login.html';
        return false;
    }
}

// Logout function
async function logout() {
    try {
        await fetch(`${API_BASE}/auth.php?action=logout`);
    } catch (error) {
        console.error('Logout error:', error);
    }
    window.location.href = 'login.html';
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 transform transition-all duration-300 ${
        type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-blue-600'
    } text-white`;
    toast.innerHTML = `
        <div class="flex items-center gap-2">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    document.body.appendChild(toast);

    // Animate in
    setTimeout(() => toast.classList.add('translate-y-0', 'opacity-100'), 10);

    // Remove after 3 seconds
    setTimeout(() => {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Format price
function formatPrice(price, priceType) {
    if (priceType === 'free' || price === 0) {
        return '<span class="text-green-600 font-medium">FREE</span>';
    }
    return `<span class="text-primary font-medium">$${parseFloat(price).toFixed(2)}</span>`;
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Get application badge HTML
function getAppBadge(app) {
    const colors = {
        indesign: 'bg-pink-100 text-pink-700',
        photoshop: 'bg-blue-100 text-blue-700',
        illustrator: 'bg-orange-100 text-orange-700'
    };
    const names = {
        indesign: 'InDesign',
        photoshop: 'Photoshop',
        illustrator: 'Illustrator'
    };
    return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors[app] || 'bg-gray-100 text-gray-700'}">${names[app] || app}</span>`;
}

// Get status badge HTML
function getStatusBadge(status) {
    if (status === 'published') {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Published</span>';
    }
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Draft</span>';
}

// Upload file helper
async function uploadFile(file, type = 'script') {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(`${API_BASE}/upload.php?action=${type}`, {
        method: 'POST',
        body: formData
    });

    return await response.json();
}

// Upload multiple images
async function uploadImages(files) {
    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }

    const response = await fetch(`${API_BASE}/upload.php?action=images`, {
        method: 'POST',
        body: formData
    });

    return await response.json();
}

// Fetch scripts from API
async function fetchScripts(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const response = await fetch(`${API_BASE}/scripts.php?action=list&${queryString}`);
    return await response.json();
}

// Fetch single script
async function fetchScript(id) {
    const response = await fetch(`${API_BASE}/scripts.php?action=get&id=${id}`);
    return await response.json();
}

// Create script
async function createScript(data) {
    const response = await fetch(`${API_BASE}/scripts.php?action=create`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
    return await response.json();
}

// Update script
async function updateScript(id, data) {
    const response = await fetch(`${API_BASE}/scripts.php?action=update&id=${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
    return await response.json();
}

// Delete script
async function deleteScript(id) {
    const response = await fetch(`${API_BASE}/scripts.php?action=delete&id=${id}`, {
        method: 'POST'
    });
    return await response.json();
}

// Toggle script status
async function toggleScriptStatus(id) {
    const response = await fetch(`${API_BASE}/scripts.php?action=toggle-status&id=${id}`, {
        method: 'POST'
    });
    return await response.json();
}

// Fetch dashboard stats
async function fetchStats() {
    const response = await fetch(`${API_BASE}/scripts.php?action=stats`);
    return await response.json();
}

// Fetch orders
async function fetchOrders(params = {}) {
    const queryString = new URLSearchParams(params).toString();
    const response = await fetch(`${API_BASE}/orders.php?action=list&${queryString}`);
    return await response.json();
}

// Fetch single order
async function fetchOrder(id) {
    const response = await fetch(`${API_BASE}/orders.php?action=get&id=${id}`);
    return await response.json();
}

// Fetch order stats
async function fetchOrderStats() {
    const response = await fetch(`${API_BASE}/orders.php?action=stats`);
    return await response.json();
}

// Initialize auth check on page load
document.addEventListener('DOMContentLoaded', () => {
    // Skip auth check on login page
    if (!window.location.pathname.includes('login.html')) {
        checkAuth();
    }

    // Add logout handler
    const logoutLinks = document.querySelectorAll('[data-logout], a[title="Logout"]');
    logoutLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            logout();
        });
    });
});
