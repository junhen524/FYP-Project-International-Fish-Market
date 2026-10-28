# International Fish Market

An integrated fishery management and seafood marketplace system developed with PHP, MySQL, HTML, CSS and JavaScript.

The project connects three parts of the seafood supply chain:

- `FishMarket/` — domestic seafood marketplace for browsing products, carts, checkout and orders.
- `International/` — international/export marketplace for users and restaurants, including wallets, recipes, favourites, orders and delivery tracking.
- `PortManagement/` — internal port and fishery management system for users, products, stock, vessels, fishing trips, logistics and analytics.

## Main Features

### FishMarket

- Product catalogue with categories, search and sorting
- Product details and stock tiers (3 kg, 6 kg and 10 kg)
- Shopping cart and checkout flow
- User login, registration, profile and order history
- Marketplace administration pages for products, orders and users

### International

- International seafood catalogue and shopping cart
- Customer and restaurant accounts
- Wallet balance and top-up records
- Export orders and delivery tracking with QR-code support
- Recipes and favourite recipes
- Admin pages for international users, restaurants and orders

### PortManagement

- Port-user login and role-based access
- Product and inventory management
- Stock movements and port-level stock views
- Vessel fleet and fishing-trip management
- Catch sorting and product listing workflow
- Domestic and international logistics/driver management
- Market, international and unified analytics
- Data export endpoints

## Technology Stack

- PHP 8.0 or newer
- MySQL 5.7+ or MariaDB 10.4+
- PDO MySQL
- Apache/XAMPP or the PHP built-in development server
- HTML, CSS and vanilla JavaScript
- PHP sessions for authentication and carts

## Project Structure

```text
.
├── FishMarket/                  # Domestic marketplace
├── International/               # International/export marketplace
├── PortManagement/              # Port, inventory and logistics management
├── fishery_db _emptydata.sql    # Database schema with no demo records
└── fishery_db_fulldata.sql      # Database schema with sample/full data
```

## Requirements

1. PHP 8.0 or newer with the `pdo_mysql` extension enabled.
2. MySQL or MariaDB.
3. A web browser.

The current PHP connection settings expect:

```text
Host:     127.0.0.1
Database: fishery_db
Username: root
Password: empty
```

If your database settings are different, update the PDO connection in the relevant `includes/bootstrap.php` or helper file before starting the applications.

## Database Setup

Create the database first:

