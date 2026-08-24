</div><!-- /container-fluid -->

<!-- Footer -->
<footer class="oc-footer mt-auto">
    <div class="container-fluid px-4">
        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <span class="text-muted small">
                    &copy; <?= date('Y') ?> <?= COMPANY_NAME ?> &mdash; All rights reserved.
                </span>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <span class="text-muted small">
                    IT Support · Web Development · WhatsApp Development · Software Development
                </span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<?= $extraScripts ?? '' ?>
</body>
</html>
