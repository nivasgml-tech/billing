<?php
// print_barcode.php

require_once 'includes/auth.php';
require_once 'config/database.php';


// Barcode print settings are stored separately from per-purchase print quantities.
$barcodeSettingsDefaults = [
    'label_width' => 36,
    'label_height' => 25,
    'barcode_height' => 10,
    'barcode_width' => 1,
    'labels_per_row' => 2,
    'content_fields' => [
        ['id' => 'shop', 'label' => 'Shop Name', 'enabled' => true],
        ['id' => 'name', 'label' => 'Product Name', 'enabled' => true],
        ['id' => 'price', 'label' => 'Price', 'enabled' => true],
        ['id' => 'barcode', 'label' => 'Barcode Image', 'enabled' => true],
        ['id' => 'barcodeId', 'label' => 'Barcode ID', 'enabled' => true],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_barcode_settings') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $allowedFieldIds = ['shop', 'name', 'price', 'barcode', 'barcodeId'];
        $postedFields = json_decode($_POST['content_fields'] ?? '[]', true);
        if (!is_array($postedFields)) {
            throw new RuntimeException('Invalid content settings.');
        }
        $cleanFields = [];
        foreach ($postedFields as $field) {
            if (!is_array($field) || !in_array($field['id'] ?? '', $allowedFieldIds, true)) continue;
            $cleanFields[] = [
                'id' => $field['id'],
                'label' => (string)($field['label'] ?? ''),
                'enabled' => filter_var($field['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        $labelWidth = min(150, max(15, (float)($_POST['label_width'] ?? 36)));
        $labelHeight = min(100, max(10, (float)($_POST['label_height'] ?? 25)));
        $barcodeHeight = min(20, max(5, (float)($_POST['barcode_height'] ?? 10)));
        $barcodeWidth = min(3, max(0.5, (float)($_POST['barcode_width'] ?? 1)));
        $labelsPerRow = min(3, max(1, (int)($_POST['labels_per_row'] ?? 2)));

        $stmt = $pdo->prepare("
            INSERT INTO barcode_print_settings
                (id, label_width, label_height, barcode_height, barcode_width, labels_per_row, content_fields)
            VALUES (1, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                label_width = VALUES(label_width),
                label_height = VALUES(label_height),
                barcode_height = VALUES(barcode_height),
                barcode_width = VALUES(barcode_width),
                labels_per_row = VALUES(labels_per_row),
                content_fields = VALUES(content_fields)
        ");
        $stmt->execute([
            $labelWidth, $labelHeight, $barcodeHeight, $barcodeWidth,
            $labelsPerRow, json_encode($cleanFields, JSON_UNESCAPED_UNICODE)
        ]);
        echo json_encode(['success' => true, 'message' => 'Barcode settings saved.']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not save settings. Confirm the barcode_print_settings table has been created.']);
    }
    exit;
}

$barcodeSavedSettings = $barcodeSettingsDefaults;
try {
    $stmt = $pdo->query("SELECT * FROM barcode_print_settings WHERE id = 1 LIMIT 1");
    $savedRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($savedRow) {
        foreach (['label_width', 'label_height', 'barcode_height', 'barcode_width', 'labels_per_row'] as $key) {
            if (isset($savedRow[$key]) && is_numeric($savedRow[$key])) {
                $barcodeSavedSettings[$key] = (float)$savedRow[$key];
            }
        }
        $savedFields = json_decode($savedRow['content_fields'] ?? '', true);
        if (is_array($savedFields) && count($savedFields)) {
            $allowedFieldIds = ['shop', 'name', 'price', 'barcode', 'barcodeId'];
            $cleanFields = [];
            foreach ($savedFields as $field) {
                if (is_array($field) && in_array($field['id'] ?? '', $allowedFieldIds, true)) {
                    $cleanFields[] = [
                        'id' => $field['id'],
                        'label' => (string)($field['label'] ?? ''),
                        'enabled' => filter_var($field['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];
                }
            }
            if ($cleanFields) $barcodeSavedSettings['content_fields'] = $cleanFields;
        }
    }
} catch (Throwable $e) {
    // Create the barcode_print_settings table once using the SQL instructions.
}

$purchase_id = (int)($_GET['purchase_id'] ?? 0);

if ($purchase_id <= 0) {
    exit('Invalid Purchase ID');
}

$company_name = 'Furniture ERP';

if (isset($settings['shop_name']) && trim($settings['shop_name']) !== '') {
    $company_name = $settings['shop_name'];
}

$stmt = $pdo->prepare("
    SELECT
        pi.product_id,
        pi.quantity,
        pi.unit_price,
        pr.product_name,
        pr.barcode,
        pr.sale_price
    FROM purchase_items pi
    JOIN products pr ON pi.product_id = pr.id
    WHERE pi.purchase_id = ?
");
$stmt->execute([$purchase_id]);

$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$items) {
    exit('No products found for this purchase.');
}

$products = [];

foreach ($items as $item) {
    $products[] = [
        'product_id' => (int)$item['product_id'],
        'name' => $item['product_name'],
        'barcode' => !empty($item['barcode'])
            ? $item['barcode']
            : str_pad((string)$item['product_id'], 8, '0', STR_PAD_LEFT),
        'price' => (
            isset($item['sale_price']) &&
            (float)$item['sale_price'] > 0
        ) ? (float)$item['sale_price'] : (float)$item['unit_price'],
        'quantity' => max(0, (int)$item['quantity'])
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Barcode Print Settings</title>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 20px;
    font-family: Arial, sans-serif;
    background: #f3f5f9;
    color: #1d2939;
}

.container {
    max-width: 1100px;
    margin: auto;
}

h2 {
    margin-top: 0;
}

.settings, .product-list, .preview-box {
    background: #fff;
    border: 1px solid #e0e5ed;
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 18px;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
}

label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 6px;
}

input {
    width: 100%;
    padding: 9px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
}

select {
    width: 100%;
    padding: 9px;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    background: white;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 10px 8px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

th {
    background: #f8fafc;
}

.qty-input {
    max-width: 90px;
}

button {
    border: 0;
    padding: 11px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.primary {
    background: #17365d;
    color: white;
}

.secondary {
    background: #e8edf5;
    color: #1d2939;
}

.actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.preview-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.label-preview {
    border: 1px dashed #9ca3af;
    background: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 2mm;
    text-align: center;
    color: #000;
}

.preview-company {
    font-weight: bold;
    font-size: 9px;
    max-width: 100%;
    overflow: hidden;
    white-space: nowrap;
}

.preview-name {
    font-size: 10px;
    max-width: 100%;
    overflow: hidden;
    white-space: nowrap;
}

.preview-barcode {
    max-width: 100%;
    overflow: hidden;
}

.preview-barcode svg {
    max-width: 100%;
    height: auto;
}

.preview-price {
    font-size: 12px;
    font-weight: bold;
}

.note {
    font-size: 13px;
    color: #667085;
    margin-top: 8px;
}

@media (max-width: 600px) {
    body {
        padding: 10px;
    }

    th, td {
        padding: 7px 4px;
        font-size: 12px;
    }
}

@media print {
    @page {
        size: var(--page-width) var(--label-height);
        margin: 0;
    }

    body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    body * {
        visibility: hidden;
    }

    #printArea,
    #printArea * {
        visibility: visible;
    }

    #printArea {
        position: absolute;
        left: 0;
        top: 0;
        width: var(--page-width);
    }

    .print-row {
        display: flex;
        flex-wrap: nowrap;
        width: var(--page-width);
        height: var(--label-height);
        margin: 0;
        padding: 0;
        page-break-after: always;
        break-after: page;
        overflow: hidden;
    }

    .print-row:last-child {
        page-break-after: auto;
        break-after: auto;
    }

    .print-label {
        flex: 0 0 var(--label-width);
        width: var(--label-width);
        height: var(--label-height);
        margin: 0;
        padding: 1mm;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        page-break-after: auto;
        break-after: auto;
    }

    .print-company {
        font-size: 9px;
        font-weight: bold;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
    }

    .print-name {
        font-size: 10px;
        white-space: nowrap;
        max-width: 100%;
        overflow: hidden;
    }

    .print-barcode {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .print-barcode svg {
        max-width: 100%;
        height: auto;
    }

    .print-price {
        font-size: 12px;
        font-weight: bold;
    }

    .no-print {
        display: none !important;
    }
}
</style>
</head>

<body>
<div class="container no-print">
    <h2>Barcode Print Settings</h2>
    <p>Set your label size and print quantity before printing.</p>

    <div class="settings">

    <div class="settings">
    <h3>Print Content Customization</h3>
    <p>Select the details and arrange their print order.</p>

    <div id="contentSettings"></div>
</div>
        <h3>Label Settings</h3>

        <div class="settings-grid">
            <div>
                <label for="labelWidth">Label Width (mm)</label>
                <input id="labelWidth" type="number" min="15" max="150" step="0.5" value="<?= htmlspecialchars((string)$barcodeSavedSettings['label_width']) ?>">
            </div>

            <div>
                <label for="labelHeight">Label Height (mm)</label>
                <input id="labelHeight" type="number" min="10" max="100" step="0.5" value="<?= htmlspecialchars((string)$barcodeSavedSettings['label_height']) ?>">
            </div>

            <div>
                <label for="barcodeHeight">Barcode Height (mm)</label>
                <input id="barcodeHeight" type="number" min="5" max="20" step="0.5" value="<?= htmlspecialchars((string)$barcodeSavedSettings['barcode_height']) ?>">
            </div>

            <div>
    <label for="barcodeWidth">Barcode Width</label>
    <input
        id="barcodeWidth"
        type="number"
        min="0.5"
        max="3"
        step="0.1"
        value="<?= htmlspecialchars((string)$barcodeSavedSettings['barcode_width']) ?>"
    >
</div>

            <div>
    <label for="labelsPerRow">Labels Per Row</label>
    <select id="labelsPerRow">
        <option value="1" <?= (int)$barcodeSavedSettings['labels_per_row'] === 1 ? 'selected' : '' ?>>1 Label</option>
        <option value="2" <?= (int)$barcodeSavedSettings['labels_per_row'] === 2 ? 'selected' : '' ?>>2 Labels</option>
        <option value="3" <?= (int)$barcodeSavedSettings['labels_per_row'] === 3 ? 'selected' : '' ?>>3 Labels</option>
    </select>
</div>
        </div>

        <p class="note">
            Width and height should match the actual sticker size loaded in your thermal printer.
        </p>
    </div>

    <div class="product-list">
        <h3>Products & Print Quantity</h3>

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Barcode</th>
                    <th>Purchase Qty</th>
                    <th>Print Qty</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $index => $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= htmlspecialchars($product['barcode']) ?></td>
                    <td><?= $product['quantity'] ?></td>
                    <td>
                        <input
                            class="qty-input quantity-input"
                            type="number"
                            min="0"
                            max="5000"
                            step="1"
                            value="<?= $product['quantity'] ?>"
                            data-index="<?= $index ?>"
                            aria-label="Print quantity for <?= htmlspecialchars($product['name']) ?>"
                        >
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="actions">
        <button type="button" class="primary" id="previewButton">Update Preview</button>
        <button type="button" class="secondary" id="saveSettingsButton">Save Settings as Default</button>
        <button type="button" class="secondary" id="printButton">Print Barcodes</button>
        <button type="button" class="secondary" onclick="window.close()">Close Window</button>
    </div>

    <div class="preview-box">
        <h3>Label Preview</h3>
        <div id="previewList" class="preview-list"></div>
        <p class="note">Preview is illustrative; actual output depends on your printer and browser print settings.</p>
    </div>
</div>

<div id="printArea"></div>

<script>
const products = <?= json_encode(
    $products,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const companyName = <?= json_encode(
    $company_name,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

let contentFields = <?= json_encode(
    $barcodeSavedSettings['content_fields'],
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

function renderContentSettings() {
    const container = document.getElementById('contentSettings');
    container.replaceChildren();

    contentFields.forEach((field, index) => {
        const row = document.createElement('div');
        row.style.cssText =
            'display:flex;align-items:center;gap:10px;' +
            'padding:10px 0;border-bottom:1px solid #e5e7eb;';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = field.enabled;
        checkbox.style.width = 'auto';

        checkbox.addEventListener('change', () => {
            field.enabled = checkbox.checked;
            renderPreview();
        });

        const label = document.createElement('span');
        label.textContent = field.label;
        label.style.flex = '1';

        const up = document.createElement('button');
        up.type = 'button';
        up.className = 'secondary';
        up.textContent = 'â†‘';
        up.disabled = index === 0;
        up.addEventListener('click', () => {
            [contentFields[index - 1], contentFields[index]] =
                [contentFields[index], contentFields[index - 1]];

            renderContentSettings();
            renderPreview();
        });

        const down = document.createElement('button');
        down.type = 'button';
        down.className = 'secondary';
        down.textContent = 'â†“';
        down.disabled = index === contentFields.length - 1;
        down.addEventListener('click', () => {
            [contentFields[index + 1], contentFields[index]] =
                [contentFields[index], contentFields[index + 1]];

            renderContentSettings();
            renderPreview();
        });

        row.append(checkbox, label, up, down);
        container.appendChild(row);
    });
}

const widthInput = document.getElementById('labelWidth');
const heightInput = document.getElementById('labelHeight');
const barcodeHeightInput = document.getElementById('barcodeHeight');
const previewList = document.getElementById('previewList');
const printArea = document.getElementById('printArea');

function getSettings() {
    const width = Math.min(
        150,
        Math.max(15, Number(widthInput.value) || 36)
    );

    const height = Math.min(
        100,
        Math.max(10, Number(heightInput.value) || 25)
    );

    const barcodeHeight = Math.min(
        20,
        Math.max(5, Number(barcodeHeightInput.value) || 10)
    );

    const barcodeWidthInput = document.getElementById('barcodeWidth');

    const barcodeWidth = Math.min(
        3,
        Math.max(0.5, Number(barcodeWidthInput.value) || 1)
    );

    return {
        width,
        height,
        barcodeHeight,
        barcodeWidth
    };
}

function getSelectedLabels() {
    const labels = [];

    document.querySelectorAll('.quantity-input').forEach(input => {
        const index = Number(input.dataset.index);
        const quantity = Math.min(5000, Math.max(0, Math.floor(Number(input.value) || 0)));
        const product = products[index];

        if (!product || quantity === 0) return;

        for (let i = 0; i < quantity; i++) {
            labels.push(product);
        }
    });

    return labels;
}

function createLabel(product, settings, isPrint) {
    const label = document.createElement('div');

    label.className = isPrint ? 'print-label' : 'label-preview';
    label.style.width = settings.width + 'mm';
    label.style.height = settings.height + 'mm';

    contentFields.forEach(field => {
        if (!field.enabled) return;

        let element;

        switch (field.id) {
            case 'shop':
                element = document.createElement('div');
                element.className = isPrint
                    ? 'print-company'
                    : 'preview-company';
                element.textContent = companyName;
                break;

            case 'name':
                element = document.createElement('div');
                element.className = isPrint
                    ? 'print-name'
                    : 'preview-name';
                element.textContent = product.name;
                break;

            case 'price':
                element = document.createElement('div');
                element.className = isPrint
                    ? 'print-price'
                    : 'preview-price';
                element.textContent =
                    'Rs. ' + Number(product.price).toFixed(2);
                break;

            case 'barcode':
                element = document.createElement('div');
                element.className = isPrint
                    ? 'print-barcode'
                    : 'preview-barcode';

                const svg = document.createElementNS(
                    'http://www.w3.org/2000/svg',
                    'svg'
                );

                element.appendChild(svg);

                try {
                    JsBarcode(svg, String(product.barcode), {
                        format: 'CODE128',
                        displayValue: false,
                        textMargin: 0,
                        margin: 5,
                        height: Math.max(
                            18,
                            settings.barcodeHeight * 3
                        ),
                        width: settings.barcodeWidth,
                        fontSize: 10
                    });
                } catch (error) {
                    element.textContent = 'Invalid barcode';
                }
                break;

            case 'barcodeId':
                element = document.createElement('div');
                element.className = isPrint
                    ? 'print-name'
                    : 'preview-name';
                element.textContent = String(product.barcode);
                break;
        }

        if (element) {
            label.appendChild(element);
        }
    });

    return label;
}

function renderPreview() {
    const settings = getSettings();
    const columns = Number(
        document.getElementById('labelsPerRow').value
    );

    const labels = getSelectedLabels();

    previewList.replaceChildren();

    previewList.style.display = 'grid';
    previewList.style.gridTemplateColumns =
        `repeat(${columns}, ${settings.width}mm)`;
    previewList.style.gridAutoRows = settings.height + 'mm';
    previewList.style.gap = '0';

    if (labels.length === 0) {
        previewList.textContent =
            'No labels selected. Enter a print quantity above.';
        return;
    }

    labels.slice(0, 30).forEach(product => {
        previewList.appendChild(
            createLabel(product, settings, false)
        );
    });

    if (labels.length > 30) {
        const note = document.createElement('p');
        note.textContent =
            'Showing first 30 labels. Total labels to print: ' +
            labels.length;
        previewList.appendChild(note);
    }
}

function preparePrint() {
    const settings = getSettings();
    const labels = getSelectedLabels();

    const columns = Number(
        document.getElementById('labelsPerRow').value
    );

    if (labels.length === 0) {
        alert('Please select at least one barcode label to print.');
        return;
    }

    if (labels.length > 5000) {
        alert('Please reduce the total number of labels to 5000 or fewer.');
        return;
    }

    const pageWidth = settings.width * columns;

    document.documentElement.style.setProperty(
        '--label-width',
        settings.width + 'mm'
    );

    document.documentElement.style.setProperty(
        '--label-height',
        settings.height + 'mm'
    );

    document.documentElement.style.setProperty(
        '--page-width',
        pageWidth + 'mm'
    );

    printArea.replaceChildren();

    for (let i = 0; i < labels.length; i += columns) {
        const row = document.createElement('div');
        row.className = 'print-row';

        const rowLabels = labels.slice(i, i + columns);

        rowLabels.forEach(product => {
            row.appendChild(
                createLabel(product, settings, true)
            );
        });

        printArea.appendChild(row);
    }

    window.print();
}

document.getElementById('previewButton').addEventListener('click', renderPreview);
document.getElementById('printButton').addEventListener('click', preparePrint);

document.getElementById('saveSettingsButton').addEventListener('click', async () => {
    const button = document.getElementById('saveSettingsButton');
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Saving...';

    const formData = new URLSearchParams({
        action: 'save_barcode_settings',
        label_width: widthInput.value,
        label_height: heightInput.value,
        barcode_height: barcodeHeightInput.value,
        barcode_width: document.getElementById('barcodeWidth').value,
        labels_per_row: document.getElementById('labelsPerRow').value,
        content_fields: JSON.stringify(contentFields)
    });

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: formData.toString()
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to save settings.');
        }
        alert('Barcode settings saved as default.');
    } catch (error) {
        alert(error.message || 'Unable to save settings. Please check the database table.');
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
});

[widthInput, heightInput, barcodeHeightInput, document.getElementById('barcodeWidth'),
 document.getElementById('labelsPerRow')].forEach(input => {
    input.addEventListener('change', renderPreview);
    input.addEventListener('input', renderPreview);
});

document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('change', renderPreview);
});
renderContentSettings();
renderPreview();
</script>
</body>
</html>
