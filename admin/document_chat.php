<?php
session_start();
include '../config/db.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../login.php");
    exit();
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
$role    = $_SESSION['role'] ?? 'user';

$success = "";
$error   = "";

$document_id = isset($_GET['document_id']) ? (int)$_GET['document_id'] : 0;
if($document_id <= 0){
    die("Invalid document ID.");
}

/* ---------------------------------
   LOAD DOCUMENT + STUDENT DETAILS
---------------------------------- */
if($role === 'admin'){
    $stmt = $conn->prepare("
        SELECT
            sd.id AS document_id,
            sd.file_name,
            sd.file_path,
            sd.uploaded_at,
            sd.verify_status,
            sd.verify_message,
            sd.verified_at,
            s.id AS student_id,
            s.student_name,
            s.user_id AS agent_user_id,
            dt.doc_name,
            dt.file_type,
            sc.name AS school_name
        FROM student_documents sd
        JOIN students s ON s.id = sd.student_id
        LEFT JOIN document_types dt ON dt.id = sd.doc_type_id
        LEFT JOIN schools sc ON sc.id = s.school_id
        WHERE sd.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $document_id);
} else {
    $stmt = $conn->prepare("
        SELECT
            sd.id AS document_id,
            sd.file_name,
            sd.file_path,
            sd.uploaded_at,
            sd.verify_status,
            sd.verify_message,
            sd.verified_at,
            s.id AS student_id,
            s.student_name,
            s.user_id AS agent_user_id,
            dt.doc_name,
            dt.file_type,
            sc.name AS school_name
        FROM student_documents sd
        JOIN students s ON s.id = sd.student_id
        LEFT JOIN document_types dt ON dt.id = sd.doc_type_id
        LEFT JOIN schools sc ON sc.id = s.school_id
        WHERE sd.id = ? AND s.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $document_id, $user_id);
}
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$doc){
    die("Document not found or access denied.");
}

$student_id      = (int)$doc['student_id'];
$student_name    = $doc['student_name'] ?? '';
$doc_name        = $doc['doc_name'] ?? 'Document';
$file_name       = $doc['file_name'] ?? '';
$file_path       = $doc['file_path'] ?? '';
$file_type       = strtolower($doc['file_type'] ?? 'pdf');
$school_name     = $doc['school_name'] ?? '';
$verify_status   = strtolower($doc['verify_status'] ?? 'pending');
$verify_message  = $doc['verify_message'] ?? '';

