<?php
include '../config/db.php';

$success = "";
$error = "";

if(isset($_POST['register'])){
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    if($name === '' || $email === '' || $password_raw === ''){
        $error = "All fields are required.";
    } else {

        // safer: prepared statements
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($exists){
            $error = "Email already exists!";
        } else {
            $password = password_hash($password_raw, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
            if(!$stmt){
                $error = "Prepare failed: " . $conn->error;
            } else {
                $stmt->bind_param("sss", $name, $email, $password);
                if($stmt->execute()){
                    $success = "Registered Successfully!";
                    // Prevent resubmission
                    header("Location: ".$_SERVER['PHP_SELF']."?success=1");
                    exit();
                } else {
                    $error = "Something went wrong. Try again.";
                }
                $stmt->close();
            }
        }
    }
}

// show toast-like messages from redirect
if(isset($_GET['success']) && $_GET['success'] == "1"){
    $success = "Registered Successfully!";
}

require_once '../template/login_status.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add New User/Agent</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
    body {
        background: #f4f6f9;
    }

    .page-container {
        max-width: 1200px;
        margin: 22px auto;
    }

    .small-ui,
    .small-ui * {
        font-size: 12.5px;
    }

    .card-box {
        padding: 16px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        background: #fff;
        margin-bottom: 16px;
    }

    .side-box {
        position: sticky;
        top: 16px;
    }

    .form-label {
        margin-bottom: 4px;
        font-weight: 800;
    }

    .form-control,
    .form-select {
        padding: .38rem .55rem;
    }

    .mb-tight {
        margin-bottom: 10px !important;
    }

    .badge-soft {
        background: #eef2ff;
        color: #2b3a67;
        border: 1px solid #d6ddff;
        font-weight: 800;
    }

    /* Bottom-right toast (popup) */
    .toast-pop {
        position: fixed;
        right: 16px;
        bottom: 16px;
        z-index: 1080;
        min-width: 280px;
        max-width: 380px;
        border-radius: 12px;
        padding: 12px 14px;
        box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18);
        display: none;
        animation: slideIn .22s ease-out;
    }

    @keyframes slideIn {
        from {
            transform: translateY(10px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    </style>
</head>

<body class="small-ui">

    <div class="container page-container">
        <div class="row g-3">

            <!-- LEFT 70% -->
            <div class="col-lg-9">

                <div class="card-box">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="m-0" style="font-weight:900;">Add New User / Agent</h5>
                            <div class="text-muted" style="font-size:12px;">Create a new agent account (role: user)
                            </div>
                        </div>
                        <a href="dashboard.php" class="btn btn-secondary btn-sm">← Back</a>
                    </div>
                </div>

                <div class="card-box">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="m-0" style="font-weight:900;">Registration Form</h6>
                            <div class="text-muted" style="font-size:12px;">Fill details and click Register</div>
                        </div>
                        <span class="badge badge-soft">Role: user</span>
                    </div>

                    <hr class="my-2">

                    <form method="POST" autocomplete="off">
                        <div class="row g-2">

                            <div class="col-md-6 mb-tight">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Ram Bahadur"
                                    required>
                            </div>

                            <div class="col-md-6 mb-tight">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" placeholder="e.g. agent@gmail.com"
                                    required>
                            </div>

                            <div class="col-md-6 mb-tight">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" class="form-control"
                                    placeholder="Create a password" required>
                            </div>

                            <div class="col-md-6 mb-tight">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control" value="Active (default)" disabled>
                            </div>

                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                            <button type="submit" name="register" class="btn btn-dark btn-sm px-4">
                                Register
                            </button>
                        </div>
                    </form>

                </div>

            </div>

            <!-- RIGHT 30% -->
            <div class="col-lg-3">
                <div class="card-box side-box">
                    <h6 class="mb-2" style="font-weight:900;">About this page</h6>
                    <div class="text-muted" style="font-size:12px; line-height:1.5;">
                        This page creates a new <b>User/Agent</b> account. Agents can add students and upload documents.
                    </div>

                    <hr class="my-3">

                    <div class="mb-2" style="font-weight:900;">How it works</div>
                    <ul class="mb-0" style="padding-left:16px; line-height:1.55;">
                        <li>Email must be unique.</li>
                        <li>Password is stored securely (hashed).</li>
                        <li>New account role is set to <b>user</b>.</li>
                        <li>Admin can view agent’s students from dashboard.</li>
                    </ul>

                    <hr class="my-3">

                    <div class="mb-2" style="font-weight:900;">Tips</div>
                    <div class="d-flex flex-column gap-2">
                        <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                            Use a real email because the agent will login using it.
                        </div>
                        <div class="p-2 rounded" style="background:#f8fafc; border:1px solid #e5e7eb;">
                            After creating, go back to dashboard to see the agent list.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bottom-right popup messages -->
    <?php if($success): ?>
    <div id="toastMsg" class="toast-pop" style="background:#198754; color:#fff;">
        <div style="font-weight:800;">Success</div>
        <div><?php echo htmlspecialchars($success); ?></div>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div id="toastMsg" class="toast-pop" style="background:#dc3545; color:#fff;">
        <div style="font-weight:800;">Error</div>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
    <?php endif; ?>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const t = document.getElementById("toastMsg");
        if (t) {
            t.style.display = "block";
            setTimeout(() => {
                t.style.display = "none";
            }, 3500);
        }
    });
    </script>

</body>

</html>