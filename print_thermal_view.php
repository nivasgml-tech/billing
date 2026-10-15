<?php
// print_thermal_view.php
// Expected variables from print_invoice.php: $sale, $items, $settings
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Thermal Receipt <?= htmlspecialchars($sale['invoice_no']) ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            margin: 0;
            padding: 0;
            width: 80mm;
            color: #000;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .left {
            text-align: left;
        }

        .bold {
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 4px 0;
            font-size: 11px;
            vertical-align: top;
        }

        .border-top {
            border-top: 1px dashed #000;
        }

        .border-bottom {
            border-bottom: 1px dashed #000;
        }

        @media print {
            @page {
                margin: 0;
                width: 80mm;
            }

            body {
                margin: 0;
                padding: 2mm !important;
            }

            .no-print {
                display: none !important;
            }
        }
        
/* Thermal receipt typography settings */
body {
    font-size: 12px;
    font-weight: 400;
    line-height: 1.35;
    font-weight:bold;
}

.shop-title {
    font-size: 16px;
    font-weight: 700;
    font-weight:bold;
}

.invoice-details {
    font-size: 12px;
    font-weight: 400;
    font-weight:bold;
}

table th,
table td {
    font-size: 12px;
    font-weight: 400;
    font-weight:bold;
}

.total-row td {
    font-size: 14px;
    font-weight: 700;
    font-weight:bold;
}

.footer-messages {
    font-size: 11px;
    font-weight: 400;
    line-height: 1.4;
    font-weight:bold;
}

    </style>
</head>

<body>
    <div style="padding: 5mm;">
        <div class="center bold shop-title" style="margin-bottom: 2px; text-transform: uppercase;">
            <?= isset($settings['shop_name']) ? htmlspecialchars($settings['shop_name']) : 'Furniture ERP' ?>
        </div>
        <div class="center" style="font-size: 11px;">
            <?= isset($settings['shop_address']) ? nl2br(htmlspecialchars($settings['shop_address'])) : '' ?>
        </div>
        <?php if (!empty($settings['shop_contact'])): ?>
            <div class="center" style="font-size: 11px;">Ph: <?= htmlspecialchars($settings['shop_contact']) ?></div>
        <?php endif; ?>
        <?php if (!empty($settings['gst_number'])): ?>
            <div class="center border-bottom" style="padding-bottom: 5px; margin-bottom: 5px; font-size: 11px;">
                GSTIN: <?= htmlspecialchars($settings['gst_number']) ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 5px;">
            <div class="bold">Inv No: <?= htmlspecialchars($sale['invoice_no']) ?></div>
            <div>Date: <?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></div>
        </div>

        <div class="border-bottom" style="padding-bottom: 5px; margin-bottom: 5px;">
            <span class="bold">To:</span> <?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?><br>
            <?= $sale['cus_phone'] ? 'Ph: ' . htmlspecialchars($sale['cus_phone']) : '' ?>
        </div>

        <table style="margin-bottom: 5px;">
            <thead class="border-bottom">
                <tr>
                    <th class="left" style="width: 50%;">Item</th>
                    <th class="center" style="width: 15%;">Qty</th>
                    <th class="right" style="width: 35%;">Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="left">
                            <?= htmlspecialchars($item['product_name']) ?><br>
                            <small>@ <?= number_format($item['unit_price'], 2) ?></small>
                        </td>
                        <td class="center"><?= floatval($item['quantity']) ?></td>
                        <td class="right"><?= number_format($item['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="border-top" style="padding-top: 5px; margin-bottom: 5px;">
            <table style="width: 100%;">
                <tr>
                    <td class="left">Sub Total:</td>
                    <td class="right"><?= number_format($sale['sub_total'], 2) ?></td>
                </tr>
                <?php if ($sale['discount_amount'] > 0): ?>
                    <tr>
                        <td class="left">Discount:</td>
                        <td class="right">-<?= number_format($sale['discount_amount'], 2) ?></td>
                    </tr>
                <?php endif; ?>
                <?php if ($sale['is_gst_bill'] && $sale['tax_amount'] > 0): ?>
                    <?php $half_tax = $sale['tax_amount'] / 2; ?>
                    <tr>
                        <td class="left">CGST:</td>
                        <td class="right">+<?= number_format($half_tax, 2) ?></td>
                    </tr>
                    <tr>
                        <td class="left">SGST:</td>
                        <td class="right">+<?= number_format($half_tax, 2) ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ($sale['round_off'] != 0): ?>
                    <tr>
                        <td class="left">Round Off:</td>
                        <td class="right"><?= number_format($sale['round_off'], 2) ?></td>
                    </tr>
                <?php endif; ?>

                <tr class="border-top total-row">
                    <td class="left" style="padding: 5px 0;">Total (<?= htmlspecialchars($sale['payment_mode']) ?>):</td>
                    <td class="right" style="padding: 5px 0;">₹<?= number_format($sale['grand_total'], 2) ?></td>
                </tr>
                <!-- <tr class="bold">
                    <td class="left" style="padding: 5px 0;">Advance Paid:</td>
                    <td class="right" style="padding: 5px 0;">₹<?= number_format($sale['paid_amount'], 2) ?></td>
                </tr>
                <tr class="border-bottom bold">
                    <td class="left" style="padding: 5px 0;">Balance Due:</td>
                    <td class="right" style="padding: 5px 0;">₹<?= number_format($sale['grand_total'] - $sale['paid_amount'], 2) ?></td>
                </tr> -->
            </table>
        </div>

       
<?php
$footerText = trim($settings['invoice_footer'] ?? '');

$footerLines = preg_split("/\r\n|\r|\n/", $footerText);
$footerLines = array_filter(
    array_map('trim', $footerLines),
    function ($line) {
        return $line !== '';
    }
);
?>

<?php if (!empty($footerLines)): ?>
    <div class="center footer-messages" style="margin-top: 10px;">
        <?php foreach ($footerLines as $line): ?>
            <div style="margin-bottom: 2px;">
                <?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

        
    </div>

    <script>
(function () {
    const params = new URLSearchParams(window.location.search);

    // Print only when POS explicitly requests thermal printing.
    if (params.get('autoprint') !== '1') {
        return;
    }

    window.addEventListener('afterprint', function () {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage(
                { type: 'thermal-print-finished' },
                window.location.origin
            );
        }

        window.close();
    }, { once: true });

    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 300);
    }, { once: true });
})();
</script>
</body>

</html>