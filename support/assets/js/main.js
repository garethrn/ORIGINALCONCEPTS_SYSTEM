/**
 * Original Concepts Support System – Main JavaScript
 */

'use strict';

// ─── Auto-dismiss alerts after 5 s ───────────────────────────────────────────
document.querySelectorAll('.alert.alert-dismissible').forEach(function (el) {
    setTimeout(function () {
        var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert.close();
    }, 5000);
});

// ─── Confirm dangerous actions ────────────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
        if (!confirm(el.dataset.confirm || 'Are you sure?')) {
            e.preventDefault();
        }
    });
});

// ─── Ticket status colour helper ─────────────────────────────────────────────
function highlightRow(selectEl) {
    var row = selectEl.closest('tr');
    if (!row) return;
    row.className = row.className.replace(/\btable-\S+/g, '');
    var map = {
        open: 'table-primary',
        in_progress: 'table-info',
        waiting: 'table-warning',
        resolved: 'table-success',
        closed: '',
        cancelled: 'table-danger'
    };
    var cls = map[selectEl.value];
    if (cls) row.classList.add(cls);
}

// ─── Live search filter on list tables ───────────────────────────────────────
var searchInput = document.getElementById('liveSearch');
if (searchInput) {
    searchInput.addEventListener('input', function () {
        var q = this.value.toLowerCase();
        document.querySelectorAll('.searchable-row').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
}

// ─── Enable Bootstrap tooltips ────────────────────────────────────────────────
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    bootstrap.Tooltip.getOrCreateInstance(el);
});

// ─── Ticket file attachment preview ──────────────────────────────────────────
var fileInput = document.getElementById('attachments');
if (fileInput) {
    fileInput.addEventListener('change', function () {
        var preview = document.getElementById('filePreview');
        if (!preview) return;
        preview.innerHTML = '';
        Array.from(this.files).forEach(function (f) {
            var li = document.createElement('li');
            li.className = 'list-group-item list-group-item-sm py-1';
            li.textContent = f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
            preview.appendChild(li);
        });
    });
}

// ─── Copy ticket reference to clipboard ──────────────────────────────────────
document.querySelectorAll('.copy-ref').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var ref = this.dataset.ref;
        navigator.clipboard.writeText(ref).then(function () {
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = ref; }, 2000);
        });
    });
});

// ─── QuickBooks OAuth popup ───────────────────────────────────────────────────
var qbConnectBtn = document.getElementById('qbConnectBtn');
if (qbConnectBtn) {
    qbConnectBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var url = this.href;
        var w = window.open(url, 'qb_oauth', 'width=800,height=600,scrollbars=yes');
        var timer = setInterval(function () {
            if (w.closed) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 800);
    });
}
