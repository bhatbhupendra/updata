<?php
session_start();
include '../config/db.php';
include '../template/login_status.php';

if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}

$success = "";
$error = "";

/**
 * ADD NEW DOCUMENT TYPE
 */
if(isset($_POST['add_doc_type'])){
    $doc_name = trim($_POST['doc_name'] ?? '');
    $category = trim($_POST['category'] ?? '');

    if($doc_name === ""){
        $error = "Document name is required.";
    } else {
        if($category === "") $category = "Other";

        // Optional: prevent exact duplicates (same name + category)
        $stmt = $conn->prepare("SELECT id FROM document_types WHERE doc_name=? AND category=? LIMIT 1");
        $stmt->bind_param("ss", $doc_name, $category);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($exists){
            $error = "This document type already exists in this category.";
        } else {
            $stmt = $conn->prepare("INSERT INTO document_types (doc_name, category) VALUES (?, ?)");
            $stmt->bind_param("ss", $doc_name, $category);
            if($stmt->execute()){
                // Keep school_id in URL if already selected
                $redirect_school_id = isset($_POST['current_school_id']) ? (int)$_POST['current_school_id'] : 0;

                header("Location: ".$_SERVER['PHP_SELF'].($redirect_school_id>0 ? "?school_id=".$redirect_school_id : ""));
                exit();
            } else {
                $error = "Failed to add document type: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

/** Load schools */
$schools = [];
$res = $conn->query("SELECT id, name FROM schools ORDER BY name ASC");
while($row = $res->fetch_assoc()){
    $schools[] = $row;
}

/** Load all document types (with category) */
$docTypes = [];
$res2 = $conn->query("SELECT id, doc_name, category FROM document_types ORDER BY category ASC, doc_name ASC");
while($row = $res2->fetch_assoc()){
    $cat = trim($row['category'] ?? '');
    if($cat === '') $cat = "Other";
    $docTypes[$cat][] = $row;
}

$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;

/** Get current required docs for selected school */
$requiredMap = []; // doc_type_id => true
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

/** Save requirements */
if(isset($_POST['save_requirements'])){
    $school_id_post = (int)($_POST['school_id'] ?? 0);
    $selected = $_POST['doc_type_ids'] ?? [];

    if($school_id_post <= 0){
        $error = "Please select a school first.";
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
            $success = "Requirements saved successfully!";
            //redirectring to dashboard to avoid resubmiting from on refresh
            header("Location: dashboard.php");
            exit();

            // reload same school
            header("Location: ".$_SERVER['PHP_SELF']."?school_id=".$school_id_post);
            exit();

        } catch(Exception $e){
            $conn->rollback();
            $error = "Save failed: " . $e->getMessage();
        }
    }
}
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
    background: #ffffff;
    margin-bottom: 18px;
}

.side-box {
    position: sticky;
    top: 16px;
}

.category-title {
    font-weight: 700;
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
</style>

<div class="container page-container small-ui">
    <div class="row g-3">

        <!-- LEFT 70% -->
        <div class="col-lg-8">

            <!-- HEADER -->
            <div class="card-box">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="m-0">School Document Requirements</h5>
                    <a href="dashboard.php" class="btn btn-sm btn-secondary">Back</a>
                </div>

                <hr class="hr-tight">

                <?php if($success): ?>
                <div class="alert alert-success py-2"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if($error): ?>
                <div class="alert alert-danger py-2"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>

            <!-- ADD NEW DOCUMENT TYPE -->
            <div class="card-box">
                <h6 class="mb-3 fw-bold">Add New Document Type</h6>

                <form method="POST" class="row g-2">
                    <input type="hidden" name="current_school_id" value="<?php echo (int)$school_id; ?>">

                    <div class="col-md-5">
                        <label class="form-label">Document Name</label>
                        <input type="text" name="doc_name" class="form-control" placeholder="e.g. Tax Document"
                            required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Finance">
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" name="add_doc_type" class="btn btn-dark w-100">
                            Add Document
                        </button>
                    </div>
                </form>
            </div>

            <!-- SELECT SCHOOL -->
            <div class="card-box">
                <h6 class="mb-3 fw-bold">Select School</h6>

                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">School</label>
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
                        <button class="btn btn-primary w-100" type="submit">
                            Load Requirements
                        </button>
                    </div>
                </form>
            </div>

            <!-- REQUIREMENT CHECKLIST -->
            <?php if($school_id > 0): ?>
            <div class="card-box">
                <h6 class="mb-3 fw-bold">Set Required Documents</h6>

                <form method="POST">
                    <input type="hidden" name="school_id" value="<?php echo (int)$school_id; ?>">

                    <?php foreach($docTypes as $category => $docs): ?>
                    <div class="category-title"><?php echo htmlspecialchars($category); ?></div>

                    <div class="row">
                        <?php foreach($docs as $d):
                            $did = (int)$d['id'];
                            $checked = isset($requiredMap[$did]) ? "checked" : "";
                        ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="doc_type_ids[]"
                                    value="<?php echo $did; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label">
                                    <?php echo htmlspecialchars($d['doc_name']); ?>
                                </label>
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

        <!-- RIGHT 30% INFO PANEL -->
        <div class="col-lg-4">
            <div class="card-box side-box">
                <h6 class="fw-bold mb-2">About This Page</h6>
                <div class="text-muted">
                    Configure which documents are required for each school.
                    Students will only see and upload the documents selected here.
                </div>

                <hr>

                <h6 class="fw-bold mb-2">How It Works</h6>
                <ul style="padding-left:16px;">
                    <li>Add global document types (e.g. Tax Document).</li>
                    <li>Select a school.</li>
                    <li>Tick required documents.</li>
                    <li>Save changes.</li>
                </ul>

                <hr>

                <h6 class="fw-bold mb-2">Best Practice</h6>
                <div class="text-muted">
                    Group documents clearly under categories like:
                    Academic, Identity, Finance, Language, etc.
                    This keeps student dashboards clean and organized.
                </div>

                <hr>

                <div class="d-grid">
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        Return to Dashboard
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>