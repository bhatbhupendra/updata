<?php
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
if($school_id <= 0){
    die("No school selected.");
}

$success = "";
$error = "";

/**
 * Fetch school
 */
$stmt = $conn->prepare("SELECT id, name FROM schools WHERE id=?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$school = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$school){
    die("School not found.");
}


/**
 * Handle UPDATE school name
 */
if(isset($_POST['update_school'])){
    $new_name = trim($_POST['school_name'] ?? '');

    if($new_name === ''){
        $error = "School name is required.";
    } else {
        $stmt = $conn->prepare("UPDATE schools SET name=? WHERE id=?");
        if(!$stmt){
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("si", $new_name, $school_id);
            if($stmt->execute()){
                $stmt->close();
                header("Location: ".$_SERVER['PHP_SELF']."?school_id=".$school_id."&msg=school_updated");
                exit();
            } else {
                $error = "Update failed: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

/**
 * Handle DELETE school
 * (Safe rule: do not delete if students exist)
 */
if(isset($_POST['delete_school'])){
    if($studentCount > 0){
        $error = "Cannot delete this school because students are enrolled. Remove/move students first.";
    } else {

        // Also delete requirements mapping (safe cleanup)
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM school_required_docs WHERE school_id=?");
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM schools WHERE id=?");
            $stmt->bind_param("i", $school_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            header("Location: dashboard.php?msg=school_deleted");
            exit();

        } catch(Exception $e){
            $conn->rollback();
            $error = "Delete failed: " . $e->getMessage();
        }
    }
}



?>




<div class="d-flex gap-2">
    <button class="btn btn-sm btn-dark" data-bs-toggle="modal" data-bs-target="#editSchoolModal">
        Edit Name
    </button>
    <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteSchoolModal">
        Delete
    </button>
</div>

<!-- EDIT MODAL (RIGHT-BOTTOM) -->
<div class="modal fade modal-mini" id="editSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:800;">Edit School Name</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <label class="form-label">School Name</label>
                    <input type="text" class="form-control" name="school_name"
                        value="<?php echo htmlspecialchars($school['name']); ?>" required>
                    <div class="text-muted mt-2" style="font-size:12px;">
                        This updates the school name for future students and requirement pages.
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_school" class="btn btn-sm btn-dark">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE MODAL (RIGHT-BOTTOM) -->
<div class="modal fade modal-mini" id="deleteSchoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:800;">Delete School</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning py-2 mb-2">
                        This action cannot be undone.
                    </div>
                    <div style="font-weight:700;">
                        Delete: "<?php echo htmlspecialchars($school['name']); ?>" ?
                    </div>
                    <div class="text-muted mt-2" style="font-size:12px;">
                        <?php if($studentCount > 0): ?>
                        Delete is disabled because <b><?php echo (int)$studentCount; ?></b> students are enrolled.
                        <?php else: ?>
                        This will also remove school requirement mappings.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_school" class="btn btn-sm btn-danger"
                        <?php echo ($studentCount > 0) ? "disabled" : ""; ?>>
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>