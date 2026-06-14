</main>
</div>

<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ivote-logout-modal">
            <div class="modal-body">
                <div class="ivote-logout-modal-icon">
                    <i class="bi bi-box-arrow-right"></i>
                </div>

                <h5>Logout Admin Account?</h5>
                <p>
                    You will be signed out of the iVotePH admin dashboard.
                </p>

                <div class="ivote-logout-modal-actions">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <a href="/ivoteph/admin/auth/logout.php" class="btn btn-danger">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    (function () {
        var sidebar = document.getElementById('ivoteSidebar');
        var overlay = document.getElementById('ivoteSidebarOverlay');
        var openBtn = document.getElementById('ivoteSidebarOpen');
        var closeBtn = document.getElementById('ivoteSidebarClose');

        function openSidebar() {
            if (sidebar) {
                sidebar.classList.add('show');
            }

            if (overlay) {
                overlay.classList.add('show');
            }
        }

        function closeSidebar() {
            if (sidebar) {
                sidebar.classList.remove('show');
            }

            if (overlay) {
                overlay.classList.remove('show');
            }
        }

        if (openBtn) {
            openBtn.addEventListener('click', openSidebar);
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeSidebar);
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }
    })();
</script>

<script src="/ivoteph/admin/assets/js/admin.js?v=adminfix20260613"></script>
</body>

</html>