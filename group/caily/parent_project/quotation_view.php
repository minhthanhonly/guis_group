<?php
require_once('../application/loader.php');

require_once '../application/model/quotation.php';

// Disable caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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

// Get company seal based on sender company name
// Pass quotation model to use its connection
$companySeal = $quotationModel->getCompanySeal();

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
    return "{$year}年{$month}月{$day}日";
}

// Get company seal image path

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
         <!-- jsPDF library for text-based PDF -->
     <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
     <!-- Puppeteer alternative: Use browser's print functionality -->
     <script>
         // Modern approach: Use browser's print to PDF capability
         function downloadPDFModern() {
             // Hide print controls
             const controls = document.querySelector('.print-controls');
             if (controls) controls.style.display = 'none';
             
             // Trigger browser's print dialog with PDF preset
             setTimeout(() => {
                 window.print();
                 // Show controls again
                 setTimeout(() => {
                     if (controls) controls.style.display = 'block';
                 }, 100);
             }, 100);
         }
     </script>
    
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
            font-size: 0.6rem !important;
        }
        .font-9{
            font-size: 0.8rem !important;
        }
        .font-10{
            font-size: 0.9rem !important;
        }
        .font-12{
            font-size: 1rem !important;
        }
        .font-14{
            font-size: 1.2rem !important;
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
            padding: 0px 12px;
            vertical-align: middle;
            color: #000;
            font-weight: normal;
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
            position: relative;
        }


        .quotation-details-text {
            line-height: 1.4;
            color: #000;
        }

        .sender-info {
            color: #000;
            font-size: 0.9rem;
            position: relative;
        }
        .company-seal-image{
            position: absolute;
            top: 10px;
            right: -20px;
            max-width: 120px;
        }

        .sender-info .fw-bold {
            font-weight: bold;
        }

        .quotation-items-table th{
            white-space: nowrap !important;
        }

        .quotation-seal-wrapper{
            float: right;
            border: 2px solid #000;
        }
        .quotation-seal-wrapper td{
            border: 2px solid #000;
            padding: 5px;
        }

        .quotation-seal {
            width: 55px;
            height: 55px;
        }
        .quotation-seal img{
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
         @media print {
             .print-controls {
                 display: none !important;
             }
             
             body {
                 margin: 0;
                 padding: 0;
             }
             
             .quotation-container {
                 box-shadow: none;
                 margin: 0;
                 padding: 15px;
                 width: 100%;
                 max-width: none;
             }
             
          
             
             /* Control table width and layout */
             .table-responsive {
                 overflow: visible !important;
             }
             
             /* Summary tables - fixed width */
             .quotation-summary-table,
             .quotation-details-table {
                 width: 100% !important;
                 table-layout: fixed !important;
                 border-collapse: collapse !important;
             }
             
             .quotation-summary-table td {
                 width: 33.33% !important;
             }
             
             .quotation-details-table td:first-child {
                 width: 35% !important;
             }
             
             .quotation-details-table td:last-child {
                 width: 65% !important;
             }
             
             /* Items table - fixed width with specific column widths */
             .quotation-items-table {
                 width: 100% !important;
                 table-layout: fixed !important;
                 border-collapse: collapse !important;
                 font-size: 8pt !important;
             }
             
             .quotation-items-table th:nth-child(1),
             .quotation-items-table td:nth-child(1) {
                 width: 10%!important;
             }
             
             .quotation-items-table th:nth-child(2),
             .quotation-items-table td:nth-child(2) {
                 width: 30% !important;
             }
             
             .quotation-items-table th:nth-child(3),
             .quotation-items-table td:nth-child(3) {
                 width: 8% !important;
             }
             
             .quotation-items-table th:nth-child(4),
             .quotation-items-table td:nth-child(4) {
                 width: 8% !important;
             }
             
             .quotation-items-table th:nth-child(5),
             .quotation-items-table td:nth-child(5) {
                 width: 8% !important;
             }
             
             .quotation-items-table th:nth-child(6),
             .quotation-items-table td:nth-child(6) {
                 width: 10% !important;
             }
             
             .quotation-items-table th:nth-child(7),
             .quotation-items-table td:nth-child(7) {
                 width: 10% !important;
             }
             
             .quotation-items-table th:nth-child(8),
             .quotation-items-table td:nth-child(8) {
                 width: 20% !important;
             }
             
             /* Prevent table overflow */
             .quotation-items-table th,
             .quotation-items-table td {
                 word-wrap: break-word !important;
                 word-break: break-word !important;
                 padding: 2px 4px !important;
             }
             
             /* Allow notes column to wrap */
             .quotation-items-table th:nth-child(7),
             .quotation-items-table td:nth-child(7) {
                 white-space: normal !important;
                 height: auto !important;
             }
             
             /* Fix Bootstrap columns for print */
             .row {
                 display: flex !important;
                 flex-wrap: nowrap !important;
                 margin: 0 !important;
             }
             
             .col-md-6 {
                 flex: 0 0 50% !important;
                 max-width: 50% !important;
                 width: 50% !important;
                 padding-left: 5px !important;
                 padding-right: 5px !important;
             }
             
             .col-md-12,
             .col-12 {
                 flex: 0 0 100% !important;
                 max-width: 100% !important;
                 width: 100% !important;
                 padding-left: 5px !important;
                 padding-right: 5px !important;
             }
             
             /* Specific layout fixes */
             .text-center {
                 text-align: center !important;
             }
             
             .text-end {
                 text-align: right !important;
             }
             
             .mb-2, .mb-3, .mb-4 {
                 margin-bottom: 8px !important;
             }
             
             .justify-content-center {
                 justify-content: center !important;
             }
             
             .d-flex {
                 display: flex !important;
             }
             
             .align-items-end {
                 align-items: flex-end !important;
             }
             
             .gap-4 {
                 gap: 1rem !important;
             }
             
             /* Company seal positioning fix */
             .quotation-seal {
                 text-align: center !important;
             }
             
             .quotation-seal-wrapper {
                 border-collapse: collapse !important;
                 float: right !important;
             }
             
             .quotation-seal-wrapper td {
                 border: 1px solid #000 !important;
                 padding: 5px !important;
             }
             
             .company-seal {
                 margin: 0 auto !important;
             }

             .font-8{
                font-size: 0.6rem !important;
            }
            .font-9{
                font-size: 0.7rem !important;
            }
            .font-10{
                font-size: 0.8rem !important;
            }
            .font-12{
                font-size: 0.9rem !important;
            }
            .font-14{
                font-size: 1rem !important;
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
                    <div class="font-9"><?php echo str_replace("\n", '<br>', htmlspecialchars($quotation['sender_address'])); ?></div>
                    <?php if ($quotation['sender_contact']): ?>
                    <div class="mb-3 font-12"><?php echo htmlspecialchars($quotation['sender_contact']); ?></div>
                    <?php endif; ?>

                    <div class="quotation-details-text font-9" style="margin-top: 3em;">
                    拝啓　時下ますますご清栄のこととお喜び申し上げます。<br>
                    平素は格別のご高配を賜り厚くお礼申し上げます。<br>
                    下記の通りお見積り申し上げます。　敬具
                    </div>
                </div>

                <!-- Right: Date and quotation info -->
                <div class="col-md-6 text-end">
                    <div class="font-10"><?php echo formatJapaneseDate($quotation['issue_date']); ?></div>
                    <div class="font-10">No. <?php echo htmlspecialchars($quotation['quotation_number']); ?></div>
                    <div class="sender-info text-start" style="margin-left: auto; width: fit-content;">
                        <?php if ($companySeal && !empty($companySeal['image_path'])): ?>
                        <img class="company-seal-image" src="<?php echo htmlspecialchars($companySeal['image_path']); ?>" 
                            alt="会社印" 
                            title="<?php echo htmlspecialchars($companySeal['name']); ?>">
                         <?php endif; ?>
                        <div class="font-10"><?php echo htmlspecialchars($quotation['receiver_company']); ?></div>
                                <?php if ($quotation['receiver_address']): ?>
        <div class="font-9"><?php echo str_replace("\n", '<br>', htmlspecialchars($quotation['receiver_address'])); ?></div>
        <?php endif; ?>
                        <div class="d-flex gap-2 font-9" style="padding-right: 7em;">
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
                    <?php if ($quotation['subject']): ?>
                        <div class="font-12">件名: <?php echo htmlspecialchars($quotation['subject'] ?: ''); ?></div>
                    <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <table class="quotation-seal-wrapper">
                        <tr>
                            <td>
                                <div class="quotation-seal">
                                    
                                </div>
                            </td>
                            <td>
                                <div class="quotation-seal">
                                    <?php if (!empty($quotation['receiver_seal_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($quotation['receiver_seal_path']); ?>" 
                                            alt="担当者印" 
                                            style="width: 60px; height: 60px; display: block; object-fit: contain;">
                                    <?php else: ?>
                                        <div class="company-seal"></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
            </div>
            <!-- Price summary table (center) -->
            <div class="row justify-content-center mb-2">
                <div class="col-md-6">
                    <table class="table table-bordered quotation-summary-table mb-0 font-10">
                        <tbody>
                            <tr>
                                <td class="text-nowrap text-center">税抜価格</td>
                                <td class="text-nowrap text-center">消費税(<?php echo $quotation['tax_rate']; ?>%)</td>
                                <td class="text-nowrap text-center">合計金額</td>
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
                    <table class="table table-bordered quotation-details-table mb-0 font-10">
                        <tbody>
                            <tr>
                                <td>納入期限</td>
                                <td><?php echo htmlspecialchars($quotation['delivery_date'] ?: '調整させていただきます'); ?></td>
                            </tr>
                            <tr>
                                <td>納入場所</td>
                                <td><?php echo htmlspecialchars($quotation['delivery_location'] ?: '設計指定場所'); ?></td>
                            </tr>
                            <tr>
                                <td>取引方法</td>
                                <td><?php echo htmlspecialchars($quotation['payment_method'] ?: '電子手形'); ?></td>
                            </tr>
                            <tr>
                                <td>有効期限</td>
                                <td><?php echo formatJapaneseDate($quotation['valid_until']) ?: '見積提出より30日'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Product details table -->
            <div class="table-responsive">
                <table class="table table-bordered quotation-items-table font-10">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">商品コード</th>
                            <th width="20%">品名</th>
                            <th width="8%">タイプ</th>
                            <th width="8%">数量</th>
                            <th width="8%">単位</th>
                            <th width="12%">単価</th>
                            <th width="12%">金額</th>
                            <th width="22%">備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                            <td><?php echo htmlspecialchars($item['title']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($item['type'] ?? ''); ?></td>
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

</body>
</html>
