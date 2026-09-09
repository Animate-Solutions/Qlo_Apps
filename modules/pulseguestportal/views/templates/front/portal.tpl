<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>{$gp_hotel|escape:'html':'UTF-8'}</title>
<link rel="stylesheet" href="{$gp_css}">
<style>:root{
  --gp-primary:{$gp_theme.primary|escape:'html':'UTF-8'};
  --gp-cream:{$gp_theme.cream|escape:'html':'UTF-8'};
  --gp-accent:{$gp_theme.accent|escape:'html':'UTF-8'};
  --gp-sand:{$gp_theme.sand|escape:'html':'UTF-8'};
  --gp-display:{$gp_theme.font_display nofilter};
  --gp-body:{$gp_theme.font_body nofilter};
}</style>
<script>window.GP_BOOT={$gp_boot nofilter};window.GP_LOGO="{$gp_logo|escape:'javascript'}";</script></head>
<body class="gp">
<div id="gp-app"><div class="gp-splash"><div class="gp-splash-inner">{if $gp_logo}<img src="{$gp_logo}" alt="" class="gp-logo">{/if}<h1>{$gp_hotel|escape:'html':'UTF-8'}</h1><p class="gp-muted">Starting your screen…</p></div></div></div>
<div id="gp-toast" class="gp-toast gp-hidden"></div>
<div id="gp-modal" class="gp-modal gp-hidden"><div class="gp-modal-box"><div class="gp-modal-head"><span id="gp-modal-title"></span><button class="gp-btn gp-ghost" data-gp-close="1">Close</button></div><div id="gp-modal-body"></div></div></div>
<div id="gp-offline" class="gp-offline gp-hidden">Showing saved hotel information — the screen will catch up when the link is back</div>
<script src="{$gp_js}"></script></body></html>
