<?php if (!defined('ABSPATH')) exit;
// Template: Category Archive - 分类归档
$term = get_queried_object();
?>
<div class="nf-header">
	<h1><?php echo esc_html($term->name); ?> - 快讯分类</h1>
	<p><?php echo esc_html($term->description ? $term->description : '该分类下的所有快讯'); ?></p>
</div>

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
<?php endwhile; else: ?><p class="nf-empty">该分类暂无快讯</p><?php endif; ?>
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