```sql
CREATE DATABASE fishery_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then import one of the SQL files from the repository root:

- `fishery_db _emptydata.sql` for a clean database with tables only.
- `fishery_db_fulldata.sql` for a database containing sample/full data.

Example using the MySQL command line:

```bash
mysql -u root -p fishery_db < "fishery_db _emptydata.sql"
```

If the local root account has no password, press Enter when prompted.

## Run Locally with PHP

Open three terminals from the repository root and run one application in each terminal:

```bash
cd FishMarket
php -S localhost:8001 router.php
```

```bash
cd International
php -S localhost:8002 router.php
```

```bash
cd PortManagement
php -S localhost:8003 router.php
```

Open these addresses in your browser:

| Module | URL |
|---|---|
| FishMarket | <http://localhost:8001/> |
| International | <http://localhost:8002/> |
| PortManagement | <http://localhost:8003/> |

Each module includes its own `router.php`. Run the command from the corresponding module directory.

## Run with XAMPP

1. Copy the whole repository into `C:\xampp\htdocs\FisherySystem\`.
2. Start Apache and MySQL in XAMPP.
3. Create the `fishery_db` database and import one of the SQL files.
4. Open:

```text
http://localhost/FisherySystem/FishMarket/
http://localhost/FisherySystem/International/
http://localhost/FisherySystem/PortManagement/
```

For clean URLs in `PortManagement`, enable Apache `mod_rewrite`. More port-management-specific notes are available in `PortManagement/XAMPP_SETUP.txt`.

## Important Notes

- This is a development/academic project. Review authentication, authorization, validation, CSRF protection and database credentials before deploying it publicly.
- Do not commit real passwords, API keys or production database credentials.
- `fishery_db_fulldata.sql` may be large; use the empty schema when you only need the table structure.
- Some pages use external CDN resources and the QR-code image service, so those features may require internet access.
- The three applications share the same MySQL database. Complete the database import before testing login, products, orders or inventory workflows.

## Troubleshooting

### `could not find driver`

Enable `pdo_mysql` in the PHP configuration used by the web server, then restart Apache or the PHP server.

### `Unknown database 'fishery_db'`

Create the database and import one of the SQL files as described above.

### Pages or static files return 404

Start the server from the correct module directory and include its `router.php` argument. With XAMPP, check that the repository path matches the URL and that Apache rewrite support is enabled.

### Products or orders are empty

Import `fishery_db_fulldata.sql`, or add the required records to the empty schema before testing those pages.

## What is a README?

README means “Read Me”. It is usually placed in the root directory of a project, and GitHub automatically displays it on the repository homepage.

A README works like a project manual. It explains:

- What the project does
- Which technologies it uses
- How to install and run it
- How to configure the database
- What each folder is responsible for
- How to solve common problems

A README does not directly affect how the program runs, but it helps teachers, teammates, interviewers and other developers understand and use the project quickly. That is why most GitHub projects include one.

## License

No separate license file is currently included in the repository. Unless the project owner adds an open-source license, treat the source code as all-rights-reserved and ask for permission before redistributing it.


---

# 中文版

# 国际鱼市场系统（International Fish Market）

这是一个基于 PHP、MySQL、HTML、CSS 和 JavaScript 开发的渔业管理与海鲜交易系统，连接海鲜供应链中的市场、国际贸易和港口管理环节。

## 项目模块

项目主要包含以下三个模块：

- `FishMarket/`：本地鱼市场，提供产品浏览、购物车、结账和订单功能。
- `International/`：国际鱼市场，面向普通用户和餐厅，提供国际海鲜交易、钱包、食谱、订单及物流追踪功能。
- `PortManagement/`：港口管理后台，负责用户、产品、库存、船只、捕鱼行程、物流和数据分析。

## 主要功能

### FishMarket 本地鱼市场

- 产品目录、分类、搜索和排序
- 产品详情和库存规格（3 kg、6 kg、10 kg）
- 购物车和结账流程
- 用户登录、注册、个人资料和订单记录
- 产品、订单和用户管理页面

### International 国际鱼市场

- 国际海鲜产品目录和购物车
- 普通用户和餐厅账号
- 钱包余额和充值记录
- 国际订单和物流追踪
- QR Code 配送追踪
- 食谱和收藏食谱
- 国际用户、餐厅和订单管理

### PortManagement 港口管理系统

- 港口用户登录和角色权限
- 产品和库存管理
- 库存变动记录
- 港口库存查看
- 船队和捕鱼行程管理
- 渔获分类和产品上架流程
- 本地及国际司机、物流管理
- 市场、国际和综合数据分析
- 数据导出接口

## 使用技术

- PHP 8.0 或更高版本
- MySQL 5.7+ 或 MariaDB 10.4+
- PDO MySQL
- Apache/XAMPP 或 PHP 内置开发服务器
- HTML、CSS、原生 JavaScript
- PHP Session（用于登录状态和购物车）

## 项目结构

```text
.
├── FishMarket/                  # 本地鱼市场
├── International/               # 国际鱼市场
├── PortManagement/              # 港口、库存和物流管理
├── fishery_db _emptydata.sql    # 只有数据库表结构的 SQL 文件
└── fishery_db_fulldata.sql      # 包含示例/完整数据的 SQL 文件
```

## 环境要求

1. PHP 8.0 或更高版本，并启用 `pdo_mysql` 扩展。
2. MySQL 或 MariaDB 数据库。
3. 浏览器。

项目目前默认使用以下数据库配置：

```text
Host:     127.0.0.1
Database: fishery_db
Username: root
Password: 空密码
```

如果你的 MySQL 用户名、密码或数据库地址不同，请修改对应的 `includes/bootstrap.php` 或相关 helper 文件中的 PDO 连接配置。

## 数据库设置

首先创建数据库：

```sql
CREATE DATABASE fishery_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