/* ---------------------------------
   VERIFY HANDLER (ADMIN ONLY)
---------------------------------- */
if($role === 'admin' && isset($_POST['verify_action'])){
    $action  = strtolower(trim($_POST['action'] ?? ''));
    $message = trim($_POST['verify_message'] ?? '');

    if(!in_array($action, ['approved','disapproved'], true)){
        $error = "Invalid action.";
    } else {
        $admin_id = (int)$_SESSION['user_id'];

        $stmt = $conn->prepare("
            UPDATE student_documents
            SET verify_status=?,
                verify_message=?,
                verified_by=?,
                verified_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("ssii", $action, $message, $admin_id, $document_id);

        if($stmt->execute()){
            $stmt->close();
            header("Location: ".$_SERVER['PHP_SELF']."?document_id=".$document_id."&verified=1");
            exit();
        } else {
            $error = "Verify failed: ".$stmt->error;
            $stmt->close();
        }
    }
}

if(isset($_GET['verified']) && $_GET['verified'] == '1'){
    $success = "Document verification saved.";
}

/* ---------------------------------
   SEND CHAT MESSAGE
---------------------------------- */
if(isset($_POST['chat_send'])){
    $chat_message = trim($_POST['chat_message'] ?? '');

    if($chat_message === ""){
        $error = "Chat message cannot be empty.";
    } else {
        $sender = ($role === 'admin') ? "ADMIN" : "AGENT";
        $ts = date("Y-m-d H:i");

        $chat_message = str_replace(["\r\n", "\r"], "\n", $chat_message);
        $line = "[".$ts."] ".$sender.": ".$chat_message;

        $stmt = $conn->prepare("SELECT id, chat FROM document_live_chat WHERE document_id=? LIMIT 1");
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($existing){
            $newChat = trim((string)($existing['chat'] ?? ''));
            if($newChat !== ""){
                $newChat .= "\n\n".$line;
            } else {
                $newChat = $line;
            }

            $up = $conn->prepare("UPDATE document_live_chat SET chat=?, updated_at=NOW() WHERE document_id=?");
            $up->bind_param("si", $newChat, $document_id);

            if($up->execute()){
                $up->close();
                header("Location: ".$_SERVER['PHP_SELF']."?document_id=".$document_id."&sent=1");
                exit();
            } else {
                $error = "Chat save failed: ".$up->error;
                $up->close();
            }
        } else {
            $ins = $conn->prepare("INSERT INTO document_live_chat (document_id, chat, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            $ins->bind_param("is", $document_id, $line);

            if($ins->execute()){
                $ins->close();
                header("Location: ".$_SERVER['PHP_SELF']."?document_id=".$document_id."&sent=1");
                exit();
            } else {
                $error = "Chat insert failed: ".$ins->error;
                $ins->close();
            }
        }
    }
}

if(isset($_GET['sent']) && $_GET['sent'] == '1'){
    $success = "Chat message sent.";
}

/* ---------------------------------
   LOAD CHAT HISTORY
---------------------------------- */
$chat_history = "";
$stmt = $conn->prepare("SELECT chat FROM document_live_chat WHERE document_id=? LIMIT 1");
$stmt->bind_param("i", $document_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if($row){
    $chat_history = $row['chat'] ?? '';
}

require_once '../template/login_status.php';
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: #f4f6f9;
}

.page-wrap {
    max-width: 1100px;
    margin: 22px auto;
}

.card-box {
    background: #fff;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, .08);
    margin-bottom: 16px;
}

.small-ui,
.small-ui * {
    font-size: 12.5px;
}

.small-muted {
    font-size: 12px;
    color: #6c757d;
}

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

.chat-box {
    height: 420px;
    overflow: auto;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #f8fafc;
    padding: 12px;
    white-space: pre-wrap;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px;
}

.info-chip {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 700;
    border: 1px solid #dbeafe;
    background: #eff6ff;
    color: #1d4ed8;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 800;
    text-transform: uppercase;
    border: 1px solid transparent;
}

.status-pending {
    background: #fff7ed;
    color: #9a3412;
    border-color: #fed7aa;
}

.status-approved {
    background: #ecfdf5;
    color: #166534;
    border-color: #bbf7d0;
}

.status-disapproved {
    background: #fef2f2;
    color: #991b1b;
    border-color: #fecaca;
}

.viewer-modal .modal-dialog {
    max-width: 980px;
}

.viewer-frame {
    width: 100%;
    height: 70vh;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    overflow: auto;
    position: relative;
}

.viewer-inner {
    transform-origin: top left;
}

.viewer-img {
    max-width: 100%;
    height: auto;
    display: block;
}
</style>

<div class="container page-wrap small-ui">
    <?php if(!empty($success)): ?>
    <div id="toastMsg" class="toast-pop" style="background:#198754;color:#fff;">
        <div style="font-weight:900;">Success</div>
        <div><?php echo htmlspecialchars($success); ?></div>
    </div>
    <?php endif; ?>

    <?php if(!empty($error)): ?>
    <div id="toastMsg" class="toast-pop" style="background:#dc3545;color:#fff;">
        <div style="font-weight:900;">Error</div>
        <div><?php echo htmlspecialchars($error); ?></div>
    </div>
    <?php endif; ?>

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h5 class="m-0">Document Live Chat</h5>
                <div class="small-muted">Admin ↔ Agent chat for this file</div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-success btnViewFile"
                    data-docid="<?php echo $document_id; ?>" data-filetype="<?php echo htmlspecialchars($file_type); ?>"
                    data-fileurl="<?php echo htmlspecialchars($file_path); ?>"
                    data-docname="<?php echo htmlspecialchars($doc_name); ?>"
                    data-verifystatus="<?php echo htmlspecialchars($verify_status); ?>">
                    View File
                </button>

                <?php if(!empty($file_path)): ?>
                <a href="<?php echo htmlspecialchars($file_path); ?>" class="btn btn-sm btn-outline-dark"
                    download>Download File</a>
                <?php endif; ?>

                <?php if($role === 'admin'): ?>
                <button type="button" class="btn btn-sm btn-dark btnOpenVerify" data-docid="<?php echo $document_id; ?>"
                    data-action="approved" data-docname="<?php echo htmlspecialchars($doc_name); ?>">
                    Approve
                </button>

                <button type="button" class="btn btn-sm btn-outline-danger btnOpenVerify"
                    data-docid="<?php echo $document_id; ?>" data-action="disapproved"
                    data-docname="<?php echo htmlspecialchars($doc_name); ?>">
                    Disapprove
                </button>
                <?php endif; ?>

                <a href="student_file.php?student_id=<?php echo $student_id; ?>"
                    class="btn btn-sm btn-secondary">Back</a>
            </div>
        </div>

        <hr>

        <div class="row g-3">
            <div class="col-md-6">
                <div><b>Student:</b> <?php echo htmlspecialchars($student_name); ?></div>
                <div><b>School:</b> <?php echo htmlspecialchars($school_name); ?></div>
                <div><b>Document:</b> <?php echo htmlspecialchars($doc_name); ?></div>
            </div>

            <div class="col-md-6">
                <div><b>Document ID:</b> <span class="info-chip"><?php echo $document_id; ?></span></div>
                <div><b>File:</b> <?php echo htmlspecialchars($file_name); ?></div>
                <div>
                    <b>Status:</b>
                    <?php
                        $statusClass = 'status-pending';
                        if($verify_status === 'approved') $statusClass = 'status-approved';
                        if($verify_status === 'disapproved') $statusClass = 'status-disapproved';
                    ?>
                    <span class="status-chip <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($verify_status ?: 'pending'); ?>
                    </span>
                </div>

                <?php if(!empty($verify_message)): ?>
                <div class="mt-1"><b>Message:</b> <?php echo htmlspecialchars($verify_message); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-box">
        <div class="mb-2" style="font-weight:900;">Chat History</div>
        <div class="chat-box" id="chatHistory">
            <?php echo trim($chat_history) !== '' ? htmlspecialchars($chat_history) : 'No chat yet...'; ?></div>

        <form method="POST" class="mt-3">
            <input type="hidden" name="chat_send" value="1">

            <label class="form-label fw-bold mb-1">New Message</label>
            <textarea name="chat_message" class="form-control form-control-sm" rows="4" placeholder="Type message..."
                required></textarea>

            <div class="d-flex justify-content-end gap-2 mt-2">
                <button type="submit" class="btn btn-sm btn-primary">Send</button>
            </div>
        </form>
    </div>
</div>

<!-- FILE VIEWER MODAL -->
<div class="modal fade viewer-modal" id="fileViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header py-2">
                <div>
                    <div style="font-weight:900;" id="viewerTitle">Document Viewer</div>
                    <div class="text-muted" style="font-size:12px;" id="viewerSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-dark" id="zoomOutBtn">-</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="zoomResetBtn">Reset</button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="zoomInBtn">+</button>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="#" class="btn btn-sm btn-outline-dark" id="viewerDownloadBtn" download>Download</a>

                        <?php if($role === 'admin'): ?>
                        <button type="button" class="btn btn-sm btn-dark" id="viewerApproveBtn">Approve</button>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                            id="viewerDisapproveBtn">Disapprove</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="viewer-frame">
                    <div class="viewer-inner" id="viewerInner">
                        <iframe id="viewerPdf" src="" style="display:none;width:100%;height:70vh;border:0;"></iframe>
                        <img id="viewerImg" src="" alt="Image" class="viewer-img" style="display:none;">

                        <div id="viewerUnsupported" style="display:none;padding:14px;">
                            <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e5e7eb;">
                                <div style="font-weight:900;">Preview not available</div>
                                <div class="text-muted" style="font-size:12px;">
                                    This file type cannot be previewed in browser. Please download it.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- VERIFY MODAL -->
<div class="modal fade" id="verifyConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header py-2">
                <h6 class="modal-title" style="font-weight:900;" id="verifyTitle">Verify</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="verify_action" value="1">
                    <input type="hidden" name="action" id="verifyAction" value="">

                    <div class="p-2 rounded mb-2" style="background:#f8fafc;border:1px solid #e5e7eb;">
                        <div style="font-weight:900;" id="verifyDocName">Document</div>
                        <div class="text-muted" style="font-size:12px;">
                            Are you sure you want to <b id="verifyActionText">approve</b> this file?
                        </div>
                    </div>

                    <label class="form-label" style="font-weight:900;">Message / Comment</label>
                    <textarea name="verify_message" class="form-control form-control-sm" rows="3"
                        placeholder="Write reason / comment (optional)"></textarea>
                </div>

                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-dark" id="verifySubmitBtn">Confirm</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const t = document.getElementById('toastMsg');
    if (t) {
        t.style.display = 'block';
        setTimeout(() => t.style.display = 'none', 3500);
    }

    const box = document.getElementById('chatHistory');
    if (box) {
        box.scrollTop = box.scrollHeight;
    }

    const fileViewerModal = new bootstrap.Modal(document.getElementById('fileViewerModal'));
    const verifyConfirmModal = new bootstrap.Modal(document.getElementById('verifyConfirmModal'));

    let currentZoom = 1;
    let currentDocId = <?php echo (int)$document_id; ?>;
    let currentDocName = <?php echo json_encode($doc_name); ?>;
    let currentVerifyStatus = <?php echo json_encode($verify_status); ?>;

    const viewerInner = document.getElementById('viewerInner');
    const viewerPdf = document.getElementById('viewerPdf');
    const viewerImg = document.getElementById('viewerImg');
    const viewerUnsupported = document.getElementById('viewerUnsupported');
    const viewerTitle = document.getElementById('viewerTitle');
    const viewerSub = document.getElementById('viewerSub');
    const viewerDownloadBtn = document.getElementById('viewerDownloadBtn');

    function applyZoom() {
        viewerInner.style.transform = "scale(" + currentZoom + ")";
    }

    function resetZoom() {
        currentZoom = 1;
        applyZoom();
    }

    document.getElementById('zoomInBtn')?.addEventListener('click', () => {
        currentZoom = Math.min(3, currentZoom + 0.15);
        applyZoom();
    });

    document.getElementById('zoomOutBtn')?.addEventListener('click', () => {
        currentZoom = Math.max(0.5, currentZoom - 0.15);
        applyZoom();
    });

    document.getElementById('zoomResetBtn')?.addEventListener('click', resetZoom);

    function setViewerMode(mode) {
        viewerPdf.style.display = "none";
        viewerImg.style.display = "none";
        viewerUnsupported.style.display = "none";
        viewerPdf.src = "";
        viewerImg.src = "";

        if (mode === "pdf") viewerPdf.style.display = "block";
        if (mode === "img") viewerImg.style.display = "block";
        if (mode === "unsupported") viewerUnsupported.style.display = "block";
    }

    document.querySelectorAll('.btnViewFile').forEach(btn => {
        btn.addEventListener('click', () => {
            const url = btn.dataset.fileurl || '';
            const ft = (btn.dataset.filetype || 'pdf').toLowerCase();
            currentDocId = parseInt(btn.dataset.docid || '0', 10);
            currentDocName = btn.dataset.docname || 'Document';
            currentVerifyStatus = btn.dataset.verifystatus || 'pending';

            viewerTitle.textContent = currentDocName;
            viewerSub.textContent = "Type: " + ft.toUpperCase() + " • Status: " +
                currentVerifyStatus.toUpperCase();
            viewerDownloadBtn.href = url;

            resetZoom();

            if (ft === 'jpg' || ft === 'jpeg') {
                setViewerMode("img");
                viewerImg.src = url;
            } else if (ft === 'pdf') {
                setViewerMode("pdf");
                viewerPdf.src = url;
            } else {
                setViewerMode("unsupported");
            }

            fileViewerModal.show();
        });
    });

    function openVerifyConfirm(action) {
        document.getElementById('verifyAction').value = action;
        document.getElementById('verifyTitle').textContent = (action === 'approved') ? "Approve Document" :
            "Disapprove Document";
        document.getElementById('verifyDocName').textContent = currentDocName;
        document.getElementById('verifyActionText').textContent = (action === 'approved') ? "approve" :
            "disapprove";

        const submitBtn = document.getElementById('verifySubmitBtn');
        submitBtn.textContent = (action === 'approved') ? "Confirm Approve" : "Confirm Disapprove";
        submitBtn.className = "btn btn-sm " + ((action === 'approved') ? "btn-dark" : "btn-danger");

        verifyConfirmModal.show();
    }

    document.querySelectorAll('.btnOpenVerify').forEach(b => {
        b.addEventListener('click', () => {
            currentDocId = parseInt(b.dataset.docid || '0', 10);
            currentDocName = b.dataset.docname || 'Document';
            openVerifyConfirm((b.dataset.action || 'approved').toLowerCase());
        });
    });

    document.getElementById('viewerApproveBtn')?.addEventListener('click', () => openVerifyConfirm('approved'));
    document.getElementById('viewerDisapproveBtn')?.addEventListener('click', () => openVerifyConfirm(
        'disapproved'));
});
</script>