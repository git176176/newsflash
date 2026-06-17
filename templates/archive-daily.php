<?php if (!defined('ABSPATH')) exit;
// Template: Daily Recap - 每日AI资讯盘点
$date_str = get_query_var('newsflash_date');
$date_obj = date_create($date_str);
$date_display = $date_obj ? date_format($date_obj, 'Y年m月d日') : $date_str;
?>
<div class="nf-header"><h1><?php echo esc_html($date_display); ?> - AI资讯盘点</h1><p>每日AI行业资讯汇总</p></div>

<div class="nf-daily-recap-list" itemscope itemtype="https://schema.org/ItemList">
<?php
global $wp_query;

if (empty($date_str)) {
    echo '<p class="nf-empty">请访问形如 /newsflash/2026-04-17/ 的日期链接</p>';
} else {
    $daily_posts = new WP_Query([
        'post_type' => 'newsflash',
        'posts_per_page' => -1,
        'date_query' => [
            'year' => (int)substr($date_str, 0, 4),
            'month' => (int)substr($date_str, 5, 2),
            'day' => (int)substr($date_str, 8, 2),
        ],
        'orderby' => 'date',
        'order' => 'ASC',
    ]);
    
    if ($daily_posts->have_posts()) {
		$item_count = 0;
        while ($daily_posts->have_posts()) : $daily_posts->the_post();
			$item_count++;
            $post_id = get_the_ID();
            $time = esc_html(get_the_date('Y-m-d H:i'));
            $title = esc_html(get_the_title());
            $content = wpautop(wp_kses_post(get_post_field('post_content', $post_id)));
            $category = get_the_terms($post_id, 'newsflash_category');
            $category_html = '';
            if ($category && !is_wp_error($category)) {
                $cats = array();
                foreach ($category as $cat) {
                    $cats[] = '<span class="nf-article-category">' . esc_html($cat->name) . '</span>';
                }
                $category_html = implode('', $cats);
            }
?>
<div class="nf-daily-item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
	<meta itemprop="position" content="<?php echo esc_attr($item_count); ?>">
	<div class="nf-article" itemscope itemtype="https://schema.org/Article">
		<meta itemprop="datePublished" content="<?php echo esc_attr(get_the_date('c')); ?>">
        <div class="nf-article-header">
            <div class="nf-article-meta">
                <span class="nf-article-time"><?php echo $time; ?></span>
                <?php echo $category_html; ?>
            </div>
            <h2 class="nf-article-title" itemprop="headline"><a href="<?php the_permalink(); ?>"><?php echo $title; ?></a></h2>
        </div>
        <div class="nf-article-body">
            <div class="nf-article-content" itemprop="articleBody">
                <?php echo $content; ?>
            </div>
        </div>
    </div>
</div>
<?php 
        endwhile;
        wp_reset_postdata();
    } else {
        echo '<p class="nf-empty">当日暂无快讯发布</p>';
    }
}
?>
</div>

<style>
.nf-daily-recap-list { max-width: 1600px; margin: 0 auto; padding: 32px 16px; }
.nf-daily-item { margin-bottom: 48px; }
.nf-daily-item:last-child { margin-bottom: 0; }
.nf-article { max-width: 100% !important; margin: 0 auto !important; padding: 0 !important; }
.nf-article-header { background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: 40px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.nf-article-body { background: #fff; border: 1px solid #e8e8e8; border-radius: 4px; padding: 40px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
.nf-article-meta { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.nf-article-time { color: #1e6fff; font-weight: 600; font-size: 13px; }
.nf-article-category { background: #f0f4ff; color: #1e6fff; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; }
.nf-article-title { font-size: 28px; font-weight: 700; color: #1a1a1a; margin: 0; line-height: 1.4; }
.nf-article-title a { color: inherit; text-decoration: none; }
.nf-article-title a:hover { color: #1e6fff; }
.nf-article-content { font-size: 17px; line-height: 1.8; color: #333; }
.nf-article-content p { margin: 0 0 16px; }
.nf-article-content p:last-child { margin-bottom: 0; }
.nf-article-content h3 { font-size: 16px; font-weight: 600; color: #1a1a1a; margin: 24px 0 12px; }
.nf-article-content ul, .nf-article-content ol { margin: 0 0 16px; padding-left: 24px; }
.nf-article-content li { margin-bottom: 8px; }
.nf-article-content a { color: #1e6fff; }
.nf-article-content strong { color: #1a1a1a; }
.nf-empty { text-align: center; padding: 60px 20px; color: #999; font-size: 16px; }
</style>
