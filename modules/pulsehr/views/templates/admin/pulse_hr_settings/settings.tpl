<div class="pulse-hr">
<div class="panel"><h3><i class="icon-mobile-phone"></i> Staff portal</h3>
<p>Staff sign in at <a href="{$ess_url}" target="_blank" rel="noreferrer">{$ess_url}</a> with their staff number and the PIN HR sets on their record. Print the address (or a QR of it) and put it in the staff canteen.</p>
<ul>
<li>The page carries no personal data at all — everything is fetched after a PIN has been exchanged for a signed, short-lived session, and the session decides whose data comes back. An employee id in a request is never allowed to select the subject.</li>
<li>Sign-in failures are counted per staff number <em>and</em> per address; after the configured number in the window, everything from that quarter is refused for the rest of it.</li>
<li>The PIN is hashed with the shop cookie key, the same way the POS does it, so a hotel that wants one PIN for both can have one.</li>
<li>Payslips stay hidden until the PIN is entered a second time, and hide themselves again a few minutes later.</li>
<li>The token lives in the phone's session storage, so closing the tab on a borrowed phone signs the person out.</li>
</ul>
<table class="table table-condensed"><tbody>
{foreach $sections as $name => $s}<tr><td>{$name|replace:'_':' '|ucfirst}</td><td>{if $s.on}<span class="label label-success">on</span>{else}<span class="label label-default">off</span> <span class="muted">{$s.why}</span>{/if}</td></tr>{/foreach}
</tbody></table>
</div>

<div class="panel"><h3>Entrance QR</h3>
<p>Print this code and post it at the staff entrance. A punch that carries it is accepted even when the phone's location is refused or wrong{if !$qr_rotates} — so treat the printed code as a key and reissue it if it walks{else} — it changes every {$qr_rotates} minutes, so a photograph of it goes stale{/if}.</p>
<p><code style="font-size:22px">{$qr_code}</code></p>
<form method="post" class="form-inline"><button name="newQr" class="btn btn-default" data-hr-confirm="Issue a new entrance code? The printed one stops working immediately.">Issue a new code</button></form>
</div>

<div class="panel"><h3>Live portal sessions</h3>
<table class="table table-condensed"><thead><tr><th>Who</th><th>Since</th><th>Last seen</th><th>Expires</th><th>Address</th><th>Device</th><th class="hr-actions"></th></tr></thead><tbody>
{foreach $sessions as $s}<tr><td>{$s.employee_name|escape:'html':'UTF-8'} <span class="muted">{$s.staff_no|escape:'html':'UTF-8'}</span></td><td>{$s.date_add|date_format:"%d/%m %H:%M"}</td><td>{$s.date_upd|date_format:"%H:%M"}</td><td>{$s.expires_at|date_format:"%H:%M"}</td>
<td class="flag">{$s.ip|escape:'html':'UTF-8'}</td><td class="muted">{$s.user_agent|escape:'html':'UTF-8'|truncate:40:'…'}</td>
<td class="hr-actions"><form method="post" class="inline"><input type="hidden" name="id_session" value="{$s.id_pulse_hr_ess_session}"><button name="endSession" class="btn btn-xs btn-default">End</button></form></td></tr>
{foreachelse}<tr><td colspan="7"><em class="muted">Nobody is signed in.</em></td></tr>{/foreach}
</tbody></table>
<form method="post" class="form-inline"><button name="endAllSessions" class="btn btn-default" data-hr-confirm="Sign every member of staff out of the portal?">End every session</button>
<button name="purgeSessions" class="btn btn-default">Purge expired sessions and old sign-in logs</button></form>
</div>

<div class="panel"><h3>Failed sign-ins (last 24 hours)</h3>
{if $fails}<table class="table table-condensed"><thead><tr><th>When</th><th>Staff number tried</th><th>Address</th><th>Why</th><th>Device</th></tr></thead><tbody>
{foreach $fails as $f}<tr><td>{$f.date_add|date_format:"%d/%m %H:%M:%S"}</td><td>{$f.staff_no|escape:'html':'UTF-8'}</td><td class="flag">{$f.ip|escape:'html':'UTF-8'}</td><td>{$f.reason|escape:'html':'UTF-8'}</td><td class="muted">{$f.user_agent|escape:'html':'UTF-8'|truncate:50:'…'}</td></tr>{/foreach}
</tbody></table>{else}<p class="muted">None — good.</p>{/if}
</div>

<div class="panel"><h3>Modules this one talks to</h3>
<table class="table table-condensed"><tbody>
<tr><td>Front Desk</td><td>{if $fd}<span class="label label-success">installed</span> occupancy for roster coverage and the labour KPI comes from the night audit{else}<span class="label label-default">absent</span> occupancy is worked out from bookings instead{/if}</td></tr>
<tr><td>Key Cards</td><td>{if $kc}<span class="label label-success">installed</span> onboarding issues a staff card, clearance revokes every live one{else}<span class="label label-default">absent</span> card tasks stay manual ticks{/if}</td></tr>
<tr><td>POS</td><td>{if $pos}<span class="label label-success">installed</span> onboarding sets a POS PIN, clearance disables the login{else}<span class="label label-default">absent</span>{/if}</td></tr>
<tr><td>Pulse Time</td><td>{if $ta}<span class="label label-success">installed</span> mobile punches are handed over to the attendance engine{else}<span class="label label-default">absent</span> mobile punches are stored here and the portal says biometric history is unavailable{/if}</td></tr>
<tr><td>Pulse Payroll</td><td>{if $pr}<span class="label label-success">installed</span> payslips appear on the portal behind a PIN re-entry{else}<span class="label label-default">absent</span> the portal tells staff payslips are unavailable and why{/if}</td></tr>
</tbody></table>
</div>

<div class="panel"><h3>Cron</h3>
<p>Run once a night, after the night audit: it accrues leave on the configured day, rolls leave whose dates have arrived, restamps document statuses and raises tickets for what has lapsed, warns on tomorrow's coverage, purges expired portal sessions and hands any unsynced mobile punches to Pulse Time.</p>
<pre>{$cron_url|escape:'html':'UTF-8'}</pre>
<pre>php modules/pulsehr/cron/hr.php {$cron_token}</pre>
<form method="post" class="form-inline"><button name="newCronToken" class="btn btn-default" data-hr-confirm="Issue a new cron token? Anything using the old URL stops working.">Issue a new token</button></form>
</div>
</div>
