// Adobe Scripts Marketplace - Main JavaScript

// API Base URL - change for production
const API_BASE = 'api';

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    initMobileMenu();

    // Load dynamic content based on page
    loadPageContent();

    // Initialize filters on catalog page
    initFilters();

    // Initialize tabs on detail page
    initTabs();

    // Initialize lightbox for images
    initLightbox();

    // Initialize form handlers
    initForms();
});

// Determine which page we're on and load appropriate content
function loadPageContent() {
    const path = window.location.pathname;

    if (path.endsWith('index.html') || path.endsWith('/') || path === '') {
        loadHomepageContent();
    } else if (path.endsWith('scripts.html')) {
        loadScriptsPage();
    } else if (path.endsWith('script-detail.html')) {
        loadScriptDetail();
    }
}

// Homepage content loader
async function loadHomepageContent() {
    try {
        // Fetch all published scripts
        const response = await fetch(`${API_BASE}/scripts.php?action=public-list`);
        const data = await response.json();

        if (data.success && data.scripts) {
            const scripts = data.scripts;

            // Update stats
            updateHomepageStats(scripts);

            // Render featured scripts (show first 6)
            renderFeaturedScripts(scripts.slice(0, 6));

            // Update category counts
            updateCategoryCounts(scripts);
        } else {
            showNoScriptsMessage();
        }
    } catch (error) {
        console.error('Error loading homepage content:', error);
        showNoScriptsMessage();
    }
}

// Update homepage stats
function updateHomepageStats(scripts) {
    const statScripts = document.getElementById('stat-scripts');
    const statDownloads = document.getElementById('stat-downloads');

    if (statScripts) {
        statScripts.textContent = scripts.length;
    }

    if (statDownloads) {
        const totalDownloads = scripts.reduce((sum, s) => sum + (parseInt(s.downloads) || 0), 0);
        statDownloads.textContent = formatNumber(totalDownloads);
    }
}

// Update category counts
function updateCategoryCounts(scripts) {
    const counts = {
        indesign: 0,
        photoshop: 0,
        illustrator: 0
    };

    scripts.forEach(script => {
        const app = script.application.toLowerCase();
        if (counts.hasOwnProperty(app)) {
            counts[app]++;
        }
    });

    Object.keys(counts).forEach(app => {
        const element = document.getElementById(`count-${app}`);
        if (element) {
            element.textContent = `${counts[app]} Script${counts[app] !== 1 ? 's' : ''}`;
        }
    });
}

// Render featured scripts on homepage
function renderFeaturedScripts(scripts) {
    const container = document.getElementById('featured-scripts');
    if (!container) return;

    if (scripts.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No scripts available yet. Check back soon!</p>
            </div>
        `;
        return;
    }

    container.innerHTML = scripts.map(script => createScriptCard(script)).join('');
}

// Show no scripts message
function showNoScriptsMessage() {
    const container = document.getElementById('featured-scripts');
    if (container) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No scripts available yet. Check back soon!</p>
            </div>
        `;
    }
}

// Create a script card HTML
function createScriptCard(script) {
    const appConfig = getAppConfig(script.application);
    const priceDisplay = script.price_type === 'free'
        ? '<span class="text-green-600 font-semibold">FREE</span>'
        : `<span class="text-primary font-semibold">$${parseFloat(script.price).toFixed(2)}</span>`;

    const thumbnail = script.thumbnail
        ? `<img src="uploads/images/${script.thumbnail}" alt="${script.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform">`
        : `<span class="text-white text-6xl font-bold opacity-90 group-hover:scale-110 transition-transform">${appConfig.abbrev}</span>`;

    const thumbnailStyle = script.thumbnail ? '' : `background-color: ${appConfig.bgColor};`;

    return `
        <a href="script-detail.html?slug=${script.slug}" class="bg-white rounded-xl shadow-sm hover:shadow-lg transition-shadow overflow-hidden group">
            <div class="aspect-video flex items-center justify-center overflow-hidden" style="${thumbnailStyle}">
                ${thumbnail}
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${appConfig.badgeBg} ${appConfig.textColor}">
                        <i class="${appConfig.icon} mr-1"></i> ${script.application}
                    </span>
                    ${priceDisplay}
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-primary transition-colors">${escapeHtml(script.name)}</h3>
                <p class="text-gray-600 text-sm mb-4 line-clamp-2">${escapeHtml(script.short_description)}</p>
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <span><i class="fas fa-download mr-1"></i> ${formatNumber(script.downloads || 0)} downloads</span>
                    <span class="text-xs text-gray-400">v${script.version || '1.0.0'}</span>
                </div>
            </div>
        </a>
    `;
}

