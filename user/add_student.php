<?php
session_start();
include '../config/db.php';
include '../includes/notification_functions.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}

$success = "";
$error = "";

/**
 * Load schools for dropdown
 */
$schools = [];
$school_result = $conn->query("SELECT id, name FROM schools ORDER BY name ASC");
if($school_result){
    while($s = $school_result->fetch_assoc()){
        $schools[] = $s;
    }
}

if(isset($_POST['add_student'])){

    $user_id = (int)$_SESSION['user_id'];

    // Required
    $school_id = 35; // Default to "pre school" school (id=35) for every new student. 
    $intake = $_POST['intake'] ?? null;
    $student_name = trim($_POST['student_name'] ?? '');

    // Optional / other fields
    $student_name_jp = trim($_POST['student_name_jp'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? null; // YYYY-MM-DD or null
    $nationality = trim($_POST['nationality'] ?? '');
    $permanent_address = trim($_POST['permanent_address'] ?? '');

    $highest_qualification = trim($_POST['highest_qualification'] ?? '');
    $last_institution_name = trim($_POST['last_institution_name'] ?? '');
    $graduation_year = $_POST['graduation_year'] ?? null;

    $japanese_level = trim($_POST['japanese_level'] ?? '');
    $japanese_test_type = trim($_POST['japanese_test_type'] ?? '');
    $japanese_exam_score = trim($_POST['japanese_exam_score'] ?? '');

    $sponsor_name_1 = trim($_POST['sponsor_name_1'] ?? '');
    $sponsor_relationship_1 = trim($_POST['sponsor_relationship_1'] ?? '');
    $sponsor_occupation_1 = trim($_POST['sponsor_occupation_1'] ?? '');
    $sponsor_annual_income_1 = $_POST['sponsor_annual_income_1'] ?? null;
    $sponsor_savings_amount_1 = $_POST['sponsor_savings_amount_1'] ?? null;

    $sponsor_name_2 = trim($_POST['sponsor_name_2'] ?? '');
    $sponsor_relationship_2 = trim($_POST['sponsor_relationship_2'] ?? '');
    $sponsor_occupation_2 = trim($_POST['sponsor_occupation_2'] ?? '');
    $sponsor_annual_income_2 = $_POST['sponsor_annual_income_2'] ?? null;
    $sponsor_savings_amount_2 = $_POST['sponsor_savings_amount_2'] ?? null;

    $career_path = trim($_POST['career_path'] ?? '');

    // Basic validation
    if($student_name === "" ||$student_name_jp === "" || $school_id <= 0){
        $error = "Student Name, Japanese Name, and School are required.";
    } else {

        // ---- Calculate age from DOB ----
        $age = null;
        if(!empty($date_of_birth)){
            try {
                $dob = new DateTime($date_of_birth);
                $today = new DateTime();
                $age = $today->diff($dob)->y; // years
            } catch(Exception $e){
                $age = null; // invalid date
            }
        }

        // Convert empty strings to NULL (optional but cleaner)
        $toNull = function($v){
            $v = trim((string)$v);
            return ($v === "") ? null : $v;
        };

        $student_name_jp = $toNull($student_name_jp);
        $gender = $toNull($gender);
        $nationality = $toNull($nationality);
        $permanent_address = $toNull($permanent_address);

        $highest_qualification = $toNull($highest_qualification);
        $last_institution_name = $toNull($last_institution_name);

        $japanese_level = $toNull($japanese_level);
        $japanese_test_type = $toNull($japanese_test_type);
        $japanese_exam_score = $toNull($japanese_exam_score);

        $sponsor_name_1 = $toNull($sponsor_name_1);
        $sponsor_relationship_1 = $toNull($sponsor_relationship_1);
        $sponsor_occupation_1 = $toNull($sponsor_occupation_1);
        $sponsor_annual_income_1 = ($sponsor_annual_income_1 === "" || $sponsor_annual_income_1 === null) ? null : (float)$sponsor_annual_income_1;
        $sponsor_savings_amount_1 = ($sponsor_savings_amount_1 === "" || $sponsor_savings_amount_1 === null) ? null : (float)$sponsor_savings_amount_1;

        $sponsor_name_2 = $toNull($sponsor_name_2);
        $sponsor_relationship_2 = $toNull($sponsor_relationship_2);
        $sponsor_occupation_2 = $toNull($sponsor_occupation_2);
        $sponsor_annual_income_2 = ($sponsor_annual_income_2 === "" || $sponsor_annual_income_2 === null) ? null : (float)$sponsor_annual_income_2;
        $sponsor_savings_amount_2 = ($sponsor_savings_amount_2 === "" || $sponsor_savings_amount_2 === null) ? null : (float)$sponsor_savings_amount_2;

        $career_path = $toNull($career_path);

        // Normalize numbers
        $graduation_year = ($graduation_year === "" || $graduation_year === null) ? null : (int)$graduation_year;

        // Insert
        $stmt = $conn->prepare("
            INSERT INTO students (
                user_id, school_id,intake,
                student_name, student_name_jp,
                gender, date_of_birth, age,
                nationality, permanent_address,
                highest_qualification, last_institution_name, graduation_year,
                japanese_level, japanese_test_type, japanese_exam_score,
                sponsor_name_1, sponsor_relationship_1, sponsor_occupation_1,
                sponsor_annual_income_1, sponsor_savings_amount_1,
                sponsor_name_2, sponsor_relationship_2, sponsor_occupation_2,
                sponsor_annual_income_2, sponsor_savings_amount_2,
                career_path
            ) VALUES (
                ?, ?,?,
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?,
                ?
            )
        ");

        if(!$stmt){
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param(
                "iisssssissssissssssddsssdds",
                $user_id, $school_id, $intake,
                $student_name, $student_name_jp,
                $gender, $date_of_birth, $age,
                $nationality, $permanent_address,
                $highest_qualification, $last_institution_name, $graduation_year,
                $japanese_level, $japanese_test_type, $japanese_exam_score,
                $sponsor_name_1, $sponsor_relationship_1, $sponsor_occupation_1,
                $sponsor_annual_income_1, $sponsor_savings_amount_1,
                $sponsor_name_2, $sponsor_relationship_2, $sponsor_occupation_2,
                $sponsor_annual_income_2, $sponsor_savings_amount_2,
                $career_path
            );

            if($stmt->execute()){
                $student_id = $conn->insert_id;
                notifyNewStudent($conn, $student_id, $student_name, $user_id);//add notification for new student
                $success = "Student Added Successfully! (ID: $student_id)";
                //redirectring to dashboard to avoid resubmiting from on refresh
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Error: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

?>
<?php include '../template/login_status.php'; ?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}

.page-container {
    max-width: 1200px;
    margin: 24px auto;
}

.card-box {
    padding: 18px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    background: #fff;
}

.small-ui,
.small-ui * {
    font-size: 12.5px;
}

.form-label {
    margin-bottom: 4px;
    font-weight: 600;
}

.form-control,
.form-select {
    padding: .38rem .55rem;
}

.mb-tight {
    margin-bottom: 10px !important;
}

.section-title {
    font-weight: 800;
    font-size: 13px;
    margin: 10px 0 8px;
    padding-top: 6px;
    border-top: 1px dashed #ddd;
}

.req {
    color: #dc3545;
}
</style>

<div class="container page-container small-ui">
    <div class="card-box">

        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <h5 class="m-0">Add Student (Japan)--<span class="req">*(star)</span>=> Must Be Filled Otherwiese form
                    will not submit.</h5>
                <div class="text-muted" style="font-size:12px;">Single form • Compact view</div>
            </div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Back</a>
        </div>

        <?php if(!empty($success)): ?>
        <div class="alert alert-success py-2"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
        <div class="alert alert-danger py-2"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">

            <!-- BASIC -->
            <div class="section-title">Basic</div>
            <div class="row g-2">
                <div class="col-md-4 mb-tight">
                    <label class="form-label">Intake<span class="req">* Must Be Filled</span></label>
                    <select name="intake" class="form-select" required>
                        <option value="">Required Intake</option>
                        <option value="2026-01(January)">2026-01(January)
                        </option>
                        <option value="2026-04(April)">
                            2026-04(April)
                        </option>
                        <option value="2026-07(July)">
                            2026-07(July)</option>
                        <option value="2026-10(October)">2026-10(October)
                        </option>
                    </select>
                </div>

                <div class="col-md-4 mb-tight">
                    <label class="form-label">Student Name <span class="req">* Must Be Filled</span></label>
                    <input type="text" name="student_name" class="form-control" placeholder="Student Name (EN)"
                        required>
                </div>

                <div class="col-md-4 mb-tight">
                    <label class="form-label">Student Name (JP)<span class="req">* Must Be Filled</span></label>
                    <input type="text" name="student_name_jp" class="form-control" placeholder="Student Name (JP)"
                        required>
                </div>

                <div class="col-md-3 mb-tight">
                    <label class="form-label">Gender<span class="req">* Must Be Filled</span></label>
                    <select name="gender" class="form-select" require>
                        <option value="">--</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="col-md-3 mb-tight">
                    <label class="form-label">Date of Birth<span class="req">* Must Be Filled</span></label>
                    <input type="date" name="date_of_birth" id="date_of_birth" class="form-control" required>
                </div>

                <div class="col-md-2 mb-tight">
                    <label class="form-label">Age</label>
                    <input type="number" name="age_display" id="age_display" class="form-control" readonly>
                    <div class="text-muted" style="font-size:11px;">Auto from DOB</div>
                </div>

                <div class="col-md-2 mb-tight">
                    <label class="form-label">Nationality</label>
                    <input type="text" name="nationality" class="form-control" placeholder="Nepalese">
                </div>

                <div class="col-md-2 mb-tight">
                    <label class="form-label">Permanent Address</label>
                    <input type="text" name="permanent_address" class="form-control" placeholder="City, District">
                </div>
            </div>

            <!-- EDUCATION -->
            <div class="section-title">Education</div>
            <div class="row g-2">
                <div class="col-md-4 mb-tight">
                    <label class="form-label">Highest Qualification<span class="req">* Must Be Filled</span></label>
                    <input type="text" name="highest_qualification" class="form-control"
                        placeholder="SEE / +2 / Bachelor" required>
                </div>

                <div class="col-md-6 mb-tight">
                    <label class="form-label">Last Institution Name<span class="req">* Must Be Filled</span></label>
                    <input type="text" name="last_institution_name" class="form-control" required
                        placeholder="College/University">
                </div>

                <div class="col-md-2 mb-tight">
                    <label class="form-label">Graduation Year<span class="req">* Must Be Filled</span></label>
                    <input type="number" name="graduation_year" class="form-control" placeholder="2024" required>
                </div>
            </div>

            <!-- JAPANESE -->
            <div class="section-title">Japanese</div>
            <div class="row g-2">
                <div class="col-md-4 mb-tight">
                    <label class="form-label">Japanese Level</label>
                    <input type="text" name="japanese_level" class="form-control" placeholder="N5 / N4 / N3">
                </div>

                <div class="col-md-4 mb-tight">
                    <label class="form-label">Japanese Test Type</label>
                    <input type="text" name="japanese_test_type" class="form-control" placeholder="JLPT / NAT / J-Test">
                </div>

                <div class="col-md-4 mb-tight">
                    <label class="form-label">Japanese Exam Score</label>
                    <input type="text" name="japanese_exam_score" class="form-control" placeholder="e.g. 110/180 or A2">
                </div>
            </div>

            <!-- SPONSOR / FINANCE -->
            <div class="section-title">Sponsor / Finance</div>
            <div class="row g-2">
                <div class="col-md-4 mb-tight">
                    <label class="form-label">Sponsor 1 Name</label>
                    <input type="text" name="sponsor_name_1" class="form-control">
                </div>

                <div class="col-md-3 mb-tight">
                    <label class="form-label">Sponsor 1 Relationship</label>
                    <input type="text" name="sponsor_relationship_1" class="form-control" placeholder="Father/Mother">
                </div>

                <div class="col-md-5 mb-tight">
                    <label class="form-label">Sponsor 1 Occupation</label>
                    <input type="text" name="sponsor_occupation_1" class="form-control"
                        placeholder="Business / Job / Farmer">
                </div>

                <div class="col-md-6 mb-tight">
                    <label class="form-label">Sponsor 1 Annual Income</label>
                    <input type="number" step="0.01" name="sponsor_annual_income_1" class="form-control"
                        placeholder="e.g. 1200000">
                </div>

                <div class="col-md-6 mb-tight">
                    <label class="form-label">Sponsor 2 Savings Amount</label>
                    <input type="number" step="0.01" name="sponsor_savings_amount_2" class="form-control"
                        placeholder="e.g. 2500000">
                </div>
                <div class="col-md-4 mb-tight">
                    <label class="form-label">Sponsor 2 Name</label>
                    <input type="text" name="sponsor_name_2" class="form-control">
                </div>

                <div class="col-md-3 mb-tight">
                    <label class="form-label">Sponsor 2 Relationship</label>
                    <input type="text" name="sponsor_relationship_2" class="form-control" placeholder="Father/Mother">
                </div>

                <div class="col-md-5 mb-tight">
                    <label class="form-label">Sponsor 2 Occupation</label>
                    <input type="text" name="sponsor_occupation_2" class="form-control"
                        placeholder="Business / Job / Farmer">
                </div>

                <div class="col-md-6 mb-tight">
                    <label class="form-label">Sponsor 2 Annual Income</label>
                    <input type="number" step="0.01" name="sponsor_annual_income_2" class="form-control"
                        placeholder="e.g. 1200000">
                </div>

                <div class="col-md-6 mb-tight">
                    <label class="form-label">Sponsor 2 Savings Amount</label>
                    <input type="number" step="0.01" name="sponsor_savings_amount_2" class="form-control"
                        placeholder="e.g. 2500000">
                </div>
            </div>

            <!-- CAREER -->
            <div class="section-title">Career</div>
            <div class="row g-2">
                <div class="col-12 mb-tight">
                    <label class="form-label">Career Path (Future Plan)</label>
                    <input type="text" name="career_path" class="form-control"
                        placeholder="e.g. Return Nepal and work in IT / Business">
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <button type="submit" name="add_student" class="btn btn-primary btn-sm px-4">
                    Save Student
                </button>
            </div>

        </form>

    </div>
</div>

<script>
(function() {
    const dob = document.getElementById('date_of_birth');
    const age = document.getElementById('age_display');

    function calcAge(val) {
        if (!val) {
            age.value = "";
            return;
        }
        const d = new Date(val);
        if (isNaN(d.getTime())) {
            age.value = "";
            return;
        }
        const today = new Date();
        let years = today.getFullYear() - d.getFullYear();
        const m = today.getMonth() - d.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < d.getDate())) years--;
        age.value = years >= 0 ? years : "";
    }

    dob?.addEventListener('change', function() {
        calcAge(this.value);
    });
})();
</script>