{* $Header: /cvsroot/bitweaver/_bit_stats/templates/stats.tpl,v 1.4 2005/08/07 17:46:44 squareing Exp $ *}
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
			<tr><td>{tr}Started{/tr}</td><td style="text-align:right;">{$site_stats.started|bit_short_date}</td></tr>
			<tr><td>{tr}Days online{/tr}</td><td style="text-align:right;">{$site_stats.days}</td></tr>
			<tr><td>{tr}Total pageviews{/tr}</td><td style="text-align:right;">{$site_stats.pageviews}</td></tr>
			<tr><td>{tr}Average pageviews per day{/tr}</td><td style="text-align:right;">{$site_stats.ppd|string_format:"%.2f"}</td></tr>
			<tr><td>{tr}Best day{/tr}</td><td style="text-align:right;">{$site_stats.bestday|bit_short_date} ({$site_stats.bestpvs} {tr}pvs{/tr})</td></tr>
			<tr><td>{tr}Worst day{/tr}</td><td style="text-align:right;">{$site_stats.worstday|bit_short_date} ({$site_stats.worstpvs} {tr}pvs{/tr})</td></tr>
			<tr><td colspan="2">&nbsp;</td></tr>
			<!-- Site stats -->

			<!-- Wiki Stats -->
			{if $wiki_stats}
				<tr><th colspan="2"><a name="wiki_stats"></a>{tr}Wiki Stats{/tr}</th></tr>
				<tr><td>{tr}Wiki Pages{/tr}</td><td style="text-align:right;">{$wiki_stats.pages}</td></tr>
				<tr><td>{tr}Size of Wiki Pages{/tr}</td><td style="text-align:right;">{$wiki_stats.size} {tr}Mb{/tr}</td></tr>
				<tr><td>{tr}Average page length{/tr}</td><td style="text-align:right;">{$wiki_stats.bpp|string_format:"%.2f"} {tr}bytes{/tr}</td></tr>
				<tr><td>{tr}Versions{/tr}</td><td style="text-align:right;">{$wiki_stats.versions}</td></tr>
				<tr><td>{tr}Average versions per page{/tr}</td><td style="text-align:right;">{$wiki_stats.vpp|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Visits to wiki pages{/tr}</td><td style="text-align:right;">{$wiki_stats.visits}</td></tr>
				<tr><td>{tr}Orphan pages{/tr}</td><td style="text-align:right;">{$wiki_stats.orphan}</td></tr>
				<tr><td>{tr}Average links per page{/tr}</td><td style="text-align:right;">{$wiki_stats.lpp|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Wiki Stats -->

			<!-- Image gallleries stats -->
			{if $igal_stats}
				<tr><th colspan="2"><a name="igal_stats"></a>{tr}Image galleries Stats{/tr}</th></tr>
				<tr><td>{tr}Galleries{/tr}</td><td style="text-align:right;">{$igal_stats.galleries}</td></tr>
				<tr><td>{tr}Images{/tr}</td><td style="text-align:right;">{$igal_stats.images}</td></tr>
				<tr><td>{tr}Average images per gallery{/tr}</td><td style="text-align:right;">{$igal_stats.ipg|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Total size of images{/tr}</td><td style="text-align:right;">{$igal_stats.size} {tr}Mb{/tr}</td></tr>
				<tr><td>{tr}Average image size{/tr}</td><td style="text-align:right;">{$igal_stats.bpi|string_format:"%.2f"} {tr}bytes{/tr}</td></tr>
				<tr><td>{tr}Visits to image galleries{/tr}</td><td style="text-align:right;">{$igal_stats.visits}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Image gallleries stats -->

			<!-- File gallleries stats -->
			{if $fgal_stats}
				<tr><th colspan="2"><a name="fgal_stats"></a>{tr}File galleries Stats{/tr}</th></tr>
				<tr><td>{tr}Galleries{/tr}</td><td style="text-align:right;">{$fgal_stats.galleries}</td></tr>
				<tr><td>{tr}Files{/tr}</td><td style="text-align:right;">{$fgal_stats.files}</td></tr>
				<tr><td>{tr}Average files per gallery{/tr}</td><td style="text-align:right;">{$fgal_stats.fpg|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Total size of files{/tr}</td><td style="text-align:right;">{$fgal_stats.size} {tr}Mb{/tr}</td></tr>
				<tr><td>{tr}Average file size{/tr}</td><td style="text-align:right;">{$fgal_stats.bpf|string_format:"%.2f"} {tr}Mb{/tr}</td></tr>
				<tr><td>{tr}Visits to file galleries{/tr}</td><td style="text-align:right;">{$fgal_stats.visits}</td></tr>
				<tr><td>{tr}Downloads{/tr}</td><td style="text-align:right;">{$fgal_stats.downloads}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- File gallleries stats -->

			<!-- CMS stats -->
			{if $cms_stats}
				<tr><th colspan="2"><a name="cms_stats"></a>{tr}CMS Stats{/tr}</th></tr>
				<tr><td>{tr}Articles{/tr}</td><td style="text-align:right;">{$cms_stats.articles}</td></tr>
				<tr><td>{tr}Total reads{/tr}</td><td style="text-align:right;">{$cms_stats.reads}</td></tr>
				<tr><td>{tr}Average reads per article{/tr}</td><td style="text-align:right;">{$cms_stats.rpa|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Total articles size{/tr}</td><td style="text-align:right;">{$cms_stats.size} {tr}bytes{/tr}</td></tr>
				<tr><td>{tr}Average article size{/tr}</td><td style="text-align:right;">{$cms_stats.bpa|string_format:"%.2f"} {tr}bytes{/tr}</td></tr>
				<tr><td>{tr}Topics{/tr}</td><td style="text-align:right;">{$cms_stats.topics}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- CMS stats -->

			<!-- Forum stats -->
			{if $forum_stats}
				<tr><th colspan="2"><a name="forum_stats"></a>{tr}Forum Stats{/tr}</th></tr>
				<tr><td>{tr}Forums{/tr}</td><td style="text-align:right;">{$forum_stats.forums}</td></tr>
				<tr><td>{tr}Total topics{/tr}</td><td style="text-align:right;">{$forum_stats.topics}</td></tr>
				<tr><td>{tr}Average topics per forums{/tr}</td><td style="text-align:right;">{$forum_stats.tpf|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Total threads{/tr}</td><td style="text-align:right;">{$forum_stats.threads}</td></tr>
				<tr><td>{tr}Average threads per topic{/tr}</td><td style="text-align:right;">{$forum_stats.tpt|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Visits to forums{/tr}</td><td style="text-align:right;">{$forum_stats.visits}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Forum stats -->

			<!-- Blogs stats -->
			{if $blog_stats}
				<tr><th colspan="2"><a name="blog_stats"></a>{tr}Blog Stats{/tr}</th></tr>
				<tr><td>{tr}Weblogs{/tr}</td><td style="text-align:right;">{$blog_stats.blogs}</td></tr>
				<tr><td>{tr}Total posts{/tr}</td><td style="text-align:right;">{$blog_stats.posts}</td></tr>
				<tr><td>{tr}Average posts per weblog{/tr}</td><td style="text-align:right;">{$blog_stats.ppb|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Visits to weblogs{/tr}</td><td style="text-align:right;">{$blog_stats.visits}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Blogs stats -->

			<!-- Poll stats -->
			{if $poll_stats}
				<tr><th colspan="2"><a name="poll_stats"></a>{tr}Poll Stats{/tr}</th></tr>
				<tr><td>{tr}Polls{/tr}</td><td style="text-align:right;">{$poll_stats.polls}</td></tr>
				<tr><td>{tr}Total votes{/tr}</td><td style="text-align:right;">{$poll_stats.votes}</td></tr>
				<tr><td>{tr}Average votes per poll{/tr}</td><td style="text-align:right;">{$poll_stats.vpp|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Poll stats -->

			<!-- FAQ stats -->
			{if $faq_stats}
				<tr><th colspan="2"><a name="faq_stats"></a>{tr}Faq Stats{/tr}</th></tr>
				<tr><td>{tr}FAQs{/tr}</td><td style="text-align:right;">{$faq_stats.faqs}</td></tr>
				<tr><td>{tr}Total questions{/tr}</td><td style="text-align:right;">{$faq_stats.questions}</td></tr>
				<tr><td>{tr}Average questions per FAQ{/tr}</td><td style="text-align:right;">{$faq_stats.qpf|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- FAQ stats -->

			<!-- Users stats -->
			{if $user_stats}
				<tr><th colspan="2"><a name="user_stats"></a>{tr}User Stats{/tr}</th></tr>
				<tr><td>{tr}<a href="{$smarty.const.STATS_PKG_URL}users.php">Users</a>{/tr}</td><td style="text-align:right;">{$user_stats.users}</td></tr>
				<tr><td>{tr}User bookmarks{/tr}</td><td style="text-align:right;">{$user_stats.bookmarks}</td></tr>
				<tr><td>{tr}Average bookmarks per user{/tr}</td><td style="text-align:right;">{$user_stats.bpu|string_format:"%.2f"}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Usersstats -->

			<!-- Quiz stats -->
			{if $quiz_stats}
				<tr><th colspan="2"><a name="quiz_stats"></a>{tr}Quiz Stats{/tr}</th></tr>
				<tr><td>{tr}Quizzes{/tr}</td><td style="text-align:right;">{$quiz_stats.quizzes}</td></tr>
				<tr><td>{tr}Questions{/tr}</td><td style="text-align:right;">{$quiz_stats.questions}</td></tr>
				<tr><td>{tr}Average questions per quiz{/tr}</td><td style="text-align:right;">{$quiz_stats.qpq|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Quizzes taken{/tr}</td><td style="text-align:right;">{$quiz_stats.visits}</td></tr>
				<tr><td>{tr}Average quiz score{/tr}</td><td style="text-align:right;">{$quiz_stats.avg|string_format:"%.2f"}</td></tr>
				<tr><td>{tr}Average time per quiz{/tr}</td><td style="text-align:right;">{$quiz_stats.avgtime|string_format:"%.2f"} {tr}seconds{/tr}</td></tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			{/if}
			<!-- Quiz stats -->
		</table>

		<h2>{tr}Usage chart{/tr}</h2>

		{if $usage_chart eq 'y'}
			<div align="center">
				<img src="{$smarty.const.STATS_PKG_URL}usage_chart.php" alt="{tr}Usage chart image{/tr}" />
			</div>
		{/if}

		{form}
			{tr}Show chart for the last {/tr}
			<input type="text" name="days" size="10" value="{$days|escape}" /> {tr}days (0=all){/tr}
			<input type="submit" name="pv_chart" value="{tr}display{/tr}" />
		{/form}

		{if $pv_chart eq 'y'}
			<div align="center">
				<img src="{$smarty.const.STATS_PKG_URL}pv_chart.php?days={$days}" alt="" />
			</div>
		{/if}
	</div> <!-- end .body -->
</div> <!-- end .statistics -->
