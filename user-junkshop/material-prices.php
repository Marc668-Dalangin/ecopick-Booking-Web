<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/controllers/MaterialPriceController.php';

if (!Auth::check()) {
    header('Location: ' . APP_URL . '/user-junkshop/login.php');
    exit;
}

if (Auth::userRole() !== 'junkshop') {
    header('Location: ' . APP_URL . '/user-junkshop/dashboard.php');
    exit;
}

$controller = new MaterialPriceController();
$accountId = Auth::userId();
$approvalStatus = $controller->getJunkshopApprovalStatus($accountId);
$materials = $controller->listActiveMaterials();
$prices = $controller->getJunkshopMaterialPrices($accountId);

$pageTitle = 'Accepted Materials and Buying Prices';
$currentPage = 'materials-prices';
$userDisplayName = Auth::userName();

ob_start();
?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-recycle"></i> Accepted Materials and Buying Prices</h4>
                <p class="text-muted mb-0">Manage the materials your junkshop accepts and the buying price per kilogram.</p>
            </div>
            <span class="badge <?php echo $approvalStatus === 'approved' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>"><?php echo strtoupper(Validator::escape($approvalStatus ?? 'pending')); ?></span>
        </div>

        <?php if ($approvalStatus !== 'approved'): ?>
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle"></i> Your junkshop account is not approved yet. Approved junkshops may manage accepted materials and buying prices.
            </div>
            <?php
                $content = ob_get_clean();
                require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
                exit;
            ?>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle"></i> Add Material</h5>
                        <form id="material-price-form" method="POST" novalidate>
                            <?php echo CSRF::field(); ?>
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label class="form-label" for="material_id">Material</label>
                                <select class="form-select" id="material_id" name="material_id" required>
                                    <option value="">Select material</option>
                                    <?php foreach ($materials as $material): ?>
                                        <option value="<?php echo (int)($material['id'] ?? 0); ?>"><?php echo Validator::escape($material['material_name'] ?? ''); ?> (<?php echo Validator::escape($material['category'] ?? ''); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="buying_price">Buying Price per kg</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="buying_price" name="buying_price" min="0.01" step="0.01" placeholder="45.00" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add Material Price</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3"><i class="bi bi-list-check"></i> Accepted Materials</h5>
                        <div id="materials-status" class="alert d-none" role="status" aria-live="polite"></div>
                        <?php if (empty($prices)): ?>
                            <div class="empty-state text-center">
                                <div class="display-6 text-muted"><i class="bi bi-recycle"></i></div>
                                <h6 class="mt-3 mb-2 fw-bold">No materials added yet</h6>
                                <p class="text-muted mb-0">Add materials from the catalog to start posting your buying prices.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0" id="material-price-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Material</th>
                                            <th>Category</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($prices as $row): ?>
                                            <tr data-price-id="<?php echo (int)($row['id'] ?? 0); ?>">
                                                <td><?php echo Validator::escape($row['material_name'] ?? ''); ?></td>
                                                <td><?php echo Validator::escape($row['category'] ?? ''); ?></td>
                                                <td class="text-end fw-semibold">₱<?php echo number_format((float)($row['buying_price'] ?? 0), 2); ?> / <?php echo Validator::escape($row['unit_of_measure'] ?? 'kg'); ?></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-price-btn" data-price-id="<?php echo (int)($row['id'] ?? 0); ?>" data-price="<?php echo number_format((float)($row['buying_price'] ?? 0), 2, '.', ''); ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger remove-price-btn" data-price-id="<?php echo (int)($row['id'] ?? 0); ?>" data-material="<?php echo Validator::escape($row['material_name'] ?? ''); ?>">
                                                        <i class="bi bi-trash"></i> Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="removePriceModal" tabindex="-1" aria-labelledby="removePriceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="removePriceModalLabel">Remove material</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="remove-price-confirmation">Remove this material from your accepted list?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-remove-price">Remove</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('material-price-form');
        const statusBox = document.getElementById('materials-status');
        const addModalEl = document.getElementById('addMaterialPriceModal');
        const removeModalEl = document.getElementById('removePriceModal');
        const removeModal = removeModalEl ? new bootstrap.Modal(removeModalEl) : null;
        const removeConfirmation = window.ecopick && window.ecopick.setupActionConfirmation
            ? window.ecopick.setupActionConfirmation({
                modalId: 'removePriceModal',
                confirmButtonSelector: '#confirm-remove-price',
                messageSelector: '#remove-price-confirmation',
                cancelButtonSelector: '[data-bs-dismiss="modal"]'
            })
            : null;
        let pendingPriceId = null;

        function showStatus(message, isSuccess) {
            if (!statusBox) return;
            statusBox.className = 'alert ' + (isSuccess ? 'alert-success' : 'alert-danger') + ' mt-3';
            statusBox.textContent = message;
            statusBox.classList.remove('d-none');
            setTimeout(function () {
                statusBox.classList.add('d-none');
                statusBox.textContent = '';
            }, 4000);
        }

        function refreshTable(payload) {
            const target = document.getElementById('material-price-table');
            if (!target || !payload || !payload.data || !Array.isArray(payload.data.prices)) {
                return;
            }

            if (!payload.data.prices.length) {
                target.innerHTML = '<thead class="table-light"><tr><th>Material</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Actions</th></tr></thead><tbody><tr><td colspan="4"><div class="empty-state text-center"><div class="display-6 text-muted"><i class="bi bi-recycle"></i></div><h6 class="mt-3 mb-2 fw-bold">No materials added yet</h6><p class="text-muted mb-0">Add materials from the catalog to start posting your buying prices.</p></div></td></tr></tbody>';
                return;
            }

            target.innerHTML = '<thead class="table-light"><tr><th>Material</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Actions</th></tr></thead><tbody>' + payload.data.prices.map(function (row) {
                return '<tr data-price-id="' + Number(row.id || 0) + '">' +
                    '<td>' + (row.material_name || '') + '</td>' +
                    '<td>' + (row.category || '') + '</td>' +
                    '<td class="text-end fw-semibold">₱' + Number(row.buying_price || 0).toFixed(2) + ' / ' + (row.unit_of_measure || 'kg') + '</td>' +
                    '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary edit-price-btn" data-price-id="' + Number(row.id || 0) + '" data-price="' + Number(row.buying_price || 0).toFixed(2) + '"><i class="bi bi-pencil"></i> Edit</button> <button type="button" class="btn btn-sm btn-outline-danger remove-price-btn" data-price-id="' + Number(row.id || 0) + '" data-material="' + (row.material_name || '') + '"><i class="bi bi-trash"></i> Remove</button></td>' +
                '</tr>';
            }).join('') + '</tbody>';

            bindRowActions();
        }

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'"]/g, function (character) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[character];
            });
        }

        function appendMaterialRow(row) {
            const emptyState = document.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }

            const content = statusBox.parentElement;
            let table = document.getElementById('material-price-table');
            if (!table) {
                const tableWrapper = document.createElement('div');
                tableWrapper.className = 'table-responsive';
                tableWrapper.innerHTML = '<table class="table align-middle mb-0" id="material-price-table"><thead class="table-light"><tr><th>Material</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Actions</th></tr></thead><tbody></tbody></table>';
                content.appendChild(tableWrapper);
                table = tableWrapper.querySelector('#material-price-table');
            }

            const tableBody = table.querySelector('tbody');
            const materialRow = document.createElement('tr');
            materialRow.dataset.priceId = String(Number(row.id || 0));
            materialRow.innerHTML = '<td>' + escapeHtml(row.material_name) + '</td>' +
                '<td>' + escapeHtml(row.category) + '</td>' +
                '<td class="text-end fw-semibold">₱' + Number(row.buying_price || 0).toFixed(2) + ' / ' + escapeHtml(row.unit_of_measure || 'kg') + '</td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary edit-price-btn" data-price-id="' + Number(row.id || 0) + '" data-price="' + Number(row.buying_price || 0).toFixed(2) + '"><i class="bi bi-pencil"></i> Edit</button> <button type="button" class="btn btn-sm btn-outline-danger remove-price-btn" data-price-id="' + Number(row.id || 0) + '" data-material="' + escapeHtml(row.material_name) + '"><i class="bi bi-trash"></i> Remove</button></td>';
            tableBody.prepend(materialRow);
            bindRowActions(materialRow);
        }

        function bindRowActions(root) {
            const scope = root || document;
            scope.querySelectorAll('.edit-price-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    const id = Number(button.dataset.priceId || 0);
                    const currentPrice = button.dataset.price || '0';
                    const row = button.closest('tr');
                    const priceCell = row ? row.querySelectorAll('td')[2] : null;
                    if (!row || !priceCell) return;

                    const input = document.createElement('input');
                    input.type = 'number';
                    input.min = '0.01';
                    input.step = '0.01';
                    input.value = currentPrice;
                    input.className = 'form-control form-control-sm';
                    input.dataset.priceId = String(id);
                    input.dataset.originalCell = 'price';
                    priceCell.innerHTML = '';
                    priceCell.appendChild(input);

                    input.focus();
                    input.addEventListener('keydown', function (event) {
                        if (event.key === 'Enter') {
                            const raw = input.value.trim();
                            if (!raw || Number(raw) <= 0) {
                                showStatus('Please enter a valid positive buying price.', false);
                                return;
                            }
                            submitPriceAction('update', { price_id: id, buying_price: raw });
                        }
                    });
                });
            });

            scope.querySelectorAll('.remove-price-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    const id = Number(button.dataset.priceId || 0);
                    pendingPriceId = id;
                    const material = button.dataset.material || 'this material';
                    if (removeConfirmation) {
                        removeConfirmation.open('Remove ' + material + ' from your accepted list?', async function () {
                            if (!pendingPriceId) {
                                return false;
                            }

                            const success = await submitPriceAction('remove', { price_id: pendingPriceId });
                            pendingPriceId = null;
                            return success;
                        });
                        return;
                    }

                    document.getElementById('remove-price-confirmation').textContent = 'Remove ' + material + ' from your accepted list?';
                    if (removeModal) removeModal.show();
                });
            });
        }

        function submitPriceAction(action, payload) {
            const csrfInput = form.querySelector('input[name="_csrf_token"]');
            if (!csrfInput) {
                return Promise.resolve(false);
            }
            const formData = new FormData();
            formData.append('_csrf_token', csrfInput.value);
            formData.append('action', action);
            for (const [key, value] of Object.entries(payload || {})) {
                if (value !== undefined && value !== null) {
                    formData.append(key, value);
                }
            }

            return fetch('<?php echo APP_URL; ?>/user-junkshop/api/material-prices.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(function (response) {
                return response.json().then(function (json) {
                    if (!response.ok && !json.success) {
                        throw new Error(json.message || 'Unable to save the price.');
                    }
                    return { response: response, json: json };
                });
            })
            .then(function (result) {
                const json = result.json;
                if (!json.success) {
                    showStatus(json.message || 'Unable to save this update.', false);
                    return false;
                }
                showStatus(json.message || 'Price updated successfully.', true);
                if (json.data && json.data.prices) {
                    refreshTable(json);
                }
                form.reset();
                return true;
            })
            .catch(function (error) {
                console.error('Failed to update material price:', error);
                showStatus('Unable to save the price right now.', false);
                return false;
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(form);

            fetch('<?php echo APP_URL; ?>/user-junkshop/api/material-prices.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (response) {
                    return response.json().then(function (json) {
                        if (!response.ok && !json.success) {
                            throw new Error(json.message || 'Unable to add the material.');
                        }
                        return json;
                    });
                })
                .then(function (response) {
                    if (!response.success) {
                        showStatus(response.message || 'Unable to add this material.', false);
                        return;
                    }

                    appendMaterialRow(response.data);
                    form.reset();
                    if (addModalEl) {
                        bootstrap.Modal.getInstance(addModalEl)?.hide();
                    }
                    showStatus(response.message || 'Material price added successfully.', true);
                })
                .catch(function (error) {
                    console.error('Failed to add material price:', error);
                    showStatus(error.message || 'Unable to add the material right now.', false);
                });
        });

        document.getElementById('confirm-remove-price')?.addEventListener('click', function () {
            if (!pendingPriceId) return;
            if (removeConfirmation) {
                return;
            }

            submitPriceAction('remove', { price_id: pendingPriceId }).then(function (success) {
                if (success && removeModal) {
                    removeModal.hide();
                }
                pendingPriceId = null;
            });
        });

        bindRowActions();
    });
</script>
<?php
$content = ob_get_clean();
require_once __DIR__ . '/../app/views/user_dashboard_shell.php';
