// Adobe Scripts Marketplace - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    initMobileMenu();

    // Initialize filters on catalog page
    initFilters();

    // Initialize tabs on detail page
    initTabs();

    // Initialize lightbox for images
    initLightbox();

    // Initialize form handlers
    initForms();

    // Parse URL parameters for filters
    parseUrlParams();
});

// Mobile Menu
function initMobileMenu() {
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            const icon = mobileMenuBtn.querySelector('i');
            if (mobileMenu.classList.contains('hidden')) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
        });
    }
}

// Filters for catalog page
function initFilters() {
    const filterForm = document.getElementById('filter-form');
    const filterToggle = document.getElementById('filter-toggle');
    const filterSidebar = document.getElementById('filter-sidebar');
    const filterOverlay = document.getElementById('filter-overlay');
    const closeFilter = document.getElementById('close-filter');
    const resetFilters = document.getElementById('reset-filters');
    const sortSelect = document.getElementById('sort-select');
    const viewToggle = document.querySelectorAll('.view-toggle');
    const scriptsGrid = document.getElementById('scripts-grid');

    // Mobile filter toggle
    if (filterToggle && filterSidebar) {
        filterToggle.addEventListener('click', function() {
            filterSidebar.classList.add('open');
            if (filterOverlay) filterOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    if (closeFilter) {
        closeFilter.addEventListener('click', closeFilterSidebar);
    }

    if (filterOverlay) {
        filterOverlay.addEventListener('click', closeFilterSidebar);
    }

    function closeFilterSidebar() {
        if (filterSidebar) filterSidebar.classList.remove('open');
        if (filterOverlay) filterOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Reset filters
    if (resetFilters && filterForm) {
        resetFilters.addEventListener('click', function() {
            filterForm.reset();
            updateActiveFilters();
            filterScripts();
        });
    }

    // Filter change handlers
    if (filterForm) {
        filterForm.addEventListener('change', function() {
            updateActiveFilters();
            filterScripts();
        });
    }

    // Sort change handler
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortScripts(this.value);
        });
    }

    // View toggle (grid/list)
    viewToggle.forEach(btn => {
        btn.addEventListener('click', function() {
            viewToggle.forEach(b => b.classList.remove('active', 'bg-gray-200'));
            this.classList.add('active', 'bg-gray-200');

            const view = this.dataset.view;
            if (scriptsGrid) {
                if (view === 'list') {
                    scriptsGrid.classList.remove('md:grid-cols-2', 'lg:grid-cols-3');
                    scriptsGrid.classList.add('grid-cols-1');
                } else {
                    scriptsGrid.classList.remove('grid-cols-1');
                    scriptsGrid.classList.add('md:grid-cols-2', 'lg:grid-cols-3');
                }
            }
        });
    });
}

function updateActiveFilters() {
    const activeFiltersContainer = document.getElementById('active-filters');
    if (!activeFiltersContainer) return;

    const checkedFilters = document.querySelectorAll('#filter-form input:checked');
    const tags = [];

    checkedFilters.forEach(filter => {
        const label = filter.nextElementSibling?.textContent || filter.value;
        tags.push(`
            <span class="inline-flex items-center px-3 py-1 bg-primary/10 text-primary text-sm rounded-full">
                ${label}
                <button class="ml-2 hover:text-primary-dark" onclick="removeFilter('${filter.id}')">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </span>
        `);
    });

    activeFiltersContainer.innerHTML = tags.join('');
}

function removeFilter(filterId) {
    const filter = document.getElementById(filterId);
    if (filter) {
        filter.checked = false;
        updateActiveFilters();
        filterScripts();
    }
}

function filterScripts() {
    const scripts = document.querySelectorAll('.script-card');
    const appFilters = getCheckedValues('app');
    const priceFilters = getCheckedValues('price');

    scripts.forEach(script => {
        const app = script.dataset.app;
        const price = script.dataset.price;

        let showApp = appFilters.length === 0 || appFilters.includes(app);
        let showPrice = priceFilters.length === 0 || priceFilters.includes(price);

        if (showApp && showPrice) {
            script.classList.remove('hidden');
        } else {
            script.classList.add('hidden');
        }
    });

    updateResultsCount();
}

function getCheckedValues(name) {
    const checkboxes = document.querySelectorAll(`input[name="${name}"]:checked`);
    return Array.from(checkboxes).map(cb => cb.value);
}

function sortScripts(sortBy) {
    const container = document.getElementById('scripts-grid');
    if (!container) return;

    const scripts = Array.from(container.querySelectorAll('.script-card'));

    scripts.sort((a, b) => {
        switch (sortBy) {
            case 'newest':
                return parseInt(b.dataset.date) - parseInt(a.dataset.date);
            case 'popular':
                return parseInt(b.dataset.downloads) - parseInt(a.dataset.downloads);
            case 'price-low':
                return parseFloat(a.dataset.priceValue) - parseFloat(b.dataset.priceValue);
            case 'price-high':
                return parseFloat(b.dataset.priceValue) - parseFloat(a.dataset.priceValue);
            case 'name-az':
                return a.dataset.name.localeCompare(b.dataset.name);
            case 'name-za':
                return b.dataset.name.localeCompare(a.dataset.name);
            default:
                return 0;
        }
    });

    scripts.forEach(script => container.appendChild(script));
}

function updateResultsCount() {
    const countElement = document.getElementById('results-count');
    const visibleScripts = document.querySelectorAll('.script-card:not(.hidden)');
    const totalScripts = document.querySelectorAll('.script-card');

    if (countElement) {
        countElement.textContent = `Showing ${visibleScripts.length} of ${totalScripts.length} scripts`;
    }
}

function parseUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const app = params.get('app');

    if (app) {
        const checkbox = document.getElementById(`app-${app}`);
        if (checkbox) {
            checkbox.checked = true;
            updateActiveFilters();
            filterScripts();
        }
    }
}

