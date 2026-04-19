<?php
/**
 * Plugin Name: NewsFlash 快讯插件
 * Version: 1.5.1
 * Description: 新浪财经风格快讯插件，支持20套模板、分页、REST API、SEO优化
 */

if (!defined('ABSPATH')) exit;

define('NEWSFLASH_VERSION', '1.5.1');
define('NEWSFLASH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEWSFLASH_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * 获取面包屑导航 HTML
 */
function newsflash_get_breadcrumb($type = 'single', $args = []) {
    $timeline_url = get_post_type_archive_link('newsflash');
    $html = '<nav class="nf-breadcrumb">';
    
    if ($type === 'single') {
        $post = $args['post'] ?? get_post();
        $post_date = get_the_date('Y-m-d', $post);
        $daily_url = home_url('/newsflash/' . $post_date . '/');
        $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a>';
        $html .= '<span class="nf-breadcrumb-sep">›</span>';
        $html .= '<a href="' . esc_url($daily_url) . '">' . esc_html(get_the_date('Y年m月d日', $post)) . ' 盘点</a>';
        $html .= '<span class="nf-breadcrumb-sep">›</span>';
        $html .= '<span class="nf-breadcrumb-current">' . esc_html(wp_trim_words($post->post_title, 10, '...')) . '</span>';
    } elseif ($type === 'daily') {
        $date_display = $args['date_display'] ?? '';
        $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a>';
        $html .= '<span class="nf-breadcrumb-sep">›</span>';
        $html .= '<span class="nf-breadcrumb-current">' . esc_html($date_display) . ' 盘点</span>';
    } elseif ($type === 'category') {
        $term = $args['term'] ?? get_queried_object();
        $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a>';
        $html .= '<span class="nf-breadcrumb-sep">›</span>';
        $html .= '<span class="nf-breadcrumb-current">' . esc_html($term->name) . '</span>';
    }
    
    $html .= '</nav>';
    return $html;
}

/**
 * 获取日期导航 HTML（用于每日盘点）
 */
function newsflash_get_date_nav($current_date) {
    $timeline_url = get_post_type_archive_link('newsflash');
    $prev_date = date_create($current_date);
    $prev_date->modify('-1 day');
    $prev_url = home_url('/newsflash/' . $prev_date->format('Y-m-d') . '/');
    
    $next_date = date_create($current_date);
    $next_date->modify('+1 day');
    $next_url = home_url('/newsflash/' . $next_date->format('Y-m-d') . '/');
    $today_str = date('Y-m-d');
    
    $html = '<nav class="nf-date-nav">';
    $html .= '<a href="' . esc_url($prev_url) . '" class="nf-date-prev">← 前一天</a>';
    $html .= '<a href="' . esc_url($timeline_url) . '" class="nf-date-all">全部快讯</a>';
    if ($next_date->format('Y-m-d') <= $today_str) {
        $html .= '<a href="' . esc_url($next_url) . '" class="nf-date-next">后一天 →</a>';
    }
    $html .= '</nav>';
    return $html;
}

/**
 * 获取每日盘点快捷链接（用于时间线页面）
 */
function newsflash_get_daily_links($limit = 7) {
    global $wpdb;
    $dates = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT DATE(post_date) as d FROM {$wpdb->posts} WHERE post_type='newsflash' AND post_status='publish' ORDER BY post_date DESC LIMIT %d",
        $limit
    ));
    
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

