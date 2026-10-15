<?php
// emi_statement.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/auth.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("Invalid EMI ID");

// Fetch EMI Details
$stmt = $pdo->prepare("SELECT * FROM emi WHERE id = ?");
$stmt->execute([$id]);
$emi = $stmt->fetch();

if (!$emi) die("EMI Record not found.");

// Fetch Schedule
$stmt = $pdo->prepare("SELECT * FROM emi_schedule WHERE emi_id = ? ORDER BY installment_no ASC");
$stmt->execute([$id]);
$schedule = $stmt->fetchAll();

// Fetch settings for header
$settings = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Statement - <?= htmlspecialchars($emi['customer_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { font-size: 13px; color: #333; background-color: #f8f9fa; }
        .statement-container { max-width: 900px; margin: 30px auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .header-title { color: #8B5A2B; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .info-label { color: #777; font-size: 11px; text-transform: uppercase; font-weight: 700; }
        .info-value { font-weight: 600; color: #222; }
        .table thead th { background-color: #8B5A2B; color: white; border: none; font-size: 12px; }
        .badge-paid { background-color: #d1e7dd; color: #0f5132; }
        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-overdue { background-color: #f8d7da; color: #842029; }
        .summary-card { background-color: #fcf8f3; border-left: 4px solid #8B5A2B; padding: 15px; border-radius: 8px; }
        
        @media print {
            body { background: white; }
            .statement-container { box-shadow: none; margin: 0; max-width: 100%; width: 100%; border: none; padding: 10px; }
            .no-print { display: none !important; }
            .statement-container { border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="container statement-container">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-7">
            <h2 class="header-title mb-1"><?= htmlspecialchars($settings['shop_name'] ?? 'Furniture ERP') ?></h2>
            <p class="text-muted small mb-0"><?= nl2br(htmlspecialchars($settings['shop_address'] ?? '')) ?></p>
            <p class="text-muted small mb-0">Phone: <?= htmlspecialchars($settings['shop_contact'] ?? '') ?></p>
        </div>
        <div class="col-5 text-end">
            <h3 class="text-muted">LOAN STATEMENT</h3>
            <p class="mb-0"><strong>Account No:</strong> L-<?= str_pad($emi['id'], 5, '0', STR_PAD_LEFT) ?></p>
            <p class="mb-0"><strong>Bill No:</strong> <?= htmlspecialchars($emi['manual_bill_no'] ?? 'N/A') ?></p>
            <p class="mb-0"><strong>Date:</strong> <?= date('d M Y') ?></p>
        </div>
    </div>

    <hr class="border-secondary opacity-25">

    <!-- Customer & Loan Details -->
    <div class="row mb-4 mt-4">
        <div class="col-md-6">
            <h6 class="text-muted text-uppercase fw-bold mb-3 small"><i class="fas fa-user me-2"></i>Customer Information</h6>
            <div class="mb-2">
                <div class="info-label">Customer Name</div>
                <div class="info-value"><?= htmlspecialchars($emi['customer_name']) ?></div>
            </div>
            <div class="mb-2">
                <div class="info-label">Contact Number</div>
                <div class="info-value"><?= htmlspecialchars($emi['mobile'] ?? 'N/A') ?></div>
            </div>
            <div class="mb-2">
                <div class="info-label">Address</div>
                <div class="info-value"><?= nl2br(htmlspecialchars($emi['address'] ?? 'N/A')) ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <h6 class="text-muted text-uppercase fw-bold mb-3 small"><i class="fas fa-info-circle me-2"></i>Loan Information</h6>
            <div class="row">
                <div class="col-6 mb-2">
                    <div class="info-label">Product Name</div>
                    <div class="info-value"><?= htmlspecialchars($emi['product_name']) ?></div>
                </div>
                <div class="col-6 mb-2">
                    <div class="info-label">Loan Date</div>
                    <div class="info-value"><?= date('d M Y', strtotime($emi['emi_date'])) ?></div>
                </div>
                <div class="col-6 mb-2">
                    <div class="info-label">Tenure</div>
                    <div class="info-value"><?= $emi['tenure'] ?> Installments</div>
                </div>
                <div class="col-6 mb-2">
                    <div class="info-label">EMI Amount</div>
                    <div class="info-value">₹<?= number_format($emi['emi_amount'], 2) ?></div>
                </div>
                <div class="col-6 mb-2">
                    <div class="info-label">Interest</div>
                    <div class="info-value"><?= $emi['interest'] ?>% (<?= $emi['interest_type'] ?>)</div>
                </div>
                <div class="col-6 mb-2">
                    <div class="info-label">Repayment</div>
                    <div class="info-value"><?= $emi['emi_method'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="summary-card shadow-sm">
                <div class="row text-center">
                    <div class="col-md-3 border-end">
                        <div class="info-label">Invoice Total</div>
                        <h5 class="mb-0">₹<?= number_format($emi['product_amount'], 2) ?></h5>
                    </div>
                    <div class="col-md-3 border-end">
                        <div class="info-label">Advance Paid</div>
                        <h5 class="mb-0 text-success">₹<?= number_format($emi['advance_amount'], 2) ?></h5>
                    </div>
                    <div class="col-md-3 border-end">
                        <div class="info-label">Base Principal</div>
                        <h5 class="mb-0 text-danger">₹<?= number_format($emi['total_amount'], 2) ?></h5>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Total Payable</div>
                        <h5 class="mb-0 fw-bold">₹<?= number_format($emi['loan_amount'], 2) ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Table -->
    <h6 class="text-muted text-uppercase fw-bold mb-3 small"><i class="fas fa-list me-2"></i>Repayment Schedule</h6>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead>
                <tr class="text-center">
                    <th width="50">#</th>
                    <th>Due Date</th>
                    <th>Inst. Amount</th>
                    <th>Penalty</th>
                    <th>Discount</th>
                    <th>Paid Amount</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_paid = 0;
                $total_penalty = 0;
                $total_discount = 0;
                foreach ($schedule as $s): 
                    $total_paid += $s['paid_amount'];
                    $total_penalty += $s['penalty_amount'];
                    $total_discount += $s['discount'];
                    
                    $status_badge = 'badge-pending';
                    if ($s['status'] == 'Paid') $status_badge = 'badge-paid';
                    if ($s['status'] == 'Overdue') $status_badge = 'badge-overdue';
                ?>
                    <tr class="text-center">
                        <td><?= $s['installment_no'] ?></td>
                        <td><?= date('d M Y', strtotime($s['due_date'])) ?></td>
                        <td class="fw-bold">₹<?= number_format($s['amount'], 2) ?></td>
                        <td class="text-danger">₹<?= number_format($s['penalty_amount'], 2) ?></td>
                        <td class="text-primary">₹<?= number_format($s['discount'], 2) ?></td>
                        <td class="text-success fw-bold">₹<?= number_format($s['paid_amount'], 2) ?></td>
                        <td><?= $s['payment_date'] ? date('d M Y', strtotime($s['payment_date'])) : '-' ?></td>
                        <td><span class="badge <?= $status_badge ?> border px-2 py-1"><?= $s['status'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr class="text-center">
                    <td colspan="3" class="text-end">TOTALS:</td>
                    <td class="text-danger">₹<?= number_format($total_penalty, 2) ?></td>
                    <td class="text-primary">₹<?= number_format($total_discount, 2) ?></td>
                    <td class="text-success">₹<?= number_format($total_paid, 2) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Final Summary -->
    <div class="row mt-4">
        <div class="col-md-6 offset-md-6">
            <table class="table table-sm table-borderless">
                <tr>
                    <td class="text-end text-muted">Total Repayable (incl. Penalties):</td>
                    <?php $final_total = $emi['loan_amount'] + $total_penalty - $total_discount; ?>
                    <td class="text-end">₹<?= number_format($final_total, 2) ?></td>
                </tr>
                <tr>
                    <td class="text-end text-muted">Total Paid So Far:</td>
                    <td class="text-end text-success">₹<?= number_format($total_paid, 2) ?></td>
                </tr>
                <tr class="border-top">
                    <td class="text-end fw-bold fs-5">Current Outstanding:</td>
                    <td class="text-end fw-bold fs-5 text-danger">₹<?= number_format(max(0, $final_total - $total_paid), 2) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="mt-5 pt-5 text-center no-print">
        <button class="btn btn-primary px-5 py-2 fw-bold" onclick="window.print()">
            <i class="fas fa-print me-2"></i> Print Statement
        </button>
        <a href="emi.php" class="btn btn-outline-secondary px-5 py-2 ms-2">
            <i class="fas fa-arrow-left me-2"></i> Back
        </a>
    </div>

</div>

</body>
</html>
