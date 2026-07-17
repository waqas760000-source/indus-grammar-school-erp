<?php
/**
 * Indus Grammar School ERP - Expense Categories Manager
 * Version 4.0.0
 */

$pageTitle = 'Expense Categories';
$breadcrumbActive = 'Accounts';
include_once __DIR__ . '/../../includes/header.php';
AuthMiddleware::requirePermission('cash_view');

$db = Database::getConnection();

// Fetch all categories with total expenses recorded under them
$categories = [];
try {
    $categories = $db->query("
        SELECT c.*, COUNT(e.id) as expense_count, COALESCE(SUM(e.amount),0) as total_spent 
        FROM expense_categories c
        LEFT JOIN expenses e ON c.id = e.category_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ")->fetchAll();
} catch (Exception $e) {
    error_log("Error loading categories: " . $e->getMessage());
}
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h3 class="fw-bold text-secondary mb-0"><i class="fa-solid fa-folder-tree me-2 text-primary"></i>Expense Categories</h3>
        <p class="text-muted small mb-0">Define operational expense classes (e.g. Utility Bills, Rent, Stationery) for financial audits.</p>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
        <button class="btn btn-primary px-4" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fa-solid fa-plus me-2"></i>Add Category
        </button>
    </div>
</div>

<!-- Categories Grid Table -->
<div class="custom-table-card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table custom-table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th width="80">ID</th>
                    <th>Category Name</th>
                    <th>Description</th>
                    <th class="text-center">Recorded Expenses</th>
                    <th>Total Disbursed</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted">No expense categories registered.</td></tr>
                <?php else: foreach ($categories as $cat): ?>
                    <tr>
                        <td><code class="fw-bold">#<?php echo $cat['id']; ?></code></td>
                        <td class="fw-bold text-dark"><?php echo sanitize($cat['name']); ?></td>
                        <td class="text-muted small"><?php echo sanitize($cat['description'] ?: '—'); ?></td>
                        <td class="text-center fw-semibold"><?php echo $cat['expense_count']; ?></td>
                        <td class="fw-bold text-danger">Rs. <?php echo number_format($cat['total_spent'], 2); ?></td>
                        <td>
                            <span class="badge badge-soft-<?php echo ($cat['status'] === 'Active') ? 'success' : 'secondary'; ?> px-3 py-2 rounded-pill">
                                <?php echo sanitize($cat['status']); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary btn-edit-cat me-1" 
                                    data-id="<?php echo $cat['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                    data-desc="<?php echo htmlspecialchars($cat['description'] ?? ''); ?>"
                                    data-status="<?php echo htmlspecialchars($cat['status']); ?>"
                                    title="Edit Category">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <?php if ($cat['expense_count'] == 0): ?>
                                <button class="btn btn-sm btn-outline-danger btn-delete-cat" data-id="<?php echo $cat['id']; ?>" title="Delete Category">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-plus me-2 text-primary"></i>Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="addCatForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="add_category">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category Name *</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. Office Rent">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Category Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="e.g. Rental premises fee"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addCatForm" class="btn btn-sm btn-primary px-4" id="btnAddCat">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow" style="border-radius:12px;">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-secondary"><i class="fa-solid fa-pen me-2 text-primary"></i>Modify Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4">
                <form id="editCatForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Description</label>
                        <textarea class="form-control" name="description" id="edit_desc" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Category Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pb-4 px-4">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editCatForm" class="btn btn-sm btn-primary px-4" id="btnEditCat">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999;">
    <div id="catToast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="catToastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $extraJS = '<script>
function showToast(msg, ok) {
    const t = document.getElementById("catToast");
    const m = document.getElementById("catToastMsg");
    t.classList.remove("bg-success","bg-danger");
    t.classList.add(ok ? "bg-success" : "bg-danger");
    m.textContent = msg;
    new bootstrap.Toast(t).show();
}

document.addEventListener("DOMContentLoaded", function() {
    // Add category submission
    const addForm = document.getElementById("addCatForm");
    if (addForm) {
        addForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnAddCat");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(addForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Add"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Add"; });
        });
    }

    // Bind Edit Modal
    document.querySelectorAll(".btn-edit-cat").forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("edit_id").value = this.dataset.id;
            document.getElementById("edit_name").value = this.dataset.name;
            document.getElementById("edit_desc").value = this.dataset.desc;
            document.getElementById("edit_status").value = this.dataset.status;
            
            new bootstrap.Modal(document.getElementById("editCategoryModal")).show();
        });
    });

    // Save Edit Form
    const editForm = document.getElementById("editCatForm");
    if (editForm) {
        editForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const btn = document.getElementById("btnEditCat");
            btn.disabled = true; btn.innerHTML = "Saving...";
            
            fetch("../../ajax/accounts.php", { method: "POST", body: new FormData(editForm) })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if(data.success) setTimeout(() => location.reload(), 1000);
                    else { btn.disabled = false; btn.innerHTML = "Save Changes"; }
                })
                .catch(() => { showToast("Network error.", false); btn.disabled = false; btn.innerHTML = "Save Changes"; });
        });
    }

    // Delete category
    document.querySelectorAll(".btn-delete-cat").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.dataset.id;
            if(!confirm("Are you sure you want to permanently delete this expense category?")) return;
            const fd = new FormData();
            fd.append("action", "delete_category");
            fd.append("csrf_token", "' . csrfToken() . '");
            fd.append("id", id);
            
            fetch("../../ajax/accounts.php", { method: "POST", body: fd })
                .then(r => r.json())
                .then(data => {
                    showToast(data.message, data.success);
                    if (data.success) setTimeout(() => location.reload(), 1000);
                });
        });
    });
});
</script>';
include_once __DIR__ . '/../../includes/footer.php'; ?>
