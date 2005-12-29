{* $Header: /cvsroot/bitweaver/_bit_stats/templates/stats.tpl,v 1.7 2005/12/29 18:27:48 squareing Exp $ *}
<div class="display statistics">
	<div class="header">
		<h1>{tr}Stats{/tr}</h1>
	</div>

	<div class="body">
		<div class="navbar">
			<ul>
				<li><a href="#site_stats">{tr}Site{/tr}</a></li>
				{if $wiki_stats}<li><a href="#wiki_stats">{tr}Wiki{/tr}</a></li>{/if}
				{if $igal_stats}<li><a href="#igal_stats">{tr}Image galleries{/tr}</a></li>{/if}
				{if $fgal_stats}<li><a href="#fgal_stats">{tr}File galleries{/tr}</a></li>{/if}
				{if $cms_stats}<li><a href="#cms_stats">{tr}Articles{/tr}</a></li>{/if}
				{if $forum_stats}<li><a href="#forum_stats">{tr}Forums{/tr}</a></li>{/if}
				{if $blog_stats}<li><a href="#blog_stats">{tr}Weblogs{/tr}</a></li>{/if}
				{if $poll_stats}<li><a href="#poll_stats">{tr}Polls{/tr}</a></li>{/if}
				{if $faq_stats}<li><a href="#faq_stats">{tr}FAQs{/tr}</a></li>{/if}
				{if $user_stats}<li><a href="#user_stats">{tr}Users{/tr}</a></li>{/if}
				{if $quiz_stats}<li><a href="#quiz_stats">{tr}Quizzes{/tr}</a></li>{/if}
			</ul>
		</div>

		<a name="site_stats"></a>
		<table class="clear data">
			<caption>{tr}Site Statistics{/tr}</caption>
			<tr><th colspan="2">{tr}Global Stats{/tr}</th></tr>
			<tr class="{cycle values="odd,even"}"><td>{tr}Started{/tr}</td><td style="text-align:right;">{$site_stats.started|bit_short_date}</td></tr>
			<tr class="{cycle}"><td>{tr}Days online{/tr}</td><td style="text-align:right;">{$site_stats.days}</td></tr>
			<tr class="{cycle}"><td>{tr}Total pageviews{/tr}</td><td style="text-align:right;">{$site_stats.pageviews}</td></tr>
			<tr class="{cycle}"><td>{tr}Average pageviews per day{/tr}</td><td style="text-align:right;">{$site_stats.ppd|string_format:"%.2f"}</td></tr>
			<tr class="{cycle}"><td>{tr}Best day{/tr}</td><td style="text-align:right;">{$site_stats.bestday|bit_short_date} ({$site_stats.bestpvs} {tr}pvs{/tr})</td></tr>
			<tr class="{cycle}"><td>{tr}Worst day{/tr}</td><td style="text-align:right;">{$site_stats.worstday|bit_short_date} ({$site_stats.worstpvs} {tr}pvs{/tr})</td></tr>
			<tr><td colspan="2">&nbsp;</td></tr>
			<!-- Site stats -->

			<!-- Wiki Stats -->
			{if $wiki_stats}
				<tr><th colspan="2"><a name="wiki_stats"></a>{tr}Wiki Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Wiki Pages{/tr}</td><td style="text-align:right;">{$wiki_stats.pages}</td></tr>
				<tr class="{cycle}"><td>{tr}Size of Wiki Pages{/tr}</td><td style="text-align:right;">{$wiki_stats.size} {tr}Mb{/tr}</td></tr>
				<tr class="{cycle}"><td>{tr}Average page length{/tr}</td><td style="text-align:right;">{$wiki_stats.bpp|string_format:"%.2f"} {tr}bytes{/tr}</td></tr>
				<tr class="{cycle}"><td>{tr}Versions{/tr}</td><td style="text-align:right;">{$wiki_stats.versions}</td></tr>
				<tr class="{cycle}"><td>{tr}Average versions per page{/tr}</td><td style="text-align:right;">{$wiki_stats.vpp|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Visits to wiki pages{/tr}</td><td style="text-align:right;">{$wiki_stats.visits}</td></tr>
				<tr class="{cycle}"><td>{tr}Orphan pages{/tr}</td><td style="text-align:right;">{$wiki_stats.orphan}</td></tr>
				<tr class="{cycle}"><td>{tr}Average links per page{/tr}</td><td style="text-align:right;">{$wiki_stats.lpp|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Wiki Stats -->

			<!-- Image gallleries stats -->
			{if $igal_stats}
				<tr><th colspan="2"><a name="igal_stats"></a>{tr}Image galleries Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Galleries{/tr}</td><td style="text-align:right;">{$igal_stats.galleries}</td></tr>
				<tr class="{cycle}"><td>{tr}Images{/tr}</td><td style="text-align:right;">{$igal_stats.images}</td></tr>
				<tr class="{cycle}"><td>{tr}Average images per gallery{/tr}</td><td style="text-align:right;">{$igal_stats.ipg|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Total size of images{/tr}</td><td style="text-align:right;">{$igal_stats.size} {tr}Mb{/tr}</td></tr>
				<tr class="{cycle}"><td>{tr}Average image size{/tr}</td><td style="text-align:right;">{$igal_stats.bpi|string_format:"%.2f"} {tr}bytes{/tr}</td></tr>
				<tr class="{cycle}"><td>{tr}Visits to image galleries{/tr}</td><td style="text-align:right;">{$igal_stats.visits}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Image gallleries stats -->

			<!-- File gallleries stats -->
			{if $fgal_stats}
				<tr><th colspan="2"><a name="fgal_stats"></a>{tr}File galleries Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Galleries{/tr}</td><td style="text-align:right;">{$fgal_stats.galleries}</td></tr>
				<tr class="{cycle}"><td>{tr}Files{/tr}</td><td style="text-align:right;">{$fgal_stats.files}</td></tr>
				<tr class="{cycle}"><td>{tr}Average files per gallery{/tr}</td><td style="text-align:right;">{$fgal_stats.fpg|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Total size of files{/tr}</td><td style="text-align:right;">{$fgal_stats.size} {tr}Mb{/tr}</td></tr>
				<tr class="{cycle}"><td>{tr}Average file size{/tr}</td><td style="text-align:right;">{$fgal_stats.bpf|string_format:"%.2f"} {tr}Mb{/tr}</td></tr>
				<tr class="{cycle}"><td>{tr}Visits to file galleries{/tr}</td><td style="text-align:right;">{$fgal_stats.visits}</td></tr>
				<tr class="{cycle}"><td>{tr}Downloads{/tr}</td><td style="text-align:right;">{$fgal_stats.downloads}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- File gallleries stats -->

			<!-- CMS stats -->
			{if $cms_stats}
				<tr><th colspan="2"><a name="cms_stats"></a>{tr}CMS Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Articles{/tr}</td><td style="text-align:right;">{$cms_stats.articles}</td></tr>
				<tr class="{cycle}"><td>{tr}Total reads{/tr}</td><td style="text-align:right;">{$cms_stats.reads}</td></tr>
				<tr class="{cycle}"><td>{tr}Average reads per article{/tr}</td><td style="text-align:right;">{$cms_stats.rpa|string_format:"%.2f"}</td></tr>
			{*	
				<tr class="{cycle}"><td>{tr}Total articles size{/tr}</td><td style="text-align:right;">{$cms_stats.size} {tr}bytes{/tr}</td></tr>
				<tr class="{cycle}"><td>{tr}Average article size{/tr}</td><td style="text-align:right;">{$cms_stats.bpa|string_format:"%.2f"} {tr}bytes{/tr}</td></tr>
			*}
				<tr class="{cycle}"><td>{tr}Topics{/tr}</td><td style="text-align:right;">{$cms_stats.topics}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- CMS stats -->

			<!-- Forum stats -->
			{if $forum_stats}
				<tr><th colspan="2"><a name="forum_stats"></a>{tr}Forum Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Forums{/tr}</td><td style="text-align:right;">{$forum_stats.forums}</td></tr>
				<tr class="{cycle}"><td>{tr}Total topics{/tr}</td><td style="text-align:right;">{$forum_stats.topics}</td></tr>
				<tr class="{cycle}"><td>{tr}Average topics per forums{/tr}</td><td style="text-align:right;">{$forum_stats.tpf|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Total threads{/tr}</td><td style="text-align:right;">{$forum_stats.threads}</td></tr>
				<tr class="{cycle}"><td>{tr}Average threads per topic{/tr}</td><td style="text-align:right;">{$forum_stats.tpt|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Visits to forums{/tr}</td><td style="text-align:right;">{$forum_stats.visits}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Forum stats -->

			<!-- Blogs stats -->
			{if $blog_stats}
				<tr><th colspan="2"><a name="blog_stats"></a>{tr}Blog Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Weblogs{/tr}</td><td style="text-align:right;">{$blog_stats.blogs}</td></tr>
				<tr class="{cycle}"><td>{tr}Total posts{/tr}</td><td style="text-align:right;">{$blog_stats.posts}</td></tr>
				<tr class="{cycle}"><td>{tr}Average posts per weblog{/tr}</td><td style="text-align:right;">{$blog_stats.ppb|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Visits to weblogs{/tr}</td><td style="text-align:right;">{$blog_stats.visits}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Blogs stats -->

			<!-- Poll stats -->
			{if $poll_stats}
				<tr><th colspan="2"><a name="poll_stats"></a>{tr}Poll Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Polls{/tr}</td><td style="text-align:right;">{$poll_stats.polls}</td></tr>
				<tr class="{cycle}"><td>{tr}Total votes{/tr}</td><td style="text-align:right;">{$poll_stats.votes}</td></tr>
				<tr class="{cycle}"><td>{tr}Average votes per poll{/tr}</td><td style="text-align:right;">{$poll_stats.vpp|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Poll stats -->

			<!-- FAQ stats -->
			{if $faq_stats}
				<tr><th colspan="2"><a name="faq_stats"></a>{tr}Faq Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}FAQs{/tr}</td><td style="text-align:right;">{$faq_stats.faqs}</td></tr>
				<tr class="{cycle}"><td>{tr}Total questions{/tr}</td><td style="text-align:right;">{$faq_stats.questions}</td></tr>
				<tr class="{cycle}"><td>{tr}Average questions per FAQ{/tr}</td><td style="text-align:right;">{$faq_stats.qpf|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- FAQ stats -->

			<!-- Users stats -->
			{if $user_stats}
				<tr><th colspan="2"><a name="user_stats"></a>{tr}User Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}<a href="{$smarty.const.STATS_PKG_URL}users.php">Users</a>{/tr}</td><td style="text-align:right;">{$user_stats.users}</td></tr>
				<tr class="{cycle}"><td>{tr}User bookmarks{/tr}</td><td style="text-align:right;">{$user_stats.bookmarks}</td></tr>
				<tr class="{cycle}"><td>{tr}Average bookmarks per user{/tr}</td><td style="text-align:right;">{$user_stats.bpu|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Usersstats -->

			<!-- Quiz stats -->
			{if $quiz_stats}
				<tr><th colspan="2"><a name="quiz_stats"></a>{tr}Quiz Stats{/tr}</th></tr>
				<tr class="{cycle}"><td>{tr}Quizzes{/tr}</td><td style="text-align:right;">{$quiz_stats.quizzes}</td></tr>
				<tr class="{cycle}"><td>{tr}Questions{/tr}</td><td style="text-align:right;">{$quiz_stats.questions}</td></tr>
				<tr class="{cycle}"><td>{tr}Average questions per quiz{/tr}</td><td style="text-align:right;">{$quiz_stats.qpq|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Quizzes taken{/tr}</td><td style="text-align:right;">{$quiz_stats.visits}</td></tr>
				<tr class="{cycle}"><td>{tr}Average quiz score{/tr}</td><td style="text-align:right;">{$quiz_stats.avg|string_format:"%.2f"}</td></tr>
				<tr class="{cycle}"><td>{tr}Average time per quiz{/tr}</td><td style="text-align:right;">{$quiz_stats.avgtime|string_format:"%.2f"} {tr}seconds{/tr}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Quiz stats -->
		</table>

		<h1>{tr}Graph options{/tr}</h1>
		{legend legend="Individual Package Statistics"}
			<div class="row">
				{formlabel label="Item Statistics"}
				{forminput}
					{smartlink ititle=All chart_type=points item_chart=1 ianchor="item_chart"}
					<br />
					{foreach from=$gLibertySystem->mContentTypes item=contentType}
						{smartlink ititle=$contentType.content_description content_type_guid=$contentType.content_type_guid chart_type=points item_chart=1 ianchor="item_chart"}
						<br />
					{/foreach}
					{formhelp note="Please note that these graphs use a logarythmic y-axis."}
				{/forminput}
			</div>
		{/legend}

		{if $smarty.request.item_chart}
			<a name="item_chart"></a>
			<div style="text-align:center;">
				<img src="{$smarty.const.STATS_PKG_URL}item_chart.php?content_type_guid={$smarty.request.content_type_guid}" alt="{tr}Usage chart image{/tr}" />
			</div>
		{/if}

		{legend legend="Usage Statistics"}
			<div class="row">
				{formlabel label="Usage Statistics"}
				{forminput}
					{smartlink ititle="Display as Pie-chart" chart_type=pie usage_chart=1 ianchor="usage_chart"}
					<br />
					{smartlink ititle="Display as Bar-chart" chart_type=bars usage_chart=1 ianchor="usage_chart"}
				{/forminput}
			</div>
		{/legend}

		{if $smarty.request.usage_chart}
			<a name="usage_chart"></a>
			<div style="text-align:center;">
				<img src="{$smarty.const.STATS_PKG_URL}usage_chart.php?chart_type={$smarty.request.chart_type}" alt="{tr}Usage chart image{/tr}" />
			</div>
		{/if}

		{form legend="Site Usage Chart" ianchor="pv_chart"}
			<div class="row">
				{formlabel label="Stats Period" for="days"}
				{forminput}
					<input type="text" name="days" id="days" size="5" value="{$days}" /> {tr}days{/tr}
					{formhelp note="Number of days you want the graph to include. Insert 0 for full duration of your site."}
				{/forminput}
			</div>

			<div class="row submit">
				<input type="submit" name="pv_chart" value="{tr}Display{/tr}" />
			</div>
		{/form}

		{if $pv_chart eq 'y'}
			<a name="pv_chart"></a>
			<div style="text-align:center;">
				<img src="{$smarty.const.STATS_PKG_URL}pv_chart.php?days={$days}" alt="Site Usage Statistics" />
			</div>
		{/if}
	</div> <!-- end .body -->
</div> <!-- end .statistics -->
