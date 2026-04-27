<?php
/**
 * Plugin Name: NewsFlash 快讯插件
 * Version: 1.8.0
 * Description: 新浪财经风格快讯插件，支持20套模板、底部推荐位（多样式Logo分类）、REST API、SEO优化
 */

if (!defined('ABSPATH')) exit;
define('NEWSFLASH_VERSION', '1.8.0');
define('NEWSFLASH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEWSFLASH_PLUGIN_URL', plugin_dir_url(__FILE__));

function newsflash_get_breadcrumb($type = 'single', $args = []) {
    $timeline_url = get_post_type_archive_link('newsflash');
    $html = '<nav class="nf-breadcrumb">';
    if ($type === 'single') {
        $post = $args['post'] ?? get_post();
        $post_date = get_the_date('Y-m-d', $post);
        $daily_url = home_url('/newsflash/' . $post_date . '/');
        $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a><span class="nf-breadcrumb-sep">›</span>';
        $html .= '<a href="' . esc_url($daily_url) . '">' . esc_html(get_the_date('Y年m月d日', $post)) . ' 盘点</a><span class="nf-breadcrumb-sep">›</span>';
        $html .= '<span class="nf-breadcrumb-current">' . esc_html(wp_trim_words($post->post_title, 10, '...')) . '</span>';
    } elseif ($type === 'daily') {
        $date_display = $args['date_display'] ?? '';
        $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a><span class="nf-breadcrumb-sep">›</span>';
        $html .= '<span class="nf-breadcrumb-current">' . esc_html($date_display) . ' 盘点</span>';
    } elseif ($type === 'category') {
        $term = $args['term'] ?? get_queried_object();
        $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a><span class="nf-breadcrumb-sep">›</span>';
        $html .= '<span class="nf-breadcrumb-current">' . esc_html($term->name) . '</span>';
    }
    $html .= '</nav>';
    return $html;
}

function newsflash_get_date_nav($current_date) {
    $timeline_url = get_post_type_archive_link('newsflash');
    $prev_date = date_create($current_date); $prev_date->modify('-1 day');
    $prev_url = home_url('/newsflash/' . $prev_date->format('Y-m-d') . '/');
    $next_date = date_create($current_date); $next_date->modify('+1 day');
    $next_url = home_url('/newsflash/' . $next_date->format('Y-m-d') . '/');
    $today_str = date('Y-m-d');
    $html = '<nav class="nf-date-nav">';
    $html .= '<a href="' . esc_url($prev_url) . '" class="nf-date-prev">← 前一天</a>';
    $html .= '<a href="' . esc_url($timeline_url) . '" class="nf-date-all">全部快讯</a>';
    if ($next_date->format('Y-m-d') <= $today_str) $html .= '<a href="' . esc_url($next_url) . '" class="nf-date-next">后一天 →</a>';
    $html .= '</nav>';
    return $html;
}

