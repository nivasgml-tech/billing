<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="border-end border-0" id="sidebar-wrapper">
    <div class="sidebar-heading border-bottom">
        <i class="fas fa-couch me-2"></i><?= isset($settings['shop_name']) ? htmlspecialchars($settings['shop_name']) : 'Furniture POS' ?>
    </div>
    <div class="list-group list-group-flush mt-2">
        <a href="index.php" class="list-group-item list-group-item-action <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="reminders.php" class="list-group-item list-group-item-action <?= $current_page == 'reminders.php' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i> Reminders
        </a>
        <!-- POS & Sales -->
        <a href="#salesSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action has-submenu" aria-expanded="false">
            <i class="fas fa-shopping-cart"></i> Sales & POS <i class="fas fa-chevron-down float-end mt-1" style="width: auto;"></i>
        </a>
        <div class="submenu collapse" id="salesSubmenu">
            <a href="customers.php" class="list-group-item list-group-item-action"><i class="fas fa-users"></i> Customers</a>
            <a href="pos.php" class="list-group-item list-group-item-action"><i class="fas fa-desktop"></i> POS Screen</a>
            <a href="sales.php" class="list-group-item list-group-item-action"><i class="fas fa-file-invoice-dollar"></i> Sales Invoices</a>
            <a href="sales_return.php" class="list-group-item list-group-item-action"><i class="fas fa-undo"></i> Sale Returns</a>
            <a href="estimates.php" class="list-group-item list-group-item-action"><i class="fas fa-file-signature"></i> Estimates</a>
            <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
                <a href="other_income.php" class="list-group-item list-group-item-action"><i class="fas fa-hand-holding-usd"></i> Other Income</a>
            <?php endif; ?>
        </div>

        <!-- Products -->
        <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
            <a href="#productSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action has-submenu" aria-expanded="false">
                <i class="fas fa-box-open"></i> Products <i class="fas fa-chevron-down float-end mt-1" style="width: auto;"></i>
            </a>
            <div class="submenu collapse" id="productSubmenu">
                <a href="products.php" class="list-group-item list-group-item-action"><i class="fas fa-boxes"></i> All Products</a>
                <a href="categories.php" class="list-group-item list-group-item-action"><i class="fas fa-tags"></i> Categories</a>
                <a href="units.php" class="list-group-item list-group-item-action"><i class="fas fa-balance-scale"></i> Units</a>
                <a href="stock.php" class="list-group-item list-group-item-action"><i class="fas fa-cubes"></i> Stock View</a>
            </div>
        <?php endif; ?>

        <!-- Purchases -->
        <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
            <a href="#purchaseSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action has-submenu" aria-expanded="false">
                <i class="fas fa-truck-loading"></i> Purchases <i class="fas fa-chevron-down float-end mt-1" style="width: auto;"></i>
            </a>
            <div class="submenu collapse" id="purchaseSubmenu">
                <a href="purchases.php" class="list-group-item list-group-item-action"><i class="fas fa-file-invoice"></i> Purchase Bills</a>
                <a href="purchase_return.php" class="list-group-item list-group-item-action"><i class="fas fa-undo-alt"></i> Purchase Returns</a>
                <a href="suppliers.php" class="list-group-item list-group-item-action"><i class="fas fa-users-cog"></i> Suppliers</a>
            </div>
        <?php endif; ?>

        <!-- Expenses -->
        <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
            <a href="#expenseSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action has-submenu" aria-expanded="false">
                <i class="fas fa-money-bill-wave"></i> Expenses <i class="fas fa-chevron-down float-end mt-1" style="width: auto;"></i>
            </a>
            <div class="submenu collapse" id="expenseSubmenu">
                <a href="expenses.php" class="list-group-item list-group-item-action"><i class="fas fa-receipt"></i> Expense List</a>
                <a href="expense_categories.php" class="list-group-item list-group-item-action"><i class="fas fa-list-ul"></i> Expense Categories</a>
            </div>
        <?php endif; ?>

        <!-- Finance -->
        <a href="#financeSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action has-submenu" aria-expanded="false">
            <i class="fas fa-wallet"></i> Finance <i class="fas fa-chevron-down float-end mt-1" style="width: auto;"></i>
        </a>
        <div class="submenu collapse" id="financeSubmenu">
            <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
                <a href="emi.php" class="list-group-item list-group-item-action"><i class="fas fa-hand-holding-heart"></i> EMI Management</a>
            <?php endif; ?>

            <a href="emi_collection.php" class="list-group-item list-group-item-action <?= $current_page == 'emi_collection.php' ? 'active' : '' ?>"><i class="fas fa-coins"></i> EMI Collection</a>
            
            <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
                <a href="emi_approvals.php" class="list-group-item list-group-item-action <?= $current_page == 'emi_approvals.php' ? 'active' : '' ?>"><i class="fas fa-check-double"></i> Collection Approvals</a>
            <?php endif; ?>
            
            <a href="emi_collection_sheet.php" class="list-group-item list-group-item-action <?= $current_page == 'emi_collection_sheet.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Collection Sheet</a>
            <a href="emi_overdue.php" class="list-group-item list-group-item-action text-danger <?= $current_page == 'emi_overdue.php' ? 'active' : '' ?>"><i class="fas fa-exclamation-circle"></i> EMI Overdue</a>

            <!-- Expense categories moved to expense submenu -->
        </div>

        <!-- Reports -->
        <a href="#reportSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action has-submenu" aria-expanded="false">
            <i class="fas fa-chart-bar"></i> Reports <i class="fas fa-chevron-down float-end mt-1" style="width: auto;"></i>
        </a>
        <div class="submenu collapse" id="reportSubmenu">
            <a href="report_sales.php" class="list-group-item list-group-item-action"><i class="fas fa-chart-line"></i> Sales Report</a>
            <a href="report_sales_return.php" class="list-group-item list-group-item-action"><i class="fas fa-undo"></i> Sales Return Report</a>
            <a href="report_purchase.php" class="list-group-item list-group-item-action"><i class="fas fa-file-invoice"></i> Purchase Report</a>
            <a href="report_purchase_return.php" class="list-group-item list-group-item-action"><i class="fas fa-undo-alt"></i> Purchase Return Report</a>
            <a href="report_partial_payments.php" class="list-group-item list-group-item-action"><i class="fas fa-hand-holding-usd"></i> Sales Partial Payments</a>
            <a href="report_purchase_partial_payments.php" class="list-group-item list-group-item-action"><i class="fas fa-file-invoice-dollar"></i> Purchase Partial Payments</a>
            <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
                <a href="report_collection_approvals.php" class="list-group-item list-group-item-action"><i class="fas fa-check-circle"></i> Collection Approvals</a>
            <?php endif; ?>
            <?php if (hasRole(['Super Admin', 'Admin'])): ?>
                <a href="report_emi_collected.php" class="list-group-item list-group-item-action"><i class="fas fa-money-check-alt"></i> EMI Collected Report</a>
                <a href="report_gst.php" class="list-group-item list-group-item-action"><i class="fas fa-file-alt"></i> GST Report</a>
                <a href="report_profit.php" class="list-group-item list-group-item-action"><i class="fas fa-hand-holding-usd"></i> Profit Report</a>
            <?php endif; ?>
        </div>

        <?php if (hasRole(['Super Admin', 'Admin', 'Staff'])): ?>
            <!-- Settings -->
            <a href="#settingsSubmenu" data-bs-toggle="collapse" class="list-group-item list-group-item-action has-submenu" aria-expanded="false">
                <i class="fas fa-cogs"></i> Settings <i class="fas fa-chevron-down float-end mt-1" style="width: auto;"></i>
            </a>
            <div class="submenu collapse" id="settingsSubmenu">
                <a href="settings.php" class="list-group-item list-group-item-action"><i class="fas fa-cog"></i> General Settings</a>
                <a href="roles.php" class="list-group-item list-group-item-action"><i class="fas fa-user-tag"></i> System Roles</a>
                <a href="users.php" class="list-group-item list-group-item-action"><i class="fas fa-id-badge"></i> Staff Management</a>
                <a href="branches.php" class="list-group-item list-group-item-action"><i class="fas fa-code-branch"></i> Branches</a>
            </div>
        <?php endif; ?>
    </div>
</div>