final class NewsFlash_Plugin {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) { self::$instance = new self(); }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'register_cpt'], 1);
        add_action('init', [$this, 'add_rewrite_rules'], 2);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('rest_api_init', [$this, 'register_api']);
        add_action('rest_api_init', [$this, 'register_api']);
        add_action('wp_enqueue_scripts', [$this, 'load_assets']);
        add_filter('template_redirect', [$this, 'load_template'], 1);
        add_shortcode('newsflash_timeline', [$this, 'timeline_shortcode']);
        add_shortcode('newsflash_list', [$this, 'list_shortcode']);
        add_action('admin_menu', [$this, 'create_admin_menu'], 1);
        add_action('add_meta_boxes', [$this, 'add_keywords_metabox']);
        add_action('save_post_newsflash', [$this, 'save_keywords_metabox']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
    
    public function activate() {
        $this->register_cpt();
        flush_rewrite_rules();
        if (get_option('newsflash_api_key') === false) {
            add_option('newsflash_api_key', wp_generate_uuid4());
        }
        if (get_option('newsflash_settings') === false) {
            add_option('newsflash_settings', [
                'article_template' => 'sina',
                'timeline_template' => 'sina',
                'timeline_position' => 'left',
                'show_footer' => true,
                'posts_per_page' => 10,
                'custom_css' => '',
                'custom_html_article' => '',
                'custom_html_timeline' => '',
            ]);
        }
    }
    
    public function register_cpt() {
        register_post_type('newsflash', [
            'label' => '快讯',
            'labels' => ['name' => '快讯', 'singular_name' => '快讯', 'add_new' => '发布快讯', 'add_new_item' => '发布新快讯', 'edit_item' => '编辑快讯', 'new_item' => '新快讯', 'view_item' => '查看快讯', 'search_items' => '搜索快讯', 'not_found' => '没有找到快讯'],
            'public' => true, 'show_ui' => true, 'show_in_menu' => false,
            'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive' => true, 'rewrite' => ['slug' => 'newsflash', 'with_front' => true],
            'show_in_rest' => true, 'rest_base' => 'newsflash',
        ]);
        
        register_taxonomy('newsflash_category', 'newsflash', [
            'label' => '快讯分类', 'labels' => ['name' => '快讯分类'],
            'hierarchical' => true, 'show_ui' => true, 'show_admin_column' => true,
            'show_in_rest' => true, 'public' => true, 'rewrite' => ['slug' => 'newsflash-category'],
        ]);
    }
    
    public function add_rewrite_rules() {
        // 日期快讯页：/newsflash/2026-04-17/
        add_rewrite_rule('newsflash/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$', 'index.php?post_type=newsflash&newsflash_date=$matches[1]', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'newsflash_date';
        return $vars;
    }
    
    public function add_keywords_metabox() {
        add_meta_box('newsflash_keywords', '快讯关键词', [$this, 'render_keywords_metabox'], 'newsflash', 'side', 'default');
    }
    
    public function render_keywords_metabox($post) {
        wp_nonce_field('newsflash_keywords', 'newsflash_keywords_nonce');
        $keywords = get_post_meta($post->ID, 'newsflash_keywords', true);
        ?>
        <style>.newsflash-keywords-box p { margin: 0 0 12px; font-size: 13px; color: #1d2327; }
        .newsflash-keywords-box input { width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px; box-sizing: border-box; }
        .newsflash-keywords-box .description { font-size: 11px; color: #646970; margin-top: 4px; }</style>
        <div class="newsflash-keywords-box">
            <p><strong>SEO 关键词</strong></p>
            <input type="text" id="newsflash_keywords" name="newsflash_keywords" value="<?php echo esc_attr($keywords); ?>" placeholder="关键词1, 关键词2, 关键词3">
            <p class="description">用英文逗号分隔，会输出为 meta keywords</p>
        </div>
        <?php
    }
    
    public function save_keywords_metabox($post_id) {
        if (!isset($_POST['newsflash_keywords_nonce']) || !wp_verify_nonce($_POST['newsflash_keywords_nonce'], 'newsflash_keywords')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (isset($_POST['newsflash_keywords'])) {
            update_post_meta($post_id, 'newsflash_keywords', sanitize_text_field($_POST['newsflash_keywords']));
        }
    }
    
    public function register_api() {
        register_rest_route('newsflash/v1', '/posts', [
            'methods' => 'POST', 'callback' => [$this, 'api_create'],
            'permission_callback' => function() {
                $key = $_SERVER['HTTP_X_NEWSFLASH_KEY'] ?? '';
                return $key === get_option('newsflash_api_key', '');
            }
        ]);
        register_rest_route('newsflash/v1', '/posts/(?P<id>\d+)', [
            'methods' => 'GET', 'callback' => [$this, 'api_get'],
            'permission_callback' => '__return_true'
        ]);
        register_rest_route('newsflash/v1', '/categories', [
            'methods' => 'GET', 'callback' => [$this, 'api_categories'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public function api_create($request) {
        $p = $request->get_json_params();
        $title = sanitize_text_field($p['title'] ?? '');
        $content = wp_kses_post($p['content'] ?? '');
        if (!$title || !$content) { return new WP_Error('error', '标题和内容不能为空', ['status' => 400]); }
        
        // 格式化内容：保留换行
        $content = wpautop($content);
        
        // 添加参考来源
        if (!empty($p['references'])) {
            $refs = $p['references'];
            if (is_array($refs)) {
                $ref_html = '<h3>📎 参考来源</h3><ul>';
                foreach ($refs as $ref) {
                    $ref_title = esc_html($ref['title'] ?? '');
                    $ref_url = esc_url($ref['url'] ?? '');
                    if ($ref_title && $ref_url) {
                        $ref_html .= '<li><a href="' . $ref_url . '" target="_blank">' . $ref_title . '</a></li>';
                    }
                }
                $ref_html .= '</ul>';
                $content .= $ref_html;
            }
        }
        
        $post_arr = ['post_type' => 'newsflash', 'post_title' => $title, 'post_content' => $content, 'post_status' => 'publish'];
        
        // 支持 slug 别名
        if (!empty($p['slug'])) {
            $post_arr['post_name'] = sanitize_title($p['slug']);
        }
        
        $post_id = wp_insert_post($post_arr);
        if (is_wp_error($post_id)) { return new WP_Error('error', '创建失败', ['status' => 500]); }
        
        if (!empty($p['category'])) { wp_set_object_terms($post_id, $p['category'], 'newsflash_category'); }
        if (!empty($p['keywords'])) { update_post_meta($post_id, 'newsflash_keywords', sanitize_text_field($p['keywords'])); }
        
        return ['success' => true, 'post_id' => $post_id, 'url' => get_permalink($post_id)];
    }
    
    public function api_get($request) {
        $post = get_post($request['id']);
        if (!$post || $post->post_type !== 'newsflash') { return new WP_Error('not_found', '快讯不存在', ['status' => 404]); }
        return ['id' => $post->ID, 'title' => $post->post_title, 'content' => $post->post_content, 'date' => $post->post_date];
    }
    
    public function api_categories() {
        $terms = get_terms(['taxonomy' => 'newsflash_category', 'hide_empty' => false]);
        return ['categories' => $terms];
    }
    
    public function load_assets() {
        if (is_singular('newsflash') || is_post_type_archive('newsflash') || is_tax('newsflash_category')) {
            wp_enqueue_style('newsflash', NEWSFLASH_PLUGIN_URL . 'assets/css/newsflash.css', [], NEWSFLASH_VERSION);
            
            $s = get_option('newsflash_settings', []);
            
            if (empty($s['show_footer'])) {
                wp_add_inline_style('newsflash', 'footer.main-footer, footer.footer-stick, #footer, .site-footer, .main-footer, .wp-footer { display: none !important; }');
            }
            
            if (!empty($s['custom_css'])) {
                wp_add_inline_style('newsflash', $s['custom_css']);
            }
        }
    }
    
    public function load_template() {
        $settings = get_option('newsflash_settings', []);
        
        // Set posts per page for archive
        add_filter('pre_get_posts', function($query) use ($settings) {
            if ($query->is_post_type_archive('newsflash')) {
                $ppp = isset($settings['posts_per_page']) ? (int)$settings['posts_per_page'] : 10;
                $query->set('posts_per_page', $ppp);
            }
        });
        
        if (is_singular('newsflash')) {
            $post_id = get_queried_object_id();
            $keywords = get_post_meta($post_id, 'newsflash_keywords', true);
            if ($keywords) {
                add_action('wp_head', function() use ($keywords) {
                    echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
                }, 1);
            }
            
            $tpl = $settings['article_template'] ?? 'sina';
            $custom_html = isset($settings['custom_html_article']) ? $settings['custom_html_article'] : '';
            
            add_filter('body_class', function($classes) use ($tpl) {
                $classes[] = 't-' . $tpl;
                $classes[] = 'nf-single';
                return $classes;
            });
            
            if ($tpl === 'custom' && !empty($custom_html)) {
                $this->render_with_footer($custom_html, $settings, 'article', $post_id);
                exit;
            }
            
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/single-' . $tpl . '.php';
            if (!file_exists($file)) { $file = NEWSFLASH_PLUGIN_DIR . 'templates/single-sina.php'; }
            if (file_exists($file)) {
                $this->render_template($file, $settings, 'single', $post_id);
                exit;
            }
        }
        
        // 日期快讯页：/newsflash/2026-04-17/
        $date_str = get_query_var('newsflash_date');
        if (!empty($date_str) && is_string($date_str)) {
            $date_obj = date_create($date_str);
            $date_display = $date_obj ? date_format($date_obj, 'Y年m月d日') : $date_str;
            $seo_title = $date_display . ' AI资讯盘点_今日科技互联网行业动态汇总 - ' . get_bloginfo('name');
            $seo_desc = $date_display . ' 科技、互联网、AI行业资讯动态汇总，涵盖人工智能、技术突破、产品发布等行业热点';
            // 通过 wp_head 直接输出 title，优先级 0 确保在主题之前覆盖
            add_action('wp_head', function() use ($seo_title, $seo_desc) {
                echo '<title>' . esc_html($seo_title) . '</title>' . "\n";
                echo '<meta name="description" content="' . esc_attr($seo_desc) . '">' . "\n";
                // 兼容 RankMath / Yoast
                if (defined('RANK_MATH_VERSION')) {
                    add_filter('rank_math/frontend/title', function() use ($seo_title) { return $seo_title; });
                    add_filter('rank_math/frontend/description', function() use ($seo_desc) { return $seo_desc; });
                }
            }, 0);
            
            $tpl = $settings['timeline_template'] ?? 'sina';
            add_filter('body_class', function($classes) use ($tpl) {
                $classes[] = 't-' . $tpl;
                $classes[] = 'nf-archive';
                $classes[] = 'nf-daily-recap';
                return $classes;
            });
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-daily.php';
            if (file_exists($file)) {
                $this->render_template_with_seo($file, $settings, $seo_title, $seo_desc, 'daily');
                exit;
            }
        }
        
        // 分类归档页：/newsflash-category/ai应用/
        if (is_tax('newsflash_category')) {
            $tpl = $settings['timeline_template'] ?? 'sina';
            
            $term = get_queried_object();
            $seo_title = $term->name . ' - AI快讯分类 - ' . get_bloginfo('name');
            $seo_desc = '浏览' . $term->name . '分类下的所有快讯';
            add_action('wp_head', function() use ($seo_title, $seo_desc) {
                echo '<title>' . esc_html($seo_title) . '</title>' . "\n";
                echo '<meta name="description" content="' . esc_attr($seo_desc) . '">' . "\n";
            }, 0);
            
            add_filter('body_class', function($classes) use ($tpl) {
                $classes[] = 't-' . $tpl;
                $classes[] = 'nf-archive';
                $classes[] = 'nf-category';
                return $classes;
            });
            
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-category.php';
            if (file_exists($file)) {
                $this->render_template_with_seo($file, $settings, $seo_title, $seo_desc, 'category');
                exit;
            }
        }
        
        if (is_post_type_archive('newsflash')) {
            $tpl = $settings['timeline_template'] ?? 'sina';
            $custom_html = isset($settings['custom_html_timeline']) ? $settings['custom_html_timeline'] : '';
            
            $seo_title = 'AI快讯_实时更新_科技互联网行业动态 - ' . get_bloginfo('name');
            $seo_desc = '实时更新AI、人工智能、科技、互联网行业最新资讯与动态';
            add_action('wp_head', function() use ($seo_title, $seo_desc) {
                echo '<title>' . esc_html($seo_title) . '</title>' . "\n";
                echo '<meta name="description" content="' . esc_attr($seo_desc) . '">' . "\n";
                if (defined('RANK_MATH_VERSION')) {
                    add_filter('rank_math/frontend/title', function() use ($seo_title) { return $seo_title; });
                    add_filter('rank_math/frontend/description', function() use ($seo_desc) { return $seo_desc; });
                }
            }, 0);
            
            add_filter('body_class', function($classes) use ($tpl, $settings) {
                $classes[] = 't-' . $tpl;
                $classes[] = 'nf-archive';
                $pos = $settings['timeline_position'] ?? 'left';
                $classes[] = 'nf-position-' . $pos;
                return $classes;
            });
            
            if ($tpl === 'custom' && !empty($custom_html)) {
                $this->render_with_footer($custom_html, $settings, 'timeline', null);
                exit;
            }
            
            $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-' . $tpl . '.php';
            if (!file_exists($file)) { $file = NEWSFLASH_PLUGIN_DIR . 'templates/archive-sina.php'; }
            if (file_exists($file)) {
                $this->render_template_with_seo($file, $settings, $seo_title, $seo_desc);
                exit;
            }
        }
    }
    
    private function render_template($file, $settings, $breadcrumb_type = '', $post_id = null) {
        $show_footer = $settings['show_footer'] ?? true;
        get_header();
        // 输出面包屑
        $this->output_breadcrumb($breadcrumb_type, $post_id);
        include $file;
        if ($show_footer) { get_footer(); }
        exit;
    }
    
    private function render_template_with_seo($file, $settings, $seo_title, $seo_desc = '', $breadcrumb_type = '') {
        $show_footer = $settings['show_footer'] ?? true;
        // 输出缓冲：捕获所有输出，然后用我们的SEO覆盖掉
        ob_start();
        get_header();
        // 输出面包屑
        $this->output_breadcrumb($breadcrumb_type);
        include $file;
        if ($show_footer) { get_footer(); }
        $output = ob_get_clean();
        // 替换 <title> 标签
        if ($seo_title) {
            $output = preg_replace('/<title[^>]*>.*?<\/title>/is', '<title>' . esc_html($seo_title) . '</title>', $output);
        }
        // 替换或追加 meta description
        if ($seo_desc) {
            if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*>/i', $output)) {
                $output = preg_replace('/<meta[^>]*name=["\']description["\'][^>]*>/i', '<meta name="description" content="' . esc_attr($seo_desc) . '">', $output);
            } else {
                // 没有就插入到 <head> 后面
                $output = preg_replace('/(<meta[^>]*charset[^>]*>)/i', '$0' . "\n" . '<meta name="description" content="' . esc_attr($seo_desc) . '">', $output, 1);
            }
        }
        echo $output;
        exit;
    }
    
    private function render_with_footer($content, $settings, $mode, $post_id) {
        $show_footer = $settings['show_footer'] ?? true;
        
        // Replace placeholders for article mode
        if ($mode === 'article' && $post_id) {
            $replacements = [
                '{{title}}' => esc_html(get_the_title($post_id)),
                '{{content}}' => wp_kses_post(get_post_field('post_content', $post_id)),
                '{{time}}' => esc_html(get_the_date('Y-m-d H:i', $post_id)),
                '{{link}}' => esc_url(get_permalink($post_id)),
            ];
            $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        }
        
        get_header();
        // 输出面包屑
        if ($mode === 'article' && $post_id) {
            $this->output_breadcrumb('single', $post_id);
        }
        echo '<div class="nf-custom-' . $mode . '">' . wp_kses_post($content) . '</div>';
        if ($show_footer) { get_footer(); }
        exit;
    }
    
    /**
     * 输出面包屑导航
     */
    private function output_breadcrumb($type = '', $post_id = null) {
        $timeline_url = get_post_type_archive_link('newsflash');
        $html = '';
        
        if ($type === 'single' && $post_id) {
            $post = get_post($post_id);
            $post_date = get_the_date('Y-m-d', $post);
            $post_date_display = get_the_date('Y年m月d日', $post);
            $daily_url = home_url('/newsflash/' . $post_date . '/');
            $html = '<nav class="nf-breadcrumb">';
            $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a>';
            $html .= '<span class="nf-breadcrumb-sep">›</span>';
            $html .= '<a href="' . esc_url($daily_url) . '">' . esc_html($post_date_display) . ' 盘点</a>';
            $html .= '<span class="nf-breadcrumb-sep">›</span>';
            $html .= '<span class="nf-breadcrumb-current">快讯</span>';
            $html .= '</nav>';
        } elseif ($type === 'daily') {
            $date_str = get_query_var('newsflash_date');
            $date_obj = date_create($date_str);
            $date_display = $date_obj ? date_format($date_obj, 'Y年m月d日') : $date_str;
            $html = '<nav class="nf-breadcrumb">';
            $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a>';
            $html .= '<span class="nf-breadcrumb-sep">›</span>';
            $html .= '<span class="nf-breadcrumb-current">' . esc_html($date_display) . ' 盘点</span>';
            $html .= '</nav>';
        } elseif ($type === 'category') {
            $term = get_queried_object();
            $html = '<nav class="nf-breadcrumb">';
            $html .= '<a href="' . esc_url($timeline_url) . '">快讯时间线</a>';
            $html .= '<span class="nf-breadcrumb-sep">›</span>';
            $html .= '<span class="nf-breadcrumb-current">' . esc_html($term->name) . '</span>';
            $html .= '</nav>';
        }
        
        if ($html) {
            echo $html . "\n";
            echo '<style>.nf-breadcrumb{max-width:1600px;margin:0 auto 16px;padding:0 16px;font-size:14px;color:#666}.nf-breadcrumb a{color:#1e6fff;text-decoration:none}.nf-breadcrumb a:hover{text-decoration:underline}.nf-breadcrumb-sep{margin:0 8px;color:#999}.nf-breadcrumb-current{color:#333}</style>' . "\n";
        }
    }
    
    public function timeline_shortcode($atts) {
        $atts = shortcode_atts(['count' => 20, 'category' => '', 'show_excerpt' => 'true', 'paged' => 1], $atts);
        $args = [
            'post_type' => 'newsflash',
            'posts_per_page' => (int)$atts['count'],
            'paged' => max(1, (int)$atts['paged']),
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        if (!empty($atts['category'])) {
            $args['tax_query'] = [['taxonomy' => 'newsflash_category', 'field' => 'slug', 'terms' => $atts['category']]];
        }
        $posts = get_posts($args);
        if (!$posts) { return '<p class="nf-empty">暂无快讯</p>'; }
        
        $html = '<div class="nf-timeline nf-timeline-list">';
        foreach ($posts as $p) {
            $time = esc_html(get_the_date('Y-m-d H:i', $p->ID));
            $title = esc_html($p->post_title);
            $excerpt = $atts['show_excerpt'] === 'true' ? '<p class="nf-excerpt">' . esc_html(wp_trim_words(strip_tags($p->post_content), 40, '...')) . '</p>' : '';
            $link = esc_url(get_permalink($p->ID));
            $html .= sprintf('<div class="nf-item"><span class="nf-time">%s</span><div class="nf-card"><h3>%s</h3>%s<a href="%s" class="nf-link">详情</a></div></div>', $time, $title, $excerpt, $link);
        }
        return $html . '</div>';
    }
    
    public function list_shortcode($atts) {
        $atts = shortcode_atts(['count' => 10], $atts);
        $posts = get_posts(['post_type' => 'newsflash', 'posts_per_page' => (int)$atts['count'], 'orderby' => 'date', 'order' => 'DESC']);
        if (!$posts) { return '<p class="nf-empty">暂无快讯</p>'; }
        $html = '<div class="nf-list nf-list-simple">';
        foreach ($posts as $p) {
            $time = esc_html(get_the_date('m-d H:i', $p->ID));
            $title = esc_html($p->post_title);
            $link = esc_url(get_permalink($p->ID));
            $html .= sprintf('<div class="nf-list-item"><span class="nf-date">%s</span><a href="%s">%s</a></div>', $time, $link, $title);
        }
        return $html . '</div>';
    }
    
    public function create_admin_menu() {
        add_menu_page('NewsFlash', '快讯', 'manage_options', 'edit.php?post_type=newsflash', '', 'dashicons-megaphone', 5);
        add_submenu_page('edit.php?post_type=newsflash', '所有快讯', '所有快讯', 'manage_options', 'edit.php?post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '发布快讯', '发布快讯', 'manage_options', 'post-new.php?post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '快讯分类', '快讯分类', 'manage_options', 'edit-tags.php?taxonomy=newsflash_category&post_type=newsflash', '');
        add_submenu_page('edit.php?post_type=newsflash', '快讯设置', '设置', 'manage_options', 'nf_settings', [$this, 'settings_page']);
        add_submenu_page('edit.php?post_type=newsflash', 'API 文档', 'API 文档', 'manage_options', 'nf_api', [$this, 'api_docs_page']);
        add_submenu_page('edit.php?post_type=newsflash', '时间线预览', '时间线预览', 'manage_options', 'nf_preview', [$this, 'preview_page']);
    }
    
    public function settings_page() {
        if (isset($_POST['save']) && wp_verify_nonce($_POST['nonce'], 'nf_settings')) {
            update_option('newsflash_settings', [
                'article_template' => sanitize_text_field($_POST['article_template'] ?? 'sina'),
                'timeline_template' => sanitize_text_field($_POST['timeline_template'] ?? 'sina'),
                'timeline_position' => sanitize_text_field($_POST['timeline_position'] ?? 'left'),
                'show_footer' => !empty($_POST['show_footer']),
                'posts_per_page' => max(1, (int)($_POST['posts_per_page'] ?? 10)),
                'custom_css' => $_POST['custom_css'] ?? '',
                'custom_html_article' => $_POST['custom_html_article'] ?? '',
                'custom_html_timeline' => $_POST['custom_html_timeline'] ?? '',
            ]);
            echo '<div class="notice notice-success"><p>设置已保存</p></div>';
        }
        
        if (isset($_POST['reset_api_key']) && wp_verify_nonce($_POST['nonce'], 'nf_settings')) {
            update_option('newsflash_api_key', wp_generate_uuid4());
            echo '<div class="notice notice-success"><p>API Key 已重置</p></div>';
        }
        
        $s = get_option('newsflash_settings', []);
        $api_key = get_option('newsflash_api_key', '');
        ?>
        <div class="wrap">
            <h1>快讯设置 <small style="font-size:12px;color:#666;">v<?php echo NEWSFLASH_VERSION; ?></small></h1>
            <style>
            .nf-settings { max-width: 960px; }
            .nf-settings-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 24px; margin-bottom: 24px; }
            .nf-settings-card h2 { margin: 0 0 16px; font-size: 16px; color: #1d2327; border-bottom: 1px solid #eee; padding-bottom: 12px; }
            .nf-settings-row { display: flex; align-items: flex-start; justify-content: space-between; padding: 16px 0; border-bottom: 1px solid #f0f0f1; }
            .nf-settings-row:last-child { border-bottom: none; }
            .nf-settings-row label { font-weight: 600; color: #2c3338; }
            .nf-settings-row .desc { font-size: 12px; color: #646970; margin-top: 4px; }
            .nf-settings-row select, .nf-settings-row input[type="number"] { padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; min-width: 200px; font-size: 14px; }
            .nf-settings-row input[type="number"] { min-width: 80px; }
            .nf-settings-row textarea { width: 100%; min-height: 120px; padding: 12px; border: 1px solid #8c8f94; border-radius: 4px; font-family: monospace; font-size: 13px; box-sizing: border-box; }
            .nf-settings-row .switch { position: relative; width: 50px; height: 26px; }
            .nf-settings-row .switch input { opacity: 0; width: 0; height: 0; }
            .nf-settings-row .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .3s; border-radius: 26px; }
            .nf-settings-row .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
            .nf-settings-row input:checked + .slider { background-color: #2271b1; }
            .nf-settings-row input:checked + .slider:before { transform: translateX(24px); }
            .nf-api-key-box { background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
            .nf-api-key-box code { font-size: 14px; color: #1d2327; word-break: break-all; flex: 1; }
            .nf-api-key-box .button { white-space: nowrap; }
            .nf-template-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 12px; }
            .nf-template-opt { border: 2px solid #e0e0e0; border-radius: 8px; padding: 12px; cursor: pointer; transition: all 0.2s; }
            .nf-template-opt:hover { border-color: #2271b1; background: #f0f6ff; }
            .nf-template-opt.selected { border-color: #2271b1; background: #e6f0ff; }
            .nf-template-opt input { display: none; }
            .nf-template-opt .t-name { font-weight: 600; font-size: 13px; margin-bottom: 4px; }
            .nf-template-opt .t-desc { font-size: 11px; color: #666; }
            </style>
            
            <form method="post" class="nf-settings">
                <?php wp_nonce_field('nf_settings', 'nonce'); ?>
                
                <?php
                $site_url = get_bloginfo('url');
                $timeline_url = $site_url . '/newsflash/';
                $today = date('Y-m-d');
                $daily_url = $site_url . '/newsflash/' . $today . '/';
                ?>
                <div class="nf-settings-card">
                    <h2>🔗 页面地址</h2>
                    <div class="nf-settings-row">
                        <div style="flex:1;">
                            <label>快讯时间线</label>
                            <p class="desc">所有快讯的列表页面</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="text" readonly value="<?php echo esc_url($timeline_url); ?>" style="background:#f0f0f1;border:1px solid #c3c4c7;border-radius:4px;padding:8px 12px;font-size:13px;color:#333;min-width:300px;" onclick="this.select();document.execCommand('copy');alert('已复制');">
                        </div>
                    </div>
                    <div class="nf-settings-row">
                        <div style="flex:1;">
                            <label>每日AI资讯盘点（今日）</label>
                            <p class="desc">当日所有快讯的完整汇总页面，无内容则显示暂无快讯</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="text" readonly value="<?php echo esc_url($daily_url); ?>" style="background:#f0f0f1;border:1px solid #c3c4c7;border-radius:4px;padding:8px 12px;font-size:13px;color:#333;min-width:300px;" onclick="this.select();document.execCommand('copy');alert('已复制');">
                        </div>
                    </div>
                </div>
                
                <div class="nf-settings-card">
                    <h2>📰 模板设置</h2>
                    
                    <div class="nf-settings-row" style="flex-direction:column;align-items:stretch;">
                        <label>文章模板</label>
                        <p class="desc">选择快讯文章页的显示模板</p>
                        <select name="article_template" style="margin-top:8px;">
                            <?php
                            $article_templates = [
                                'sina' => '📰 新浪财经 - 新浪财经7x24风格',
                                'default' => '⚪ 简约白 - 简洁白色风格',
                                'dark' => '⚫ 暗夜 - 深色护眼模式',
                                'cyberpunk' => '🟣 赛博朋克 - 霓虹科幻风格',
                                'glass' => '🔵 毛玻璃 - 磨砂玻璃效果',
                                'pro' => '💼 专业商务 - 高端正统风格',
                                'tech' => '🚀 科技感 - 深色科技风格',
                                'bloomberg' => '📊 Bloomberg - 彭博终端风格',
                                'elegant' => '✨ 优雅 - 衬线字体优雅风',
                                'premium' => '💎 Premium - 高级卡片风格',
                                'minimal' => '⬜ 极简 - 极简无装饰',
                                'editorial' => '📝 编辑风 - 杂志编辑风格',
                                'brutalist' => '🧱 粗野主义 - 粗犷大胆风格',
                                'retro' => '📜复古 - 复古打印风格',
                                'neon' => '🌈 霓虹 - 赛博霓虹风格',
                                'nature' => '🌿 自然 - 绿色自然风格',
                                'luxury' => '👑 奢侈品 - 低调奢华风格',
                                'startup' => '🚀 创业风 - 现代创业风格',
                                'govt' => '🏛️ 政府公务 - 政务红+黄',
                                'magazine' => '📰 杂志风 - 大刊排版风格',
                                'custom' => '🎨 自定义 - 自定义HTML',
                            ];
                            $current_article = $s['article_template'] ?? 'sina';
                            foreach ($article_templates as $k => $v) {
                                echo '<option value="'.esc_attr($k).'" '.selected($current_article, $k, false).'>'.esc_html($v).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="nf-settings-row" style="flex-direction:column;align-items:stretch;">
                        <label>时间线模板</label>
                        <p class="desc">选择快讯列表页的显示模板</p>
                        <select name="timeline_template" style="margin-top:8px;">
                            <?php
                            $timeline_templates = [
                                'sina' => '📰 新浪财经 - 新浪财经7x24风格',
                                'default' => '⚪ 简约白 - 简洁白色风格',
                                'dark' => '⚫ 暗夜 - 深色护眼模式',
                                'cyberpunk' => '🟣 赛博朋克 - 霓虹科幻风格',
                                'glass' => '🔵 毛玻璃 - 磨砂玻璃效果',
                                'pro' => '💼 专业商务 - 高端正统风格',
                                'tech' => '🚀 科技感 - 深色科技风格',
                                'bloomberg' => '📊 Bloomberg - 彭博终端风格',
                                'elegant' => '✨ 优雅 - 衬线字体优雅风',
                                'premium' => '💎 Premium - 高级卡片风格',
                                'minimal' => '⬜ 极简 - 极简无装饰',
                                'editorial' => '📝 编辑风 - 杂志编辑风格',
                                'brutalist' => '🧱 粗野主义 - 粗犷大胆风格',
                                'retro' => '📜复古 - 复古打印风格',
                                'neon' => '🌈 霓虹 - 赛博霓虹风格',
                                'nature' => '🌿 自然 - 绿色自然风格',
                                'luxury' => '👑 奢侈品 - 低调奢华风格',
                                'startup' => '🚀 创业风 - 现代创业风格',
                                'govt' => '🏛️ 政府公务 - 政务红+黄',
                                'magazine' => '📰 杂志风 - 大刊排版风格',
                                'custom' => '🎨 自定义 - 自定义HTML',
                            ];
                            $current_timeline = $s['timeline_template'] ?? 'sina';
                            foreach ($timeline_templates as $k => $v) {
                                echo '<option value="'.esc_attr($k).'" '.selected($current_timeline, $k, false).'>'.esc_html($v).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="nf-settings-row">
                        <div>
                            <label>每页显示数量</label>
                            <p class="desc">列表页每页显示的快讯数量</p>
                        </div>
                        <input type="number" name="posts_per_page" value="<?php echo esc_attr($s['posts_per_page'] ?? 10); ?>" min="1" max="100">
                    </div>
                    
                    <div class="nf-settings-row">
                        <div>
                            <label>时间线位置</label>
                            <p class="desc">选择时间轴的显示位置</p>
                        </div>
                        <select name="timeline_position" style="min-width:150px;">
                            <option value="left" <?php selected($s['timeline_position'] ?? 'left', 'left'); ?>>⬅️ 线在左</option>
                            <option value="center" <?php selected($s['timeline_position'] ?? 'left', 'center'); ?>>⬆️ 线居中</option>
                            <option value="right" <?php selected($s['timeline_position'] ?? 'left', 'right'); ?>>➡️ 线在右</option>
                        </select>
                    </div>
                    
                    <div class="nf-settings-row">
                        <div>
                            <label>显示主题页脚</label>
                            <p class="desc">关闭后通过CSS隐藏Footer区域</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="show_footer" value="1" <?php checked(!empty($s['show_footer'])); ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                
                <div class="nf-settings-card">
                    <h2>🔑 API Key 管理</h2>
                    <div class="nf-api-key-box">
                        <code id="api_key_display"><?php echo esc_html($api_key); ?></code>
                        <input type="hidden" name="api_key" value="<?php echo esc_attr($api_key); ?>">
                        <button type="submit" name="reset_api_key" class="button button-secondary" onclick="return confirm('确定要重置 API Key 吗？旧的 Key 将立即失效！');">🔄 重置 Key</button>
                    </div>
                    <p style="margin:12px 0 0;font-size:12px;color:#646970;">
                        <strong>调用示例：</strong><br>
                        <code>curl -X POST <?php echo esc_url(rest_url('newsflash/v1/posts')); ?> \<br>
                        -H "Content-Type: application/json" \<br>
                        -H "X-NewsFlash-Key: <?php echo esc_html($api_key); ?>" \<br>
                        -d '{"title":"快讯标题","content":"快讯内容","category":"分类slug"}'</code>
                    </p>
                </div>
                
                <div class="nf-settings-card">
                    <h2>🎨 自定义模板（文章页）</h2>
                    <p style="margin:0 0 16px;font-size:13px;color:#646970;">当文章模板选择"自定义"时使用。支持占位符：{{title}}、{{content}}、{{time}}、{{link}}</p>
                    <div class="nf-settings-row" style="flex-direction:column;align-items:stretch;">
                        <textarea name="custom_html_article" placeholder="自定义HTML"><?php echo isset($s['custom_html_article']) ? esc_textarea($s['custom_html_article']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="nf-settings-card">
                    <h2>🎨 自定义模板（时间线页）</h2>
                    <p style="margin:0 0 16px;font-size:13px;color:#646970;">当时间线模板选择"自定义"时使用</p>
                    <div class="nf-settings-row" style="flex-direction:column;align-items:stretch;">
                        <textarea name="custom_html_timeline" placeholder="自定义HTML"><?php echo isset($s['custom_html_timeline']) ? esc_textarea($s['custom_html_timeline']) : ''; ?></textarea>
                    </div>
                </div>
                
                <div class="nf-settings-card">
                    <h2>🎨 自定义 CSS</h2>
                    <div class="nf-settings-row" style="flex-direction:column;align-items:stretch;">
                        <textarea name="custom_css" placeholder="自定义CSS，作用于所有模板"><?php echo isset($s['custom_css']) ? esc_textarea($s['custom_css']) : ''; ?></textarea>
                    </div>
                </div>
                
                <p class="submit"><input type="submit" name="save" class="button button-primary button-large" value="保存设置"></p>
            </form>
        </div>
        <?php
    }
    
    public function api_docs_page() {
        $key = get_option('newsflash_api_key', '');
        $url = rest_url('newsflash/v1/posts');
        ?>
        <div class="wrap">
            <h1>API 文档</h1>
            <style>.nf-api-table { border-collapse: collapse; width: 100%; max-width: 800px; }
            .nf-api-table th, .nf-api-table td { border: 1px solid #e0e0e0; padding: 12px 16px; text-align: left; font-size: 14px; }
            .nf-api-table th { background: #f6f7f7; font-weight: 600; }
            .nf-api-table code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; }
            .nf-api-table .method { font-weight: 700; }
            .nf-api-table .method-post { color: #2271b1; }
            .nf-api-table .method-get { color: #008a20; }
            </style>
            
            <h2>认证方式</h2>
            <p>所有写操作需要通过 <code>X-NewsFlash-Key</code> Header 传递 API Key。</p>
            <p>当前 Key：<code><?php echo esc_html($key); ?></code></p>
            
            <h2>接口列表</h2>
            <table class="nf-api-table">
                <tr><th>方法</th><th>路径</th><th>说明</th></tr>
                <tr><td><span class="method method-post">POST</span></td><td><code><?php echo esc_url($url); ?></code></td><td>创建快讯</td></tr>
                <tr><td><span class="method method-get">GET</span></td><td><code><?php echo esc_url($url); ?>/{id}</code></td><td>获取单条快讯</td></tr>
                <tr><td><span class="method method-get">GET</span></td><td><code><?php echo esc_url(rest_url('newsflash/v1/categories')); ?></code></td><td>获取分类列表</td></tr>
            </table>
            
            <h2>创建快讯示例</h2>
            <pre style="background:#f6f7f7;padding:16px;border-radius:8px;max-width:800px;overflow:auto;">curl -X POST <?php echo esc_url($url); ?> \
  -H "Content-Type: application/json" \
  -H "X-NewsFlash-Key: <?php echo esc_html($key); ?>" \
  -d '{
    "title": "苹果发布新产品",
    "content": "苹果公司今日发布了...",
    "category": "科技",
    "keywords": "苹果,新产品"
  }'</pre>
            
            <h2>响应示例</h2>
            <pre style="background:#f6f7f7;padding:16px;border-radius:8px;max-width:800px;overflow:auto;">{
  "success": true,
  "post_id": 123,
  "url": "https://aiproducthub.cn/newsflash/123/"
}</pre>
        </div>
        <?php
    }
    
    public function preview_page() {
        $s = get_option('newsflash_settings', []);
        $tpl = $s['timeline_template'] ?? 'sina';
        echo '<div class="wrap"><h1>时间线预览</h1>';
        echo '<p>当前模板：<strong>' . esc_html($tpl) . '</strong>（<a href="'.admin_url('admin.php?page=nf_settings').'">去设置</a>）</p>';
        
        $site_url = get_bloginfo('url');
        $timeline_url = $site_url . '/newsflash/';
        
        // 获取所有有快讯的日期
        global $wpdb;
        $dates = $wpdb->get_col("
            SELECT DISTINCT DATE(post_date) as d
            FROM {$wpdb->posts}
            WHERE post_type = 'newsflash' AND post_status = 'publish'
            ORDER BY d DESC
            LIMIT 30
        ");
        
        echo '<div style="background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:24px;margin:20px 0;">';
        echo '<h2 style="margin:0 0 16px;font-size:16px;color:#1d2327;">🔗 页面地址</h2>';
        echo '<div style="margin-bottom:12px;"><strong>📰 快讯时间线：</strong><a href="' . esc_url($timeline_url) . '" target="_blank">' . esc_url($timeline_url) . '</a></div>';
        
        if (!empty($dates)) {
            echo '<h3 style="margin:20px 0 12px;font-size:14px;color:#1d2327;">📅 每日AI资讯盘点（近30天）</h3>';
            echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
            foreach ($dates as $date) {
                $date_obj = date_create($date);
                $date_display = $date_obj ? date_format($date_obj, 'm月d日') : $date;
                $daily_url = $site_url . '/newsflash/' . $date . '/';
                echo '<a href="' . esc_url($daily_url) . '" target="_blank" style="display:inline-block;padding:6px 14px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:20px;font-size:13px;color:#2271b1;text-decoration:none;">' . esc_html($date_display) . ' →</a>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        echo '<h2 style="font-size:16px;color:#1d2327;margin:20px 0 12px;">📋 近期快讯预览（最近5条）</h2>';
        echo do_shortcode('[newsflash_timeline count="5"]');
        echo '</div>';
    }
}

function newsflash_plugin() { return NewsFlash_Plugin::instance(); }
newsflash_plugin();