然后从项目根目录选择一个 SQL 文件导入：

- `fishery_db _emptydata.sql`：只创建表结构，适合重新开始。
- `fishery_db_fulldata.sql`：包含示例或完整数据，适合直接测试功能。

使用 MySQL 命令行导入的例子：

```bash
mysql -u root -p fishery_db < "fishery_db _emptydata.sql"
```

如果 root 用户没有密码，出现密码提示时直接按 Enter 即可。

## 使用 PHP 内置服务器运行

请打开三个终端，并分别从项目根目录启动三个模块：

```bash
cd FishMarket
php -S localhost:8001 router.php
```

```bash
cd International
php -S localhost:8002 router.php
```

```bash
cd PortManagement
php -S localhost:8003 router.php
```

启动后，可以通过以下地址访问：

| 模块 | 访问地址 |
|---|---|
| FishMarket 本地鱼市场 | <http://localhost:8001/> |
| International 国际鱼市场 | <http://localhost:8002/> |
| PortManagement 港口管理 | <http://localhost:8003/> |

每个模块都有自己的 `router.php`，启动时请在对应的模块文件夹内执行命令。

## 使用 XAMPP 运行

1. 将整个项目复制到：`C:\xampp\htdocs\FisherySystem\`
2. 打开 XAMPP，启动 Apache 和 MySQL。
3. 创建 `fishery_db` 数据库并导入 SQL 文件。
4. 在浏览器打开：

```text
http://localhost/FisherySystem/FishMarket/
http://localhost/FisherySystem/International/
http://localhost/FisherySystem/PortManagement/
```

如果要使用 `PortManagement` 的简洁 URL，请确认 Apache 已启用 `mod_rewrite`。更多港口管理部署说明可以查看 `PortManagement/XAMPP_SETUP.txt`。

## 注意事项

- 这是一个开发/课程项目。正式部署前，应进一步加强身份验证、权限控制、输入验证和 CSRF 防护。
- 不要把真实密码、API Key 或生产数据库账号提交到 GitHub。
- `fishery_db_fulldata.sql` 文件可能比较大；如果只需要表结构，可以使用空数据版本。
- 部分页面使用外部 CDN 和 QR Code 图片服务，因此相关功能可能需要网络连接。
- 三个模块共用同一个 MySQL 数据库。测试登录、产品、订单和库存功能前，应先完成数据库导入。

## 常见问题

### 出现 `could not find driver`

请在 PHP 配置中启用 `pdo_mysql`，然后重启 Apache 或 PHP 服务器。

### 出现 `Unknown database 'fishery_db'`

请先创建名为 `fishery_db` 的数据库，再导入 SQL 文件。

### 页面或静态文件出现 404

请确认你是在正确的模块目录中启动服务器，并且命令中包含对应的 `router.php`。使用 XAMPP 时，请检查项目目录和 URL 是否一致，并确认 Apache rewrite 功能已启用。

### 产品或订单没有数据显示

请导入 `fishery_db_fulldata.sql`，或者在空数据库中手动添加所需数据。

## 什么是 README？

README 的意思是“Read Me”，也就是“请先阅读我”。它通常放在项目的根目录，GitHub 会自动把它显示在项目首页。

README 就像项目的说明书，通常会告诉别人：

- 这个项目是做什么的
- 使用了哪些技术
- 如何安装和运行
- 数据库如何配置
- 每个文件夹负责什么功能
- 遇到常见问题应该怎么解决

README 不会直接影响程序运行，但它能帮助老师、组员、面试官或其他开发者快速理解和使用项目，所以大多数 GitHub 项目都会包含 README。

## License

目前项目中没有发现单独的 License 文件。如果项目作者没有添加开源许可证，请不要默认可以随意复制、修改或重新发布代码。

