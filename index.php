<?php
// index.php
require_once 'includes/header.php';

// Prepare Dashboard Variables
$today = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd   = date('Y-m-d', strtotime('sunday this week'));

// Today's Sales
$stmt = $pdo->prepare("SELECT SUM(grand_total) as today_sales FROM sales WHERE sale_date = ?");
$stmt->execute([$today]);
$today_sales = $stmt->fetch()['today_sales'] ?? 0;

// Monthly Sales
$stmt = $pdo->prepare("SELECT SUM(grand_total) as monthly_sales FROM sales WHERE MONTH(sale_date) = ? AND YEAR(sale_date) = ?");
$stmt->execute([$currentMonth, $currentYear]);
$monthly_sales = $stmt->fetch()['monthly_sales'] ?? 0;

// Pending Payments (Sales where paid_amount < grand_total)
$stmt = $pdo->query("SELECT SUM(grand_total - paid_amount) as pending_payments FROM sales WHERE payment_status != 'Paid'");
$pending_payments = $stmt->fetch()['pending_payments'] ?? 0;

// Low Stock Alerts
$stmt = $pdo->query("SELECT p.product_name, bs.quantity, p.min_stock_alert FROM branch_stock bs JOIN products p ON bs.product_id = p.id WHERE bs.quantity <= p.min_stock_alert");
$low_stock_items = $stmt->fetchAll();

// --- EXPENSE SUMMARIES ---
$branch_id = $_SESSION['branch_id'] ?? 1;
$isSuperAdminOrStaff = hasRole(['Super Admin', 'Staff']);

$branchFilter = $isSuperAdminOrStaff ? '' : 'AND e.branch_id = ' . (int)$branch_id;

$today_expense = $pdo->query("SELECT COALESCE(SUM(amount),0) as tot FROM expenses e WHERE e.expense_date = '$today' $branchFilter")->fetchColumn();
$week_expense  = $pdo->query("SELECT COALESCE(SUM(amount),0) as tot FROM expenses e WHERE e.expense_date BETWEEN '$weekStart' AND '$weekEnd' $branchFilter")->fetchColumn();
$month_expense = $pdo->query("SELECT COALESCE(SUM(amount),0) as tot FROM expenses e WHERE MONTH(e.expense_date) = '$currentMonth' AND YEAR(e.expense_date) = '$currentYear' $branchFilter")->fetchColumn();

// Last 7 days sales for chart
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date));
    $stmt = $pdo->prepare("SELECT SUM(grand_total) as daily FROM sales WHERE sale_date = ?");
    $stmt->execute([$date]);
    $chart_data[] = $stmt->fetch()['daily'] ?? 0;
}

// --- REMINDERS & TASKS ---
$reminder_where = $isSuperAdminOrStaff ? "status = 'Pending'" : "status = 'Pending' AND branch_id = " . (int)$branch_id;
$pending_reminders = $pdo->query("SELECT * FROM reminders WHERE $reminder_where AND remind_date <= CURDATE() ORDER BY remind_date ASC, priority DESC LIMIT 10")->fetchAll();

// EMI Due Today Reminders
$emi_where = $isSuperAdminOrStaff ? "" : " AND branch_id = " . (int)$branch_id;
$emi_dues = $pdo->query("SELECT es.*, e.customer_name FROM emi_schedule es JOIN emi e ON es.emi_id = e.id WHERE es.due_date = '$today' AND es.status != 'Paid' $emi_where LIMIT 10")->fetchAll();

// EMI Summary Metrics
$emi_filter = $isSuperAdminOrStaff ? "" : " AND e.branch_id = " . (int)$branch_id;
$total_outstanding = $pdo->query("SELECT SUM(es.amount + es.penalty_amount - es.paid_amount - es.discount) FROM emi_schedule es JOIN emi e ON es.emi_id = e.id WHERE es.status != 'Paid' $emi_filter")->fetchColumn() ?: 0;
$today_emi = $pdo->query("SELECT SUM(es.amount + es.penalty_amount - es.paid_amount - es.discount) FROM emi_schedule es JOIN emi e ON es.emi_id = e.id WHERE es.due_date = '$today' AND es.status != 'Paid' $emi_filter")->fetchColumn() ?: 0;
?>


