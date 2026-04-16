# NewsFlash - AI快讯 WordPress 插件

一款专业的 WordPress 快讯/新闻公告插件，支持多种模板样式、时间线布局、每日资讯盘点、SEO优化，以及 REST API 发布。

## 主要功能

- 📰 **20+ 专业模板**：新浪财经、简约白、暗夜、赛博朋克、毛玻璃、Premium、Bloomberg 等
- 📅 **每日AI资讯盘点**：每日快讯完整汇总页，支持 SEO 优化
- ⬅️⬆️➡️ **三轴时间线布局**：线在左、线居中（双栏交替）、线在右
- 🔌 **REST API 发布**：支持 API Key 认证，可通过 curl 快速发布快讯
- 📱 **响应式设计**：适配桌面和移动端
- 🔍 **SEO 优化**：自定义页面标题、Meta description、关键词
- 🏷️ **分类管理**：支持自定义分类和标签
- 📝 **分页支持**：时间线自动分页

## 安装

1. 下载最新版本的 `newsflash-v*.zip`
2. 在 WordPress 后台上传并安装插件
3. 启用插件后访问「快讯 → 时间线预览」
4. （首次安装需重新保存固定链接以刷新伪静态规则）

## API 发布快讯

```bash
curl -X POST https://your-site.com/wp-json/newsflash/v1/posts \
  -H "Content-Type: application/json" \
  -H "X-NewsFlash-Key: YOUR_API_KEY" \
  -d '{
    "title": "快讯标题",
    "content": "快讯正文内容，支持换行",
    "slug": "url-slug",
    "category": "AI,科技",
    "references": [
      {"title": "参考来源标题", "url": "https://..."}
    ],
    "status": "publish"
  }'
```

## 页面结构

| 页面 | URL | 说明 |
|------|-----|------|
| 快讯列表 | `/newsflash/` | 时间线展示所有快讯 |
| 每日盘点 | `/newsflash/2026-04-17/` | 指定日期的完整快讯 |
| 单篇快讯 | `/newsflash/xxx/` | 单篇快讯详情页 |

## 模板列表

| 模板 | 说明 |
|------|------|
| Sina | 📰 新浪财经风格 |
| Default | ⚪ 简约白 |
| Dark | ⚫ 暗夜护眼 |
| Cyberpunk | 🟣 赛博朋克 |
| Glass | 🔵 毛玻璃效果 |
| Pro | 💼 专业商务 |
| Tech | 🚀 科技感 |
| Bloomberg | 📊 Bloomberg终端 |
| Elegant | ✨ 优雅衬线 |
| Premium | 💎 Premium卡片 |
| Minimal | ⬜ 极简无装饰 |
| Editorial | 📝 杂志编辑 |
| Brutalist | 🧱 粗野主义 |
| Retro | 📜 复古打印 |
| Neon | 🌈 霓虹 |
| Nature | 🌿 自然绿色 |
| Luxury | 👑 奢侈品低调 |
| Startup | 🚀 创业风 |
| Govt | 🏛️ 政务红黄 |
| Magazine | 📰 杂志大刊 |

## 截图

（插件截图待补充）

## 更新日志

### v1.4.7
- 新增每日AI资讯盘点页面（按日期汇总）
- 新增时间线位置设置（左/中/右三选）
- 快讯列表页宽度扩大一倍
- 优化 SEO（支持主题SEO覆盖）
- API 支持 slug 别名、references 来源
- 插件设置页新增页面地址卡片
- 20套模板全面支持

## License

GPL-2.0+
