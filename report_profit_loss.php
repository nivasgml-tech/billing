<?php
// report_profit_loss.php
require_once 'includes/header.php';
requireRole(['Super Admin', 'Admin', 'Staff']);

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$business_segment = isset($_GET['business_segment']) ? $_GET['business_segment'] : 'All';

// Logic for P&L:
// Income = Net Sales + Other Income
// Expenses = Net Purchases + Operating Expenses
// EMI/Loan data is excluded from this statement.

// 1. Sales made in period (Net of any returns)
// 2. Returns processed in period for sales made OUTSIDE the period? 
// Actually, simple way: 
// Revenue = (Sum sub_total of all sales made in period) 
// Cost of Sale = (Sum sub_total of all purchases in period)
// NO, that's Cash Flow.
// For periodic P&L: 
// - Income = Current Revenue from Sales made in period (which is net of returns due to our fix)
// - Cost = Current Cost from Purchases made in period (net of returns)

// --- REVENUE CALCULATION ---
$stmtSales = $pdo->prepare("SELECT SUM(sub_total) as total FROM sales WHERE sale_date BETWEEN ? AND ? AND sale_type = 'Sale Invoice' AND payment_status != 'Returned'");
$stmtSales->execute([$start_date, $end_date]);
$netSales = $stmtSales->fetch()['total'] ?? 0;

$stmtSalesRet = $pdo->prepare("SELECT SUM(subtotal_refund) as total FROM sales_returns WHERE return_date BETWEEN ? AND ?");
$stmtSalesRet->execute([$start_date, $end_date]);
$salesReturns = $stmtSalesRet->fetch()['total'] ?? 0;

$emiRevenue = 0; // Excluded as requested

// --- EXPENSES / COSTS ---
$stmtPurchases = $pdo->prepare("SELECT SUM(sub_total) as total FROM purchases WHERE purchase_date BETWEEN ? AND ? AND payment_status != 'Returned'");
$stmtPurchases->execute([$start_date, $end_date]);
$netPurchases = $stmtPurchases->fetch()['total'] ?? 0;

$stmtPurRet = $pdo->prepare("SELECT SUM(subtotal_refund) as total FROM purchase_returns WHERE return_date BETWEEN ? AND ?");
$stmtPurRet->execute([$start_date, $end_date]);
$purchaseReturns = $stmtPurRet->fetch()['total'] ?? 0;

// Operating Expenses (Excluding EMI tagged)
$stmtExpenses = $pdo->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ? AND account_type != 'EMI'");
$stmtExpenses->execute([$start_date, $end_date]);
$operatingExpenses = $stmtExpenses->fetch()['total'] ?? 0;

// Other Income (Excluding EMI tagged)
$stmtIncome = $pdo->prepare("SELECT SUM(amount) as total FROM other_income WHERE income_date BETWEEN ? AND ? AND account_type != 'EMI'");
$stmtIncome->execute([$start_date, $end_date]);
$otherIncome = $stmtIncome->fetch()['total'] ?? 0;

// Calculation
$totalRevenue = $netSales + $emiRevenue + $otherIncome;
$totalCosts = $netPurchases + $operatingExpenses;
$netProfit = $totalRevenue - $totalCosts;

