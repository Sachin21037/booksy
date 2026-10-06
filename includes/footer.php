</main>

<!-- Floating Back to Top Button -->
<button type="button" class="btn-back-to-top" id="btnBackToTop" title="Back to top" aria-label="Back to top">
    <i class="bi bi-chevron-up"></i>
</button>

<!-- Book Condition Grading Guide Modal -->
<div class="modal fade" id="conditionGuideModal" tabindex="-1" aria-labelledby="conditionGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-navy text-white py-3 px-4" style="border-bottom: 2px solid var(--teal-primary);">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center" id="conditionGuideModalLabel">
                    <i class="bi bi-patch-question-fill text-gold me-2"></i> Book Condition Grading Guide
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <p class="text-muted small mb-4">Booksy ensures full transparency in all marketplace listings. Here is how sellers grade condition:</p>
                <div class="d-flex flex-column gap-3">
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                        <span class="badge badge-condition badge-brand-new fs-6 px-3 py-2 flex-shrink-0">Brand New</span>
                        <div>
                            <div class="fw-bold text-navy">Unopened / Publisher Sealed</div>
                            <div class="small text-muted">Direct from publisher or bookstore. Zero signs of wear, uncreased spine, pristine pages.</div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                        <span class="badge badge-condition badge-like-new fs-6 px-3 py-2 flex-shrink-0">Like New</span>
                        <div>
                            <div class="fw-bold text-navy">Virtually Flawless</div>
                            <div class="small text-muted">Read once or stored carefully on a bookshelf. No markings, folded pages, or spine creases.</div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                        <span class="badge badge-condition badge-good fs-6 px-3 py-2 flex-shrink-0">Good</span>
                        <div>
                            <div class="fw-bold text-navy">Clean & Intact</div>
                            <div class="small text-muted">Shows gentle shelf wear or light reading. Solid binding, all pages clean and fully legible.</div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                        <span class="badge badge-condition badge-fair fs-6 px-3 py-2 flex-shrink-0">Fair</span>
                        <div>
                            <div class="fw-bold text-navy">Readable with Highlights / Wear</div>
                            <div class="small text-muted">May contain textbook pencil/pen notes, minor corner creasing, or cover wear. 100% complete text.</div>
                        </div>
                    </div>
                    <div class="p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row gap-3 align-items-sm-center">
                        <span class="badge badge-condition badge-poor fs-6 px-3 py-2 flex-shrink-0">Poor</span>
                        <div>
                            <div class="fw-bold text-navy">Heavy Wear / Reading Copy</div>
                            <div class="small text-muted">Significant wear, possible loose cover or aging, but all essential text pages remain readable.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 px-4 border-0">
                <button type="button" class="btn btn-booksy-primary btn-sm px-4 fw-bold" data-bs-dismiss="modal">Got It</button>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer-booksy mt-5 pt-5">
    <div class="container pb-4">
        <div class="row g-4">
            <!-- Col 1: About Booksy & Logo -->
            <div class="col-lg-4 col-md-6">
                <div class="mb-3">
                    <img src="images/booksy-logo.svg" alt="Booksy - Finding New Homes For Books" style="height: 75px; width: auto; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.3));">
                </div>
                <p class="small text-secondary mb-3" style="line-height: 1.6;">
                    Sri Lanka’s premier C2C book reselling marketplace. Connecting students, parents, collectors, and bibliophiles to find new homes for pre-loved and brand-new books at affordable prices.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-dark border border-secondary text-secondary py-2 px-3"><i class="bi bi-shield-check text-teal me-1"></i> Verified Sellers</span>
                    <span class="badge bg-dark border border-secondary text-secondary py-2 px-3"><i class="bi bi-truck text-warning me-1"></i> Islandwide Delivery</span>
                </div>
            </div>

            <!-- Col 2: Categories -->
            <div class="col-lg-3 col-md-6">
                <h5>Popular Categories</h5>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="index.php?category=novels-fiction"><i class="bi bi-chevron-right me-1 text-teal"></i> Novels & Fiction</a></li>
                    <li><a href="index.php?category=educational-academic"><i class="bi bi-chevron-right me-1 text-teal"></i> Educational & Academic</a></li>
                    <li><a href="index.php?category=self-help"><i class="bi bi-chevron-right me-1 text-teal"></i> Self-Help & Growth</a></li>
                    <li><a href="index.php?category=mystery-thriller-scifi"><i class="bi bi-chevron-right me-1 text-teal"></i> Mystery & Sci-Fi</a></li>
                    <li><a href="index.php?category=childrens-books"><i class="bi bi-chevron-right me-1 text-teal"></i> Children's Books</a></li>
                </ul>
            </div>

            <!-- Col 3: Quick Links & Guides -->
            <div class="col-lg-2 col-md-6">
                <h5>Help & Guides</h5>
                <ul class="list-unstyled small d-flex flex-column gap-2 mb-0">
                    <li><a href="index.php">Browse Books</a></li>
                    <li><a href="sell.php">Sell Your Books</a></li>
                    <li><a href="cart.php">Shopping Cart</a></li>
                    <li><a href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#conditionGuideModal"><i class="bi bi-patch-question text-gold me-1"></i> Condition Guide</a></li>
                    <li><a href="login.php">Sign In</a></li>
                    <li><a href="register.php">Create Account</a></li>
                </ul>
            </div>

            <!-- Col 4: Safe Trading & Contact -->
            <div class="col-lg-3 col-md-6">
                <h5>Trust & Support</h5>
                <div class="card bg-dark border-secondary p-3 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-whatsapp text-success fs-3"></i>
                        <div>
                            <div class="small text-secondary">Student & Reader Helpline</div>
                            <div class="fw-bold text-white">+94 77 123 4567</div>
                        </div>
                    </div>
                </div>
                <p class="small text-secondary mb-0">
                    <i class="bi bi-geo-alt me-1 text-danger"></i> Colombo, Sri Lanka • Cash on Delivery & Direct Transfers Supported.
                </p>
            </div>
        </div>
    </div>

    <!-- Bottom Copyright -->
    <div class="footer-bottom">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
            <div class="text-secondary small">
                &copy; <?= date('Y') ?> <strong>Booksy</strong> — Finding New Homes For Books. All rights reserved.
            </div>
            <div class="d-flex gap-3 small text-secondary">
                <span><i class="bi bi-cash-coin me-1 text-teal"></i> COD Available</span>
                <span><i class="bi bi-shield-lock me-1 text-info"></i> Secure Marketplace</span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle & Custom JS Helper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
