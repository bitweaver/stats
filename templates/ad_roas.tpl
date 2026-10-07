{function name=roas_money v=0}{if $gCommerceCurrencies}{$gCommerceCurrencies->format($v)}{else}{$v|string_format:"%.2f"}{/if}{/function}
{function name=roas_x v=null}{if $v === null || $v === ''}<span class="text-muted">—</span>{else}{$v|string_format:"%.2f"}x{/if}{/function}
{function name=roas_pct v=null}{if $v === null || $v === ''}<span class="text-muted">—</span>{else}{math equation="v*100" v=$v format="%.0f"}%{/if}{/function}
{function name=roas_num v=null}{if $v === null || $v === ''}<span class="text-muted">—</span>{else}{$v|string_format:"%.2f"}{/if}{/function}
{literal}<script>
(function(){
	function num(td){
		var v = td.getAttribute('data-sort');
		if (v !== null) { v = parseFloat(v); return isNaN(v) ? -Infinity : v; }
		var t = td.textContent.replace(/[^\d.\-]/g, '');
		return t === '' ? -Infinity : parseFloat(t);
	}
	document.addEventListener('click', function(e){
		var th = e.target.closest ? e.target.closest('table.roas-sortable th[data-sortable]') : null;
		if (!th) { return; }
		var table = th.closest('table'), tbody = table.tBodies[0], idx = th.cellIndex;
		var dir = th.getAttribute('data-dir') === 'desc' ? 'asc' : 'desc';
		Array.prototype.forEach.call(table.querySelectorAll('th[data-sortable]'), function(h){ h.removeAttribute('data-dir'); });
		th.setAttribute('data-dir', dir);
		var numeric = th.getAttribute('data-sortable') === 'num';
		var rows = Array.prototype.slice.call(tbody.rows);
		rows.sort(function(a, b){
			if (numeric) { var x = num(a.cells[idx]), y = num(b.cells[idx]); return dir === 'asc' ? x - y : y - x; }
			var s = a.cells[idx].textContent.trim().toLowerCase(), t = b.cells[idx].textContent.trim().toLowerCase();
			return dir === 'asc' ? s.localeCompare(t) : t.localeCompare(s);
		});
		rows.forEach(function(r){ tbody.appendChild(r); });
	});

	// Period select: a fixed period reloads at once; custom reveals the date inputs.
	document.addEventListener('change', function(e){
		if (e.target.id !== 'roas-period') { return; }
		var form = document.getElementById('roas-range-form'), dates = form.querySelector('.roas-custom-dates');
		if (e.target.value === 'custom') { dates.classList.remove('is-hidden'); form.querySelector('#roas-since').focus(); return; }
		form.submit();
	});

	function numAttr(el, k) {
		var td = el.querySelector('td[data-k="' + k + '"]');
		var raw = td ? td.getAttribute('data-sort') : el.getAttribute('data-' + k);
		if (raw === null || raw === '') { return null; }
		var v = parseFloat(raw); return isNaN(v) ? null : v;
	}
	function moneyPrefix(cell) { var m = /^\s*([^\d\-]*)/.exec(cell.textContent || ''); return m ? m[1] : ''; }
	function fmt(v, kind, prefix) {
		if (v === null) { return '\u2014'; }
		if (kind === 'money') { return prefix + v.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
		if (kind === 'x') { return v.toFixed(2) + 'x'; }
		if (kind === 'int') { return Math.round(v).toLocaleString(); }
		return v.toFixed(2);
	}

	// Unticked rows hide; the count becomes a button that shows them again.
	var reveal = {}, recalcs = {};
	function renderCount(el, n, total, key) {
		if (n === total) { el.textContent = n + '/' + total; return; }
		el.textContent = '';
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'btn btn-xs btn-default roas-pick-toggle';
		b.setAttribute('data-key', key);
		b.textContent = n + '/' + total + ' \u00b7 ' + (reveal[key] ? el.getAttribute('data-hide') : el.getAttribute('data-show'));
		el.appendChild(b);
	}
	document.addEventListener('click', function(e){
		var b = e.target.closest ? e.target.closest('.roas-pick-toggle') : null;
		if (!b) { return; }
		var k = b.getAttribute('data-key');
		reveal[k] = !reveal[k];
		if (recalcs[k]) { recalcs[k](); }
	});

	// Pickers attach once the tables exist.
	document.addEventListener('DOMContentLoaded', function(){
	// Campaign picker: unticked rows hide and leave the totals.
	(function(){
		var table = document.getElementById('roas-campaigns'); if (!table) { return; }
		var prefix = null;
		function recalc() {
			var all = Array.prototype.slice.call(table.tBodies[0].rows), picked = [];
			all.forEach(function(r){
				var on = r.querySelector('input.roas-pick').checked;
				r.style.display = (on || reveal.campaigns) ? '' : 'none';
				r.classList.toggle('roas-off', !on);
				if (on) { picked.push(r); }
			});
			Array.prototype.forEach.call(table.tFoot.querySelectorAll('[data-calc]'), function(cell){
				if (prefix === null && cell.getAttribute('data-fmt') === 'money') { prefix = moneyPrefix(cell); }
				var spec = cell.getAttribute('data-calc').split(':'), kind = cell.getAttribute('data-fmt'), out = null, p;
				if (spec[0] === 'sum') { out = 0; picked.forEach(function(r){ var v = numAttr(r, spec[1]); if (v !== null) { out += v; } }); }
				else if (spec[0] === 'div') { p = spec[1].split('/'); var a = 0, b = 0; picked.forEach(function(r){ var x = numAttr(r, p[0]), y = numAttr(r, p[1]); if (x !== null) { a += x; } if (y !== null) { b += y; } }); out = b > 0 ? a / b : null; }
				else if (spec[0] === 'wavg') { p = spec[1].split('*'); var sw = 0, w = 0; picked.forEach(function(r){ var x = numAttr(r, p[0]), y = numAttr(r, p[1]); if (x !== null && y !== null) { sw += x * y; w += y; } }); out = w > 0 ? sw / w : null; }
				var mul = parseFloat(cell.getAttribute('data-mul') || '');
				if (cell.hasAttribute('data-mul')) { out = (out !== null && !isNaN(mul) && mul > 0) ? out * mul : null; }
				cell.textContent = fmt(out, kind, prefix || '');
			});
			if (picked.length === all.length) { reveal.campaigns = false; }
			Array.prototype.forEach.call(document.querySelectorAll('.roas-pick-count'), function(e){ renderCount(e, picked.length, all.length, 'campaigns'); });
			var apply = document.getElementById('roas-pick-apply');
			if (apply) {
				var ids = picked.map(function(r){ return r.getAttribute('data-campaign'); });
				apply.href = apply.getAttribute('data-base') + '&campaigns=' + encodeURIComponent(ids.join(','));
				apply.style.display = (picked.length < all.length && picked.length > 0) ? '' : 'none';
			}
			var master = table.querySelector('input.roas-pick-all');
			if (master) { master.checked = picked.length === all.length; master.indeterminate = picked.length > 0 && picked.length < all.length; }
		}
		recalcs.campaigns = recalc;
		table.addEventListener('change', function(e){
			if (e.target.classList.contains('roas-pick-all')) { Array.prototype.forEach.call(table.querySelectorAll('input.roas-pick'), function(c){ c.checked = e.target.checked; }); }
			if (e.target.classList.contains('roas-pick') || e.target.classList.contains('roas-pick-all')) { recalc(); }
		});
		recalc();
	})();

	// First-touch picker: shares and the total row follow the ticked rows.
	(function(){
		var table = document.getElementById('roas-recon'); if (!table) { return; }
		var prefix = null;
		function recalc() {
			var all = Array.prototype.slice.call(table.tBodies[0].rows), t = {orders: 0, buyers: 0, revenue: 0}, n = 0;
			all.forEach(function(r){ var on = r.querySelector('input.roas-recon-pick').checked; r.style.display = (on || reveal.recon) ? '' : 'none'; r.classList.toggle('roas-off', !on); if (!on) { return; } n++; t.orders += parseFloat(r.getAttribute('data-orders')) || 0; t.buyers += parseFloat(r.getAttribute('data-buyers')) || 0; t.revenue += parseFloat(r.getAttribute('data-revenue')) || 0; });
			all.forEach(function(r){
				var on = r.querySelector('input.roas-recon-pick').checked;
				var rev = parseFloat(r.getAttribute('data-revenue')) || 0, share = (on && t.revenue > 0) ? rev / t.revenue : null;
				var cell = r.querySelector('.roas-recon-share'), bar = r.querySelector('.roas-bar');
				if (cell) { cell.textContent = share === null ? '\u2014' : Math.round(share * 100) + '%'; }
				if (bar) { bar.style.width = share === null ? '0' : (share * 100).toFixed(1) + '%'; }
			});
			var revCell = table.tFoot.querySelector('[data-recon="revenue"]');
			if (prefix === null && revCell) { prefix = moneyPrefix(revCell); }
			table.tFoot.querySelector('[data-recon="orders"]').textContent = fmt(t.orders, 'int');
			table.tFoot.querySelector('[data-recon="buyers"]').textContent = fmt(t.buyers, 'int');
			if (revCell) { revCell.textContent = fmt(t.revenue, 'money', prefix || ''); }
			if (n === all.length) { reveal.recon = false; }
			Array.prototype.forEach.call(table.querySelectorAll('.roas-recon-count'), function(e){ renderCount(e, n, all.length, 'recon'); });
			var master = table.querySelector('input.roas-recon-pick-all');
			if (master) { master.checked = n === all.length; master.indeterminate = n > 0 && n < all.length; }
		}
		recalcs.recon = recalc;
		table.addEventListener('change', function(e){
			if (e.target.classList.contains('roas-recon-pick-all')) { Array.prototype.forEach.call(table.querySelectorAll('input.roas-recon-pick'), function(c){ c.checked = e.target.checked; }); }
			if (e.target.classList.contains('roas-recon-pick') || e.target.classList.contains('roas-recon-pick-all')) { recalc(); }
		});
		recalc();
	})();
	});
})();
</script>{/literal}
<div class="display statistics ad-roas{if $roasCohort} roas-cohort-hl{/if}">
	<div class="header">
		<h1>{tr}Ad ROAS{/tr}
			{if $roasReport}<small>{$roasReport.network_label|escape} · {$roasSince|escape} – {$roasUntil|escape}</small>{/if}
		</h1>
	</div>
	<div class="body">
		{formfeedback hash=$feedback}

		<form class="roas-toolbar" id="roas-range-form" method="get" action="{$smarty.const.STATS_PKG_URL}ad_roas.php">
			{if $roasCampaignFilterStr}<input type="hidden" name="campaigns" value="{$roasCampaignFilterStr|escape}" />{/if}
			<div class="form-group">
				<label class="sr-only" for="roas-period">{tr}Period{/tr}</label>
				<select id="roas-period" class="form-control input-sm" name="period">
					{foreach from=$roasPeriodChoices key=code item=label}
						<option value="{$code}" {if ($roasRange.period and $roasRange.period eq $code) or (!$roasRange.period and $code eq 'custom')}selected="selected"{/if}>{tr}{$label}{/tr}</option>
					{/foreach}
				</select>
			</div>
			<div class="form-group roas-pager">
				{if $roasPrevUrl}<a class="btn btn-default btn-sm" href="{$roasPrevUrl|escape}" title="{tr}Previous{/tr}">{booticon iname="fa-chevron-left"}</a>{else}<span class="btn btn-default btn-sm disabled">{booticon iname="fa-chevron-left"}</span>{/if}
				<span class="roas-range-label">{$roasRange.display|escape}</span>
				{if $roasNextUrl}<a class="btn btn-default btn-sm" href="{$roasNextUrl|escape}" title="{tr}Next{/tr}">{booticon iname="fa-chevron-right"}</a>{else}<span class="btn btn-default btn-sm disabled" title="{tr}Up to today{/tr}">{booticon iname="fa-chevron-right"}</span>{/if}
			</div>
			<div class="form-group roas-custom-dates{if $roasRange.period} is-hidden{/if}">
				<label class="sr-only" for="roas-since">{tr}Since{/tr}</label>
				<input id="roas-since" class="form-control input-sm" type="date" name="since" value="{$roasSince|escape}" />
				<span class="roas-sub">{tr}to{/tr}</span>
				<label class="sr-only" for="roas-until">{tr}Until{/tr}</label>
				<input id="roas-until" class="form-control input-sm" type="date" name="until" value="{$roasUntil|escape}" />
			</div>
			{if $roasNetworks}
				<div class="form-group">
					<label class="sr-only" for="roas-network">{tr}Network{/tr}</label>
					<select id="roas-network" class="form-control input-sm" name="network">
						{foreach from=$roasNetworks key=code item=label}
							<option value="{$code|escape}" {if $roasNetwork eq $code}selected="selected"{/if}>{$label|escape}</option>
						{/foreach}
					</select>
				</div>
			{/if}
			<div class="form-group">
				<button type="submit" class="btn btn-primary btn-sm">{tr}Update{/tr}</button>
				{if $roasReport}
					<a class="btn btn-default btn-sm" href="{$smarty.const.STATS_PKG_URL}ad_roas.php?{$roasBaseQuery}&amp;download=1">{tr}CSV{/tr}</a>
				{/if}
			</div>
		</form>

		{if $roasReport}
			<p class="roas-help">
				{tr}Period revenue pairs with the network's conversion-date value; cohort revenue pairs with its click-dated value. The target is a bid target the network now delivers toward on budget-limited campaigns, not a floor it beats.{/tr}
				<a href="#" onclick="BitBase.toggleElementDisplay('roas-method','block');return false;">{tr}Definitions{/tr}</a>
				·
				<a href="#" onclick="BitBase.toggleElementDisplay('roas-assumptions','block');return false;">{tr}Assumptions{/tr}</a>
				{if $roasReferrersUrl}· <a href="{$roasReferrersUrl|escape}">{tr}Registrations for this period{/tr}</a>{/if}
			</p>

			<div class="box roas-method" id="roas-method" style="display:none">
				<dl class="dl-horizontal">
					<dt>{tr}Spend{/tr}</dt><dd>{tr}The network's cost for clicks dated inside the range.{/tr}</dd>
					<dt>{tr}Period revenue{/tr}</dt><dd>{tr}Paid orders purchased inside the range by customers whose first touch was this campaign, whenever they registered. Compare with the network's conversion-date value.{/tr}</dd>
					<dt>{tr}Cohort revenue{/tr}</dt><dd>{tr}Customers whose first touch (matched click, else registration) is inside the range; their paid orders within the click window of that first touch, even after the range ends. Compare with the network's click-dated value.{/tr}</dd>
					<dt>{tr}LTV{/tr}</dt><dd>{tr}The same cohort's paid orders to date, no cap.{/tr}</dd>
					<dt>{tr}Target{/tr}</dt><dd>{tr}The network's target ROAS, spend-weighted over the daily settings history. Days before the first stored snapshot use the current target.{/tr}</dd>
					<dt>{tr}Value ratio{/tr}</dt><dd>{tr}Network click-dated value divided by cohort revenue: how much the network's value exceeds our books.{/tr}</dd>
					<dt>{tr}Suggested target{/tr}</dt><dd>{tr}Desired commerce ROAS multiplied by the value ratio: the network target that should land on the desired commerce ROAS.{/tr}</dd>
					<dt>{tr}Budget-limited{/tr}</dt><dd>{tr}Search budget-lost impression share of 10% or more, or the network reports the campaign as budget-constrained. These are the campaigns whose delivery now tracks the stated target.{/tr}</dd>
				</dl>
			</div>

			<div class="box roas-assumptions" id="roas-assumptions" style="display:none">
				<form class="form-inline" method="post" action="{$smarty.const.STATS_PKG_URL}ad_roas.php?{$roasBaseQuery}">
					<input type="hidden" name="tk" value="{$gBitUser->mTicket|escape}" />
					<input type="hidden" name="since" value="{$roasSince|escape}" />
					<input type="hidden" name="until" value="{$roasUntil|escape}" />
					<input type="hidden" name="network" value="{$roasNetwork|escape}" />
					<div class="form-group">
						<label for="roas-margin">{tr}Gross margin %{/tr}</label>
						<input id="roas-margin" class="form-control input-sm" type="number" step="0.1" min="0.1" max="100" name="gross_margin_pct" value="{$roasReport.assumptions.gross_margin_pct|escape}" size="6" />
					</div>
					<div class="form-group">
						<label for="roas-desired">{tr}Desired commerce ROAS{/tr}</label>
						<input id="roas-desired" class="form-control input-sm" type="number" step="0.1" min="0.1" name="desired_commerce_roas" value="{$roasReport.assumptions.desired_commerce_roas|escape}" size="6" />
					</div>
					<button type="submit" class="btn btn-default btn-sm" name="save_assumptions" value="1">{tr}Save{/tr}</button>
					{if $roasReport.assumptions.break_even_roas}
						<span class="roas-sub">{tr}Break-even{/tr} {call roas_x v=$roasReport.assumptions.break_even_roas}</span>
					{/if}
				</form>
				<p class="roas-sub">
					{if $roasReport.click_window_days}
						{$roasReport.network_label|escape} {tr}click-through window{/tr}: <strong>{$roasReport.click_window_days|escape} {tr}days{/tr}</strong>
						({if $roasReport.window_source eq 'network'}{tr}from the network's purchase action{/tr}{elseif $roasReport.window_source eq 'user'}{tr}set here{/tr}{else}{tr}default{/tr}{/if})
						· <a href="#" onclick="BitBase.toggleElementDisplay('roas-window','block');return false;">{tr}change{/tr}</a>
					{/if}
					{if $roasReport.tz_warning}
						· <span class="text-warning">{tr}Database session timezone{/tr} {$roasReport.tz_warning.session|escape} {tr}differs from the account timezone{/tr} {$roasReport.tz_warning.account|escape}; {tr}day totals may shift.{/tr}</span>
					{/if}
				</p>
				<form class="form-inline" id="roas-window" method="post" action="{$smarty.const.STATS_PKG_URL}ad_roas.php?{$roasBaseQuery}" {if !$roasWindowAsk}style="display:none"{/if}>
					<input type="hidden" name="tk" value="{$gBitUser->mTicket|escape}" />
					<input type="hidden" name="since" value="{$roasSince|escape}" />
					<input type="hidden" name="until" value="{$roasUntil|escape}" />
					<input type="hidden" name="network" value="{$roasNetwork|escape}" />
					<label for="roas-window-days">{tr}Click-through window (days){/tr}</label>
					<input id="roas-window-days" class="form-control input-sm" type="number" name="click_window_days" min="1" max="90" value="{if $roasReport.click_window_days}{$roasReport.click_window_days|escape}{else}90{/if}" />
					<button type="submit" class="btn btn-default btn-sm" name="save_window" value="1">{tr}Save window{/tr}</button>
				</form>
			</div>

			{if $roasWindowAsk}
				<div class="alert alert-info">
					{tr}The click-through window for{/tr} <strong>{$roasReport.network_label|escape}</strong> {tr}is not stored. Run the warehouse pull (it reads the purchase action's lookback) or save one under Assumptions.{/tr}
				</div>
			{/if}

			{assign var=tot value=$roasReport.totals}
			<div class="roas-tiles">
				<div class="roas-tile is-network">
					<div class="lbl">{tr}Spend{/tr}</div>
					<div class="val">{call roas_money v=$tot.spend}</div>
					<div class="sub">{$tot.clicks} {tr}clicks{/tr} · {tr}CPC{/tr} {call roas_money v=$tot.cpc}</div>
				</div>
				<div class="roas-tile is-network">
					<div class="lbl">{$roasReport.network_label|escape} {tr}ROAS{/tr}</div>
					<div class="val">{call roas_x v=$tot.network_roas}</div>
					<div class="sub">{tr}click-dated{/tr} {call roas_money v=$tot.network_value} · {tr}conversion-dated{/tr} {call roas_x v=$tot.network_roas_conv_date}</div>
				</div>
				<div class="roas-tile is-commerce">
					<div class="lbl">{tr}Commerce ROAS{/tr}</div>
					<div class="val">{call roas_x v=$tot.commerce_roas}</div>
					<div class="sub">{call roas_money v=$tot.period_revenue} · {$tot.period_orders} {tr}orders{/tr} · {tr}AOV{/tr} {call roas_money v=$tot.aov}</div>
				</div>
				<div class="roas-tile is-commerce">
					<div class="lbl">{tr}Cohort ROAS{/tr}{if $roasReport.click_window_days} ({$roasReport.click_window_days}d){/if}</div>
					<div class="val">{call roas_x v=$tot.cohort_roas}</div>
					<div class="sub">{$tot.cohort_users} {tr}new customers{/tr} · {$tot.cohort_buyers} {tr}buyers{/tr} · {tr}CAC{/tr} {call roas_money v=$tot.cac}</div>
				</div>
				<div class="roas-tile is-target">
					<div class="lbl">{tr}Target (spend-weighted){/tr}</div>
					<div class="val">{call roas_x v=$tot.target_roas}</div>
					<div class="sub">
						{if $tot.vs_target eq 'above'}<span class="roas-above">{tr}commerce above target{/tr}</span>{elseif $tot.vs_target eq 'below'}<span class="roas-below">{tr}commerce below target{/tr}</span>{/if}
						{if $tot.budget_limited_campaigns} · {$tot.budget_limited_campaigns} {tr}budget-limited{/tr}{/if}
					</div>
				</div>
				<div class="roas-tile is-target">
					<div class="lbl">{tr}Value ratio{/tr}</div>
					<div class="val">{call roas_num v=$tot.value_ratio}</div>
					<div class="sub">
						{if $tot.suggested_target}{tr}suggested target{/tr} {call roas_x v=$tot.suggested_target}{elseif $roasReport.assumptions.break_even_roas}{tr}break-even{/tr} {call roas_x v=$roasReport.assumptions.break_even_roas}{else}{tr}network value ÷ cohort revenue{/tr}{/if}
					</div>
				</div>
				{if $roasReport.reconciliation}
					{assign var=recon value=$roasReport.reconciliation}
					<div class="roas-tile">
						<div class="lbl">{tr}Attributed share{/tr}</div>
						<div class="val">{call roas_pct v=$recon.rows.0.revenue_share}</div>
						<div class="sub">{tr}of{/tr} {call roas_money v=$recon.total.revenue} {tr}paid in range{/tr} ({$recon.total.orders} {tr}orders{/tr})</div>
					</div>
				{/if}
				<div class="roas-tile">
					<div class="lbl">{tr}Data through{/tr}</div>
					<div class="val roas-val-sm">{if $roasReport.last_metric_date}{$roasReport.last_metric_date|escape|truncate:10:""}{else}—{/if}</div>
					<div class="sub">{tr}pulled{/tr} {if $roasReport.pulled_at}{$roasReport.pulled_at|escape|truncate:16:""}{else}—{/if}{if $roasReport.immature_from} · {tr}maturing since{/tr} {$roasReport.immature_from|escape}{/if}</div>
				</div>
			</div>

			{if $roasReport.reconciliation}
				<h2>{tr}Where paid orders in this range come from{/tr}</h2>
				<table class="table table-condensed roas-recon" id="roas-recon">
					<thead>
						<tr>
							<th class="roas-pick-col"><input type="checkbox" class="roas-recon-pick-all" checked="checked" title="{tr}Select all{/tr}" /></th>
							<th>{tr}First touch{/tr}</th>
							<th class="text-right">{tr}Orders{/tr}</th>
							<th class="text-right">{tr}Buyers{/tr}</th>
							<th class="text-right">{tr}Revenue{/tr}</th>
							<th class="text-right">{tr}Share{/tr}</th>
							<th class="roas-barcell"></th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$recon.rows item=b}
							<tr class="{if $b.is_network}roas-recon-net{/if}" data-orders="{$b.orders}" data-buyers="{$b.buyers}" data-revenue="{$b.revenue}">
								<td class="roas-pick-col"><input type="checkbox" class="roas-recon-pick" checked="checked" /></td>
								<td>{if $b.is_network}<strong>{$roasReport.network_label|escape}:</strong> {/if}{tr}{$b.label}{/tr}</td>
								<td class="text-right">{$b.orders}</td>
								<td class="text-right">{$b.buyers}</td>
								<td class="text-right">{call roas_money v=$b.revenue}</td>
								<td class="text-right roas-recon-share">{call roas_pct v=$b.revenue_share}</td>
								<td class="roas-barcell"><div class="roas-bar" style="width:{math equation="v*100" v=$b.revenue_share format="%.1f"}%"></div></td>
							</tr>
						{/foreach}
					</tbody>
					<tfoot>
						<tr>
							<th></th>
							<th><span class="roas-sub roas-recon-count" data-show="{tr}show all{/tr}" data-hide="{tr}hide unselected{/tr}"></span></th>
							<th class="text-right" data-recon="orders">{$recon.total.orders}</th>
							<th class="text-right" data-recon="buyers">{$recon.total.buyers}</th>
							<th class="text-right" data-recon="revenue">{call roas_money v=$recon.total.revenue}</th>
							<th class="text-right" data-recon="share">100%</th>
							<th></th>
						</tr>
					</tfoot>
				</table>
			{/if}

			{if $roasSvg}
				<h2>{tr}ROAS by{/tr} {tr}{$roasReport.series.bucket}{/tr}</h2>
				<div class="roas-legend">
					<span><span class="roas-swatch sw-commerce"></span>{tr}Commerce ROAS{/tr}</span>
					<span><span class="roas-swatch sw-network"></span>{$roasReport.network_label|escape} {tr}ROAS (click-dated){/tr}</span>
					<span><span class="roas-swatch sw-target"></span>{tr}Target{/tr}</span>
					{if $roasReport.assumptions.break_even_roas}<span><span class="roas-swatch sw-ref"></span>{tr}Break-even{/tr}</span>{/if}
					{if $roasReport.immature_from}<span><span class="roas-swatch sw-band"></span>{tr}Conversions still maturing{/tr}</span>{/if}
					<a href="#" onclick="BitBase.toggleElementDisplay('roas-series-table','block');return false;">{tr}Table{/tr}</a>
				</div>
				{$roasSvg}
				<div id="roas-series-table" style="display:none">
					<table class="table table-condensed roas-table">
						<thead>
							<tr>
								<th>{tr}Bucket{/tr}</th>
								<th class="text-right">{tr}Spend{/tr}</th>
								<th class="text-right">{tr}Clicks{/tr}</th>
								<th class="text-right">{$roasReport.network_label|escape} {tr}value{/tr}</th>
								<th class="text-right">{$roasReport.network_label|escape} {tr}ROAS{/tr}</th>
								<th class="text-right">{tr}Revenue{/tr}</th>
								<th class="text-right">{tr}Orders{/tr}</th>
								<th class="text-right">{tr}Commerce ROAS{/tr}</th>
								<th class="text-right">{tr}Target{/tr}</th>
							</tr>
						</thead>
						<tbody>
							{foreach from=$roasReport.series.rows item=b}
								<tr>
									<td>{$b.bucket|escape|truncate:10:""}</td>
									<td class="text-right">{call roas_money v=$b.spend}</td>
									<td class="text-right">{$b.clicks}</td>
									<td class="text-right">{call roas_money v=$b.network_value}</td>
									<td class="text-right">{call roas_x v=$b.network_roas}</td>
									<td class="text-right">{call roas_money v=$b.revenue}</td>
									<td class="text-right">{$b.orders}</td>
									<td class="text-right">{call roas_x v=$b.commerce_roas}</td>
									<td class="text-right">{call roas_x v=$b.target_roas}</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			{/if}

			<h2>{tr}Campaigns{/tr} <small>({$roasReport.rows|@count})</small></h2>
			<div class="roas-legend">
				<span><span class="roas-swatch sw-network"></span>{$roasReport.network_label|escape} {tr}reports{/tr}</span>
				<span><span class="roas-swatch sw-commerce"></span>{tr}Our books{/tr}</span>
				<span><span class="roas-swatch sw-target"></span>{tr}Gap and target{/tr}</span>
				<span class="roas-sub">{tr}Click a heading to sort. Untick a campaign to drop it from the totals.{/tr}</span>
				<span class="roas-pick-bar">
					<span class="roas-sub"><span class="roas-pick-count" data-show="{tr}show all{/tr}" data-hide="{tr}hide unselected{/tr}"></span> {tr}selected{/tr}</span>
					<a id="roas-pick-apply" class="btn btn-default btn-xs" data-base="{$smarty.const.STATS_PKG_URL}ad_roas.php?{$roasUnfilteredQuery}" href="#" style="display:none">{tr}Tiles and chart for selected{/tr}</a>
					{if $roasCampaignFilter}<a class="btn btn-default btn-xs" href="{$smarty.const.STATS_PKG_URL}ad_roas.php?{$roasUnfilteredQuery}">{tr}All campaigns{/tr}</a>{/if}
				</span>
			</div>
			<div class="table-responsive">
				<table class="table table-condensed table-striped table-hover roas-table roas-sortable roas-campaigns" id="roas-campaigns">
					<thead>
						<tr>
							<th class="roas-pick-col"><input type="checkbox" class="roas-pick-all" checked="checked" title="{tr}Select all{/tr}" /></th>
							<th data-sortable="text">{tr}Campaign{/tr}</th>
							<th data-sortable="text">{tr}Bidding{/tr}</th>
							<th class="text-right grp-net" data-sortable="num">{tr}Spend{/tr}</th>
							<th class="text-right grp-net" data-sortable="num">{tr}Clicks{/tr}</th>
							<th class="text-right grp-net" data-sortable="num">{tr}CPC{/tr}</th>
							<th class="text-right grp-net" data-sortable="num" title="{tr}Conversion value credited to the click date{/tr}">{tr}Value (click){/tr}</th>
							<th class="text-right grp-net" data-sortable="num">{tr}ROAS{/tr}</th>
							<th class="text-right grp-net" data-sortable="num" title="{tr}Conversion value credited to the conversion date{/tr}">{tr}Value (conv.){/tr}</th>
							<th class="text-right grp-net" data-sortable="num" title="{tr}Search impression share lost to budget{/tr}">{tr}Budget lost{/tr}</th>
							<th class="text-right grp-books" data-sortable="num">{tr}Orders{/tr}</th>
							<th class="text-right grp-books" data-sortable="num">{tr}Revenue{/tr}</th>
							<th class="text-right grp-books" data-sortable="num">{tr}Commerce ROAS{/tr}</th>
							<th class="text-right grp-books grp-cohort" data-sortable="num">{tr}New cust.{/tr}</th>
							<th class="text-right grp-books grp-cohort" data-sortable="num">{tr}Cohort rev.{/tr}{if $roasReport.click_window_days} ({$roasReport.click_window_days}d){/if}</th>
							<th class="text-right grp-books grp-cohort" data-sortable="num">{tr}Cohort ROAS{/tr}</th>
							<th class="text-right grp-books" data-sortable="num">{tr}LTV ROAS{/tr}</th>
							<th class="text-right grp-books" data-sortable="num">{tr}CAC{/tr}</th>
							<th class="text-right grp-gap" data-sortable="num">{tr}Target{/tr}</th>
							<th class="text-right grp-gap" data-sortable="num" title="{tr}Network click-dated value ÷ cohort revenue{/tr}">{tr}Value ratio{/tr}</th>
							<th class="text-right grp-gap" data-sortable="num">{tr}Suggested{/tr}</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$roasReport.rows item=row}
							<tr{if $row.campaign_id eq $roasCampaignId} class="info"{/if} data-campaign="{$row.campaign_id|escape}" data-cohort_ltv="{$row.cohort_ltv}" data-cohort_buyers="{$row.cohort_buyers}">
								<td class="roas-pick-col"><input type="checkbox" class="roas-pick" checked="checked" /></td>
								<td>
									<a href="{$smarty.const.STATS_PKG_URL}ad_roas.php?{$roasBaseQuery}&amp;campaign_id={$row.campaign_id|escape}#roas-campaign">{$row.campaign_name|escape}</a>
									<div class="roas-sub"><code>{$row.campaign_id|escape}</code>
										{if $row.channel} {$row.channel|escape|replace:'_':' '|lower}{/if}
										{if $row.status && $row.status ne 'ENABLED'} · {$row.status|escape|lower}{/if}
										{if $row.budget_limited}<span class="label label-warning roas-flag">{tr}budget-limited{/tr}</span>{/if}
										{if $row.spend == 0}<span class="label label-default roas-flag">{tr}no spend in range{/tr}</span>{/if}
									</div>
								</td>
								<td>
									{if $row.bidding_strategy_type}{$row.bidding_strategy_type|escape|replace:'_':' '|lower}{/if}
									{if $row.bidding_scope eq 'portfolio'}<span class="roas-sub">({tr}portfolio{/tr})</span>{/if}
									<div class="roas-sub">{if $row.budget_amount}{call roas_money v=$row.budget_amount}/{tr}day{/tr}{/if}{if $row.target_cpa} · {tr}tCPA{/tr} {call roas_money v=$row.target_cpa}{/if}</div>
								</td>
								<td class="text-right" data-k="spend" data-sort="{$row.spend}">{call roas_money v=$row.spend}</td>
								<td class="text-right" data-k="clicks" data-sort="{$row.clicks}">{$row.clicks}</td>
								<td class="text-right" data-k="cpc" data-sort="{$row.cpc}">{call roas_money v=$row.cpc}</td>
								<td class="text-right" data-k="network_value" data-sort="{$row.network_value}">{call roas_money v=$row.network_value}<div class="roas-sub">{call roas_num v=$row.network_conversions} {tr}conv.{/tr}</div></td>
								<td class="text-right" data-k="network_roas" data-sort="{$row.network_roas}">{call roas_x v=$row.network_roas}</td>
								<td class="text-right" data-k="value_by_conv_date" data-sort="{$row.value_by_conv_date}">{call roas_money v=$row.value_by_conv_date}<div class="roas-sub">{call roas_x v=$row.network_roas_conv_date}</div></td>
								<td class="text-right" data-k="budget_lost_is" data-sort="{$row.budget_lost_is}">{call roas_pct v=$row.budget_lost_is}{if $row.search_is !== null}<div class="roas-sub">{tr}IS{/tr} {call roas_pct v=$row.search_is}</div>{/if}</td>
								<td class="text-right" data-k="period_orders" data-sort="{$row.period_orders}">{$row.period_orders}</td>
								<td class="text-right" data-k="period_revenue" data-sort="{$row.period_revenue}">{call roas_money v=$row.period_revenue}<div class="roas-sub">{tr}AOV{/tr} {call roas_money v=$row.aov}</div></td>
								<td class="text-right" data-k="commerce_roas" data-sort="{$row.commerce_roas}">
									<strong class="{if $row.vs_target eq 'above'}roas-above{elseif $row.vs_target eq 'below'}roas-below{/if}">{call roas_x v=$row.commerce_roas}</strong>
								</td>
								<td class="text-right grp-cohort" data-k="cohort_users" data-sort="{$row.cohort_users}">{$row.cohort_users}<div class="roas-sub">{$row.cohort_buyers} {tr}buyers{/tr}</div></td>
								<td class="text-right grp-cohort" data-k="cohort_revenue" data-sort="{$row.cohort_revenue}">{call roas_money v=$row.cohort_revenue}<div class="roas-sub">{$row.cohort_orders} {tr}orders{/tr}</div></td>
								<td class="text-right grp-cohort" data-k="cohort_roas" data-sort="{$row.cohort_roas}">{call roas_x v=$row.cohort_roas}</td>
								<td class="text-right" data-k="ltv_roas" data-sort="{$row.ltv_roas}">{call roas_x v=$row.ltv_roas}<div class="roas-sub">{call roas_money v=$row.cohort_ltv}</div></td>
								<td class="text-right" data-k="cac" data-sort="{$row.cac}">{call roas_money v=$row.cac}</td>
								<td class="text-right" data-k="target_roas" data-sort="{$row.target_roas}">
									{call roas_x v=$row.target_roas}
									{if $row.target_roas !== null}<div class="roas-sub">{if $row.target_source eq 'history'}{tr}history{/tr}{elseif $row.target_source eq 'mixed'}{tr}partly current{/tr}{else}{tr}current{/tr}{/if}{if $row.target_variants > 1} · {call roas_x v=$row.target_min}–{call roas_x v=$row.target_max}{/if}</div>{/if}
								</td>
								<td class="text-right" data-k="value_ratio" data-sort="{$row.value_ratio}">{call roas_num v=$row.value_ratio}{if $row.value_ratio_period !== null}<div class="roas-sub">{tr}conv.{/tr} {call roas_num v=$row.value_ratio_period}</div>{/if}</td>
								<td class="text-right" data-k="suggested_target" data-sort="{$row.suggested_target}">{call roas_x v=$row.suggested_target}</td>
							</tr>
						{/foreach}
					</tbody>
					<tfoot>
						<tr>
							<th></th>
							<th><span class="roas-sub roas-pick-count" data-show="{tr}show all{/tr}" data-hide="{tr}hide unselected{/tr}"></span></th>
							<th></th>
							<th class="text-right" data-calc="sum:spend" data-fmt="money">{call roas_money v=$tot.spend}</th>
							<th class="text-right" data-calc="sum:clicks" data-fmt="int">{$tot.clicks}</th>
							<th class="text-right" data-calc="div:spend/clicks" data-fmt="money">{call roas_money v=$tot.cpc}</th>
							<th class="text-right" data-calc="sum:network_value" data-fmt="money">{call roas_money v=$tot.network_value}</th>
							<th class="text-right" data-calc="div:network_value/spend" data-fmt="x">{call roas_x v=$tot.network_roas}</th>
							<th class="text-right" data-calc="sum:value_by_conv_date" data-fmt="money">{call roas_money v=$tot.value_by_conv_date}</th>
							<th></th>
							<th class="text-right" data-calc="sum:period_orders" data-fmt="int">{$tot.period_orders}</th>
							<th class="text-right" data-calc="sum:period_revenue" data-fmt="money">{call roas_money v=$tot.period_revenue}</th>
							<th class="text-right" data-calc="div:period_revenue/spend" data-fmt="x">{call roas_x v=$tot.commerce_roas}</th>
							<th class="text-right" data-calc="sum:cohort_users" data-fmt="int">{$tot.cohort_users}</th>
							<th class="text-right" data-calc="sum:cohort_revenue" data-fmt="money">{call roas_money v=$tot.cohort_revenue}</th>
							<th class="text-right" data-calc="div:cohort_revenue/spend" data-fmt="x">{call roas_x v=$tot.cohort_roas}</th>
							<th class="text-right" data-calc="div:cohort_ltv/spend" data-fmt="x">{call roas_x v=$tot.ltv_roas}</th>
							<th class="text-right" data-calc="div:spend/cohort_buyers" data-fmt="money">{call roas_money v=$tot.cac}</th>
							<th class="text-right" data-calc="wavg:target_roas*spend" data-fmt="x">{call roas_x v=$tot.target_roas}</th>
							<th class="text-right" data-calc="div:network_value/cohort_revenue" data-fmt="num">{call roas_num v=$tot.value_ratio}</th>
							<th class="text-right" data-calc="div:network_value/cohort_revenue" data-fmt="x" data-mul="{$roasReport.assumptions.desired_commerce_roas}">{call roas_x v=$tot.suggested_target}</th>
						</tr>
					</tfoot>
				</table>
			</div>

			{if $roasDetail}
				{include file="bitpackage:stats/ad_roas_campaign_inc.tpl"}
			{/if}
		{/if}
	</div>
</div>
