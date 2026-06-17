<?php if (!defined('ABSPATH')) exit;
// Template: nature
$p = get_post();
$cats = get_the_terms($p->ID, 'newsflash_category');
?>
<main class="nf-article">
<article>
<header class="nf-article-header">
<div class="nf-article-meta">
<span class="nf-article-time"><?php echo esc_html(get_the_date('Y-m-d H:i')); ?></span>
<?php if ($cats && !is_wp_error($cats)) { echo '<a href="'.esc_url(get_term_link($cats[0])).'" class="nf-article-category">'.esc_html($cats[0]->name).'</a>'; } ?>
</div>
<h1 class="nf-article-title"><?php echo esc_html($p->post_title); ?></h1>
</header>
<div class="nf-article-body">
<div class="nf-article-content"><?php echo wp_kses_post($p->post_content); ?></div>
</div>
</article>
</main>
