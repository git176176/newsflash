<?php
/**
 * Plugin Name: NewsFlash 快讯插件
 * Version: 1.11.6
 * Description: 新浪财经风格快讯插件，支持20套模板、底部推荐位（多样式Logo分类）、REST API、SEO优化。v1.11 全新后台（仪表盘 + 控制台 Tabs + 推荐位曝光/点击统计 + 紫粉鲜艳配色）
 */

if (!defined('ABSPATH')) exit;
define('NEWSFLASH_VERSION', '1.11.6');
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

function newsflash_get_recommend_section($position = null, $page_type = 'single') {
    $settings = get_option('newsflash_recommend_settings', []);
    if (empty($settings['enabled'])) return '';

    // 页面类型开关：字段存在且为 false 时跳过，未设字段时默认显示
    $page_enabled_key = 'show_' . $page_type;
    if (isset($settings[$page_enabled_key]) && empty($settings[$page_enabled_key])) return '';

    // 位置匹配：position 参数非空时，必须与设置一致或在 both 模式下
    if ($position !== null) {
        $setting_position = $settings['position'] ?? 'bottom';
        if ($setting_position !== 'both' && $setting_position !== $position) return '';
    }
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

    // 累计曝光（仅前台、非搜索引擎机器人粗判；高流量可后续接 transient/队列）
    if (!is_admin()) {
        $stats = get_option('newsflash_recommend_stats', []);
        $dirty = false;
        foreach ($enabled_tools as $tool) {
            $tid = $tool['id'] ?? '';
            if ($tid === '') continue;
            if (!isset($stats[$tid])) $stats[$tid] = ['v' => 0, 'c' => 0];
            $stats[$tid]['v'] = (int)$stats[$tid]['v'] + 1;
            $dirty = true;
        }
        if ($dirty) update_option('newsflash_recommend_stats', $stats, false);
    }
    
    $title = esc_html($settings['title'] ?: '热门AI工具推荐');
    
    // 获取该页面类型的每行卡片数
    $per_row_key = 'per_row_' . $page_type;
    $per_row = intval($settings[$per_row_key] ?? $settings['per_row'] ?? 6);
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
        $tid  = esc_attr($tool['id'] ?? '');
        $cat = esc_html($tool['category'] ?: '');
        $desc = $show_desc && !empty($tool['description']) ? '<span class="nf-rec-desc">' . esc_html($tool['description']) . '</span>' : '';
        $url = esc_url($tool['url'] ?: '#');
        $logo = $show_logo && !empty($tool['logo']) ? '<img src="' . esc_url($tool['logo']) . '" alt="' . esc_attr($name) . '" class="nf-rec-logo" loading="lazy">' : '';
        $cta = $show_cta ? '<a href="' . $url . '" class="nf-rec-cta" target="_blank" rel="noopener">' . $cta_text . ' →</a>' : '';
        $cat_tag = $cat ? '<span class="nf-rec-cat">' . $cat . '</span>' : '';

        $html .= '<div class="nf-recommend-card" data-tool-id="'.$tid.'" data-url="'.$url.'" onclick="window.open(this.dataset.url,\'_blank\',\'noopener\')" style="cursor:pointer">';
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
        add_action('pre_get_posts', [$this, 'modify_main_query']);
        add_action('rest_api_init', [$this, 'register_api']);
        add_action('wp_enqueue_scripts', [$this, 'load_assets']);
        add_filter('template_redirect', [$this, 'load_template'], 1);
        add_shortcode('newsflash_timeline', [$this, 'timeline_shortcode']);
        add_shortcode('newsflash_list', [$this, 'list_shortcode']);
        add_shortcode('newsflash_recommend', [$this, 'recommend_shortcode']);
        add_action('admin_menu', [$this, 'create_admin_menu'], 1);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'redirect_legacy_pages'], 1);
        add_action('add_meta_boxes', [$this, 'add_keywords_metabox']);
        add_action('save_post_newsflash', [$this, 'save_keywords_metabox']);
        add_action('admin_init', [$this, 'handle_recommend_form']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    public function activate() {
        $this->register_cpt(); flush_rewrite_rules();
        if (get_option('newsflash_api_key') === false) add_option('newsflash_api_key', wp_generate_uuid4());
        if (get_option('newsflash_settings') === false) add_option('newsflash_settings', ['article_template'=>'sina','timeline_template'=>'sina','timeline_position'=>'left','show_footer'=>true,'posts_per_page'=>10,'custom_css'=>'']);
        if (get_option('newsflash_recommend_settings') === false) add_option('newsflash_recommend_settings', [
            'enabled'=>false,
            'title'=>'热门AI工具推荐',
            'per_row'=>6,
            'show_description'=>true,
            'show_logo'=>true,
            'show_cta'=>true,
            'cta_text'=>'访问',
            'card_style'=>'card',
            'card_bg'=>'#ffffff',
            'position'=>'bottom',
            // 页面类型开关
            'show_single'=>true,
            'show_timeline'=>true,
            'show_daily'=>true,
            'show_category'=>true,
            // 页面类型每行卡片数
            'per_row_single'=>6,
            'per_row_timeline'=>6,
            'per_row_daily'=>6,
            'per_row_category'=>6
        ]);
        if (get_option('newsflash_recommend_tools') === false) add_option('newsflash_recommend_tools', []);
        if (get_option('newsflash_recommend_categories') === false) add_option('newsflash_recommend_categories', ['AI写作','AI设计','AI编程','AI视频','AI音频','AI办公','AI对话','AI开发平台','AI数字人','AI效率工具','其他']);
        // 统计数据 — autoload=no 避免每次请求都读
        if (get_option('newsflash_recommend_stats') === false) add_option('newsflash_recommend_stats', [], '', 'no');
        // 页面访问量统计（按页面类型）— autoload=no
        if (get_option('newsflash_page_views') === false) add_option('newsflash_page_views', ['single'=>0,'timeline'=>0,'daily'=>0,'category'=>0], '', 'no');
    }
    public function register_cpt() {
        register_post_type('newsflash', ['label'=>'快讯','labels'=>['name'=>'快讯','singular_name'=>'快讯','add_new'=>'发布快讯','add_new_item'=>'发布新快讯','edit_item'=>'编辑快讯','new_item'=>'新快讯','view_item'=>'查看快讯','search_items'=>'搜索快讯','not_found'=>'没有找到快讯'],'public'=>true,'show_ui'=>true,'show_in_menu'=>false,'supports'=>['title','editor','author','thumbnail','excerpt','custom-fields'],'has_archive'=>true,'rewrite'=>['slug'=>'newsflash','with_front'=>true],'show_in_rest'=>true,'rest_base'=>'newsflash']);
        register_taxonomy('newsflash_category', 'newsflash', ['label'=>'快讯分类','labels'=>['name'=>'快讯分类'],'hierarchical'=>true,'show_ui'=>true,'show_admin_column'=>true,'show_in_rest'=>true,'public'=>true,'rewrite'=>['slug'=>'newsflash-category']]);
    }
    public function add_rewrite_rules() { add_rewrite_rule('newsflash/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?post_type=newsflash&newsflash_date=$matches[1]', 'top'); }
    public function add_query_vars($vars) { $vars[] = 'newsflash_date'; return $vars; }
    public function modify_main_query($q) {
        if (is_admin() || !$q->is_main_query()) return;
        if ($q->is_post_type_archive('newsflash')) {
            $s = get_option('newsflash_settings', []);
            $q->set('posts_per_page', max(1, (int)($s['posts_per_page'] ?? 10)));
        }
    }
    /** 页面访问量累计 — 每次前台渲染对应页面类型 +1 */
    private function bump_page_view($type) {
        $valid = ['single', 'timeline', 'daily', 'category'];
        if (!in_array($type, $valid, true)) return;
        $pv = get_option('newsflash_page_views', []);
        $pv[$type] = (int)($pv[$type] ?? 0) + 1;
        update_option('newsflash_page_views', $pv, false);
    }
    public function add_keywords_metabox() { add_meta_box('newsflash_keywords', '快讯关键词', [$this, 'render_keywords_metabox'], 'newsflash', 'side', 'default'); }
    public function render_keywords_metabox($post) { wp_nonce_field('newsflash_keywords', 'newsflash_keywords_nonce'); $k = get_post_meta($post->ID, 'newsflash_keywords', true); echo '<input type="text" name="newsflash_keywords" value="'.esc_attr($k).'" style="width:100%;padding:6px;border:1px solid #8c8f94;border-radius:4px"><p style="margin:4px 0 0;color:#646970;font-size:11px">英文逗号分隔</p>'; }
    public function save_keywords_metabox($pid) { if (!isset($_POST['newsflash_keywords_nonce']) || !wp_verify_nonce($_POST['newsflash_keywords_nonce'], 'newsflash_keywords')) return; if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return; if (isset($_POST['newsflash_keywords'])) update_post_meta($pid, 'newsflash_keywords', sanitize_text_field($_POST['newsflash_keywords'])); }
    public function register_api() {
        register_rest_route('newsflash/v1', '/posts', ['methods'=>'POST','callback'=>[$this,'api_create'],'permission_callback'=>function(){
            $stored = get_option('newsflash_api_key', '');
            $sent   = isset($_SERVER['HTTP_X_NEWSFLASH_KEY']) ? (string)$_SERVER['HTTP_X_NEWSFLASH_KEY'] : '';
            return $stored !== '' && $sent !== '' && hash_equals($stored, $sent);
        }]);
        register_rest_route('newsflash/v1', '/posts/(?P<id>\d+)', ['methods'=>'GET','callback'=>[$this,'api_get'],'permission_callback'=>'__return_true']);
        register_rest_route('newsflash/v1', '/categories', ['methods'=>'GET','callback'=>[$this,'api_categories'],'permission_callback'=>'__return_true']);
        register_rest_route('newsflash/v1', '/recommend-tools', ['methods'=>'GET','callback'=>[$this,'api_recommend_tools'],'permission_callback'=>'__return_true']);
        register_rest_route('newsflash/v1', '/track', [
            'methods' => 'POST',
            'callback' => [$this, 'api_track'],
            'permission_callback' => '__return_true',
        ]);
    }
    public function api_create($req) {
        $p = $req->get_json_params(); $title = sanitize_text_field($p['title'] ?? ''); $content = wp_kses_post($p['content'] ?? '');
        if (!$title || !$content) return new WP_Error('error', '标题和内容不能为空', ['status'=>400]);
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
    /** 公开埋点端点：仅记录推荐位 click（view 由 PHP 渲染端记录） */
    public function api_track($req) {
        // 同时支持 query params 与 JSON body — sendBeacon 的 body 解析在部分环境会失败，
        // get_param() 会按 URL→query→body→JSON 顺序取值，最稳妥
        $tool_id = sanitize_text_field((string)$req->get_param('tool_id'));
        $type    = sanitize_text_field((string)$req->get_param('type'));
        if ($tool_id === '' || $type !== 'click') return new WP_Error('bad_request', 'invalid params', ['status' => 400]);

        $stats = get_option('newsflash_recommend_stats', []);
        if (!isset($stats[$tool_id])) $stats[$tool_id] = ['v' => 0, 'c' => 0];
        $stats[$tool_id]['c'] = (int)$stats[$tool_id]['c'] + 1;
        update_option('newsflash_recommend_stats', $stats, false);
        return ['ok' => true, 'clicks' => $stats[$tool_id]['c']];
    }
    public function load_assets() {
        if (is_singular('newsflash') || is_post_type_archive('newsflash') || is_tax('newsflash_category')) {
            wp_enqueue_style('newsflash', NEWSFLASH_PLUGIN_URL . 'assets/css/newsflash.css', [], NEWSFLASH_VERSION);
            $s = get_option('newsflash_settings', []);
            if (empty($s['show_footer'])) wp_add_inline_style('newsflash', 'footer.main-footer,footer.footer-stick,#footer,.site-footer,.main-footer,.wp-footer{display:none!important}');
            if (!empty($s['custom_css'])) wp_add_inline_style('newsflash', $s['custom_css']);
            // 推荐位前台 JS（含 click 跟踪）
            // 注意：必须 false（head 加载）。若用 footer，当 show_footer=false 时
            // load_template 不调用 get_footer()→wp_footer() 不触发→脚本永不输出→点击不上报。
            wp_enqueue_script('newsflash', NEWSFLASH_PLUGIN_URL . 'assets/js/newsflash.js', [], NEWSFLASH_VERSION, false);
            wp_add_inline_script('newsflash', 'window.NF_TRACK_URL=' . wp_json_encode(rest_url('newsflash/v1/track')) . ';', 'before');
        }
    }
    public function load_template() {
        $settings = get_option('newsflash_settings', []);
        if (is_singular('newsflash')) {
            $this->bump_page_view('single');
            $pid = get_queried_object_id(); $kw = get_post_meta($pid, 'newsflash_keywords', true);
            if ($kw) add_action('wp_head', function() use ($kw) { echo '<meta name="keywords" content="' . esc_attr($kw) . '">' . "\n"; }, 1);
            $tpl = $settings['article_template'] ?? 'sina';
            add_filter('body_class', function($c) use ($tpl) { $c[] = 't-'.$tpl; $c[] = 'nf-single'; return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/single-' . $tpl . '.php';
            if (!file_exists($file)) $file = NEWSFLASH_PLUGIN_DIR . 'templates/single-sina.php';
            if (file_exists($file)) { 
                get_header(); 
                $this->output_breadcrumb('single', $pid);
                // 顶部推荐位
                echo newsflash_get_recommend_section('top', 'single');
                include $file; 
                // 底部推荐位
                echo newsflash_get_recommend_section('bottom', 'single');
                if ($settings['show_footer'] ?? true) get_footer(); 
                exit; 
            }
        }
        $date_str = get_query_var('newsflash_date');
        if (!empty($date_str) && is_string($date_str)) {
            $this->bump_page_view('daily');
            $do = date_create($date_str); $dd = $do ? date_format($do, 'Y年m月d日') : $date_str;
            add_action('wp_head', function() use ($dd) { echo '<title>'.$dd.' AI资讯盘点 - '.get_bloginfo('name').'</title><meta name="description" content="'.$dd.' AI行业资讯">'."\n"; }, 0);
            add_filter('body_class', function($c) { $c[] = 't-'.(get_option('newsflash_settings')['timeline_template']??'sina'); $c[] = 'nf-archive nf-daily-recap'; return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-daily.php';
            if (file_exists($file)) { 
                ob_start(); 
                get_header(); 
                $this->output_breadcrumb('daily');
                // 顶部推荐位
                echo newsflash_get_recommend_section('top', 'daily');
                include $file; 
                // 底部推荐位
                echo newsflash_get_recommend_section('bottom', 'daily');
                if ($settings['show_footer']??true) get_footer(); 
                $out = ob_get_clean(); 
                $out = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>'.$dd.' AI资讯盘点 - '.get_bloginfo('name').'</title>', $out); 
                echo $out; 
                exit; 
            }
        }
        if (is_tax('newsflash_category')) {
            $this->bump_page_view('category');
            $term = get_queried_object();
            add_action('wp_head', function() use ($term) { echo '<title>'.$term->name.' - AI快讯 - '.get_bloginfo('name').'</title><meta name="description" content="浏览'.$term->name.'分类快讯">'."\n"; }, 0);
            add_filter('body_class', function($c) { $c[] = 't-'.(get_option('newsflash_settings')['timeline_template']??'sina'); $c[] = 'nf-archive nf-category'; return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-category.php';
            if (file_exists($file)) { 
                ob_start(); 
                get_header(); 
                $this->output_breadcrumb('category');
                // 顶部推荐位
                echo newsflash_get_recommend_section('top', 'category');
                include $file; 
                // 底部推荐位
                echo newsflash_get_recommend_section('bottom', 'category');
                if ($settings['show_footer']??true) get_footer(); 
                $out = ob_get_clean(); 
                $out = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>'.$term->name.' - AI快讯</title>', $out); 
                echo $out; 
                exit; 
            }
        }
        if (is_post_type_archive('newsflash')) {
            $this->bump_page_view('timeline');
            add_action('wp_head', function() { echo '<title>AI快讯 - '.get_bloginfo('name').'</title><meta name="description" content="实时更新AI行业资讯">'."\n"; }, 0);
            $tpl = $settings['timeline_template'] ?? 'sina';
            add_filter('body_class', function($c) use ($tpl, $settings) { $c[] = 't-'.$tpl; $c[] = 'nf-archive nf-position-'.($settings['timeline_position']??'left'); return $c; });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-' . $tpl . '.php';
            if (!file_exists($file)) $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-sina.php';
            if (file_exists($file)) { 
                ob_start(); 
                get_header(); 
                $this->output_breadcrumb('');
                // 顶部推荐位
                echo newsflash_get_recommend_section('top', 'timeline');
                include $file; 
                // 底部推荐位
                echo newsflash_get_recommend_section('bottom', 'timeline');
                if ($settings['show_footer']??true) get_footer(); 
                $out = ob_get_clean(); 
                $out = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>AI快讯 - '.get_bloginfo('name').'</title>', $out); 
                echo $out; 
                exit; 
            }
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
        // 新菜单（顺序：仪表盘 → 内容管理 → 控制台 → 预览）
        add_submenu_page('edit.php?post_type=newsflash', 'NewsFlash 仪表盘', '📊 仪表盘', 'manage_options', 'nf_dashboard', [$this, 'dashboard_page']);
        add_submenu_page('edit.php?post_type=newsflash', '所有快讯', '所有快讯', 'manage_options', 'edit.php?post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '发布快讯', '发布快讯', 'manage_options', 'post-new.php?post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '快讯分类', '快讯分类', 'manage_options', 'edit-tags.php?taxonomy=newsflash_category&post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', 'NewsFlash 控制台', '⚙️ 控制台', 'manage_options', 'nf_console', [$this, 'console_page']);
        add_submenu_page('edit.php?post_type=newsflash', '时间线预览', '时间线预览', 'manage_options', 'nf_preview', [$this, 'preview_page']);
        // 旧菜单：隐藏（parent = null）但回调仍存在，避免外部链接 404；实际上 redirect_legacy_pages 会更早拦截
        add_submenu_page(null, '底部推荐位', '底部推荐位', 'manage_options', 'nf_recommend', [$this, 'recommend_page']);
        add_submenu_page(null, '快讯设置', '设置', 'manage_options', 'nf_settings', [$this, 'settings_page']);
        add_submenu_page(null, 'API 文档', 'API 文档', 'manage_options', 'nf_api', [$this, 'api_docs_page']);
    }

    /** 拦截旧菜单 URL，重定向到新控制台对应 Tab */
    public function redirect_legacy_pages() {
        if (!is_admin() || !isset($_GET['page'])) return;
        $map = [
            'nf_recommend' => 'recommend',
            'nf_settings'  => 'display',
            'nf_api'       => 'api',
        ];
        $page = sanitize_key($_GET['page']);
        if (isset($map[$page])) {
            wp_safe_redirect(admin_url('admin.php?page=nf_console&tab=' . $map[$page]), 301);
            exit;
        }
    }

    /** 仅在 NewsFlash 自有 admin 页加载 admin CSS/JS */
    public function enqueue_admin_assets($hook) {
        if (!isset($_GET['page'])) return;
        $page = sanitize_key($_GET['page']);
        if (!in_array($page, ['nf_dashboard', 'nf_console', 'nf_recommend', 'nf_settings', 'nf_api'], true)) return;
        wp_enqueue_style('newsflash-admin', NEWSFLASH_PLUGIN_URL . 'assets/css/admin.css', [], NEWSFLASH_VERSION);
        wp_enqueue_script('newsflash-admin', NEWSFLASH_PLUGIN_URL . 'assets/js/admin.js', [], NEWSFLASH_VERSION, true);
    }

    /** 控制台主入口（Tab 路由） */
    public function console_page() {
        if (!current_user_can('manage_options')) return;
        $tabs = [
            'display'   => ['label' => '📰 模板与显示', 'renderer' => 'render_tab_display'],
            'recommend' => ['label' => '📌 推荐位',     'renderer' => 'render_tab_recommend'],
            'api'       => ['label' => '🔑 API',        'renderer' => 'render_tab_api'],
            'advanced'  => ['label' => '⚙️ 高级',        'renderer' => 'render_tab_advanced'],
        ];
        $current = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'display';
        if (!isset($tabs[$current])) $current = 'display';

        echo '<div class="wrap nfui-wrap"><h1>⚙️ NewsFlash 控制台 <span class="nfui-ver">v'.esc_html(NEWSFLASH_VERSION).'</span></h1>';
        if (isset($_GET['ok'])) {
            $msgs = [1=>'✅ 已保存',2=>'✅ 已添加',3=>'✅ 已删除',4=>'✅ 已添加',5=>'✅ 已删除',6=>'✅ 已切换',7=>'✅ 已更新',8=>'✅ 批量删除',9=>'✅ 批量启用',10=>'✅ 批量禁用'];
            $ok = (int)$_GET['ok'];
            if (isset($msgs[$ok])) echo '<div class="notice notice-success is-dismissible"><p>'.esc_html($msgs[$ok]).'</p></div>';
        }
        echo '<nav class="nfui-tabs">';
        foreach ($tabs as $key => $t) {
            $url = admin_url('admin.php?page=nf_console&tab=' . $key);
            $active = ($key === $current) ? ' is-active' : '';
            echo '<a class="nfui-tab'.$active.'" href="'.esc_url($url).'">'.esc_html($t['label']).'</a>';
        }
        echo '</nav>';

        $renderer = $tabs[$current]['renderer'];
        if (method_exists($this, $renderer)) {
            $this->$renderer();
        } else {
            echo '<div class="nfui-card"><div class="nfui-card-bd"><p>该 Tab 内容即将上线。</p></div></div>';
        }
        echo '</div>';
    }

    /** Dashboard 页 */
    public function dashboard_page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;

        // 统计数据
        $counts = wp_count_posts('newsflash');
        $total = isset($counts->publish) ? (int)$counts->publish : 0;
        $today = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish' AND DATE(post_date)=CURDATE()",
            'newsflash'
        ));
        $tools_count = count(get_option('newsflash_recommend_tools', []));
        $rec_settings = get_option('newsflash_recommend_settings', []);
        $rec_enabled = !empty($rec_settings['enabled']);
        $api_key = get_option('newsflash_api_key', '');

        // 7 天发布趋势
        $trend = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(post_date) AS d, COUNT(*) AS c
             FROM {$wpdb->posts}
             WHERE post_type=%s AND post_status='publish'
               AND post_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
             GROUP BY DATE(post_date)",
            'newsflash'
        ), OBJECT_K);
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $days[] = ['date' => $date, 'count' => isset($trend[$date]) ? (int)$trend[$date]->c : 0];
        }
        $max = max(1, max(array_column($days, 'count')));

        // 最近 5 条
        $recent = get_posts(['post_type'=>'newsflash','posts_per_page'=>5,'orderby'=>'date','order'=>'DESC']);

        echo '<div class="wrap nfui-wrap"><h1>📊 NewsFlash 仪表盘 <span class="nfui-ver">v'.esc_html(NEWSFLASH_VERSION).'</span></h1>';

        // ---- 4 统计卡 ----
        echo '<div class="nfui-stat-grid">';
        echo '<div class="nfui-stat"><div class="nfui-stat-label">总快讯数</div><div class="nfui-stat-value">'.number_format($total).'</div><div class="nfui-stat-sub">已发布</div></div>';
        echo '<div class="nfui-stat"><div class="nfui-stat-label">今日发布</div><div class="nfui-stat-value">'.number_format($today).'</div><div class="nfui-stat-sub">'.esc_html(date('Y-m-d')).'</div></div>';
        echo '<div class="nfui-stat"><div class="nfui-stat-label">推荐位工具</div><div class="nfui-stat-value">'.number_format($tools_count).'</div><div class="nfui-stat-sub">'.($rec_enabled ? '✓ 已启用' : '○ 未启用').'</div></div>';
        echo '<div class="nfui-stat"><div class="nfui-stat-label">API 状态</div><div class="nfui-stat-value">'.($api_key ? '✓' : '✗').'</div><div class="nfui-stat-sub">'.($api_key ? 'Key 已配置' : '未配置').'</div></div>';
        echo '</div>';

        // ---- 7 天趋势图 ----
        echo '<div class="nfui-card"><div class="nfui-card-hd">📈 最近 7 天发布趋势 <span class="nfui-meta">'.number_format(array_sum(array_column($days,'count'))).' 条</span></div><div class="nfui-card-bd" style="padding:0">';
        echo '<div class="nfui-chart">';
        foreach ($days as $d) {
            $h = ($d['count'] / $max) * 100;
            echo '<div class="nfui-chart-col">';
            echo '<div class="nfui-chart-tip">'.esc_html($d['date']).'：'.(int)$d['count'].' 条</div>';
            echo '<div class="nfui-chart-bar" data-count="'.(int)$d['count'].'" style="height:'.number_format($h,1,'.','').'%"></div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="nfui-chart-labels">';
        foreach ($days as $d) {
            echo '<div>'.esc_html(date('m/d', strtotime($d['date']))).'</div>';
        }
        echo '</div>';
        echo '</div></div>';

        // ---- 快速操作 ----
        echo '<div class="nfui-card"><div class="nfui-card-hd">⚡ 快速操作</div><div class="nfui-card-bd"><div class="nfui-quick">';
        echo '<a class="nfui-btn nfui-btn-p" href="'.esc_url(admin_url('post-new.php?post_type=newsflash')).'">＋ 发布新快讯</a>';
        echo '<a class="nfui-btn nfui-btn-s" href="'.esc_url(admin_url('admin.php?page=nf_console&tab=recommend')).'">📌 管理推荐位</a>';
        echo '<a class="nfui-btn nfui-btn-s" href="'.esc_url(admin_url('admin.php?page=nf_console&tab=display')).'">📰 模板设置</a>';
        echo '<a class="nfui-btn nfui-btn-s" href="'.esc_url(admin_url('admin.php?page=nf_console&tab=api')).'">🔑 API 文档</a>';
        echo '<a class="nfui-btn nfui-btn-s" href="'.esc_url(get_post_type_archive_link('newsflash')).'" target="_blank">🔗 查看时间线</a>';
        echo '</div></div></div>';

        // ---- 推荐位表现概览 ----
        $rec_stats = get_option('newsflash_recommend_stats', []);
        $tools_map = [];
        foreach (get_option('newsflash_recommend_tools', []) as $tool) {
            if (!empty($tool['id'])) $tools_map[$tool['id']] = $tool;
        }
        $total_v = $total_c = 0;
        $rows = [];
        foreach ($rec_stats as $tid => $s2) {
            $v = (int)($s2['v'] ?? 0); $c = (int)($s2['c'] ?? 0);
            $total_v += $v; $total_c += $c;
            $name = isset($tools_map[$tid]) ? ($tools_map[$tid]['name'] ?? '') : '';
            $cat  = isset($tools_map[$tid]) ? ($tools_map[$tid]['category'] ?? '') : '';
            $logo = isset($tools_map[$tid]) ? ($tools_map[$tid]['logo'] ?? '') : '';
            $deleted = !isset($tools_map[$tid]);
            $rows[] = ['name' => $name ?: '(已删除工具)', 'cat' => $cat, 'logo' => $logo, 'v' => $v, 'c' => $c, 'deleted' => $deleted];
        }
        // 按点击量降序，相同时按曝光降序
        usort($rows, function($a, $b) {
            if ($b['c'] !== $a['c']) return $b['c'] - $a['c'];
            return $b['v'] - $a['v'];
        });

        // 页面访问量（真实页面访问，非 产品×曝光）
        $pv = get_option('newsflash_page_views', []);
        $pv_single   = (int)($pv['single'] ?? 0);
        $pv_timeline = (int)($pv['timeline'] ?? 0);
        $pv_daily    = (int)($pv['daily'] ?? 0);
        $pv_category = (int)($pv['category'] ?? 0);
        $pv_total = $pv_single + $pv_timeline + $pv_daily + $pv_category;
        // 整体 CTR = 总点击 / 页面访问量（更贴近"每次访问带来多少推荐位点击"）
        $overall_ctr = $pv_total > 0 ? round($total_c / $pv_total * 100, 2) : 0;

        echo '<div class="nfui-card"><div class="nfui-card-hd">📌 推荐位表现 <span class="nfui-meta">累计</span></div><div class="nfui-card-bd">';
        echo '<div class="nfui-stat-grid" style="margin:0 0 16px">';
        echo '<div class="nfui-stat"><div class="nfui-stat-label">页面访问</div><div class="nfui-stat-value">'.number_format($pv_total).'</div><div class="nfui-stat-sub">快讯页 + 时间线 + 盘点</div></div>';
        echo '<div class="nfui-stat"><div class="nfui-stat-label">推荐位点击</div><div class="nfui-stat-value">'.number_format($total_c).'</div><div class="nfui-stat-sub">card click</div></div>';
        echo '<div class="nfui-stat"><div class="nfui-stat-label">整体 CTR</div><div class="nfui-stat-value">'.$overall_ctr.'<span style="font-size:18px">%</span></div><div class="nfui-stat-sub">点击 / 页面访问</div></div>';
        echo '</div>';
        // 页面访问分项
        echo '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">';
        echo '<span class="nfui-bdg nfui-bdg-info" style="font-size:12px;padding:5px 12px">📄 快讯单页 '.number_format($pv_single).'</span>';
        echo '<span class="nfui-bdg nfui-bdg-info" style="font-size:12px;padding:5px 12px">📰 时间线 '.number_format($pv_timeline).'</span>';
        echo '<span class="nfui-bdg nfui-bdg-info" style="font-size:12px;padding:5px 12px">📅 每日盘点 '.number_format($pv_daily).'</span>';
        echo '<span class="nfui-bdg nfui-bdg-info" style="font-size:12px;padding:5px 12px">🏷️ 分类页 '.number_format($pv_category).'</span>';
        echo '</div>';

        if ($rows) {
            echo '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">';
            echo '<div style="font-size:11px;color:var(--nfui-mut);text-transform:uppercase;letter-spacing:.06em;font-weight:600">📋 完整工具数据 · '.count($rows).' 个</div>';
            echo '<a href="'.esc_url(admin_url('admin.php?page=nf_console&tab=recommend')).'" style="font-size:12px;color:var(--nfui-p);text-decoration:none;font-weight:500">详细管理 →</a>';
            echo '</div>';
            echo '<div style="max-height:420px;overflow-y:auto;border:1px solid var(--nfui-brd-soft);border-radius:var(--nfui-r-md)">';
            echo '<table class="nfui-tbl" style="margin:0">';
            echo '<thead style="position:sticky;top:0;z-index:1"><tr>';
            echo '<th style="width:36px;text-align:center">#</th>';
            echo '<th>工具</th>';
            echo '<th style="width:90px">分类</th>';
            echo '<th style="width:90px;text-align:right">曝光</th>';
            echo '<th style="width:90px;text-align:right">点击</th>';
            echo '<th style="width:80px;text-align:right">CTR</th>';
            echo '</tr></thead><tbody>';
            foreach ($rows as $i => $r) {
                $rctr = $r['v'] > 0 ? round($r['c'] / $r['v'] * 100, 1) : 0;
                $medal = $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i + 1)));
                $logo_html = !empty($r['logo']) ? '<img src="'.esc_url($r['logo']).'" alt="" style="width:20px;height:20px;border-radius:4px;object-fit:contain;background:var(--nfui-bg);border:1px solid var(--nfui-brd-soft)">' : '<span style="display:inline-flex;width:20px;height:20px;align-items:center;justify-content:center;font-size:12px;background:var(--nfui-bg);border-radius:4px;border:1px solid var(--nfui-brd-soft)">🔧</span>';
                $name_style = $r['deleted'] ? 'color:var(--nfui-mut);text-decoration:line-through' : '';
                echo '<tr>';
                echo '<td style="text-align:center;font-weight:600;color:var(--nfui-mut);font-size:12px">'.$medal.'</td>';
                echo '<td><div style="display:flex;align-items:center;gap:8px">'.$logo_html.'<span style="font-weight:500;'.$name_style.'">'.esc_html($r['name']).'</span></div></td>';
                echo '<td>'.($r['cat'] ? '<span class="nfui-bdg nfui-bdg-info">'.esc_html($r['cat']).'</span>' : '<span style="color:var(--nfui-mut);font-size:11px">—</span>').'</td>';
                echo '<td style="text-align:right;font-variant-numeric:tabular-nums">'.number_format($r['v']).'</td>';
                echo '<td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600">'.number_format($r['c']).'</td>';
                echo '<td style="text-align:right;font-variant-numeric:tabular-nums;font-weight:700;color:var(--nfui-p)">'.$rctr.'%</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<div style="text-align:center;padding:32px;color:var(--nfui-mut);background:var(--nfui-bg-soft);border-radius:var(--nfui-r-md)">暂无统计数据。等用户开始访问带推荐位的页面后，数据会在此累积。</div>';
        }
        echo '</div></div>';

        // ---- 最近 5 条 ----
        echo '<div class="nfui-card"><div class="nfui-card-hd">📰 最近 5 条快讯 <span class="nfui-meta">'.number_format($total).' 总数</span></div><div class="nfui-card-bd" style="padding:0">';
        if ($recent) {
            echo '<table class="nfui-tbl nfui-recent"><tbody>';
            foreach ($recent as $p) {
                echo '<tr>';
                echo '<td class="date">'.esc_html(get_the_date('Y-m-d H:i', $p)).'</td>';
                echo '<td><a href="'.esc_url(get_edit_post_link($p->ID)).'">'.esc_html($p->post_title ?: '（无标题）').'</a></td>';
                echo '<td style="text-align:right"><a class="nfui-btn nfui-btn-s nfui-btn-sm" href="'.esc_url(get_permalink($p->ID)).'" target="_blank">查看</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<div style="padding:40px;text-align:center;color:var(--nfui-mut)">暂无快讯，<a href="'.esc_url(admin_url('post-new.php?post_type=newsflash')).'">立即发布第一条</a></div>';
        }
        echo '</div></div>';

        echo '</div>';
    }

    /** Tab 1: 模板与显示（迁移自老 settings_page） */
    private function render_tab_display() {
        // 处理 POST（与老 settings_page 一致逻辑，但 redirect 回 console tab）
        if (isset($_POST['save']) && wp_verify_nonce($_POST['nonce'] ?? '', 'nf_settings')) {
            update_option('newsflash_settings', [
                'article_template'  => sanitize_text_field($_POST['at'] ?? 'sina'),
                'timeline_template' => sanitize_text_field($_POST['tt'] ?? 'sina'),
                'timeline_position' => sanitize_text_field($_POST['tp'] ?? 'left'),
                'show_footer'       => !empty($_POST['sf']),
                'posts_per_page'    => max(1, (int)($_POST['pp'] ?? 10)),
                'custom_css'        => wp_strip_all_tags(wp_unslash($_POST['css'] ?? '')),
            ]);
            echo '<div class="notice notice-success is-dismissible"><p>✅ 已保存</p></div>';
        }
        $s = get_option('newsflash_settings', []);
        $tpls = ['sina'=>'📰新浪财经','default'=>'⚪简约白','dark'=>'⚫暗夜','cyberpunk'=>'🟣赛博朋克','glass'=>'🔵毛玻璃','pro'=>'💼专业商务','tech'=>'🚀科技感','bloomberg'=>'📊Bloomberg','elegant'=>'✨优雅','premium'=>'💎Premium','minimal'=>'⬜极简','editorial'=>'📝编辑风','brutalist'=>'🧱粗野主义','retro'=>'📜复古','neon'=>'🌈霓虹','nature'=>'🌿自然','luxury'=>'👑奢侈品','startup'=>'🚀创业风','govt'=>'🏛️政府','magazine'=>'📰杂志','custom'=>'🎨自定义'];

        echo '<form method="post" action="'.esc_url(admin_url('admin.php?page=nf_console&tab=display')).'">';
        echo wp_nonce_field('nf_settings', 'nonce', true, false);

        echo '<div class="nfui-card"><div class="nfui-card-hd">📰 模板</div><div class="nfui-card-bd">';
        echo '<div class="nfui-row"><label>文章模板</label><select name="at" class="nfui-select" style="min-width:200px">';
        foreach ($tpls as $k => $v) echo '<option value="'.esc_attr($k).'"'.selected($s['article_template'] ?? 'sina', $k, false).'>'.esc_html($v).'</option>';
        echo '</select></div>';
        echo '<div class="nfui-row"><label>时间线模板</label><select name="tt" class="nfui-select" style="min-width:200px">';
        foreach ($tpls as $k => $v) echo '<option value="'.esc_attr($k).'"'.selected($s['timeline_template'] ?? 'sina', $k, false).'>'.esc_html($v).'</option>';
        echo '</select></div>';
        echo '<div class="nfui-row"><label>时间线位置</label><select name="tp" class="nfui-select"><option value="left"'.selected($s['timeline_position'] ?? 'left', 'left', false).'>左</option><option value="center"'.selected($s['timeline_position'] ?? 'left', 'center', false).'>中</option><option value="right"'.selected($s['timeline_position'] ?? 'left', 'right', false).'>右</option></select></div>';
        echo '<div class="nfui-row"><label>每页数量</label><input type="number" name="pp" class="nfui-input" min="1" max="100" style="width:80px" value="'.esc_attr($s['posts_per_page'] ?? 10).'"></div>';
        echo '<div class="nfui-row"><label>显示页脚</label><label class="nfui-toggle"><input type="checkbox" name="sf" value="1"'.checked(!empty($s['show_footer']), true, false).'><span class="nfui-tg-slot"></span></label></div>';
        echo '</div></div>';

        echo '<p style="margin:0"><button type="submit" name="save" class="nfui-btn nfui-btn-p">保存</button></p>';
        echo '</form>';
    }
    /** Tab 2: 推荐位（迁移自老 recommend_page，全套 UI 重写为 nfui-* 组件） */
    private function render_tab_recommend() {
        $s     = get_option('newsflash_recommend_settings', []);
        $tools = get_option('newsflash_recommend_tools', []);
        $cats  = get_option('newsflash_recommend_categories', []);
        $stats = get_option('newsflash_recommend_stats', []);
        $all_tools = $tools;

        // 筛选
        $filter_cat    = sanitize_text_field($_GET['cat'] ?? '');
        $filter_status = sanitize_text_field($_GET['status'] ?? '');
        if ($filter_cat || $filter_status) {
            $tools = array_filter($tools, function($t) use ($filter_cat, $filter_status) {
                if ($filter_cat && ($t['category'] ?? '') !== $filter_cat) return false;
                if ($filter_status === 'enabled' && empty($t['enabled'])) return false;
                if ($filter_status === 'disabled' && !empty($t['enabled'])) return false;
                return true;
            });
        }
        usort($tools, function($a, $b) { return intval($b['priority'] ?? 0) - intval($a['priority'] ?? 0); });

        $cat_counts = array_count_values(array_map(function($t){ return $t['category'] ?? ''; }, $all_tools));
        $action_url = esc_url(admin_url('admin.php?page=nf_console&tab=recommend'));

        // ---- 全局设置（折叠）----
        echo '<div class="nfui-card"><div class="nfui-card-hd">⚙️ 全局设置</div><div class="nfui-card-bd">';
        echo '<form method="post" action="'.$action_url.'">'.wp_nonce_field('nr','nr',true,false);
        echo '<div class="nfui-grid">';
        echo '<div class="nfui-row"><label>启用推荐位</label><label class="nfui-toggle"><input type="checkbox" name="en" value="1"'.checked(!empty($s['enabled']),true,false).'><span class="nfui-tg-slot"></span></label></div>';
        echo '<div class="nfui-row"><label>标题</label><input type="text" name="title" class="nfui-input" value="'.esc_attr($s['title'] ?? '热门AI工具推荐').'" style="width:160px"></div>';
        echo '<div class="nfui-row"><label>显示位置</label><select name="pos" class="nfui-select"><option value="bottom"'.selected($s['position']??'bottom','bottom',false).'>页面底部</option><option value="top"'.selected($s['position']??'bottom','top',false).'>页面顶部</option><option value="both"'.selected($s['position']??'bottom','both',false).'>顶部 + 底部</option></select></div>';
        echo '<div class="nfui-row"><label>默认每行</label><select name="pr" class="nfui-select" style="width:80px">';
        for ($i = 4; $i <= 8; $i++) echo '<option value="'.$i.'"'.selected($s['per_row']??6,$i,false).'>'.$i.'</option>';
        echo '</select></div>';
        echo '<div class="nfui-row"><label>显示 Logo</label><label class="nfui-toggle"><input type="checkbox" name="sl" value="1"'.checked(!empty($s['show_logo']),true,false).'><span class="nfui-tg-slot"></span></label></div>';
        echo '<div class="nfui-row"><label>显示简介</label><label class="nfui-toggle"><input type="checkbox" name="sd" value="1"'.checked(!empty($s['show_description']),true,false).'><span class="nfui-tg-slot"></span></label></div>';
        echo '<div class="nfui-row"><label>显示按钮</label><label class="nfui-toggle"><input type="checkbox" name="sc" value="1"'.checked(!empty($s['show_cta']),true,false).'><span class="nfui-tg-slot"></span></label></div>';
        echo '<div class="nfui-row"><label>按钮文字</label><input type="text" name="ct" class="nfui-input" value="'.esc_attr($s['cta_text'] ?? '访问').'" style="width:80px"></div>';
        echo '<div class="nfui-row"><label>背景色</label><input type="color" name="bg" value="'.esc_attr($s['card_bg'] ?? '#ffffff').'" style="width:36px;height:28px;border:1px solid var(--nfui-brd);border-radius:4px;cursor:pointer"></div>';
        echo '<div class="nfui-row"><label>卡片样式</label><select name="cs" class="nfui-select"><option value="card"'.selected($s['card_style']??'card','card',false).'>标准</option><option value="compact"'.selected($s['card_style']??'card','compact',false).'>紧凑</option><option value="highlight"'.selected($s['card_style']??'card','highlight',false).'>突出</option><option value="minimal"'.selected($s['card_style']??'card','minimal',false).'>简约</option></select></div>';
        echo '</div>';

        // 页面类型设置
        echo '<h4 style="margin:20px 0 10px;font-size:13px;color:var(--nfui-txt);padding-top:16px;border-top:1px dashed var(--nfui-brd)">📍 各页面类型独立设置</h4>';
        echo '<div class="nfui-grid-4">';
        $page_types = [
            'single'   => ['icon' => '📄', 'label' => '快讯单页'],
            'timeline' => ['icon' => '📰', 'label' => '时间线页'],
            'daily'    => ['icon' => '📅', 'label' => '每日盘点'],
            'category' => ['icon' => '🏷️', 'label' => '分类页'],
        ];
        foreach ($page_types as $pt => $info) {
            $show_key = 'show_' . $pt;
            $row_key  = 'per_row_' . $pt;
            echo '<div class="nfui-pagetype-card">';
            echo '<div class="nfui-pagetype-card-hd">';
            echo '<span class="title">'.$info['icon'].' '.esc_html($info['label']).'</span>';
            echo '<label class="nfui-toggle"><input type="checkbox" name="'.$show_key.'" value="1"'.checked(!empty($s[$show_key]),true,false).'><span class="nfui-tg-slot"></span></label>';
            echo '</div>';
            echo '<div class="nfui-pagetype-card-row"><label>每行</label><select name="'.$row_key.'" class="nfui-select" style="padding:4px 8px">';
            for ($i = 4; $i <= 8; $i++) echo '<option value="'.$i.'"'.selected($s[$row_key]??6,$i,false).'>'.$i.'</option>';
            echo '</select></div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<p style="margin:16px 0 0"><button type="submit" name="save_set" class="nfui-btn nfui-btn-p">保存设置</button></p>';
        echo '</form></div></div>';

        // ---- 分类管理 ----
        echo '<div class="nfui-card"><div class="nfui-card-hd">🏷️ 分类 <span class="nfui-meta">'.count($cats).'</span></div><div class="nfui-card-bd">';
        echo '<div class="nfui-cats">';
        foreach ($cats as $c) {
            $cnt = $cat_counts[$c] ?? 0;
            echo '<span class="nfui-cat">'.esc_html($c).'<span class="cnt">'.$cnt.'</span><form method="post" action="'.$action_url.'" style="display:inline">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="c" value="'.esc_attr($c).'"><span class="del" onclick="if(confirm(\'删除分类「'.esc_js($c).'」？该分类下的工具会被一并删除\'))this.parentElement.submit()">×</span></form></span>';
        }
        echo '</div>';
        echo '<form method="post" action="'.$action_url.'" style="display:flex;gap:8px">'.wp_nonce_field('nr','nr',true,false);
        echo '<input type="text" name="nc" class="nfui-input" placeholder="新分类名称" required style="flex:1;max-width:240px">';
        echo '<button type="submit" name="add_c" class="nfui-btn nfui-btn-s">添加</button>';
        echo '</form></div></div>';

        // ---- 工具表 ----
        echo '<div class="nfui-card"><div class="nfui-card-hd">📋 工具 <span class="nfui-meta">'.count($all_tools).' 个，当前显示 '.count($tools).'</span></div><div class="nfui-card-bd">';

        // 筛选条
        echo '<div class="nfui-tbar">';
        echo '<form method="get" style="display:flex;gap:8px;flex:1"><input type="hidden" name="page" value="nf_console"><input type="hidden" name="tab" value="recommend">';
        echo '<select name="cat" onchange="this.form.submit()" class="nfui-select"><option value="">全部分类</option>';
        foreach ($cats as $c) echo '<option value="'.esc_attr($c).'"'.selected($filter_cat,$c,false).'>'.esc_html($c).'</option>';
        echo '</select>';
        echo '<select name="status" onchange="this.form.submit()" class="nfui-select"><option value="">全部状态</option><option value="enabled"'.selected($filter_status,'enabled',false).'>启用</option><option value="disabled"'.selected($filter_status,'disabled',false).'>禁用</option></select>';
        if ($filter_cat || $filter_status) echo '<a href="'.$action_url.'" class="nfui-btn nfui-btn-s nfui-btn-sm">清除筛选</a>';
        echo '</form></div>';

        // 批量操作 form
        echo '<form method="post" action="'.$action_url.'" id="bulk-form">'.wp_nonce_field('nr','nr',true,false);
        echo '<div class="nfui-bulk" id="nfui-bulk-bar"><span>已选 <strong id="nfui-sel-cnt">0</strong> 个</span>';
        echo '<select name="bulk_action" class="nfui-select" required><option value="">批量操作…</option><option value="enable">启用</option><option value="disable">禁用</option><option value="delete">删除</option></select>';
        echo '<button type="submit" class="nfui-btn nfui-btn-s nfui-btn-sm" onclick="return this.form.bulk_action.value!==\'delete\'||confirm(\'确定批量删除选中工具？\')">应用</button>';
        echo '</div>';

        // 表格
        echo '<table class="nfui-tbl"><thead><tr>';
        echo '<th style="width:32px"><input type="checkbox" id="nfui-check-all"></th>';
        echo '<th style="width:60px" title="数字越大越靠前">排序↓</th>';
        echo '<th>工具</th><th style="width:90px">分类</th><th>链接</th><th>简介</th>';
        echo '<th style="width:120px" title="曝光 / 点击 / CTR">📊 数据</th>';
        echo '<th style="width:60px">状态</th><th style="width:100px">操作</th>';
        echo '</tr></thead><tbody>';

        if (empty($tools)) {
            echo '<tr><td colspan="9" style="text-align:center;color:var(--nfui-mut);padding:32px">'.($all_tools ? '当前筛选条件下无结果' : '暂无工具，下方添加第一个').'</td></tr>';
        }
        foreach ($tools as $t) {
            $id = esc_attr($t['id'] ?? '');
            echo '<tr class="'.(empty($t['enabled']) ? 'is-off' : '').'">';
            echo '<td><input type="checkbox" name="tool_ids[]" value="'.$id.'" class="nfui-tool-cb" onchange="nfuiUpdateBulk()"></td>';
            echo '<td><input type="number" value="'.esc_attr($t['priority'] ?? 0).'" class="nfui-input" style="width:54px;padding:3px 6px;text-align:center" min="0" max="1000" onchange="nfuiToggleQuick(\''.$id.'\');document.querySelector(\'#nfui-qe-'.$id.' input[name=priority]\').value=this.value"></td>';
            echo '<td><div class="nfui-tool-nm"><div class="nfui-tool-logo">'.(!empty($t['logo']) ? '<img src="'.esc_url($t['logo']).'" alt="">' : '🔧').'</div><div><span class="nfui-tool-name">'.esc_html($t['name'] ?? '').'</span></div></div></td>';
            echo '<td><span class="nfui-bdg nfui-bdg-info">'.esc_html($t['category'] ?? '').'</span></td>';
            echo '<td><a href="'.esc_url($t['url'] ?? '#').'" target="_blank" class="nfui-tool-url">'.esc_html($t['url'] ?? '').'</a></td>';
            echo '<td class="nfui-tool-desc">'.esc_html($t['description'] ?? '').'</td>';
            // 数据列：曝光 / 点击 / CTR
            $tv = (int)($stats[$t['id'] ?? '']['v'] ?? 0);
            $tc = (int)($stats[$t['id'] ?? '']['c'] ?? 0);
            $ctr = $tv > 0 ? round($tc / $tv * 100, 1) : 0;
            echo '<td><div style="display:flex;flex-direction:column;gap:2px;font-size:11px;line-height:1.4">';
            echo '<span title="曝光"><span style="color:var(--nfui-mut)">👁</span> '.number_format($tv).'</span>';
            echo '<span title="点击 / CTR"><span style="color:var(--nfui-mut)">🖱</span> '.number_format($tc).' <span style="color:var(--nfui-p);font-weight:600">'.$ctr.'%</span></span>';
            echo '</div></td>';
            echo '<td><span class="nfui-bdg '.(!empty($t['enabled']) ? 'nfui-bdg-on' : 'nfui-bdg-off').'">'.(!empty($t['enabled']) ? '启用' : '禁用').'</span></td>';
            echo '<td><div class="nfui-acts">';
            echo '<a href="javascript:void(0)" class="nfui-btn nfui-btn-s nfui-btn-sm" onclick="nfuiToggleQuick(\''.$id.'\')">编辑</a>';
            echo '<form method="post" action="'.$action_url.'" style="display:inline">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="id" value="'.$id.'"><button type="submit" name="tog_t" class="nfui-btn nfui-btn-s nfui-btn-sm">'.(!empty($t['enabled']) ? '禁' : '启').'</button></form>';
            echo '<form method="post" action="'.$action_url.'" style="display:inline" onsubmit="return confirm(\'确定删除「'.esc_js($t['name'] ?? '').'」？\')">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="id" value="'.$id.'"><button type="submit" name="del_t" class="nfui-btn nfui-btn-danger nfui-btn-sm">删</button></form>';
            echo '</div>';
            // 行内编辑表单
            echo '<div id="nfui-qe-'.$id.'" class="nfui-quick-edit"><form method="post" action="'.$action_url.'">'.wp_nonce_field('nr','nr',true,false).'<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="upd_t" value="1">';
            echo '<div class="nfui-quick-edit-grid">';
            echo '<div class="f"><label>分类</label><select name="category">';
            foreach ($cats as $c) echo '<option value="'.esc_attr($c).'"'.selected($t['category'] ?? '', $c, false).'>'.esc_html($c).'</option>';
            echo '</select></div>';
            echo '<div class="f"><label>名称</label><input type="text" name="name" value="'.esc_attr($t['name'] ?? '').'" required></div>';
            echo '<div class="f"><label>链接</label><input type="url" name="url" value="'.esc_attr($t['url'] ?? '').'" required></div>';
            echo '<div class="f"><label>Logo URL</label><input type="url" name="logo" value="'.esc_attr($t['logo'] ?? '').'"></div>';
            echo '<div class="f"><label>简介</label><input type="text" name="description" value="'.esc_attr($t['description'] ?? '').'"></div>';
            echo '<div class="f"><label>排序</label><input type="number" name="priority" value="'.esc_attr($t['priority'] ?? 0).'" min="0" max="1000"></div>';
            echo '<div style="display:flex;gap:5px;grid-column:span 6"><button type="submit" class="nfui-btn nfui-btn-p nfui-btn-sm">保存</button><button type="button" class="nfui-btn nfui-btn-s nfui-btn-sm" onclick="nfuiToggleQuick(\''.$id.'\')">取消</button></div>';
            echo '</div></form></div>';
            echo '</td></tr>';
        }
        echo '</tbody></table></form>';

        // 添加表单
        echo '<form method="post" action="'.$action_url.'" class="nfui-add-form">'.wp_nonce_field('nr','nr',true,false);
        echo '<div class="f"><label>分类</label><select name="tc" required class="nfui-select"><option value="">选择…</option>';
        foreach ($cats as $c) echo '<option value="'.esc_attr($c).'">'.esc_html($c).'</option>';
        echo '</select></div>';
        echo '<div class="f"><label>名称</label><input type="text" name="tn" class="nfui-input" placeholder="工具名称" required></div>';
        echo '<div class="f"><label>链接</label><input type="url" name="tu" class="nfui-input" placeholder="https://…" required></div>';
        echo '<div class="f"><label>Logo URL</label><input type="url" name="tl" class="nfui-input" placeholder="图标地址（可选）"></div>';
        echo '<div><label>&nbsp;</label><button type="submit" name="add_t" class="nfui-btn nfui-btn-p" style="width:100%">＋ 添加工具</button></div>';
        echo '</form>';

        echo '</div></div>';
    }
    /** Tab 3: API（迁移自老 api_docs_page，扩展端点速查） */
    private function render_tab_api() {
        // 重置 Key 处理
        if (isset($_POST['reset_key']) && wp_verify_nonce($_POST['nonce'] ?? '', 'nf_settings')) {
            update_option('newsflash_api_key', wp_generate_uuid4());
            echo '<div class="notice notice-success is-dismissible"><p>✅ API Key 已重置</p></div>';
        }
        $key = get_option('newsflash_api_key', '');
        $base = rest_url('newsflash/v1');

        echo '<div class="nfui-card"><div class="nfui-card-hd">🔑 API Key</div><div class="nfui-card-bd">';
        echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">';
        echo '<code class="nfui-key">'.esc_html($key ?: '（未生成）').'</code>';
        echo '<button type="button" class="nfui-btn nfui-btn-s nfui-btn-sm" onclick="nfuiCopy(\''.esc_js($key).'\', this)">📋 复制</button>';
        echo '<form method="post" style="display:inline">'.wp_nonce_field('nf_settings','nonce',true,false);
        echo '<button type="submit" name="reset_key" class="nfui-btn nfui-btn-danger nfui-btn-sm" onclick="return confirm(\'确定重置 API Key？所有使用旧 Key 的客户端将失效\')">🔄 重置</button>';
        echo '</form></div>';
        echo '<p style="color:var(--nfui-mut);font-size:12px;margin-top:12px">请求时在 Header 中携带 <code>X-NewsFlash-Key</code>。</p>';
        echo '</div></div>';

        echo '<div class="nfui-card"><div class="nfui-card-hd">📡 接口速查</div><div class="nfui-card-bd">';
        echo '<table class="nfui-tbl" style="margin:-8px 0"><thead><tr><th style="width:60px">方法</th><th>路径</th><th>用途</th></tr></thead><tbody>';
        echo '<tr><td><span class="nfui-bdg nfui-bdg-info">POST</span></td><td><code>/posts</code></td><td>创建快讯（需 Key）</td></tr>';
        echo '<tr><td><span class="nfui-bdg" style="background:#fef3c7;color:#92400e">GET</span></td><td><code>/posts/{id}</code></td><td>读取快讯</td></tr>';
        echo '<tr><td><span class="nfui-bdg" style="background:#fef3c7;color:#92400e">GET</span></td><td><code>/categories</code></td><td>分类列表</td></tr>';
        echo '<tr><td><span class="nfui-bdg" style="background:#fef3c7;color:#92400e">GET</span></td><td><code>/recommend-tools</code></td><td>推荐位工具列表</td></tr>';
        echo '</tbody></table>';
        echo '</div></div>';

        echo '<div class="nfui-card"><div class="nfui-card-hd">💻 curl 示例</div><div class="nfui-card-bd">';
        $sample = "curl -X POST ".$base."/posts \\\n"
                . "  -H \"Content-Type: application/json\" \\\n"
                . "  -H \"X-NewsFlash-Key: ".$key."\" \\\n"
                . "  -d '{\n"
                . "    \"title\": \"测试快讯\",\n"
                . "    \"content\": \"<p>正文内容</p>\",\n"
                . "    \"category\": [\"AI对话\"],\n"
                . "    \"keywords\": \"AI,GPT\"\n"
                . "  }'";
        echo '<code class="nfui-code">'.esc_html($sample).'</code>';
        echo '<p style="margin-top:8px"><button type="button" class="nfui-btn nfui-btn-s nfui-btn-sm" onclick="nfuiCopy(this.previousElementSibling.previousElementSibling.textContent, this)">📋 复制命令</button></p>';
        echo '</div></div>';
    }
    /** Tab 4: 高级（自定义 CSS / 导入导出 / 重置） */
    private function render_tab_advanced() {
        // 自定义 CSS 单独保存
        if (isset($_POST['save_css']) && wp_verify_nonce($_POST['nonce'] ?? '', 'nf_advanced')) {
            $s = get_option('newsflash_settings', []);
            $s['custom_css'] = wp_strip_all_tags(wp_unslash($_POST['css'] ?? ''));
            update_option('newsflash_settings', $s);
            echo '<div class="notice notice-success is-dismissible"><p>✅ 自定义 CSS 已保存</p></div>';
        }
        // 导出 JSON
        if (isset($_POST['export']) && wp_verify_nonce($_POST['nonce'] ?? '', 'nf_advanced')) {
            $payload = [
                'version'    => NEWSFLASH_VERSION,
                'exported_at'=> current_time('mysql'),
                'recommend_settings'   => get_option('newsflash_recommend_settings', []),
                'recommend_tools'      => get_option('newsflash_recommend_tools', []),
                'recommend_categories' => get_option('newsflash_recommend_categories', []),
                'recommend_stats'      => get_option('newsflash_recommend_stats', []),
                'page_views'           => get_option('newsflash_page_views', []),
            ];
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=newsflash-recommend-'.date('Ymd-His').'.json');
            echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 导入 JSON
        if (isset($_POST['import']) && wp_verify_nonce($_POST['nonce'] ?? '', 'nf_advanced') && !empty($_FILES['import_file']['tmp_name'])) {
            $raw = file_get_contents($_FILES['import_file']['tmp_name']);
            $data = json_decode($raw, true);
            if (is_array($data) && isset($data['recommend_settings'], $data['recommend_tools'], $data['recommend_categories'])) {
                update_option('newsflash_recommend_settings', $data['recommend_settings']);
                update_option('newsflash_recommend_tools', $data['recommend_tools']);
                update_option('newsflash_recommend_categories', $data['recommend_categories']);
                if (isset($data['recommend_stats']) && is_array($data['recommend_stats'])) {
                    update_option('newsflash_recommend_stats', $data['recommend_stats'], false);
                }
                if (isset($data['page_views']) && is_array($data['page_views'])) {
                    update_option('newsflash_page_views', $data['page_views'], false);
                }
                echo '<div class="notice notice-success is-dismissible"><p>✅ 已导入 '.count($data['recommend_tools']).' 个工具、'.count($data['recommend_categories']).' 个分类'.(isset($data['recommend_stats']) ? '、并合并统计数据' : '').'</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ JSON 格式不符（缺少 recommend_settings / recommend_tools / recommend_categories）</p></div>';
            }
        }
        // 清空所有工具
        if (isset($_POST['clear_tools']) && wp_verify_nonce($_POST['nonce'] ?? '', 'nf_advanced')) {
            update_option('newsflash_recommend_tools', []);
            echo '<div class="notice notice-success is-dismissible"><p>✅ 已清空所有推荐位工具</p></div>';
        }
        if (isset($_POST['clear_stats']) && wp_verify_nonce($_POST['nonce'] ?? '', 'nf_advanced')) {
            update_option('newsflash_recommend_stats', [], false);
            update_option('newsflash_page_views', ['single'=>0,'timeline'=>0,'daily'=>0,'category'=>0], false);
            echo '<div class="notice notice-success is-dismissible"><p>✅ 已清空所有页面访问 / 点击统计</p></div>';
        }

        $s = get_option('newsflash_settings', []);

        // ---- 自定义 CSS ----
        echo '<div class="nfui-card"><div class="nfui-card-hd">🎨 自定义 CSS <span class="nfui-meta">前台页面追加样式</span></div><div class="nfui-card-bd">';
        echo '<form method="post" action="'.esc_url(admin_url('admin.php?page=nf_console&tab=advanced')).'">'.wp_nonce_field('nf_advanced','nonce',true,false);
        echo '<textarea name="css" class="nfui-textarea" placeholder="/* 在此输入 CSS，自动注入到所有快讯页面 */&#10;.nf-article-title { color: #1e6fff; }">'.esc_textarea($s['custom_css'] ?? '').'</textarea>';
        echo '<p style="margin:10px 0 0"><button type="submit" name="save_css" class="nfui-btn nfui-btn-p">保存 CSS</button></p>';
        echo '</form></div></div>';

        // ---- 导入 / 导出 ----
        echo '<div class="nfui-card"><div class="nfui-card-hd">💾 推荐位备份 <span class="nfui-meta">配置 + 工具 + 分类 一并打包</span></div><div class="nfui-card-bd">';
        echo '<div class="nfui-grid-2">';
        // 导出
        echo '<form method="post"><h4 style="margin:0 0 8px;font-size:13px">导出 JSON</h4>';
        echo '<p style="color:var(--nfui-mut);font-size:12px;margin:0 0 10px">下载当前推荐位完整配置（settings + tools + categories），用于备份或迁移到其他站点。</p>';
        echo wp_nonce_field('nf_advanced','nonce',true,false);
        echo '<button type="submit" name="export" class="nfui-btn nfui-btn-s">⬇️ 下载 .json</button>';
        echo '</form>';
        // 导入
        echo '<form method="post" enctype="multipart/form-data"><h4 style="margin:0 0 8px;font-size:13px">导入 JSON</h4>';
        echo '<p style="color:var(--nfui-mut);font-size:12px;margin:0 0 10px"><strong>会覆盖</strong>当前所有推荐位配置 / 工具 / 分类，请先导出备份。</p>';
        echo wp_nonce_field('nf_advanced','nonce',true,false);
        echo '<input type="file" name="import_file" accept=".json,application/json" required style="margin-bottom:8px;display:block">';
        echo '<button type="submit" name="import" class="nfui-btn nfui-btn-s" onclick="return confirm(\'导入会覆盖当前所有推荐位数据，确定继续？\')">⬆️ 上传并导入</button>';
        echo '</form>';
        echo '</div></div></div>';

        // ---- 危险操作 ----
        echo '<div class="nfui-card" style="border-color:#fecaca"><div class="nfui-card-hd" style="background:linear-gradient(to bottom,#fef2f2,#fee2e2);color:#991b1b">⚠️ 危险操作</div><div class="nfui-card-bd">';
        echo '<form method="post" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-bottom:14px;border-bottom:1px dashed var(--nfui-brd-soft);margin-bottom:14px">'.wp_nonce_field('nf_advanced','nonce',true,false);
        echo '<div style="flex:1;min-width:200px"><strong>清空所有推荐位工具</strong><p style="color:var(--nfui-mut);font-size:12px;margin:2px 0 0">设置和分类保留，仅删除工具数据。不可恢复。</p></div>';
        echo '<button type="submit" name="clear_tools" class="nfui-btn nfui-btn-danger" onclick="return confirm(\'确定清空所有推荐位工具？此操作不可恢复！\')">清空工具</button>';
        echo '</form>';
        // 清空统计
        $stats_total = array_sum(array_map(function($x){ return (int)($x['v']??0)+(int)($x['c']??0); }, get_option('newsflash_recommend_stats', [])));
        echo '<form method="post" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">'.wp_nonce_field('nf_advanced','nonce',true,false);
        echo '<div style="flex:1;min-width:200px"><strong>清空曝光 / 点击统计</strong><p style="color:var(--nfui-mut);font-size:12px;margin:2px 0 0">当前累计 '.number_format($stats_total).' 个事件。重置后从 0 重新累计。工具本身不受影响。</p></div>';
        echo '<button type="submit" name="clear_stats" class="nfui-btn nfui-btn-danger" onclick="return confirm(\'确定清空所有曝光 / 点击统计？\')">重置统计</button>';
        echo '</form>';
        echo '</div></div>';
    }


    public function handle_recommend_form() {
        if (!isset($_POST['nr']) || !wp_verify_nonce($_POST['nr'], 'nr')) return;
        if (!current_user_can('manage_options')) return;

        // 批量操作（v1.10.2 UI 还在但 handler 缺失）
        if (!empty($_POST['bulk_action']) && !empty($_POST['tool_ids']) && is_array($_POST['tool_ids'])) {
            $action = sanitize_text_field($_POST['bulk_action']);
            $ids = array_map('sanitize_text_field', $_POST['tool_ids']);
            $tools = get_option('newsflash_recommend_tools', []);
            if ($action === 'delete') {
                $tools = array_values(array_filter($tools, function($t) use ($ids) {
                    return !in_array($t['id'] ?? '', $ids, true);
                }));
                update_option('newsflash_recommend_tools', $tools);
                wp_safe_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=8')); exit;
            }
            if ($action === 'enable' || $action === 'disable') {
                foreach ($tools as &$t) {
                    if (in_array($t['id'] ?? '', $ids, true)) {
                        $t['enabled'] = ($action === 'enable');
                    }
                }
                unset($t);
                update_option('newsflash_recommend_tools', $tools);
                $ok = ($action === 'enable') ? '9' : '10';
                wp_safe_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=' . $ok)); exit;
            }
        }

        if (isset($_POST['save_set'])) {
            update_option('newsflash_recommend_settings', [
                'enabled'=>!empty($_POST['en']),
                'title'=>sanitize_text_field($_POST['title']?:'热门AI工具推荐'),
                'per_row'=>max(4,min(8,intval($_POST['pr']?:6))),
                'show_description'=>!empty($_POST['sd']),
                'show_logo'=>!empty($_POST['sl']),
                'show_cta'=>!empty($_POST['sc']),
                'cta_text'=>sanitize_text_field($_POST['ct']?:'访问'),
                'card_style'=>sanitize_text_field($_POST['cs']?:'card'),
                'card_bg'=>sanitize_hex_color($_POST['bg']?:'#ffffff'),
                'position'=>sanitize_text_field($_POST['pos']??'bottom'),
                // 页面类型开关
                'show_single'=>!empty($_POST['show_single']),
                'show_timeline'=>!empty($_POST['show_timeline']),
                'show_daily'=>!empty($_POST['show_daily']),
                'show_category'=>!empty($_POST['show_category']),
                // 页面类型每行卡片数
                'per_row_single'=>max(4,min(8,intval($_POST['per_row_single']??6))),
                'per_row_timeline'=>max(4,min(8,intval($_POST['per_row_timeline']??6))),
                'per_row_daily'=>max(4,min(8,intval($_POST['per_row_daily']??6))),
                'per_row_category'=>max(4,min(8,intval($_POST['per_row_category']??6))),
            ]);
            wp_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=1')); exit;
        }
        if (isset($_POST['add_c'])) { $cats = get_option('newsflash_recommend_categories',[]); $n = sanitize_text_field($_POST['nc']); if ($n && !in_array($n,$cats)) { $cats[]=$n; update_option('newsflash_recommend_categories',$cats); } wp_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=2')); exit; }
        if (isset($_POST['del_c'])) { $c = sanitize_text_field($_POST['c']); $cats = get_option('newsflash_recommend_categories',[]); $cats = array_values(array_filter($cats,function($x)use($c){return $x!==$c;})); update_option('newsflash_recommend_categories',$cats); $tools = get_option('newsflash_recommend_tools',[]); $tools = array_values(array_filter($tools,function($t)use($c){return($t['category']??'')!==$c;})); update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=3')); exit; }
        if (isset($_POST['add_t'])) {
            $tools = get_option('newsflash_recommend_tools',[]);
            $max_pri = 0; foreach($tools as $t) { $p = intval($t['priority']??0); if($p>$max_pri) $max_pri=$p; }
            $tools[] = ['id'=>uniqid(),'category'=>sanitize_text_field($_POST['tc']?:''),'name'=>sanitize_text_field($_POST['tn']?:''),'description'=>sanitize_textarea_field($_POST['td']?:''),'url'=>esc_url_raw($_POST['tu']?:''),'logo'=>esc_url_raw($_POST['tl']?:''),'priority'=>$max_pri+10,'enabled'=>true];
            update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=4')); exit;
        }
        if (isset($_POST['del_t'])) { $id=sanitize_text_field($_POST['id']); $tools=get_option('newsflash_recommend_tools',[]); $tools=array_values(array_filter($tools,function($t)use($id){return($t['id']??'')!==$id;})); update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=5')); exit; }
        if (isset($_POST['tog_t'])) { $id=sanitize_text_field($_POST['id']); $tools=get_option('newsflash_recommend_tools',[]); foreach($tools as &$t) { if(($t['id']??'')==$id) $t['enabled']=empty($t['enabled']); } update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=6')); exit; }
        if (isset($_POST['upd_t'])) {
            $id=sanitize_text_field($_POST['id']); $tools=get_option('newsflash_recommend_tools',[]);
            foreach($tools as &$t) { if(($t['id']??'')==$id) {
                $t['category']=sanitize_text_field($_POST['category']??$t['category']); $t['name']=sanitize_text_field($_POST['name']??$t['name']);
                $t['description']=sanitize_textarea_field($_POST['description']??$t['description']); $t['url']=esc_url_raw($_POST['url']??$t['url']);
                $t['logo']=esc_url_raw($_POST['logo']??$t['logo']); $t['priority']=intval($_POST['priority']??($t['priority']??0));
            }}
            update_option('newsflash_recommend_tools',$tools); wp_redirect(admin_url('admin.php?page=nf_console&tab=recommend&ok=7')); exit;
        }
    }
    public function recommend_page() { wp_safe_redirect(admin_url('admin.php?page=nf_console&tab=recommend'), 301); exit; }
    public function settings_page() { wp_safe_redirect(admin_url('admin.php?page=nf_console&tab=display'), 301); exit; }
    public function api_docs_page() { wp_safe_redirect(admin_url('admin.php?page=nf_console&tab=api'), 301); exit; }
    public function preview_page() { echo '<div class="wrap"><h1>预览</h1>'.do_shortcode('[newsflash_timeline count=5]').'</div>'; }
}
function newsflash_plugin() { return NewsFlash_Plugin::instance(); }
newsflash_plugin();
