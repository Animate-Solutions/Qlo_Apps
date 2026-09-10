<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="robots" content="noindex,nofollow">
<title>Staff portal — {$hr_hotel|escape:'html':'UTF-8'}</title>
<link rel="stylesheet" href="{$hr_css}">
<script>window.HR_BOOT={$hr_boot nofilter};</script></head>
<body class="hr-ess">
<div id="hr-app">
  <div class="hr-screen hr-center" id="hr-boot">
    <div class="hr-card hr-narrow">
      <h1>{$hr_hotel|escape:'html':'UTF-8'}</h1>
      <p class="hr-muted">Staff portal</p>
      {if !$hr_enabled}<p class="hr-warn">The staff portal is closed at the moment. Please see HR.</p>{else}<p class="hr-muted">Loading…</p>{/if}
    </div>
  </div>
</div>
<div id="hr-toast" class="hr-toast hr-hidden"></div>
<noscript><div class="hr-screen hr-center"><div class="hr-card hr-narrow"><h2>JavaScript is needed</h2><p>The staff portal needs JavaScript switched on in your browser.</p></div></div></noscript>
<script src="{$hr_js}"></script></body></html>
