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

// Fetch the current semester from the settings table
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'current_semester'");
$stmt->execute();
$result = $stmt->get_result();
$current_semester = $result->num_rows > 0 ? $result->fetch_assoc()['setting_value'] : '';
$stmt->close();

if (empty($current_semester)) {
    $_SESSION['message'] = 'Current semester not set. Please contact an administrator.';
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard.php');
    exit;
}

// Initialize variables for form feedback
$success_message = '';
$error_message = '';
$show_success_modal = false;

// Maximum number of documents allowed per request
$max_documents = 2; // Change to 3 if needed

// Check for cancellation from PayMongo
if (isset($_GET['error']) && $_GET['error'] === 'cancelled') {
    $_SESSION['message'] = 'Payment was cancelled. Please try again.';
    $_SESSION['message_type'] = 'warning';
}

// Handle form submission (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $user_id = $_SESSION['user_id'];
    $document_types = $_POST['document_types'] ?? [];
    $remarks = trim($_POST['remarks'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? 'none'); // Default to 'none' for free documents

    // Validate required fields
    if (empty($document_types) || empty($remarks)) {
        $error_message = 'Please select at least one document and provide remarks.';
    } elseif (count($document_types) > $max_documents) {
        $error_message = "You can only request up to $max_documents documents at a time.";
    } else {
        // Fetch user details for course_id, section_id, year_id
        $stmt = $conn->prepare("SELECT course_id, section_id, year_id FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_details = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Check semester restrictions, pending/processing requests, and prepare documents
        $stmt = $conn->prepare("SELECT name, unit_price, form_needed, restrict_per_semester FROM documents WHERE name = ? AND is_active = 1");
        $total_amount = 0;
        $documents_to_request = [];
        $has_error = false;
        $requires_payment = false;

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

            if ($unit_price > 0) {
                $requires_payment = true;
                $total_amount += (int)($unit_price * 100); // Convert to centavos for PayMongo
            }

            // Check for pending or processing requests
            $stmt_check_pending = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE user_id = ? AND document_type = ? AND status IN ('Pending', 'In Process')");
            $stmt_check_pending->bind_param("is", $user_id, $document_type);
            $stmt_check_pending->execute();
            $result_pending = $stmt_check_pending->get_result()->fetch_assoc();
            $pending_count = $result_pending['count'];
            $stmt_check_pending->close();

            if ($pending_count > 0) {
                $error_message = "You already have a pending or in-process request for '$document_type'. Please wait until it is completed or rejected.";
                $has_error = true;
                break;
            }

            // Check semester restrictions
            if ($restrict_per_semester) {
                $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM requests WHERE user_id = ? AND document_type = ? AND semester = ? AND status != 'Rejected'");
                $stmt_check->bind_param("iss", $user_id, $document_type, $current_semester);
                $stmt_check->execute();
                $result = $stmt_check->get_result()->fetch_assoc();
                $request_count = $result['count'];
                $stmt_check->close();

                if ($request_count > 0) {
                    $error_message = "You have already requested a '$document_type' this semester ($current_semester).";
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

            $documents_to_request[] = [
                'document_type' => $document_type,
                'unit_price' => $unit_price,
                'file_path' => $file_path,
                'course_id' => $user_details['course_id'] ?? null,
                'section_id' => $user_details['section_id'] ?? null,
                'year_id' => $user_details['year_id'] ?? null,
            ];
        }
        $stmt->close();

        // Validate payment method if required
        if ($requires_payment && !in_array($payment_method, ['cash', 'online'])) {
            $error_message = 'Please select a payment method for documents requiring payment.';
            $has_error = true;
        } elseif (!$requires_payment) {
            $payment_method = 'none'; // Force 'none' if no payment required
        }

        // If no errors, proceed based on payment method
        if (!$has_error) {
            if ($payment_method === 'none' || $payment_method === 'cash') {
                // Free documents or cash payment: Insert requests directly
                $request_ids = [];
                $doc_names = array_column($documents_to_request, 'document_type');
                $stmt = $conn->prepare("INSERT INTO requests (user_id, document_type, quantity, unit_price, amount, payment_status, payment_method, status, remarks, file_path, course_id, section_id, year_id, semester, requested_date, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

                // Prepare statement for payments table
                $stmt_payment = $conn->prepare("INSERT INTO payments (request_id, payment_method, amount, payment_status, description, payment_date, created_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");

                foreach ($documents_to_request as $doc) {
                    $amount = (int)($doc['unit_price'] * 100); // In centavos
                    $unit_price = (float)$doc['unit_price'];
                    $quantity = 1;
                    $request_payment_status = ($unit_price > 0 && $payment_method === 'cash') ? 'pending' : 'paid';
                    $request_payment_method = ($unit_price > 0 && $payment_method === 'cash') ? 'cash' : 'N/A';
                    $course_id = $doc['course_id'] ?? null;
                    $section_id = $doc['section_id'] ?? null;
                    $year_id = $doc['year_id'] ?? null;
                    $status = 'Pending';
                    $stmt->bind_param("isiddssssiisss", $user_id, $doc['document_type'], $quantity, $unit_price, $amount, $request_payment_status, $request_payment_method, $status, $remarks, $doc['file_path'], $course_id, $section_id, $year_id, $current_semester);
                    if ($stmt->execute()) {
                        $request_id = $conn->insert_id;
                        $request_ids[] = $request_id;

                        // Insert into payments table
                        $payment_method_db = ($unit_price > 0 && $payment_method === 'cash') ? 'Cash' : 'N/A';
                        $payment_status = ($unit_price > 0 && $payment_method === 'cash') ? 'PENDING' : 'PAID';
                        $payment_amount = (float)$unit_price; // In pesos
                        $description = "Payment for document request: {$doc['document_type']}";
                        $stmt_payment->bind_param("isdss", $request_id, $payment_method_db, $payment_amount, $payment_status, $description);
                        if (!$stmt_payment->execute()) {
                            error_log("Failed to insert payment for request ID $request_id: " . $stmt_payment->error);
                            $error_message = "Failed to save payment record for {$doc['document_type']}.";
                            $has_error = true;
                            break;
                        }
                    } else {
                        error_log("Failed to insert request for {$doc['document_type']}: " . $stmt->error);
                        $error_message = "Failed to save request for {$doc['document_type']}.";
                        $has_error = true;
                        break;
                    }
                }
                $stmt->close();
                $stmt_payment->close();

                if (!$has_error) {
                    // Create notifications for student
                    $doc_names_list = implode(' and ', $doc_names);
                    $message = "Request for $doc_names_list was successful. Request ID(s): #" . implode(', #', $request_ids) . ".";
                    if ($payment_method === 'cash' && $requires_payment) {
                        $message .= " Please proceed to the cashier for payment after registrar approval.";
                    } else {
                        $message .= " Awaiting registrar approval.";
                    }
                    $link = "dashboard.php";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                    $stmt->bind_param("iss", $user_id, $message, $link);
                    if ($stmt->execute()) {
                        error_log("Notification created for user $user_id: $message");
                    } else {
                        error_log("Failed to create notification for user $user_id: " . $stmt->error);
                    }
                    $stmt->close();

                    // Notify admins and registrars
                    $stmt = $conn->prepare("SELECT id FROM users WHERE role IN ('admin', 'registrar')");
                    $stmt->execute();
                    $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    $stmt = $conn->prepare("SELECT firstname, lastname FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    $student_name = $user['firstname'] . ' ' . $user['lastname'];
                    $admin_message = "Student $student_name submitted a request for $doc_names_list via " . ($requires_payment ? ($payment_method === 'cash' ? 'Cash' : 'Online') : 'N/A') . " payment. Request ID(s): #" . implode(', #', $request_ids) . ".";
                    $admin_link = "/admin/request.php?id=" . $request_ids[0];

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
                    $success_message = "Request for $doc_names_list was successful. Request ID(s): #" . implode(', #', $request_ids) . ".";
                    if ($payment_method === 'cash' && $requires_payment) {
                        $success_message .= " Please proceed to the cashier for payment after registrar approval.";
                    } else {
                        $success_message .= " Awaiting registrar approval.";
                    }
                }
            } else {
                // Online payment: Create PayMongo Checkout Session for paid documents only
                $_SESSION['remarks'] = $remarks;
                $_SESSION['payment_method'] = 'online';
                $curl = curl_init();
                $description = "Payment for document request: " . implode(', ', array_column(array_filter($documents_to_request, function($doc) { return $doc['unit_price'] > 0; }), 'document_type'));
                $line_items = [];
                foreach ($documents_to_request as $doc) {
                    if ($doc['unit_price'] > 0) {
                        $line_items[] = [
                            'amount' => (int)($doc['unit_price'] * 100),
                            'currency' => 'PHP',
                            'name' => $doc['document_type'],
                            'quantity' => 1,
                            'description' => "Document request for {$doc['document_type']}",
                        ];
                    }
                }
                $fields = [
                    'data' => [
                        'attributes' => [
                            'amount' => $total_amount,
                            'currency' => 'PHP',
                            'description' => $description,
                            'line_items' => $line_items,
                            'payment_method_types' => ['gcash', 'card'],
                            'success_url' => 'https://61a4-120-29-76-67.ngrok-free.app/capstone-admin/users/request_success.php?request_ids=' . urlencode(json_encode($documents_to_request)),
                            'cancel_url' => 'https://61a4-120-29-76-67.ngrok-free.app/capstone-admin/users/request_document.php?error=cancelled',
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

// Fetch available documents for the form
$stmt = $conn->prepare("SELECT name, unit_price, form_needed, restrict_per_semester, requirements FROM documents WHERE is_active = 1");
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
        <small>Hello, <?= htmlspecialchars($user['firstname'] ?? 'User'); ?>! Here you can request up to <?php echo $max_documents; ?> documents.</small>
    </div>
    <div class="page-content">
        <h5 class="page-title mb-4">Document Request Form</h5>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="document_types" class="form-label">Select Documents (Max <?php echo $max_documents; ?>)</label>
                <select name="document_types[]" id="document_types" class="form-select" multiple required>
                    <?php foreach ($documents as $doc): ?>
                        <option value="<?= htmlspecialchars($doc['name']); ?>" 
                                data-form-needed="<?= $doc['form_needed']; ?>" 
                                data-price="<?= $doc['unit_price']; ?>" 
                                data-restrict="<?= $doc['restrict_per_semester']; ?>"
                                data-requirements="<?= htmlspecialchars($doc['requirements'] ?? ''); ?>">
                            <?= htmlspecialchars($doc['name']); ?> (₱<?= htmlspecialchars($doc['unit_price']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Hold Ctrl (Windows) or Command (Mac) to select multiple documents.</small>
            </div>

            <div class="mb-3" id="requirements_display" style="display: none;">
                <label class="form-label">Requirements for Selected Documents</label>
                <ul id="requirements_list" class="list-group"></ul>
            </div>

            <?php foreach ($documents as $doc): ?>
                <div class="mb-3 form-upload" id="form_upload_<?= str_replace(' ', '_', $doc['name']); ?>" style="display: none;">
                    <label for="form_file_<?= str_replace(' ', '_', $doc['name']); ?>" class="form-label">Upload Attachment for <?= htmlspecialchars($doc['name']); ?></label>
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

            <div class="mb-3" id="payment_method_container" style="display: none;">
                <label class="form-label">Payment Method</label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="payment_method" id="payment_cash" value="cash">
                        <label class="form-check-label" for="payment_cash">Cash (Pay at Cashier)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="payment_method" id="payment_online" value="online">
                        <label class="form-check-label" for="payment_online">Online (GCash/Card)</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label for="remarks" class="form-label">Purpose/Remarks</label>
                <textarea name="remarks" id="remarks" class="form-control" rows="4" required placeholder="Enter the purpose of your request..."><?= isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
            </div>

            <button type="submit" name="submit_request" class="btn btn-primary" id="submit_button">Submit Request</button>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>
    </div>
</main>

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
    const requirementsDisplay = document.getElementById('requirements_display');
    const requirementsList = document.getElementById('requirements_list');
    const paymentMethodContainer = document.getElementById('payment_method_container');
    const paymentCash = document.getElementById('payment_cash');
    const paymentOnline = document.getElementById('payment_online');
    const submitButton = document.getElementById('submit_button');
    const maxDocuments = <?php echo $max_documents; ?>;

    function updateForm() {
        // Reset form uploads and requirements
        document.querySelectorAll('.form-upload').forEach(field => {
            field.style.display = 'none';
            const fileInput = field.querySelector('input[type="file"]');
            fileInput.required = false;
        });
        requirementsList.innerHTML = '';
        requirementsDisplay.style.display = 'none';

        let totalPrice = 0;
        let requiresPayment = false;
        const selectedOptions = Array.from(documentSelect.selectedOptions);

        if (selectedOptions.length > 0) {
            selectedOptions.forEach(option => {
                const formNeeded = option.getAttribute('data-form-needed') === '1';
                const price = parseFloat(option.getAttribute('data-price'));
                const requirements = option.getAttribute('data-requirements');
                totalPrice += price;
                if (price > 0) {
                    requiresPayment = true;
                }

                // Show form upload if needed
                if (formNeeded) {
                    const docName = option.value.replace(/\s+/g, '_');
                    const formUpload = document.getElementById(`form_upload_${docName}`);
                    formUpload.style.display = 'block';
                    const fileInput = document.getElementById(`form_file_${docName}`);
                    fileInput.required = true;
                }

                // Show requirements if they exist
                if (requirements) {
                    const li = document.createElement('li');
                    li.className = 'list-group-item';
                    li.innerHTML = `<strong>${option.value}:</strong> ${requirements}`;
                    requirementsList.appendChild(li);
                    requirementsDisplay.style.display = 'block';
                }
            });
        }

        totalPriceInput.value = `₱${totalPrice.toFixed(2)}`;

        // Toggle payment method visibility and requirements
        paymentMethodContainer.style.display = requiresPayment ? 'block' : 'none';
        if (requiresPayment) {
            paymentCash.required = true;
            paymentOnline.required = true;
        } else {
            paymentCash.required = false;
            paymentOnline.required = false;
            paymentCash.checked = false;
            paymentOnline.checked = false;
        }

        // Update submit button text
        if (!requiresPayment) {
            submitButton.textContent = 'Submit Request';
        } else if (paymentCash.checked) {
            submitButton.textContent = 'Submit Request';
        } else if (paymentOnline.checked) {
            submitButton.textContent = 'Proceed to Payment';
        } else {
            submitButton.textContent = 'Submit Request';
        }
    }

    updateForm();
    documentSelect.addEventListener('change', function() {
        const selectedOptions = Array.from(documentSelect.selectedOptions);
        if (selectedOptions.length > maxDocuments) {
            alert(`You can only select up to ${maxDocuments} documents.`);
            this.value = selectedOptions.slice(0, maxDocuments).map(opt => opt.value);
        }
        updateForm();
    });

    paymentCash.addEventListener('change', updateForm);
    paymentOnline.addEventListener('change', updateForm);

    // Show success modal if applicable
    <?php if ($show_success_modal): ?>
        var successModal = new bootstrap.Modal(document.getElementById('successModal'));
        successModal.show();
    <?php endif; ?>
});
</script>

<?php include('includes/footer.php'); ?>