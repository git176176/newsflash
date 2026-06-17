<?php if (!defined('ABSPATH')) exit;
// Template: sina
global $wpdb;
$dates = $wpdb->get_col("SELECT DISTINCT DATE(post_date) as d FROM {$wpdb->posts} WHERE post_type='newsflash' AND post_status='publish' ORDER BY post_date DESC LIMIT 7");
?>
<div class="nf-header"><h1>快讯</h1><p>实时更新</p></div>

<nav class="nf-daily-nav">
	<span class="nf-daily-nav-label">每日盘点：</span>
	<?php
	if (!empty($dates)) {
		foreach ($dates as $date) {
			$date_obj = date_create($date);
			$date_display = $date_obj ? date_format($date_obj, 'm月d日') : $date;
			$daily_url = home_url('/newsflash/' . $date . '/');
			echo '<a href="' . esc_url($daily_url) . '" class="nf-daily-link">' . esc_html($date_display) . '</a>';
		}
	}
	?>
</nav>

<div class="nf-timeline">
<div class="nf-timeline-track">
<?php if (have_posts()): while (have_posts()): the_post(); ?>
<div class="nf-item">
<span class="nf-time"><?php the_date('Y-m-d H:i'); ?></span>
<div class="nf-card">
<h3><?php the_title(); ?></h3>
<p class="nf-excerpt"><?php echo esc_html(wp_trim_words(strip_tags(get_the_content()), 40, '...')); ?></p>
<a href="<?php the_permalink(); ?>" class="nf-link">详情 →</a>
</div>
</div>
<?php endwhile; else: ?><p class="nf-empty">暂无快讯</p><?php endif; ?>
</div>
</div>

<div class="nf-pagination">
        <?php
        global $wp_query;
        $big = 999999999;
        echo paginate_links(array(
            'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
            'format' => '?paged=%#%',
            'current' => max(1, get_query_var('paged')),
            'total' => $wp_query->max_num_pages,
            'prev_next' => True,
            'prev_text' => '← 上一页',
            'next_text' => '下一页 →',
            'mid_size' => 2,
        ));
        ?>
    </div>

<style>
.nf-daily-nav { max-width: 1600px; margin: 0 auto 16px; padding: 0 16px; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.nf-daily-nav-label { font-size: 14px; color: #666; font-weight: 500; }
.nf-daily-link { display: inline-block; padding: 4px 12px; background: #f0f4ff; color: #1e6fff; text-decoration: none; border-radius: 16px; font-size: 13px; }
.nf-daily-link:hover { background: #1e6fff; color: #fff; }
</style>