// Get app-specific configuration
function getAppConfig(application) {
    const configs = {
        indesign: {
            icon: 'fas fa-file-alt',
            abbrev: 'Id',
            textColor: 'text-indesign',
            badgeBg: 'bg-indesign/10',
            bgColor: '#FF3366',
            gradient: 'from-indesign/20 to-indesign/5'
        },
        photoshop: {
            icon: 'fas fa-image',
            abbrev: 'Ps',
            textColor: 'text-photoshop',
            badgeBg: 'bg-photoshop/10',
            bgColor: '#31A8FF',
            gradient: 'from-photoshop/20 to-photoshop/5'
        },
        illustrator: {
            icon: 'fas fa-pen-nib',
            abbrev: 'Ai',
            textColor: 'text-illustrator',
            badgeBg: 'bg-illustrator/10',
            bgColor: '#FF9A00',
            gradient: 'from-illustrator/20 to-illustrator/5'
        }
    };

    return configs[application.toLowerCase()] || configs.indesign;
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Scripts page loader
async function loadScriptsPage() {
    const container = document.getElementById('scripts-grid');
    if (!container) return;

    // Show loading state
    container.innerHTML = `
        <div class="col-span-full text-center py-12">
            <i class="fas fa-spinner fa-spin text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-500">Loading scripts...</p>
        </div>
    `;

    try {
        // Always fetch all scripts, filtering is done client-side
        const response = await fetch(`${API_BASE}/scripts.php?action=public-list`);
        const data = await response.json();

        if (data.success && data.scripts) {
            renderScriptsGrid(data.scripts);
            // Apply any URL-based filters after rendering
            parseUrlParams();
            // Apply current filter state
            filterScripts();
        } else {
            container.innerHTML = `
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No scripts found. Try adjusting your filters.</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading scripts:', error);
        container.innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="fas fa-exclamation-triangle text-4xl text-red-300 mb-4"></i>
                <p class="text-gray-500">Error loading scripts. Please try again later.</p>
            </div>
        `;
    }
}

// Render scripts grid
function renderScriptsGrid(scripts) {
    const container = document.getElementById('scripts-grid');
    if (!container) return;

    if (scripts.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12">
                <i class="fas fa-box-open text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No scripts found. Try adjusting your filters.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = scripts.map(script => {
        const appConfig = getAppConfig(script.application);
        const priceDisplay = script.price_type === 'free'
            ? '<span class="text-green-600 font-semibold">FREE</span>'
            : `<span class="text-primary font-semibold">$${parseFloat(script.price).toFixed(2)}</span>`;

        const thumbnail = script.thumbnail
            ? `<img src="uploads/images/${script.thumbnail}" alt="${script.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform">`
            : `<span class="text-white text-6xl font-bold opacity-90 group-hover:scale-110 transition-transform">${appConfig.abbrev}</span>`;

        const thumbnailStyle = script.thumbnail ? '' : `background-color: ${appConfig.bgColor};`;

        return `
            <a href="script-detail.html?slug=${script.slug}"
               class="script-card bg-white rounded-xl shadow-sm hover:shadow-lg transition-shadow overflow-hidden group"
               data-app="${script.application.toLowerCase()}"
               data-price="${script.price_type}"
               data-price-value="${script.price || 0}"
               data-downloads="${script.downloads || 0}"
               data-name="${escapeHtml(script.name)}"
               data-date="${new Date(script.created_at).getTime()}">
                <div class="aspect-video flex items-center justify-center overflow-hidden" style="${thumbnailStyle}">
                    ${thumbnail}
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium ${appConfig.badgeBg} ${appConfig.textColor}">
                            <i class="${appConfig.icon} mr-1"></i> ${script.application}
                        </span>
                        ${priceDisplay}
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-primary transition-colors">${escapeHtml(script.name)}</h3>
                    <p class="text-gray-600 text-sm mb-4 line-clamp-2">${escapeHtml(script.short_description)}</p>
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span><i class="fas fa-download mr-1"></i> ${formatNumber(script.downloads || 0)}</span>
                        <span class="text-xs text-gray-400">v${script.version || '1.0.0'}</span>
                    </div>
                </div>
            </a>
        `;
    }).join('');
}

// Script detail page loader
async function loadScriptDetail() {
    const params = new URLSearchParams(window.location.search);
    const slug = params.get('slug');

    if (!slug) {
        showScriptNotFound();
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/scripts.php?action=public-get&slug=${slug}`);
        const data = await response.json();

        if (data.success && data.script) {
            renderScriptDetail(data.script);
        } else {
            showScriptNotFound();
        }
    } catch (error) {
        console.error('Error loading script detail:', error);
        showScriptNotFound();
    }
}

// Render script detail
function renderScriptDetail(script) {
    // Update page title
    document.title = `${script.name} - Adoscript`;

    // Update meta description
    const metaDesc = document.querySelector('meta[name="description"]');
    if (metaDesc) {
        metaDesc.content = script.short_description;
    }

    // Update script name
    const nameEl = document.getElementById('script-name');
    if (nameEl) nameEl.textContent = script.name;

    // Update breadcrumb
    const breadcrumbName = document.getElementById('breadcrumb-name');
    if (breadcrumbName) breadcrumbName.textContent = script.name;

    // Update short description
    const shortDescEl = document.getElementById('script-short-desc');
    if (shortDescEl) shortDescEl.textContent = script.short_description;

    // Update app badge
    const appConfig = getAppConfig(script.application);
    const appBadge = document.getElementById('script-app-badge');
    if (appBadge) {
        appBadge.className = `inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${appConfig.badgeBg} ${appConfig.textColor}`;
        appBadge.innerHTML = `<i class="${appConfig.icon} mr-2"></i> ${script.application}`;
    }

    // Update version
    const versionEl = document.getElementById('script-version');
    if (versionEl) versionEl.textContent = `v${script.version || '1.0.0'}`;

    // Update downloads
    const downloadsEl = document.getElementById('script-downloads');
    if (downloadsEl) downloadsEl.textContent = formatNumber(script.downloads || 0);

    // Update price
    const priceEl = document.getElementById('script-price');
    if (priceEl) {
        if (script.price_type === 'free') {
            priceEl.textContent = 'FREE';
            priceEl.className = 'text-3xl font-bold text-green-600';
        } else {
            priceEl.textContent = `$${parseFloat(script.price).toFixed(2)}`;
            priceEl.className = 'text-3xl font-bold text-primary';
        }
    }

    // Update download button
    const downloadBtn = document.getElementById('download-btn');
    if (downloadBtn) {
        downloadBtn.dataset.scriptId = script.id;
        downloadBtn.dataset.free = script.price_type === 'free' ? 'true' : 'false';
        downloadBtn.dataset.slug = script.slug;

        if (script.price_type === 'free') {
            downloadBtn.innerHTML = '<i class="fas fa-download mr-2"></i> Download Free';
            downloadBtn.onclick = () => downloadFreeScript(script.id);
        } else {
            downloadBtn.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Buy Now';
            downloadBtn.onclick = () => {
                window.location.href = `checkout.html?slug=${script.slug}`;
            };
        }
    }

    // Update full description
    const fullDescEl = document.getElementById('script-full-desc');
    if (fullDescEl) fullDescEl.innerHTML = script.full_description || script.short_description;

    // Update installation
    const installEl = document.getElementById('script-installation');
    if (installEl) installEl.innerHTML = script.installation_instructions || 'Installation instructions will be provided with download.';

    // Update usage
    const usageEl = document.getElementById('script-usage');
    if (usageEl) usageEl.innerHTML = script.usage_instructions || 'Usage instructions will be provided with download.';

    // Update requirements
    const reqEl = document.getElementById('script-requirements');
    if (reqEl) reqEl.textContent = script.system_requirements || 'Not specified';

    // Update compatibility
    const compatEl = document.getElementById('script-compatibility');
    if (compatEl) compatEl.textContent = script.compatibility || 'Not specified';

    // Update changelog
    const changelogEl = document.getElementById('script-changelog');
    if (changelogEl) changelogEl.innerHTML = script.changelog || 'No changelog available.';

    // Update file info
    const fileSizeEl = document.getElementById('script-file-size');
    if (fileSizeEl) fileSizeEl.textContent = script.file_size || 'N/A';

    // Update sidebar info
    const appInfoEl = document.getElementById('script-app-info');
    if (appInfoEl) appInfoEl.textContent = script.application;

    const versionInfoEl = document.getElementById('script-version-info');
    if (versionInfoEl) versionInfoEl.textContent = `v${script.version || '1.0.0'}`;

    // Update images gallery
    if (script.images && script.images.length > 0) {
        renderImageGallery(script.images);
    } else {
        // Show app abbreviation placeholder when no images
        renderImagePlaceholder(script.application);
    }

    // Update videos if any
    if (script.videos && script.videos.length > 0) {
        renderVideoGallery(script.videos);
    }
}

// Render image gallery
function renderImageGallery(images) {
    const mainImage = document.getElementById('main-image');
    const placeholder = document.getElementById('main-image-placeholder');
    const thumbnails = document.getElementById('image-thumbnails');

    if (mainImage && images.length > 0) {
        mainImage.src = `uploads/images/${images[0].image_path}`;
        mainImage.alt = 'Script screenshot';
        mainImage.classList.remove('hidden');
        if (placeholder) placeholder.classList.add('hidden');
    }

    if (thumbnails && images.length > 1) {
        thumbnails.innerHTML = images.map((img, index) => `
            <button class="w-20 h-20 rounded-lg overflow-hidden border-2 ${index === 0 ? 'border-primary' : 'border-transparent'} hover:border-primary transition-colors"
                    onclick="switchImage('uploads/images/${img.image_path}', this)">
                <img src="uploads/images/${img.image_path}" alt="Thumbnail ${index + 1}" class="w-full h-full object-cover">
            </button>
        `).join('');
    }
}

// Render placeholder for scripts without images
function renderImagePlaceholder(application) {
    const container = document.querySelector('.aspect-video');
    const placeholder = document.getElementById('main-image-placeholder');
    const mainImage = document.getElementById('main-image');
    const thumbnails = document.getElementById('image-thumbnails');

    if (!container) return;

    const appConfig = getAppConfig(application);

    // Update container background with inline style
    container.className = 'aspect-video flex items-center justify-center';
    container.style.backgroundColor = appConfig.bgColor;

    // Replace icon with abbreviation
    if (placeholder) {
        placeholder.className = 'text-white text-8xl font-bold opacity-90';
        placeholder.innerHTML = appConfig.abbrev;
    }

    // Hide main image and thumbnails
    if (mainImage) mainImage.classList.add('hidden');
    if (thumbnails) thumbnails.classList.add('hidden');
}

// Switch main image
function switchImage(src, button) {
    const mainImage = document.getElementById('main-image');
    if (mainImage) {
        mainImage.src = src;
    }

    // Update thumbnail borders
    const thumbnails = document.querySelectorAll('#image-thumbnails button');
    thumbnails.forEach(btn => btn.classList.remove('border-primary'));
    if (button) button.classList.add('border-primary');
}

// Render video gallery
function renderVideoGallery(videos) {
    const container = document.getElementById('video-gallery');
    if (!container) return;

    container.innerHTML = videos.map(video => {
        // Convert YouTube URL to embed format if needed
        let embedUrl = video.video_url;
        if (embedUrl.includes('youtube.com/watch')) {
            const videoId = new URL(embedUrl).searchParams.get('v');
            embedUrl = `https://www.youtube.com/embed/${videoId}`;
        } else if (embedUrl.includes('youtu.be/')) {
            const videoId = embedUrl.split('youtu.be/')[1];
            embedUrl = `https://www.youtube.com/embed/${videoId}`;
        }

        return `
            <div class="aspect-video rounded-lg overflow-hidden">
                <iframe src="${embedUrl}" frameborder="0" allowfullscreen class="w-full h-full"></iframe>
            </div>
        `;
    }).join('');
}