// Tabs for detail page
function initTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.dataset.tab;

            // Update buttons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            // Update content
            tabContents.forEach(content => {
                if (content.id === tabId) {
                    content.classList.add('active');
                } else {
                    content.classList.remove('active');
                }
            });
        });
    });
}

// Lightbox for images
function initLightbox() {
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const closeLightbox = document.getElementById('close-lightbox');
    const galleryImages = document.querySelectorAll('.gallery-image');

    galleryImages.forEach(img => {
        img.addEventListener('click', function() {
            if (lightbox && lightboxImg) {
                lightboxImg.src = this.src;
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });

    if (closeLightbox) {
        closeLightbox.addEventListener('click', closeLightboxModal);
    }

    if (lightbox) {
        lightbox.addEventListener('click', function(e) {
            if (e.target === lightbox) {
                closeLightboxModal();
            }
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightboxModal();
        }
    });

    function closeLightboxModal() {
        if (lightbox) {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
}

// Form handlers
function initForms() {
    // Download button handler
    const downloadBtn = document.getElementById('download-btn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function(e) {
            if (this.dataset.free === 'true') {
                showToast('Download started!', 'success');
                // In real implementation, trigger file download
            } else {
                // Show payment modal or redirect to checkout
                window.location.href = 'checkout.html?script=' + this.dataset.scriptId;
            }
        });
    }

    // Admin forms
    initAdminForms();
}

function initAdminForms() {
    // Price type toggle
    const priceTypeRadios = document.querySelectorAll('input[name="price_type"]');
    const priceInput = document.getElementById('price-input-container');

    priceTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (priceInput) {
                if (this.value === 'paid') {
                    priceInput.classList.remove('hidden');
                } else {
                    priceInput.classList.add('hidden');
                }
            }
        });
    });

    // File upload drag and drop
    const uploadZones = document.querySelectorAll('.upload-zone');

    uploadZones.forEach(zone => {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        zone.addEventListener('dragleave', function() {
            this.classList.remove('dragover');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            const files = e.dataTransfer.files;
            handleFileUpload(files, this);
        });
    });

    // File input change
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const zone = this.closest('.upload-zone');
            if (zone && this.files.length > 0) {
                handleFileUpload(this.files, zone);
            }
        });
    });
}

function handleFileUpload(files, zone) {
    const preview = zone.querySelector('.upload-preview');
    if (!preview) return;

    Array.from(files).forEach(file => {
        const reader = new FileReader();

        reader.onload = function(e) {
            if (file.type.startsWith('image/')) {
                const img = document.createElement('div');
                img.className = 'relative inline-block m-1';
                img.innerHTML = `
                    <img src="${e.target.result}" alt="${file.name}" class="w-24 h-24 object-cover rounded-lg">
                    <button class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                preview.appendChild(img);
            } else {
                const fileItem = document.createElement('div');
                fileItem.className = 'flex items-center justify-between p-2 bg-gray-50 rounded-lg m-1';
                fileItem.innerHTML = `
                    <div class="flex items-center">
                        <i class="fas fa-file text-gray-400 mr-2"></i>
                        <span class="text-sm text-gray-600">${file.name}</span>
                    </div>
                    <button class="text-red-500 hover:text-red-600" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                preview.appendChild(fileItem);
            }
        };

        reader.readAsDataURL(file);
    });
}

// Toast notifications
function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle';
    const iconColor = type === 'success' ? 'text-green-500' : type === 'error' ? 'text-red-500' : 'text-yellow-500';

    toast.innerHTML = `
        <i class="fas fa-${icon} ${iconColor} text-xl"></i>
        <span class="text-gray-700">${message}</span>
        <button class="ml-auto text-gray-400 hover:text-gray-600" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    container.appendChild(toast);

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

// Utility functions
function formatPrice(price) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(price);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

// Expose functions globally for inline handlers
window.removeFilter = removeFilter;
window.showToast = showToast;