<!-- Title -->
<h1 class="mt-4 text-wood">Dashboard</h1>
<ol class="breadcrumb mb-4">
    <li class="breadcrumb-item active">Overview</li>
</ol>

<!-- Stats Row -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card bg-primary text-white h-100 py-2 border-left-wood stat-card" onclick="showSalesDetails('today')" style="cursor:pointer;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Today's Sales</div>
                        <div class="h5 mb-0 font-weight-bold">₹<?= number_format($today_sales, 2) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-calendar-day fa-2x text-white-50"></i>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top border-white-50">
                    <small class="text-white-50 small"><i class="fas fa-mouse-pointer me-1"></i>View Details</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card bg-success text-white h-100 py-2 border-left-wood stat-card" onclick="showSalesDetails('month')" style="cursor:pointer;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Monthly Sales</div>
                        <div class="h5 mb-0 font-weight-bold">₹<?= number_format($monthly_sales, 2) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-rupee-sign fa-2x text-white-50"></i>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top border-white-50">
                    <small class="text-white-50 small"><i class="fas fa-mouse-pointer me-1"></i>View Details</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card bg-warning text-dark h-100 py-2 border-left-wood stat-card" onclick="showPendingPayments()" style="cursor:pointer;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Pending Payments</div>
                        <div class="h5 mb-0 font-weight-bold">₹<?= number_format($pending_payments, 2) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-dark-50"></i>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top border-dark-50">
                    <small class="text-dark-50 small"><i class="fas fa-mouse-pointer me-1"></i>View Details</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card bg-danger text-white h-100 py-2 border-left-wood stat-card" onclick="showLowStockDetails()" style="cursor:pointer;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">Low Stock Items</div>
                        <div class="h5 mb-0 font-weight-bold"><?= count($low_stock_items) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-white-50"></i>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top border-white-50">
                    <small class="text-white-50 small"><i class="fas fa-mouse-pointer me-1"></i>View Details</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EMI Summary Cards -->
<?php if (hasRole(['Super Admin', 'Admin'])): ?>
<div class="row mb-4">
    <div class="col-12 mb-2">
        <h6 class="text-muted fw-bold" style="font-size:0.8rem; letter-spacing:1px; text-transform:uppercase;">
            <i class="fas fa-coins me-1 text-primary"></i> EMI Overview
        </h6>
    </div>

    <!-- Total Outstanding -->
    <div class="col-xl-6 col-md-6 mb-3">
        <div class="card h-100 py-2 border-0 shadow-sm stat-card" onclick="showEmiOutstanding()" style="cursor:pointer; background: linear-gradient(135deg,#f0f7ff,#fff); border-left: 4px solid #0d6efd !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Outstanding</div>
                        <div class="h5 mb-0 fw-bold text-primary">₹<?= number_format($total_outstanding, 2) ?></div>
                        <small class="text-muted">Total unpaid balance</small>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:rgba(13,110,253,0.1);">
                            <i class="fas fa-hand-holding-usd fa-lg text-primary"></i>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top">
                    <small class="text-muted small"><i class="fas fa-mouse-pointer me-1"></i>View Details</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's EMI Amount -->
    <div class="col-xl-6 col-md-6 mb-3">
        <div class="card h-100 py-2 border-0 shadow-sm stat-card" onclick="showEmiToday()" style="cursor:pointer; background: linear-gradient(135deg,#fffbf0,#fff); border-left: 4px solid #ffc107 !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Today's EMI (Need to Collect)</div>
                        <div class="h5 mb-0 fw-bold text-warning">₹<?= number_format($today_emi, 2) ?></div>
                        <small class="text-muted"><?= date('d M Y') ?></small>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:rgba(255,193,7,0.1);">
                            <i class="fas fa-calendar-check fa-lg text-warning"></i>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top">
                    <small class="text-muted small"><i class="fas fa-mouse-pointer me-1"></i>View Details</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Expense Summary Cards -->
