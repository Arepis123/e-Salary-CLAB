<?php
// $context contains: payer_name, payer_address, receipt_no, paid_at, payment_items, amount_in_words, service_tax_rate, service_tax_amount
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>CLAB Official Receipt</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      font-size: 11px;
      line-height: 1.3;
      background-color:rgb(255, 255, 255);
      padding: 20px;
    }

    .receipt-container {
      max-width: 210mm;
      background: white;
      margin: 0 auto;
      border: 1px solid #000;
    }

    .header {
      display: flex;
      align-items: flex-start;
      padding: 15px;
      border-bottom: 1px solid #000;
    }

    .logo {
      width: 100px;
      height: 80px;
      margin-right: 20px;
      flex-shrink: 0;
    }

    .company-info {
      flex: 1;
    }

    .company-name {
      font-size: 16px;
      font-weight: bold;
      margin-bottom: 8px;
    }

    .company-details {
      font-size: 10px;
      line-height: 1.4;
    }

    .company-details div {
      margin-bottom: 2px;
    }

    .receipt-title {
      text-align: center;
      font-size: 14px;
      font-weight: bold;
      padding: 15px;
      border-bottom: 1px solid #000;
    }

    .receipt-info {
      display: flex;
      justify-content: space-between;
      padding: 10px 15px;
      border-bottom: 1px solid #000;
      align-items: flex-start;
    }

    .to-section {
      flex: 1;
    }

    .receipt-meta {
      text-align: right;
      white-space: nowrap;
      align-self: flex-start;
    }

    .to-name {
      font-weight: bold;
      margin-bottom: 3px;
    }

    .to-phone {
      margin-bottom: 3px;
    }    

    .address-lines {
      line-height: 1.3;
      margin-bottom: 3px;
    }

    .table-container {
      border-bottom: 1px solid #000;
      margin: 15px;
    }

    .transaction-table {
      width: 100%;
      border-collapse: collapse;
    }

    .transaction-table th {
      background-color: #f0f0f0;
      border: 1px solid #000;
      padding: 8px 5px;
      text-align: center;
      font-weight: bold;
      font-size: 10px;
    }

    .transaction-table td {
      border: 1px solid #000;
      padding: 5px;
      font-size: 10px;
      vertical-align: top;
    }

    .description-item {
      margin-bottom: 2px;
    }

    .remarks {
      color: #0066cc;
      font-style: italic;
    }

    .summary-row {
      background-color: #f8f8f8;
    }

    .amount-words {
      padding: 0px 15px 10px;
      border-bottom: 1px solid #000;
      display: flex;
      align-items: center;
    }

    .amount-words-label {
      font-weight: bold;
      margin-right: 10px;
      white-space: nowrap;
    }

    .amount-words-text {
      font-weight: bold;
    }

    .footer-note {
      padding: 10px 15px;
      font-size: 10px;
      color: #666;
    }

    .text-center {
      text-align: center;
    }

    .text-right{
      text-align: right;
    }    
  </style>
</head>

