<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include necessary configurations and functions
require_once $_SERVER['DOCUMENT_ROOT'] . '/capstone-admin/config/function.php';

// Ensure user is authenticated
if (!isset($_SESSION['auth']) || $_SESSION['auth'] !== true || !in_array($_SESSION['role'], ['student', 'alumni'])) {
    redirect('../index.php', 'Please log in as a student or alumni to access this page.', 'warning');
    exit();
}

// PayMongo API keys
$paymongo_secret_key = 'sk_test_qCKzU9gWR64WpE2ftVFHPVgs';

// Helper function to determine the current semester
function getCurrentSemester() {
    $month = (int)date('m');
    $year = date('Y');
    if ($month >= 8 && $month <= 12) {
        return ['semester' => 'First Semester', 'year' => $year];
    } elseif ($month >= 1 && $month <= 5) {
        return ['semester' => 'Second Semester', 'year' => $year];
    } else {
        return ['semester' => 'Summer Term', 'year' => $year];
    }
}

// Initialize variables for form feedback
$success_message = '';
$error_message = '';
$show_success_modal = false;

// Check for cancellation from PayMongo
if (isset($_GET['error']) && $_GET['error'] === 'cancelled') {
    $_SESSION['message'] = 'Payment was cancelled. Please try again.';
    $_SESSION['message_type'] = 'warning';
}

