(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Sidebar toggle for mobile
    var burger = document.getElementById('adminBurger');
    var sidebar = document.querySelector('.admin-sidebar');
    if (burger && sidebar) {
        burger.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // Tabs
    document.querySelectorAll('.admin-tabs').forEach(function (tabBar) {
        var tabs = tabBar.querySelectorAll('.admin-tab');
        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-tab');
                var container = tabBar.closest('[data-tab-container]') || tabBar.parentElement;
                tabs.forEach(function (t) { t.classList.remove('active'); });
                tab.classList.add('active');
                container.querySelectorAll('.admin-tab-pane').forEach(function (pane) {
                    pane.classList.toggle('active', pane.getAttribute('data-pane') === target);
                });
            });
        });
    });

    // Confirm dialogs for destructive actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // Select-all checkboxes for bulk actions
    document.querySelectorAll('[data-select-all]').forEach(function (master) {
        master.addEventListener('change', function () {
            var name = master.getAttribute('data-select-all');
            document.querySelectorAll('input[type=checkbox][data-bulk="' + name + '"]').forEach(function (cb) {
                cb.checked = master.checked;
            });
        });
    });

    // Image preview before upload
    document.querySelectorAll('[data-image-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var targetSel = input.getAttribute('data-image-preview');
            var target = document.querySelector(targetSel);
            if (!target || !input.files || !input.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) { target.src = e.target.result; target.style.display = ''; };
            reader.readAsDataURL(input.files[0]);
        });
    });

    // Dynamic repeatable rows (customization options, etc.)
    document.querySelectorAll('[data-repeater-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var containerSel = btn.getAttribute('data-repeater-add');
            var container = document.querySelector(containerSel);
            var template = container.querySelector('[data-repeater-template]');
            if (!template) return;
            var clone = template.cloneNode(true);
            clone.removeAttribute('data-repeater-template');
            clone.style.display = '';
            var idx = container.querySelectorAll('.admin-option-row').length;
            clone.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/__INDEX__/g, idx);
                field.value = field.type === 'checkbox' ? field.value : '';
                field.checked = false;
            });
            container.appendChild(clone);
        });
    });
    document.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('[data-repeater-remove]');
        if (removeBtn) {
            e.preventDefault();
            var row = removeBtn.closest('.admin-option-row');
            if (row) row.remove();
        }
    });

    // Chart.js revenue chart (dashboard)
    var chartCanvas = document.getElementById('revenueChart');
    if (chartCanvas && window.Chart && window.GDD_CHART_DATA) {
        var data = window.GDD_CHART_DATA;
        new Chart(chartCanvas, {
            type: 'line',
            data: {
                labels: data.map(function (r) { return r.label; }),
                datasets: [{
                    label: 'Revenue (₹)',
                    data: data.map(function (r) { return parseFloat(r.revenue); }),
                    borderColor: '#d6336c',
                    backgroundColor: 'rgba(214,51,108,.12)',
                    tension: 0.35,
                    fill: true
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });
    }

    // Quill editor (product description / about us)
    document.querySelectorAll('[data-quill]').forEach(function (el) {
        var hiddenInputSel = el.getAttribute('data-quill');
        var hidden = document.querySelector(hiddenInputSel);
        if (!window.Quill || !hidden) return;
        var quill = new Quill(el, { theme: 'snow' });
        if (hidden.value) quill.root.innerHTML = hidden.value;
        quill.on('text-change', function () { hidden.value = quill.root.innerHTML; });
        var form = hidden.closest('form');
        if (form) form.addEventListener('submit', function () { hidden.value = quill.root.innerHTML; });
    });

    // Generic AJAX POST helper for toggle/upload buttons
    window.gddAdminPost = function (url, body, onSuccess) {
        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken() },
            body: body
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (onSuccess) onSuccess(data);
        }).catch(function (err) { console.error(err); });
    };
})();
