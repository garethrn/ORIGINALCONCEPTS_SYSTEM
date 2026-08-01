<?php
define('OC_SYSTEM', true);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

requireLogin();
requireRole('admin', 'manager', 'technician');

$pageTitle = 'Add Client';
$errors    = [];
$formData  = [
    'name'       => '',
    'company'    => '',
    'email'      => '',
    'phone'      => '',
    'address'    => '',
    'city'       => '',
    'province'   => '',
    'postal_code'=> '',
    'country'    => 'South Africa',
    'latitude'   => '',
    'longitude'  => '',
    'notes'      => '',
    'is_active'  => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $formData = [
            'name'        => sanitize($_POST['name']        ?? ''),
            'company'     => sanitize($_POST['company']     ?? ''),
            'email'       => strtolower(sanitize($_POST['email']       ?? '')),
            'phone'       => sanitize($_POST['phone']       ?? ''),
            'address'     => sanitize($_POST['address']     ?? ''),
            'city'        => sanitize($_POST['city']        ?? ''),
            'province'    => sanitize($_POST['province']    ?? ''),
            'postal_code' => sanitize($_POST['postal_code'] ?? ''),
            'country'     => sanitize($_POST['country']     ?? 'South Africa'),
            'latitude'    => sanitize($_POST['latitude']    ?? ''),
            'longitude'   => sanitize($_POST['longitude']   ?? ''),
            'notes'       => sanitize($_POST['notes']       ?? ''),
            'is_active'   => 1,
        ];

        // Validation
        if (empty($formData['name']))              $errors[] = 'Client name is required.';
        if ($formData['email'] && !validateEmail($formData['email'])) $errors[] = 'Invalid email address.';
        if ($formData['phone'] && !validatePhone($formData['phone'])) $errors[] = 'Invalid phone number.';
        if ($formData['email'] && dbRow('SELECT id FROM clients WHERE email = ?', [$formData['email']])) {
            $errors[] = 'A client with this email already exists.';
        }

        if (empty($errors)) {
            $lat = $formData['latitude']  ? (float)$formData['latitude']  : null;
            $lng = $formData['longitude'] ? (float)$formData['longitude'] : null;

            $clientId = dbInsert('clients', [
                'name'        => $formData['name'],
                'company'     => $formData['company']     ?: null,
                'email'       => $formData['email']       ?: null,
                'phone'       => $formData['phone']       ?: null,
                'address'     => $formData['address']     ?: null,
                'city'        => $formData['city']        ?: null,
                'province'    => $formData['province']    ?: null,
                'postal_code' => $formData['postal_code'] ?: null,
                'country'     => $formData['country']     ?: 'South Africa',
                'latitude'    => $lat,
                'longitude'   => $lng,
                'notes'       => $formData['notes']       ?: null,
                'is_active'   => 1,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);

            redirectWithMessage(BASE_URL . '/clients/view.php?id=' . $clientId, 'Client added successfully.');
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex align-items-center mb-3">
    <a href="<?= BASE_URL ?>/clients/index.php" class="btn btn-sm btn-outline-secondary me-3">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2"></i>Add New Client</h4>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0 ps-3"><?php foreach ($errors as $err) echo '<li>' . e($err) . '</li>'; ?></ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <form method="POST">
            <?= csrfField() ?>

            <!-- Basic Info -->
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person me-2"></i>Basic Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= e($formData['name']) ?>" required maxlength="150">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Company / Organisation</label>
                            <input type="text" name="company" class="form-control"
                                   value="<?= e($formData['company']) ?>" maxlength="150">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($formData['email']) ?>" maxlength="150">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Phone Number</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?= e($formData['phone']) ?>"
                                   placeholder="+27 000 000 0000" maxlength="30">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Internal Notes</label>
                            <textarea name="notes" class="form-control" rows="2"
                                      placeholder="Private notes about this client…"><?= e($formData['notes']) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-geo-alt me-2"></i>Location & Address</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Street Address</label>
                            <input type="text" name="address" class="form-control"
                                   value="<?= e($formData['address']) ?>" maxlength="255"
                                   id="streetAddress">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city" class="form-control"
                                   value="<?= e($formData['city']) ?>" maxlength="100">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Province</label>
                            <select name="province" class="form-select">
                                <option value="">— Select Province —</option>
                                <?php
                                $provinces = ['Gauteng','Western Cape','KwaZulu-Natal','Eastern Cape','Limpopo',
                                              'Mpumalanga','North West','Free State','Northern Cape'];
                                foreach ($provinces as $p):
                                ?>
                                <option value="<?= $p ?>" <?= $formData['province'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">Postal Code</label>
                            <input type="text" name="postal_code" class="form-control"
                                   value="<?= e($formData['postal_code']) ?>" maxlength="10">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Country</label>
                            <input type="text" name="country" class="form-control"
                                   value="<?= e($formData['country']) ?>" maxlength="80">
                        </div>
                    </div>

                    <!-- GPS Coordinates -->
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <hr class="my-2">
                            <label class="form-label fw-semibold small text-muted">
                                GPS Coordinates <small>(auto-fill via browser or enter manually)</small>
                            </label>
                        </div>
                        <div class="col-sm-4">
                            <input type="text" name="latitude" id="latitude" class="form-control form-control-sm"
                                   value="<?= e($formData['latitude']) ?>" placeholder="Latitude e.g. -26.2041">
                        </div>
                        <div class="col-sm-4">
                            <input type="text" name="longitude" id="longitude" class="form-control form-control-sm"
                                   value="<?= e($formData['longitude']) ?>" placeholder="Longitude e.g. 28.0473">
                        </div>
                        <div class="col-sm-4">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="getLocationBtn">
                                <i class="bi bi-crosshair me-1"></i> Use My Location
                            </button>
                        </div>
                        <div class="col-12" id="mapPreview" style="display:none">
                            <iframe id="mapIframe" width="100%" height="200" frameborder="0" style="border-radius:6px"></iframe>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle me-1"></i> Save Client
                </button>
                <a href="<?= BASE_URL ?>/clients/index.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Notes</div>
            <div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li class="mb-1">Email address is used for ticket notifications.</li>
                    <li class="mb-1">GPS coordinates enable map features for on-site support.</li>
                    <li class="mb-1">Internal notes are not visible to the client.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<JS
<script>
document.getElementById('getLocationBtn').addEventListener('click', function () {
    if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
    navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById('latitude').value  = pos.coords.latitude.toFixed(6);
        document.getElementById('longitude').value = pos.coords.longitude.toFixed(6);
        showMap(pos.coords.latitude, pos.coords.longitude);
    }, function () { alert('Unable to retrieve location.'); });
});

document.getElementById('latitude').addEventListener('change', updateMap);
document.getElementById('longitude').addEventListener('change', updateMap);

function updateMap() {
    var lat = parseFloat(document.getElementById('latitude').value);
    var lng = parseFloat(document.getElementById('longitude').value);
    if (!isNaN(lat) && !isNaN(lng)) showMap(lat, lng);
}

function showMap(lat, lng) {
    var preview = document.getElementById('mapPreview');
    var iframe  = document.getElementById('mapIframe');
    iframe.src  = 'https://maps.google.com/maps?q=' + lat + ',' + lng + '&z=15&output=embed';
    preview.style.display = 'block';
}
</script>
JS;

require_once dirname(__DIR__) . '/includes/footer.php';
?>
