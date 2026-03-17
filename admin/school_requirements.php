<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$toast_success = "";
$toast_error = "";

/* ---------------------------
   HELPERS
---------------------------- */
function normFileType($ft){
    $ft = strtolower(trim((string)$ft));
    $allowed = ['pdf','jpeg','jpg','doc','docx','xls','xlsx'];
    if(!in_array($ft, $allowed, true)) return 'pdf';
    if($ft === 'jpg') return 'jpeg'; // normalize to jpeg
    return $ft;
}

function cleanCategory($c){
    $c = trim((string)$c);
    return $c === "" ? "Other" : $c;
}

/* ---------------------------
   ADD NEW DOCUMENT TYPE
---------------------------- */
if(isset($_POST['add_doc_type'])){
    $doc_name   = trim($_POST['doc_name'] ?? '');
    $category   = trim($_POST['category'] ?? '');
    $category2  = trim($_POST['category_custom'] ?? '');
    $file_type  = normFileType($_POST['file_type'] ?? 'pdf');

    // category from dropdown OR custom input
    if($category === '__custom__'){
        $category = $category2;
    }
    $category = cleanCategory($category);

    if($doc_name === ""){
        $toast_error = "Document name is required.";
    } else {
        // Optional: prevent exact duplicates (same name + category)
        $stmt = $conn->prepare("SELECT id FROM document_types WHERE doc_name=? AND category=? LIMIT 1");
        $stmt->bind_param("ss", $doc_name, $category);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($exists){
            $toast_error = "This document type already exists in this category.";
        } else {
            // insert (needs file_type column)
            $stmt = $conn->prepare("INSERT INTO document_types (doc_name, category, file_type) VALUES (?, ?, ?)");
            if(!$stmt){
                $toast_error = "Prepare failed: ".$conn->error." (Check if document_types has file_type column)";
            } else {
                $stmt->bind_param("sss", $doc_name, $category, $file_type);
                if($stmt->execute()){
                    $redirect_school_id = isset($_POST['current_school_id']) ? (int)$_POST['current_school_id'] : 0;
                    header("Location: ".$_SERVER['PHP_SELF'].($redirect_school_id>0 ? "?school_id=".$redirect_school_id."&msg=doc_added" : "?msg=doc_added"));
                    exit();
                } else {
                    $toast_error = "Failed to add document type: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

/* ---------------------------
   UPDATE EXISTING DOCUMENT TYPE
---------------------------- */
if(isset($_POST['update_doc_type'])){
    $doc_type_id = (int)($_POST['doc_type_id'] ?? 0);
    $new_name    = trim($_POST['edit_doc_name'] ?? '');
    $new_cat     = trim($_POST['edit_category'] ?? '');
    $new_cat2    = trim($_POST['edit_category_custom'] ?? '');
    $new_ft      = normFileType($_POST['edit_file_type'] ?? 'pdf');

    if($new_cat === '__custom__'){
        $new_cat = $new_cat2;
    }
    $new_cat = cleanCategory($new_cat);

    if($doc_type_id <= 0){
        $toast_error = "Invalid doc type.";
    } elseif($new_name === ""){
        $toast_error = "Document name is required.";
    } else {
        // duplicate check (exclude itself)
        $stmt = $conn->prepare("SELECT id FROM document_types WHERE doc_name=? AND category=? AND id<>? LIMIT 1");
        $stmt->bind_param("ssi", $new_name, $new_cat, $doc_type_id);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($dup){
            $toast_error = "Another document type already uses that name in that category.";
        } else {
            $stmt = $conn->prepare("UPDATE document_types SET doc_name=?, category=?, file_type=? WHERE id=? LIMIT 1");
            if(!$stmt){
                $toast_error = "Prepare failed: ".$conn->error." (Check file_type column)";
            } else {
                $stmt->bind_param("sssi", $new_name, $new_cat, $new_ft, $doc_type_id);
                if($stmt->execute()){
                    $keep_school = isset($_POST['keep_school_id']) ? (int)$_POST['keep_school_id'] : 0;
                    header("Location: ".$_SERVER['PHP_SELF'].($keep_school>0 ? "?school_id=".$keep_school."&msg=doc_updated" : "?msg=doc_updated"));
                    exit();
                } else {
                    $toast_error = "Update failed: ".$stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

/* show toast after redirect */
if(isset($_GET['msg']) && $_GET['msg'] === 'doc_added'){
    $toast_success = "Document type added successfully!";
}
if(isset($_GET['msg']) && $_GET['msg'] === 'doc_updated'){
    $toast_success = "Document type updated successfully!";
}

/* ---------------------------
   LOAD SCHOOLS
---------------------------- */
$schools = [];
$res = $conn->query("SELECT id, name FROM schools ORDER BY name ASC");
while($row = $res->fetch_assoc()){
    $schools[] = $row;
}

/* ---------------------------
   LOAD CATEGORIES (distinct)
---------------------------- */
$categories = [];
$resC = $conn->query("SELECT DISTINCT category FROM document_types ORDER BY category ASC");
while($r = $resC->fetch_assoc()){
    $cat = trim($r['category'] ?? '');
    if($cat === "") $cat = "Other";
    $categories[] = $cat;
}
if(empty($categories)) $categories = ["Other"];

/* ---------------------------
   LOAD DOC TYPES GROUPED
---------------------------- */
$docTypes = [];
$res2 = $conn->query("SELECT id, doc_name, category, file_type FROM document_types ORDER BY category ASC, doc_name ASC");
while($row = $res2->fetch_assoc()){
    $cat = trim($row['category'] ?? '');
    if($cat === '') $cat = "Other";
    $row['file_type'] = normFileType($row['file_type'] ?? 'pdf');
    $docTypes[$cat][] = $row;
}

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;

/* ---------------------------
   CURRENT REQUIRED DOCS
---------------------------- */
$requiredMap = [];
if($school_id > 0){
    $stmt = $conn->prepare("SELECT doc_type_id FROM school_required_docs WHERE school_id=?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $r = $stmt->get_result();
    while($x = $r->fetch_assoc()){
        $requiredMap[(int)$x['doc_type_id']] = true;
    }
    $stmt->close();
}

/* ---------------------------
   SAVE REQUIREMENTS
---------------------------- */
if(isset($_POST['save_requirements'])){
    $school_id_post = (int)($_POST['school_id'] ?? 0);
    $selected = $_POST['doc_type_ids'] ?? [];

    if($school_id_post <= 0){
        $toast_error = "Please select a school first.";
    } else {

        $selectedIds = [];
        foreach($selected as $id){
            $selectedIds[] = (int)$id;
        }

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("DELETE FROM school_required_docs WHERE school_id=?");
            $stmt->bind_param("i", $school_id_post);
            $stmt->execute();
            $stmt->close();

            if(count($selectedIds) > 0){
                $stmt = $conn->prepare("INSERT INTO school_required_docs (school_id, doc_type_id, is_required) VALUES (?, ?, 1)");
                foreach($selectedIds as $doc_type_id){
                    $stmt->bind_param("ii", $school_id_post, $doc_type_id);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $conn->commit();
            header("Location: ".$_SERVER['PHP_SELF']."?school_id=".$school_id_post."&msg=req_saved");
            exit();

        } catch(Exception $e){
            $conn->rollback();
            $toast_error = "Save failed: " . $e->getMessage();
        }
    }
}

if(isset($_GET['msg']) && $_GET['msg'] === 'req_saved'){
    $toast_success = "Requirements saved successfully!";
}

require_once '../template/login_status.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}

.page-container {
    max-width: 1400px;
    margin: 24px auto;
}

.small-ui,
.small-ui * {
    font-size: 12.5px;
}

.card-box {
    padding: 18px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    background: #fff;
    margin-bottom: 18px;
}

.side-box {
    position: sticky;
    top: 16px;
}

.category-title {
    font-weight: 800;
    margin-top: 14px;
    margin-bottom: 6px;
}

.hr-tight {
    margin: 12px 0;
}

.form-control,
.form-select {
    padding: .38rem .55rem;
    font-size: 12.5px;
}

.form-check {
    margin-bottom: 4px;
}

.badge-soft {
    background: #eef2ff;
    color: #2b3a67;
    border: 1px solid #d6ddff;
    font-weight: 800;
}

.doc-mini {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 6px 8px;
    border: 1px solid #eef2f7;
    border-radius: 10px;
    margin-bottom: 6px;
    background: #fff;
}

.doc-mini:hover {
    background: #fafcff;
}

.doc-left {
    min-width: 0;
}

.doc-name {
    font-weight: 800;
}

.doc-meta {
    font-size: 12px;
    color: #6c757d;
    white-space: nowrap;
}

.btn-icon {
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 10px;
    padding: 5px 8px;
    font-weight: 800;
}

.btn-icon:hover {
    background: #f8fafc;
}

/* Bottom-right mini modal */
.modal-mini .modal-dialog {
    position: fixed;
    right: 16px;
    bottom: 16px;
    margin: 0;
    width: 440px;
    max-width: calc(100vw - 32px);
}

.modal-mini .modal-content {
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, .2);
}

/* Bottom-right toast */
.toast-pop {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 1080;
    min-width: 280px;
    max-width: 420px;
    border-radius: 12px;
    padding: 12px 14px;
    box-shadow: 0 14px 30px rgba(0, 0, 0, .18);
    display: none;
}
</style>

<div class="container page-container small-ui">
    <div class="row g-3">

        <!-- LEFT ~70% -->
        <div class="col-lg-8">

            <!-- HEADER -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="m-0">School Document Requirements</h5>
                    <a href="dashboard.php" class="btn btn-sm btn-secondary">Back</a>
                </div>
                <hr class="hr-tight">
                <div class="text-muted" style="font-size:12px;">
                    Add document types (global), then select a school and tick required docs.
                </div>
            </div>

            <!-- ADD NEW DOCUMENT TYPE -->
            <div class="card-box">
                <h6 class="mb-3 fw-bold">Add New Document Type</h6>

                <form method="POST" class="row g-2">
                    <input type="hidden" name="current_school_id" value="<?php echo (int)$school_id; ?>">

                    <div class="col-md-5">
                        <label class="form-label fw-bold">Document Name</label>
                        <input type="text" name="doc_name" class="form-control" placeholder="e.g. Tax Document"
                            required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">File Type</label>
                        <select name="file_type" class="form-select">
                            <option value="pdf" selected>PDF</option>
                            <option value="jpeg">JPEG</option>
                            <option value="doc">DOC</option>
                            <option value="docx">DOCX</option>
                            <option value="xls">XLS</option>
                            <option value="xlsx">XLSX</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Category</label>
                        <select name="category" id="categorySelect" class="form-select">
                            <?php foreach($categories as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="__custom__">Custom…</option>
                        </select>
                        <input type="text" name="category_custom" id="categoryCustom" class="form-control mt-2"
                            placeholder="Type new category" style="display:none;">
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" name="add_doc_type" class="btn btn-dark px-4">Add Document</button>
                    </div>
                </form>
            </div>

            <!-- SELECT SCHOOL -->
            <div class="card-box">
                <h6 class="mb-3 fw-bold">Select School</h6>

                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">School</label>
                        <select name="school_id" class="form-select" required>
                            <option value="">-- Choose School --</option>
                            <?php foreach($schools as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>"
                                <?php echo ($school_id == (int)$s['id']) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit">Load Requirements</button>
                    </div>
                </form>
            </div>

            <!-- REQUIREMENT CHECKLIST + EDIT -->
            <?php if($school_id > 0): ?>
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="m-0 fw-bold">Set Required Documents</h6>
                    <span class="badge badge-soft">School ID: <?php echo (int)$school_id; ?></span>
                </div>

                <form method="POST">
                    <input type="hidden" name="school_id" value="<?php echo (int)$school_id; ?>">

                    <?php foreach($docTypes as $category => $docs): ?>
                    <div class="category-title"><?php echo htmlspecialchars($category); ?></div>

                    <div class="row g-2">
                        <?php foreach($docs as $d):
                $did = (int)$d['id'];
                $checked = isset($requiredMap[$did]) ? "checked" : "";
                $ftShow = strtoupper($d['file_type'] ?? 'PDF');
              ?>
                        <div class="col-md-6">
                            <div class="doc-mini">
                                <div class="doc-left">
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="checkbox" name="doc_type_ids[]"
                                            value="<?php echo $did; ?>" <?php echo $checked; ?>
                                            id="doc_<?php echo $did; ?>">
                                        <label class="form-check-label" for="doc_<?php echo $did; ?>">
                                            <span
                                                class="doc-name"><?php echo htmlspecialchars($d['doc_name']); ?></span>
                                        </label>
                                    </div>
                                    <div class="doc-meta">
                                        Type: <b><?php echo htmlspecialchars($ftShow); ?></b>
                                    </div>
                                </div>

                                <button type="button" class="btn-icon btnEditDoc" data-id="<?php echo $did; ?>"
                                    data-name="<?php echo htmlspecialchars($d['doc_name']); ?>"
                                    data-cat="<?php echo htmlspecialchars($category); ?>"
                                    data-ft="<?php echo htmlspecialchars($d['file_type']); ?>">
                                    Edit
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <hr class="hr-tight">
                    <?php endforeach; ?>

                    <div class="d-flex justify-content-end">
                        <button type="submit" name="save_requirements" class="btn btn-success px-4">
                            Save Requirements
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

        </div>

        <!-- RIGHT ~30% -->
        <div class="col-lg-4">
            <div class="card-box side-box">
                <h6 class="fw-bold mb-2">About This Page</h6>
                <div class="text-muted" style="font-size:12px; line-height:1.55;">
                    Configure which documents are required for each school. Students only see and upload selected items.
                </div>

                <hr>

                <h6 class="fw-bold mb-2">What you can do</h6>
                <ul style="padding-left:16px; line-height:1.65; margin:0;">
                    <li>Add a document type with file type (PDF/JPEG/Word/Excel).</li>
                    <li>Reuse existing categories from dropdown.</li>
                    <li>Edit any document type from the list (bottom-right popup).</li>
                    <li>Tick required docs per school.</li>
                </ul>

                <hr>

                <div class="d-grid">
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Return to Dashboard</a>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- =========================
     EDIT DOC TYPE MODAL (BOTTOM-RIGHT)
========================== -->
<div class="modal fade modal-mini" id="editDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:900;">Edit Document Type</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="update_doc_type" value="1">
                    <input type="hidden" name="doc_type_id" id="editDocId" value="">
                    <input type="hidden" name="keep_school_id" value="<?php echo (int)$school_id; ?>">

                    <div class="mb-2">
                        <label class="form-label fw-bold">Document Name</label>
                        <input type="text" name="edit_doc_name" id="editDocName" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">File Type</label>
                        <select name="edit_file_type" id="editFileType" class="form-select">
                            <option value="pdf">PDF</option>
                            <option value="jpeg">JPEG</option>
                            <option value="doc">DOC</option>
                            <option value="docx">DOCX</option>
                            <option value="xls">XLS</option>
                            <option value="xlsx">XLSX</option>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">Category</label>
                        <select name="edit_category" id="editCategorySelect" class="form-select">
                            <?php foreach($categories as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="__custom__">Custom…</option>
                        </select>
                        <input type="text" name="edit_category_custom" id="editCategoryCustom" class="form-control mt-2"
                            placeholder="Type new category" style="display:none;">
                    </div>

                    <div class="text-muted" style="font-size:12px;">
                        Editing file type will affect what students can upload for this document.
                    </div>
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-dark">Save Changes</button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Toast -->
<?php if(!empty($toast_success)): ?>
<div id="toastMsg" class="toast-pop" style="background:#198754;color:#fff;">
    <div style="font-weight:900;">Success</div>
    <div><?php echo htmlspecialchars($toast_success); ?></div>
</div>
<?php endif; ?>

<?php if(!empty($toast_error)): ?>
<div id="toastMsg" class="toast-pop" style="background:#dc3545;color:#fff;">
    <div style="font-weight:900;">Error</div>
    <div><?php echo htmlspecialchars($toast_error); ?></div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // Toast show
    const t = document.getElementById("toastMsg");
    if (t) {
        t.style.display = "block";
        setTimeout(() => t.style.display = "none", 3500);
    }

    // Add form category toggle
    const catSel = document.getElementById('categorySelect');
    const catCustom = document.getElementById('categoryCustom');
    if (catSel && catCustom) {
        catSel.addEventListener('change', () => {
            catCustom.style.display = (catSel.value === '__custom__') ? 'block' : 'none';
            if (catSel.value !== '__custom__') catCustom.value = '';
        });
    }

    // Edit modal category toggle
    const editCatSel = document.getElementById('editCategorySelect');
    const editCatCustom = document.getElementById('editCategoryCustom');
    if (editCatSel && editCatCustom) {
        editCatSel.addEventListener('change', () => {
            editCatCustom.style.display = (editCatSel.value === '__custom__') ? 'block' : 'none';
            if (editCatSel.value !== '__custom__') editCatCustom.value = '';
        });
    }

    // Open edit modal
    const editModalEl = document.getElementById('editDocModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    document.querySelectorAll('.btnEditDoc').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id || '';
            const name = btn.dataset.name || '';
            const cat = btn.dataset.cat || 'Other';
            const ft = (btn.dataset.ft || 'pdf').toLowerCase();

            document.getElementById('editDocId').value = id;
            document.getElementById('editDocName').value = name;

            // file type
            const ftSel = document.getElementById('editFileType');
            if (ftSel) {
                // normalize jpg -> jpeg
                const norm = (ft === 'jpg') ? 'jpeg' : ft;
                ftSel.value = norm;
            }

            // category
            const catSel2 = document.getElementById('editCategorySelect');
            const catCustom2 = document.getElementById('editCategoryCustom');

            let found = false;
            if (catSel2) {
                for (const opt of catSel2.options) {
                    if (opt.value === cat) {
                        found = true;
                        break;
                    }
                }
                if (found) {
                    catSel2.value = cat;
                    catCustom2.style.display = 'none';
                    catCustom2.value = '';
                } else {
                    catSel2.value = '__custom__';
                    catCustom2.style.display = 'block';
                    catCustom2.value = cat;
                }
            }

            editModal?.show();
        });
    });

});
</script>