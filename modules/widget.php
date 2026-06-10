<?php if ($nanoNodeAccount): ?>
<?php $accountParam = e(rawurlencode($nanoNodeAccount)); ?>
<?php if ($widgetType == 'qr'): ?>

<!-- QR is generated locally (vendored qrcode-generator), no third-party service -->
<div class="n-widget-qr" style="width:150px; padding:8px; background:#fff; border-radius:8px; margin: 0 0 0 auto;" role="img" aria-label="Account QR code">
  <div id="nodeQr"></div>
</div>
<script src="static/js/qrcode.js?v=<?php echo PROJECT_VERSION; ?>"></script>
<script>
(function () {
  var qr = qrcode(0, 'M');
  qr.addData('nano:' + <?php echo json_encode($nanoNodeAccount); ?>);
  qr.make();
  var el = document.getElementById('nodeQr');
  el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
  var svg = el.firstElementChild;
  svg.style.width = '100%';
  svg.style.height = 'auto';
  svg.style.display = 'block';
})();
</script>

<?php elseif($widgetType == 'natricon'): ?>

<img src="https://natricon.com/api/v1/nano?address=<?php echo $accountParam; ?>" style="max-width:250px; display:block; margin: 0 0 0 auto;" alt="Natricon" />

<?php elseif($widgetType == 'monkey'): ?>

<img src="https://monkey.banano.cc/api/v1/monkey/<?php echo $accountParam; ?>" style="max-width:250px; display:block; margin: 0 0 0 auto;" alt="MonKey" />

<?php elseif($widgetType == 'paw'): ?>

<img src="https://pawnimal.paw.digital/api/v1/nano?address=<?php echo $accountParam; ?>" style="max-width:250px; display:block; margin: 0 0 0 auto;" alt="Pawnimal" />

<?php endif; ?>
<?php endif; ?>
