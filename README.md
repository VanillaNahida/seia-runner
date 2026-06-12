# 圣娅快跑 (Seia Runner)

![:name](https://count.getloli.com/@seia-runner?name=seia-runner&theme=minecraft&padding=6&offset=0&align=top&scale=1&pixelated=1&darkmode=auto)

一个基于 Canvas 的横版跑酷网页游戏，控制《蔚蓝档案》的百合园圣娅躲避障碍物，挑战最高分。

<div align="center">

  [![GitHub license](https://img.shields.io/github/license/VanillaNahida/seia-runner?style=flat-square)](https://github.com/VanillaNahida/seia-runner/blob/main/LICENSE)
  [![GitHub stars](https://img.shields.io/github/stars/VanillaNahida/seia-runner?style=flat-square)](https://github.com/VanillaNahida/seia-runner/stargazers)
  [![GitHub forks](https://img.shields.io/github/forks/VanillaNahida/seia-runner?style=flat-square)](https://github.com/VanillaNahida/seia-runner/network)
  [![GitHub issues](https://img.shields.io/github/issues/VanillaNahida/seia-runner?style=flat-square)](https://github.com/VanillaNahida/seia-runner/issues)
  [![Platform](https://img.shields.io/badge/Platform-Web-brightgreen.svg?style=flat-square)]()
  [![Author](https://img.shields.io/badge/%E4%BD%9C%E8%80%85-VanillaNahida-green)](https://github.com/VanillaNahida)

</div>

# 玩法说明

## 基本操作

- **跳跃**：按空格 / ↑ / 移动端跳跃按钮（长按跳得更高）
- **下蹲**：按 ↓ / 移动端下蹲按钮，躲避高处障碍物
- 落地瞬间按跳跃会被缓冲，极大提升手感

## 障碍物

- **巧乐兹**：需要跳跃躲避
- **雪碧瓶**：需要下蹲躲避

# 技术栈

- **前端**：HTML5 Canvas + CSS3 + 原生 JavaScript
- **后端**：PHP + MySQL
- **敏感词过滤**：基于词库的子串匹配检测
- **IP 归属地**：通过 `api.silveridc.cn` 接口获取

# 部署指南

## 环境要求

- PHP 7.4+
- MySQL 5.7+
- Web 服务器（Apache / Nginx）

## 安装步骤

1. 克隆仓库到 Web 服务器目录

```bash
git clone https://github.com/VanillaNahida/seia-runner.git
```

2. 确保 `api/` 目录可读写，PHP 开启 `mysqli` 和 `mbstring` 扩展

3. 修改 `api/config.php` 中的数据库连接信息

```php
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "your_password";
$DB_NAME = "seia_runner";
```

4. 配置 Web 服务器指向项目根目录

5. 首次访问排行榜页面时，数据库和表会自动创建

6. （可选）编辑 `sensitive_words/weijinci.txt` 自定义敏感词库

## 配置文件说明

| 文件 | 说明 |
|------|------|
| `api/config.php` | 数据库连接配置、敏感词库路径 |
| `sensitive_words/weijinci.txt` | 敏感词词库（逐行存放） |

## 安全配置

`api/.htaccess` 已禁止直接访问 `config.php`、`init.php`、`cheat_code.txt` 等敏感文件。如果使用 Nginx，需手动添加对应的 deny 规则。

# 项目结构

```
seia-runner/
├── api/                    # 后端 PHP
│   ├── .htaccess           # 安全规则
│   ├── config.php          # 数据库配置
│   ├── init.php            # 数据库初始化 + 敏感词检测
│   ├── submit_score.php    # 成绩提交接口
│   └── get_scores.php      # 排行榜查询接口
├── assets/                 # 静态资源
│   ├── audio/              # 音效
│   ├── img/                # 图片素材
│   └── music/              # 背景音乐
├── sensitive_words/        # 敏感词库
├── index.html              # 游戏主页面
├── index.css               # 游戏样式
├── index.js                # 游戏逻辑
├── leaderboard.html        # 排行榜页面
├── leaderboard.css         # 排行榜样式
├── leaderboard.js          # 排行榜逻辑
└── favicon.ico             # 站点图标
```

# 免责声明

本项目仅供学习交流和研究目的，禁止用于商业用途。

# 致谢

- [zhaoyj7](https://github.com/zhaoyj7)：提供IP地址查询和技术支持
- [zoujingli](https://github.com/zoujingli/ip2region)：IP地址归属地查询API支持
- [lionsoul2014](https://github.com/lionsoul2014/ip2region)：IP地址归属地查询数据库支持
- [ottomate](https://ottomate.games/zxf/)：原作地址，本文借鉴了完整源代码
# Bug 反馈

如果在使用过程中遇到任何问题，请通过以下方式反馈：

- [GitHub Issues](https://github.com/VanillaNahida/seia-runner/issues)
- 问题反馈 & 交流群：https://xcnahida.cn/contact

# Star History

[![Star History Chart](https://api.star-history.com/svg?repos=VanillaNahida/seia-runner&type=Date)](https://star-history.com/#VanillaNahida/seia-runner&Date)