function newsflash_get_daily_links($limit = 7) {
    global $wpdb;
    $dates = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT DATE(post_date) as d FROM {$wpdb->posts} WHERE post_type='newsflash' AND post_status='publish' ORDER BY post_date DESC LIMIT %d", $limit));
    if (empty($dates)) return '';
    $html = '<nav class="nf-daily-nav"><span class="nf-daily-nav-label">每日盘点：</span>';
    foreach ($dates as $date) {
        $date_obj = date_create($date);
        $date_display = $date_obj ? date_format($date_obj, 'm月d日') : $date;
        $daily_url = home_url('/newsflash/' . $date . '/');
        $html .= '<a href="' . esc_url($daily_url) . '" class="nf-daily-link">' . esc_html($date_display) . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

function newsflash_get_recommend_section() {
    $settings = get_option('newsflash_recommend_settings', []);
    if (empty($settings['enabled'])) return '';
    $tools = get_option('newsflash_recommend_tools', []);
    
    // 按排序值排序（数值越大越靠前）
    usort($tools, function($a, $b) {
        $pa = intval($a['priority'] ?? 0);
        $pb = intval($b['priority'] ?? 0);
        return $pb - $pa; // 降序
    });
    
    // 过滤启用的工具
    $enabled_tools = array_filter($tools, function($t) { return !empty($t['enabled']); });
    if (empty($enabled_tools)) return '';
    
    $title = esc_html($settings['title'] ?: '热门AI工具推荐');
    $per_row = intval($settings['per_row'] ?: 6);
    $show_desc = !empty($settings['show_description']);
    $show_logo = !empty($settings['show_logo']);
    $show_cta = !empty($settings['show_cta']);
    $cta_text = esc_html($settings['cta_text'] ?: '访问');
    $card_style = sanitize_text_field($settings['card_style'] ?: 'card');
    $card_bg = esc_attr($settings['card_bg'] ?: '#ffffff');
    
    $html = '<section class="nf-recommend-section" id="nf-recommend"><div class="nf-recommend-inner">';
    $html .= '<h2 class="nf-recommend-title">' . $title . '</h2>';
    $html .= '<div class="nf-recommend-grid nf-style-' . $card_style . '" style="--cols:' . $per_row . ';--card-bg:' . $card_bg . ';">';
    
    foreach ($enabled_tools as $tool) {
        $name = esc_html($tool['name'] ?: '');
        $cat = esc_html($tool['category'] ?: '');
        $desc = $show_desc && !empty($tool['description']) ? '<span class="nf-rec-desc">' . esc_html($tool['description']) . '</span>' : '';
        $url = esc_url($tool['url'] ?: '#');
        $logo = $show_logo && !empty($tool['logo']) ? '<img src="' . esc_url($tool['logo']) . '" alt="' . esc_attr($name) . '" class="nf-rec-logo" loading="lazy">' : '';
        $cta = $show_cta ? '<a href="' . $url . '" class="nf-rec-cta" target="_blank" rel="noopener">' . $cta_text . ' →</a>' : '';
        $cat_tag = $cat ? '<span class="nf-rec-cat">' . $cat . '</span>' : '';
        
        $html .= '<div class="nf-recommend-card" onclick="window.open(\''.$url.'\', \'_blank\')" style="cursor:pointer">';
        if ($logo) $html .= '<div class="nf-rec-logo-wrap">' . $logo . '</div>';
        $html .= '<div class="nf-rec-body">';
        $html .= '<div class="nf-rec-head"><a href="'.$url.'" class="nf-rec-name" target="_blank" rel="noopener">' . $name . '</a>' . $cat_tag . '</div>';
        if ($desc) $html .= $desc;
        $html .= '</div></div>';
    }
    $html .= '</div></div></section>';
    return $html;
}

final class NewsFlash_Plugin {
    private static $instance = null;
    public static function instance() { if (is_null(self::$instance)) self::$instance = new self(); return self::$instance; }
    private function __construct() {
        add_action('init', [$this, 'register_cpt'], 1);
        add_action('init', [$this, 'add_rewrite_rules'], 2);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('rest_api_init', [$this, 'register_api']);
        add_action('wp_enqueue_scripts', [$this, 'load_assets']);
        add_filter('template_redirect', [$this, 'load_template'], 1);
        add_shortcode('newsflash_timeline', [$this, 'timeline_shortcode']);
        add_shortcode('newsflash_list', [$this, 'list_shortcode']);
        add_shortcode('newsflash_recommend', [$this, 'recommend_shortcode']);
        add_action('admin_menu', [$this, 'create_admin_menu'], 1);
        add_action('add_meta_boxes', [$this, 'add_keywords_metabox']);
        add_action('save_post_newsflash', [$this, 'save_keywords_metabox']);
        add_action('admin_init', [$this, 'handle_recommend_form']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    public function activate() {
        $this->register_cpt(); flush_rewrite_rules();
        if (get_option('newsflash_api_key') === false) add_option('newsflash_api_key', wp_generate_uuid4());
        if (get_option('newsflash_settings') === false) add_option('newsflash_settings', ['article_template'=>'sina','timeline_template'=>'sina','timeline_position'=>'left','show_footer'=>true,'posts_per_page'=>10,'custom_css'=>'','custom_html_article'=>'','custom_html_timeline'=>'']);
        if (get_option('newsflash_recommend_settings') === false) add_option('newsflash_recommend_settings', ['enabled'=>false,'title'=>'热门AI工具推荐','per_row'=>6,'show_description'=>true,'show_logo'=>true,'show_cta'=>true,'cta_text'=>'访问','card_style'=>'card','card_bg'=>'#ffffff']);
        if (get_option('newsflash_recommend_tools') === false) add_option('newsflash_recommend_tools', []);
        if (get_option('newsflash_recommend_categories') === false) add_option('newsflash_recommend_categories', ['AI写作','AI设计','AI编程','AI视频','AI音频','AI办公','AI对话','AI开发平台','AI数字人','AI效率工具','其他']);
    }
    public function register_cpt() {
        register_post_type('newsflash', ['label'=>'快讯','labels'=>['name'=>'快讯','singular_name'=>'快讯','add_new'=>'发布快讯','add_new_item'=>'发布新快讯','edit_item'=>'编辑快讯','new_item'=>'新快讯','view_item'=>'查看快讯','search_items'=>'搜索快讯','not_found'=>'没有找到快讯'],'public'=>true,'show_ui'=>true,'show_in_menu'=>false,'supports'=>['title','editor','author','thumbnail','excerpt','custom-fields'],'has_archive'=>true,'rewrite'=>['slug'=>'newsflash','with_front'=>true],'show_in_rest'=>true,'rest_base'=>'newsflash']);
        register_taxonomy('newsflash_category', 'newsflash', ['label'=>'快讯分类','labels'=>['name'=>'快讯分类'],'hierarchical'=>true,'show_ui'=>true,'show_admin_column'=>true,'show_in_rest'=>true,'public'=>true,'rewrite'=>['slug'=>'newsflash-category']]);
    }
    public function add_rewrite_rules() { add_rewrite_rule('newsflash/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?post_type=newsflash&newsflash_date=$matches[1]', 'top'); }
    public function add_query_vars($vars) { $vars[] = 'newsflash_date'; return $vars; }
    public function add_keywords_metabox() { add_meta_box('newsflash_keywords', '快讯关键词', [$this, 'render_keywords_metabox'], 'newsflash', 'side', 'default'); }
    public function render_keywords_metabox($post) { wp_nonce_field('newsflash_keywords', 'newsflash_keywords_nonce'); $k = get_post_meta($post->ID, 'newsflash_keywords', true); echo '<input type="text" name="newsflash_keywords" value="'.esc_attr($k).'" style="width:100%;padding:6px;border:1px solid #8c8f94;border-radius:4px"><p style="margin:4px 0 0;color:#646970;font-size:11px">英文逗号分隔</p>'; }
    public function save_keywords_metabox($pid) { if (!isset($_POST['newsflash_keywords_nonce']) || !wp_verify_nonce($_POST['newsflash_keywords_nonce'], 'newsflash_keywords')) return; if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return; if (isset($_POST['newsflash_keywords'])) update_post_meta($pid, 'newsflash_keywords', sanitize_text_field($_POST['newsflash_keywords'])); }
    public function register_api() {
        register_rest_route('newsflash/v1', '/posts', ['methods'=>'POST','callback'=>[$this,'api_create'],'permission_callback'=>function(){ return isset($_SERVER['HTTP_X_NEWSFLASH_KEY']) && $_SERVER['HTTP_X_NEWSFLASH_KEY'] === get_option('newsflash_api_key',''); }]);
        register_rest_route('newsflash/v1', '/posts/(?P<id>\d+)', ['methods'=>'GET','callback'=>[$this,'api_get'],'permission_callback'=>'__return_true']);
        register_rest_route('newsflash/v1', '/categories', ['methods'=>'GET','callback'=>[$this,'api_categories'],'permission_callback'=>'__return_true']);
        register_rest_route('newsflash/v1', '/recommend-tools', ['methods'=>'GET','callback'=>[$this,'api_recommend_tools'],'permission_callback'=>'__return_true']);
    }
    public function api_create($req) {
        $p = $req->get_json_params(); $title = sanitize_text_field($p['title'] ?? ''); $content = wp_kses_post($p['content'] ?? '');
        if (!$title || !$content) return new WP_Error('error', '标题和内容不能为空', ['status'=>400]);
        $content = wpautop($content);
        if (!empty($p['references']) && is_array($p['references'])) { $r = '<h3>📎 参考来源</h3><ul>'; foreach ($p['references'] as $ref) { $rt = esc_html($ref['title'] ?? ''); $ru = esc_url($ref['url'] ?? ''); if ($rt && $ru) $r .= '<li><a href="'.$ru.'" target="_blank">'.$rt.'</a></li>'; } $r .= '</ul>'; $content .= $r; }
        $arr = ['post_type'=>'newsflash','post_title'=>$title,'post_content'=>$content,'post_status'=>'publish'];
        if (!empty($p['slug'])) $arr['post_name'] = sanitize_title($p['slug']);
        $pid = wp_insert_post($arr);
        if (is_wp_error($pid)) return new WP_Error('error', '创建失败', ['status'=>500]);
        if (!empty($p['category'])) wp_set_object_terms($pid, $p['category'], 'newsflash_category');
        if (!empty($p['keywords'])) update_post_meta($pid, 'newsflash_keywords', sanitize_text_field($p['keywords']));
        return ['success'=>true,'post_id'=>$pid,'url'=>get_permalink($pid)];
    }
    public function api_get($req) { $p = get_post($req['id']); if (!$p || $p->post_type !== 'newsflash') return new WP_Error('not_found', '快讯不存在', ['status'=>404]); return ['id'=>$p->ID,'title'=>$p->post_title,'content'=>$p->post_content,'date'=>$p->post_date]; }
    public function api_categories() { return ['categories'=>get_terms(['taxonomy'=>'newsflash_category','hide_empty'=>false])]; }
    public function api_recommend_tools() { return ['settings'=>get_option('newsflash_recommend_settings',[]),'tools'=>get_option('newsflash_recommend_tools',[]),'categories'=>get_option('newsflash_recommend_categories',[])]; }
    public function load_assets() {
        if (is_singular('newsflash') || is_post_type_archive('newsflash') || is_tax('newsflash_category')) {
            wp_enqueue_style('newsflash', NEWSFLASH_PLUGIN_URL . 'assets/css/newsflash.css', [], NEWSFLASH_VERSION);
            $s = get_option('newsflash_settings', []);
            if (empty($s['show_footer'])) wp_add_inline_style('newsflash', 'footer.main-footer,footer.footer-stick,#footer,.site-footer,.main-footer,.wp-footer{display:none!important}');
            if (!empty($s['custom_css'])) wp_add_inline_style('newsflash', $s['custom_css']);
        }
    }
    public function load_template() {
        $settings = get_option('newsflash_settings', []);
        add_filter('pre_get_posts', function($q) use ($settings) { if ($q->is_post_type_archive('newsflash')) $q->set('posts_per_page', $settings['posts_per_page'] ?? 10); });
        if (is_singular('newsflash')) {
            $pid = get_queried_object_id(); $kw = get_post_meta($pid, 'newsflash_keywords', true);
            if ($kw) add_action('wp_head', function() use ($kw) { echo '<meta name="keywords" content="' . esc_attr($kw) . '">' . "\n"; }, 1);
            $tpl = $settings['article_template'] ?? 'sina';
            add_filter('body_class', function($c) use ($tpl) { $c[] = 't-'.$tpl; $c[] = 'nf-single'; return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/single-' . $tpl . '.php';
            if (!file_exists($file)) $file = NEWSFLASH_PLUGIN_DIR . 'templates/single-sina.php';
            if (file_exists($file)) { get_header(); $this->output_breadcrumb('single', $pid); include $file; echo newsflash_get_recommend_section(); if ($settings['show_footer'] ?? true) get_footer(); exit; }
        }
        $date_str = get_query_var('newsflash_date');
        if (!empty($date_str) && is_string($date_str)) {
            $do = date_create($date_str); $dd = $do ? date_format($do, 'Y年m月d日') : $date_str;
            add_action('wp_head', function() use ($dd) { echo '<title>'.$dd.' AI资讯盘点 - '.get_bloginfo('name').'</title><meta name="description" content="'.$dd.' AI行业资讯">'."\n"; }, 0);
            add_filter('body_class', function($c) { $c[] = 't-'.(get_option('newsflash_settings')['timeline_template']??'sina'); $c[] = 'nf-archive nf-daily-recap'; return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-daily.php';
            if (file_exists($file)) { ob_start(); get_header(); $this->output_breadcrumb('daily'); include $file; if ($settings['show_footer']??true) get_footer(); $out = ob_get_clean(); $out = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>'.$dd.' AI资讯盘点 - '.get_bloginfo('name').'</title>', $out); echo $out; exit; }
        }
        if (is_tax('newsflash_category')) {
            $term = get_queried_object();
            add_action('wp_head', function() use ($term) { echo '<title>'.$term->name.' - AI快讯 - '.get_bloginfo('name').'</title><meta name="description" content="浏览'.$term->name.'分类快讯">'."\n"; }, 0);
            add_filter('body_class', function($c) { $c[] = 't-'.(get_option('newsflash_settings')['timeline_template']??'sina'); $c[] = 'nf-archive nf-category'; return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-category.php';
            if (file_exists($file)) { ob_start(); get_header(); $this->output_breadcrumb('category'); include $file; if ($settings['show_footer']??true) get_footer(); $out = ob_get_clean(); $out = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>'.$term->name.' - AI快讯</title>', $out); echo $out; exit; }
        }
        if (is_post_type_archive('newsflash')) {
            add_action('wp_head', function() { echo '<title>AI快讯 - '.get_bloginfo('name').'</title><meta name="description" content="实时更新AI行业资讯">'."\n"; }, 0);
            $tpl = $settings['timeline_template'] ?? 'sina';
            add_filter('body_class', function($c) use ($tpl, $settings) { $c[] = 't-'.$tpl; $c[] = 'nf-archive nf-position-'.($settings['timeline_position']??'left'); return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-' . $tpl . '.php';
            if (!file_exists($file)) $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-sina.php';
            if (file_exists($file)) { ob_start(); get_header(); $this->output_breadcrumb(''); include $file; if ($settings['show_footer']??true) get_footer(); $out = ob_get_clean(); $out = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>AI快讯 - '.get_bloginfo('name').'</title>', $out); echo $out; exit; }
        }
    }
    private function output_breadcrumb($type = '', $pid = null) {
        $tu = get_post_type_archive_link('newsflash'); $h = '';
        if ($type === 'single' && $pid) { $p = get_post($pid); $pd = get_the_date('Y-m-d', $p); $pdd = get_the_date('Y年m月d日', $p); $du = home_url('/newsflash/'.$pd.'/'); $h = '<nav class="nf-breadcrumb"><a href="'.$tu.'">快讯时间线</a><span class="nf-breadcrumb-sep">›</span><a href="'.$du.'">'.$pdd.' 盘点</a><span class="nf-breadcrumb-sep">›</span><span class="nf-breadcrumb-current">快讯</span></nav>'; }
        elseif ($type === 'daily') { $ds = get_query_var('newsflash_date'); $do = date_create($ds); $dd = $do ? date_format($do, 'Y年m月d日') : $ds; $h = '<nav class="nf-breadcrumb"><a href="'.$tu.'">快讯时间线</a><span class="nf-breadcrumb-sep">›</span><span class="nf-breadcrumb-current">'.$dd.' 盘点</span></nav>'; }
        elseif ($type === 'category') { $t = get_queried_object(); $h = '<nav class="nf-breadcrumb"><a href="'.$tu.'">快讯时间线</a><span class="nf-breadcrumb-sep">›</span><span class="nf-breadcrumb-current">'.$t->name.'</span></nav>'; }
        if ($h) echo $h.'<style>.nf-breadcrumb{max-width:1600px;margin:0 auto 16px;padding:0 16px;font-size:14px;color:#666}.nf-breadcrumb a{color:#1e6fff;text-decoration:none}.nf-breadcrumb-sep{margin:0 8px;color:#999}.nf-breadcrumb-current{color:#333}</style>';
    }
    public function timeline_shortcode($atts) {
        $a = shortcode_atts(['count'=>20,'category'=>'','show_excerpt'=>'true','paged'=>1], $atts);
        $args = ['post_type'=>'newsflash','posts_per_page'=>(int)$a['count'],'paged'=>max(1,(int)$a['paged']),'orderby'=>'date','order'=>'DESC'];
        if (!empty($a['category'])) $args['tax_query'] = [['taxonomy'=>'newsflash_category','field'=>'slug','terms'=>$a['category']]];
        $posts = get_posts($args); if (!$posts) return '<p class="nf-empty">暂无快讯</p>';
        $h = '<div class="nf-timeline nf-timeline-list">';
        foreach ($posts as $p) { $t = esc_html(get_the_date('Y-m-d H:i', $p->ID)); $tt = esc_html($p->post_title); $ex = $a['show_excerpt']==='true' ? '<p class="nf-excerpt">'.esc_html(wp_trim_words(strip_tags($p->post_content),40,'...')).'</p>' : ''; $l = esc_url(get_permalink($p->ID)); $h .= sprintf('<div class="nf-item"><span class="nf-time">%s</span><div class="nf-card"><h3>%s</h3>%s<a href="%s" class="nf-link">详情</a></div></div>', $t, $tt, $ex, $l); }
        return $h.'</div>';
    }
    public function list_shortcode($atts) {
        $a = shortcode_atts(['count'=>10], $atts);
        $posts = get_posts(['post_type'=>'newsflash','posts_per_page'=>(int)$a['count'],'orderby'=>'date','order'=>'DESC']);
        if (!$posts) return '<p class="nf-empty">暂无快讯</p>';
        $h = '<div class="nf-list nf-list-simple">';
        foreach ($posts as $p) $h .= sprintf('<div class="nf-list-item"><span class="nf-date">%s</span><a href="%s">%s</a></div>', esc_html(get_the_date('m-d H:i', $p->ID)), esc_url(get_permalink($p->ID)), esc_html($p->post_title));
        return $h.'</div>';
    }
    public function recommend_shortcode($atts) { return newsflash_get_recommend_section(); }
    public function create_admin_menu() {
        add_menu_page('NewsFlash', '快讯', 'manage_options', 'edit.php?post_type=newsflash', '', 'dashicons-megaphone', 5);
        add_submenu_page('edit.php?post_type=newsflash', '所有快讯', '所有快讯', 'manage_options', 'edit.php?post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '发布快讯', '发布快讯', 'manage_options', 'post-new.php?post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '快讯分类', '快讯分类', 'manage_options', 'edit-tags.php?taxonomy=newsflash_category&post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '底部推荐位', '底部推荐位', 'manage_options', 'nf_recommend', [$this, 'recommend_page']);
        add_submenu_page('edit.php?post_type=newsflash', '快讯设置', '设置', 'manage_options', 'nf_settings', [$this, 'settings_page']);
        add_submenu_page('edit.php?post_type=newsflash', 'API 文档', 'API 文档', 'manage_options', 'nf_api', [$this, 'api_docs_page']);
        add_submenu_page('edit.php?post_type=newsflash', '时间线预览', '时间线预览', 'manage_options', 'nf_preview', [$this, 'preview_page']);
    }
    public function handle_recommend_form() {
        if (!isset($_POST['nr']) || !wp_verify_nonce($_POST['nr'], 'nr')) return;
        if (isset($_POST['save_set'])) {
            update_option('newsflash_recommend_settings', [
                'enabled'=>!empty($_POST['en']),'title'=>sanitize_text_field($_POST['title']?:'热门AI工具推荐'),'per_row'=>max(4,min(8,intval($_POST['pr']?:6))),
                'show_description'=>!empty($_POST['sd']),'show_logo'=>!empty($_POST['sl']),'show_cta'=>!empty($_POST['sc']),
                'cta_text'=>sanitize_text_field($_POST['ct']?:'访问'),'card_style'=>sanitize_text_field($_POST['cs']?:'card'),'card_bg'=>sanitize_hex_color($_POST['bg']?:'#ffffff'),
            ]);
            wp_redirect(admin_url('admin.php?page=nf_recommend&ok=1')); exit;
        }
        if (isset($_POST['add_c'])) { $cats = get_option('newsflash_recommend_categories',[]); $n = sanitize_text_field($_POST['nc']); if ($n && !in_array($n,$cats)) { $cats[]=$n; update_option('newsflash_recommend_categories',$cats); } wp_redirect(admin_url('admin.php?page=nf_recommend&ok=2')); exit; }
        if (isset($_POST['del_c'])) { $c = sanitize_text_field($_POST['c']); $cats = get_option('newsflash_recommend_categories',[]); $cats = array_values(array_filter($cats,function($x)use($c){return $x!==$c;})); update_option('newsflash_recommend_categories',$cats); $tools = get_option('newsflash_recommend_tools',[]); $tools = array_values(array_filter($tools,function($t)use($c){return($t['category']??'')!==$c;})); update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_recommend&ok=3')); exit; }
        if (isset($_POST['add_t'])) {
            $tools = get_option('newsflash_recommend_tools',[]);
            $max_pri = 0; foreach($tools as $t) { $p = intval($t['priority']??0); if($p>$max_pri) $max_pri=$p; }
            $tools[] = ['id'=>uniqid(),'category'=>sanitize_text_field($_POST['tc']?:''),'name'=>sanitize_text_field($_POST['tn']?:''),'description'=>sanitize_textarea_field($_POST['td']?:''),'url'=>esc_url_raw($_POST['tu']?:''),'logo'=>esc_url_raw($_POST['tl']?:''),'priority'=>$max_pri+10,'enabled'=>true];
            update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_recommend&ok=4')); exit;
        }
        if (isset($_POST['del_t'])) { $id=sanitize_text_field($_POST['id']); $tools=get_option('newsflash_recommend_tools',[]); $tools=array_values(array_filter($tools,function($t)use($id){return($t['id']??'')!==$id;})); update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_recommend&ok=5')); exit; }
        if (isset($_POST['tog_t'])) { $id=sanitize_text_field($_POST['id']); $tools=get_option('newsflash_recommend_tools',[]); foreach($tools as &$t) { if(($t['id']??'')==$id) $t['enabled']=empty($t['enabled']); } update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_recommend&ok=6')); exit; }
        if (isset($_POST['upd_t'])) {
            $id=sanitize_text_field($_POST['id']); $tools=get_option('newsflash_recommend_tools',[]);
            foreach($tools as &$t) { if(($t['id']??'')==$id) {
                $t['category']=sanitize_text_field($_POST['category']??$t['category']); $t['name']=sanitize_text_field($_POST['name']??$t['name']);
                $t['description']=sanitize_textarea_field($_POST['description']??$t['description']); $t['url']=esc_url_raw($_POST['url']??$t['url']);
                $t['logo']=esc_url_raw($_POST['logo']??$t['logo']); $t['priority']=intval($_POST['priority']??($t['priority']??0));
            }}
            update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_recommend&ok=7')); exit;
        }
    }
    public function recommend_page() {
        $s = get_option('newsflash_recommend_settings',[]);
        $tools = get_option('newsflash_recommend_tools',[]);
        $cats = get_option('newsflash_recommend_categories',[]);
        $all_tools = $tools;
        
        $filter_cat = sanitize_text_field($_GET['cat'] ?? '');
        $filter_status = sanitize_text_field($_GET['status'] ?? '');
        if ($filter_cat || $filter_status) {
            $tools = array_filter($tools, function($t) use ($filter_cat, $filter_status) {
                if ($filter_cat && ($t['category'] ?? '') !== $filter_cat) return false;
                if ($filter_status === 'enabled' && empty($t['enabled'])) return false;
                if ($filter_status === 'disabled' && !empty($t['enabled'])) return false;
                return true;
            });
        }
        usort($tools, function($a,$b){ return (intval($b['priority']??0) - intval($a['priority']??0)); });
        
        echo '<div class="wrap"><h1>📌 AI工具推荐</h1>';
        $msgs = [1=>'✅ 已保存',2=>'✅ 已添加',3=>'✅ 已删除',4=>'✅ 已添加',5=>'✅ 已删除',6=>'✅ 已切换',7=>'✅ 已更新',8=>'✅ 批量删除',9=>'✅ 批量启用',10=>'✅ 批量禁用'];
        if (isset($_GET['ok']) && isset($msgs[$_GET['ok']])) echo '<div class="notice notice-success is-dismissible"><p>'.$msgs[$_GET['ok']].'</p></div>';
        
        echo '<style>
        :root{--p:#4f46e5;--bg:#f8fafc;--card:#fff;--brd:#e2e8f0;--txt:#1e293b;--mut:#64748b;--r:12px}
        .nf-card{background:var(--card);border-radius:var(--r);box-shadow:0 1px 3px rgba(0,0,0,.08);border:1px solid var(--brd);margin:20px 0;overflow:hidden}
        .nf-hd{padding:14px 20px;border-bottom:1px solid var(--brd);font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px;background:linear-gradient(to bottom,#fff,#fafafa)}
        .nf-bd{padding:20px}
        .nf-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}
        .nf-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0}
        .nf-row:last-child{border-bottom:none}
        .nf-tg{position:relative;width:40px;height:22px}
        .nf-tg input{opacity:0;width:0;height:0}
        .nf-tg .sl{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:22px;transition:.2s}
        .nf-tg .sl:before{position:absolute;content:"";height:16px;width:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s}
        .nf-tg input:checked+.sl{background:var(--p)}
        .nf-tg input:checked+.sl:before{transform:translateX(18px)}
        .nf-in{padding:6px 10px;border:1px solid var(--brd);border-radius:6px;font-size:13px}
        .nf-btn{display:inline-flex;align-items:center;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;border:none;gap:5px}
        .nf-btn-p{background:var(--p);color:#fff}
        .nf-btn-p:hover{background:#4338ca}
        .nf-btn-s{background:#fff;color:var(--txt);border:1px solid var(--brd)}
        .nf-btn-sm{padding:5px 10px;font-size:12px}
        .nf-cats{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px}
        .nf-cat{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;background:var(--bg);border-radius:18px;font-size:12px}
        .nf-cat:hover{background:#e2e8f0}
        .nf-cat .cnt{font-size:9px;background:#cbd5e1;padding:1px 5px;border-radius:9px}
        .nf-cat .del{width:14px;height:14px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#e2e8f0;font-size:10px;cursor:pointer}
        .nf-cat .del:hover{background:#fecaca;color:#dc2626}
        .nf-tbl{width:100%;border-collapse:collapse;font-size:13px}
        .nf-tbl th{text-align:left;padding:10px 14px;font-weight:600;color:var(--mut);font-size:10px;text-transform:uppercase;border-bottom:1px solid var(--brd);background:linear-gradient(to bottom,#f8fafc,#f1f5f9)}
        .nf-tbl td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
        .nf-tbl tbody tr:hover td{background:#f8fafc}
        .nf-tbl tbody tr:last-child td{border-bottom:none}
        .nf-tbl tbody tr.off{opacity:.5}
        .nf-bdg{display:inline-flex;padding:2px 8px;border-radius:16px;font-size:10px;font-weight:500}
        .nf-bdg-on{background:#dcfce7;color:#166534}
        .nf-bdg-off{background:#fee2e2;color:#991b1b}
        .nf-bdg-info{background:#e0e7ff;color:#3730a3}
        .nf-acts{display:flex;gap:3px;opacity:.5}
        .nf-tbl tr:hover .nf-acts{opacity:1}
        .nf-act{padding:3px 8px;border-radius:4px;font-size:11px;cursor:pointer;border:1px solid #ddd;background:#fff;color:var(--txt)}
        .nf-act:hover{background:#f6f7f7}
        .nf-act.danger{color:#dc2626;border-color:#fecaca}
        .nf-qe{display:none;background:linear-gradient(135deg,#fefce8,#fef9c3);border:1px solid #fde68a;border-radius:6px;padding:12px;margin-top:8px}
        .nf-qe.show{display:block;animation:slideDown .15s}
        @keyframes slideDown{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
        .nf-qe-g{display:grid;grid-template-columns:1fr 1fr 2fr 2fr 60px auto;gap:8px;align-items:center}
        .nf-qe-g .f label{font-size:9px;color:#92400e;font-weight:600;text-transform:uppercase;display:block;margin-bottom:2px}
        .nf-qe-g .f input,.nf-qe-g .f select{padding:5px 7px;border:1px solid #fcd34d;border-radius:4px;font-size:12px;background:rgba(255,255,255,.8);width:100%;box-sizing:border-box}
        .nf-add{display:grid;grid-template-columns:1fr 1.5fr 2fr 2fr auto;gap:10px;align-items:end;padding:16px;background:var(--bg);border-radius:var(--r);margin-top:14px}
        .nf-add .f{display:flex;flex-direction:column;gap:3px}
        .nf-add .f label{font-size:10px;color:var(--mut);font-weight:500}
        .nf-tbar{display:flex;gap:10px;margin-bottom:14px}
        .nf-bulk{display:none;align-items:center;gap:10px;padding:8px 14px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);border-radius:6px;margin-bottom:14px}
        .nf-bulk.show{display:flex}
        .nf-nm{display:flex;align-items:center;gap:8px}
        .nf-logo{width:28px;height:28px;border-radius:5px;background:var(--bg);border:1px solid var(--brd);display:flex;align-items:center;justify-content:center;font-size:12px;overflow:hidden;flex-shrink:0}
        .nf-logo img{width:100%;height:100%;object-fit:contain}
        .nf-name{font-weight:600;font-size:12px}
        .nf-name small{font-weight:400;color:var(--mut);font-size:9px}
        .nf-url{max-width:160px;color:var(--p);text-decoration:none;font-size:11px;word-break:break-all;white-space:normal;line-height:1.4}
        .nf-desc{color:var(--mut);font-size:11px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .nf-col{max-height:0;overflow:hidden;transition:max-height .2s}
        .nf-col.open{max-height:300px}
        </style>';
        
        echo '<div class="nf-card"><div class="nf-hd" onclick="document.getElementById(\'nf-set\').classList.toggle(\'open\')" style="cursor:pointer">⚙️ 设置 <span style="margin-left:auto;font-weight:400;font-size:10px;color:#94a3b8">展开 ▼</span></div>';
        echo '<div id="nf-set" class="nf-col"><div class="nf-bd"><form method="post">'.wp_nonce_field('nr','nr',true,false);
        echo '<div class="nf-grid">';
        echo '<div class="nf-row"><label>启用</label><label class="nf-tg"><input type="checkbox" name="en" value="1"'.checked(!empty($s['enabled']),true,false).'><span class="sl"></span></label></div>';
        echo '<div class="nf-row"><label>标题</label><input type="text" name="title" class="nf-in" value="'.esc_attr($s['title']??'热门AI工具推荐').'" style="width:140px"></div>';
        echo '<div class="nf-row"><label>每行</label><select name="pr" class="nf-in" style="width:60px">'; for($i=4;$i<=8;$i++) echo '<option value="'.$i.'"'.selected($s['per_row']??6,$i,false).'>'.$i.'</option>'; echo '</select></div>';
        echo '<div class="nf-row"><label>Logo</label><label class="nf-tg"><input type="checkbox" name="sl" value="1"'.checked(!empty($s['show_logo']),true,false).'><span class="sl"></span></label></div>';
        echo '<div class="nf-row"><label>简介</label><label class="nf-tg"><input type="checkbox" name="sd" value="1"'.checked(!empty($s['show_description']),true,false).'><span class="sl"></span></label></div>';
        echo '<div class="nf-row"><label>按钮</label><label class="nf-tg"><input type="checkbox" name="sc" value="1"'.checked(!empty($s['show_cta']),true,false).'><span class="sl"></span></label></div>';
        echo '</div>';
        echo '<div class="nf-row"><label>按钮文字</label><input type="text" name="ct" class="nf-in" value="'.esc_attr($s['cta_text']??'访问').'" style="width:70px"> <label style="margin-left:12px">背景色</label><input type="color" name="bg" value="'.esc_attr($s['card_bg']??'#ffffff').'" style="width:30px;height:24px;border:1px solid #ddd;border-radius:4px;cursor:pointer"></div>';
        echo '<div class="nf-row"><label>卡片样式</label><select name="cs" class="nf-in" style="width:100px"><option value="card"'.selected($s['card_style']??'card','card',false).'>标准卡片</option><option value="compact"'.selected($s['card_style']??'card','compact',false).'>紧凑型</option><option value="highlight"'.selected($s['card_style']??'card','highlight',false).'>突出型</option><option value="minimal"'.selected($s['card_style']??'card','minimal',false).'>简约型</option></select></div>';
        echo '<p style="margin:10px 0 0"><button type="submit" name="save_set" class="nf-btn nf-btn-p">保存设置</button></p></form></div></div></div>';
        
        echo '<div class="nf-card"><div class="nf-hd">🏷️ 分类 <span style="margin-left:auto;font-weight:400;font-size:10px;color:#94a3b8">'.count($cats).'</span></div><div class="nf-bd">';
        echo '<div class="nf-cats">';
        foreach($cats as $c){$cnt=0;foreach($all_tools as $t)if(($t['category']??'')==$c)$cnt++;
            echo '<span class="nf-cat">'.esc_html($c).'<span class="cnt">'.$cnt.'</span><form method="post" style="display:inline">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="c" value="'.esc_attr($c).'"><span class="del" onclick="if(confirm(\'删除?\'))this.parentElement.submit()">×</span></form></span>';
        }
        echo '</div>';
        echo '<form method="post" style="display:flex;gap:8px">'.wp_nonce_field('nr','nr',true,false).'<input type="text" name="nc" class="nf-in" placeholder="新分类" required style="flex:1;max-width:240px"><button type="submit" name="add_c" class="nf-btn nf-btn-s">添加</button></form></div></div>';
        
        echo '<div class="nf-card"><div class="nf-hd">📋 工具 <span style="margin-left:auto;font-weight:400;font-size:10px;color:#94a3b8">'.count($tools).'</span></div><div class="nf-bd">';
        
        echo '<div class="nf-tbar"><form method="get" style="display:flex;gap:8px;flex:1"><input type="hidden" name="page" value="nf_recommend">';
        echo '<select name="cat" onchange="this.form.submit()" class="nf-in"><option value="">全部</option>'; foreach($cats as $c) echo '<option value="'.esc_attr($c).'"'.selected($filter_cat,$c,false).'>'.esc_html($c).'</option>'; echo '</select>';
        echo '<select name="status" onchange="this.form.submit()" class="nf-in"><option value="">状态</option><option value="enabled"'.selected($filter_status,'enabled',false).'>启用</option><option value="disabled"'.selected($filter_status,'disabled',false).'>禁用</option></select></form></div>';
        
        echo '<form method="post" id="bulk-form">'.wp_nonce_field('nr','nr',true,false);
        echo '<div class="nf-bulk" id="bulk-bar"><span>已选 <strong id="sel-cnt">0</strong></span><select name="bulk_action" class="nf-in"><option value="">操作</option><option value="enable">启用</option><option value="disable">禁用</option><option value="delete">删除</option></select><button type="submit" class="nf-btn nf-btn-s nf-btn-sm">应用</button></div>';
        
        echo '<table class="nf-tbl"><thead><tr>';
        echo '<th style="width:32px"><input type="checkbox" id="check-all" onchange="document.querySelectorAll(\'.tool-cb\').forEach(c=>c.checked=this.checked);upd()"></th>';
        echo '<th style="width:50px" title="数字越大越靠前">排序↓</th><th>工具</th><th style="width:80px">分类</th><th>链接</th><th>简介</th><th style="width:60px">状态</th><th style="width:90px">操作</th></tr></thead><tbody>';
        
        foreach($tools as $t){
            $id=esc_attr($t['id']??'');
            echo '<tr class="'.(empty($t['enabled'])?'off':'').'">';
            echo '<td><input type="checkbox" name="tool_ids[]" value="'.$id.'" class="tool-cb" onchange="upd()"></td>';
            echo '<td><input type="number" value="'.esc_attr($t['priority']??0).'" class="nf-in" style="width:50px;padding:3px;text-align:center" min="0" max="1000" onchange="document.getElementById(\'qe-'.$id.'\').classList.add(\'show\');document.querySelector(\'#qe-'.$id.' input[name=priority]\').value=this.value"></td>';
            echo '<td><div class="nf-nm"><div class="nf-logo">'; if(!empty($t['logo'])) echo '<img src="'.esc_url($t['logo']).'" alt="">'; else echo '🔧'; echo '</div><div><span class="nf-name">'.esc_html($t['name']??'').'</span></div></div></td>';
            echo '<td><span class="nf-bdg nf-bdg-info">'.esc_html($t['category']??'').'</span></td>';
            echo '<td><a href="'.esc_url($t['url']??'#').'" target="_blank" class="nf-url">'.esc_html($t['url']??'').'</a></td>';
            echo '<td class="nf-desc">'.esc_html($t['description']??'').'</td>';
            echo '<td><span class="nf-bdg '.(!empty($t['enabled'])?'nf-bdg-on':'nf-bdg-off').'">'.(!empty($t['enabled'])?'启用':'禁用').'</span></td>';
            echo '<td><div class="nf-acts">';
            echo '<a href="javascript:void(0)" class="nf-act" onclick="document.getElementById(\'qe-'.$id.'\').classList.toggle(\'show\')">编辑</a>';
            echo '<form method="post" style="display:inline">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="id" value="'.$id.'"><button type="submit" name="tog_t" class="nf-act">'.(!empty($t['enabled'])?'禁':'启').'</button></form>';
            echo '<form method="post" style="display:inline" onsubmit="return confirm(\'删除?\')">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="id" value="'.$id.'"><button type="submit" name="del_t" class="nf-act danger">删</button></form>';
            echo '</div>';
            echo '<div id="qe-'.$id.'" class="nf-qe"><form method="post">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="upd_t" value="1">';
            echo '<div class="nf-qe-g">';
            echo '<div class="f"><label>分类</label><select name="category">'; foreach($cats as $c) echo '<option value="'.esc_attr($c).'"'.selected($t['category']??'',$c,false).'>'.esc_html($c).'</option>'; echo '</select></div>';
            echo '<div class="f"><label>名称</label><input type="text" name="name" value="'.esc_attr($t['name']??'').'" required></div>';
            echo '<div class="f"><label>链接</label><input type="url" name="url" value="'.esc_attr($t['url']??'').'" required></div>';
            echo '<div class="f"><label>Logo</label><input type="url" name="logo" value="'.esc_attr($t['logo']??'').'"></div><div class="f"><label>简介</label><input type="text" name="description" value="'.esc_attr($t['description']??'').'"></div>';
            echo '<div class="f"><label>排序(0-1000)</label><input type="number" name="priority" value="'.esc_attr($t['priority']??0).'" min="0" max="1000" class="nf-in" style="width:100%"></div>';
            echo '<div style="display:flex;gap:5px"><button type="submit" class="nf-btn nf-btn-p nf-btn-sm">保存</button><button type="button" class="nf-btn nf-btn-s nf-btn-sm" onclick="document.getElementById(\'qe-'.$id.'\').classList.remove(\'show\')">取消</button></div>';
            echo '</div></form></div></td></tr>';
        }
        
        if(empty($tools)) echo '<tr><td colspan="8" style="text-align:center;color:var(--mut);padding:24px">暂无工具</td></tr>';
        echo '</tbody></table></form>';
        
        echo '<form method="post" class="nf-add">'.wp_nonce_field('nr','nr',true,false);
        echo '<div class="f"><label>分类</label><select name="tc" required class="nf-in"><option value="">选择</option>'; foreach($cats as $c) echo '<option value="'.esc_attr($c).'">'.esc_html($c).'</option>'; echo '</select></div>';
        echo '<div class="f"><label>名称</label><input type="text" name="tn" class="nf-in" placeholder="工具名称" required></div>';
        echo '<div class="f"><label>链接</label><input type="url" name="tu" class="nf-in" placeholder="https://..." required></div>';
        echo '<div class="f"><label>Logo URL</label><input type="url" name="tl" class="nf-in" placeholder="图标地址"></div>';
        echo '<div><label>&nbsp;</label><button type="submit" name="add_t" class="nf-btn nf-btn-p" style="width:100%">+ 添加工具</button></div></form></div></div>';
        
        echo '<script>function upd(){var c=document.querySelectorAll(".tool-cb:checked").length;document.getElementById("sel-cnt").textContent=c;document.getElementById("bulk-bar").classList.toggle("show",c>0)}</script>';
    }
    public function settings_page() {
        if (isset($_POST['save']) && wp_verify_nonce($_POST['nonce'], 'nf_settings')) {
            update_option('newsflash_settings', ['article_template'=>sanitize_text_field($_POST['at']??'sina'),'timeline_template'=>sanitize_text_field($_POST['tt']??'sina'),'timeline_position'=>sanitize_text_field($_POST['tp']??'left'),'show_footer'=>!empty($_POST['sf']),'posts_per_page'=>max(1,(int)($_POST['pp']??10)),'custom_css'=>$_POST['css']??'']);
            echo '<div class="notice notice-success"><p>已保存</p></div>';
        }
        if (isset($_POST['reset_key']) && wp_verify_nonce($_POST['nonce'], 'nf_settings')) { update_option('newsflash_api_key', wp_generate_uuid4()); echo '<div class="notice notice-success"><p>API Key已重置</p></div>'; }
        $s = get_option('newsflash_settings',[]); $api_key = get_option('newsflash_api_key','');
        $tpls = ['sina'=>'📰新浪财经','default'=>'⚪简约白','dark'=>'⚫暗夜','cyberpunk'=>'🟣赛博朋克','glass'=>'🔵毛玻璃','pro'=>'💼专业商务','tech'=>'🚀科技感','bloomberg'=>'📊Bloomberg','elegant'=>'✨优雅','premium'=>'💎Premium','minimal'=>'⬜极简','editorial'=>'📝编辑风','brutalist'=>'🧱粗野主义','retro'=>'📜复古','neon'=>'🌈霓虹','nature'=>'🌿自然','luxury'=>'👑奢侈品','startup'=>'🚀创业风','govt'=>'🏛️政府','magazine'=>'📰杂志','custom'=>'🎨自定义'];
        echo '<div class="wrap"><h1>快讯设置 <small style="font-size:11px;color:#666">v'.NEWSFLASH_VERSION.'</small></h1>';
        echo '<style>.nf-c{background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:20px;margin-bottom:20px}.nf-c h2{margin:0 0 16px;font-size:14px;border-bottom:1px solid #eee;padding-bottom:10px}.nf-r{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f0f0f0}.nf-r:last-child{border-bottom:none}.nf-r label{font-weight:500}.nf-r select{padding:6px 10px;border:1px solid #8c8f94;border-radius:4px;min-width:180px}.nf-r input[type=number]{width:70px;padding:6px 8px;border:1px solid #8c8f94;border-radius:4px}.sw{position:relative;width:44px;height:24px}.sw input{opacity:0}.sl{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;border-radius:24px}.sl:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:#fff;border-radius:50%}input:checked+.sl{background:#2271b1}input:checked+.sl:before{transform:translateX(20px)}</style>';
        echo '<form method="post">'.wp_nonce_field('nf_settings','nonce',true,false);
        echo '<div class="nf-c"><h2>📰 模板</h2>';
        echo '<div class="nf-r"><label>文章模板</label><select name="at">'; foreach($tpls as $key=>$val) echo '<option value="'.$key.'"'.selected($s['article_template']??'sina',$key,false).'>'.$val.'</option>'; echo '</select></div>';
        echo '<div class="nf-r"><label>时间线模板</label><select name="tt">'; foreach($tpls as $key=>$val) echo '<option value="'.$key.'"'.selected($s['timeline_template']??'sina',$key,false).'>'.$val.'</option>'; echo '</select></div>';
        echo '<div class="nf-r"><label>每页数量</label><input type="number" name="pp" value="'.esc_attr($s['posts_per_page']??10).'"></div>';
        echo '<div class="nf-r"><label>时间线位置</label><select name="tp"><option value="left"'.selected($s['timeline_position']??'left','left',false).'>左</option><option value="center"'.selected($s['timeline_position']??'left','center',false).'>中</option><option value="right"'.selected($s['timeline_position']??'left','right',false).'>右</option></select></div>';
        echo '<div class="nf-r"><label>显示页脚</label><label class="sw"><input type="checkbox" name="sf" value="1"'.checked(!empty($s['show_footer']),true,false).'><span class="sl"></span></label></div></div>';
        echo '<div class="nf-c"><h2>🔑 API Key</h2><code style="background:#f6f7f7;padding:8px 12px;border-radius:4px">'.esc_html($api_key).'</code> <button type="submit" name="reset_key" class="button" onclick="return confirm(\'确定重置？\')">重置</button></div>';
        echo '<p style="margin:0"><button type="submit" name="save" class="button button-primary">保存</button></p></form></div>';
    }
    public function api_docs_page() { $k=get_option('newsflash_api_key',''); echo '<div class="wrap"><h1>API</h1><p>Key: <code>'.esc_html($k).'</code></p><pre style="background:#f6f7f7;padding:16px;border-radius:8px">curl -X POST '.rest_url('newsflash/v1/posts').' -H "Content-Type: application/json" -H "X-NewsFlash-Key: '.$k.'" -d \'{"title":"标题","content":"内容"}\'</pre></div>'; }
    public function preview_page() { echo '<div class="wrap"><h1>预览</h1>'.do_shortcode('[newsflash_timeline count=5]').'</div>'; }
}
function newsflash_plugin() { return NewsFlash_Plugin::instance(); }
newsflash_plugin();
