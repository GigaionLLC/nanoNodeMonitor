<?php if ($nanoNodeAccount): ?>
<?php $accountParam = e(rawurlencode($nanoNodeAccount)); ?>
<?php if ($widgetType == 'qr'): ?>

<img src="https://qrcode.tec-it.com/API/QRCode?data=nano:<?php echo $accountParam; ?>&amp;choe=UTF-8" style="max-width:150px; display:block; margin: 0 0 0 auto;" alt="Account QR code" />

<?php elseif($widgetType == 'natricon'): ?>

<img src="https://natricon.com/api/v1/nano?address=<?php echo $accountParam; ?>" style="max-width:250px; display:block; margin: 0 0 0 auto;" alt="Natricon" />

<?php elseif($widgetType == 'monkey'): ?>

<img src="https://monkey.banano.cc/api/v1/monkey/<?php echo $accountParam; ?>" style="max-width:250px; display:block; margin: 0 0 0 auto;" alt="MonKey" />

<?php elseif($widgetType == 'paw'): ?>

<img src="https://pawnimal.paw.digital/api/v1/nano?address=<?php echo $accountParam; ?>" style="max-width:250px; display:block; margin: 0 0 0 auto;" alt="Pawnimal" />

<?php endif; ?>
<?php endif; ?>
