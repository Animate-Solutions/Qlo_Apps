<div class="bootstrap">
	<nav id="{if $employee->bo_menu}nav-sidebar{else}nav-topbar{/if}" role="navigation">
		{if !$tab}
			<div class="mainsubtablist" style="display:none;"></div>
		{/if}
		<ul class="menu">
			<li class="searchtab">
				{include file="search_form.tpl" id="header_search" show_clear_btn=1}
			</li>
			{foreach $tabs as $t}
				{if $t.active}
				<li class="maintab {if $t.current}active{/if} {if $t.sub_tabs|@count}has_submenu{/if}" id="maintab-{$t.class_name}" data-submenu="{$t.id_tab}">
					<a href="{if $t.sub_tabs|@count && isset($t.sub_tabs[0].href)}{$t.sub_tabs[0].href|escape:'html':'UTF-8'}{else}{$t.href|escape:'html':'UTF-8'}{/if}" class="title" >
						{if $t.class_name == 'AdminPulseCore'}<i class="icon-cogs"></i>
						{elseif $t.class_name == 'AdminPulseLicense'}<i class="icon-key"></i>
						{elseif $t.class_name == 'AdminPulseFdDashboard'}<i class="icon-home"></i>
						{elseif $t.class_name == 'AdminPulsePos'}<i class="icon-cutlery"></i>
						{elseif $t.class_name == 'AdminPulseInventory'}<i class="icon-archive"></i>
						{elseif $t.class_name == 'AdminPulseReports'}<i class="icon-bar-chart"></i>
						{elseif $t.class_name == 'AdminPulseLaundry'}<i class="icon-tint"></i>
						{elseif $t.class_name == 'AdminPulseMaintenance'}<i class="icon-wrench"></i>
						{else}<i class="icon-{$t.class_name}"></i>{/if}
						<span>{if $t.name eq ''}{$t.class_name|escape:'html':'UTF-8'}{else}{$t.name|escape:'html':'UTF-8'}{/if}</span>
					</a>
					{if $t.sub_tabs|@count}
						<ul class="submenu">
						{foreach from=$t.sub_tabs item=t2}
							{if $t2.active}
							<li id="subtab-{$t2.class_name|escape:'html':'UTF-8'}" {if $t2.current} class="active"{/if}>
								<a href="{$t2.href|escape:'html':'UTF-8'}">
									{if $t2.name eq ''}{$t2.class_name|escape:'html':'UTF-8'}{else}{$t2.name|escape:'html':'UTF-8'}{/if}
								</a>
							</li>
							{/if}
						{/foreach}
						</ul>
					{/if}
				</li>
				{/if}
			{/foreach}
		</ul>
		<span class="menu-collapse">
			<i class="icon-align-justify icon-rotate-90"></i>
		</span>
		{hook h='displayAdminNavBarBeforeEnd'}
	</nav>
</div>