<div class="row mb-2">
    <div class="col-12 mb-2">
        <h6 class="text-muted fw-bold" style="font-size:0.8rem; letter-spacing:1px; text-transform:uppercase;">
            <i class="fas fa-fire me-1 text-danger"></i> Expense Overview
        </h6>
    </div>

    <!-- Today Expense -->
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card h-100 py-2 border-0 shadow-sm expense-stat-card" onclick="showExpenseReport('today')" style="cursor:pointer; background: linear-gradient(135deg,#fff5f5,#fff); border-left: 4px solid #dc3545 !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">Today's Expenses</div>
                        <div class="h5 mb-0 fw-bold text-danger">₹<?= number_format($today_expense, 2) ?></div>
                        <small class="text-muted"><?= date('d M Y') ?></small>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:rgba(220,53,69,0.1);">
                            <i class="fas fa-calendar-day fa-lg text-danger"></i>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top">
                    <small class="text-muted"><i class="fas fa-mouse-pointer me-1"></i>Click to view details</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Expense -->
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card h-100 py-2 border-0 shadow-sm expense-stat-card" onclick="showExpenseReport('week')" style="cursor:pointer; background: linear-gradient(135deg,#fff8f0,#fff); border-left: 4px solid #fd7e14 !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">This Week's Expenses</div>
                        <div class="h5 mb-0 fw-bold" style="color:#fd7e14;">₹<?= number_format($week_expense, 2) ?></div>
                        <small class="text-muted"><?= date('d M', strtotime($weekStart)) ?> – <?= date('d M Y', strtotime($weekEnd)) ?></small>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:rgba(253,126,20,0.1);">
                            <i class="fas fa-calendar-week fa-lg" style="color:#fd7e14;"></i>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top">
                    <small class="text-muted"><i class="fas fa-mouse-pointer me-1"></i>Click to view details</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Expense -->
    <div class="col-xl-4 col-md-6 mb-3">
        <div class="card h-100 py-2 border-0 shadow-sm expense-stat-card" onclick="showExpenseReport('month')" style="cursor:pointer; background: linear-gradient(135deg,#f0fff4,#fff); border-left: 4px solid #198754 !important;">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">This Month's Expenses</div>
                        <div class="h5 mb-0 fw-bold text-success">₹<?= number_format($month_expense, 2) ?></div>
                        <small class="text-muted"><?= date('F Y') ?></small>
                    </div>
                    <div class="col-auto">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:52px;height:52px;background:rgba(25,135,84,0.1);">
                            <i class="fas fa-calendar-alt fa-lg text-success"></i>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-1 border-top">
                    <small class="text-muted"><i class="fas fa-mouse-pointer me-1"></i>Click to view details</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Expense Detail Modal -->
<div class="modal fade" id="expenseReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" id="expenseReportHeader" style="background: linear-gradient(135deg,#dc3545,#c0392b);">
                <h5 class="modal-title" id="expenseReportTitle"><i class="fas fa-file-invoice-dollar me-2"></i>Expense Report</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Summary Banner -->
                <div class="px-4 py-3 d-flex justify-content-between align-items-center" id="expenseSummaryBanner" style="background:#fff5f5;border-bottom:1px solid #f8d7da;">
                    <div>
                        <span class="text-muted small" id="expenseReportRange"></span>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Total Expenses</div>
                        <div class="fw-bold fs-4 text-danger" id="expenseReportTotal">₹0.00</div>
                    </div>
                </div>
                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="expenseDetailTable">
                        <thead class="table-light">
                            <tr>
                                <th width="15%">Date</th>
                                <th width="20%">Category</th>
                                <th width="30%">Description</th>
                                <th width="15%">Mode</th>
                                <th width="20%" class="text-end">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody id="expenseReportBody">
                            <tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <a href="expenses.php" class="btn btn-outline-danger btn-sm"><i class="fas fa-external-link-alt me-1"></i>Manage All Expenses</a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- General Detail Modal -->
<div class="modal fade" id="dashboardDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header text-white" id="modalHeader" style="background: linear-gradient(135deg,#4e73df,#224abe);">
                <h5 class="modal-title" id="modalTitle">Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="detailTable">
                        <thead class="table-light" id="detailTableHeader">
                        </thead>
                        <tbody id="detailTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <!-- Chart -->
    <div class="col-xl-6 col-lg-12 mb-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-header bg-white border-bottom-0 pt-3">
                <h6 class="fw-bold mb-0"><i class="fas fa-chart-area me-1 text-primary"></i> Sales Last 7 Days</h6>
            </div>
            <div class="card-body"><canvas id="myAreaChart" width="100%" height="40"></canvas></div>
        </div>
    </div>

    <!-- Reminders & Tasks Box -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card h-100 border-warning shadow-sm">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center py-2">
                <span class="small fw-bold"><i class="fas fa-bell me-1"></i> Today's Tasks</span>
                <a href="reminders.php" class="btn btn-sm btn-outline-dark py-0 px-2" style="font-size: 0.65rem;">View All</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small" style="max-height: 300px; overflow-y: auto;">
                    <?php 
                    $has_tasks = false;
                    foreach ($emi_dues as $d): $has_tasks = true; ?>
                        <li class="list-group-item list-group-item-warning border-0 border-bottom p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="font-size: 0.75rem;">
                                    <div class="fw-bold"><?= htmlspecialchars($d['customer_name']) ?></div>
                                    <div class="text-muted">₹<?= number_format($d['amount'], 0) ?> Due</div>
                                </div>
                                <a href="emi_collection.php" class="btn btn-xs btn-success p-1 py-0"><i class="fas fa-coins" style="font-size: 0.7rem;"></i></a>
                            </div>
                        </li>
                    <?php endforeach;
                    foreach ($pending_reminders as $r): $has_tasks = true; 
                        $pColor = $r['priority'] == 'High' ? 'danger' : ($r['priority'] == 'Medium' ? 'warning' : 'info');
                    ?>
                        <li class="list-group-item border-0 border-bottom p-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="font-size: 0.75rem;">
                                    <div class="fw-bold text-<?= $pColor ?>"><?= htmlspecialchars($r['title']) ?></div>
                                    <div class="text-muted text-truncate" style="max-width: 120px;"><?= htmlspecialchars($r['description']) ?></div>
                                </div>
                                <button class="btn btn-xs btn-outline-success p-1 py-0" onclick="updateReminderStatus(<?= $r['id'] ?>)"><i class="fas fa-check" style="font-size: 0.7rem;"></i></button>
                            </div>
                        </li>
                    <?php endforeach;
                    if (!$has_tasks): ?>
                        <li class="list-group-item text-center text-muted py-4 small">No tasks.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Box -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card h-100 border-danger shadow-sm">
            <div class="card-header bg-danger text-white py-2">
                <span class="small fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Low Stock</span>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small" style="max-height: 300px; overflow-y: auto;">
                    <?php if (count($low_stock_items) > 0): ?>
                        <?php foreach ($low_stock_items as $item): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center p-2" style="font-size: 0.75rem;">
                                <?= htmlspecialchars($item['product_name']) ?>
                                <span class="badge bg-danger rounded-pill"><?= $item['quantity'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item text-center text-muted py-4">Stock OK.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Expense Report Modal
    const expensePeriodConfig = {
        today: {
            label: 'Today\'s Expenses',
            color: 'linear-gradient(135deg,#dc3545,#c0392b)',
            rangeText: '<?= date("d M Y") ?>'
        },
        week: {
            label: 'This Week\'s Expenses',
            color: 'linear-gradient(135deg,#fd7e14,#e67e22)',
            rangeText: '<?= date("d M", strtotime($weekStart)) ?> – <?= date("d M Y", strtotime($weekEnd)) ?>'
        },
        month: {
            label: 'This Month\'s Expenses',
            color: 'linear-gradient(135deg,#198754,#16a085)',
            rangeText: '<?= date("F Y") ?>'
        }
    };

    function showExpenseReport(period) {
        let cfg = expensePeriodConfig[period];
        document.getElementById('expenseReportTitle').innerHTML = '<i class="fas fa-file-invoice-dollar me-2"></i>' + cfg.label;
        document.getElementById('expenseReportHeader').style.background = cfg.color;
        document.getElementById('expenseReportRange').textContent = cfg.rangeText;
        document.getElementById('expenseReportTotal').textContent = '₹...';
        document.getElementById('expenseReportBody').innerHTML = '<tr><td colspan="5" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>';

        let modal = new bootstrap.Modal(document.getElementById('expenseReportModal'));
        modal.show();

        $.post('ajax/expense_action.php', { action: 'get_expense_report', period: period }, function(res) {
            if (!res.success) {
                document.getElementById('expenseReportBody').innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Failed to load data.</td></tr>';
                return;
            }
            let rows = '';
            let total = 0;
            if (res.data.length === 0) {
                rows = '<tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-receipt fa-2x mb-2 d-block opacity-25"></i>No expenses found for this period.</td></tr>';
            } else {
                res.data.forEach(e => {
                    let amt = parseFloat(e.amount);
                    total += amt;
                    rows += `
                        <tr>
                            <td class="text-muted small">${e.expense_date}</td>
                            <td><span class="badge bg-secondary">${e.category_name || 'N/A'}</span></td>
                            <td class="small">${e.description || '–'}</td>
                            <td><span class="badge bg-light text-dark border">${e.payment_mode}</span></td>
                            <td class="text-end fw-bold text-danger">₹${amt.toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                        </tr>`;
                });
            }
            document.getElementById('expenseReportBody').innerHTML = rows;
            document.getElementById('expenseReportTotal').textContent = '₹' + total.toLocaleString('en-IN', {minimumFractionDigits:2});
        }, 'json');
    }

    // Hover animation for expense cards
    document.querySelectorAll('.expense-stat-card').forEach(card => {
        card.addEventListener('mouseenter', () => card.style.transform = 'translateY(-3px)');
        card.addEventListener('mouseleave', () => card.style.transform = 'translateY(0)');
    });

    // Load Chart
    document.addEventListener('DOMContentLoaded', function() {
        var ctx = document.getElementById("myAreaChart");
        var myLineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: "Sales (₹)",
                    lineTension: 0.3,
                    backgroundColor: "rgba(139, 90, 43, 0.2)",
                    borderColor: "rgba(139, 90, 43, 1)",
                    pointRadius: 5,
                    pointBackgroundColor: "rgba(139, 90, 43, 1)",
                    pointBorderColor: "rgba(255,255,255,0.8)",
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: "rgba(139, 90, 43, 1)",
                    pointHitRadius: 50,
                    pointBorderWidth: 2,
                    data: <?= json_encode($chart_data) ?>,
                }],
            },
            options: {
                scales: {
                    xAxes: [{
                        time: {
                            unit: 'date'
                        },
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            maxTicksLimit: 7
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            min: 0,
                            maxTicksLimit: 5
                        },
                        gridLines: {
                            color: "rgba(0, 0, 0, .125)",
                        }
                    }],
                },
                legend: {
                    display: false
                }
            }
        });
    });

    // Dashboard Detail Functions
    function showSalesDetails(period) {
        let title = period === 'today' ? "Today's Sales" : "Monthly Sales";
        let header = `
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th class="text-end">Amount</th>
            </tr>`;
        
        setupModal(title, 'linear-gradient(135deg,#4e73df,#224abe)', header);
        
        $.post('ajax/dashboard_action.php', { action: 'get_sales_details', period: period }, function(res) {
            if (res.success) {
                let rows = '';
                res.data.forEach(s => {
                    rows += `
                        <tr>
                            <td><a href="print_invoice.php?id=${s.id}" target="_blank" class="fw-bold">${s.invoice_no}</a></td>
                            <td>${s.customer_name || 'Walk-in'}</td>
                            <td>${s.sale_date}</td>
                            <td><span class="badge ${s.payment_status === 'Paid' ? 'bg-success' : 'bg-warning'}">${s.payment_status}</span></td>
                            <td class="text-end fw-bold">₹${parseFloat(s.grand_total).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                        </tr>`;
                });
                renderModalBody(rows || '<tr><td colspan="5" class="text-center py-4">No sales found.</td></tr>');
            }
        }, 'json');
    }

    function showPendingPayments() {
        let header = `
            <tr>
                <th>Invoice</th>
                <th>Customer</th>
                <th class="text-end">Total</th>
                <th class="text-end">Paid</th>
                <th class="text-end">Pending</th>
            </tr>`;
        
        setupModal('Pending Payments', 'linear-gradient(135deg,#f6c23e,#f4b619)', header);
        
        $.post('ajax/dashboard_action.php', { action: 'get_pending_payments' }, function(res) {
            if (res.success) {
                let rows = '';
                res.data.forEach(s => {
                    rows += `
                        <tr>
                            <td><a href="print_invoice.php?id=${s.id}" target="_blank" class="fw-bold">${s.invoice_no}</a></td>
                            <td>${s.customer_name || 'Walk-in'}</td>
                            <td class="text-end">₹${parseFloat(s.grand_total).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                            <td class="text-end">₹${parseFloat(s.paid_amount).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                            <td class="text-end fw-bold text-danger">₹${parseFloat(s.pending).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                        </tr>`;
                });
                renderModalBody(rows || '<tr><td colspan="5" class="text-center py-4">No pending payments.</td></tr>');
            }
        }, 'json');
    }

    function showLowStockDetails() {
        let header = `
            <tr>
                <th>Product</th>
                <th class="text-center">Current Qty</th>
                <th class="text-center">Alert Qty</th>
                <th class="text-end">Price</th>
            </tr>`;
        
        setupModal('Low Stock Items', 'linear-gradient(135deg,#e74a3b,#be2617)', header);
        
        $.post('ajax/dashboard_action.php', { action: 'get_low_stock' }, function(res) {
            if (res.success) {
                let rows = '';
                res.data.forEach(p => {
                    rows += `
                        <tr>
                            <td class="fw-bold">${p.product_name}</td>
                            <td class="text-center text-danger fw-bold">${p.quantity}</td>
                            <td class="text-center">${p.min_stock_alert}</td>
                            <td class="text-end">₹${parseFloat(p.sale_price).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                        </tr>`;
                });
                renderModalBody(rows || '<tr><td colspan="4" class="text-center py-4">All stock levels are healthy.</td></tr>');
            }
        }, 'json');
    }

    function showEmiOutstanding() {
        let header = `
            <tr>
                <th>Customer</th>
                <th>Product</th>
                <th>Due Date</th>
                <th class="text-end">Pending</th>
            </tr>`;
        
        setupModal('EMI Outstanding', 'linear-gradient(135deg,#4e73df,#224abe)', header);
        
        $.post('ajax/dashboard_action.php', { action: 'get_emi_outstanding' }, function(res) {
            if (res.success) {
                let rows = '';
                res.data.forEach(e => {
                    rows += `
                        <tr>
                            <td class="fw-bold">${e.customer_name}</td>
                            <td>${e.product_name}</td>
                            <td>${e.due_date}</td>
                            <td class="text-end fw-bold text-danger">₹${parseFloat(e.pending).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                        </tr>`;
                });
                renderModalBody(rows || '<tr><td colspan="4" class="text-center py-4">No outstanding EMIs.</td></tr>');
            }
        }, 'json');
    }

    function showEmiToday() {
        let header = `
            <tr>
                <th>Customer</th>
                <th>Product</th>
                <th class="text-end">Amount</th>
            </tr>`;
        
        setupModal("Today's EMI Collection", 'linear-gradient(135deg,#f6c23e,#f4b619)', header);
        
        $.post('ajax/dashboard_action.php', { action: 'get_emi_today' }, function(res) {
            if (res.success) {
                let rows = '';
                res.data.forEach(e => {
                    rows += `
                        <tr>
                            <td class="fw-bold">${e.customer_name}</td>
                            <td>${e.product_name}</td>
                            <td class="text-end fw-bold text-warning">₹${parseFloat(e.pending).toLocaleString('en-IN', {minimumFractionDigits:2})}</td>
                        </tr>`;
                });
                renderModalBody(rows || '<tr><td colspan="3" class="text-center py-4">No EMI collections for today.</td></tr>');
            }
        }, 'json');
    }

    function setupModal(title, background, headerHtml) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalHeader').style.background = background;
        document.getElementById('detailTableHeader').innerHTML = headerHtml;
        document.getElementById('detailTableBody').innerHTML = '<tr><td colspan="10" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>';
        
        let modal = new bootstrap.Modal(document.getElementById('dashboardDetailModal'));
        modal.show();
    }

    function renderModalBody(html) {
        document.getElementById('detailTableBody').innerHTML = html;
    }

    // Hover animation for all stat cards
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', () => card.style.transform = 'translateY(-3px)');
        card.addEventListener('mouseleave', () => card.style.transform = 'translateY(0)');
    });

    function updateReminderStatus(id) {
        $.post('ajax/reminder_action.php', { action: 'update_status', id: id, status: 'Completed' }, function(res) {
            if (res.success) {
                location.reload();
            }
        }, 'json');
    }
</script>

<?php require_once 'includes/footer.php'; ?>