<body>
  <div class="receipt-container">
    <div class="header">
      <div class="logo"><img src="../public/assets/clab_logo.png" alt="CLAB Logo" width="110"></div>
      <div class="company-info">
        <div class="company-name">CONSTRUCTION LABOUR EXCHANGE CENTRE BERHAD (CLAB)</div>
        <div class="company-details">
          <div>Level 2, Annexe Block, Menara Millenium,</div>
          <div>No. 8, Jalan Damanlela, Bukit Damansara, 50490 Kuala Lumpur</div>
          <div>Tel: 03-2095 9559, Fax: 03-2095 9566, Email: info@clab.com.my</div>
          <div>Website: www.clab.com.my</div>
          <div>No Daftar Kastam : W10-1808-32001804</div>
        </div>
      </div>
    </div>

    <div class="receipt-title">OFFICIAL RECEIPT / INVOICE</div>

    <div class="receipt-info"
      style="display: flex; justify-content: space-between; padding: 10px 15px; border-bottom: 1px solid #000; align-items: flex-start;">
      <div class="to-section" style="flex: 1;">
        <div class="to-name"><?= htmlspecialchars($context['payer_roc']) ?></div>
        <div class="to-name"><?= htmlspecialchars($context['payer_name']) ?></div>
        <div class="address-lines" style="line-height: 1.3;"><?= nl2br(htmlspecialchars($context['payer_address'])) ?></div>
        <div class="to-phone"><?= htmlspecialchars($context['payer_phone']) ?></div>
      </div>
      <div class="receipt-meta"
        style="text-align: right; white-space: nowrap; min-width: 160px; align-self: flex-start;">
        <div>Woid : <?= $context['legacy_id'] ?></div></br>
        <div>No. : <?= $context['receipt_no'] ?></div>
        <div style="margin-top: 10px;">Date : <?= date('d-M-Y', strtotime($context['paid_at'])) ?></div>
      </div>
    </div>

    <div class="table-container">
      <table class="transaction-table">
        <thead>
          <tr>
            <th>TRANSACTION NO.</th>
            <th>PAYMENT TO</th>
            <th>DESCRIPTION OF PAYMENT</th>
            <th>FCL</th>
            <th>UNIT PRICE (RM)</th>
            <th>TOTAL (RM)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($context['payment_items'] as $item): ?>
            <tr>
              <td class="transaction-no"><?= $context['transaction_id'] ?></td>
              <td class="payment-to"><?= $item['payment_to'] ?></td>
              <td class="description">
                <?php foreach ($item['description'] as $desc): ?>
                  <div class="description-item"><?= $desc ?></div>
                <?php endforeach; ?>
                <div class="remarks">Remarks</div>
                <div class="description-item"><?= $item['remarks'] ?></div>
              </td>
              <td class="fcl text-center">
                <?php foreach ($item['fcl'] as $fcl): ?>
                  <div><?= $fcl ?></div>
                <?php endforeach; ?>
              </td>
              <td class="unit-price text-right">
                <?php foreach ($item['unit_price'] as $price): ?>
                  <div><?= number_format($price, 2) ?></div>
                <?php endforeach; ?>
              </td>
              <td class="total text-right">
                <?php foreach ($item['total'] as $total): ?>
                  <div><?= number_format($total, 2) ?></div>
                <?php endforeach; ?>
              </td>
            </tr>
          <?php endforeach; ?>

          <tr>
            <td colspan="2"></td>
            <td class="description">Service Tax <?= $context['service_tax_rate'] ?>%</td>
            <td></td>
            <td class="unit-price text-right"><?= number_format($context['admin_fee_total'], 2) ?></td>
            <td class="total text-right"><?= number_format($context['service_tax_amount'], 2) ?></td>
          </tr>
          <tr class="summary-row">
            <td colspan="2"></td>
            <td class="description"><strong>Total Before Service Tax (RM)</strong></td>
            <td></td>
            <td></td>
            <td class="total text-right"><strong><?= number_format($context['amount_before_tax'], 2) ?></strong></td>
          </tr>
          <tr class="summary-row">
            <td colspan="2"></td>
            <td class="description"><strong>Add Service Tax <?= $context['service_tax_rate'] ?>%</strong></td>
            <td></td>
            <td></td>
            <td class="total text-right"><strong><?= number_format($context['service_tax_amount'], 2) ?></strong></td>
          </tr>
          <tr class="summary-row">
            <td colspan="2"></td>
            <td class="description"><strong>Total (RM)</strong></td>
            <td></td>
            <td></td>
            <td class="total text-right"><strong><?= number_format($context['amount'], 2) ?></strong></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="amount-words">
      <span class="amount-words-label">Amount in words (RM):</span>
      <span class="amount-words-text"><?= strtoupper($context['amount_in_words']) ?> RINGGIT MALAYSIA ONLY</span>
    </div>

    <div class="footer-note">
      This is a computer generated receipt, no signature required.
    </div>
  </div>
</body>

</html>