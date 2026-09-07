<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"><title>Pulse POS</title><link rel="stylesheet" href="{$pos_css}">
<script>window.POS_CFG={$pos_cfg nofilter};window.POS_OUTLETS={$pos_outlets nofilter};window.POS_API="{$pos_api nofilter}";window.POS_CUR="{$pos_currency}";window.POS_HOTEL="{$pos_hotel|escape:'javascript'}";window.POS_SW="{$pos_sw nofilter}";</script></head>
<body class="pos"><div id="app"><div class="screen center"><h1>Pulse POS</h1><p>Loading…</p></div></div>
<div id="modal" class="modal hidden"><div class="modal-box"><div class="modal-head"><span id="modal-title"></span><button class="btn ghost" onclick="POS.closeModal()">✕</button></div><div id="modal-body"></div></div></div>
<div id="toast" class="toast hidden"></div>
<script src="{$pos_js}"></script></body></html>