// Handle form submission (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $user_id = $_SESSION['user_id'];
    $document_types = $_POST['document_types'] ?? [];
    $remarks = $_POST['remarks'] ?? '';
    $file_paths = [];

    // Validate required fields
    if (empty($document_types) || empty($remarks)) {
        $error_message = 'Please select at least one document and provide remarks.';
    } elseif (count($document_types) > 2) {
        $error_message = 'You can only request up to 2 documents at a time.';
    } else {
        // Fetch current semester details
        $current_semester = getCurrentSemester();
        $semester_name = $current_semester['semester'];
        $semester_year = $current_semester['year'];

        // Check semester restrictions and prepare documents
        $stmt = $conn->prepare("SELECT name, unit_price, form_needed, restrict_per_semester FROM documents WHERE name = ? AND is_active = 1");
        $total_amount = 0;
        $documents_to_request = [];
        $has_error = false;

        foreach ($document_types as $document_type) {
            $stmt->bind_param("s", $document_type);
            $stmt->execute();
            $document = $stmt->get_result()->fetch_assoc();

            if (!$document) {
                $error_message = "Document '$document_type' is not available.";
                $has_error = true;
                break;
            }

            $unit_price = (float)($document['unit_price'] ?? 0.00);
            $form_needed = $document['form_needed'] ?? 0;
            $restrict_per_semester = $document['restrict_per_semester'] ?? 0;

            // Check semester restrictions
            if ($restrict_per_semester) {
                $semester_start = $semester_name === 'First Semester' ? "$semester_year-08-01" : ($semester_name === 'Second Semester' ? "$semester_year-01-01" : "$semester_year-06-01");
                $semester_end = $semester_name === 'First Semester' ? "$semester_year-12-31" : ($semester_name === 'Second Semester' ? "$semester_year-05-31" : "$semester_year-07-31");

                $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE user_id = ? AND document_type = ? AND requested_date BETWEEN ? AND ? AND status != 'Rejected'");
                $stmt_check->bind_param("isss", $user_id, $document_type, $semester_start, $semester_end);
                $stmt_check->execute();
                $result = $stmt_check->get_result()->fetch_assoc();
                $request_count = $result['count'];
                $stmt_check->close();

                if ($request_count > 0) {
                    $error_message = "You have already requested a '$document_type' this semester ($semester_name $semester_year).";
                    $has_error = true;
                    break;
                }
            }

            // Handle file upload
            $file_path = null;
            $file_key = 'form_file_' . str_replace(' ', '_', $document_type);
            if ($form_needed && isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/forms/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_name = uniqid() . '-' . basename($_FILES[$file_key]['name']);
                $file_path = $upload_dir . $file_name;
                if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $file_path)) {
                    $error_message = "Failed to upload the form for '$document_type'.";
                    $has_error = true;
                    break;
                }
            } elseif ($form_needed) {
                $error_message = "Please upload the required form for '$document_type'.";
                $has_error = true;
                break;
            }

            $total_amount += (int)($unit_price * 100); // Convert to centavos
            $documents_to_request[] = [
                'document_type' => $document_type,
                'unit_price' => $unit_price,
                'file_path' => $file_path,
            ];
        }
        $stmt->close();

        // If no errors, proceed based on total amount
        if (!$has_error) {
            if ($total_amount == 0) {
                // Store data in session to use after confirmation
                $_SESSION['pending_request'] = [
                    'documents_to_request' => $documents_to_request,
                    'remarks' => $remarks,
                    'user_id' => $user_id
                ];
                // Show confirmation modal via JavaScript
                $show_confirmation_modal = true;
            } else {
                // Payment required, create PayMongo Checkout Session
                $_SESSION['remarks'] = $remarks; // Store remarks in session for PayMongo flow
                $curl = curl_init();
                $description = "Payment for document request: " . implode(', ', $document_types);
                $line_items = [];
                foreach ($documents_to_request as $doc) {
                    $line_items[] = [
                        'amount' => (int)($doc['unit_price'] * 100),
                        'currency' => 'PHP',
                        'name' => $doc['document_type'],
                        'quantity' => 1,
                        'description' => "Document request for {$doc['document_type']}",
                    ];
                }
                $fields = [
                    'data' => [
                        'attributes' => [
                            'amount' => $total_amount,
                            'currency' => 'PHP',
                            'description' => $description,
                            'line_items' => $line_items,
                            'payment_method_types' => ['gcash', 'card'],
                            'success_url' => 'https://9b67-120-29-78-198.ngrok-free.app/capstone-admin/users/request_success.php?request_ids=' . urlencode(json_encode($documents_to_request)),
                            'cancel_url' => 'https://9b67-120-29-78-198.ngrok-free.app/capstone-admin/users/request_document.php?error=cancelled',
                            'reference_number' => 'REQ_' . time(),
                            'send_email_receipt' => true,
                            'show_description' => true,
                            'show_line_items' => true,
                            'metadata' => ['user_id' => $user_id, 'documents' => $documents_to_request, 'remarks' => $remarks],
                        ]
                    ]
                ];

                curl_setopt_array($curl, [
                    CURLOPT_URL => "https://api.paymongo.com/v1/checkout_sessions",
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($fields),
                    CURLOPT_HTTPHEADER => [
                        "Accept: application/json",
                        "Content-Type: application/json",
                        "Authorization: Basic " . base64_encode($paymongo_secret_key . ":")
                    ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                curl_close($curl);

                if ($err) {
                    $error_message = "Failed to initiate payment: $err";
                    error_log("PayMongo error: $err");
                } else {
                    $response_data = json_decode($response, true);
                    error_log("PayMongo response: " . json_encode($response_data));
                    if (isset($response_data['data']['id']) && isset($response_data['data']['attributes']['checkout_url'])) {
                        // Store checkout_session_id in session
                        $_SESSION['checkout_session_id'] = $response_data['data']['id'];
                        // Redirect to PayMongo checkout
                        header('Location: ' . $response_data['data']['attributes']['checkout_url']);
                        exit;
                    } else {
                        $error_message = "Failed to create payment session: " . json_encode($response_data);
                        error_log("PayMongo response error: " . json_encode($response_data));
                    }
                }
            }
        }
    }

    // If there's an error, store the message in the session and redirect back
    if ($error_message) {
        $_SESSION['message'] = $error_message;
        $_SESSION['message_type'] = 'danger';
        header('Location: request_document.php');
        exit;
    }
}

// Handle confirmation for free documents
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_request'])) {
    $user_id = $_SESSION['user_id'];
    $document_types = $_POST['document_types'] ?? [];
    $remarks = $_POST['remarks'] ?? '';
    $file_paths = [];

    // Validate required fields
    if (empty($document_types) || empty($remarks)) {
        $error_message = 'Please select at least one document and provide remarks.';
    } else {
        // Rebuild documents_to_request since files may have been uploaded
        $stmt = $conn->prepare("SELECT name, unit_price, form_needed FROM documents WHERE name = ? AND is_active = 1");
        $documents_to_request = [];
        $has_error = false;

        foreach ($document_types as $document_type) {
            $stmt->bind_param("s", $document_type);
            $stmt->execute();
            $document = $stmt->get_result()->fetch_assoc();

            if (!$document) {
                $error_message = "Document '$document_type' is not available.";
                $has_error = true;
                break;
            }

            $unit_price = (float)($document['unit_price'] ?? 0.00);
            $form_needed = $document['form_needed'] ?? 0;

            // Handle file upload (re-upload since this is a new request)
            $file_path = null;
            $file_key = 'form_file_' . str_replace(' ', '_', $document_type);
            if ($form_needed && isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'Uploads/forms/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_name = uniqid() . '-' . basename($_FILES[$file_key]['name']);
                $file_path = $upload_dir . $file_name;
                if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $file_path)) {
                    $error_message = "Failed to upload the form for '$document_type'.";
                    $has_error = true;
                    break;
                }
            } elseif ($form_needed) {
                $error_message = "Please upload the required form for '$document_type'.";
                $has_error = true;
                break;
            }

            $documents_to_request[] = [
                'document_type' => $document_type,
                'unit_price' => $unit_price,
                'file_path' => $file_path,
            ];
        }
        $stmt->close();

        if (!$has_error) {
            // Insert requests into database
            $request_ids = [];
            $doc_names = array_column($documents_to_request, 'document_type');
            $stmt = $conn->prepare("INSERT INTO requests (user_id, document_type, quantity, unit_price, amount, payment_status, status, remarks, file_path, requested_date, created_at) VALUES (?, ?, 1, ?, ?, 'paid', 'Pending', ?, ?, NOW(), NOW())");
            foreach ($documents_to_request as $doc) {
                $amount = (int)($doc['unit_price'] * 100);
                $unit_price = (float)$doc['unit_price'];
                $stmt->bind_param("isdiss", $user_id, $doc['document_type'], $unit_price, $amount, $remarks, $doc['file_path']);
                if ($stmt->execute()) {
                    $request_ids[] = $conn->insert_id;
                } else {
                    error_log("Failed to insert request for {$doc['document_type']}: " . $stmt->error);
                    $error_message = "Failed to save request for {$doc['document_type']}.";
                    break;
                }
            }
            $stmt->close();

            if (!$error_message) {
                // Create notifications for student
                $message = "Request for " . implode(' and ', $doc_names) . " was successful.";
                $link = "dashboard.php";
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $stmt->bind_param("iss", $user_id, $message, $link);
                if ($stmt->execute()) {
                    error_log("Notification created for user $user_id: $message");
                } else {
                    error_log("Failed to create notification for user $user_id: " . $stmt->error);
                }
                $stmt->close();

                // Notify admins
                $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $student_name = $user['firstname'] . ' ' . $user['lastname'];
                $admin_message = "Student $student_name submitted a request for " . implode(' and ', $doc_names) . ".";
                $admin_link = "/capstone-admin/admin/request.php?id=" . $request_ids[0];

                foreach ($admins as $admin) {
                    $stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $admin['id'], $admin_message, $admin_link);
                    if ($stmt->execute()) {
                        error_log("Admin notification created for admin {$admin['id']}: $admin_message");
                    } else {
                        error_log("Failed to create admin notification for admin {$admin['id']}: " . $stmt->error);
                    }
                    $stmt->close();
                }

                // Show success modal
                $show_success_modal = true;
                $success_message = "Request for " . implode(' and ', $doc_names) . " was successful.";

                // Clear pending request
                unset($_SESSION['pending_request']);
            }
        }
    }

    if ($error_message) {
        $_SESSION['message'] = $error_message;
        $_SESSION['message_type'] = 'danger';
        header('Location: request_document.php');
        exit;
    }
}