?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark"><i class="fas fa-balance-scale-right me-2 text-primary"></i>Profit & Loss Statement</h1>
            <p class="text-secondary small mb-0">Financial summarized report for the selected period</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Print Report</button>
            <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#filterCard"><i class="fas fa-filter me-1"></i> Filter Period</button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="collapse mb-4" id="filterCard">
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">From Date</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" name="start_date" class="form-control border-start-0" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold text-muted">To Date</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="fas fa-calendar-alt"></i></span>
                            <input type="date" name="end_date" class="form-control border-start-0" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">Generate Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Revenue Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-chart-line me-2 text-success"></i>Revenue & Income</h5>
                </div>
                <div class="card-body p-4">
                    <div class="list-group list-group-flush border-0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-secondary">Sales Revenue (Net of Returns)</span>
                            <span class="fw-bold text-dark fs-5">₹<?= number_format($netSales, 2) ?></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-secondary">Other Income / Fees</span>
                            <span class="fw-bold text-dark fs-5">₹<?= number_format($otherIncome, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 opacity-75">
                            <span class="text-muted small">Returns processed this period</span>
                            <span class="text-danger small">(- ₹<?= number_format($salesReturns, 2) ?>)</span>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center p-3 bg-success bg-opacity-10 rounded-3">
                        <span class="fw-bold text-success h5 mb-0">Total Revenue (A)</span>
                        <span class="fw-bold text-success h4 mb-0">₹<?= number_format($totalRevenue, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expenses Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-shopping-basket me-2 text-danger"></i>Direct Costs & Expenses</h5>
                </div>
                <div class="card-body p-4">
                    <div class="list-group list-group-flush border-0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-secondary">Direct Purchases (Net of Returns)</span>
                            <span class="fw-bold text-dark fs-5">₹<?= number_format($netPurchases, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-secondary">Operating Expenses (Admin, Utilities, Rent)</span>
                            <span class="fw-bold text-dark fs-5">₹<?= number_format($operatingExpenses, 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 opacity-75">
                            <span class="text-muted small">Purchase returns to suppliers</span>
                            <span class="text-info small">(₹<?= number_format($purchaseReturns, 2) ?>)</span>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center p-3 bg-danger bg-opacity-10 rounded-3">
                        <span class="fw-bold text-danger h5 mb-0">Total Costs (B)</span>
                        <span class="fw-bold text-danger h4 mb-0">₹<?= number_format($totalCosts, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Box -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-lg rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #2d3436 0%, #000000 100%);">
                <div class="card-body p-5 text-center text-white">
                    <p class="text-uppercase tracking-widest small mb-2" style="letter-spacing: 2px;">Net Surplus / Deficit</p>
                    <h2 class="h4 text-muted mb-4">A (Revenue) - B (Costs)</h2>
                    
                    <div class="d-inline-block px-5 py-3 rounded-4 bg-white shadow-lg">
                        <?php if ($netProfit >= 0): ?>
                            <div class="h5 text-muted mb-1 fw-semibold">NET PROFIT</div>
                            <h1 class="display-3 fw-bold text-success mb-0">₹<?= number_format($netProfit, 2) ?></h1>
                        <?php else: ?>
                            <div class="h5 text-muted mb-1 fw-semibold">NET LOSS</div>
                            <h1 class="display-3 fw-bold text-danger mb-0">- ₹<?= number_format(abs($netProfit), 2) ?></h1>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4">
                        <p class="text-secondary small mb-0">Report Generated on <?= date('d M Y, h:i A') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exportable Details Table -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title mb-0 fw-bold"><i class="fas fa-file-export me-2 text-primary"></i>Exportable Data Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped datatable w-100 table-bordered">
                        <thead class="bg-wood text-white">
                            <tr>
                                <th>Category</th>
                                <th>Particulars</th>
                                <th class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Revenue</td>
                                <td>Sales Revenue (Net of Returns)</td>
                                <td class="text-end fw-bold text-success"><?= number_format($netSales, 2) ?></td>
                            </tr>

                            <tr>
                                <td>Revenue</td>
                                <td>Other Income / Fees</td>
                                <td class="text-end fw-bold text-success"><?= number_format($otherIncome, 2) ?></td>
                            </tr>
                            <tr>
                                <td>Deduction</td>
                                <td>Returns Processed this Period</td>
                                <td class="text-end fw-bold text-danger"><?= number_format($salesReturns, 2) ?></td>
                            </tr>

                            <tr>
                                <td>Expense</td>
                                <td>Direct Purchases (Net of Returns)</td>
                                <td class="text-end fw-bold text-danger"><?= number_format($netPurchases, 2) ?></td>
                            </tr>

                            <tr>
                                <td>Expense</td>
                                <td>Operating Expenses</td>
                                <td class="text-end fw-bold text-danger"><?= number_format($operatingExpenses, 2) ?></td>
                            </tr>
                            <tr class="table-primary border-top border-dark">
                                <td>Summary</td>
                                <td>Total Revenue</td>
                                <td class="text-end fw-bold text-dark fs-5"><?= number_format($totalRevenue, 2) ?></td>
                            </tr>
                            <tr class="table-warning">
                                <td>Summary</td>
                                <td>Total Costs</td>
                                <td class="text-end fw-bold text-dark fs-5"><?= number_format($totalCosts, 2) ?></td>
                            </tr>
                            <tr class="table-<?= $netProfit >= 0 ? 'success' : 'danger' ?>">
                                <td>Final Result</td>
                                <td class="fw-bold fs-4"><?= $netProfit >= 0 ? 'Net Profit' : 'Net Loss' ?></td>
                                <td class="text-end fw-bold fs-4"><?= number_format(abs($netProfit), 2) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
@media print {
    #sidebar-wrapper, .navbar, .btn, #filterCard { display: none !important; }
    #page-content-wrapper { padding: 0 !important; }
    .card { border: 1px solid #eee !important; box-shadow: none !important; }
}
.tracking-widest { letter-spacing: 0.1em; }
</style>

<?php require_once 'includes/footer.php'; ?>