// Download free script
async function downloadFreeScript(scriptId) {
    try {
        const response = await fetch(`${API_BASE}/scripts.php?action=download&id=${scriptId}`);
        const data = await response.json();

        if (data.success && data.download_url) {
            showToast('Download started!', 'success');
            window.location.href = data.download_url;
        } else {
            showToast(data.message || 'Download not available', 'error');
        }
    } catch (error) {
        console.error('Download error:', error);
        showToast('Error starting download', 'error');
    }
}

// Show script not found
function showScriptNotFound() {
    const container = document.querySelector('main') || document.body;
    container.innerHTML = `
        <div class="max-w-2xl mx-auto px-4 py-20 text-center">
            <i class="fas fa-search text-6xl text-gray-300 mb-6"></i>
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Script Not Found</h1>
            <p class="text-gray-600 mb-8">The script you're looking for doesn't exist or has been removed.</p>
            <a href="scripts.html" class="inline-flex items-center px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> Browse All Scripts
            </a>
        </div>
    `;
}

// Expose switchImage globally
window.switchImage = switchImage;

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
    const price = params.get('price');

    if (app) {
        const checkbox = document.getElementById(`app-${app}`);
        if (checkbox) {
            checkbox.checked = true;
        }
    }

    if (price) {
        const checkbox = document.getElementById(`price-${price}`);
        if (checkbox) {
            checkbox.checked = true;
        }
    }

    updateActiveFilters();
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
    // Download button is handled in renderScriptDetail()
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