// Fetch available documents for the form
$stmt = $conn->prepare("SELECT * FROM documents WHERE is_active = 1");
$stmt->execute();
$documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Now include the header (after all redirects)
$page_title = "Request Document";
include('includes/header.php');
?>

<link rel="stylesheet" href="../assets/css/user_dashboard.css">
<link rel="stylesheet" href="../assets/css/request.css">

<main>
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x" style="z-index: 1050; margin-top: 20px;" role="alert">
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>
    <div class="page-header">
        <h1>Request a Document</h1>
        <small>Hello, <?= htmlspecialchars($user['firstname'] ?? 'User'); ?>! Here you can request up to 2 documents.</small>
    </div>
    <div class="page-content">
        <h5 class="page-title mb-4">Document Request Form</h5>
        <form method="POST" enctype="multipart/form-data" id="requestForm">
            <div class="mb-3">
                <label for="document_types" class="form-label">Select Documents (Max 2)</label>
                <select name="document_types[]" id="document_types" class="form-select" multiple required>
                    <?php foreach ($documents as $doc): ?>
                        <option value="<?= htmlspecialchars($doc['name']); ?>" 
                                data-form-needed="<?= $doc['form_needed']; ?>" 
                                data-price="<?= $doc['unit_price']; ?>" 
                                data-restrict="<?= $doc['restrict_per_semester']; ?>">
                            <?= htmlspecialchars($doc['name']); ?> (₱<?= htmlspecialchars($doc['unit_price']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple documents.</small>
            </div>

            <?php foreach ($documents as $doc): ?>
                <div class="mb-3 form-upload" id="form_upload_<?= str_replace(' ', '_', $doc['name']); ?>" style="display: none;">
                    <label for="form_file_<?= str_replace(' ', '_', $doc['name']); ?>" class="form-label">Upload Form for <?= htmlspecialchars($doc['name']); ?></label>
                    <input type="file" name="form_file_<?= str_replace(' ', '_', $doc['name']); ?>" 
                           id="form_file_<?= str_replace(' ', '_', $doc['name']); ?>" 
                           class="form-control" accept=".pdf,.doc,.docx">
                    <small class="form-text text-muted">Accepted formats: PDF, DOC, DOCX (Max 5MB)</small>
                </div>
            <?php endforeach; ?>

            <div class="mb-3">
                <label for="total_price" class="form-label">Total Price</label>
                <input type="text" id="total_price" class="form-control" value="₱0.00" readonly>
            </div>

            <div class="mb-3">
                <label for="remarks" class="form-label">Purpose/Remarks</label>
                <textarea name="remarks" id="remarks" class="form-control" rows="4" required placeholder="Enter the purpose of your request..."><?= isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
            </div>

            <button type="button" id="proceedButton" class="btn btn-primary">Proceed to Payment</button>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>
</main>

<!-- Confirmation Modal for Free Documents -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirm Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to request <span id="confirmDocNames"></span>?
                <small class="form-text text-muted d-block mt-2">Note: If requesting both free and paid documents, the free document(s) will be processed after payment.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="confirmForm" enctype="multipart/form-data">
                    <input type="hidden" name="confirm_request" value="1">
                    <!-- Hidden inputs for document_types and remarks will be added dynamically -->
                    <button type="submit" class="btn btn-primary">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="successModalLabel">Request Successful</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <div class="modal-footer">
                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const documentSelect = document.getElementById('document_types');
        const totalPriceInput = document.getElementById('total_price');
        const requestForm = document.getElementById('requestForm');
        const proceedButton = document.getElementById('proceedButton');
        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const confirmDocNames = document.getElementById('confirmDocNames');
        const confirmForm = document.getElementById('confirmForm');
        const remarksInput = document.getElementById('remarks');

        function updateForm() {
            document.querySelectorAll('.form-upload').forEach(field => {
                field.style.display = 'none';
                const fileInput = field.querySelector('input[type="file"]');
                fileInput.required = false;
            });

            let totalPrice = 0;
            const selectedOptions = Array.from(documentSelect.selectedOptions);
            selectedOptions.forEach(option => {
                const formNeeded = option.getAttribute('data-form-needed') === '1';
                const price = parseFloat(option.getAttribute('data-price'));
                totalPrice += price;
                if (formNeeded) {
                    const docName = option.value.replace(/\s+/g, '_');
                    const formUpload = document.getElementById(`form_upload_${docName}`);
                    formUpload.style.display = 'block';
                    const fileInput = document.getElementById(`form_file_${docName}`);
                    fileInput.required = true;
                }
            });

            totalPriceInput.value = `₱${totalPrice.toFixed(2)}`;
            return totalPrice;
        }

        updateForm();
        documentSelect.addEventListener('change', function() {
            const selectedOptions = Array.from(documentSelect.selectedOptions);
            if (selectedOptions.length > 2) {
                alert('You can only select up to 2 documents.');
                this.value = selectedOptions.slice(0, 2).map(opt => opt.value);
            }
            updateForm();
        });

        proceedButton.addEventListener('click', function(e) {
            e.preventDefault();
            const totalPrice = updateForm();
            const selectedOptions = Array.from(documentSelect.selectedOptions);
            const docNames = selectedOptions.map(opt => opt.value);

            // Validate form
            if (!requestForm.checkValidity()) {
                requestForm.reportValidity();
                return;
            }

            // Clear any existing hidden inputs in confirmForm
            confirmForm.querySelectorAll('input[name="document_types[]"], input[name="remarks"]').forEach(input => input.remove());

            // Add selected documents as hidden inputs to confirmForm
            docNames.forEach(docName => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'document_types[]';
                hiddenInput.value = docName;
                confirmForm.appendChild(hiddenInput);
            });

            // Add remarks as a hidden input to confirmForm
            const remarksHiddenInput = document.createElement('input');
            remarksHiddenInput.type = 'hidden';
            remarksHiddenInput.name = 'remarks';
            remarksHiddenInput.value = remarksInput.value;
            confirmForm.appendChild(remarksHiddenInput);

            // Move file inputs to confirmForm to preserve uploads
            selectedOptions.forEach(option => {
                const formNeeded = option.getAttribute('data-form-needed') === '1';
                if (formNeeded) {
                    const docName = option.value.replace(/\s+/g, '_');
                    const fileInput = document.getElementById(`form_file_${docName}`);
                    if (fileInput && fileInput.files.length > 0) {
                        confirmForm.appendChild(fileInput);
                    }
                }
            });

            if (totalPrice === 0) {
                // Show confirmation modal for free documents
                confirmDocNames.textContent = docNames.join(' and ');
                confirmModal.show();
            } else {
                // Submit form for PayMongo payment
                requestForm.submit();
            }
        });

        // Show success modal if applicable
        <?php if ($show_success_modal): ?>
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        <?php endif; ?>
    });
</script>

<?php include('includes/footer.php'); ?>