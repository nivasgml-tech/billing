<?php
// print_invoice.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/database.php';
require_once 'includes/auth.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($sale_id <= 0) die("Invalid Invoice ID");

$stmt = $pdo->prepare("
    SELECT s.*, c.customer_name, c.address as cus_address, c.phone as cus_phone, c.gst_number as cus_gst,
           u.full_name as created_by 
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ? AND s.branch_id = ?
");
$stmt->execute([$sale_id, $_SESSION['branch_id'] ?? 1]);
$sale = $stmt->fetch();

if (!$sale) die("Invoice not found.");

$stmtItems = $pdo->prepare("
    SELECT si.*, p.product_name, p.hsn_code, u.unit_name 
    FROM sales_items si
    JOIN products p ON si.product_id = p.id
    LEFT JOIN units u ON p.unit_id = u.id
    WHERE si.sale_id = ?
");
$stmtItems->execute([$sale_id]);
$items = $stmtItems->fetchAll();
//$print_type = isset($_GET['type']) ? $_GET['type'] : 'a5';
$print_type = isset($_GET['type']) ? $_GET['type'] : 'thermal';

if ($print_type === 'thermal') {
    include 'print_thermal_view.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Invoice <?= htmlspecialchars($sale['invoice_no']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-size: 14px;
            color: #333;
        }

        .invoice-title {
            color: #8B5A2B;
            font-weight: bold;
        }

        .table th {
            background-color: #f8f9fa !important;
        }

        @media print {
            @page {
                size: A5 portrait;
                margin: 5mm;
            }

            .no-print {
                display: none !important;
            }

            body {
                margin: 0;
                padding: 0;
            }

            .container {
                width: 100%;
                max-width: 100%;
                border: none !important;
                box-shadow: none !important;
                padding: 10px !important;
                page-break-inside: avoid;
            }

            table {
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
        }
    </style>
</head>

<body class="bg-light <?= isset($_GET['modal']) ? 'p-0' : 'p-4' ?>">

    <div class="container bg-white p-5 border rounded shadow-sm" style="max-width: 900px;">

        <div class="row mb-4">
            <div class="col-6">
                <h2 class="invoice-title mb-0"><?= isset($settings['shop_name']) ? htmlspecialchars($settings['shop_name']) : 'Furniture ERP' ?></h2>
                <p class="text-muted mb-0"><?= isset($settings['shop_address']) ? nl2br(htmlspecialchars($settings['shop_address'])) : '' ?></p>
                <p class="text-muted mb-0">Phone: <?= isset($settings['shop_contact']) ? htmlspecialchars($settings['shop_contact']) : '' ?></p>
                <p class="text-muted fw-bold">GSTIN: <?= isset($settings['gst_number']) ? htmlspecialchars($settings['gst_number']) : '' ?></p>
            </div>
            <div class="col-6 text-end">
                <h1 class="text-uppercase text-muted"><?= htmlspecialchars($sale['sale_type']) ?></h1>
                <p class="mb-0"><strong>Invoice No:</strong> <?= htmlspecialchars($sale['invoice_no']) ?></p>
                <p class="mb-0"><strong>Date:</strong> <?= date('d M Y', strtotime($sale['sale_date'])) ?></p>
                <p class="mb-0"><strong>Type:</strong> <?= $sale['is_gst_bill'] ? 'Tax Invoice' : 'Bill of Supply' ?></p>
            </div>
        </div>

        <hr>

        <div class="row mb-4">
            <div class="col-6">
                <h6 class="text-muted text-uppercase fw-bold">Billed To:</h6>
                <?php if ($sale['customer_name']): ?>
                    <h5 class="mb-0"><?= htmlspecialchars($sale['customer_name']) ?></h5>
                    <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($sale['cus_address'] ?? '')) ?></p>
                    <p class="mb-0 text-muted">Phone: <?= htmlspecialchars($sale['cus_phone'] ?? 'N/A') ?></p>
                    <?php if ($sale['cus_gst']): ?>
                        <p class="mb-0 text-muted fw-bold">GSTIN: <?= htmlspecialchars($sale['cus_gst']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <h5 class="mb-0 text-muted">Walk-in Customer</h5>
                <?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-muted text-uppercase fw-bold">Payment Details:</h6>
                <p class="mb-0"><strong>Payment Mode:</strong> <?= htmlspecialchars($sale['payment_mode']) ?></p>
                <p class="mb-0"><strong>Status:</strong>
                    <?php if ($sale['payment_status'] == 'Paid'): ?>
                        <span class="text-success fw-bold">Paid</span>
                    <?php elseif ($sale['payment_status'] == 'Partial'): ?>
                        <span class="text-warning fw-bold">Partial</span>
                    <?php else: ?>
                        <span class="text-danger fw-bold">Unpaid</span>
                    <?php endif; ?>
                </p>
                <p class="mb-0"><strong>Generated By:</strong> <?= htmlspecialchars($sale['created_by']) ?></p>
            </div>
        </div>

        <table class="table table-bordered table-sm mb-4">
            <thead>
                <tr class="text-center">
                    <th>#</th>
                    <th class="text-start">Item Description</th>
                    <th>HSN</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Discount</th>
                    <?php if ($sale['is_gst_bill']): ?>
                        <th>CGST %</th>
                        <th>CGST Amt</th>
                        <th>SGST %</th>
                        <th>SGST Amt</th>
                    <?php endif; ?>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1;
                foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center"><?= $i++ ?></td>
                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="text-center"><?= htmlspecialchars($item['hsn_code']) ?></td>
                        <td class="text-center">
                            <?= floatval($item['quantity']) ?> <?= htmlspecialchars($item['unit_name']) ?>
                            <?php if (floatval($item['returned_qty']) > 0): ?>
                                <br><small class="text-danger fw-bold">(Ret: <?= floatval($item['returned_qty']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><?= number_format($item['unit_price'], 2) ?></td>
                        <td class="text-end"><?= number_format($item['discount_amount'], 2) ?></td>
                        <?php if ($sale['is_gst_bill']): ?>
                            <?php 
                            $active_qty = floatval($item['quantity'] - $item['returned_qty']);
                            $ratio = ($item['quantity'] > 0) ? ($active_qty / $item['quantity']) : 0;
                            $net_tax = $item['tax_amount'] * $ratio;
                            $half_tax = $item['tax_rate'] / 2;
                            $half_amt = $net_tax / 2; 
                            ?>
                            <td class="text-center"><?= floatval($half_tax) ?>%</td>
                            <td class="text-end"><?= number_format($half_amt, 2) ?></td>
                            <td class="text-center"><?= floatval($half_tax) ?>%</td>
                            <td class="text-end"><?= number_format($half_amt, 2) ?></td>
                        <?php endif; ?>
                        <td class="text-end fw-bold">
                            <?php 
                             $active_qty = floatval($item['quantity'] - $item['returned_qty']);
                             $ratio = ($item['quantity'] > 0) ? ($active_qty / $item['quantity']) : 0;
                             $net_total = $item['total_amount'] * $ratio;
                             echo number_format($net_total, 2);
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row">
            <div class="col-6">
                <div class="border p-3 rounded h-100 bg-light">
                    <h6 class="text-muted text-uppercase fw-bold">Terms & Conditions</h6>
                    <p class="small text-muted mb-0">
                        <?= isset($settings['invoice_footer']) ? nl2br(htmlspecialchars($settings['invoice_footer'])) : 'Goods once sold cannot be returned. All disputes subject to local jurisdiction.' ?>
                    </p>
                </div>
            </div>
            <div class="col-6">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-end text-muted">Sub Total:</td>
                        <td class="text-end">₹<?= number_format($sale['sub_total'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-end text-muted">Discount:</td>
                        <td class="text-end text-danger">- ₹<?= number_format($sale['discount_amount'], 2) ?></td>
                    </tr>
                    <?php if ($sale['is_gst_bill']): ?>
                        <?php $half_total_tax = $sale['tax_amount'] / 2; ?>
                        <tr>
                            <td class="text-end text-muted">CGST Amount:</td>
                            <td class="text-end text-primary">+ ₹<?= number_format($half_total_tax, 2) ?></td>
                        </tr>
                        <tr>
                            <td class="text-end text-muted">SGST Amount:</td>
                            <td class="text-end text-primary">+ ₹<?= number_format($half_total_tax, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-end text-muted">Round Off:</td>
                        <td class="text-end">₹<?= number_format($sale['round_off'], 2) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td class="text-end fw-bold fs-5">Grand Total:</td>
                        <td class="text-end fw-bold fs-5 text-success">₹<?= number_format($sale['grand_total'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-end fw-bold">Advance Paid:</td>
                        <td class="text-end fw-bold text-success">₹<?= number_format($sale['paid_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-end fw-bold">Balance Due:</td>
                        <td class="text-end fw-bold text-danger">₹<?= number_format($sale['grand_total'] - $sale['paid_amount'], 2) ?></td>
                    </tr>
                </table>

                <div class="mt-4 pt-4 text-center border-top">
                    <p class="mb-5 small text-muted">Authorized Signatory</p>
                </div>
            </div>
        </div>

        <!-- Print Button -->
        <div class="text-center mt-4 no-print">
            <a href="print_invoice.php?id=<?= (int)$sale['id'] ?>&type=thermal"
       class="btn btn-primary">
        <i class="fas fa-print me-1"></i> Print Invoice
    </a>
     <?php if (!isset($_GET['modal'])): ?>
                <a href="pos.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to POS</a>
            <?php endif; ?>
        </div>

    </div>

</body>

</html>