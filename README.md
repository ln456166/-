# 视频播放地址替换系统 - 开发文档

## 目录

1. [项目概述](#项目概述)
2. [文件结构](#文件结构)
3. [部署说明](#部署说明)
4. [API 接口文档](#api-接口文档)
5. [核心类说明](#核心类说明)
6. [支持的平台](#支持的平台)
7. [配置说明](#配置说明)
8. [使用示例](#使用示例)
9. [缓存机制](#缓存机制)
10. [匹配算法](#匹配算法)
11. [常见问题](#常见问题)

---

## 项目概述

本系统用于将主流视频平台（腾讯视频、优酷、爱奇艺、哔哩哔哩、芒果TV等）的播放地址替换为资源站的播放地址，并支持通过第三方API进行二次解析。

### 主要功能

- ✅ 支持多平台URL解析（腾讯视频、优酷、爱奇艺、哔哩哔哩、芒果TV等）
- ✅ 自动提取视频名称和集数
- ✅ 资源站智能匹配（支持电影、电视剧、动漫、综艺等）
- ✅ 集数精准对应
- ✅ 支持API二次解析
- ✅ 内置缓存机制，提升响应速度
- ✅ 支持JSON和纯文本两种返回格式
- ✅ 支持直接跳转播放

### 技术栈

- **后端语言**：PHP 7.0+
- **依赖扩展**：cURL、mbstring、json
- **前端**：原生 HTML/CSS/JavaScript
- **数据格式**：JSON

---

## 文件结构

```
video-replace/
├── config.php          # 配置文件
├── replace.php         # 入口脚本
├── VideoReplace.php    # 核心类库
├── index.html          # 前端测试页面
├── cache/              # 缓存目录
│   ├── search_*.cache  # 搜索缓存
│   ├── detail_*.cache  # 详情缓存
│   └── parse_*.cache   # 解析缓存
└── README.md           # 开发文档
```

### 文件说明

| 文件名 | 说明 | 重要性 |
|--------|------|--------|
| `config.php` | 配置文件，存储API地址、缓存时间等配置 | ⭐⭐⭐ |
| `replace.php` | 入口脚本，接收HTTP请求并返回结果 | ⭐⭐⭐ |
| `VideoReplace.php` | 核心类库，包含所有业务逻辑 | ⭐⭐⭐ |
| `index.html` | 前端测试页面，可视化测试界面 | ⭐⭐ |
| `cache/` | 缓存目录，存储缓存文件 | ⭐ |

---

## 部署说明

### 环境要求

- PHP 7.0 或更高版本
- 启用 cURL 扩展
- 启用 mbstring 扩展
- 启用 json 扩展
- 缓存目录具有写入权限

### 部署步骤

1. **上传文件**
   将所有PHP文件和HTML文件上传到Web服务器目录。

2. **创建缓存目录**
   ```bash
   mkdir cache
   chmod 755 cache
   ```

3. **修改配置（可选）**
   编辑 `config.php` 文件，根据需要修改配置项。

4. **测试访问**
   - 访问 `index.html` 进行可视化测试
   - 或直接调用API：`replace.php?url=视频地址&parse=1`

### 常见部署问题

**问题：缓存目录不可写**
```
Warning: file_put_contents(cache/xxx.cache): failed to open stream: Permission denied
```
**解决：** 确保 cache 目录有写入权限
```bash
chmod 777 cache
# 或者
chown www-data:www-data cache
```

**问题：cURL 扩展未启用**
**解决：** 编辑 php.ini，取消 curl 扩展的注释
```ini
extension=curl
```

---

## API 接口文档

### 接口地址

```
replace.php
```

### 请求方式

```
GET
```

### 请求参数

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| `url` | string | 否 | - | 视频播放地址（url和name二选一） |
| `name` | string | 否 | - | 视频名称（url和name二选一） |
| `episode` | int | 否 | 1 | 集数（仅当使用name参数时有效） |
| `parse` | int | 否 | 0 | 是否启用API解析：1=启用，0=不启用 |
| `format` | string | 否 | json | 返回格式：json / text |
| `redirect` | int | 否 | 0 | 是否直接跳转：1=跳转，0=不跳转 |

### 返回格式

#### 成功响应（code=200）

```json
{
    "code": 200,
    "msg": "解析成功",
    "url": "https://amumu.jerrytom.vip/...解析后地址",
    "data": {
        "resource_url": "https://vip.dytt-see.com/...资源站地址",
        "original_url": "https://v.qq.com/...原始地址"
    }
}
```

#### 失败响应（code=400）

```json
{
    "code": 400,
    "msg": "解析失败",
    "url": "",
    "data": {
        "resource_url": "",
        "original_url": ""
    }
}
```

### 字段说明

| 字段 | 说明 |
|------|------|
| `code` | 状态码：200=成功，400=失败 |
| `msg` | 消息：解析成功 / 解析失败 |
| `url` | 最终播放地址（解析后地址，或资源站地址） |
| `data.resource_url` | 资源站原始播放地址 |
| `data.original_url` | 用户输入的原始视频地址 |

---

## 核心类说明

### VideoReplace 类

核心业务类，包含视频地址解析、资源搜索、播放地址获取等功能。

#### 公共方法

| 方法名 | 参数 | 返回值 | 说明 |
|--------|------|--------|------|
| `replace()` | `$url`, `$name`, `$episode`, `$parse` | array | 执行地址替换主逻辑 |
| `parseUrl()` | `$url` | array/bool | 解析视频URL，提取名称和集数 |
| `searchVideo()` | `$name` | array/bool | 搜索资源站视频 |
| `getVideoDetail()` | `$vodId` | array/bool | 获取视频详情 |
| `getEpisodeUrl()` | `$video`, `$episode` | string/bool | 获取指定集数的播放地址 |
| `getEpisodeName()` | `$video`, `$episode` | string | 获取指定集数的名称 |
| `parsePlayUrl()` | `$playUrl` | array | 解析播放地址（调用第三方API） |
| `getSupportedPlatforms()` | 无 | array | 获取支持的平台列表 |

#### 私有方法分类

**URL解析相关：**
- `parseTencent()` - 解析腾讯视频URL
- `parseYouku()` - 解析优酷URL
- `parseIqiyi()` - 解析爱奇艺URL
- `parseBilibili()` - 解析哔哩哔哩URL
- `parseMgtv()` - 解析芒果TVURL
- `parseByHtml()` - 通用HTML解析

**标题处理相关：**
- `cleanTitle()` - 清理标题
- `removeAllSuffixes()` - 移除所有后缀
- `removeSuffix()` - 移除指定后缀
- `mbTrim()` - 多字节安全trim
- `isValidVideoName()` - 验证视频名称有效性

**匹配相关：**
- `findBestMatch()` - 查找最佳匹配
- `calcMatchScore()` - 计算匹配分数
- `normalizeName()` - 名称归一化

**播放列表相关：**
- `parsePlayList()` - 解析播放列表
- `extractEpisodeNumber()` - 提取集数编号

**网络请求相关：**
- `httpGet()` - 通用HTTP GET请求
- `httpGetMobile()` - 移动端HTTP GET请求

**缓存相关：**
- `getCache()` - 获取缓存
- `setCache()` - 设置缓存

---

## 支持的平台

| 平台 | 标识符 | 支持域名 | 解析方式 |
|------|--------|----------|----------|
| 腾讯视频 | `tencent` | `qq.com`, `v.qq.com` | API + HTML |
| 优酷 | `youku` | `youku.com`, `v.youku.com` | API + HTML |
| 爱奇艺 | `iqiyi` | `iqiyi.com`, `m.iqiyi.com` | 移动端HTML |
| 哔哩哔哩 | `bilibili` | `bilibili.com`, `b23.tv` | API + HTML |
| 芒果TV | `mgtv` | `mgtv.com`, `m.mgtv.com` | API + 移动端HTML |
| 搜狐视频 | `sohu` | `sohu.com`, `tv.sohu.com` | HTML |
| PPTV | `pptv` | `pptv.com`, `v.pptv.com` | HTML |
| 乐视视频 | `letv` | `letv.com`, `www.letv.com` | HTML |

### 平台解析说明

#### 腾讯视频
- 通过 `vid` 参数调用官方API获取视频信息
- 支持提取视频标题和集数

#### 优酷
- 通过视频ID调用开放API获取视频信息
- 从URL中提取 `id_xxxxx` 格式的视频ID

#### 爱奇艺
- **特殊处理**：PC端页面使用JS动态渲染，无法直接解析
- 使用移动端页面（m.iqiyi.com）进行解析
- 模拟iPhone Safari浏览器User-Agent
- 从meta标签、title、script变量中提取信息

#### 哔哩哔哩
- 普通视频：通过 `bvid` 或 `aid` 调用API
- 番剧：通过 `epid` 调用PGC API
- 支持分P视频

#### 芒果TV
- 优先使用移动端页面解析
- 支持通过 `video_id` 调用API
- 支持提取集数/期数信息

---

## 配置说明

配置文件位于 `config.php`，返回一个配置数组。

### 配置项说明

```php
return [
    // 资源站API地址
    'resource_api' => 'http://caiji.dyttzyapi.com/api.php/provide/vod/from/dyttm3u8/at/json',
    
    // 搜索缓存时间（秒）
    'cache_time' => 3600,
    
    // 缓存目录
    'cache_dir' => __DIR__ . '/cache',
    
    // 解析API配置
    'parse_api' => [
        // 解析API地址
        'url' => 'http://amumu.jerrytom.vip/api/',
        // API密钥
        'key' => 'nwIBFGntBSN8aCLkJM',
        // 缓存时间（秒）
        'cache_time' => 7200,
        // 请求超时时间（秒）
        'timeout' => 15,
    ],
];
```

### 配置项详解

| 配置项 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| `resource_api` | string | - | 资源站采集API地址，JSON格式 |
| `cache_time` | int | 3600 | 搜索和详情缓存时间，单位秒 |
| `cache_dir` | string | - | 缓存文件存储目录 |
| `parse_api.url` | string | - | 第三方解析API地址 |
| `parse_api.key` | string | - | 解析API密钥 |
| `parse_api.cache_time` | int | 7200 | 解析结果缓存时间 |
| `parse_api.timeout` | int | 15 | 解析API请求超时时间 |

---

## 使用示例

### 示例1：通过URL替换（腾讯视频）

**请求：**
```
replace.php?url=https://v.qq.com/x/cover/mzc002004tg5y8n/b0036ko87cz.html&parse=1
```

**响应：**
```json
{
    "code": 200,
    "msg": "解析成功",
    "url": "https://amumu.jerrytom.vip/Amumu/m3u8.php?vkey=...",
    "data": {
        "resource_url": "https://vip.dytt-see.com/20260610/.../index.m3u8",
        "original_url": "https://v.qq.com/x/cover/mzc002004tg5y8n/b0036ko87cz.html"
    }
}
```

### 示例2：通过名称搜索

**请求：**
```
replace.php?name=斗罗大陆&episode=1&parse=1
```

**响应：**
```json
{
    "code": 200,
    "msg": "解析成功",
    "url": "https://amumu.jerrytom.vip/Amumu/m3u8.php?vkey=...",
    "data": {
        "resource_url": "https://vip.dytt-see.com/.../index.m3u8",
        "original_url": ""
    }
}
```

### 示例3：直接跳转播放

**请求：**
```
replace.php?url=视频地址&parse=1&redirect=1
```

**效果：** 浏览器直接跳转到解析后的播放地址

### 示例4：纯文本格式返回

**请求：**
```
replace.php?url=视频地址&format=text
```

**响应：**
```
https://vip.dytt-see.com/.../index.m3u8
```

### 示例5：不启用解析

**请求：**
```
replace.php?url=视频地址&parse=0
```

**响应：**
```json
{
    "code": 200,
    "msg": "解析成功",
    "url": "https://vip.dytt-see.com/.../index.m3u8",
    "data": {
        "resource_url": "https://vip.dytt-see.com/.../index.m3u8",
        "original_url": "..."
    }
}
```

---

## 缓存机制

系统采用文件缓存机制，提升响应速度，减少对外部API的请求。

### 缓存类型

| 缓存类型 | 前缀 | 缓存时间 | 说明 |
|----------|------|----------|------|
| 搜索缓存 | `search_` | 3600秒 | 按视频名称搜索的结果 |
| 详情缓存 | `detail_` | 3600秒 | 视频详情（含播放列表） |
| 解析缓存 | `parse_` | 7200秒 | 第三方API解析结果 |

### 缓存文件格式

缓存文件使用PHP序列化格式存储：

```php
[
    'expire' => 1234567890,  // 过期时间戳
    'content' => ...          // 缓存内容
]
```

### 缓存清理

缓存文件会在读取时自动检查是否过期，过期则自动删除。

手动清理缓存：
```bash
rm -rf cache/*.cache
```

### 缓存key生成规则

| 类型 | 生成规则 | 示例 |
|------|----------|------|
| 搜索缓存 | `search_` + md5(视频名称) | `search_e10adc3949ba59abbe56e057f20f883e` |
| 详情缓存 | `detail_` + vod_id | `detail_2354` |
| 解析缓存 | `parse_` + md5(播放地址) | `parse_4d775b06ee56af955c7efe04346e764a` |

---

## 匹配算法

系统采用多层级匹配算法，确保视频名称匹配的准确性。

### 匹配评分规则

| 匹配情况 | 分数 | 说明 |
|----------|------|------|
| 完全一致 | 200 | 搜索名称与目标名称完全相同 |
| 大小写不同 | 195 | 仅大小写不同 |
| 开头匹配（短→长） | 150+ | 搜索名称是目标名称的前缀 |
| 开头匹配（长→短） | 140+ | 目标名称是搜索名称的前缀 |
| 包含匹配（短→长） | 100+ | 搜索名称在目标名称中间 |
| 包含匹配（长→短） | 85 | 目标名称在搜索名称中间 |
| 基名相同+数字 | 180 | 如"斗罗大陆2"和"斗罗大陆2" |
| 基名相同+不同数字 | 160 | 如"斗罗大陆1"和"斗罗大陆2" |
| 基名相同+无数字 | 140 | 如"斗罗大陆"和"斗罗大陆2" |
| 字符共现F1 | 0-100 | 基于字符重合度计算 |

### 匹配阈值

- 最低匹配分数：**25分**
- 低于25分的结果会被丢弃

### 名称归一化

在计算匹配分数前，会对名称进行归一化处理：
- 去除所有空格、符号、括号
- 去除集数信息（第X集/期/话）
- 去除末尾数字

### 附加加分

- 演员匹配：+5分/人
- 导演匹配：+3分/人

### 多维度匹配

系统会尝试以下多种匹配方式，取最高分数：
1. 原始名称 vs 原始名称
2. 归一化名称 vs 归一化名称
3. 原始名称 vs 别名（vod_sub）
4. 归一化名称 vs 归一化别名

---

## 常见问题

### Q1: 为什么有些视频解析失败？

**可能原因：**
1. 资源站没有该视频的资源
2. 视频名称匹配失败（名称差异太大）
3. 网络请求失败（超时、被屏蔽等）

**解决方法：**
1. 确认资源站是否有该视频
2. 尝试使用 `name` 参数手动指定视频名称
3. 检查服务器网络连接

### Q2: 爱奇艺视频解析失败怎么办？

**原因：** 爱奇艺PC端页面使用JavaScript动态渲染，PHP无法直接解析。

**解决方法：**
- 系统已自动切换到爱奇艺移动端页面（m.iqiyi.com）解析
- 如果仍失败，尝试使用 `name` 参数手动搜索

### Q3: 集数对应不正确怎么办？

**可能原因：**
1. 资源站的集数编号与平台不一致
2. 视频名称匹配错误

**解决方法：**
1. 使用 `episode` 参数手动指定集数
2. 确认视频名称匹配是否正确

### Q4: 电影类型的视频如何处理？

**处理方式：**
- 电影在资源站中通常没有集数编号（如"HD国语"、"蓝光"等）
- 系统检测到所有集数编号都为0时，默认返回第一个播放地址
- 不影响电视剧、动漫等有集数的视频

### Q5: 如何提高匹配准确率？

**建议：**
1. 使用更准确的视频名称
2. 避免使用简称或别名
3. 对于有多季的视频，带上季数（如"斗罗大陆2"）

### Q6: 缓存时间可以修改吗？

可以，编辑 `config.php` 文件：
```php
'cache_time' => 3600,      // 搜索/详情缓存时间
'parse_api' => [
    'cache_time' => 7200,  // 解析缓存时间
],
```

### Q7: 支持哪些返回格式？

目前支持两种格式：
- **JSON**（默认）：结构化数据，适合程序调用
- **text**：纯文本，仅返回播放地址，适合简单场景

### Q8: 如何添加新的视频平台支持？

1. 在 `$platforms` 数组中添加新平台配置
2. 实现 `parseXxx()` 方法（可选，有通用HTML解析兜底）
3. 在 `$platformSuffixes` 中添加平台后缀（可选）

---

## 更新日志

### v1.0.0
- 初始版本
- 支持腾讯视频、优酷、爱奇艺、哔哩哔哩、芒果TV
- 支持资源站搜索和匹配
- 支持API二次解析
- 支持缓存机制

### v1.1.0
- 优化爱奇艺解析（使用移动端页面）
- 优化芒果TV解析
- 修复多字节字符处理问题
- 优化电影类型视频的集数处理
- 精简返回数据格式

---

## 技术支持

如有问题或建议，请参考本文档的常见问题部分，或查阅代码注释。
