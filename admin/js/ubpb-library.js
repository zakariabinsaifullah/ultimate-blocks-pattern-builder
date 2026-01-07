jQuery(document).ready(function ($) {
    if (!$('#ubpb-library-app').length) return;

    // State
    var allPatterns = ubpbLibraryConfig.patterns || [];
    var filteredPatterns = [];
    var activeCategory = 'all';
    var searchQuery = '';
    var currentPage = 1;
    var itemsPerPage = 12;

    // Elements
    var $app = $('#ubpb-library-app');
    var $refreshBtn = $('#ubpb-library-refresh');

    // Init
    init();

    function init() {
        if (!Array.isArray(allPatterns)) {
            // Handle edge case where API might fail returning array
            allPatterns = [];
        }
        applyFilters();
    }

    // --- Render Logic ---

    function renderApp() {
        if (allPatterns.length === 0 && !searchQuery) {
            $app.html(`<div class="ubpb-empty-state">${ubpbLibraryConfig.strings.noResults || 'No patterns loaded. Try refreshing.'}</div>`);
            return;
        }

        var controlsHtml = renderControls();
        var gridHtml = renderGrid();
        var paginationHtml = renderPagination();

        $app.html(controlsHtml + gridHtml + paginationHtml);

        bindEvents();
    }

    function renderControls() {
        // Extract Categories
        var categories = ['all'];
        allPatterns.forEach(function (p) {
            if (p.categories && Array.isArray(p.categories)) {
                p.categories.forEach(c => {
                    if (!categories.includes(c)) categories.push(c);
                });
            }
        });

        var catsHtml = categories.map(function (cat) {
            var label = cat === 'all' ? ubpbLibraryConfig.strings.allCategories : cat;
            var isActive = activeCategory === cat ? 'active' : '';
            return `<button class="ubpb-filter-btn ${isActive}" data-cat="${cat}">${label}</button>`;
        }).join('');

        return `
            <div class="ubpb-library-controls">
                <div class="ubpb-cat-filters">
                    ${catsHtml}
                </div>
                <div class="ubpb-search-box">
                    <span class="dashicons dashicons-search"></span>
                    <input type="text" id="ubpb-search-input" placeholder="${ubpbLibraryConfig.strings.searchPlaceholder}" value="${searchQuery}">
                </div>
            </div>
        `;
    }

    function renderGrid() {
        if (filteredPatterns.length === 0) {
            return `<div class="ubpb-empty-state">${ubpbLibraryConfig.strings.noResults}</div>`;
        }

        // Pagination Slice
        var start = (currentPage - 1) * itemsPerPage;
        var end = start + itemsPerPage;
        var pageItems = filteredPatterns.slice(start, end);

        var cardsHtml = pageItems.map(function (pattern) {
            var thumb = pattern.thumbnail ? pattern.thumbnail : '';
            var cats = pattern.categories ? pattern.categories.join(', ') : '';
            var isPro = pattern.type === 'pro';

            var actionBtn = '';

            if (isPro) {
                actionBtn = `
                    <a href="https://gutenlayouts.com/#price" target="_blank" class="button ubpb-pro-btn">
                        <span class="dashicons dashicons-lock"></span> ${ubpbLibraryConfig.strings.unlockPro}
                    </a>
                 `;
            } else {
                actionBtn = `
                    <button class="button button-primary ubpb-add-btn" data-id="${pattern.id}">
                        ${ubpbLibraryConfig.strings.import}
                    </button>
                 `;
            }

            return `
                <div class="ubpb-pattern-card ${isPro ? 'ubpb-is-pro' : ''}" data-id="${pattern.id}">
                    <div class="ubpb-card-thumb">
                        ${isPro ? '<span class="ubpb-pro-badge">PRO</span>' : ''}
                        ${thumb ? `<img src="${thumb}" alt="${pattern.title}" loading="lazy">` : ''}
                    </div>
                    <div class="ubpb-card-body">
                        <div class="ubpb-card-meta">${cats}</div>
                        <h3 class="ubpb-card-title">${pattern.title}</h3>
                        <div class="ubpb-card-actions">
                            ${actionBtn}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        return `<div class="ubpb-patterns-grid">${cardsHtml}</div>`;
    }

    function renderPagination() {
        var totalPages = Math.ceil(filteredPatterns.length / itemsPerPage);
        if (totalPages <= 1) return '';

        var btns = '';

        // Prev
        btns += `<button class="ubpb-page-btn" data-page="prev" ${currentPage === 1 ? 'disabled' : ''}>&lsaquo;</button>`;

        // Simple Pagination Logic (show first, last, current, neighbours)
        // For simplicity, showing a simplified range or all if few.

        for (var i = 1; i <= totalPages; i++) {
            // Show first, last, current, and +/- 1 neighbors
            if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                var active = i === currentPage ? 'active' : '';
                btns += `<button class="ubpb-page-btn ${active}" data-page="${i}">${i}</button>`;
            } else if (i === currentPage - 2 || i === currentPage + 2) {
                btns += `<span class="ubpb-page-dots">...</span>`;
            }
        }

        // Next
        btns += `<button class="ubpb-page-btn" data-page="next" ${currentPage === totalPages ? 'disabled' : ''}>&rsaquo;</button>`;

        return `<div class="ubpb-pagination">${btns}</div>`;
    }

    // --- Events & Logic ---

    function applyFilters() {
        filteredPatterns = allPatterns.filter(function (p) {
            var matchCat = activeCategory === 'all' || (p.categories && p.categories.includes(activeCategory));
            var matchSearch = !searchQuery || p.title.toLowerCase().indexOf(searchQuery.toLowerCase()) > -1;
            return matchCat && matchSearch;
        });

        // Reset page if filtered results are less than current page start
        var totalPages = Math.ceil(filteredPatterns.length / itemsPerPage);
        if (currentPage > totalPages) currentPage = 1;
        if (currentPage < 1) currentPage = 1;

        renderApp();
    }

    function bindEvents() {
        // Category change
        $('.ubpb-filter-btn').off('click').on('click', function () {
            activeCategory = $(this).data('cat');
            currentPage = 1;
            applyFilters();
        });

        // Search
        var searchTimeout;
        $('#ubpb-search-input').off('input').on('input', function () {
            var val = $(this).val();
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                searchQuery = val.trim();
                currentPage = 1;
                applyFilters();
            }, 300);
        });

        // Keep focus on search input after render (hacky but needed since we re-render full HTML)
        // Better approach: don't re-render controls, only grid.
        // But for this task, I'll just restore focus if it was focused.
        // Actually, simplest is to use delegation for grid/pagination and separate controls render.
        // REFACTOR: Separate render functions updates.
    }

    // Improved Event Binding using delegation to avoid re-binding issues
    $app.on('click', '.ubpb-filter-btn', function () {
        activeCategory = $(this).data('cat');
        currentPage = 1;
        // Optimization: update active class only? No, filtering changes grid.
        // Re-render whole app is easiest but kills focus.
        // We will separate rendering of Grid vs Controls.
        updateGridAndPagination();
        updateControlsState();
    });

    $app.on('input', '#ubpb-search-input', function () {
        var val = $(this).val();
        searchQuery = val.trim();
        currentPage = 1;
        updateGridAndPagination();
    });

    $app.on('click', '.ubpb-page-btn', function () {
        var page = $(this).data('page');
        var totalPages = Math.ceil(filteredPatterns.length / itemsPerPage);

        if (page === 'prev') {
            if (currentPage > 1) currentPage--;
        } else if (page === 'next') {
            if (currentPage < totalPages) currentPage++;
        } else {
            currentPage = parseInt(page);
        }
        updateGridAndPagination();
        // Scroll to top of grid
        $('html, body').animate({
            scrollTop: $(".ubpb-library-wrapper").offset().top
        }, 300);
    });

    $app.on('click', '.ubpb-add-btn', function (e) {
        e.preventDefault();
        var id = $(this).data('id');
        var pattern = allPatterns.find(p => p.id == id);

        if (!pattern) return;

        var $btn = $(this);
        var originalText = $btn.text();

        $btn.prop('disabled', true).text(ubpbLibraryConfig.strings.importing);

        $.post(ubpbLibraryConfig.ajaxUrl, {
            action: 'ubpb_import_library_pattern',
            nonce: ubpbLibraryConfig.nonce,
            title: pattern.title,
            content: pattern.content
        }, function (response) {
            if (response.success) {
                $btn.text(ubpbLibraryConfig.strings.imported);
                $btn.replaceWith(`<a href="${response.data.editLink}" class="button button-secondary ubpb-view-btn" target="_blank">${ubpbLibraryConfig.strings.view}</a>`);
            } else {
                $btn.text(ubpbLibraryConfig.strings.error);
                setTimeout(() => $btn.prop('disabled', false).text(originalText), 2000);
                alert(response.data);
            }
        }).fail(function () {
            $btn.text(ubpbLibraryConfig.strings.error);
            setTimeout(() => $btn.prop('disabled', false).text(originalText), 2000);
        });
    });

    $refreshBtn.on('click', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.addClass('updating').prop('disabled', true);
        $app.find('.ubpb-patterns-grid').css('opacity', '0.5');

        $.post(ubpbLibraryConfig.ajaxUrl, {
            action: 'ubpb_refresh_library',
            nonce: ubpbLibraryConfig.nonce
        }, function (response) {
            $btn.removeClass('updating').prop('disabled', false);
            if (response.success) {
                allPatterns = response.data;
                applyFilters(); // Re-runs filtering and rendering
                // Flash success?
            } else {
                alert('Failed to refresh.');
            }
        });
    });

    // Initial Render call split
    // Overwrite the monolithic renderApp to be smarter
    function renderInitial() {
        $app.html(`
            <div id="ubpb-controls-container"></div>
            <div id="ubpb-grid-container"></div>
            <div id="ubpb-pagination-container"></div>
        `);
        renderControlsContainer();
        updateGridAndPagination();
    }

    function renderControlsContainer() {
        $('#ubpb-controls-container').html(renderControls());
    }

    function updateControlsState() {
        // Just update active classes on buttons
        $('.ubpb-filter-btn').removeClass('active');
        $(`.ubpb-filter-btn[data-cat="${activeCategory}"]`).addClass('active');
    }

    function updateGridAndPagination() {
        // Re-filter first
        filteredPatterns = allPatterns.filter(function (p) {
            var matchCat = activeCategory === 'all' || (p.categories && p.categories.includes(activeCategory));
            var matchSearch = !searchQuery || p.title.toLowerCase().indexOf(searchQuery.toLowerCase()) > -1;
            return matchCat && matchSearch;
        });

        // Reset page if needed (logic was in applyFilters but we need it here if we skip applyFilters)
        // But here we just update view based on current state.
        // Actually filtering should happen before this fn call or inside. 
        // Let's keep filter logic separate or inside.
        // For simplicity:

        $('#ubpb-grid-container').html(renderGrid());
        $('#ubpb-pagination-container').html(renderPagination());
    }

    // Start
    if (Array.isArray(allPatterns)) {
        renderInitial();
    } else {
        // If data empty/loading
        $app.html(`<div class="ubpb-empty-state">No data available. <br><br> <button class="button" onclick="location.reload()">Reload Page</button></div>`);
    }

});
