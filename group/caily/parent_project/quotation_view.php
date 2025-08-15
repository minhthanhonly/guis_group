<?php
require_once('../application/loader.php');

require_once '../application/model/quotation.php';
// Simple authentication check
if (!isset($_SESSION['userid'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$quotationId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$quotationId) {
    http_response_code(400);
    exit('Invalid quotation ID');
}

// Get quotation data
$quotationModel = new Quotation();
$result = $quotationModel->getById($quotationId);

if (!$result || empty($result['data'])) {
    http_response_code(404);
    exit('Quotation not found');
}

$quotation = $result['data'];

// Items are already included in getById result
$items = isset($quotation['items']) ? $quotation['items'] : [];

// Helper functions
function formatPrice($amount) {
    return number_format($amount, 0, '.', ',');
}

function formatNumber($number) {
    return number_format($number, 0, '.', ',');
}

function formatDate($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    return $date->format('Y年m月d日');
}

function formatJapaneseDate($dateString) {
    if (!$dateString) return '';
    $date = new DateTime($dateString);
    $year = $date->format('Y');
    $month = $date->format('n');
    $day = $date->format('j');
    return "令和{$year}年{$month}月{$day}日";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>見積書 - <?php echo htmlspecialchars($quotation['quotation_number']); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- html2pdf library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <style>
        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif;
            background-color: #ffffff;
            margin: 0;
            padding: 20px;
        }
        
        .quotation-container {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20px;
        }
        .font-8{
            font-size: 0.6rem;
        }
        .font-9{
            font-size: 0.8rem;
        }
        .font-10{
            font-size: 0.9rem;
        }
        .font-12{
            font-size: 1rem;
        }
        .font-14{
            font-size: 1.2rem;
        }

        
        .print-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .quotation-document {
            font-family: 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif;
            line-height: 1.6;
            background-color: #ffffff;
            color: #000;
        }

        .quotation-document h2 {
            font-size: 1.8rem;
            font-weight: bold;
            color: #000;
            border-bottom: 3px solid #000;
            display: inline-block;
            padding-bottom: 5px;
        }

        .quotation-document h5 {
            font-size: 1.2rem;
            font-weight: bold;
            color: #000;
            margin-bottom: 0.5rem;
        }

        /* Summary tables styling */
        .quotation-summary-table,
        .quotation-details-table {
            font-size: 0.9rem;
            border: 2px solid #000 !important;
            background-color: #ffffff;
        }

        .quotation-summary-table th,
        .quotation-summary-table td,
        .quotation-details-table th,
        .quotation-details-table td {
            border: 1px solid #000 !important;
            padding: 8px 12px;
            vertical-align: middle;
            color: #000;
            background-color: #ffffff;
        }

        .quotation-summary-table .fw-bold,
        .quotation-details-table .fw-bold {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        /* Items table styling */
        .quotation-items-table {
            font-size: 0.85rem;
            border: 2px solid #000 !important;
            background-color: #ffffff;
        }

        .quotation-items-table th {
            background-color: #f8f9fa !important;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #000 !important;
            color: #000;
            padding: 8px 4px;
        }

        .quotation-items-table td {
            vertical-align: middle;
            border: 1px solid #000 !important;
            color: #000;
            background-color: #ffffff;
            padding: 6px 4px;
        }

        .quotation-items-table .table-secondary {
            background-color: #f8f9fa !important;
        }

        .quotation-items-table .table-secondary td {
            background-color: #f8f9fa !important;
            font-weight: bold;
        }

        /* Company seal styling */
        .company-seal {
            width: 60px;
            height: 60px;
            border: 2px solid #d32f2f;
            border-radius: 50%;
            background-color: #ffffff;
            position: relative;
        }

        .company-seal::after {
            content: "印";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #d32f2f;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .quotation-details-text {
            line-height: 1.4;
            color: #000;
        }

        .sender-info {
            color: #000;
            font-size: 0.9rem;
        }

        .sender-info .fw-bold {
            font-weight: bold;
        }
        
        @media print {
            .print-controls {
                display: none !important;
            }
            
            body {
                margin: 0;
                padding: 0;
            }
            .font-8{
            font-size: 0.5rem;
            }
            .font-9{
                font-size: 0.7rem;
            }
            .font-10{
                font-size: 0.8rem;
            }
            .font-12{
                font-size: 0.9rem;
            }
            .font-14{
                font-size: 1rem;
            }
            
            
            @page {
                margin: 10mm;
                size: A4;
            }
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="print-controls">
        <button class="btn btn-success btn-sm me-2" onclick="downloadPDF()">
            <i class="fa fa-download me-1"></i> PDF Download
        </button>
        <button class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="fa fa-print me-1"></i> Print
        </button>
    </div>

    <!-- Quotation Content -->
    <div class="quotation-container" id="quotation-content">
        <div class="quotation-document">
            <!-- Header with title centered -->
            <div class="text-center mb-4">
                <h2 class="fw-bold">御見積書</h2>
            </div>

            <!-- Company info and date/number section -->
            <div class="row mb-2">
                <!-- Left: Company info -->
                <div class="col-md-6">
                    <div class="font-12"><?php echo htmlspecialchars($quotation['sender_company']); ?></div>
                    <div class="font-10"><?php echo htmlspecialchars($quotation['sender_address']); ?></div>
                    <?php if ($quotation['sender_contact']): ?>
                    <div class="mb-3 font-12"><?php echo htmlspecialchars($quotation['sender_contact']); ?></div>
                    <?php endif; ?>

                    <div class="quotation-details-text font-9">
                        詳細 時下ますます清栄のことと存じます。<br>
                        さて依拠提出したお見積書を送付申し上げます。ご査収<br>
                        下記の通りお見積申し上げます。敬具
                    </div>
                </div>

                <!-- Right: Date and quotation info -->
                <div class="col-md-6 text-end">
                    <div class="font-10"><?php echo formatJapaneseDate($quotation['issue_date']); ?></div>
                    <div class="font-10">No. <?php echo htmlspecialchars($quotation['quotation_number']); ?></div>
                    <div class="sender-info text-start" style="margin-left: auto; width: fit-content;">
                        <div class="font-10"><?php echo htmlspecialchars($quotation['receiver_company']); ?></div>
                        <?php if ($quotation['receiver_address']): ?>
                        <div class="font-9"><?php echo htmlspecialchars($quotation['receiver_address']); ?></div>
                        <?php endif; ?>
                        <div class="d-flex gap-4 font-9">
                            <?php if ($quotation['receiver_tel']): ?>
                            <div >TEL　<?php echo htmlspecialchars($quotation['receiver_tel']); ?></div>
                            <?php endif; ?>
                            <?php if ($quotation['receiver_fax']): ?>
                            <div >FAX　<?php echo htmlspecialchars($quotation['receiver_fax']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($quotation['receiver_registration_number']): ?>
                        <div class="font-9">登録番号　<?php echo htmlspecialchars($quotation['receiver_registration_number']); ?></div>
                        <?php endif; ?>
                        <div class="font-9">担当　<?php echo htmlspecialchars($quotation['receiver_contact']); ?></div>
                        
                    </div>
                </div>
            </div>
            <div class="row mb-2">
                <div class="col-md-6 d-flex align-items-end">
                    <div class="quotation-subject">
                        <div class="font-12">件名: <?php echo htmlspecialchars($quotation['subject'] ?: ''); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="quotation-seal">
                         <div class="company-seal" style="margin-left: auto;"></div>
                    </div>
                </div>
            </div>
            <!-- Price summary table (center) -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-6">
                    <table class="table table-bordered quotation-summary-table">
                        <tbody>
                            <tr>
                                <td class="fw-bold">税抜価格</td>
                                <td class="fw-bold">消費税(<?php echo $quotation['tax_rate']; ?>%)</td>
                                <td class="fw-bold">合計金額</td>
                            </tr>
                            <tr>
                                <td class="text-center">¥<?php echo formatPrice($quotation['total_amount']); ?></td>
                                <td class="text-center">¥<?php echo formatPrice($quotation['total_amount'] * $quotation['tax_rate'] / 100); ?></td>
                                <td class="text-center">¥<?php echo formatPrice($quotation['total_with_tax']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Right side details -->
                <div class="col-md-6">
                    <table class="table table-bordered quotation-details-table">
                        <tbody>
                            <tr>
                                <td class="fw-bold">納入期限</td>
                                <td><?php echo htmlspecialchars($quotation['delivery_date'] ?: '調整させていただきます'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">納入場所</td>
                                <td><?php echo htmlspecialchars($quotation['delivery_location'] ?: '設計指定場所'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">取引方法</td>
                                <td><?php echo htmlspecialchars($quotation['payment_method'] ?: '電子手形'); ?></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">有効期限</td>
                                <td><?php echo formatDate($quotation['valid_until']) ?: '見積提出より30日'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Product details table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered quotation-items-table">
                    <thead class="table-light">
                        <tr>
                            <th width="8%">商品コード</th>
                            <th width="25%">品名</th>
                            <th width="8%">数量</th>
                            <th width="8%">単位</th>
                            <th width="12%">単価</th>
                            <th width="12%">金額</th>
                            <th width="27%">備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-center"><?php echo formatNumber($item['quantity']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td class="text-end"><?php echo formatPrice($item['unit_price']); ?></td>
                            <td class="text-end"><?php echo formatPrice($item['amount']); ?></td>
                            <td><?php echo htmlspecialchars($item['notes']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php 
                        // Add empty rows to match document style
                        $emptyRows = max(0, 15 - count($items));
                        for ($i = 0; $i < $emptyRows; $i++): 
                        ?>
                        <tr>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                        </tr>
                        <?php endfor; ?>
                        
                        <!-- Total row -->
                        <tr class="table-secondary">
                            <td colspan="5" class="text-end fw-bold">合計</td>
                            <td class="text-end fw-bold">¥<?php echo formatPrice($quotation['total_amount']); ?></td>
                            <td>&nbsp;</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('quotation-content');
            const opt = {
                margin: [10, 10, 10, 10],
                filename: '見積書_<?php echo htmlspecialchars($quotation['quotation_number']); ?>_<?php echo date('Y-m-d'); ?>.pdf',
                image: { type: 'jpeg', quality: 1 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    letterRendering: true
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                }
            };

            // Hide print controls before generating PDF
            const controls = document.querySelector('.print-controls');
            controls.style.display = 'none';

            html2pdf().set(opt).from(element).save().then(() => {
                // Show controls again after PDF generation
                controls.style.display = 'block';
            });
        }
    </script>
</body>
</html>
