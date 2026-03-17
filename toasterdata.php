<?php
$success = "";
$error = "";



/**
 * Flash via GET
 */
if(isset($_GET['msg'])){
    if($_GET['msg'] === 'updated') $success = "School name updated successfully!";
    if($_GET['msg'] === 'deleted') $success = "School deleted successfully!";
}



?>
<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">

    <?php if($success): ?>
    <div id="liveToastSuccess" class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <?php echo htmlspecialchars($success); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <?php endif; ?>

    <?php if($error): ?>
    <div id="liveToastError" class="toast align-items-center text-bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
    <?php endif; ?>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    const successToast = document.getElementById('liveToastSuccess');
    const errorToast = document.getElementById('liveToastError');

    if (successToast) {
        const toast = new bootstrap.Toast(successToast, {
            delay: 3500
        });
        toast.show();
    }

    if (errorToast) {
        const toast = new bootstrap.Toast(errorToast, {
            delay: 4500
        });
        toast.show();
    }

});
</script>