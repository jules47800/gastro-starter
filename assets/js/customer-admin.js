(function() {
    'use strict';

    let currentSegment = 'all';
    let currentSort = 'last_visit';
    let currentSearch = '';
    let currentPage = 1;
    let searchTimeout = null;

    document.addEventListener('DOMContentLoaded', function() {
        const grid = document.getElementById('customers-grid');
        if (!grid) return;

        loadCustomers();
        bindEvents();
    });

    function bindEvents() {
        // KPI boxes filter
        document.querySelectorAll('.kpi-box').forEach(function(box) {
            box.addEventListener('click', function() {
                document.querySelectorAll('.kpi-box').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                currentSegment = this.dataset.segment;
                currentPage = 1;
                loadCustomers();
            });
        });

        // Search
        var searchInput = document.getElementById('customer-search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    currentSearch = searchInput.value;
                    currentPage = 1;
                    loadCustomers();
                }, 300);
            });
        }

        // Sort
        var sortSelect = document.getElementById('customer-sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                currentSort = this.value;
                currentPage = 1;
                loadCustomers();
            });
        }

        // Panel close
        var panelClose = document.getElementById('panel-close');
        var panelOverlay = document.getElementById('panel-overlay');
        if (panelClose) panelClose.addEventListener('click', closePanel);
        if (panelOverlay) panelOverlay.addEventListener('click', closePanel);

        // Export CSV
        var btnExport = document.getElementById('btn-export-csv');
        if (btnExport) {
            btnExport.addEventListener('click', exportCSV);
        }

        // Add customer modal
        var btnAdd = document.getElementById('btn-add-customer');
        var modalOverlay = document.getElementById('add-customer-overlay');
        var modalClose = document.getElementById('modal-close-customer');
        var modalCancel = document.getElementById('modal-cancel-customer');
        var addForm = document.getElementById('add-customer-form');

        if (btnAdd) {
            btnAdd.addEventListener('click', openAddCustomerModal);
            modalOverlay.addEventListener('click', closeAddCustomerModal);
            modalClose.addEventListener('click', closeAddCustomerModal);
            modalCancel.addEventListener('click', closeAddCustomerModal);
            addForm.addEventListener('submit', submitAddCustomer);
        }
    }

    function loadCustomers() {
        var grid = document.getElementById('customers-grid');
        grid.innerHTML = '<div class="customers-loading">Chargement...</div>';

        var data = new FormData();
        data.append('action', 'gastro_starter_customer_search');
        data.append('nonce', gastro_starter_customers.nonce);
        data.append('search', currentSearch);
        data.append('segment', currentSegment);
        data.append('sort', currentSort);
        data.append('page', currentPage);

        fetch(gastro_starter_customers.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                grid.innerHTML = response.data.html;
                renderPagination(response.data.pages, response.data.page);
                bindCardEvents();
            }
        });
    }

    function bindCardEvents() {
        // Timeline button
        document.querySelectorAll('.card-action-btn[data-action="timeline"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var card = this.closest('.customer-card');
                openTimeline(card.dataset.id);
            });
        });

        // Card click opens timeline
        document.querySelectorAll('.customer-card').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (e.target.closest('.card-action-btn') || e.target.closest('a')) return;
                openTimeline(this.dataset.id);
            });
        });

        // Toggle VIP
        document.querySelectorAll('.card-action-btn[data-action="toggle-vip"]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var card = this.closest('.customer-card');
                toggleVIP(card.dataset.id, this);
            });
        });
    }

    function openTimeline(customerId) {
        var panel = document.getElementById('customer-panel');
        var overlay = document.getElementById('panel-overlay');
        var content = document.getElementById('panel-content');

        content.innerHTML = '<div class="customers-loading">Chargement...</div>';
        panel.classList.add('open');
        overlay.classList.add('open');

        var data = new FormData();
        data.append('action', 'gastro_starter_customer_timeline');
        data.append('nonce', gastro_starter_customers.nonce);
        data.append('customer_id', customerId);

        fetch(gastro_starter_customers.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                content.innerHTML = response.data.html;
                bindPanelEvents();
            }
        });
    }

    function closePanel() {
        document.getElementById('customer-panel').classList.remove('open');
        document.getElementById('panel-overlay').classList.remove('open');
    }

    function bindPanelEvents() {
        var btnSave = document.getElementById('btn-save-notes');
        if (btnSave) {
            btnSave.addEventListener('click', function() {
                var textarea = document.getElementById('panel-notes');
                var data = new FormData();
                data.append('action', 'gastro_starter_customer_save_notes');
                data.append('nonce', gastro_starter_customers.nonce);
                data.append('customer_id', this.dataset.customerId);
                data.append('notes', textarea.value);

                this.disabled = true;
                this.textContent = 'Enregistré ✓';

                fetch(gastro_starter_customers.ajax_url, {
                    method: 'POST',
                    body: data
                }).then(function() {
                    setTimeout(function() {
                        btnSave.disabled = false;
                        btnSave.textContent = 'Enregistrer';
                    }, 2000);
                });
            });
        }
    }

    function toggleVIP(customerId, btn) {
        var data = new FormData();
        data.append('action', 'gastro_starter_customer_toggle_vip');
        data.append('nonce', gastro_starter_customers.nonce);
        data.append('customer_id', customerId);

        fetch(gastro_starter_customers.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                var icon = btn.querySelector('.dashicons');
                if (response.data.is_vip) {
                    icon.className = 'dashicons dashicons-star-filled';
                    btn.title = 'Retirer VIP';
                } else {
                    icon.className = 'dashicons dashicons-star-empty';
                    btn.title = 'Promouvoir VIP';
                }
                // Refresh after short delay to update badges
                setTimeout(function() { loadCustomers(); }, 500);
            }
        });
    }

    function exportCSV() {
        var data = new FormData();
        data.append('action', 'gastro_starter_customer_export_csv');
        data.append('nonce', gastro_starter_customers.nonce);
        data.append('segment', currentSegment);

        fetch(gastro_starter_customers.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                var blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = response.data.filename;
                link.click();
            }
        });
    }

    function openAddCustomerModal() {
        document.getElementById('add-customer-modal').classList.add('open');
        document.getElementById('add-customer-overlay').classList.add('open');
        document.getElementById('new-customer-name').focus();
    }

    function closeAddCustomerModal() {
        document.getElementById('add-customer-modal').classList.remove('open');
        document.getElementById('add-customer-overlay').classList.remove('open');
        document.getElementById('add-customer-form').reset();
        document.getElementById('add-customer-error').style.display = 'none';
    }

    function submitAddCustomer(e) {
        e.preventDefault();
        var errorEl = document.getElementById('add-customer-error');
        errorEl.style.display = 'none';

        var data = new FormData();
        data.append('action', 'gastro_starter_customer_add');
        data.append('nonce', gastro_starter_customers.nonce);
        data.append('name', document.getElementById('new-customer-name').value.trim());
        data.append('phone', document.getElementById('new-customer-phone').value.trim());
        data.append('email', document.getElementById('new-customer-email').value.trim());
        data.append('notes', document.getElementById('new-customer-notes').value.trim());

        fetch(gastro_starter_customers.ajax_url, {
            method: 'POST',
            body: data
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                closeAddCustomerModal();
                currentPage = 1;
                loadCustomers();
            } else {
                errorEl.textContent = response.data || 'Erreur';
                errorEl.style.display = 'block';
            }
        });
    }

    function renderPagination(totalPages, currentPageNum) {
        var container = document.getElementById('customers-pagination');
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        var html = '';
        if (currentPageNum > 1) {
            html += '<button class="page-btn" data-page="' + (currentPageNum - 1) + '">&laquo;</button>';
        }
        for (var i = 1; i <= totalPages; i++) {
            if (i === currentPageNum) {
                html += '<span class="page-btn page-current">' + i + '</span>';
            } else if (i <= 3 || i > totalPages - 3 || Math.abs(i - currentPageNum) <= 1) {
                html += '<button class="page-btn" data-page="' + i + '">' + i + '</button>';
            } else if (html.slice(-3) !== '...') {
                html += '...';
            }
        }
        if (currentPageNum < totalPages) {
            html += '<button class="page-btn" data-page="' + (currentPageNum + 1) + '">&raquo;</button>';
        }

        container.innerHTML = html;

        container.querySelectorAll('button.page-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                currentPage = parseInt(this.dataset.page);
                loadCustomers();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }
})();
